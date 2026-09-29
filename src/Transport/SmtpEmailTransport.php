<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Transport;

use InvalidArgumentException;
use PhpSoftBox\Mailer\Contracts\SmtpClientInterface;
use PhpSoftBox\Mailer\Email\EmailPayload;
use PhpSoftBox\Mailer\Email\EmailTransportInterface;
use PhpSoftBox\Mailer\Message\EmailMessage;
use PhpSoftBox\Mailer\Mime\MimeMessageBuilder;
use PhpSoftBox\Mailer\Support\EmailAddress;
use Throwable;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function is_string;
use function trim;

final class SmtpEmailTransport implements EmailTransportInterface
{
    private readonly MimeMessageBuilder $mimeBuilder;

    public function __construct(
        private readonly SmtpClientInterface $client,
        private readonly ?string $defaultFrom = null,
        private readonly ?string $defaultFromName = null,
    ) {
        $this->mimeBuilder = new MimeMessageBuilder();
    }

    public function send(EmailMessage $message, EmailPayload $payload): void
    {
        $from        = $this->resolveFrom($payload);
        $mailboxFrom = EmailAddress::mailbox($from);

        $recipients = array_values(array_unique(array_map(
            EmailAddress::mailbox(...),
            array_filter(
                array_merge($payload->to, $payload->cc, $payload->bcc),
                static fn (string $value): bool => trim($value) !== '',
            ),
        )));

        if ($recipients === []) {
            throw new InvalidArgumentException('SMTP transport requires at least one recipient.');
        }

        // Письмо собирается (и адреса проверяются) до соединения с сервером.
        $mime = $this->mimeBuilder->build($payload, $from, $payload->from === null ? $this->defaultFromName : null);

        $this->client->connect();

        try {
            $this->client->mailFrom($mailboxFrom);
            foreach ($recipients as $address) {
                $this->client->rcptTo($address);
            }

            $this->client->data($mime);
        } catch (Throwable $exception) {
            // Сервер отклонил транзакцию: закрываем соединение, чтобы оно не осталось висеть в воркере.
            try {
                $this->client->quit();
            } catch (Throwable) {
            }

            throw $exception;
        }

        $this->client->quit();
    }

    private function resolveFrom(EmailPayload $payload): string
    {
        $from = $payload->from ?? $this->defaultFrom;
        if (!is_string($from) || trim($from) === '') {
            throw new InvalidArgumentException('SMTP transport requires a "from" address.');
        }

        return trim($from);
    }
}
