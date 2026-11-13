<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/staff.php';
require_login();

$directory = staff_load();
$people = [];
foreach ($directory['people'] as $person) {
    $people[] = staff_public_person($person);
}
json_response([
    'success' => true,
    'fields' => $directory['fields'],
    'people' => $people,
]);
