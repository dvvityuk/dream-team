<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

json_response(employees_read());
