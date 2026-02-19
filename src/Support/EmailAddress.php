<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Support;

use InvalidArgumentException;

use function base64_encode;
use function function_exists;
use function mb_encode_mimeheader;
use function preg_match;
use function sprintf;
use function str_contains;
use function trim;

final class EmailAddress
{
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

        return $address;
    }

    public static function header(string $address, ?string $displayName = null): string
    {
        $address = self::clean($address);
        if ($address === '') {
            throw new InvalidArgumentException('Email address must not be empty.');
        }

        $displayName = self::clean($displayName ?? '');
        if ($displayName === '') {
            return $address;
        }

        return self::encodeDisplayName($displayName) . ' <' . self::mailbox($address) . '>';
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

        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B');
        }

        return sprintf('=?UTF-8?B?%s?=', base64_encode($value));
    }
}
