<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Email;

final readonly class EmailPayload
{
    /**
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<string> $bcc
     */
    public function __construct(
        public array $to,
        public array $cc,
        public array $bcc,
        public ?string $from,
        public ?string $replyTo,
        public string $subject,
        public ?string $text,
        public ?string $html,
    ) {
    }
}
