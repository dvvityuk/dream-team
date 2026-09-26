<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/albums.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    json_response(['success' => true] + albums_public(albums_read()));
}

require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}

$body = read_json_body();
$action = (string) ($body['action'] ?? '');
$data = albums_read();

if ($action === 'create') {
    $name = trim(str_replace(["\r", "\n"], ' ', (string) ($body['name'] ?? '')));
    if ($name === '' || text_length($name) > 60) {
        json_response(['success' => false, 'error' => 'Введите название папки до 60 символов'], 400);
    }
    $id = bin2hex(random_bytes(8));
    $dir = albums_root() . '/' . $id;
    if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
        json_response(['success' => false, 'error' => 'Не удалось создать папку'], 500);
    }
    array_unshift($data['folders'], ['id' => $id, 'name' => $name, 'photos' => []]);
    if (!albums_write($data)) {
        @rmdir($dir);
        json_response(['success' => false, 'error' => 'Не удалось сохранить альбом'], 500);
    }
    json_response(['success' => true, 'id' => $id]);
}

if ($action === 'delete_folder') {
    $id = (string) ($body['id'] ?? '');
    $index = valid_folder_id($id) ? folder_index($data, $id) : -1;
    if ($index < 0) {
        json_response(['success' => false, 'error' => 'Папка не найдена'], 404);
    }
    $removedPhotos = $data['folders'][$index]['photos'] ?? [];
    array_splice($data['folders'], $index, 1);
    if (!albums_write($data)) {
        json_response(['success' => false, 'error' => 'Не удалось сохранить альбом'], 500);
    }
    foreach ($removedPhotos as $photo) {
        delete_stored_photo($id, (string) ($photo['file'] ?? ''));
    }
    $dir = albums_root() . '/' . $id;
    $real = realpath($dir);
    $root = realpath(albums_root());
    if ($real !== false && $root !== false && path_is_inside($real, $root)) {
        remove_tree($real);
    }
    json_response(['success' => true]);
}

if ($action === 'delete_photo') {
    $id = (string) ($body['id'] ?? '');
    $file = (string) ($body['file'] ?? '');
    $index = (valid_folder_id($id) && valid_photo_name($file)) ? folder_index($data, $id) : -1;
    if ($index < 0) {
        json_response(['success' => false, 'error' => 'Фото не найдено'], 404);
    }
    $photos = [];
    foreach ($data['folders'][$index]['photos'] ?? [] as $photo) {
        if (($photo['file'] ?? '') !== $file) {
            $photos[] = $photo;
        }
    }
    $data['folders'][$index]['photos'] = array_values($photos);
    if (!albums_write($data)) {
        json_response(['success' => false, 'error' => 'Не удалось сохранить альбом'], 500);
    }
    delete_stored_photo($id, $file);
    json_response(['success' => true]);
}

json_response(['success' => false, 'error' => 'Неизвестное действие'], 400);
