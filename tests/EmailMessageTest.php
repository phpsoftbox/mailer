<?php

declare(strict_types=1);

namespace PhpSoftBox\Mailer\Tests;

use PhpSoftBox\Mailer\Message\EmailMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailMessage::class)]
#[CoversMethod(EmailMessage::class, 'layout')]
#[CoversMethod(EmailMessage::class, 'layoutData')]
#[CoversMethod(EmailMessage::class, 'layoutTemplateName')]
final class EmailMessageTest extends TestCase
{
    private readonly object $dataDto;

    protected function setUp(): void
    {
        $this->dataDto = new class () {
            public string $code = '123456';
        };
    }

    /**
     * Проверяет сохранение имени layout-шаблона и его данных.
     */
    #[Test]
    public function testLayoutTemplateCanBeConfigured(): void
    {
        $message = EmailMessage::create('Subject')
            ->layout('email/layout.phtml', [
                'unsubscribeBlock' => true,
            ]);

        $this->assertSame('email/layout.phtml', $message->layoutTemplateName());
        $this->assertSame([
            'unsubscribeBlock' => true,
        ], $message->layoutData());
    }

    /**
     * Проверяет, что template/layout могут хранить DTO-объекты.
     */
    #[Test]
    public function testTemplateAndLayoutSupportDtoObjects(): void
    {
        $message = EmailMessage::create('Subject')
            ->template('email/content.phtml', $this->dataDto)
            ->layout('email/layout.phtml', $this->dataDto);

        $this->assertSame($this->dataDto, $message->templateData());
        $this->assertSame($this->dataDto, $message->layoutData());
    }
}
