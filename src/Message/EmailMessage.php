<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Message;

use function is_string;
use function trim;

final class EmailMessage
{
    /** @var list<string> */
    private array $to = [];
    /** @var list<string> */
    private array $cc = [];
    /** @var list<string> */
    private array $bcc = [];

    private ?string $from           = null;
    private ?string $replyTo        = null;
    private ?string $subject        = null;
    private ?string $text           = null;
    private ?string $html           = null;
    private ?string $markdown       = null;
    private ?string $template       = null;
    private ?string $layoutTemplate = null;
    /** @var array<string, mixed>|object */
    private array|object $templateData = [];
    /** @var array<string, mixed>|object */
    private array|object $layoutData = [];
    private bool $templateIsMarkdown = false;

    public static function create(?string $subject = null): self
    {
        $message = new self();

        if ($subject !== null) {
            $message->subject($subject);
        }

        return $message;
    }

    /**
     * @param string|list<string> $addresses
     */
    public function to(string|array $addresses): self
    {
        $this->to = $this->normalizeAddresses($addresses);

        return $this;
    }

    /**
     * @param string|list<string> $addresses
     */
    public function cc(string|array $addresses): self
    {
        $this->cc = $this->normalizeAddresses($addresses);

        return $this;
    }

    /**
     * @param string|list<string> $addresses
     */
    public function bcc(string|array $addresses): self
    {
        $this->bcc = $this->normalizeAddresses($addresses);

        return $this;
    }

    public function from(string $address): self
    {
        $this->from = $address;

        return $this;
    }

    public function replyTo(string $address): self
    {
        $this->replyTo = $address;

        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function html(string $html): self
    {
        $this->html = $html;

        return $this;
    }

    public function markdown(string $markdown): self
    {
        $this->markdown = $markdown;

        return $this;
    }

    /**
     * @param array<string, mixed>|object $data
     */
    public function template(string $template, array|object $data = [], bool $isMarkdown = false): self
    {
        $this->template           = $template;
        $this->templateData       = $data;
        $this->templateIsMarkdown = $isMarkdown;

        return $this;
    }

    /**
     * @param array<string, mixed>|object $data
     */
    public function layout(string $template, array|object $data = []): self
    {
        $this->layoutTemplate = $template;
        $this->layoutData     = $data;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function toAddresses(): array
    {
        return $this->to;
    }

    /**
     * @return list<string>
     */
    public function ccAddresses(): array
    {
        return $this->cc;
    }

    /**
     * @return list<string>
     */
    public function bccAddresses(): array
    {
        return $this->bcc;
    }

    public function fromAddress(): ?string
    {
        return $this->from;
    }

    public function replyToAddress(): ?string
    {
        return $this->replyTo;
    }

    public function subjectText(): ?string
    {
        return $this->subject;
    }

    public function textBody(): ?string
    {
        return $this->text;
    }

    public function htmlBody(): ?string
    {
        return $this->html;
    }

    public function markdownBody(): ?string
    {
        return $this->markdown;
    }

    public function templateName(): ?string
    {
        return $this->template;
    }

    public function layoutTemplateName(): ?string
    {
        return $this->layoutTemplate;
    }

    /**
     * @return array<string, mixed>|object
     */
    public function templateData(): array|object
    {
        return $this->templateData;
    }

    /**
     * @return array<string, mixed>|object
     */
    public function layoutData(): array|object
    {
        return $this->layoutData;
    }

    public function templateIsMarkdown(): bool
    {
        return $this->templateIsMarkdown;
    }

    /**
     * @param string|list<string> $addresses
     * @return list<string>
     */
    private function normalizeAddresses(string|array $addresses): array
    {
        $list = is_string($addresses) ? [$addresses] : $addresses;

        $result = [];
        foreach ($list as $value) {
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value === '') {
                continue;
            }

            $result[] = $value;
        }

        return $result;
    }
}
