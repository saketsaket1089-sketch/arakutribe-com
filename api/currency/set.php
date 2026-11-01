<?php
require_once __DIR__ . '/../config.php';

$user = require_auth($conn);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$currency = strtoupper(trim((string) ($input['currency'] ?? '')));

if (!array_key_exists($currency, $CURRENCY_INFO)) {
    json_error('VALIDATION_ERROR', 'Unsupported currency code.', 400);
}

$stmt = $conn->prepare("UPDATE users SET preferred_currency = ? WHERE user_id = ?");
$stmt->bind_param("si", $currency, $user['user_id']);
$stmt->execute();
$stmt->close();

json_ok(['message' => 'Currency preference updated.', 'currency' => $currency]);
