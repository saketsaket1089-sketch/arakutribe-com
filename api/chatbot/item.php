<?php
/**
 * api/chatbot/item.php
 * Mirrors chatbot_api.php's `get_item` case. Public, GET ?id=.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/products.php';
require_once __DIR__ . '/../lib/currency.php';

$id = trim((string) ($_GET['id'] ?? ''));
$currency = currency_code($_GET['currency'] ?? 'USD');
if ($id === '') {
    json_error('VALIDATION_ERROR', 'id is required.', 400);
}

$stmt = $conn->prepare(
    "SELECT product_id, product_name, price, quantity, minimum_quantity, category, image
     FROM products WHERE product_id = ?"
);
$stmt->bind_param("s", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    json_error('NOT_FOUND', 'Item not found.', 404);
}

json_ok([
    'product_id' => $row['product_id'],
    'product_name' => $row['product_name'],
    'price' => (float) $row['price'],
    'converted_price' => currency_convert($row['price'], $currency),
    'currency' => $currency,
    'currency_symbol' => currency_symbol($currency),
    'quantity' => (int) $row['quantity'],
    'minimum_quantity' => (int) ($row['minimum_quantity'] ?: 100),
    'category' => $row['category'],
    'image' => $row['image'],
    'image_path' => product_image_url($row['category'], $row['image']),
]);
