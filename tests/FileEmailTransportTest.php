<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Tests;

use InvalidArgumentException;
use PhpSoftBox\Mailer\Email\EmailPayload;
use PhpSoftBox\Mailer\Message\EmailMessage;
use PhpSoftBox\Mailer\Support\EmailAddress;
use PhpSoftBox\Mailer\Transport\FileEmailTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function glob;
use function is_dir;
use function is_file;
use function is_string;
use function mkdir;
use function unlink;

#[CoversClass(FileEmailTransport::class)]
#[CoversClass(EmailAddress::class)]
#[CoversMethod(FileEmailTransport::class, 'send')]
final class FileEmailTransportTest extends TestCase
{
    protected function setUp(): void
    {
        $this->clearDirectory($this->mailDirectory());
    }

    /**
     * Проверяет, что транспорт сохраняет письмо в файл.
     */
    #[Test]
    public function testSendWritesMailFile(): void
    {
        $directory = $this->mailDirectory();
        $transport = new FileEmailTransport($directory, 'no-reply@example.com');

        $payload = new EmailPayload(
            to: ['user@example.com'],
            cc: [],
            bcc: [],
            from: null,
            replyTo: null,
            subject: 'Subject',
            text: 'Hello',
            html: null,
        );

        $transport->send(EmailMessage::create('Subject'), $payload);

        $files = glob($directory . '/*.eml');
        $this->assertIsArray($files);
        $this->assertCount(1, $files);

        $content = is_string($files[0] ?? null) ? file_get_contents($files[0]) : '';
        $this->assertIsString($content);
        $this->assertStringContainsString('Subject: Subject', $content);
        $this->assertStringContainsString('From: no-reply@example.com', $content);
        $this->assertStringContainsString('To: user@example.com', $content);
        $this->assertStringContainsString('Hello', $content);

    }

    /**
     * Проверяет, что file transport пишет From с отображаемым именем.
     */
    #[Test]
    public function testSendWritesDefaultFromName(): void
    {
        $directory = $this->mailDirectory();
        $transport = new FileEmailTransport($directory, 'robot@email.chgsdev.ru', defaultFromName: 'CHGS WMS');

        $payload = new EmailPayload(
            to: ['user@example.com'],
            cc: [],
            bcc: [],
            from: null,
            replyTo: null,
            subject: 'Subject',
            text: 'Hello',
            html: null,
        );

        $transport->send(EmailMessage::create('Subject'), $payload);

        $files = glob($directory . '/*.eml');
        $this->assertIsArray($files);
        $this->assertCount(1, $files);

        $content = is_string($files[0] ?? null) ? file_get_contents($files[0]) : '';
        $this->assertIsString($content);
        $this->assertStringContainsString('From: CHGS WMS <robot@email.chgsdev.ru>', $content);

    }

    /**
     * Проверяет, что отсутствие from приводит к исключению.
     */
    #[Test]
    public function testSendRequiresFromAddress(): void
    {
        $directory = $this->mailDirectory();
        $transport = new FileEmailTransport($directory);

        $payload = new EmailPayload(
            to: ['user@example.com'],
            cc: [],
            bcc: [],
            from: null,
            replyTo: null,
            subject: 'Subject',
            text: 'Hello',
            html: null,
        );

        $this->expectException(InvalidArgumentException::class);

        $transport->send(EmailMessage::create('Subject'), $payload);
    }

    private function mailDirectory(): string
    {
        return __DIR__ . '/../local/tests/file-email-transport';
    }

    private function clearDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);

            return;
        }

        $files = glob($directory . '/*');
        $this->assertIsArray($files);

        foreach ($files as $file) {
            if (is_string($file) && is_file($file)) {
                unlink($file);
            }
        }
    }
}
