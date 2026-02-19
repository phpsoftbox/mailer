<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Smtp;

use PhpSoftBox\Mailer\Contracts\SmtpClientInterface;
use RuntimeException;
use Throwable;

use function base64_encode;
use function count;
use function explode;
use function fclose;
use function fgets;
use function fwrite;
use function implode;
use function in_array;
use function is_resource;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function stream_context_create;
use function stream_get_meta_data;
use function stream_set_timeout;
use function stream_socket_client;
use function stream_socket_enable_crypto;
use function strlen;
use function substr;

use const STREAM_CLIENT_CONNECT;
use const STREAM_CRYPTO_METHOD_TLS_CLIENT;

final class SmtpClient implements SmtpClientInterface
{
    /**
     * @var resource|null
     */
    private $stream = null;

    public function __construct(
        private readonly SmtpClientConfig $config,
    ) {
    }

    public function connect(): void
    {
        $phase  = 'socket connect';
        $scheme = '';
        if ($this->config->encryption === 'ssl') {
            $scheme = 'ssl://';
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'      => $this->config->verifyPeer,
                'verify_peer_name' => $this->config->verifyPeerName,
            ],
        ]);

        $remote = $scheme . $this->config->host . ':' . $this->config->port;
        try {
            $stream = @stream_socket_client($remote, $errno, $errstr, $this->config->timeout, STREAM_CLIENT_CONNECT, $context);
            if (!is_resource($stream)) {
                throw new RuntimeException(sprintf('SMTP socket connect failed: %s (%s)', $errstr, (string) $errno));
            }

            $this->stream = $stream;
            stream_set_timeout($this->stream, $this->config->timeout);

            $phase = 'server greeting';
            $this->expect([220]);

            $phase = 'EHLO before STARTTLS';
            $this->ehlo($this->config->helo);

            if ($this->config->encryption === 'tls') {
                $phase = 'STARTTLS';
                $this->startTls();

                $phase = 'EHLO after STARTTLS';
                $this->ehlo($this->config->helo);
            }

            if ($this->config->username !== null && $this->config->username !== '') {
                $phase = 'AUTH LOGIN';
                $this->authLogin($this->config->username, $this->config->password ?? '');
            }
        } catch (Throwable $exception) {
            if (is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->stream = null;

            throw new RuntimeException(sprintf(
                'SMTP connection failed during %s: %s [host=%s port=%d encryption=%s helo=%s auth=%s]',
                $phase,
                $exception->getMessage(),
                $this->config->host,
                $this->config->port,
                $this->config->encryption,
                $this->config->helo,
                $this->config->username !== null && $this->config->username !== '' ? 'enabled' : 'disabled',
            ), previous: $exception);
        }
    }

    public function ehlo(string $hostname): void
    {
        $this->command('EHLO ' . $hostname, [250]);
    }

    public function startTls(): void
    {
        $this->command('STARTTLS', [220]);

        if (!is_resource($this->stream)) {
            throw new RuntimeException('SMTP stream is not connected.');
        }

        $result = stream_socket_enable_crypto($this->stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($result !== true) {
            throw new RuntimeException('Failed to enable TLS for SMTP connection.');
        }
    }

    public function authLogin(string $username, string $password): void
    {
        $this->command('AUTH LOGIN', [334]);
        $this->command(base64_encode($username), [334]);
        $this->command(base64_encode($password), [235]);
    }

    public function mailFrom(string $address): void
    {
        $this->command('MAIL FROM:<' . $address . '>', [250]);
    }

    public function rcptTo(string $address): void
    {
        $this->command('RCPT TO:<' . $address . '>', [250, 251]);
    }

    public function data(string $data): void
    {
        $this->command('DATA', [354]);

        $payload = $this->dotStuff($data);
        $this->write($payload . "\r\n.\r\n");

        $this->expect([250]);
    }

    public function quit(): void
    {
        if ($this->stream === null) {
            return;
        }

        try {
            $this->command('QUIT', [221]);
        } finally {
            if (is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->stream = null;
        }
    }

    /**
     * @param list<int> $expected
     */
    private function command(string $command, array $expected): void
    {
        $this->write($command . "\r\n");
        $this->expect($expected);
    }

    private function write(string $payload): void
    {
        if (!is_resource($this->stream)) {
            throw new RuntimeException('SMTP stream is not connected.');
        }

        $written = 0;
        $length  = strlen($payload);
        while ($written < $length) {
            $chunk = fwrite($this->stream, substr($payload, $written));
            if ($chunk === false || $chunk === 0) {
                throw new RuntimeException('Failed to write SMTP payload.');
            }
            $written += $chunk;
        }
    }

    /**
     * @param list<int> $expected
     */
    private function expect(array $expected): void
    {
        $lines = $this->readResponseLines();
        $last  = $lines[count($lines) - 1] ?? '';

        if (!preg_match('/^(\\d{3})\\s/', $last, $matches)) {
            throw new RuntimeException('Invalid SMTP response: ' . implode(' | ', $lines));
        }

        $code = (int) $matches[1];
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('Unexpected SMTP response: ' . implode(' | ', $lines));
        }
    }

    /**
     * @return list<string>
     */
    private function readResponseLines(): array
    {
        if (!is_resource($this->stream)) {
            throw new RuntimeException('SMTP stream is not connected.');
        }

        $lines = [];
        while (($line = fgets($this->stream)) !== false) {
            $line    = rtrim($line, "\r\n");
            $lines[] = $line;

            if (preg_match('/^\\d{3}\\s/', $line)) {
                break;
            }
        }

        if ($lines === []) {
            $meta = stream_get_meta_data($this->stream);
            if (($meta['timed_out'] ?? false) === true) {
                throw new RuntimeException('SMTP response timed out.');
            }

            throw new RuntimeException('Empty SMTP response.');
        }

        return $lines;
    }

    private function dotStuff(string $payload): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $payload));
        foreach ($lines as $index => $line) {
            $line = rtrim($line, "\r");
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
            $lines[$index] = $line;
        }

        return implode("\r\n", $lines);
    }
}
