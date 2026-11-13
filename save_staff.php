<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/staff.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}

$body = read_json_body();
$action = (string) ($body['action'] ?? '');
$directory = staff_load();
$imageToDelete = '';

function staff_clean_person(array $input, array $fields, array $answers): array
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
    if (!staff_image_ok($image)) {
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
    $person = staff_clean_person($input, $directory['fields'], staff_empty_answers($directory['fields']));
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
        $person = staff_clean_person($input, $directory['fields'], $current['answers']);
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
        $stillUsed = false;
        foreach ($directory['people'] as $current) {
            if ($current['image'] === $previousImage) {
                $stillUsed = true;
                break;
            }
        }
        if (!$stillUsed) {
            $imageToDelete = $previousImage;
        }
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
    $imageStillUsed = false;
    foreach ($directory['people'] as $current) {
        if ($current['image'] === $removed['image']) {
            $imageStillUsed = true;
            break;
        }
    }
    if (!$imageStillUsed) {
        $imageToDelete = $removed['image'];
    }
} else {
    json_response(['success' => false, 'error' => 'Неизвестное действие'], 400);
}

if (!staff_write($directory)) {
    json_response(['success' => false, 'error' => 'Не удалось сохранить список'], 500);
}
if ($imageToDelete !== '') {
    delete_public_image($imageToDelete);
}

$people = [];
foreach ($directory['people'] as $person) {
    $people[] = staff_public_person($person);
}
json_response(['success' => true, 'people' => $people]);
