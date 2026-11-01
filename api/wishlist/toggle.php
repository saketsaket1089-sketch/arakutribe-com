<?php
require_once __DIR__ . '/../config.php';

$user = require_auth($conn);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$productId = trim((string) ($input['product_id'] ?? ''));

if ($productId === '') {
    json_error('VALIDATION_ERROR', 'product_id is required.', 400);
}

$stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("is", $user['user_id'], $productId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row) {
    $del = $conn->prepare("DELETE FROM wishlist WHERE id = ?");
    $del->bind_param("i", $row['id']);
    $del->execute();
    $del->close();
    json_ok(['wishlisted' => false]);
} else {
    $ins = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
    $ins->bind_param("is", $user['user_id'], $productId);
    $ins->execute();
    $ins->close();
    json_ok(['wishlisted' => true]);
}
