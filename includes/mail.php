<?php

const FEEDBACK_RECEIVER = 'nastasya_zablotskaya@mail.ru';

function mail_settings_path(): string
{
    return data_dir() . '/feedback-mail.json';
}

function mail_settings_defaults(): array
{
    return [
        'sender' => FEEDBACK_RECEIVER,
        'receiver' => FEEDBACK_RECEIVER,
        'subject' => 'Анонимное пожелание НАПИШИ МНЕ',
        'app_password' => '',
    ];
}

function clean_header(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function mail_settings_load(): array
{
    $settings = mail_settings_defaults();
    $path = mail_settings_path();
    if (!is_file($path)) {
        $path = app_root() . '/email_settings.json';
    }
    if (is_file($path)) {
        $stored = json_decode((string) file_get_contents($path), true);
        if (is_array($stored)) {
            $settings = array_merge($settings, $stored);
        }
    }
    $sender = clean_header((string) $settings['sender']);
    if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
        $sender = FEEDBACK_RECEIVER;
    }
    $subject = clean_header((string) $settings['subject']);
    if ($subject === '') {
        $subject = mail_settings_defaults()['subject'];
    }
    return [
        'sender' => $sender,
        'receiver' => FEEDBACK_RECEIVER,
        'subject' => $subject,
        'app_password' => (string) $settings['app_password'],
    ];
}

function mail_settings_public(array $settings): array
{
    $settings['has_app_password'] = $settings['app_password'] !== '';
    unset($settings['app_password']);
    return $settings;
}

function mail_settings_save(string $sender, string $subject, string $password): bool
{
    $settings = [
        'sender' => $sender,
        'subject' => $subject,
        'app_password' => $password,
    ];
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $json !== false && write_project_file(mail_settings_path(), $json);
}

function send_feedback_smtp(array $settings, string $subject, string $body, array $headers): bool
{
    if (!function_exists('curl_init')) {
        return false;
    }
    $payload = fopen('php://temp', 'w+b');
    if ($payload === false) {
        return false;
    }

    $headers[] = 'To: ' . $settings['receiver'];
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'Date: ' . date(DATE_RFC2822);
    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n";
    fwrite($payload, $message);
    rewind($payload);

    $smtp = curl_init('smtps://smtp.mail.ru:465');
    try {
        return curl_setopt_array($smtp, [
            CURLOPT_USERNAME => $settings['sender'],
            CURLOPT_PASSWORD => $settings['app_password'],
            CURLOPT_MAIL_FROM => '<' . $settings['sender'] . '>',
            CURLOPT_MAIL_RCPT => ['<' . $settings['receiver'] . '>'],
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $payload,
            CURLOPT_INFILESIZE => strlen($message),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RETURNTRANSFER => true,
        ]) && curl_exec($smtp) !== false;
    } finally {
        fclose($payload);
    }
}

function send_feedback_message(string $message): array
{
    $message = trim($message);
    if ($message === '' || text_length($message) > 5000) {
        return ['success' => false, 'error' => 'Введите текст пожелания'];
    }
    $settings = mail_settings_load();
    $subject = $settings['subject'];
    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n", 9);
    $body = str_replace(["\r\n", "\r"], "\n", $message);
    $body = quoted_printable_encode(str_replace("\n", "\r\n", $body));
    $from = $settings['sender'];
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
        'From: ' . $from,
        'Reply-To: ' . FEEDBACK_RECEIVER,
        'X-Mailer: PHP/' . phpversion(),
    ];
    $sent = $settings['app_password'] !== ''
        ? send_feedback_smtp($settings, $encodedSubject, $body, $headers)
        : mail($settings['receiver'], $encodedSubject, $body, implode("\r\n", $headers));
    if (!$sent) {
        return ['success' => false, 'error' => 'Почтовый сервер не принял сообщение. Попробуйте ещё раз позже.'];
    }
    return ['success' => true];
}
