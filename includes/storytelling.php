<?php

function storytelling_path(): string
{
    return data_dir() . '/storytelling.json';
}

function storytelling_load(array $directory): array
{
    $path = storytelling_path();
    if (is_file($path)) {
        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    $members = [];
    foreach ($directory['people'] as $person) {
        $pairs = [];
        foreach (array_slice($directory['fields'], 0, 3) as $field) {
            $pairs[] = ['question' => $field['label'], 'answer' => $person['answers'][$field['id']]];
        }
        $members[$person['id']] = ['avatar' => '', 'pairs' => $pairs];
    }
    return ['hero' => '', 'heroVisual' => 'video', 'members' => $members];
}

function storytelling_pairs(array $pairs): array
{
    return array_pad(array_slice($pairs, 0, 3), 3, ['question' => '', 'answer' => '']);
}

function storytelling_people(array $directory, array $story): array
{
    $people = [];
    foreach ($directory['people'] as $person) {
        $id = $person['id'];
        if (isset($story['members'][$id])) {
            $member = $story['members'][$id];
            $person['avatar'] = $member['avatar'] ?: $person['image'];
            $person['pairs'] = storytelling_pairs($member['pairs']);
            $people[$id] = $person;
        }
    }
    return $people;
}

function storytelling_video(string $path): bool
{
    return in_array(pathinfo($path, PATHINFO_EXTENSION), ['webm', 'mp4'], true);
}

function storytelling_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function storytelling_prune_members(array $directory): void
{
    if (!is_file(storytelling_path())) {
        return;
    }
    $story = storytelling_load($directory);
    $removed = array_diff_key($story['members'], array_column($directory['people'], null, 'id'));
    if ($removed === []) {
        return;
    }
    $story['members'] = array_diff_key($story['members'], $removed);
    if (isset($removed[$story['hero']])) {
        $story['hero'] = '';
    }
    $json = json_encode($story, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!write_project_file(storytelling_path(), $json . "\n")) {
        throw new RuntimeException('Не удалось очистить список Storytelling');
    }
    foreach ($removed as $member) {
        remove_unused_public_image($member['avatar']);
    }
}
