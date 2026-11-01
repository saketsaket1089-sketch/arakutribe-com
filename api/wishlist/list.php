<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/products.php';

$user = require_auth($conn);
$currency = currency_code($_GET['currency'] ?? 'USD');

$stmt = $conn->prepare(
    "SELECT p.* FROM wishlist w JOIN products p ON w.product_id = p.product_id
     WHERE w.user_id = ? ORDER BY w.created_at DESC, w.id DESC"
);
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = format_product($row, $currency, true);
}
$stmt->close();

json_ok(['items' => $items]);
