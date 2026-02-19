<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Email;

use PhpSoftBox\Mailer\Message\EmailMessage;

interface EmailTransportInterface
{
    public function send(EmailMessage $message, EmailPayload $payload): void;
}
