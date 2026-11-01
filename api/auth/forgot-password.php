<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim((string) ($input['email'] ?? ''));

// Anti-enumeration: always the same response, regardless of whether the
// email exists - matches existing site behavior.
if ($email !== '') {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $del = $conn->prepare("DELETE FROM password_resets WHERE user_email = ?");
        $del->bind_param("s", $email);
        $del->execute();
        $del->close();

        $code = (string) random_int(100000, 999999);
        $ins = $conn->prepare("INSERT INTO password_resets (user_email, token, expires) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)");
        $ins->bind_param("ss", $email, $code);
        $ins->execute();
        $ins->close();

        send_mail(
            $email,
            'Araku Tribe Password Reset Code',
            "<h2>{$code}</h2><p>This code is valid for 1 hour.</p>"
        );
    }
}

json_ok(['message' => "If that email exists, we've sent a reset code."]);
