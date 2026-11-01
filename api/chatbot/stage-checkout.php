<?php
/**
 * api/chatbot/stage-checkout.php
 * Mirrors chatbot_api.php's `stage_for_payment_page` case, adapted per
 * the addendum: instead of staging session keys and redirecting to
 * dummy_payment.php, just return the computed order items + total so the
 * Flutter app can show its own checkout/payment-method UI, then call
 * place-order.php to finalize. Auth required (matches the original,
 * which required login for this step). Deliberately does NOT clear the
 * agent cart here (unlike the original) - place-order.php still needs it
 * and clears it itself once the order is actually inserted.
 *
 * POST {session_id, shipping_address, billing_address?, currency?}.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/chatbot.php';

$user = require_auth($conn);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$sessionId = trim((string) ($input['session_id'] ?? ''));
$shippingAddress = trim((string) ($input['shipping_address'] ?? ''));
$billingAddress = trim((string) ($input['billing_address'] ?? ''));
if ($billingAddress === '') {
    $billingAddress = $shippingAddress;
}
$currency = currency_code($input['currency'] ?? 'USD');

if ($sessionId === '') {
    json_error('VALIDATION_ERROR', 'session_id is required.', 400);
}
if ($shippingAddress === '') {
    json_error('VALIDATION_ERROR', 'Shipping address is required.', 400);
}

$cart = chatbot_get_cart($conn, $sessionId);
if (empty($cart)) {
    json_error('EMPTY_CART', 'Your cart is empty.', 400);
}

$orderItems = [];
$total = 0.0;
foreach ($cart as $pid => $item) {
    $price = (float) ($item['price'] ?? 0);
    $qty = (int) ($item['quantity'] ?? 0);
    $orderItems[] = [
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
    'items' => $orderItems,
    'total' => $total,
    'converted_total' => currency_convert($total, $currency),
    'currency' => $currency,
    'currency_symbol' => currency_symbol($currency),
    'shipping_address' => $shippingAddress,
    'billing_address' => $billingAddress,
]);
