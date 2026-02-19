<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Transport;

use InvalidArgumentException;
use PhpSoftBox\Mailer\Email\EmailPayload;
use PhpSoftBox\Mailer\Email\EmailTransportInterface;
use PhpSoftBox\Mailer\Message\EmailMessage;
use PhpSoftBox\Mailer\Support\EmailAddress;
use RuntimeException;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function base64_encode;
use function bin2hex;
use function file_put_contents;
use function function_exists;
use function gmdate;
use function implode;
use function is_dir;
use function is_string;
use function mb_encode_mimeheader;
use function mkdir;
use function preg_match;
use function random_bytes;
use function rtrim;
use function sprintf;
use function str_contains;
use function trim;

final class FileEmailTransport implements EmailTransportInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly ?string $defaultFrom = null,
        private readonly string $filenamePrefix = 'email',
        private readonly ?string $defaultFromName = null,
    ) {
    }

    public function send(EmailMessage $message, EmailPayload $payload): void
    {
        $from       = $this->resolveFrom($payload);
        $fromHeader = $this->resolveFromHeader($payload, $from);

        $recipients = array_values(array_unique(array_filter(array_merge(
            $payload->to,
            $payload->cc,
            $payload->bcc,
        ), static fn (string $value): bool => trim($value) !== '')));

        if ($recipients === []) {
            throw new InvalidArgumentException('File transport requires at least one recipient.');
        }

        $directory = rtrim($this->directory, '/');
        if ($directory === '') {
            throw new InvalidArgumentException('File transport directory must be a non-empty path.');
        }

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Failed to create mail directory: ' . $directory);
        }

        $content = $this->buildMimeMessage($payload, $fromHeader);
        $path    = $this->buildPath($directory);

        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('Failed to write mail file: ' . $path);
        }
    }

    private function buildPath(string $directory): string
    {
        $stamp  = gmdate('Ymd_His');
        $random = bin2hex(random_bytes(6));

        return $directory . '/' . $this->filenamePrefix . '_' . $stamp . '_' . $random . '.eml';
    }

    private function resolveFrom(EmailPayload $payload): string
    {
        $from = $payload->from ?? $this->defaultFrom;
        if (!is_string($from) || trim($from) === '') {
            throw new InvalidArgumentException('File transport requires a "from" address.');
        }

        return trim($from);
    }

    private function resolveFromHeader(EmailPayload $payload, string $from): string
    {
        return EmailAddress::header(
            $from,
            $payload->from === null ? $this->defaultFromName : null,
        );
    }

    private function buildMimeMessage(EmailPayload $payload, string $from): string
    {
        $subject = $this->encodeHeader($payload->subject);
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s O'),
            'From: ' . $from,
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Message-ID: <' . $this->messageId() . '>',
        ];

        if ($payload->replyTo !== null && trim($payload->replyTo) !== '') {
            $headers[] = 'Reply-To: ' . $payload->replyTo;
        }

        if ($payload->to !== []) {
            $headers[] = 'To: ' . $this->joinAddresses($payload->to);
        } else {
            $headers[] = 'To: undisclosed-recipients:;';
        }

        if ($payload->cc !== []) {
            $headers[] = 'Cc: ' . $this->joinAddresses($payload->cc);
        }

        $body = '';
        $text = $payload->text;
        $html = $payload->html;

        if (is_string($text) && $text !== '' && is_string($html) && $html !== '') {
            $boundary  = 'b' . bin2hex(random_bytes(12));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

            $body = implode("\r\n", [
                '--' . $boundary,
                'Content-Type: text/plain; charset=UTF-8',
                '',
                $text,
                '--' . $boundary,
                'Content-Type: text/html; charset=UTF-8',
                '',
                $html,
                '--' . $boundary . '--',
                '',
            ]);
        } elseif (is_string($html) && $html !== '') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $body      = $html;
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $body      = $text ?? '';
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /**
     * @param list<string> $addresses
     */
    private function joinAddresses(array $addresses): string
    {
        $clean = array_values(array_filter(array_map('trim', $addresses), static fn (string $value): bool => $value !== ''));

        return implode(', ', $clean);
    }

    private function encodeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (!str_contains($value, "\n") && !str_contains($value, "\r") && preg_match('/^[\\x20-\\x7E]+$/', $value) === 1) {
            return $value;
        }

        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B');
        }

        return sprintf('=?UTF-8?B?%s?=', base64_encode($value));
    }

    private function messageId(): string
    {
        $domain = 'localhost';

        return bin2hex(random_bytes(12)) . '@' . $domain;
    }
}
