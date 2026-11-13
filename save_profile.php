<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/staff.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}

$body = read_json_body();
$id = trim((string) ($body['id'] ?? ''));
$incoming = is_array($body['answers'] ?? null) ? $body['answers'] : [];
$directory = staff_load();
$found = false;
foreach ($directory['people'] as $index => $person) {
    if ($person['id'] !== $id) {
        continue;
    }
    $answers = [];
    foreach ($directory['fields'] as $field) {
        $value = trim((string) ($incoming[$field['id']] ?? ''));
        if (text_length($value) > 5000) {
            json_response(['success' => false, 'error' => 'Слишком длинный текст'], 400);
        }
        $answers[$field['id']] = $value;
    }
    $person['answers'] = $answers;
    $directory['people'][$index] = $person;
    $found = true;
    break;
}
if (!$found) {
    json_response(['success' => false, 'error' => 'Сотрудник не найден'], 404);
}
if (!staff_write($directory)) {
    json_response(['success' => false, 'error' => 'Не удалось сохранить информацию'], 500);
}
json_response(['success' => true]);
