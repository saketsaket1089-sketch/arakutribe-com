<?php
/**
 * api/profile/update.php
 * multipart/form-data (to allow an optional `photo` file) - reads $_POST /
 * $_FILES, not the JSON body. Email format is validated but NOT restricted
 * to gmail/yahoo like the original edit_profile.php - a deliberate
 * improvement for a mobile app, per the API contract.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/profile.php';

$user = require_auth($conn);

$firstname = trim((string) ($_POST['firstname'] ?? ''));
$lastname = trim((string) ($_POST['lastname'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phonenumber = trim((string) ($_POST['phonenumber'] ?? ''));
$area = trim((string) ($_POST['area'] ?? ''));
$landmark = trim((string) ($_POST['landmark'] ?? ''));
$zipcode = trim((string) ($_POST['zipcode'] ?? ''));

if ($firstname === '' || $lastname === '') {
    json_error('VALIDATION_ERROR', 'First and last name are required.', 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('VALIDATION_ERROR', 'Please enter a valid email address.', 400);
}
if (!preg_match('/^[0-9]{10}$/', $phonenumber)) {
    json_error('VALIDATION_ERROR', 'Phone number must be exactly 10 digits.', 400);
}
if ($area === '' || $zipcode === '') {
    json_error('VALIDATION_ERROR', 'Area and zipcode are required.', 400);
}

$checkEmail = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
$checkEmail->bind_param("si", $email, $user['user_id']);
$checkEmail->execute();
$taken = $checkEmail->get_result()->fetch_assoc();
$checkEmail->close();

if ($taken) {
    json_error('EMAIL_EXISTS', 'This email is already in use.', 409);
}

$sets = ['firstname = ?', 'lastname = ?', 'email = ?', 'phonenumber = ?', 'area = ?', 'landmark = ?', 'zipcode = ?'];
$params = [$firstname, $lastname, $email, $phonenumber, $area, $landmark, $zipcode];
$types = 'sssssss';

if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['photo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png'];
    $maxBytes = 2 * 1024 * 1024;

    if (!in_array($ext, $allowed, true)) {
        json_error('VALIDATION_ERROR', 'Only JPG, JPEG, and PNG images are allowed.', 400);
    }
    if ($file['size'] > $maxBytes) {
        json_error('VALIDATION_ERROR', 'Image must be smaller than 2MB.', 400);
    }
    if (!@getimagesize($file['tmp_name'])) {
        json_error('VALIDATION_ERROR', 'Uploaded file is not a valid image.', 400);
    }

    $targetDir = dirname(__DIR__, 2) . '/uploads/profile_pictures/';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    $newName = uniqid('pp_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $targetDir . $newName)) {
        json_error('SERVER_ERROR', 'Could not save the uploaded image.', 500);
    }

    $sets[] = 'profile_picture = ?';
    $params[] = $newName;
    $types .= 's';
}

$params[] = $user['user_id'];
$types .= 'i';

$sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE user_id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->close();

$fetch = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$fetch->bind_param("i", $user['user_id']);
$fetch->execute();
$updatedUser = $fetch->get_result()->fetch_assoc();
$fetch->close();

json_ok(format_profile($updatedUser));
