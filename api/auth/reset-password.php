<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim((string) ($input['email'] ?? ''));
$code = trim((string) ($input['code'] ?? ''));
$newPassword = (string) ($input['new_password'] ?? '');

if ($email === '' || $code === '' || strlen($newPassword) < 6) {
    json_error('VALIDATION_ERROR', 'Email, code, and a password of at least 6 characters are required.', 400);
}

$stmt = $conn->prepare("SELECT * FROM password_resets WHERE user_email = ? AND token = ? AND expires > NOW()");
$stmt->bind_param("ss", $email, $code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    json_error('INVALID_OR_EXPIRED_CODE', 'That reset code is invalid or has expired.', 400);
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$upd = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$upd->bind_param("ss", $hash, $email);
$upd->execute();
$upd->close();

$del = $conn->prepare("DELETE FROM password_resets WHERE id = ?");
$del->bind_param("i", $row['id']);
$del->execute();
$del->close();

json_ok(['message' => 'Password updated.']);
