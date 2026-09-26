<?php

const FEEDBACK_RECEIVER = 'nastasya_zablotskaya@mail.ru';

function mail_settings_path(): string
{
    return app_root() . '/email_settings.json';
}

function mail_settings_defaults(): array
{
    return [
        'sender' => FEEDBACK_RECEIVER,
        'receiver' => FEEDBACK_RECEIVER,
        'subject' => 'Анонимное пожелание НАПИШИ МНЕ',
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
    ];
}

function mail_settings_save(string $sender, string $subject): bool
{
    $settings = [
        'sender' => $sender,
        'receiver' => FEEDBACK_RECEIVER,
        'subject' => $subject,
    ];
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents(mail_settings_path(), $json, LOCK_EX) !== false;
}

function send_feedback_message(string $message): array
{
    $message = trim($message);
    if ($message === '' || text_length($message) > 5000) {
        return ['success' => false, 'error' => 'Введите текст пожелания'];
    }
    $settings = mail_settings_load();
    $subject = $settings['subject'];
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $body = str_replace(["\r\n", "\r"], "\n", $message);
    $body = str_replace("\n", "\r\n", $body);
    $from = $settings['sender'];
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . $from,
        'Reply-To: ' . FEEDBACK_RECEIVER,
        'X-Mailer: PHP/' . phpversion(),
    ];
    $sent = mail(FEEDBACK_RECEIVER, $encodedSubject, $body, implode("\r\n", $headers));
    if (!$sent) {
        return ['success' => false, 'error' => 'Сервер не отправил письмо. Нужен локальный почтовый агент (postfix).'];
    }
    return ['success' => true];
}
