<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Smtp;

final readonly class SmtpClientConfig
{
    public function __construct(
        public string $host,
        public int $port = 25,
        public ?string $username = null,
        public ?string $password = null,
        public string $encryption = 'none', // none|tls|ssl
        public string $helo = 'localhost',
        public int $timeout = 10,
        public bool $verifyPeer = true,
        public bool $verifyPeerName = true,
    ) {
    }
}
