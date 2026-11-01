<?php
/**
 * api/chatbot/cart/add.php
 * Mirrors chatbot_api.php's `add_to_cart` case, against
 * chatbot_sessions.agent_cart for the given session_id instead of
 * $_SESSION['agent_cart'] - entirely separate from the site's regular
 * cart table / api/cart/add.php, same as the original's design intent.
 * POST {session_id, product_id, qty, category?}.
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/products.php';
require_once __DIR__ . '/../../lib/chatbot.php';
require_once __DIR__ . '/../../lib/currency.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = trim((string) ($input['session_id'] ?? ''));
$id = trim((string) ($input['product_id'] ?? ''));
$qty = (int) ($input['qty'] ?? 0);
$category = trim((string) ($input['category'] ?? ''));
$currency = currency_code($input['currency'] ?? 'USD');

if ($sessionId === '') {
    json_error('VALIDATION_ERROR', 'session_id is required.', 400);
}
if ($id === '' || $qty <= 0) {
    json_error('VALIDATION_ERROR', 'Invalid item or quantity.', 400);
}

$stmt = $conn->prepare(
    "SELECT product_id, product_name, price, image, category FROM products WHERE product_id = ?"
);
$stmt->bind_param("s", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    json_error('NOT_FOUND', 'Product not found.', 404);
}

$cart = chatbot_get_cart($conn, $sessionId);
if (isset($cart[$id])) {
    $cart[$id]['quantity'] = (int) $cart[$id]['quantity'] + $qty;
} else {
    $cart[$id] = [
        'product_name' => $product['product_name'],
        'price' => (float) $product['price'],
        'image' => $product['image'],
        'quantity' => $qty,
    ];
}
chatbot_save_cart($conn, $sessionId, $cart);

$cartCount = count($cart);
$imgCategory = $category !== '' ? $category : $product['category'];

$rawSubtotal = round((float) $product['price'] * $qty, 2);

json_ok([
    'message' => $product['product_name'] . ' added to cart',
    'cart_count' => $cartCount,
    'product_name' => $product['product_name'],
    'price' => (float) $product['price'],
    'converted_price' => currency_convert((float) $product['price'], $currency),
    'qty' => $qty,
    'subtotal' => $rawSubtotal,
    'converted_subtotal' => currency_convert($rawSubtotal, $currency),
    'currency' => $currency,
    'currency_symbol' => currency_symbol($currency),
    'image_path' => product_image_url($imgCategory, $product['image']),
]);
