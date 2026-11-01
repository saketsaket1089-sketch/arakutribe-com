<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/products.php';
require_once __DIR__ . '/../lib/cart.php';

$user = require_auth($conn);
$currency = currency_code($_GET['currency'] ?? 'USD');

json_ok(get_cart_summary($conn, $user['user_id'], $currency));
