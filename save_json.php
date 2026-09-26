<?php
require __DIR__ . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}

$data = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($data)) {
    json_response(['success' => false, 'error' => 'Некорректный JSON'], 400);
}

$clean = [];
foreach ($data as $item) {
    if (!is_array($item)) {
        json_response(['success' => false, 'error' => 'Некорректная карточка'], 400);
    }
    $name = trim((string) ($item['name'] ?? ''));
    $achievements = trim((string) ($item['achievements'] ?? ''));
    $image = str_replace('\\', '/', trim((string) ($item['image'] ?? '')));
    if ($name === '' || $achievements === '' || text_length($name) > 200 || text_length($achievements) > 2000) {
        json_response(['success' => false, 'error' => 'Проверьте имя и достижения'], 400);
    }
    if (!preg_match('#^images/[A-Za-z0-9._-]+$#', $image)) {
        json_response(['success' => false, 'error' => 'Некорректный путь к фото'], 400);
    }
    $clean[] = [
        'name' => $name,
        'achievements' => $achievements,
        'image' => $image,
    ];
}

$previousImages = [];
foreach (employees_read() as $employee) {
    $previousImages[$employee['image']] = true;
}
$keptImages = [];
foreach ($clean as $employee) {
    $keptImages[$employee['image']] = true;
}

$json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if (file_put_contents(app_root() . '/employees.json', $json, LOCK_EX) === false) {
    json_response(['success' => false, 'error' => 'Не удалось записать файл'], 500);
}

foreach (array_keys($previousImages) as $image) {
    if (!isset($keptImages[$image])) {
        delete_public_image($image);
    }
}
json_response(['success' => true]);
