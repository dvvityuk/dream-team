<?php
require __DIR__ . '/includes/bootstrap.php';

$token = $_SESSION['storytelling_csrf'] ?? '';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}
if ($token === '' || !hash_equals($token, (string) ($_POST['csrf'] ?? ''))) {
    json_response(['success' => false, 'error' => 'Обновите страницу и повторите попытку'], 403);
}

$uploaded = '';
try {
    lock_project_storage();
    $old = home_image();
    $uploaded = store_project_image($_FILES['image'] ?? [], 'home-', 8 * 1024 * 1024, [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
        'image/webp' => 'webp', 'image/avif' => 'avif',
    ]);
    if (!write_project_file(data_dir() . '/home-image.txt', $uploaded . "\n")) {
        throw new RuntimeException('Не удалось сохранить настройки главной страницы');
    }
    $current = $uploaded;
    $uploaded = '';
    if ($old === 'img/team-hero-v5.webp' && is_file(app_root() . '/' . $old)) {
        unlink(app_root() . '/' . $old);
    } else {
        remove_unused_public_image($old);
    }
    json_response(['success' => true, 'path' => $current]);
} catch (InvalidArgumentException $error) {
    json_response(['success' => false, 'error' => $error->getMessage()], 400);
} catch (Throwable $error) {
    delete_public_image($uploaded);
    error_log($error->getMessage());
    json_response(['success' => false, 'error' => 'Не удалось сохранить изображение'], 500);
}
