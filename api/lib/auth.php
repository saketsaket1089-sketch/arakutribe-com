<?php
/**
 * api/lib/auth.php
 * Bearer-token auth for the stateless mobile API (see api_contract.md's
 * "Auth model"). Tokens live in the `api_tokens` table (already created in
 * the live DB). Also holds the base64url + OTP-ticket helpers used by the
 * two-step login flow.
 */

function generate_api_token() {
    return bin2hex(random_bytes(32));
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

/** Builds a signed, stateless OTP ticket per the contract's Auth model. */
function build_otp_ticket($userId, $otp, $ttlSeconds = 300) {
    $payload = base64url_encode(json_encode([
        'uid' => (int) $userId,
        'otp' => (string) $otp,
        'exp' => time() + $ttlSeconds,
    ]));
    $sig = hash_hmac('sha256', $payload, OTP_TICKET_SECRET);
    return $payload . '.' . $sig;
}

/**
 * Verifies a ticket's HMAC signature only (ignores expiry) and returns the
 * decoded payload array, or null if the signature/shape is invalid.
 */
function decode_otp_ticket($ticket) {
    if (!is_string($ticket) || $ticket === '') return null;
    $parts = explode('.', $ticket, 2);
    if (count($parts) !== 2) return null;
    [$payload, $sig] = $parts;

    $expectedSig = hash_hmac('sha256', $payload, OTP_TICKET_SECRET);
    if (!hash_equals($expectedSig, $sig)) return null;

    $data = json_decode(base64url_decode($payload), true);
    if (!is_array($data) || !isset($data['uid'], $data['otp'], $data['exp'])) return null;

    return $data;
}

function get_bearer_token() {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k => $v) {
            if (strtolower($k) === 'authorization') {
                $header = $v;
                break;
            }
        }
    }
    if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        return $m[1];
    }
    return null;
}

/** Returns the current user's row (no password stripping - callers decide), or null if unauthenticated. */
function current_user($conn) {
    $token = get_bearer_token();
    if (!$token) return null;

    $stmt = $conn->prepare("SELECT u.* FROM api_tokens t JOIN users u ON u.user_id = t.user_id WHERE t.token = ? AND t.expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

/** Enforces auth for protected endpoints: emits 401/403 and exits if not a valid, non-deleted user. */
function require_auth($conn) {
    $user = current_user($conn);
    if (!$user) {
        json_error('INVALID_TOKEN', 'Missing or invalid authorization token.', 401);
    }
    if (($user['account_status'] ?? 'active') === 'deleted') {
        json_error('ACCOUNT_DELETED', 'This account has been deleted.', 403);
    }
    return $user;
}

/**
 * Issues a fresh api_tokens row for this user (same 90-day expiry as
 * auth/verify-otp.php) and returns the raw token string. Used by
 * chatbot/guest/verify-otp.php to passwordlessly log a verified guest
 * into the whole app once they've proven control of a registered email
 * via OTP, not just into that one chat session.
 */
function issue_api_token($conn, $userId) {
    $token = generate_api_token();
    $deviceInfo = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $stmt = $conn->prepare("INSERT INTO api_tokens (user_id, token, device_info, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 90 DAY))");
    $stmt->bind_param("iss", $userId, $token, $deviceInfo);
    $stmt->execute();
    $stmt->close();
    return $token;
}
