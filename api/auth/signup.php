<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$firstname = trim((string) ($input['firstname'] ?? ''));
$lastname = trim((string) ($input['lastname'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$phonenumber = trim((string) ($input['phonenumber'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($firstname === '' || $lastname === '') {
    json_error('VALIDATION_ERROR', 'First and last name are required.', 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('VALIDATION_ERROR', 'Please enter a valid email address.', 400);
}
if (!preg_match('/^[0-9]{10}$/', $phonenumber)) {
    json_error('VALIDATION_ERROR', 'Phone number must be exactly 10 digits.', 400);
}
if (strlen($password) < 6) {
    json_error('VALIDATION_ERROR', 'Password must be at least 6 characters.', 400);
}

$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($exists) {
    json_error('EMAIL_EXISTS', 'This email is already registered.', 409);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$insert = $conn->prepare("INSERT INTO users (firstname, lastname, email, phonenumber, password) VALUES (?, ?, ?, ?, ?)");
$insert->bind_param("sssss", $firstname, $lastname, $email, $phonenumber, $hash);

if (!$insert->execute()) {
    error_log('signup insert failed: ' . $insert->error);
    json_error('SERVER_ERROR', 'Signup failed. Please try again.', 500);
}
$insert->close();

json_ok(['message' => 'Account created. Please log in.']);
