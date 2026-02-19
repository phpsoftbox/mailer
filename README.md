# Mailer (SMTP)

SMTP-отправка для PhpSoftBox, совместимая с компонентом Notifications.

## Быстрый старт

```php
use PhpSoftBox\Mailer\Smtp\SmtpClient;
use PhpSoftBox\Mailer\Smtp\SmtpClientConfig;
use PhpSoftBox\Mailer\Transport\SmtpEmailTransport;
use PhpSoftBox\Mailer\Transport\FileEmailTransport;
use PhpSoftBox\Notifications\Email\EmailChannel;

$config = new SmtpClientConfig(
    host: 'mailhog',
    port: 1025,
    username: null,
    password: null,
    encryption: 'none',
    helo: 'domain.local',
);

$transport = new SmtpEmailTransport(
    new SmtpClient($config),
    defaultFrom: 'no-reply@domain.local',
    defaultFromName: 'CHGS WMS',
);
$channel = new EmailChannel($transport, /* markdown */ null, /* renderer */ null, 'CHGS WMS <no-reply@domain.local>');
```

`defaultFrom` и payload `from` могут быть как чистым mailbox-адресом, так и адресом вида
`CHGS WMS <no-reply@domain.local>`. SMTP envelope `MAIL FROM` всегда получает только mailbox-часть.
Если транспорт используется без `EmailChannel`, отображаемое имя можно задать отдельно через
`defaultFromName`.

## File transport

Для локальной отладки можно сохранять письма в файлы:

```php
$transport = new FileEmailTransport(
    __DIR__ . '/var/mails',
    defaultFrom: 'no-reply@domain.local',
    defaultFromName: 'CHGS WMS',
);
```

## MailHog

Для локальной отладки удобно использовать MailHog (SMTP + UI).
Сервис можно поднять через `docker-compose`:

```yaml
mailhog:
  image: mailhog/mailhog
  ports:
    - "1025:1025"
    - "8025:8025"
```

UI доступен на `http://localhost:8025`.

## EmailMessage layout

Для шаблонных email используйте `template()` и `layout()`:

```php
use PhpSoftBox\Mailer\Message\EmailMessage;

$message = EmailMessage::create('Тема письма')
    ->template('email/content.phtml', ['name' => 'User'])
    ->layout('email/layout.phtml', ['title' => 'Тема письма']);
```

В `template()`/`layout()` можно передавать как массив, так и DTO-объект.
