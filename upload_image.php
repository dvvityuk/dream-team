<?php
require __DIR__ . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['image'])) {
    json_response(['success' => false, 'error' => 'Файл не передан'], 400);
}

$file = $_FILES['image'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_response(['success' => false, 'error' => 'Ошибка загрузки файла'], 400);
}
if (($file['size'] ?? 0) > 5000000) {
    json_response(['success' => false, 'error' => 'Файл больше 5 МБ'], 400);
}

$info = @getimagesize($file['tmp_name']);
$types = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];
$mime = is_array($info) ? ($info['mime'] ?? '') : '';
if (!isset($types[$mime])) {
    json_response(['success' => false, 'error' => 'Нужен jpg, png, gif или webp'], 400);
}

$dir = app_root() . '/images';
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    json_response(['success' => false, 'error' => 'Каталог images недоступен'], 500);
}

$name = bin2hex(random_bytes(8)) . '.' . $types[$mime];
$target = $dir . '/' . $name;
if (!move_uploaded_file($file['tmp_name'], $target)) {
    json_response(['success' => false, 'error' => 'Не удалось сохранить файл'], 500);
}

json_response(['success' => true, 'path' => 'images/' . $name]);
