<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim((string) ($input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($email === '' || $password === '') {
    json_error('VALIDATION_ERROR', 'Email and password are required.', 400);
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password'] ?? '')) {
    json_error('INVALID_CREDENTIALS', 'Invalid email or password.', 401);
}

if (($user['account_status'] ?? 'active') === 'deleted') {
    json_error('ACCOUNT_DELETED', 'This account has been deleted.', 403);
}

$otp = (string) random_int(100000, 999999);
$ticket = build_otp_ticket($user['user_id'], $otp, 300);

send_mail(
    $user['email'],
    'Your Araku Tribe OTP Code',
    "<h2>{$otp}</h2><p>This code is valid for 5 minutes.</p>"
);

json_ok(['ticket' => $ticket, 'expires_in' => 300]);
