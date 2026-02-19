<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Contracts;

interface SmtpClientInterface
{
    public function connect(): void;

    public function ehlo(string $hostname): void;

    public function startTls(): void;

    public function authLogin(string $username, string $password): void;

    public function mailFrom(string $address): void;

    public function rcptTo(string $address): void;

    public function data(string $data): void;

    public function quit(): void;
}
