<?php
session_start();
include 'db1.php'; 

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- 1. Admin Authorization and Session Check ---
// The redirect happens here if $_SESSION['admin_email'] is NOT set.
if (!isset($_SESSION['admin_email'])) {
    // Session expired or invalid for admin.
    $_SESSION['message'] = "Admin session expired or invalid. Please log in again.";
    
    // Redirect to the admin login page (as specified by you).
    header("Location: adminlogin.php"); 
    exit;
}

$email_to_send = $_SESSION['admin_email'];

// --- 2. Generate New OTP and Update Session ---
$new_otp = rand(100000, 999999);

$_SESSION['otp'] = $new_otp;
$_SESSION['otp_expire'] = time() + 300; // 5 minutes validity

// --- 3. Send Email ---
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'aiinfoways.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'support@hayward.university';
    $mail->Password   = 'zh9nCzzB1ds_';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('info@aiinfoways.com', 'Admin System');
    $mail->addAddress($email_to_send);
    $mail->isHTML(true);
    $mail->Subject = 'Your New Admin OTP Code (Resent)';
    $mail->Body    = "Your **new Admin OTP** code is **" . $new_otp . "**. It is valid for 5 minutes. - Araku Tribe Team";

    $mail->send();

    // 4. Set success message and redirect back to verify_otp.php
    $_SESSION['message'] = "A new OTP has been successfully sent to your admin email address.";
    header("Location: verify_otp.php");
    exit;

} catch (Exception $e) {
    // 5. Handle email sending failure
    $error_message = "Failed to resend OTP to admin email: " . $mail->ErrorInfo;

    $_SESSION['message'] = $error_message;
    header("Location: verify_otp.php");
    exit;
}
?>