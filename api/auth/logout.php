<?php
require_once __DIR__ . '/../config.php';

$user = require_auth($conn);
$token = get_bearer_token();

$stmt = $conn->prepare("DELETE FROM api_tokens WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$stmt->close();

json_ok(['message' => 'Logged out.']);
