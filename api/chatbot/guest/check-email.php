<?php
/**
 * api/chatbot/guest/check-email.php
 * Mirrors chatbot_api.php's `check_email_and_send_otp` case exactly:
 * registered-user check -> guest-order-history check -> OTP email if
 * either matched, generic "proceed as new guest" if neither. State goes
 * into chatbot_sessions for this session_id (5 min expiry) instead of
 * $_SESSION. POST {session_id, email}. Public.
 *
 * Security note (from the original): we never hand over saved addresses
 * just because someone typed a matching email - registered account or
 * repeat guest, we send a short-lived OTP to that inbox first and only
 * reveal any saved address once they prove they control it.
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/chatbot.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = trim((string) ($input['session_id'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));

if ($sessionId === '') {
    json_error('VALIDATION_ERROR', 'session_id is required.', 400);
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('VALIDATION_ERROR', 'Please enter a valid email address.', 400);
}

$matchType = 'none';
$targetName = '';
$targetUserId = null;

// 1. Registered account?
$stmt = $conn->prepare("SELECT user_id, firstname FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$foundUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($foundUser) {
    $matchType = 'user';
    $targetUserId = (int) $foundUser['user_id'];
    $targetName = $foundUser['firstname'] ?? '';
} else {
    // 2. No account - but have they ordered as a guest before?
    $stmt = $conn->prepare("SELECT 1 FROM orders WHERE guest_email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $hasGuestOrders = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($hasGuestOrders) {
        $matchType = 'guest';
    }
}

if ($matchType === 'none') {
    // Nothing on file for this email - nothing to verify, proceed as a new guest.
    json_ok(['match_type' => 'none']);
}

$otp = (string) random_int(100000, 999999);
$expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes
chatbot_save_guest_otp($conn, $sessionId, $otp, $email, $matchType, $targetUserId, $expiresAt);

// Email copy copied verbatim from chatbot_api.php's check_email_and_send_otp case.
$sent = send_mail(
    $email,
    'Your Araku Tribe Verification Code',
    "<h2>{$otp}</h2><p>Enter this code in the chat to access your saved address. Valid for 5 minutes.</p>"
);

if (!$sent) {
    json_error('MAIL_FAILED', 'Could not send a verification code right now. You can continue as a guest instead.', 502);
}

json_ok(['match_type' => $matchType]);
