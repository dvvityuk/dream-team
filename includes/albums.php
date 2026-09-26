<?php

function albums_path(): string
{
    return data_dir() . '/albums.json';
}

function albums_root(): string
{
    $dir = data_dir() . '/albums';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Не удалось создать каталог альбомов');
    }
    return $dir;
}

function albums_read(): array
{
    $path = albums_path();
    if (!is_file($path)) {
        $empty = ['folders' => []];
        file_put_contents($path, json_encode($empty, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $empty;
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || !isset($data['folders']) || !is_array($data['folders'])) {
        return ['folders' => []];
    }
    return $data;
}

function albums_write(array $data): bool
{
    $json = json_encode(['folders' => array_values($data['folders'] ?? [])], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents(albums_path(), $json, LOCK_EX) !== false;
}

function valid_folder_id(string $id): bool
{
    return (bool) preg_match('/^[a-f0-9]{16}$/', $id);
}

function valid_photo_name(string $name): bool
{
    return (bool) preg_match('/^[a-f0-9]{16}\.(jpg|png|gif|webp)$/', $name);
}

function folder_index(array $data, string $id): int
{
    foreach ($data['folders'] as $index => $folder) {
        if (($folder['id'] ?? '') === $id) {
            return (int) $index;
        }
    }
    return -1;
}

function albums_public(array $data): array
{
    $folders = [];
    foreach ($data['folders'] as $folder) {
        $id = (string) ($folder['id'] ?? '');
        if (!valid_folder_id($id)) {
            continue;
        }
        $photos = [];
        foreach ($folder['photos'] ?? [] as $photo) {
            $file = (string) ($photo['file'] ?? '');
            if (!valid_photo_name($file)) {
                continue;
            }
            $photos[] = [
                'file' => $file,
                'url' => 'media.php?folder=' . rawurlencode($id) . '&file=' . rawurlencode($file),
            ];
        }
        $folders[] = [
            'id' => $id,
            'name' => (string) ($folder['name'] ?? 'Папка'),
            'photos' => $photos,
        ];
    }
    return ['folders' => $folders];
}

function delete_stored_photo(string $folderId, string $file): void
{
    if (!valid_folder_id($folderId) || !valid_photo_name($file)) {
        return;
    }
    $path = albums_root() . '/' . $folderId . '/' . $file;
    $real = realpath($path);
    $root = realpath(albums_root() . '/' . $folderId);
    if ($real !== false && $root !== false && path_is_inside($real, $root) && is_file($real)) {
        unlink($real);
    }
    $publicImage = 'images/' . $file;
    foreach (employees_read() as $employee) {
        if ($employee['image'] === $publicImage) {
            return;
        }
    }
    delete_public_image($publicImage);
}

function remove_tree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            remove_tree($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function path_is_inside(string $path, string $root): bool
{
    $path = str_replace('\\', '/', $path);
    $root = rtrim(str_replace('\\', '/', $root), '/');
    return $path === $root || str_starts_with($path, $root . '/');
}
