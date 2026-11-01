<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$ticket = (string) ($input['ticket'] ?? '');
$otpInput = trim((string) ($input['otp'] ?? ''));

$data = decode_otp_ticket($ticket);
if ($data === null) {
    json_error('INVALID_TICKET', 'This ticket is invalid or malformed.', 401);
}

if (time() > (int) $data['exp']) {
    json_error('OTP_EXPIRED', 'This OTP has expired. Please request a new one.', 410);
}

if (!hash_equals((string) $data['otp'], $otpInput)) {
    json_error('INVALID_OTP', 'Incorrect OTP.', 401);
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $data['uid']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    json_error('INVALID_TICKET', 'This ticket is invalid or malformed.', 401);
}

if (($user['account_status'] ?? 'active') === 'deleted') {
    json_error('ACCOUNT_DELETED', 'This account has been deleted.', 403);
}

$token = generate_api_token();
$deviceInfo = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

$insert = $conn->prepare("INSERT INTO api_tokens (user_id, token, device_info, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 90 DAY))");
$insert->bind_param("iss", $user['user_id'], $token, $deviceInfo);
$insert->execute();
$insert->close();

json_ok([
    'token' => $token,
    'user' => [
        'user_id' => (int) $user['user_id'],
        'firstname' => $user['firstname'],
        'lastname' => $user['lastname'],
        'email' => $user['email'],
        'phonenumber' => $user['phonenumber'],
        'area' => $user['area'],
        'landmark' => $user['landmark'],
        'zipcode' => $user['zipcode'],
        'profile_picture' => $user['profile_picture'],
        'preferred_currency' => $user['preferred_currency'],
    ],
]);
