<?php

function lock_project_storage(): void
{
    static $lock;
    if ($lock !== null) {
        return;
    }
    $handle = fopen(data_dir() . '/.storage.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        throw new RuntimeException('Не удалось заблокировать хранилище');
    }
    $lock = $handle;
}

function write_project_file(string $path, string $content): bool
{
    $tmp = tempnam(dirname($path), '.qdigest-');
    if ($tmp === false) {
        return false;
    }
    try {
        if (is_file($path) && !chmod($tmp, fileperms($path) & 0777)) {
            return false;
        }
        return file_put_contents($tmp, $content) === strlen($content) && rename($tmp, $path);
    } finally {
        if (is_file($tmp)) {
            unlink($tmp);
        }
    }
}

function home_image(): string
{
    $path = data_dir() . '/home-image.txt';
    $image = is_file($path) ? trim((string) file_get_contents($path)) : '';
    return preg_match('#^images/home-[a-f0-9]{32}\.(jpg|png|gif|webp|avif)$#D', $image)
        ? $image : 'img/team-hero-v5.webp';
}

function store_project_image(array $file, string $prefix, int $limit, array $types): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $file['size'] > $limit) {
        throw new InvalidArgumentException('Не удалось загрузить изображение или превышен допустимый размер.');
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? '';
    if (!isset($types[$mime])) {
        throw new InvalidArgumentException('Выберите изображение в одном из поддерживаемых форматов.');
    }
    $relative = 'images/' . $prefix . bin2hex(random_bytes($prefix === '' ? 8 : 16)) . '.' . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'], app_root() . '/' . $relative)) {
        throw new RuntimeException('Не удалось сохранить изображение');
    }
    return $relative;
}

function public_image_references(): array
{
    $keep = [home_image() => true];
    foreach (['data/staff.json' => 'people', 'employees.json' => null, 'data/storytelling.json' => 'members'] as $file => $key) {
        $path = app_root() . '/' . $file;
        if (!is_file($path)) {
            continue;
        }
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $people = $key === null ? $data : ($data[$key] ?? null);
        if (!is_array($people)) {
            throw new RuntimeException('Некорректные данные в ' . $file);
        }
        foreach ($people as $person) {
            foreach (['image', 'avatar'] as $field) {
                if (!empty($person[$field])) {
                    $keep[$person[$field]] = true;
                }
            }
        }
    }
    return $keep;
}

function remove_unused_public_image(string $path): void
{
    if ($path !== '' && !isset(public_image_references()[$path])) {
        delete_public_image($path);
    }
}
