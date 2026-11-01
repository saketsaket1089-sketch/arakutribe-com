<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/products.php';

$id = trim((string) ($_GET['id'] ?? ''));
$currency = currency_code($_GET['currency'] ?? 'USD');

if ($id === '') {
    json_error('VALIDATION_ERROR', 'id is required.', 400);
}

$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->bind_param("s", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    json_error('NOT_FOUND', 'Product not found.', 404);
}

$user = current_user($conn);
$isWishlisted = false;
if ($user) {
    $wstmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $wstmt->bind_param("is", $user['user_id'], $id);
    $wstmt->execute();
    $isWishlisted = (bool) $wstmt->get_result()->fetch_assoc();
    $wstmt->close();
}

json_ok(format_product($row, $currency, $isWishlisted));
