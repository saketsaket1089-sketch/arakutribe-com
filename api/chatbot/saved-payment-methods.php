<?php
/**
 * api/chatbot/saved-payment-methods.php
 * Mirrors chatbot_api.php's `get_saved_payment_methods` case. Auth
 * required (guests never see this - they skip payment-method selection
 * entirely, same as the original). GET.
 */
require_once __DIR__ . '/../config.php';

$user = require_auth($conn);

$stmt = $conn->prepare(
    "SELECT DISTINCT payment_method
     FROM orders
     WHERE user_id = ? AND payment_method IS NOT NULL AND payment_method <> '' AND payment_method <> 'pending'
     ORDER BY order_date DESC
     LIMIT 5"
);
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$res = $stmt->get_result();

$labels = ['cod' => 'Cash on Delivery'];
$methods = [];
while ($row = $res->fetch_assoc()) {
    $val = $row['payment_method'];
    $methods[] = ['value' => $val, 'label' => $labels[$val] ?? ucfirst($val)];
}
$stmt->close();

json_ok(['methods' => $methods]);
