<?php
require __DIR__ . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}

$date = trim(str_replace(["\r", "\n"], ' ', (string) (read_json_body()['date'] ?? '')));
if ($date === '' || text_length($date) > 80) {
    json_response(['success' => false, 'error' => 'Введите дату до 80 символов'], 400);
}
if (!header_date_write($date)) {
    json_response(['success' => false, 'error' => 'Не удалось записать дату'], 500);
}
json_response(['success' => true]);
