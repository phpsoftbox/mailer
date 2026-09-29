<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Support;

use InvalidArgumentException;

use function mb_encode_mimeheader;
use function preg_match;
use function str_contains;
use function trim;

final class EmailAddress
{
    /**
     * Mailbox-часть адреса (`user@example.com`) для SMTP envelope: из `Name <user@example.com>` берётся часть в
     * угловых скобках. Переводы строк, пробелы и угловые скобки в mailbox запрещены.
     */
    public static function mailbox(string $address): string
    {
        $address = self::clean($address);
        if ($address === '') {
            throw new InvalidArgumentException('Email address must not be empty.');
        }

        if (preg_match('/<([^<>]+)>/', $address, $matches) === 1) {
            $address = self::clean($matches[1]);
        }

        if ($address === '') {
            throw new InvalidArgumentException('Email mailbox must not be empty.');
        }

        if (preg_match('/[\s<>]/', $address) === 1) {
            throw new InvalidArgumentException('Email mailbox must not contain whitespace or angle brackets.');
        }

        return $address;
    }

    /**
     * Значение адреса для MIME-заголовка (From, To, Cc, Reply-To): `user@example.com` или
     * `Name <user@example.com>`. Отображаемое имя берётся из `$displayName` или из адреса вида
     * `Name <user@example.com>` и кодируется по RFC 2047, если содержит не только простые ASCII-символы.
     */
    public static function header(string $address, ?string $displayName = null): string
    {
        $address = self::clean($address);
        if ($address === '') {
            throw new InvalidArgumentException('Email address must not be empty.');
        }

        $displayName = self::clean($displayName ?? '');
        if ($displayName === '' && preg_match('/^(.*?)\s*<([^<>]+)>$/', $address, $matches) === 1) {
            $displayName = trim($matches[1], " \t\"");
            $address     = $matches[2];
        }

        $mailbox = self::mailbox($address);
        if ($displayName === '') {
            return $mailbox;
        }

        return self::encodeDisplayName($displayName) . ' <' . $mailbox . '>';
    }

    private static function clean(string $value): string
    {
        $value = trim($value);
        if (str_contains($value, "\n") || str_contains($value, "\r")) {
            throw new InvalidArgumentException('Email address header values must not contain line breaks.');
        }

        return $value;
    }

    private static function encodeDisplayName(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9._ -]+$/', $value) === 1) {
            return $value;
        }

        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }
}
