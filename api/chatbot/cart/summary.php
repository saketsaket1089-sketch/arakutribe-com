<?php
/**
 * api/chatbot/cart/summary.php
 * Mirrors chatbot_api.php's `get_cart_summary` case, reading from this
 * session's chatbot_sessions.agent_cart. Public, GET ?session_id=.
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/chatbot.php';
require_once __DIR__ . '/../../lib/currency.php';

$sessionId = trim((string) ($_GET['session_id'] ?? ''));
$currency = currency_code($_GET['currency'] ?? 'USD');
if ($sessionId === '') {
    json_error('VALIDATION_ERROR', 'session_id is required.', 400);
}

$cart = chatbot_get_cart($conn, $sessionId);
$items = [];
$total = 0.0;

foreach ($cart as $pid => $item) {
    $price = (float) ($item['price'] ?? 0);
    $qty = (int) ($item['quantity'] ?? 0);
    $items[] = [
        'product_id' => (string) $pid,
        'product_name' => $item['product_name'] ?? 'Item',
        'price' => $price,
        'converted_price' => currency_convert($price, $currency),
        'quantity' => $qty,
    ];
    $total += $price * $qty;
}

$total = round($total, 2);

json_ok([
    'items' => $items,
    'total' => $total,
    'converted_total' => currency_convert($total, $currency),
    'currency' => $currency,
    'currency_symbol' => currency_symbol($currency),
]);
