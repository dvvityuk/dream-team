<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/staff.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}

$body = read_json_body();
$incoming = $body['fields'] ?? null;
if (!is_array($incoming)) {
    json_response(['success' => false, 'error' => 'Некорректный список полей'], 400);
}
if (count($incoming) > 20) {
    json_response(['success' => false, 'error' => 'Слишком много полей'], 400);
}

$directory = staff_load();
$existing = [];
foreach ($directory['fields'] as $field) {
    $existing[$field['id']] = true;
}
$fields = [];
$seen = [];
foreach ($incoming as $field) {
    if (!is_array($field)) {
        json_response(['success' => false, 'error' => 'Некорректное поле'], 400);
    }
    $label = trim((string) ($field['label'] ?? ''));
    if ($label === '' || text_length($label) > 80) {
        json_response(['success' => false, 'error' => 'Проверьте название поля'], 400);
    }
    $id = trim((string) ($field['id'] ?? ''));
    if ($id === '') {
        $id = 'f' . bin2hex(random_bytes(4));
    }
    if (!preg_match('/^[a-z0-9_]{2,32}$/', $id) || isset($seen[$id])) {
        json_response(['success' => false, 'error' => 'Некорректный идентификатор поля'], 400);
    }
    if (!isset($existing[$id]) && !str_starts_with($id, 'f')) {
        json_response(['success' => false, 'error' => 'Некорректный идентификатор поля'], 400);
    }
    $seen[$id] = true;
    $fields[] = ['id' => $id, 'label' => $label];
}

foreach ($directory['people'] as $index => $person) {
    $answers = [];
    foreach ($fields as $field) {
        $answers[$field['id']] = trim((string) ($person['answers'][$field['id']] ?? ''));
    }
    $person['answers'] = $answers;
    $directory['people'][$index] = $person;
}
$directory['fields'] = $fields;
if (!staff_write($directory)) {
    json_response(['success' => false, 'error' => 'Не удалось сохранить поля'], 500);
}

$people = [];
foreach ($directory['people'] as $person) {
    $people[] = staff_public_person($person);
}
json_response(['success' => true, 'fields' => $fields, 'people' => $people]);
