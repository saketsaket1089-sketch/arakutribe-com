<?php
session_start();
require_once 'db1.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer_lib/PHPMailer.php';
require 'phpmailer_lib/SMTP.php';
require 'phpmailer_lib/Exception.php';

// --- PHPMailer Settings ---
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'vegiprasanthi697@gmail.com');
define('SMTP_PASSWORD', 'bhfqbeglekuxoppn'); 
define('SMTP_PORT', 465); 
define('SENDER_EMAIL', 'vegiprasanthi697@gmail.com');
define('SENDER_NAME', 'Araku Tribe Support');

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['email'])) {
    $_SESSION['error_message'] = "Invalid request.";
    header('Location: forgot_password.php');
    exit();
}

$email = trim($_POST['email']);
$user_exists = false;
$token = null;

// 1. Check if user exists 
$stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $user_exists = true;
}
$stmt->close();

if ($user_exists) {
    // 2. Generate and store token ONLY if user exists
    $token = bin2hex(random_bytes(32));
    $expires = date("Y-m-d H:i:s", time() + 3600); // 1 hour expiration

    // Delete old tokens (Prevents link spamming)
    $del_stmt = $conn->prepare("DELETE FROM password_resets WHERE user_email = ?");
    $del_stmt->bind_param("s", $email);
    if (!$del_stmt->execute()) {
        error_log("Token DELETE Failed: " . $del_stmt->error);
    }
    $del_stmt->close();

    // Insert new token
    $ins_stmt = $conn->prepare("INSERT INTO password_resets (user_email, token, expires) VALUES (?, ?, ?)");
    $ins_stmt->bind_param("sss", $email, $token, $expires);
    if (!$ins_stmt->execute()) {
        error_log("Token INSERT Failed for email $email: " . $ins_stmt->error);
        $_SESSION['error_message'] = "An error occurred. Please try again later.";
        header('Location: forgot_password.php');
        exit();
    }
    $ins_stmt->close();

    // 3. Generate reset link dynamically 
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
    $base_url = $protocol . $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    $reset_link = $base_url . rtrim($path, '/') . "/reset_password.php?token=" . urlencode($token);

    // 4. Send email using PHPMailer
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = (SMTP_PORT == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Password Reset Request - Araku Tribe Coffee";
        
        // HTML email body
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #fff; }
                .header { background: #6F4E37; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { background: #f9f9f9; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 14px 30px; background: #6F4E37; color: white !important; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                .button:hover { background: #5a3a2a; }
                .link-box { word-break: break-all; background: white; padding: 12px; border-radius: 5px; border: 1px solid #ddd; font-size: 13px; color: #6F4E37; }
                .footer { text-align: center; margin-top: 20px; color: #777; font-size: 12px; border-top: 1px solid #e0e0e0; padding-top: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>☕ Araku Tribe Coffee</h1>
                    <p style='margin: 10px 0 0 0; font-size: 16px;'>Password Reset Request</p>
                </div>
                <div class='content'>
                    <p>Hello,</p>
                    <p>We received a request to reset your password for your Araku Tribe Coffee account.</p>
                    <p>Click the button below to reset your password:</p>
                    <p style='text-align: center;'>
                        <a href='$reset_link' class='button'>Reset My Password</a>
                    </p>
                    <p>Or copy and paste this link into your browser:</p>
                    <p class='link-box'>$reset_link</p>
                    <p style='background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; border-radius: 4px;'><strong>⏰ This link will expire in 1 hour.</strong></p>
                    <p>If you didn't request a password reset, please ignore this email or contact our support team if you have concerns.</p>
                    <p>Best regards,<br><strong>Araku Tribe Coffee Team</strong></p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Araku Tribe Coffee. All rights reserved.</p>
                    <p style='margin-top: 5px;'>Bringing authentic coffee from the hills of Araku Valley</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Plain text alternative
        $mail->AltBody = "Hello,\n\nWe received a request to reset your password.\n\nClick the link below to reset your password:\n$reset_link\n\nThis link expires in 1 hour.\n\nIf you didn't request this, please ignore this email.\n\nBest regards,\nAraku Tribe Coffee Team";

        $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Error to $email: " . $e->getMessage());
        // Don't reveal to user whether email sending failed
    }
}

$conn->close();

// 5. Unified, safe success message (same message whether user exists or not - prevents email enumeration)
$_SESSION['success_message'] = "If that email address is registered, you will receive a password reset email shortly. Please check your inbox and spam folder.";

header('Location: forgot_password.php');
exit();
?>