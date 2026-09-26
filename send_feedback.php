<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mail.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}

$message = (string) (read_json_body()['message'] ?? '');
$result = send_feedback_message($message);
json_response($result, $result['success'] ? 200 : 400);
