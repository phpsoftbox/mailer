<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Mime;

use PhpSoftBox\Mailer\Email\EmailPayload;
use PhpSoftBox\Mailer\Support\EmailAddress;

use function array_filter;
use function array_map;
use function array_values;
use function bin2hex;
use function gmdate;
use function implode;
use function is_string;
use function mb_encode_mimeheader;
use function preg_match;
use function preg_replace;
use function quoted_printable_encode;
use function random_bytes;
use function str_contains;
use function trim;

/**
 * Сборка MIME-письма для транспортов.
 *
 * Все адреса проходят через {@see EmailAddress}: перевод строки в адресе — исключение (защита от внедрения
 * заголовков). Тема кодируется по RFC 2047, тело — quoted-printable (UTF-8 и строки не длиннее 76 символов,
 * без требования 8BITMIME у SMTP-сервера и лимита 998 байт на строку).
 */
final class MimeMessageBuilder
{
    public function build(EmailPayload $payload, string $from, ?string $fromName = null): string
    {
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s O'),
            'From: ' . EmailAddress::header($from, $fromName),
            'Subject: ' . $this->encodeHeader($payload->subject),
            'MIME-Version: 1.0',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@localhost>',
        ];

        if ($payload->replyTo !== null && trim($payload->replyTo) !== '') {
            $headers[] = 'Reply-To: ' . EmailAddress::header($payload->replyTo);
        }

        $to        = $this->joinAddresses($payload->to);
        $headers[] = 'To: ' . ($to !== '' ? $to : 'undisclosed-recipients:;');

        $cc = $this->joinAddresses($payload->cc);
        if ($cc !== '') {
            $headers[] = 'Cc: ' . $cc;
        }

        $text = $payload->text;
        $html = $payload->html;

        if (is_string($text) && $text !== '' && is_string($html) && $html !== '') {
            $boundary  = 'b' . bin2hex(random_bytes(12));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

            $body = implode("\r\n", [
                '--' . $boundary,
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                $this->encodeBody($text),
                '--' . $boundary,
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                $this->encodeBody($html),
                '--' . $boundary . '--',
                '',
            ]);
        } elseif (is_string($html) && $html !== '') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';
            $body      = $this->encodeBody($html);
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';
            $body      = $this->encodeBody($text ?? '');
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /**
     * @param list<string> $addresses
     */
    private function joinAddresses(array $addresses): string
    {
        $clean = array_values(array_filter($addresses, static fn (string $value): bool => trim($value) !== ''));

        return implode(', ', array_map(static fn (string $address): string => EmailAddress::header($address), $clean));
    }

    private function encodeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (!str_contains($value, "\n") && !str_contains($value, "\r") && preg_match('/^[\x20-\x7E]+$/', $value) === 1) {
            return $value;
        }

        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }

    private function encodeBody(string $body): string
    {
        // Единые CRLF до кодирования: quoted_printable_encode сохраняет CRLF как переводы строк.
        $normalized = preg_replace('/\r\n|\r|\n/', "\r\n", $body) ?? $body;

        return quoted_printable_encode($normalized);
    }
}
