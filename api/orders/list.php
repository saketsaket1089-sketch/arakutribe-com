<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/currency.php';

$user = require_auth($conn);
$currency = currency_code($_GET['currency'] ?? 'USD');

$stmt = $conn->prepare(
    "SELECT order_id, total_amount, order_status, payment_status, order_date, payment_method
     FROM orders WHERE user_id = ? ORDER BY order_date DESC, order_id DESC"
);
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        'order_id' => (int) $row['order_id'],
        'total_amount' => (float) $row['total_amount'],
        'converted_total' => currency_convert($row['total_amount'], $currency),
        'currency_symbol' => currency_symbol($currency),
        'order_status' => $row['order_status'],
        'payment_status' => $row['payment_status'],
        'order_date' => $row['order_date'],
        'payment_method' => $row['payment_method'],
    ];
}
$stmt->close();

json_ok(['items' => $items]);
