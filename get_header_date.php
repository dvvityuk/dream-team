<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

json_response(['success' => true, 'date' => header_date_read()]);
