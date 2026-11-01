<?php
session_start();
include 'db1.php'; // Include the database connection file

// Include PHPMailer files
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if the user is in an OTP verification flow
if (!isset($_SESSION['user_email'])) {
    // If essential session variable is missing, redirect to the login page
    $_SESSION['error'] = "Session expired or invalid. Please log in again.";
    header("Location: verify_user_otp.php");
    exit;
}

$email = $_SESSION['user_email'];

// --- Resend OTP Logic ---

// 1. Generate a new OTP and set new expiry time (e.g., 5 minutes)
$new_otp = rand(100000, 999999);
$_SESSION['otp'] = $new_otp;
$_SESSION['otp_expire'] = time() + 300; // New expiry: current time + 300 seconds (5 minutes)

// 2. Send the new OTP via email
$mail = new PHPMailer(true);
try {
    // Server settings (Use the same settings as in verify_user_otp.php)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'thotabhuvaneswari1@gmail.com';
    $mail->Password   = 'hcwe gqpf iulm vqls';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // Recipients and content
    $mail->setFrom('thotabhuvaneswari1@gmail.com', 'User Auth');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Your New User OTP Code (Resent)';
    $mail->Body    = "Your **new** OTP code is " . $new_otp . ". It is valid for 5 minutes. - Your Website Team";

    $mail->send();

    // 3. Redirect back to the OTP verification page with a success message
    $_SESSION['resend_success'] = "A new OTP has been sent to your email address.";
    header("Location: verify_user_otp.php");
    exit;

} catch (Exception $e) {
    // 4. Handle email sending failure
    $error = "Could not resend OTP email: " . $mail->ErrorInfo;

    // Store the error and redirect back to the verification page
    $_SESSION['error'] = $error;
    header("Location: verify_user_otp.php");
    exit;
}
?>