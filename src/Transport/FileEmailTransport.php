<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Transport;

use InvalidArgumentException;
use PhpSoftBox\Mailer\Email\EmailPayload;
use PhpSoftBox\Mailer\Email\EmailTransportInterface;
use PhpSoftBox\Mailer\Message\EmailMessage;
use PhpSoftBox\Mailer\Mime\MimeMessageBuilder;
use RuntimeException;

use function array_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function bin2hex;
use function file_put_contents;
use function gmdate;
use function is_dir;
use function is_string;
use function mkdir;
use function random_bytes;
use function rtrim;
use function trim;

final class FileEmailTransport implements EmailTransportInterface
{
    private readonly MimeMessageBuilder $mimeBuilder;

    public function __construct(
        private readonly string $directory,
        private readonly ?string $defaultFrom = null,
        private readonly string $filenamePrefix = 'email',
        private readonly ?string $defaultFromName = null,
    ) {
        $this->mimeBuilder = new MimeMessageBuilder();
    }

    public function send(EmailMessage $message, EmailPayload $payload): void
    {
        $from = $this->resolveFrom($payload);

        $recipients = array_values(array_unique(array_filter(array_merge(
            $payload->to,
            $payload->cc,
            $payload->bcc,
        ), static fn (string $value): bool => trim($value) !== '')));

        if ($recipients === []) {
            throw new InvalidArgumentException('File transport requires at least one recipient.');
        }

        $directory = rtrim($this->directory, '/');
        if ($directory === '') {
            throw new InvalidArgumentException('File transport directory must be a non-empty path.');
        }

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Failed to create mail directory: ' . $directory);
        }

        $content = $this->mimeBuilder->build($payload, $from, $payload->from === null ? $this->defaultFromName : null);
        $path    = $this->buildPath($directory);

        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('Failed to write mail file: ' . $path);
        }
    }

    private function buildPath(string $directory): string
    {
        $stamp  = gmdate('Ymd_His');
        $random = bin2hex(random_bytes(6));

        return $directory . '/' . $this->filenamePrefix . '_' . $stamp . '_' . $random . '.eml';
    }

    private function resolveFrom(EmailPayload $payload): string
    {
        $from = $payload->from ?? $this->defaultFrom;
        if (!is_string($from) || trim($from) === '') {
            throw new InvalidArgumentException('File transport requires a "from" address.');
        }

        return trim($from);
    }
}
