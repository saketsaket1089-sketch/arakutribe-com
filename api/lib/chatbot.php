<?php
/**
 * api/lib/chatbot.php
 * Shared helpers for the chatbot v2 endpoints (api/chatbot/**). Replaces
 * chatbot_api.php's $_SESSION-based agent_cart / guest-verify-OTP /
 * cancel-OTP state with the same data keyed by a client-generated
 * session_id in the `chatbot_sessions` table (see the v2 contract
 * addendum), since this API is a stateless bearer-token API with no PHP
 * session per caller.
 *
 * All OTP expiry timestamps are computed and compared in PHP (not MySQL's
 * NOW()) so there is no risk of a MySQL-server-vs-PHP timezone mismatch
 * silently mis-expiring a code.
 */

/** Same category label map as the original chatbot_api.php's category_label(). */
function chatbot_category_label($cat) {
    $map = [
        'mug'     => 'Coffee Mugs',
        'cup'     => 'Coffee Cups',
        'bag'     => 'Coffee Bags',
        'tshirt'  => 'T-Shirts',
        't_shirt' => 'T-Shirts',
    ];
    return $map[$cat] ?? ucfirst($cat);
}

/** Opportunistic cleanup of stale rows (>24h old) - call once from a low-traffic entry point (init.php), not a cron job. */
function chatbot_cleanup_expired($conn) {
    $conn->query("DELETE FROM chatbot_sessions WHERE updated_at < NOW() - INTERVAL 1 DAY");
}

/** Fetches this session's chatbot_sessions row, or null if it doesn't exist (treat as "nothing pending"). */
function chatbot_get_session($conn, $sessionId) {
    if ($sessionId === '' || $sessionId === null) return null;
    $stmt = $conn->prepare("SELECT * FROM chatbot_sessions WHERE session_id = ?");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Returns this session's agent cart as an assoc array keyed by product_id, or [] if empty/missing. */
function chatbot_get_cart($conn, $sessionId) {
    $row = chatbot_get_session($conn, $sessionId);
    if (!$row || empty($row['agent_cart'])) return [];
    $decoded = json_decode($row['agent_cart'], true);
    return is_array($decoded) ? $decoded : [];
}

/** Persists this session's agent cart (upsert). */
function chatbot_save_cart($conn, $sessionId, array $cart) {
    $json = json_encode($cart);
    $stmt = $conn->prepare(
        "INSERT INTO chatbot_sessions (session_id, agent_cart) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE agent_cart = VALUES(agent_cart)"
    );
    $stmt->bind_param("ss", $sessionId, $json);
    $stmt->execute();
    $stmt->close();
}

/** Clears this session's agent cart (after a successful place-order). */
function chatbot_clear_cart($conn, $sessionId) {
    $stmt = $conn->prepare("UPDATE chatbot_sessions SET agent_cart = NULL WHERE session_id = ?");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $stmt->close();
}

/** True if a stored DATETIME string (or null/empty) is expired relative to now. */
function chatbot_otp_expired($expireDatetime) {
    if (empty($expireDatetime)) return true;
    return time() > strtotime($expireDatetime);
}

/** Stores the guest-email verification OTP state for this session (upsert). $userId may be null (guest/none match). */
function chatbot_save_guest_otp($conn, $sessionId, $otp, $email, $matchType, $userId, $expiresAt) {
    $stmt = $conn->prepare(
        "INSERT INTO chatbot_sessions (session_id, verify_otp, verify_email, verify_match_type, verify_user_id, verify_expire)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             verify_otp = VALUES(verify_otp),
             verify_email = VALUES(verify_email),
             verify_match_type = VALUES(verify_match_type),
             verify_user_id = VALUES(verify_user_id),
             verify_expire = VALUES(verify_expire)"
    );
    $stmt->bind_param("ssssis", $sessionId, $otp, $email, $matchType, $userId, $expiresAt);
    $stmt->execute();
    $stmt->close();
}

/**
 * Clears only the OTP-specific columns (otp code + expiry) once consumed,
 * on purpose leaving verify_email/verify_match_type in place - saved-
 * addresses.php relies on verify_match_type='guest' + verify_email
 * staying readable for the rest of this chat session after a guest
 * verifies successfully.
 */
function chatbot_clear_guest_otp($conn, $sessionId) {
    $stmt = $conn->prepare(
        "UPDATE chatbot_sessions SET verify_otp = NULL, verify_expire = NULL WHERE session_id = ?"
    );
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $stmt->close();
}

/** Stores the cancel-order OTP state for this session (upsert). */
function chatbot_save_cancel_otp($conn, $sessionId, $orderId, $otp, $email, $expiresAt) {
    $stmt = $conn->prepare(
        "INSERT INTO chatbot_sessions (session_id, cancel_otp_order_id, cancel_otp_code, cancel_otp_email, cancel_otp_expire)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             cancel_otp_order_id = VALUES(cancel_otp_order_id),
             cancel_otp_code = VALUES(cancel_otp_code),
             cancel_otp_email = VALUES(cancel_otp_email),
             cancel_otp_expire = VALUES(cancel_otp_expire)"
    );
    $stmt->bind_param("sisss", $sessionId, $orderId, $otp, $email, $expiresAt);
    $stmt->execute();
    $stmt->close();
}

/** Clears the cancel-order OTP state (single-use, once consumed). */
function chatbot_clear_cancel_otp($conn, $sessionId) {
    $stmt = $conn->prepare(
        "UPDATE chatbot_sessions SET cancel_otp_order_id = NULL, cancel_otp_code = NULL, cancel_otp_email = NULL, cancel_otp_expire = NULL WHERE session_id = ?"
    );
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $stmt->close();
}
