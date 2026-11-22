<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/staff.php';
require_once __DIR__ . '/includes/storytelling.php';

$token = $_SESSION['storytelling_csrf'] ?? '';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}
if ($token === '' || !hash_equals($token, (string) ($_POST['csrf'] ?? ''))) {
    json_response(['success' => false, 'error' => 'Обновите страницу и повторите попытку'], 403);
}

function storytelling_upload(array $file): string
{
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 8 * 1024 * 1024) {
        throw new InvalidArgumentException('Не удалось загрузить файл. Максимальный размер — 8 МБ.');
    }
    $extensions = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
        'image/webp' => 'webp', 'image/avif' => 'avif',
        'video/webm' => 'webm', 'video/mp4' => 'mp4',
    ];
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($extensions[$type]) || (str_starts_with($type, 'image/') && !@getimagesize($file['tmp_name']))) {
        throw new InvalidArgumentException('Выберите JPEG, PNG, GIF, WebP, AVIF, WebM или MP4.');
    }
    $path = 'images/story-' . bin2hex(random_bytes(16)) . '.' . $extensions[$type];
    if (!move_uploaded_file($file['tmp_name'], app_root() . '/' . $path)) {
        throw new RuntimeException('Не удалось сохранить аватар');
    }
    return $path;
}

$status = 200;
$result = ['success' => true];
$uploaded = '';
try {
    lock_project_storage();
    $directory = staff_load();
    $story = storytelling_load($directory);
    $action = $_POST['action'] ?? '';
    $id = (string) ($_POST['staffId'] ?? '');
    $previousAvatar = '';

    switch ($action) {
        case 'hero':
            if ($id !== '' && (!isset($story['members'][$id]) || staff_find($directory, $id) === null)) {
                throw new InvalidArgumentException('Выберите участника Storytelling');
            }
            $story['hero'] = $id;
            $story['heroVisual'] = ($_POST['heroVisual'] ?? '') === 'avatar' ? 'avatar' : 'video';
            break;
        case 'add':
            if (staff_find($directory, $id) === null || isset($story['members'][$id])) {
                throw new InvalidArgumentException('Выберите сотрудника, которого ещё нет в Storytelling');
            }
            $story['members'][$id] = ['avatar' => '', 'pairs' => storytelling_pairs([])];
            break;
        case 'remove':
            if (!isset($story['members'][$id])) {
                throw new InvalidArgumentException('Участник не найден');
            }
            $previousAvatar = $story['members'][$id]['avatar'];
            unset($story['members'][$id]);
            if ($story['hero'] === $id) {
                $story['hero'] = '';
            }
            break;
        case 'update':
            if (!isset($story['members'][$id]) || staff_find($directory, $id) === null) {
                throw new InvalidArgumentException('Участник не найден');
            }
            $member = $story['members'][$id];
            $previousAvatar = $member['avatar'];
            $member['pairs'] = [];
            for ($index = 0; $index < 3; $index++) {
                $question = trim((string) ($_POST['question'][$index] ?? ''));
                $answer = trim((string) ($_POST['answer'][$index] ?? ''));
                if (text_length($question) > 120 || text_length($answer) > 5000 || ($answer !== '' && $question === '')) {
                    throw new InvalidArgumentException('Укажите вопрос для каждого ответа. Вопрос — до 120 символов, ответ — до 5000.');
                }
                $member['pairs'][] = ['question' => $question, 'answer' => $answer];
            }
            $file = $_FILES['avatar'] ?? ['error' => UPLOAD_ERR_NO_FILE];
            if (($_POST['avatarMode'] ?? '') === 'main') {
                $member['avatar'] = '';
            } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploaded = storytelling_upload($file);
                $member['avatar'] = $uploaded;
            } elseif ($member['avatar'] === '') {
                throw new InvalidArgumentException('Выберите файл для аватара');
            }
            $story['members'][$id] = $member;
            if ($member['avatar'] === $previousAvatar) {
                $previousAvatar = '';
            }
            break;
        default:
            throw new InvalidArgumentException('Неизвестное действие');
    }

    $json = json_encode($story, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!write_project_file(storytelling_path(), $json . "\n")) {
        throw new RuntimeException('Не удалось сохранить storytelling');
    }
    $uploaded = '';
    remove_unused_public_image($previousAvatar);
} catch (InvalidArgumentException $error) {
    $status = 400;
    $result = ['success' => false, 'error' => $error->getMessage()];
} catch (Throwable $error) {
    error_log($error->getMessage());
    $status = 500;
    $result = ['success' => false, 'error' => 'Не удалось сохранить Storytelling'];
} finally {
    delete_public_image($uploaded);
}
json_response($result, $status);
