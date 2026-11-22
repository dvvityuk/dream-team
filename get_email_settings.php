<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mail.php';
require_admin();

json_response(['success' => true, 'settings' => mail_settings_public(mail_settings_load())]);
