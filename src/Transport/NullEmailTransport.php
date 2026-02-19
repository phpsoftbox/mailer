<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Transport;

use PhpSoftBox\Mailer\Email\EmailPayload;
use PhpSoftBox\Mailer\Email\EmailTransportInterface;
use PhpSoftBox\Mailer\Message\EmailMessage;

final class NullEmailTransport implements EmailTransportInterface
{
    public function send(EmailMessage $message, EmailPayload $payload): void
    {
    }
}
