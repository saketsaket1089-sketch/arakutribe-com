<?php
require_once __DIR__ . '/../config.php';

$user = require_auth($conn);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$currentPassword = (string) ($input['current_password'] ?? '');
$newPassword = (string) ($input['new_password'] ?? '');

if (!password_verify($currentPassword, $user['password'] ?? '')) {
    json_error('INVALID_CREDENTIALS', 'Current password is incorrect.', 401);
}
if (strlen($newPassword) < 6) {
    json_error('VALIDATION_ERROR', 'New password must be at least 6 characters.', 400);
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
$stmt->bind_param("si", $hash, $user['user_id']);
$stmt->execute();
$stmt->close();

json_ok(['message' => 'Password changed successfully.']);
