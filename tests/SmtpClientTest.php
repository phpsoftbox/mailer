<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Tests;

use PhpSoftBox\Mailer\Smtp\SmtpClient;
use PhpSoftBox\Mailer\Smtp\SmtpClientConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

use function base64_encode;
use function fclose;
use function fgets;
use function is_resource;
use function proc_close;
use function proc_open;
use function proc_terminate;
use function substr;
use function trim;

use const PHP_BINARY;

#[CoversClass(SmtpClient::class)]
#[CoversMethod(SmtpClient::class, 'connect')]
#[CoversMethod(SmtpClient::class, 'authLogin')]
final class SmtpClientTest extends TestCase
{
    #[Test]
    public function testConnectFailureContainsDiagnosticContext(): void
    {
        $client = new SmtpClient(new SmtpClientConfig(
            host: '127.0.0.1',
            port: 1,
            username: 'user@example.com',
            password: 'secret',
            encryption: 'tls',
            helo: 'example.com',
            timeout: 1,
        ));

        try {
            $client->connect();
            self::fail('Expected SMTP connection failure.');
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();

            $this->assertStringContainsString('SMTP connection failed during socket connect', $message);
            $this->assertStringContainsString('host=127.0.0.1', $message);
            $this->assertStringContainsString('port=1', $message);
            $this->assertStringContainsString('encryption=tls', $message);
            $this->assertStringContainsString('helo=example.com', $message);
            $this->assertStringContainsString('auth=enabled', $message);
            $this->assertStringNotContainsString('secret', $message);
        }
    }

    /**
     * Проверим, что при отказе сервера в AUTH LOGIN ни пароль, ни его base64 не попадают в трассировку исключений:
     * трассировку пишет логгер ошибок.
     *
     * @see SmtpClient::connect()
     * @see SmtpClient::authLogin()
     */
    #[Test]
    public function authFailureTraceHidesCredentials(): void
    {
        // Поднимаем фейковый SMTP-сервер, который отклоняет пароль.
        $process = proc_open([PHP_BINARY, __DIR__ . '/Fixtures/fake-smtp-server.php'], [1 => ['pipe', 'w']], $pipes);
        self::assertTrue(is_resource($process));
        $port = (int) trim((string) fgets($pipes[1]));

        $password = 'VerySecretPassword123';
        $client   = new SmtpClient(new SmtpClientConfig(
            host: '127.0.0.1',
            port: $port,
            username: 'user@example.com',
            password: $password,
            timeout: 5,
        ));

        $traces = '';
        try {
            $client->connect();
            self::fail('Expected SMTP authentication failure.');
        } catch (RuntimeException $exception) {
            // Собираем трассировки всей цепочки исключений.
            for ($current = $exception; $current instanceof Throwable; $current = $current->getPrevious()) {
                $traces .= $current->getMessage() . "\n" . $current->getTraceAsString() . "\n";
            }
        } finally {
            fclose($pipes[1]);
            proc_terminate($process);
            proc_close($process);
        }

        $this->assertStringContainsString('AUTH LOGIN', $traces);
        $this->assertStringContainsString('535', $traces);
        $this->assertStringNotContainsString(substr($password, 0, 10), $traces);
        $this->assertStringNotContainsString(substr(base64_encode($password), 0, 10), $traces);
    }
}
