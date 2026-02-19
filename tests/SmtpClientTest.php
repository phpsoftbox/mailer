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

#[CoversClass(SmtpClient::class)]
#[CoversMethod(SmtpClient::class, 'connect')]
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
}
