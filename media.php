<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/albums.php';
require_login();

$folder = (string) ($_GET['folder'] ?? '');
$file = (string) ($_GET['file'] ?? '');
if (!valid_folder_id($folder) || !valid_photo_name($file)) {
    http_response_code(404);
    exit;
}

$data = albums_read();
$index = folder_index($data, $folder);
$known = false;
if ($index >= 0) {
    foreach ($data['folders'][$index]['photos'] ?? [] as $photo) {
        if (($photo['file'] ?? '') === $file) {
            $known = true;
            break;
        }
    }
}
$path = albums_root() . '/' . $folder . '/' . $file;
$real = realpath($path);
$root = realpath(albums_root() . '/' . $folder);
if (!$known || $real === false || $root === false || !path_is_inside($real, $root) || !is_file($real)) {
    http_response_code(404);
    exit;
}

$info = @getimagesize($real);
if (!is_array($info) || empty($info['mime'])) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $info['mime']);
header('Content-Length: ' . (string) filesize($real));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($real);
