<?php
/**
 * api/account/delete.php
 * Soft-delete exactly like the existing delete_account.php, then also
 * revoke every outstanding api_tokens row for this user so their mobile
 * sessions are cut off immediately (delete_account.php's session-based
 * equivalent is session_destroy(), which we have no session for here).
 */
require_once __DIR__ . '/../config.php';

$user = require_auth($conn);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$password = (string) ($input['password'] ?? '');
$reason = trim((string) ($input['deletion_reason'] ?? $input['reason'] ?? ''));

if (!password_verify($password, $user['password'] ?? '')) {
    json_error('INVALID_CREDENTIALS', 'Incorrect password.', 401);
}

$stmt = $conn->prepare(
    "UPDATE users SET account_status = 'pending_deletion', deletion_requested_at = NOW(), deletion_reason = ? WHERE user_id = ?"
);
$stmt->bind_param("si", $reason, $user['user_id']);
$stmt->execute();
$stmt->close();

$del = $conn->prepare("DELETE FROM api_tokens WHERE user_id = ?");
$del->bind_param("i", $user['user_id']);
$del->execute();
$del->close();

json_ok(['message' => 'Account scheduled for deletion.']);
