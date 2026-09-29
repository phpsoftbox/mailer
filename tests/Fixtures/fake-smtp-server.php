<?php

declare(strict_types=1);

// Минимальный SMTP-сервер для тестов SmtpClient: принимает одно соединение, печатает порт в STDOUT
// и отклоняет пароль AUTH LOGIN (535).

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, $errstr);
    exit(1);
}

$name = (string) stream_socket_get_name($server, false);
fwrite(STDOUT, substr($name, (int) strrpos($name, ':') + 1) . "\n");
fflush(STDOUT);

$client = stream_socket_accept($server, 10);
if ($client === false) {
    exit(1);
}

fwrite($client, "220 fake ESMTP\r\n");
$authStep = 0;
while (($line = fgets($client)) !== false) {
    $line = rtrim($line, "\r\n");

    if ($authStep === 1) {
        $authStep = 2;
        fwrite($client, "334 UGFzc3dvcmQ6\r\n");
        continue;
    }

    if ($authStep === 2) {
        fwrite($client, "535 5.7.8 Authentication failed\r\n");
        break;
    }

    if (str_starts_with($line, 'EHLO ')) {
        fwrite($client, "250-fake\r\n250 AUTH LOGIN\r\n");
    } elseif ($line === 'AUTH LOGIN') {
        $authStep = 1;
        fwrite($client, "334 VXNlcm5hbWU6\r\n");
    } else {
        fwrite($client, "500 unknown command\r\n");
    }
}

fclose($client);
