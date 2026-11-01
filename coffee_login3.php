<?php
/* =====================================================================
   ARAKU TRIBE - coffee_login3.php
   Same logic as coffee_login.php:
     email + password + CAPTCHA (generate_captcha.php)
     -> password_verify against `users`
     -> 6-digit code emailed with PHPMailer (valid 5 minutes)
     -> verify_user_otp3.php (signs the customer in only after the code is right)
   Plus "Sign in with Google" (user_login_google.php), forgot password
   and register links. Uses header3.php + footer3.php.
   ===================================================================== */
session_start();

/* ================= DEBUG (turn off on the live site) ================= */
error_reporting(E_ALL);
ini_set('display_errors', 1);
/* ===================================================================== */

include 'db1.php';

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ---------------------------------------------------------------------
   SETTINGS
   Mail login: these were written in coffee_login.php. Please change the
   mailbox password and keep the new one out of shared files.
   --------------------------------------------------------------------- */
$SMTP = [
    'host'       => 'aiinfoways.com',
    'username'   => 'support@hayward.university',
    'password'   => 'zh9nCzzB1ds_',
    'secure'     => 'tls',
    'port'       => 587,
    'from_email' => 'info@aiinfoways.com',
    'from_name'  => 'Araku Tribe',
];
$OTP_MINUTES   = 5;
$VERIFY_PAGE   = 'verify_user_otp3.php';
$GOOGLE_PAGE   = 'user_login_google.php';
$FORGOT_PAGE   = 'forgot_password3.php';
$SIGNUP_PAGE   = 'coffee_signup3.php';
$CAPTCHA_IMAGE = 'generate_captcha.php';

/* ---------------------------------------------------------------------
   Sign-in code email (same in coffee_login3.php and verify_user_otp3.php)
   Table layout + inline styles so it looks right in Gmail, Outlook and
   phone mail apps. Returns [subject, html, plain text].
   --------------------------------------------------------------------- */
