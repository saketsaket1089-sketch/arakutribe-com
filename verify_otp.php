<?php
/* =====================================================================
   ARAKU TRIBE - verify_otp.php  (admin sign-in, step 2 of 2)
   Checks the 6-digit code emailed by adminlogin.php. Only a correct,
   unexpired code opens the admin area (sets admin_otp_ok).
   ===================================================================== */
require_once __DIR__ . '/admin_layout3.php';

if (admin3_is_in()) { header('Location: dashboard.php'); exit; }
if (empty($_SESSION['is_admin']) || !isset($_SESSION['otp'])) { header('Location: adminlogin.php'); exit; }   // no sign-in in progress

$error = '';
$info  = '';
if (isset($_SESSION['message'])) { $info = (string)$_SESSION['message']; unset($_SESSION['message']); }

$_SESSION['otp_expire'] = $_SESSION['otp_expire'] ?? time() + 300;
if (($_SESSION['otp_tries_for'] ?? null) !== $_SESSION['otp']) { $_SESSION['otp_tries'] = 0; $_SESSION['otp_tries_for'] = $_SESSION['otp']; }   // a new code (resend) = fresh tries

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = trim($_POST['otp'] ?? '');
    if (!preg_match('/^\d{6}$/', $entered)) {
        $error = 'Please enter the 6-digit code.';
    } elseif (time() > $_SESSION['otp_expire']) {
        $error = 'This code has expired. Please ask for a new one.';
    } elseif (++$_SESSION['otp_tries'] > 5) {
        $error = 'Too many wrong codes. Please sign in again.';
        unset($_SESSION['otp'], $_SESSION['otp_expire'], $_SESSION['otp_tries'], $_SESSION['is_admin'], $_SESSION['role']);
    } elseif (hash_equals((string)$_SESSION['otp'], $entered)) {
        session_regenerate_id(true);
        unset($_SESSION['otp'], $_SESSION['otp_expire'], $_SESSION['otp_tries']);
        $_SESSION['admin_otp_ok']  = true;                      // the admin area is now open
        $_SESSION['LAST_ACTIVITY'] = time();
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'That code is not right. Please check the email and try again.';
    }
}
$remaining = max(0, (int)($_SESSION['otp_expire'] ?? 0) - time());

admin3_auth_open('Enter your code - Araku Tribe admin');
?>
<h1>Enter your code</h1>
<p class="sub">Step 2 of 2: we emailed a 6-digit code to <b><?= admin3_e($_SESSION['admin_email'] ?? 'your email') ?></b>.</p>
<?php if ($info): ?><p class="a3-msg ok" role="status"><?= admin3_e($info) ?></p><?php endif; ?>
<?php if ($error): ?><p class="a3-msg err" role="alert"><?= admin3_e($error) ?></p><?php endif; ?>
<p style="text-align:center;margin:0 0 12px;font-weight:800;color:var(--brown)">Time left: <span id="countdown"></span></p>
<form method="POST" autocomplete="off">
  <div class="a3-field">
    <label for="otpInput" style="text-align:center">6-digit code</label>
    <input type="text" name="otp" id="otpInput" maxlength="6" inputmode="numeric" pattern="\d{6}" required autofocus autocomplete="one-time-code"
           style="text-align:center;font-size:24px;letter-spacing:10px;font-weight:900">
  </div>
  <button type="submit" class="btn btn-primary btn-lg" id="verifyBtn">Verify and open the dashboard</button>
</form>
<p class="a3-links">Didn't get it? <a href="resend_otp.php">Send a new code</a> · <a href="adminlogin.php">Start again</a></p>
<script>
let remaining = <?= $remaining ?>;
const out = document.getElementById('countdown'), btn = document.getElementById('verifyBtn'), inp = document.getElementById('otpInput');
(function tick() {
  if (remaining <= 0) { out.textContent = 'Expired'; btn.disabled = true; inp.disabled = true; return; }
  out.textContent = Math.floor(remaining / 60) + ':' + String(remaining % 60).padStart(2, '0');
  remaining--; setTimeout(tick, 1000);
})();
</script>
<?php admin3_auth_close(); ?>