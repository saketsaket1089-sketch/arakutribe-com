<?php
/**
 * api/chatbot/saved-addresses.php
 * Mirrors chatbot_api.php's `get_saved_addresses` case, extended per the
 * addendum for guests: auth optional - if a valid bearer token is
 * present, addresses come from orders.user_id. Else, if this session's
 * chatbot_sessions.verify_match_type='guest' (i.e. they verified via OTP
 * earlier in THIS chat via guest/check-email.php + guest/verify-otp.php),
 * addresses come from orders.guest_email = verify_email. Otherwise []ups.
 *
 * GET ?session_id=.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/chatbot.php';

$user = current_user($conn); // auth optional
$sessionId = trim((string) ($_GET['session_id'] ?? ''));

if ($user) {
    $stmt = $conn->prepare(
        "SELECT DISTINCT shipping_address
         FROM orders
         WHERE user_id = ? AND shipping_address IS NOT NULL AND shipping_address <> ''
         ORDER BY order_date DESC
         LIMIT 5"
    );
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $addresses = [];
    while ($row = $res->fetch_assoc()) {
        $addresses[] = $row['shipping_address'];
    }
    $stmt->close();
    json_ok(['addresses' => $addresses]);
}

if ($sessionId !== '') {
    $session = chatbot_get_session($conn, $sessionId);
    if ($session && ($session['verify_match_type'] ?? '') === 'guest' && !empty($session['verify_email'])) {
        $verifiedEmail = $session['verify_email'];
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
        json_ok(['addresses' => $addresses]);
    }
}

json_ok(['addresses' => []]);