function araku_otp_email($otp, $name, $minutes, $toEmail) {
    $code  = (string)$otp;
    $spaced = implode(' ', str_split($code, 3));                  // "637 533" is easier to read
    $first = trim(explode(' ', (string)$name)[0] ?? '');
    $hi    = $first !== '' ? 'Hi ' . htmlspecialchars($first) . ',' : 'Hi there,';
    $when  = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('j M Y, g:i A') . ' IST';
    $digits = '';
    foreach (str_split($code) as $d) {
        $digits .= '<td style="padding:0 4px"><div style="width:44px;height:56px;line-height:56px;border-radius:12px;background:#FFF7ED;border:1px solid #EDE2D6;'
                .  'font-family:Arial,Helvetica,sans-serif;font-size:28px;font-weight:700;color:#3B2317;text-align:center">' . $d . '</div></td>';
    }
    $subject = "$spaced is your Araku Tribe sign-in code";
    $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $subject . '</title></head>'
      . '<body style="margin:0;padding:0;background:#F7EADB">'
      . '<div style="display:none;max-height:0;overflow:hidden;opacity:0">Your code is ' . $spaced . '. It expires in ' . $minutes . ' minutes.</div>'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7EADB;padding:32px 12px"><tr><td align="center">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#FFFFFF;border-radius:20px;overflow:hidden">'
      // brand bar
      . '<tr><td style="background:#3B2317;padding:22px 28px">'
      .   '<span style="font-family:Georgia,\'Times New Roman\',serif;font-size:22px;color:#FFF7ED">Araku Tribe</span>'
      .   '<span style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#F4B63D;padding-left:10px">Coffee from the Araku Valley</span>'
      . '</td></tr>'
      // body
      . '<tr><td style="padding:32px 28px 8px;font-family:Arial,Helvetica,sans-serif;color:#242424">'
      .   '<p style="margin:0 0 6px;font-size:16px">' . $hi . '</p>'
      .   '<h1 style="margin:0 0 12px;font-family:Georgia,\'Times New Roman\',serif;font-weight:400;font-size:26px;color:#6B3E26">Here\'s your sign-in code</h1>'
      .   '<p style="margin:0 0 22px;font-size:15px;line-height:1.55;color:#5E5751">Enter this code on the Araku Tribe website to finish signing in.</p>'
      . '</td></tr>'
      // code
      . '<tr><td align="center" style="padding:4px 20px 6px"><table role="presentation" cellpadding="0" cellspacing="0"><tr>' . $digits . '</tr></table></td></tr>'
      . '<tr><td align="center" style="padding:14px 28px 4px;font-family:Arial,Helvetica,sans-serif">'
      .   '<span style="display:inline-block;padding:7px 14px;border-radius:999px;background:#FFF1D6;color:#6A4A12;font-size:13px;font-weight:700">Expires in ' . $minutes . ' minutes</span>'
      . '</td></tr>'
      // safety
      . '<tr><td style="padding:24px 28px 8px;font-family:Arial,Helvetica,sans-serif">'
      .   '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#E6EFE9;border-radius:14px"><tr><td style="padding:14px 16px;font-size:13.5px;line-height:1.5;color:#2F4F3E">'
      .   '<strong style="color:#2F6B4F">Keep this code private.</strong> Araku Tribe will never call, text or email you to ask for it. '
      .   'If you didn\'t try to sign in, you can ignore this email; your account stays safe.'
      .   '</td></tr></table>'
      . '</td></tr>'
      // footer
      . '<tr><td style="padding:22px 28px 28px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#77716C;border-top:1px solid #EDE2D6">'
      .   'Requested on ' . $when . ' for ' . htmlspecialchars($toEmail) . '.<br>'
      .   'Araku Tribe Coffee, Araku Valley, Visakhapatnam &middot; info@arakutribe.com'
      . '</td></tr>'
      . '</table></td></tr></table></body></html>';
    $text = "$hi\n\nYour Araku Tribe sign-in code is: $code\nIt expires in $minutes minutes.\n\n"
          . "Keep this code private. Araku Tribe will never ask you for it.\n"
          . "If you didn't try to sign in, you can ignore this email.\n\nAraku Tribe Coffee, Araku Valley";
    $text = str_replace(['<', '>'], '', html_entity_decode(strip_tags($text)));
    return [$subject, $html, $text];
}

$error = '';
$emailValue = trim((string)($_GET['email'] ?? ''));   // e.g. coming from sign-up

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email         = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';
    $captcha_input = $_POST['captcha'] ?? '';
    $emailValue    = $email;

    /* CAPTCHA CHECK (same as before: one try per image) */
    $captcha_valid = false;
    if (isset($_SESSION['captcha']) && !empty($captcha_input)) {
        if (strtolower($captcha_input) === strtolower($_SESSION['captcha'])) {
            $captcha_valid = true;
        }
        unset($_SESSION['captcha']);
    }

    if ($email === '' || $password === '') {
        $error = "Please enter both email and password.";
    }
    elseif (!$captcha_valid) {
        $error = "The CAPTCHA didn't match. Please try the new one.";
    }
    else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc();
        $stmt->close();

        if ($userData && password_verify($password, $userData['password'])) {

            // Password is right, but the customer is NOT signed in yet: verify_user_otp3.php
            // sets user_id only after the emailed code is checked.
            unset($_SESSION['user_id'], $_SESSION['user_logged_in']);
            $_SESSION['mfa_pending'] = [
                'user_id' => $userData['user_id'],
                'email'   => $userData['email'],
                'name'    => $userData['firstname'] ?? '',
            ];
            $_SESSION['user_email'] = $userData['email'];
            $_SESSION['user_name']  = $userData['firstname'] ?? '';

            /* OTP */
            $otp = random_int(100000, 999999);
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_expire'] = time() + $OTP_MINUTES * 60;
            $_SESSION['otp_sent_at'] = time();
            $_SESSION['otp_attempts'] = 0;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = $SMTP['host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $SMTP['username'];
                $mail->Password   = $SMTP['password'];
                $mail->SMTPSecure = $SMTP['secure'];
                $mail->Port       = $SMTP['port'];

                $mail->setFrom($SMTP['from_email'], $SMTP['from_name']);
                $mail->addAddress($email);

                [$subj, $html, $text] = araku_otp_email($otp, $userData['firstname'] ?? '', $OTP_MINUTES, $email);
                $mail->CharSet = 'UTF-8';
                $mail->isHTML(true);
                $mail->Subject = $subj;
                $mail->Body    = $html;
                $mail->AltBody = $text;

                $mail->send();
                header("Location: $VERIFY_PAGE");
                exit;

            } catch (Exception $e) {
                $error = "We couldn't send your sign-in code. Please try again in a moment.";
            }

        } else {
            $error = "That email and password don't match. Please check and try again.";
        }
    }
}

