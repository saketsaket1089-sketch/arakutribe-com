<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/currency.php';

$user = require_auth($conn);
$orderId = (int) ($_GET['order_id'] ?? 0);
// Same pattern as orders/list.php - display in whatever currency the app
// currently has selected, not necessarily the one the order was placed in
// (orders.currency is just a record of what was shown at checkout time;
// total_amount/order_items.price are always stored in USD).
$currency = currency_code($_GET['currency'] ?? 'USD');

if ($orderId <= 0) {
    json_error('VALIDATION_ERROR', 'order_id is required.', 400);
}

$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $orderId, $user['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    json_error('NOT_FOUND', 'Order not found.', 404);
}

$itemsStmt = $conn->prepare("SELECT product_id, product_name, quantity, price FROM order_items WHERE order_id = ?");
$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$itemsRes = $itemsStmt->get_result();

$items = [];
while ($row = $itemsRes->fetch_assoc()) {
    $items[] = [
        'product_id' => $row['product_id'],
        'product_name' => $row['product_name'],
        'quantity' => (int) $row['quantity'],
        'price' => (float) $row['price'],
        'converted_price' => currency_convert($row['price'], $currency),
    ];
}
$itemsStmt->close();

json_ok([
    'order_id' => (int) $order['order_id'],
    'total_amount' => (float) $order['total_amount'],
    'converted_total' => currency_convert($order['total_amount'], $currency),
    'currency' => $currency,
    'currency_symbol' => currency_symbol($currency),
    'shipping_address' => $order['shipping_address'],
    'billing_address' => $order['billing_address'],
    'order_status' => $order['order_status'],
    'payment_status' => $order['payment_status'],
    'payment_method' => $order['payment_method'],
    'order_date' => $order['order_date'],
    'items' => $items,
]);
