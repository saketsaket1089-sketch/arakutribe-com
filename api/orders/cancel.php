<?php
/**
 * api/orders/cancel.php
 * Sends the cancellation email via the agent mailer (PHPMailer/SMTP) - this
 * FIXES the existing site's broken raw mail() cancellation email in
 * user_orders.php, per the API contract.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/currency.php';

$user = require_auth($conn);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$orderId = (int) ($input['order_id'] ?? 0);

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

$nonCancellable = ['processing', 'out for delivery', 'delivered'];
if (in_array(strtolower($order['order_status']), $nonCancellable, true)) {
    json_error('NOT_CANCELLABLE', 'This order can no longer be cancelled.', 409);
}

$newStatus = 'Cancelled';
$upd = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ? AND user_id = ?");
$upd->bind_param("sii", $newStatus, $orderId, $user['user_id']);
$upd->execute();
$upd->close();

$itemsStmt = $conn->prepare("SELECT product_name, quantity, price FROM order_items WHERE order_id = ?");
$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

if (!empty($user['email'])) {
    $currency = currency_code($order['currency'] ?? 'USD');
    $symbol = currency_symbol($currency);
    $itemsHtml = '';
    foreach ($items as $it) {
        $lineTotal = currency_convert((float) $it['price'] * (int) $it['quantity'], $currency);
        $itemsHtml .= '<tr><td>' . htmlspecialchars($it['product_name']) . '</td><td>' . (int) $it['quantity'] . '</td><td>' . htmlspecialchars($symbol) . number_format($lineTotal, 2) . '</td></tr>';
    }
    $convertedTotal = currency_convert((float) $order['total_amount'], $currency);
    $html = "<p>Dear " . htmlspecialchars($user['firstname'] ?? '') . ",</p>"
        . "<p>Your order #{$orderId} has been successfully cancelled at your request.</p>"
        . "<table border='1' cellpadding='6' cellspacing='0'><tr><th>Item</th><th>Qty</th><th>Subtotal</th></tr>{$itemsHtml}</table>"
        . "<p><strong>Total cancelled amount: " . htmlspecialchars($symbol) . number_format($convertedTotal, 2) . "</strong></p>"
        . "<p>If payment was already processed, a refund will be initiated and should reflect within 5-7 business days.</p>";

    send_mail($user['email'], "Araku Tribe - Order Cancelled #{$orderId}", $html);
}

json_ok(['message' => 'Order cancelled.']);