$fromCart = (($_SESSION['redirect_after_login'] ?? '') !== '');
// messages left by user_login_google.php (e.g. Google sign-in cancelled)
if ($error === '' && !empty($_SESSION['message']) && empty($_SESSION['mfa_pending'])) $error = $_SESSION['message'];
unset($_SESSION['message']);

$PAGE_TITLE = 'Sign in - Araku Tribe';
$ACTIVE_NAV = '';
include __DIR__ . '/header3.php';
?>
<style>
/* ============ coffee_login3.php ============ */
.auth{background:var(--cream);padding:48px 0 88px}
.auth-card{display:grid;grid-template-columns:1fr 1fr;max-width:1020px;margin:0 auto;border-radius:30px;overflow:hidden;background:#fff;box-shadow:0 30px 70px -40px rgba(59,35,23,.6)}
.auth-side{position:relative;padding:48px 44px;color:var(--cream);overflow:hidden;
  background:radial-gradient(120% 90% at 100% 0%,#8A4F2B 0%,rgba(138,79,43,0) 55%),radial-gradient(90% 80% at 0% 100%,#2F6B4F 0%,rgba(47,107,79,0) 60%),var(--brown-deep)}
.auth-side h1{color:#fff;font-size:clamp(2rem,3.4vw,2.7rem)}
.auth-side .lede{color:#E6D5C4}
.perk-list{list-style:none;padding:0;margin:30px 0 0;display:grid;gap:16px}
.perk-list li{display:flex;gap:14px;align-items:flex-start}
.perk-list .dot{flex:none;width:40px;height:40px;border-radius:12px;background:rgba(255,247,237,.12);display:flex;align-items:center;justify-content:center;color:var(--gold)}
.perk-list .dot .ico{width:20px;height:20px}
.perk-list strong{display:block;color:#fff;font-weight:600}
.perk-list span{font-size:14px;color:#D9C6B4}
.side-note{margin-top:34px;padding:14px 16px;border-radius:16px;background:rgba(255,247,237,.1);font-size:14px;color:#F4D9B8}
.auth-form{padding:44px 44px 40px}
.auth-form h2{font-size:1.8rem}
.auth-form .sub{margin:6px 0 24px;color:var(--muted);font-size:15px}
.cart-flag{display:flex;gap:10px;align-items:center;margin:-8px 0 20px;padding:11px 14px;border-radius:14px;background:var(--green-soft);color:var(--green);font-size:14px;font-weight:600}
.cart-flag .ico{width:18px;height:18px}
.err{display:flex;gap:10px;align-items:flex-start;margin:0 0 18px;padding:12px 14px;border-radius:14px;background:#FBE4E4;color:#9B2626;font-size:14px;font-weight:600}
.field{margin-bottom:16px}
.field label{display:block;margin:0 0 6px;font-weight:600;font-size:14px;color:var(--brown-deep)}
.field .ctrl{position:relative}
.field input{width:100%;height:50px;border:1.5px solid var(--line);border-radius:14px;padding:0 16px;background:var(--cream);font:inherit;font-size:15.5px;color:var(--ink);transition:border-color .2s,background-color .2s}
.field input:focus{outline:none;border-color:var(--brown-soft);background:#fff}
.field .row{display:flex;justify-content:space-between;align-items:baseline}
.field .row a{font-size:13.5px;font-weight:600;color:var(--orange-cta);text-decoration:none}
.field .row a:hover{text-decoration:underline}
.peek{position:absolute;right:8px;top:7px;height:36px;padding:0 12px;border:0;border-radius:10px;background:none;font-weight:700;font-size:13px;color:var(--brown)}
.peek:hover{background:var(--cream-2)}
.captcha{display:flex;gap:10px;align-items:center}
.captcha .img{flex:none;display:flex;align-items:center;gap:6px;padding:4px;border:1.5px solid var(--line);border-radius:14px;background:#fff}
.captcha img{height:40px;border-radius:10px;display:block}
.captcha .re{width:36px;height:36px;border:0;border-radius:10px;background:var(--cream);color:var(--brown);display:flex;align-items:center;justify-content:center}
.captcha .re:hover{background:var(--cream-2)}
.captcha .re svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.captcha input{flex:1;min-width:0}
.mfa{display:flex;gap:10px;align-items:center;margin:4px 0 18px;font-size:13.5px;color:var(--muted)}
.mfa .ico{width:18px;height:18px;color:var(--green)}
.auth-form .btn-primary{width:100%;padding:15px;font-size:16px}
.or{display:flex;align-items:center;gap:12px;margin:20px 0;font-size:13px;color:var(--muted)}
.or::before,.or::after{content:"";flex:1;height:1px;background:var(--line)}
.google{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;height:50px;border:1.5px solid var(--line);border-radius:999px;background:#fff;color:var(--ink);font-weight:600;font-size:15px;text-decoration:none;transition:border-color .2s,box-shadow .2s}
.google:hover{border-color:var(--brown-soft);box-shadow:0 6px 16px -10px rgba(59,35,23,.5)}
.google svg{width:20px;height:20px}
.switch{margin:22px 0 0;text-align:center;font-size:15px;color:var(--muted)}
.switch a{color:var(--brown);font-weight:700}
@media (max-width:900px){
  .auth{padding:24px 0 60px}
  .auth-card{grid-template-columns:1fr;border-radius:24px}
  .auth-side{padding:28px 24px}
  .perk-list{grid-template-columns:1fr 1fr;gap:14px;margin-top:20px}
  .perk-list span{display:none}
  .side-note{display:none}
  .auth-form{padding:28px 22px 30px}
}
@media (max-width:520px){ .perk-list{grid-template-columns:1fr} .captcha{flex-wrap:wrap} .captcha input{flex-basis:100%} }
</style>

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.5-3 7.8-7 9-4-1.2-7-4.5-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/></symbol>
  <symbol id="i-truck" viewBox="0 0 24 24"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></symbol>
  <symbol id="i-pinmap" viewBox="0 0 24 24"><path d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11Z"/><circle cx="12" cy="10" r="2.2"/></symbol>
</svg>

<main>
  <section class="auth">
    <div class="wrap">
      <div class="auth-card">
        <aside class="auth-side">
          <h1>Welcome back to the Tribe.</h1>
          <p class="lede">Sign in to check out faster and pick up where you left off.</p>
          <ul class="perk-list">
            <li><span class="dot"><svg class="ico"><use href="#i-truck"/></svg></span><div><strong>Track your orders</strong><span>See every order and its status in one place.</span></div></li>
            <li><span class="dot"><svg class="ico"><use href="#i-pinmap"/></svg></span><div><strong>Saved addresses</strong><span>No retyping your delivery details.</span></div></li>
            <li><span class="dot"><svg class="ico"><use href="#i-heart"/></svg></span><div><strong>Wishlist on every device</strong><span>Your saved products follow you.</span></div></li>
            <li><span class="dot"><svg class="ico"><use href="#i-spark"/></svg></span><div><strong>AI Agent checkout</strong><span>Order in one chat with your details ready.</span></div></li>
          </ul>
          <p class="side-note">For your security we use two steps: your password, then a one-time code sent to your email.</p>
        </aside>

        <div class="auth-form">
          <h2>Sign in</h2>
          <p class="sub">Use the email and password you registered with.</p>

          <?php if ($fromCart): ?>
            <p class="cart-flag"><svg class="ico"><use href="#i-bag"/></svg>Your cart is saved. Sign in to finish checking out.</p>
          <?php endif; ?>
          <?php if ($error): ?>
            <p class="err" role="alert"><?= h3_e($error) ?></p>
          <?php endif; ?>

          <form method="POST" action="coffee_login3.php" novalidate>
            <div class="field">
              <label for="email">Email</label>
              <div class="ctrl"><input type="email" id="email" name="email" value="<?= h3_e($emailValue) ?>" autocomplete="email" required <?= $emailValue === '' ? 'autofocus' : '' ?>></div>
            </div>
            <div class="field">
              <div class="row"><label for="password">Password</label><a href="<?= h3_e($FORGOT_PAGE) ?>">Forgot password?</a></div>
              <div class="ctrl">
                <input type="password" id="password" name="password" autocomplete="current-password" required <?= $emailValue !== '' ? 'autofocus' : '' ?>>
                <button type="button" class="peek" id="peek" aria-label="Show password" aria-pressed="false">Show</button>
              </div>
            </div>
            <div class="field">
              <label for="captcha">Type the characters you see</label>
              <div class="captcha">
                <span class="img">
                  <img src="<?= h3_e($CAPTCHA_IMAGE) ?>" alt="CAPTCHA image" id="capImg" width="130" height="40">
                  <button type="button" class="re" id="capNew" aria-label="Show a new CAPTCHA" title="New CAPTCHA">
                    <svg viewBox="0 0 24 24"><path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/></svg>
                  </button>
                </span>
                <input type="text" id="captcha" name="captcha" autocomplete="off" autocapitalize="off" spellcheck="false" required>
              </div>
            </div>
            <p class="mfa"><svg class="ico"><use href="#i-shield"/></svg>Next, we'll email you a 6-digit code to finish signing in.</p>
            <button type="submit" class="btn btn-primary" id="signIn">Sign in</button>
          </form>

          <div class="or">or</div>
          <a href="<?= h3_e($GOOGLE_PAGE) ?>" class="google">
            <svg viewBox="0 0 48 48" aria-hidden="true">
              <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/>
              <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/>
              <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/>
              <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/>
            </svg>
            Sign in with Google
          </a>
          <p class="switch">New to Araku Tribe? <a href="<?= h3_e($SIGNUP_PAGE) ?>">Create an account</a></p>
        </div>
      </div>
    </div>
  </section>
</main>

<script>
(function () {
  const pw = document.getElementById('password'), peek = document.getElementById('peek');
  peek.addEventListener('click', () => {
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    peek.textContent = show ? 'Hide' : 'Show';
    peek.setAttribute('aria-pressed', show); peek.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    pw.focus();
  });
  // new CAPTCHA image without reloading the page
  const img = document.getElementById('capImg');
  document.getElementById('capNew').addEventListener('click', () => {
    img.src = <?= json_encode($CAPTCHA_IMAGE) ?> + '?t=' + Date.now();
    const c = document.getElementById('captcha'); c.value = ''; c.focus();
  });
  // simple checks before sending, and stop double submits
  const form = document.querySelector('.auth-form form'), btn = document.getElementById('signIn');
  form.addEventListener('submit', e => {
    const missing = ['email', 'password', 'captcha'].find(id => !document.getElementById(id).value.trim());
    if (missing) { e.preventDefault(); document.getElementById(missing).focus(); if (window.A3) A3.toast('Please fill in all fields.'); return; }
    btn.disabled = true; btn.textContent = 'Checking…';
  });
})();
</script>

<?php include __DIR__ . '/footer3.php'; ?>