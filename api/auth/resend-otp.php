<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$ticket = (string) ($input['ticket'] ?? '');

// Signature-only check - a stale (expired) ticket can still be resent from.
$data = decode_otp_ticket($ticket);
if ($data === null) {
    json_error('INVALID_TICKET', 'This ticket is invalid or malformed.', 401);
}

$stmt = $conn->prepare("SELECT user_id, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $data['uid']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    json_error('INVALID_TICKET', 'This ticket is invalid or malformed.', 401);
}

$otp = (string) random_int(100000, 999999);
$newTicket = build_otp_ticket($user['user_id'], $otp, 300);

send_mail(
    $user['email'],
    'Your Araku Tribe OTP Code',
    "<h2>{$otp}</h2><p>This code is valid for 5 minutes.</p>"
);

json_ok(['ticket' => $newTicket, 'expires_in' => 300]);
