<?php

function staff_path(): string
{
    return data_dir() . '/staff.json';
}

function staff_default_fields(): array
{
    return [
        ['id' => 'iqos', 'label' => 'Как попал в IQOS'],
        ['id' => 'client', 'label' => 'Самый запоминающийся клиент'],
        ['id' => 'flavor', 'label' => 'Любимый вкус'],
    ];
}

function staff_new_id(): string
{
    return bin2hex(random_bytes(8));
}

function staff_split_name(string $name): array
{
    $position = '';
    if (preg_match('/\(([^)]+)\)\s*$/u', $name, $match) === 1) {
        $position = trim($match[1]);
        $name = trim((string) preg_replace('/\(([^)]+)\)\s*$/u', '', $name));
    }
    $parts = preg_split('/\s+/u', $name, 2) ?: [];
    return [
        'lastName' => trim((string) ($parts[0] ?? '')),
        'firstName' => trim((string) ($parts[1] ?? '')),
        'position' => $position,
    ];
}

function staff_display_name(array $person): string
{
    $name = trim($person['lastName'] . ' ' . $person['firstName']);
    if ($person['position'] !== '') {
        $name .= ' (' . $person['position'] . ')';
    }
    return $name;
}

function staff_image_ok(string $image): bool
{
    return preg_match('#^images/[A-Za-z0-9._-]+$#', $image) === 1;
}

function staff_empty_answers(array $fields): array
{
    $answers = [];
    foreach ($fields as $field) {
        $answers[$field['id']] = '';
    }
    return $answers;
}

function staff_write(array $directory): bool
{
    $json = json_encode($directory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $path = staff_path();
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        return false;
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function digest_path(): string
{
    return app_root() . '/employees.json';
}

function digest_read_raw(): array
{
    $path = digest_path();
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function digest_write(array $cards): bool
{
    $json = json_encode(array_values($cards), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    return file_put_contents(digest_path(), $json . "\n", LOCK_EX) !== false;
}

function staff_migrate_from_digest(): array
{
    $fields = staff_default_fields();
    $people = [];
    $cards = [];
    foreach (digest_read_raw() as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string) ($item['name'] ?? ''));
        $image = str_replace('\\', '/', trim((string) ($item['image'] ?? '')));
        $achievements = trim((string) ($item['achievements'] ?? ''));
        if ($name === '' || !staff_image_ok($image)) {
            continue;
        }
        $parts = staff_split_name($name);
        if ($parts['lastName'] === '' || $parts['firstName'] === '') {
            continue;
        }
        $id = staff_new_id();
        $people[] = [
            'id' => $id,
            'lastName' => $parts['lastName'],
            'firstName' => $parts['firstName'],
            'position' => $parts['position'],
            'image' => $image,
            'answers' => staff_empty_answers($fields),
        ];
        $cards[] = [
            'staffId' => $id,
            'achievements' => $achievements,
        ];
    }
    $directory = ['fields' => $fields, 'people' => $people];
    if (!staff_write($directory)) {
        return ['fields' => $fields, 'people' => []];
    }
    if (!digest_write($cards)) {
        @unlink(staff_path());
        return ['fields' => $fields, 'people' => []];
    }
    return $directory;
}

function staff_normalize(array $directory): array
{
    $fields = [];
    foreach ($directory['fields'] ?? [] as $field) {
        if (!is_array($field)) {
            continue;
        }
        $id = trim((string) ($field['id'] ?? ''));
        $label = trim((string) ($field['label'] ?? ''));
        if (!preg_match('/^[a-z0-9_]{2,32}$/', $id) || $label === '') {
            continue;
        }
        $fields[] = ['id' => $id, 'label' => $label];
    }
    if ($fields === []) {
        $fields = staff_default_fields();
    }
    $people = [];
    foreach ($directory['people'] ?? [] as $person) {
        if (!is_array($person)) {
            continue;
        }
        $id = trim((string) ($person['id'] ?? ''));
        $image = str_replace('\\', '/', trim((string) ($person['image'] ?? '')));
        if (!preg_match('/^[a-f0-9]{16}$/', $id) || !staff_image_ok($image)) {
            continue;
        }
        $answers = [];
        $stored = is_array($person['answers'] ?? null) ? $person['answers'] : [];
        foreach ($fields as $field) {
            $answers[$field['id']] = trim((string) ($stored[$field['id']] ?? ''));
        }
        $people[] = [
            'id' => $id,
            'lastName' => trim((string) ($person['lastName'] ?? '')),
            'firstName' => trim((string) ($person['firstName'] ?? '')),
            'position' => trim((string) ($person['position'] ?? '')),
            'image' => $image,
            'answers' => $answers,
        ];
    }
    return ['fields' => $fields, 'people' => $people];
}

function staff_load(): array
{
    $path = staff_path();
    if (!is_file($path)) {
        return staff_normalize(staff_migrate_from_digest());
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return staff_normalize(staff_migrate_from_digest());
    }
    return staff_normalize($decoded);
}

function staff_find(array $directory, string $id): ?array
{
    foreach ($directory['people'] as $person) {
        if ($person['id'] === $id) {
            return $person;
        }
    }
    return null;
}

function staff_public_person(array $person): array
{
    return [
        'id' => $person['id'],
        'lastName' => $person['lastName'],
        'firstName' => $person['firstName'],
        'position' => $person['position'],
        'image' => $person['image'],
        'label' => staff_display_name($person),
        'answers' => $person['answers'],
    ];
}

function digest_public_list(): array
{
    $directory = staff_load();
    $byId = [];
    foreach ($directory['people'] as $person) {
        $byId[$person['id']] = $person;
    }
    $list = [];
    foreach (digest_read_raw() as $item) {
        if (!is_array($item)) {
            continue;
        }
        $staffId = trim((string) ($item['staffId'] ?? ''));
        if ($staffId === '' || !isset($byId[$staffId])) {
            continue;
        }
        $person = $byId[$staffId];
        $list[] = [
            'staffId' => $staffId,
            'name' => staff_display_name($person),
            'lastName' => $person['lastName'],
            'firstName' => $person['firstName'],
            'position' => $person['position'],
            'achievements' => trim((string) ($item['achievements'] ?? '')),
            'image' => $person['image'],
        ];
    }
    return $list;
}
