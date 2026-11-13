<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/staff.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}

$data = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($data)) {
    json_response(['success' => false, 'error' => 'Некорректный JSON'], 400);
}

$directory = staff_load();
$known = [];
foreach ($directory['people'] as $person) {
    $known[$person['id']] = true;
}

$clean = [];
$seen = [];
foreach ($data as $item) {
    if (!is_array($item)) {
        json_response(['success' => false, 'error' => 'Некорректная карточка'], 400);
    }
    $staffId = trim((string) ($item['staffId'] ?? ''));
    $achievements = trim((string) ($item['achievements'] ?? ''));
    if (!isset($known[$staffId])) {
        json_response(['success' => false, 'error' => 'Выберите сотрудника из списка'], 400);
    }
    if (isset($seen[$staffId])) {
        json_response(['success' => false, 'error' => 'Этот сотрудник уже есть в Q digest'], 400);
    }
    if ($achievements === '' || text_length($achievements) > 2000) {
        json_response(['success' => false, 'error' => 'Опишите достижения'], 400);
    }
    $seen[$staffId] = true;
    $clean[] = [
        'staffId' => $staffId,
        'achievements' => $achievements,
    ];
}

if (!digest_write($clean)) {
    json_response(['success' => false, 'error' => 'Не удалось записать файл'], 500);
}
json_response(['success' => true]);
