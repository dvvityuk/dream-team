<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mail.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}

$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '', 2)[0]));
if ($contentType !== 'application/json') {
    json_response(['success' => false, 'error' => 'Неверный формат запроса'], 415);
}

$message = read_json_body()['message'] ?? null;
if (!is_string($message) || trim($message) === '') {
    json_response(['success' => false, 'error' => 'Введите текст пожелания'], 400);
}
if (text_length(trim($message)) > 5000) {
    json_response(['success' => false, 'error' => 'Максимальная длина сообщения — 5000 символов'], 400);
}

$result = send_feedback_message($message);
json_response($result, $result['success'] ? 200 : 503);
