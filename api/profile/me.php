<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/profile.php';

$user = require_auth($conn);

json_ok(format_profile($user));
