<?php
/**
 * api/chatbot/send-cancel-otp.php
 * Mirrors chatbot_api.php's `send_cancel_otp` case (the block right after
 * `lookup_order`, before `cancel_order` in the original file): same
 * ownership check as lookup_order, same eligibility re-check (still
 * Processing/Placed, within 15 days), and the OTP is emailed to whichever
 * address actually owns the order (the account's own email, or the guest
 * email on file) - never to whatever the caller just typed. The OTP state
 * is stored in chatbot_sessions for the given session_id, since there's no
 * PHP session to hold it in this stateless API.
 *
 * Auth optional (same ownership rule as lookup-order.php).
 * POST {session_id, order_id, email?}.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/chatbot.php';

$user = current_user($conn); // auth optional
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$sessionId = trim((string) ($input['session_id'] ?? ''));
$orderId = preg_replace('/\D/', '', trim((string) ($input['order_id'] ?? '')));
$verifyEmail = trim((string) ($input['email'] ?? ''));

if ($sessionId === '') {
    json_error('VALIDATION_ERROR', 'session_id is required.', 400);
}
if ($orderId === '') {
    json_error('VALIDATION_ERROR', 'Please enter a valid order ID.', 400);
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
$recipientEmail = null;

if ($order['user_id'] !== null && $user && (int) $order['user_id'] === (int) $user['user_id']) {
    $ownsOrder = true;
    $recipientEmail = $user['email'] ?? null;
} elseif ($order['user_id'] === null && !empty($order['guest_email']) && $verifyEmail !== '' && strcasecmp($verifyEmail, $order['guest_email']) === 0) {
    $ownsOrder = true;
    $recipientEmail = $order['guest_email'];
}

if (!$ownsOrder || !$recipientEmail) {
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

$otp = (string) random_int(100000, 999999);
$expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes
chatbot_save_cancel_otp($conn, $sessionId, (int) $orderId, $otp, $recipientEmail, $expiresAt);

// Email copy copied verbatim from chatbot_api.php's send_cancel_otp case.
$emailHtml = "<h2>{$otp}</h2><p>Enter this code in the chat to confirm cancelling order #{$orderId}. Valid for 5 minutes.</p><p>If you didn't request this, you can safely ignore this email - nothing will be cancelled.</p>";
$sent = send_mail($recipientEmail, "Confirm cancellation of Order #{$orderId}", $emailHtml);

if (!$sent) {
    json_error('MAIL_FAILED', 'Could not send a verification code right now. Please try again.', 502);
}

json_ok(['email' => $recipientEmail]);
