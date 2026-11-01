<?php
/* =====================================================================
   ARAKU TRIBE - adminlogin.php  (admin sign-in, step 1 of 2)
   Email + password + picture code. If correct, a 6-digit code is emailed
   and the admin goes to verify_otp.php (step 2). The admin area only
   opens after that code is verified (admin_otp_ok, see admin_layout3.php).
   ===================================================================== */
require_once __DIR__ . '/admin_layout3.php';
include 'db1.php';

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// EMAIL (SMTP) SETTINGS for sending the code.
// IMPORTANT: this password was shared in a chat - change it at your email host and put the new one here.
const A3_SMTP_HOST = 'aiinfoways.com';
const A3_SMTP_USER = 'support@hayward.university';
const A3_SMTP_PASS = 'zh9nCzzB1ds_';
const A3_SMTP_FROM = 'info@aiinfoways.com';

if (admin3_is_in()) { header('Location: dashboard.php'); exit; }   // already signed in

$error = '';

/* picture code (CAPTCHA) */
function generateCaptchaImage() {
    if (!isset($_SESSION['admin_captcha_code'])) $_SESSION['admin_captcha_code'] = substr(md5(mt_rand()), 0, 6);
    $code  = $_SESSION['admin_captcha_code'];
    $image = imagecreatetruecolor(150, 40);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    $text  = imagecolorallocate($image, 90, 56, 30);
    $line  = imagecolorallocate($image, 150, 150, 150);
    for ($i = 0; $i < 5; $i++) imageline($image, 0, rand(0, 40), 150, rand(0, 40), $line);
    if (file_exists('arial.ttf')) imagettftext($image, 20, rand(-5, 5), 10, 30, $text, 'arial.ttf', $code);
    else imagestring($image, 5, 40, 10, $code, $text);
    ob_start(); imagepng($image); $data = ob_get_clean(); imagedestroy($image);
    return 'data:image/png;base64,' . base64_encode($data);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha  = trim($_POST['captcha'] ?? '');
    $expected = $_SESSION['admin_captcha_code'] ?? null;

    if ($captcha === '') {
        $error = 'Please enter the picture code.';
    } elseif (!$expected || strcasecmp($captcha, $expected) !== 0) {
        $error = 'The picture code is wrong. Please try the new one.';
        unset($_SESSION['admin_captcha_code']);
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        unset($_SESSION['admin_captcha_code']);

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $otp = random_int(100000, 999999);
            $_SESSION['otp']           = $otp;
            $_SESSION['otp_expire']    = time() + 300;            // 5 minutes
            $_SESSION['id']            = $admin['id'];
            $_SESSION['admin_email']   = $admin['email'];
            $_SESSION['is_admin']      = true;
            $_SESSION['role']          = $admin['role'];
            $_SESSION['LAST_ACTIVITY'] = time();
            unset($_SESSION['admin_otp_ok']);                     // admin area stays closed until the code is verified

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = A3_SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = A3_SMTP_USER;
                $mail->Password   = A3_SMTP_PASS;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->setFrom(A3_SMTP_FROM);
                $mail->addAddress($admin['email']);
                $mail->isHTML(true);
                $mail->Subject = 'Your Araku Tribe admin code';
                $mail->Body    = "Hi Admin,<br>Your code is <b>$otp</b>. It is valid for 5 minutes.<br>Araku Tribe Team";
                $mail->send();
                header('Location: verify_otp.php');
                exit;
            } catch (Exception $e) {
                $error = 'We could not email your code. Please try again in a minute.';
            }
        } else {
            $error = 'Wrong email or password.';
        }
    }
}
$conn->close();
$captcha_src = generateCaptchaImage();

admin3_auth_open('Admin sign in - Araku Tribe');
?>
<h1>Admin sign in</h1>
<p class="sub">Step 1 of 2: we'll email you a 6-digit code.</p>
<?php if ($error): ?><p class="a3-msg err" role="alert"><?= admin3_e($error) ?></p><?php endif; ?>
<form method="POST" autocomplete="on">
  <div class="a3-field"><label for="email">Email</label><input type="email" name="email" id="email" required autocomplete="username" value="<?= admin3_e($_POST['email'] ?? '') ?>"></div>
  <div class="a3-field"><label for="password">Password</label><input type="password" name="password" id="password" required autocomplete="current-password"></div>
  <div class="a3-field">
    <label for="captcha">Type the code in the picture</label>
    <div class="a3-cap" style="margin-bottom:8px"><img src="<?= $captcha_src ?>" alt="Picture code" title="Click for a new code" onclick="location.reload()"><button type="button" title="New code" aria-label="New picture code" onclick="location.reload()">↻</button></div>
    <input type="text" name="captcha" id="captcha" required autocomplete="off" maxlength="6">
  </div>
  <button type="submit" class="btn btn-primary btn-lg">Sign in</button>
</form>
<p class="a3-links"><a href="forgot.php">Forgot password?</a></p>
<?php admin3_auth_close(); ?>