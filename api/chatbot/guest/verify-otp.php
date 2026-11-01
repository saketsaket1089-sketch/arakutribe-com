<?php
/**
 * api/chatbot/guest/verify-otp.php
 * Mirrors chatbot_api.php's `verify_guest_otp` case, PLUS the addendum's
 * intentional improvement: when match_type === 'user', also issue a real
 * api_tokens row and return {"token": "..."} so the verified guest is
 * passwordlessly logged into the whole app (not just this chat), unlike
 * the original which only set $_SESSION['user_id'] for that one browser
 * tab. POST {session_id, otp}. Public (this IS the login step for a
 * verified account holder).
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/chatbot.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = trim((string) ($input['session_id'] ?? ''));
$otpInput = trim((string) ($input['otp'] ?? ''));

if ($sessionId === '') {
    json_error('VALIDATION_ERROR', 'session_id is required.', 400);
}

$session = chatbot_get_session($conn, $sessionId);

if (
    !$session
    || empty($session['verify_otp'])
    || empty($session['verify_match_type'])
    || chatbot_otp_expired($session['verify_expire'])
) {
    json_error('OTP_EXPIRED', 'That code has expired. Please try again or continue as a guest.', 410);
}

if (!hash_equals((string) $session['verify_otp'], $otpInput)) {
    json_error('INVALID_OTP', 'Incorrect code. Please try again.', 401);
}

$matchType = $session['verify_match_type'];
$verifiedEmail = $session['verify_email'];
$response = ['match_type' => $matchType];

if ($matchType === 'user') {
    $verifiedUserId = (int) $session['verify_user_id'];
    $stmt = $conn->prepare("SELECT user_id, account_status FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $verifiedUserId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u || ($u['account_status'] ?? 'active') === 'deleted') {
        json_error('NOT_FOUND', 'This account is no longer available.', 404);
    }

    $response['token'] = issue_api_token($conn, $verifiedUserId);
} else {
    // Verified repeat guest (no account) - surface their previous
    // shipping address(es) as choices, but they remain a guest.
    $stmt = $conn->prepare(
        "SELECT DISTINCT shipping_address
         FROM orders
         WHERE guest_email = ? AND shipping_address IS NOT NULL AND shipping_address <> ''
         ORDER BY order_date DESC
         LIMIT 5"
    );
    $stmt->bind_param("s", $verifiedEmail);
    $stmt->execute();
    $res = $stmt->get_result();
    $addresses = [];
    while ($row = $res->fetch_assoc()) {
        $addresses[] = $row['shipping_address'];
    }
    $stmt->close();
    $response['addresses'] = $addresses;
}

// OTP is single-use - clear it now that it's been consumed (leaves
// verify_match_type/verify_email in place so saved-addresses.php keeps
// working for a verified guest for the rest of this chat session).
chatbot_clear_guest_otp($conn, $sessionId);

json_ok($response);
