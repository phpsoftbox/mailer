<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Tests;

use PhpSoftBox\Mailer\Contracts\SmtpClientInterface;

final class FakeSmtpClient implements SmtpClientInterface
{
    /**
     * @var list<string>
     */
    public array $calls = [];

    /**
     * @var list<string>
     */
    public array $rcpt = [];

    public ?string $payload = null;

    public function connect(): void
    {
        $this->calls[] = 'connect';
    }

    public function ehlo(string $hostname): void
    {
        $this->calls[] = 'ehlo:' . $hostname;
    }

    public function startTls(): void
    {
        $this->calls[] = 'startTls';
    }

    public function authLogin(string $username, string $password): void
    {
        $this->calls[] = 'auth:' . $username;
    }

    public function mailFrom(string $address): void
    {
        $this->calls[] = 'mailFrom:' . $address;
    }

    public function rcptTo(string $address): void
    {
        $this->calls[] = 'rcptTo:' . $address;
        $this->rcpt[]  = $address;
    }

    public function data(string $data): void
    {
        $this->calls[] = 'data';
        $this->payload = $data;
    }

    public function quit(): void
    {
        $this->calls[] = 'quit';
    }
}
