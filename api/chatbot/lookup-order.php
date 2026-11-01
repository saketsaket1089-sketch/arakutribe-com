<?php
/**
 * api/chatbot/lookup-order.php
 * Mirrors chatbot_api.php's `lookup_order` case exactly, including the
 * ownership rule (auth token owns it, OR guest email matches
 * orders.guest_email case-insensitively), the 15-day cancellation window,
 * and the 7-day return-eligibility window. Auth optional.
 *
 * Security note (from the original): order_id is a sequential
 * auto-increment value, so a lookup must never work on ID alone - a
 * logged-in user must own it via their token, and a guest must also
 * supply the exact email the order was placed under.
 *
 * POST {order_id, email?}.
 */
require_once __DIR__ . '/../config.php';

$user = current_user($conn); // auth optional
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$orderId = preg_replace('/\D/', '', trim((string) ($input['order_id'] ?? '')));
$verifyEmail = trim((string) ($input['email'] ?? ''));
$currency = currency_code($input['currency'] ?? 'USD');

if ($orderId === '') {
    json_error('VALIDATION_ERROR', 'Please enter a valid order ID.', 400);
}

// days_since_order is computed by MySQL itself (TIMESTAMPDIFF against its
// own NOW()) rather than in PHP against order_date - this DB's NOW() runs
// in the server's local timezone while api/config.php pins PHP to UTC, so
// comparing PHP's time() against a MySQL-inserted order_date would drift
// by that offset (harmless near the middle of the cancellation window,
// but it would corrupt the day-boundary math right at its edges).
$stmt = $conn->prepare(
    "SELECT *, TIMESTAMPDIFF(DAY, order_date, NOW()) AS days_since_order FROM orders WHERE order_id = ? LIMIT 1"
);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    json_error('NOT_FOUND', 'No order found with that ID.', 404);
}

$ownsOrder = false;
if ($order['user_id'] !== null && $user && (int) $order['user_id'] === (int) $user['user_id']) {
    $ownsOrder = true;
} elseif ($order['user_id'] === null && !empty($order['guest_email']) && $verifyEmail !== '' && strcasecmp($verifyEmail, $order['guest_email']) === 0) {
    $ownsOrder = true;
}

if (!$ownsOrder) {
    if (!$user && $verifyEmail === '') {
        json_error('EMAIL_REQUIRED', 'Please also provide the email used for that order.', 400);
    }
    json_error('NOT_FOUND', "That order ID and email don't match our records.", 404);
}

$itemsStmt = $conn->prepare("SELECT product_name, quantity, price FROM order_items WHERE order_id = ?");
$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$orderItems = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

// mysqli returns DECIMAL columns as PHP strings - cast so the client
// always gets a JSON number, matching api/orders/detail.php's convention.
foreach ($orderItems as &$item) {
    $item['price'] = (float) $item['price'];
    $item['converted_price'] = currency_convert($item['price'], $currency);
    $item['quantity'] = (int) $item['quantity'];
}
unset($item);

$daysSinceOrder = (int) $order['days_since_order'];

// Cancellable only while still Processing/Placed AND within 15 days of the
// order date - both conditions must hold.
$CANCEL_WINDOW_DAYS = 15;
$eligibleForCancellation = in_array($order['order_status'], ['Processing', 'Placed'], true)
    && ($daysSinceOrder <= $CANCEL_WINDOW_DAYS);
$eligibleForReturn = ($order['order_status'] === 'Delivered') && ($daysSinceOrder <= 7);

$cancellationBlockedReason = null;
if (!$eligibleForCancellation && $order['order_status'] !== 'Cancelled') {
    if (!in_array($order['order_status'], ['Processing', 'Placed'], true)) {
        $cancellationBlockedReason = 'This order has already ' . strtolower($order['order_status']) . ' and can no longer be cancelled.';
    } else {
        $cancellationBlockedReason = "This order was placed {$daysSinceOrder} day(s) ago, which is past our {$CANCEL_WINDOW_DAYS}-day cancellation window.";
    }
}

json_ok([
    'order_id' => (int) $order['order_id'],
    'order_status' => $order['order_status'],
    'payment_status' => $order['payment_status'],
    'payment_method' => $order['payment_method'],
    'order_date' => $order['order_date'],
    'total' => (float) $order['total_amount'],
    'converted_total' => currency_convert($order['total_amount'], $currency),
    'currency' => $currency,
    'currency_symbol' => currency_symbol($currency),
    'shipping_address' => $order['shipping_address'],
    'items' => $orderItems,
    'days_since_order' => $daysSinceOrder,
    'eligible_for_cancellation' => $eligibleForCancellation,
    'eligible_for_return' => $eligibleForReturn,
    'already_cancelled' => $order['order_status'] === 'Cancelled',
    'cancellation_blocked_reason' => $cancellationBlockedReason,
]);
