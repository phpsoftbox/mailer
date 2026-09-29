<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Tests;

use InvalidArgumentException;
use PhpSoftBox\Mailer\Email\EmailPayload;
use PhpSoftBox\Mailer\Message\EmailMessage;
use PhpSoftBox\Mailer\Support\EmailAddress;
use PhpSoftBox\Mailer\Transport\SmtpEmailTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SmtpEmailTransport::class)]
#[CoversClass(EmailAddress::class)]
#[CoversMethod(SmtpEmailTransport::class, 'send')]
final class SmtpEmailTransportTest extends TestCase
{
    /**
     * Проверяет, что отправка вызывает SMTP-клиент и собирает письмо.
     */
    #[Test]
    public function testSendBuildsMessageAndCallsClient(): void
    {
        $client = new FakeSmtpClient();

        $transport = new SmtpEmailTransport($client, 'no-reply@example.com');

        $payload = new EmailPayload(
            to: ['user@example.com'],
            cc: ['cc@example.com'],
            bcc: ['bcc@example.com'],
            from: null,
            replyTo: null,
            subject: 'Subject',
            text: 'Hello',
            html: null,
        );

        $transport->send(EmailMessage::create('Subject'), $payload);

        $this->assertSame(['connect', 'mailFrom:no-reply@example.com', 'rcptTo:user@example.com', 'rcptTo:cc@example.com', 'rcptTo:bcc@example.com', 'data', 'quit'], $client->calls);
        $this->assertNotNull($client->payload);
        $this->assertStringContainsString('Subject: Subject', $client->payload ?? '');
        $this->assertStringContainsString('Hello', $client->payload ?? '');
    }

    /**
     * Проверяет, что отображаемое имя используется только в MIME-заголовке From.
     */
    #[Test]
    public function testSendUsesDefaultFromNameOnlyForHeader(): void
    {
        $client = new FakeSmtpClient();

        $transport = new SmtpEmailTransport($client, 'robot@email.chgsdev.ru', 'CHGS WMS');

        $payload = new EmailPayload(
            to: ['User <user@example.com>'],
            cc: [],
            bcc: [],
            from: null,
            replyTo: null,
            subject: 'Subject',
            text: 'Hello',
            html: null,
        );

        $transport->send(EmailMessage::create('Subject'), $payload);

        $this->assertSame(['connect', 'mailFrom:robot@email.chgsdev.ru', 'rcptTo:user@example.com', 'data', 'quit'], $client->calls);
        $this->assertNotNull($client->payload);
        $this->assertStringContainsString('From: CHGS WMS <robot@email.chgsdev.ru>', $client->payload ?? '');
        $this->assertStringContainsString('To: User <user@example.com>', $client->payload ?? '');
    }

    /**
     * Проверяет, что явный From может содержать отображаемое имя, но SMTP envelope получает только mailbox.
     */
    #[Test]
    public function testSendUsesMailboxFromExplicitDisplayAddress(): void
    {
        $client = new FakeSmtpClient();

        $transport = new SmtpEmailTransport($client, 'robot@email.chgsdev.ru', 'CHGS WMS');

        $payload = new EmailPayload(
            to: ['user@example.com'],
            cc: [],
            bcc: [],
            from: 'Sender Name <sender@example.com>',
            replyTo: null,
            subject: 'Subject',
            text: 'Hello',
            html: null,
        );

        $transport->send(EmailMessage::create('Subject'), $payload);

        $this->assertSame(['connect', 'mailFrom:sender@example.com', 'rcptTo:user@example.com', 'data', 'quit'], $client->calls);
        $this->assertNotNull($client->payload);
        $this->assertStringContainsString('From: Sender Name <sender@example.com>', $client->payload ?? '');
    }

    /**
     * Проверяет, что отсутствие from приводит к исключению.
     */
    #[Test]
    public function testSendRequiresFromAddress(): void
    {
        $client = new FakeSmtpClient();

        $transport = new SmtpEmailTransport($client);

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

    /**
     * Проверим, что при отказе сервера в середине транзакции соединение закрывается (QUIT), а ошибка пробрасывается.
     *
     * @see SmtpEmailTransport::send()
     */
    #[Test]
    public function rejectedTransactionClosesConnection(): void
    {
        $client = new FakeSmtpClient();

        $client->rejectRecipient = 'bad@example.com';

        $transport = new SmtpEmailTransport($client, 'no-reply@example.com');

        try {
            $transport->send(EmailMessage::create('Subject'), $this->payload(['bad@example.com']));
            self::fail('Expected SMTP rejection.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('550', $exception->getMessage());
        }

        $this->assertSame(['connect', 'mailFrom:no-reply@example.com', 'rcptTo:bad@example.com', 'quit'], $client->calls);
    }

    /**
     * Проверим, что недопустимый адрес получателя отклоняется до соединения с SMTP-сервером.
     *
     * @see SmtpEmailTransport::send()
     */
    #[Test]
    public function invalidRecipientIsRejectedBeforeConnect(): void
    {
        $client = new FakeSmtpClient();

        $transport = new SmtpEmailTransport($client, 'no-reply@example.com');

        try {
            $transport->send(EmailMessage::create('Subject'), $this->payload(["user@example.com\r\nBcc: victim@example.com"]));
            self::fail('Expected invalid recipient.');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame([], $client->calls);
    }

    /**
     * @param list<string> $to
     */
    private function payload(array $to): EmailPayload
    {
        return new EmailPayload(
            to: $to,
            cc: [],
            bcc: [],
            from: null,
            replyTo: null,
            subject: 'Subject',
            text: 'Hello',
            html: null,
        );
    }
}
