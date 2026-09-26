<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/albums.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
    json_response(['success' => false, 'error' => 'Запрос больше лимита PHP post_max_size'], 413);
}

$id = (string) ($_POST['folder'] ?? '');
if (!valid_folder_id($id) || !isset($_FILES['photos'])) {
    json_response(['success' => false, 'error' => 'Выберите папку и фотографии'], 400);
}

$data = albums_read();
$index = folder_index($data, $id);
if ($index < 0) {
    json_response(['success' => false, 'error' => 'Папка не найдена'], 404);
}

$files = $_FILES['photos'];
$batch = [];
if (is_array($files['name'])) {
    foreach ($files['name'] as $i => $name) {
        $batch[] = [
            'name' => $name,
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        ];
    }
} else {
    $batch[] = $files;
}

if (count($batch) > 20) {
    json_response(['success' => false, 'error' => 'За один раз можно загрузить не больше 20 фото'], 400);
}

$types = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];
$dir = albums_root() . '/' . $id;
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    json_response(['success' => false, 'error' => 'Каталог папки недоступен'], 500);
}

$prepared = [];
foreach ($batch as $file) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'error' => 'Ошибка загрузки файла'], 400);
    }
    if (($file['size'] ?? 0) > 5000000) {
        json_response(['success' => false, 'error' => 'Каждое фото должно быть не больше 5 МБ'], 400);
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = is_array($info) ? ($info['mime'] ?? '') : '';
    if (!isset($types[$mime])) {
        json_response(['success' => false, 'error' => 'Нужен jpg, png, gif или webp'], 400);
    }
    $prepared[] = [
        'tmp_name' => $file['tmp_name'],
        'ext' => $types[$mime],
    ];
}

$saved = [];
$stored = [];
foreach ($prepared as $item) {
    $filename = bin2hex(random_bytes(8)) . '.' . $item['ext'];
    $target = $dir . '/' . $filename;
    if (!move_uploaded_file($item['tmp_name'], $target)) {
        foreach ($stored as $done) {
            unlink($done);
        }
        json_response(['success' => false, 'error' => 'Не удалось сохранить фото'], 500);
    }
    $stored[] = $target;
    $saved[] = ['file' => $filename];
}

if ($saved === []) {
    json_response(['success' => false, 'error' => 'Выберите фотографии'], 400);
}

$existing = $data['folders'][$index]['photos'] ?? [];
$data['folders'][$index]['photos'] = array_merge($existing, $saved);
if (!albums_write($data)) {
    foreach ($stored as $done) {
        unlink($done);
    }
    json_response(['success' => false, 'error' => 'Не удалось сохранить альбом'], 500);
}
json_response(['success' => true, 'count' => count($saved)]);
