<?php
/**
 * api/chatbot/cancel-order.php
 * Mirrors chatbot_api.php's `cancel_order` case exactly: OTP check
 * against chatbot_sessions for this session_id (sent via
 * send-cancel-otp.php), ownership + status/window re-check (defense in
 * depth - state could have changed between sending the OTP and confirming
 * it), Paid->Refunded payment_status handling, and the same cancellation
 * email copy. The live orders.payment_status enum is
 * ('Paid','Pending','Refunded') per the base contract, so setting
 * 'Refunded' just works - no try/catch fallback needed here (unlike the
 * original, which had to guard against the enum not yet having it).
 *
 * Auth optional (same ownership rule as lookup-order.php).
 * POST {session_id, order_id, otp, email?}.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/chatbot.php';

$user = current_user($conn); // auth optional
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$sessionId = trim((string) ($input['session_id'] ?? ''));
$orderId = preg_replace('/\D/', '', trim((string) ($input['order_id'] ?? '')));
$verifyEmail = trim((string) ($input['email'] ?? ''));
$otpInput = trim((string) ($input['otp'] ?? ''));

if ($sessionId === '') {
    json_error('VALIDATION_ERROR', 'session_id is required.', 400);
}
if ($orderId === '') {
    json_error('VALIDATION_ERROR', 'Please enter a valid order ID.', 400);
}

$session = chatbot_get_session($conn, $sessionId);

if (
    $otpInput === ''
    || !$session
    || (int) ($session['cancel_otp_order_id'] ?? 0) !== (int) $orderId
    || empty($session['cancel_otp_code'])
    || chatbot_otp_expired($session['cancel_otp_expire'])
    || !hash_equals((string) $session['cancel_otp_code'], $otpInput)
) {
    json_error('INVALID_OTP', 'Invalid or expired verification code. Please request a new one.', 401);
}

// days_since_order computed by MySQL itself against its own NOW() - see
// the comment in lookup-order.php for why (PHP is pinned to UTC while
// this DB's NOW() runs in the server's local timezone).
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
    json_error('NOT_FOUND', "That order ID and email don't match our records.", 404);
}

if ($order['order_status'] === 'Cancelled') {
    json_error('ALREADY_CANCELLED', 'That order has already been cancelled.', 409);
}
if (!in_array($order['order_status'], ['Processing', 'Placed'], true)) {
    json_error('NOT_CANCELLABLE', 'This order has already ' . strtolower($order['order_status']) . ' and can no longer be cancelled.', 409);
}

$CANCEL_WINDOW_DAYS = 15;
$daysSinceOrder = (int) $order['days_since_order'];
if ($daysSinceOrder > $CANCEL_WINDOW_DAYS) {
    json_error('CANCEL_WINDOW_EXPIRED', "This order was placed {$daysSinceOrder} day(s) ago, which is past our {$CANCEL_WINDOW_DAYS}-day cancellation window.", 409);
}

$wasPaid = ($order['payment_status'] === 'Paid');

try {
    if ($wasPaid) {
        $stmt = $conn->prepare("UPDATE orders SET order_status = 'Cancelled', payment_status = 'Refunded' WHERE order_id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE orders SET order_status = 'Cancelled' WHERE order_id = ?");
    }
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $stmt->close();
} catch (\Throwable $e) {
    // Same defense-in-depth as the original: if 'Refunded' somehow isn't
    // accepted by the enum, cancel the order regardless so the person
    // isn't stuck, and log the real cause instead of failing silently.
    error_log("Order #{$orderId}: could not set payment_status='Refunded' - " . $e->getMessage());
    $stmt = $conn->prepare("UPDATE orders SET order_status = 'Cancelled' WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $stmt->close();
}

// Work out who to email the cancellation confirmation to.
$recipientEmail = $order['guest_email'];
if ($user) {
    $recipientEmail = $user['email'] ?? $recipientEmail;
}

$emailSent = false;
if ($recipientEmail) {
    // Email copy copied verbatim from chatbot_api.php's cancel_order case.
    $refundNote = $wasPaid
        ? "Your payment will be refunded to your original payment method (" . htmlspecialchars($order['payment_method']) . ") within 5-7 business days."
        : "No payment had been made yet, so there's nothing to refund.";
    $emailHtml = "<p>Your order <strong>#{$orderId}</strong> has been cancelled as requested.</p><p>{$refundNote}</p>";
    $emailSent = send_mail($recipientEmail, "Order #{$orderId} Cancelled - Araku Tribe", $emailHtml);
}

// OTP is single-use - clear it now that it's been consumed.
chatbot_clear_cancel_otp($conn, $sessionId);

json_ok([
    'order_id' => (int) $orderId,
    'was_paid' => $wasPaid,
    'email_sent' => $emailSent,
    'email' => $recipientEmail,
]);
