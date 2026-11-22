<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/staff.php';
require_once __DIR__ . '/includes/storytelling.php';
$token = $_SESSION['storytelling_csrf'] ?? '';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}

if (isset($_POST['payload']) && ($token === '' || !hash_equals($token, (string) ($_POST['csrf'] ?? '')))) {
    json_response(['success' => false, 'error' => 'Обновите страницу и повторите попытку'], 403);
}

$body = isset($_POST['payload']) ? json_decode((string) $_POST['payload'], true) : read_json_body();
$action = (string) ($body['action'] ?? '');
lock_project_storage();
$directory = staff_load();
$imageToDelete = '';
$uploaded = '';

function staff_clean_person(array $input, array $answers, string &$uploaded): array
{
    $lastName = trim((string) ($input['lastName'] ?? ''));
    $firstName = trim((string) ($input['firstName'] ?? ''));
    $position = trim((string) ($input['position'] ?? ''));
    $image = str_replace('\\', '/', trim((string) ($input['image'] ?? '')));
    if ($lastName === '' || $firstName === '' || $position === '') {
        json_response(['success' => false, 'error' => 'Заполните фамилию, имя и должность'], 400);
    }
    if (text_length($lastName) > 80 || text_length($firstName) > 80 || text_length($position) > 80) {
        json_response(['success' => false, 'error' => 'Слишком длинное значение'], 400);
    }
    if (isset($_FILES['image'])) {
        try {
            $uploaded = store_project_image($_FILES['image'], '', 5000000, [
                'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
            ]);
            $image = $uploaded;
        } catch (InvalidArgumentException $error) {
            json_response(['success' => false, 'error' => $error->getMessage()], 400);
        }
    }
    if (!staff_image_ok($image) || !is_file(app_root() . '/' . $image)) {
        json_response(['success' => false, 'error' => 'Загрузите фото'], 400);
    }
    return [
        'lastName' => $lastName,
        'firstName' => $firstName,
        'position' => $position,
        'image' => $image,
        'answers' => $answers,
    ];
}

if ($action === 'create') {
    $input = is_array($body['person'] ?? null) ? $body['person'] : [];
    $person = staff_clean_person($input, staff_empty_answers($directory['fields']), $uploaded);
    $person['id'] = staff_new_id();
    $directory['people'][] = $person;
} elseif ($action === 'update') {
    $id = trim((string) ($body['id'] ?? ''));
    $input = is_array($body['person'] ?? null) ? $body['person'] : [];
    $found = false;
    $previousImage = '';
    foreach ($directory['people'] as $index => $current) {
        if ($current['id'] !== $id) {
            continue;
        }
        $person = staff_clean_person($input, $current['answers'], $uploaded);
        $person['id'] = $id;
        $previousImage = $current['image'];
        $directory['people'][$index] = $person;
        $found = true;
        break;
    }
    if (!$found) {
        json_response(['success' => false, 'error' => 'Сотрудник не найден'], 404);
    }
    if ($previousImage !== '' && $previousImage !== ($directory['people'][$index]['image'] ?? '')) {
        $imageToDelete = $previousImage;
    }
} elseif ($action === 'delete') {
    $id = trim((string) ($body['id'] ?? ''));
    $removed = null;
    $people = [];
    foreach ($directory['people'] as $current) {
        if ($current['id'] === $id) {
            $removed = $current;
            continue;
        }
        $people[] = $current;
    }
    if ($removed === null) {
        json_response(['success' => false, 'error' => 'Сотрудник не найден'], 404);
    }
    $directory['people'] = $people;
    $cards = [];
    foreach (digest_read_raw() as $item) {
        if (!is_array($item) || trim((string) ($item['staffId'] ?? '')) === $id) {
            continue;
        }
        $cards[] = $item;
    }
    if (!digest_write($cards)) {
        json_response(['success' => false, 'error' => 'Не удалось обновить Q digest'], 500);
    }
    $imageToDelete = $removed['image'];
} else {
    json_response(['success' => false, 'error' => 'Неизвестное действие'], 400);
}

if (!staff_write($directory)) {
    delete_public_image($uploaded);
    json_response(['success' => false, 'error' => 'Не удалось сохранить список'], 500);
}
if ($action === 'delete') {
    storytelling_prune_members($directory);
}
remove_unused_public_image($imageToDelete);

$people = [];
foreach ($directory['people'] as $person) {
    $people[] = staff_public_person($person);
}
json_response(['success' => true, 'people' => $people]);
