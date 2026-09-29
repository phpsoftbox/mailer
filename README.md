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

## Формат письма и безопасность

- Письмо собирает `PhpSoftBox\Mailer\Mime\MimeMessageBuilder` (общий для SMTP и file-транспорта).
- Адреса `From`, `To`, `Cc`, `Reply-To` проходят через `EmailAddress`: перевод строки в адресе, а также пробел
  или угловые скобки в mailbox — `InvalidArgumentException` (защита от внедрения заголовков и параметров
  SMTP-команд). Адреса проверяются до соединения с SMTP-сервером.
- Отображаемое имя (`Иван <ivan@example.com>` или `defaultFromName`) и тема с не-ASCII символами кодируются
  по RFC 2047.
- Тело (text и html) передаётся в `Content-Transfer-Encoding: quoted-printable`: строки не длиннее 76 символов,
  не нужен 8BITMIME у сервера. Для просмотра `.eml` из file-транспорта используйте почтовый клиент или MailHog.
- Если сервер отклонил транзакцию (MAIL FROM/RCPT TO/DATA), транспорт отправляет `QUIT` и пробрасывает ошибку.
- Пароль SMTP помечен `#[SensitiveParameter]` (в `SmtpClientConfig` и `authLogin()`), команды SMTP-клиента не
  попадают в аргументы трассировки исключений — base64 логина и пароля не утекает в лог ошибок.

Требования: `ext-mbstring`; для `encryption: tls|ssl` — `ext-openssl`.

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
