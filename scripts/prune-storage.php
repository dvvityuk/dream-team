<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('QDIGEST_MAINTENANCE', true);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once app_root() . '/includes/staff.php';
require_once app_root() . '/includes/storytelling.php';
require_once app_root() . '/includes/albums.php';

$dryRun = in_array('--dry-run', $argv, true);
$removed = 0;
$failed = 0;
function discard_file(string $path, int $before): void
{
    global $dryRun, $removed, $failed;
    if (is_link($path) || !is_file($path) || filemtime($path) >= $before) {
        return;
    }
    if ($dryRun) {
        echo $path, "\n";
    }
    if ($dryRun || unlink($path)) {
        $removed++;
    } else {
        $failed++;
    }
}

$sessionDirs = array_unique(array_filter([session_directory(), data_dir() . '/sessions']));
lock_project_storage();
$keep = public_image_references();
$albums = [];
if (is_file(albums_path())) {
    $index = json_decode((string) file_get_contents(albums_path()), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($index['folders'] ?? null)) {
        throw new RuntimeException('Некорректный список альбомов');
    }
    foreach ($index['folders'] as $folder) {
        $albums[$folder['id']] = array_fill_keys(array_column($folder['photos'], 'file'), true);
    }
}
if (!$dryRun && is_file(staff_path())) {
    storytelling_prune_members(staff_load());
    $keep = public_image_references();
}

// Allow old two-step upload clients to finish a pending staff edit.
$orphanBefore = time() - 86400;
foreach (glob(app_root() . '/images/*') ?: [] as $path) {
    $name = basename($path);
    if (preg_match('/^(?:[a-f0-9]{16}|(?:home|story)-[a-f0-9]{32})\.(?:jpg|png|gif|webp|avif|mp4|webm)$/D', $name)
        && !isset($keep['images/' . $name])) {
        discard_file($path, $orphanBefore);
    }
}
if (home_image() !== 'img/team-hero-v5.webp') {
    discard_file(app_root() . '/img/team-hero-v5.webp', time() + 1);
}
foreach (glob(albums_root() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
    $id = basename($dir);
    if (!valid_folder_id($id) || is_link($dir)) {
        continue;
    }
    foreach (glob($dir . '/*') ?: [] as $path) {
        $name = basename($path);
        if (valid_photo_name($name) && !isset($albums[$id][$name])) {
            discard_file($path, $orphanBefore);
        }
    }
    if (!$dryRun && !isset($albums[$id]) && count(scandir($dir)) === 2) {
        rmdir($dir);
    }
}
foreach ([data_dir(), app_root()] as $dir) {
    foreach (glob($dir . '/.qdigest-*') ?: [] as $path) {
        discard_file($path, $orphanBefore);
    }
}

foreach (['.story-*', 'staff.json.tmp', 'users.php.tmp'] as $pattern) {
    foreach (glob(data_dir() . '/' . $pattern) ?: [] as $path) {
        discard_file($path, $orphanBefore);
    }
}

$cacheBefore = time() - 30 * 86400;
foreach ($sessionDirs as $dir) {
    foreach (glob($dir . '/sess_*') ?: [] as $file) {
        if (is_link($file) || !is_file($file) || !is_readable($file) || filemtime($file) >= $cacheBefore) {
            continue;
        }
        $handle = @fopen($file, 'r');
        if ($handle !== false) {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                clearstatcache(true, $file);
                if (fstat($handle)['ino'] === @fileinode($file)) {
                    discard_file($file, $cacheBefore);
                }
            }
            fclose($handle);
        }
    }
}
foreach ([data_dir() . '/cache', '/var/cache/nginx'] as $dir) {
    if (!is_dir($dir) || is_link($dir) || ($dir === '/var/cache/nginx' && !is_file('/.dockerenv'))) {
        continue;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        discard_file($file->getPathname(), $cacheBefore);
    }
}
if (!$dryRun) {
    project_logs_prune();
}
if ($dryRun || $failed > 0 || stream_isatty(STDOUT)) {
    printf("%s: %d files; errors: %d\n", $dryRun ? 'Eligible for removal' : 'Removed', $removed, $failed);
}
exit($failed > 0 ? 1 : 0);
