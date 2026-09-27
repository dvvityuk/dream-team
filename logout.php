<?php
require __DIR__ . '/includes/bootstrap.php';

$_SESSION = [];
expire_cookie(session_name());
session_destroy();
redirect_to('login.php');
