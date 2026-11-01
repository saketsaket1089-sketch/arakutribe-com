<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/products.php';
require_once __DIR__ . '/../lib/cart.php';

$user = require_auth($conn);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$productId = trim((string) ($input['product_id'] ?? ''));
$quantity = (int) ($input['quantity'] ?? 0);
$currency = currency_code($input['currency'] ?? ($_GET['currency'] ?? 'USD'));

if ($productId === '' || $quantity <= 0) {
    json_error('VALIDATION_ERROR', 'product_id and a positive integer quantity are required.', 400);
}

$stmt = $conn->prepare("SELECT product_id FROM products WHERE product_id = ?");
$stmt->bind_param("s", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    json_error('NOT_FOUND', 'Product not found.', 404);
}

// No unique constraint on (user_id, product_id) in `cart` - select-then-insert-or-update ourselves.
$check = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
$check->bind_param("is", $user['user_id'], $productId);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    $newQty = (int) $existing['quantity'] + $quantity;
    $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
    $upd->bind_param("ii", $newQty, $existing['id']);
    $upd->execute();
    $upd->close();
} else {
    $ins = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
    $ins->bind_param("isi", $user['user_id'], $productId, $quantity);
    $ins->execute();
    $ins->close();
}

json_ok(get_cart_summary($conn, $user['user_id'], $currency));
