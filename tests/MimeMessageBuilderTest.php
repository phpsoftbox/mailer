<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Tests;

use InvalidArgumentException;
use PhpSoftBox\Mailer\Email\EmailPayload;
use PhpSoftBox\Mailer\Mime\MimeMessageBuilder;
use PhpSoftBox\Mailer\Support\EmailAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function explode;
use function mb_decode_mimeheader;
use function preg_match;
use function quoted_printable_decode;
use function str_repeat;
use function strlen;

#[CoversClass(MimeMessageBuilder::class)]
#[CoversClass(EmailAddress::class)]
#[CoversMethod(MimeMessageBuilder::class, 'build')]
#[CoversMethod(EmailAddress::class, 'header')]
#[CoversMethod(EmailAddress::class, 'mailbox')]
final class MimeMessageBuilderTest extends TestCase
{
    /**
     * Проверим, что перевод строки в Reply-To не позволяет внедрить заголовок (например, Bcc).
     *
     * @see MimeMessageBuilder::build()
     * @see EmailAddress::header()
     */
    #[Test]
    public function replyToWithLineBreakIsRejected(): void
    {
        $payload = $this->payload(replyTo: "attacker@example.com\r\nBcc: victim@example.com");

        $this->expectException(InvalidArgumentException::class);

        new MimeMessageBuilder()->build($payload, 'no-reply@example.com');
    }

    /**
     * Проверим, что перевод строки в адресе получателя не позволяет внедрить заголовок.
     *
     * @see MimeMessageBuilder::build()
     * @see EmailAddress::header()
     */
    #[Test]
    public function recipientWithLineBreakIsRejected(): void
    {
        $payload = $this->payload(to: ["user@example.com\nBcc: victim@example.com"]);

        $this->expectException(InvalidArgumentException::class);

        new MimeMessageBuilder()->build($payload, 'no-reply@example.com');
    }

    /**
     * Проверим, что перевод строки в теме кодируется в encoded-word и не создаёт отдельный заголовок.
     *
     * @see MimeMessageBuilder::build()
     */
    #[Test]
    public function subjectWithLineBreakIsEncoded(): void
    {
        $payload = $this->payload(subject: "Hello\r\nBcc: victim@example.com");

        $mime = new MimeMessageBuilder()->build($payload, 'no-reply@example.com');

        // Заголовки — до первой пустой строки; строки Bcc среди них быть не должно.
        [$headers] = explode("\r\n\r\n", $mime, 2);
        $this->assertSame(0, preg_match('/^Bcc:/mi', $headers));
        $this->assertSame(1, preg_match('/^Subject: =\?UTF-8\?B\?/m', $headers));
    }

    /**
     * Проверим, что тело кодируется quoted-printable: длинная строка HTML с кириллицей разбивается на строки
     * не длиннее 76 символов и декодируется обратно без потерь.
     *
     * @see MimeMessageBuilder::build()
     */
    #[Test]
    public function bodyIsQuotedPrintableWithShortLines(): void
    {
        $html    = '<p>' . str_repeat('Длинная строка письма без переносов. ', 100) . '</p>';
        $payload = $this->payload(html: $html);

        $mime = new MimeMessageBuilder()->build($payload, 'no-reply@example.com');

        [$headers, $body] = explode("\r\n\r\n", $mime, 2);
        $this->assertStringContainsString('Content-Transfer-Encoding: quoted-printable', $headers);
        foreach (explode("\r\n", $body) as $line) {
            $this->assertLessThanOrEqual(76, strlen($line));
        }
        $this->assertSame($html, quoted_printable_decode($body));
    }

    /**
     * Проверим, что отображаемое имя с кириллицей в адресе вида `Имя <mailbox>` кодируется по RFC 2047.
     *
     * @see MimeMessageBuilder::build()
     * @see EmailAddress::header()
     */
    #[Test]
    public function nonAsciiDisplayNameIsEncoded(): void
    {
        $payload = $this->payload(to: ['Иван Петров <ivan@example.com>']);

        $mime = new MimeMessageBuilder()->build($payload, 'no-reply@example.com');

        $this->assertSame(1, preg_match('/^To: (=\?UTF-8\?B\?\S+\?=) <ivan@example\.com>\r$/m', $mime, $matches));
        $this->assertSame('Иван Петров', mb_decode_mimeheader($matches[1]));
    }

    /**
     * Проверим, что mailbox с пробелом (попытка передать параметры SMTP-команды) отклоняется.
     *
     * @see EmailAddress::mailbox()
     */
    #[Test]
    public function mailboxWithWhitespaceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EmailAddress::mailbox('<user@example.com NOTIFY=NEVER>');
    }

    /**
     * @param list<string> $to
     */
    private function payload(
        array $to = ['user@example.com'],
        ?string $replyTo = null,
        string $subject = 'Subject',
        ?string $html = null,
    ): EmailPayload {
        return new EmailPayload(
            to: $to,
            cc: [],
            bcc: [],
            from: null,
            replyTo: $replyTo,
            subject: $subject,
            text: $html === null ? 'Hello' : null,
            html: $html,
        );
    }
}
