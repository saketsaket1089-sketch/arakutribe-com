<?php
/* =====================================================================
   ARAKU TRIBE - verify_user_otp3.php
   Step 2 of sign-in: checks the 6-digit code from the email.
   Same flow as verify_user_otp.php, with these fixes:
     - the customer is signed in (user_id set) ONLY after the right code
     - at most 5 wrong tries per code, then a new code is needed
     - resend a code right here (with a 30 second wait between sends)
     - a guest's cart is moved into the account on sign-in, and the
       customer goes back to the cart if that's where they came from
   Also handles Google sign-in (user_login_google.php sets
   google_login_pending + user_email; point its redirect to this page).
   ===================================================================== */
session_start();
include 'db1.php';

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ---------------------------------------------------------------------
   SETTINGS
   Mail login: copied from verify_user_otp.php. Please revoke that Gmail
   app password, create a new one and keep it out of shared files.
   --------------------------------------------------------------------- */
$SMTP = [
    'host'       => 'smtp.gmail.com',
    'username'   => 'thotabhuvaneswari1@gmail.com',
    'password'   => 'hcwe gqpf iulm vqls',
    'secure'     => 'tls',
    'port'       => 587,
    'from_email' => 'thotabhuvaneswari1@gmail.com',
    'from_name'  => 'Araku Tribe',
];
$OTP_MINUTES  = 5;
$MAX_ATTEMPTS = 5;
$RESEND_WAIT  = 30;               // seconds between "Resend code" clicks
$LOGIN_PAGE   = 'coffee_login3.php';
$HOME_PAGE    = 'index.php';
$THIS_PAGE    = 'verify_user_otp3.php';

if (!isset($conn) || !($conn instanceof mysqli)) { $conn = new mysqli($host, $user, $pass, $db); }

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

function v3_send_code($email, $name) {
    global $SMTP, $OTP_MINUTES;
    $otp = random_int(100000, 999999);
    $_SESSION['otp']          = $otp;
    $_SESSION['otp_expire']   = time() + $OTP_MINUTES * 60;
    $_SESSION['otp_sent_at']  = time();
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
        [$subj, $html, $text] = araku_otp_email($otp, $name, $OTP_MINUTES, $email);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $subj;
        $mail->Body    = $html;
        $mail->AltBody = $text;
        $mail->send();
        return '';
    } catch (Exception $e) {
        return "We couldn't send the code right now. Please try again in a moment.";
    }
}
function v3_clear_otp() { unset($_SESSION['otp'], $_SESSION['otp_expire'], $_SESSION['otp_sent_at'], $_SESSION['otp_attempts']); }
function v3_mask($email) {
    [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
    return ($u === '' ? '' : substr($u, 0, 2) . str_repeat('•', max(1, strlen($u) - 2))) . ($d !== '' ? '@' . $d : '');
}

$error = ''; $notice = '';

/* use a different account */
if (isset($_GET['cancel'])) {
    v3_clear_otp();
    unset($_SESSION['mfa_pending'], $_SESSION['google_login_pending'], $_SESSION['user_email'], $_SESSION['user_name'], $_SESSION['google_id']);
    header("Location: $LOGIN_PAGE");
    exit;
}

/* Google sign-in arrives here with google_login_pending (same as before) */
if (isset($_SESSION['google_login_pending'], $_SESSION['user_email'])) {
    unset($_SESSION['google_login_pending']);
    v3_clear_otp();
    $gEmail = $_SESSION['user_email'];
    $uid = $_SESSION['mfa_pending']['user_id'] ?? ($_SESSION['user_id'] ?? null);   // the fixed Google file sets mfa_pending
    if (!$uid) {
        $st = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $st->bind_param("s", $gEmail); $st->execute();
        $row = $st->get_result()->fetch_assoc(); $st->close();
        $uid = $row['user_id'] ?? null;
    }
    if ($uid) {
        unset($_SESSION['user_id'], $_SESSION['user_logged_in']);       // not signed in until the code is checked
        $_SESSION['mfa_pending'] = ['user_id' => $uid, 'email' => $gEmail, 'name' => $_SESSION['user_name'] ?? ''];
        $error = v3_send_code($gEmail, $_SESSION['user_name'] ?? '');
        if (!$error) $notice = $_SESSION['message'] ?? 'We sent a code to your Google email.';
        unset($_SESSION['message']);
    } else {
        $error = 'We couldn\'t find an account for that Google email. Please create one first.';
    }
}

/* someone who signed in on the OLD coffee_login.php (user_id already set + code sent): treat as pending */
if (!isset($_SESSION['mfa_pending']) && isset($_SESSION['otp'], $_SESSION['user_id']) && empty($_SESSION['user_logged_in'])) {
    $_SESSION['mfa_pending'] = ['user_id' => $_SESSION['user_id'], 'email' => $_SESSION['user_email'] ?? '', 'name' => $_SESSION['user_name'] ?? ''];
    unset($_SESSION['user_id']);
}

$pending = $_SESSION['mfa_pending'] ?? null;
if (!$pending) {                          // nothing to verify: go and sign in
    header("Location: $LOGIN_PAGE");
    exit;
}

/* resend */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    $wait = $RESEND_WAIT - (time() - (int)($_SESSION['otp_sent_at'] ?? 0));
    if ($wait > 0) $error = "Please wait {$wait} seconds before asking for another code.";
    else { $error = v3_send_code($pending['email'], $pending['name']); if (!$error) $notice = 'A new code is on its way.'; }
}

/* check the code */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $entered = preg_replace('/\D/', '', (string)$_POST['otp']);
    if (!isset($_SESSION['otp'])) {
        $error = 'Please ask for a new code.';
    } elseif (time() > (int)$_SESSION['otp_expire']) {
        v3_clear_otp();
        $error = 'That code has expired. Ask for a new one below.';
    } elseif ((int)($_SESSION['otp_attempts'] ?? 0) >= $MAX_ATTEMPTS) {
        v3_clear_otp();
        $error = 'Too many wrong tries. Ask for a new code below.';
    } elseif (hash_equals((string)$_SESSION['otp'], $entered)) {
        /* success: NOW the customer is signed in */
        session_regenerate_id(true);
        $guestCart = (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) ? $_SESSION['cart'] : [];
        $_SESSION['user_id']        = $pending['user_id'];
        $_SESSION['user_email']     = $pending['email'];
        $_SESSION['user_name']      = $pending['name'];
        $_SESSION['user_logged_in'] = true;
        v3_clear_otp();
        unset($_SESSION['mfa_pending'], $_SESSION['message'], $_SESSION['google_login_pending']);

        /* move a guest's cart into the account (adds to anything already saved) */
        foreach ($guestCart as $pid => $item) {
            $qty = (int)($item['quantity'] ?? 0); if ($qty <= 0) continue;
            $pid = (string)$pid; $uid = $pending['user_id'];
            $ck = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $ck->bind_param("is", $uid, $pid); $ck->execute();
            $exists = $ck->get_result()->num_rows > 0; $ck->close();
            if ($exists) { $q = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?"); $q->bind_param("iis", $qty, $uid, $pid); }
            else         { $q = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)"); $q->bind_param("isi", $uid, $pid, $qty); }
            $q->execute(); $q->close();
        }

        /* account was scheduled for deletion? signing in again restores it (Account Deletion Terms) */
        try {
            $ra = $conn->prepare("UPDATE users SET account_status = 'active', deletion_requested_at = NULL, deletion_reason = NULL WHERE user_id = ? AND account_status = 'pending_deletion'");
            if ($ra) { $ra->bind_param("i", $pending['user_id']); $ra->execute(); if ($ra->affected_rows > 0) $_SESSION['account_restored'] = true; $ra->close(); }
        } catch (\Throwable $e) { error_log('Restore account check failed: ' . $e->getMessage()); }

        $to = $_SESSION['redirect_after_login'] ?? $HOME_PAGE;
        unset($_SESSION['redirect_after_login']);
        if (!preg_match('/^[a-z0-9_\-]+\.php([?#].*)?$/i', $to)) $to = $HOME_PAGE;   // only our own pages
        header("Location: $to");
        exit;
    } else {
        $_SESSION['otp_attempts'] = (int)($_SESSION['otp_attempts'] ?? 0) + 1;
        $left = $MAX_ATTEMPTS - $_SESSION['otp_attempts'];
        if ($left <= 0) { v3_clear_otp(); $error = 'Too many wrong tries. Ask for a new code below.'; }
        else $error = "That code isn't right. You have $left " . ($left === 1 ? 'try' : 'tries') . ' left.';
    }
}

$hasCode   = isset($_SESSION['otp']);
$remaining = $hasCode ? max(0, (int)$_SESSION['otp_expire'] - time()) : 0;
$resendIn  = max(0, $RESEND_WAIT - (time() - (int)($_SESSION['otp_sent_at'] ?? 0)));
if (!$notice && !empty($_SESSION['message'])) $notice = $_SESSION['message'];
unset($_SESSION['message']);

$PAGE_TITLE = 'Enter your code - Araku Tribe';
$ACTIVE_NAV = '';
include __DIR__ . '/header3.php';
?>
<style>
/* ============ verify_user_otp3.php ============ */

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

.otp-card{max-width:520px;margin:0 auto;background:#fff;border-radius:30px;padding:40px 40px 34px;text-align:center;box-shadow:0 30px 70px -40px rgba(59,35,23,.6)}
.otp-icon{width:72px;height:72px;margin:0 auto 18px;border-radius:22px;display:flex;align-items:center;justify-content:center;color:#fff;background:radial-gradient(circle at 30% 28%,#FFE3A3 0%,#F4A13D 38%,#D2561C 70%,#7A2E0E 100%)}
.otp-icon .ico{width:32px;height:32px}
.otp-card h1{font-size:clamp(1.9rem,3vw,2.4rem)}
.otp-card .sub{margin:8px 0 22px;color:var(--muted)}
.otp-card .sub b{color:var(--brown-deep)}
.otp-card .err,.otp-card .ok{justify-content:center;text-align:left}
.ok{display:flex;gap:10px;margin:0 0 18px;padding:12px 14px;border-radius:14px;background:var(--green-soft);color:var(--green);font-size:14px;font-weight:600}
.boxes{display:flex;justify-content:center;gap:10px;margin:4px 0 16px}
.boxes input{width:52px;height:62px;border:1.5px solid var(--line);border-radius:14px;background:var(--cream);text-align:center;font:700 1.6rem var(--sans);color:var(--brown-deep);caret-color:var(--orange-cta)}
.boxes input:focus{outline:none;border-color:var(--brown);background:#fff;box-shadow:0 0 0 3px rgba(107,62,38,.12)}
.boxes input:disabled{opacity:.5}
.timer{display:inline-flex;align-items:center;gap:8px;margin:0 0 20px;padding:7px 14px;border-radius:999px;background:var(--cream);font-weight:600;font-size:14px;color:var(--brown)}
.timer.out{background:#FBE4E4;color:#9B2626}
.otp-card .btn-primary{width:100%;padding:15px;font-size:16px}
.resend{margin:18px 0 0;font-size:14.5px;color:var(--muted)}
.resend form{display:inline}
.resend button{border:0;background:none;padding:0;font:inherit;font-weight:700;color:var(--orange-cta);text-decoration:underline;text-underline-offset:3px}
.resend button:disabled{color:var(--muted);background:none;text-decoration:none;cursor:default;opacity:1}
.other{display:inline-block;margin-top:14px;font-size:14px;font-weight:600;color:var(--brown)}
@media (max-width:520px){ .otp-card{padding:28px 18px 26px;border-radius:24px} .boxes{gap:6px} .boxes input{width:44px;height:56px;font-size:1.35rem} }
</style>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.5-3 7.8-7 9-4-1.2-7-4.5-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/></symbol>
  <symbol id="i-truck" viewBox="0 0 24 24"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></symbol>
  <symbol id="i-pinmap" viewBox="0 0 24 24"><path d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11Z"/><circle cx="12" cy="10" r="2.2"/></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></symbol>
</svg>

<main>
  <section class="auth">
    <div class="wrap">
      <div class="otp-card">
        <div class="otp-icon"><svg class="ico"><use href="#i-mail"/></svg></div>
        <h1>Check your email</h1>
        <p class="sub">Enter the 6-digit code we sent to <b><?= h3_e(v3_mask($pending['email'])) ?></b>.</p>

        <?php if ($notice): ?><p class="ok" role="status"><?= h3_e($notice) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="err" role="alert"><?= h3_e($error) ?></p><?php endif; ?>

        <form method="POST" action="<?= h3_e($THIS_PAGE) ?>" id="otpForm" autocomplete="off">
          <input type="hidden" name="otp" id="otpValue">
          <div class="boxes" id="boxes" role="group" aria-label="6-digit code">
            <?php for ($i = 0; $i < 6; $i++): ?>
              <input type="text" inputmode="numeric" maxlength="1" aria-label="Digit <?= $i + 1 ?>" <?= $i === 0 ? 'autocomplete="one-time-code" autofocus' : '' ?> <?= $hasCode && $remaining > 0 ? '' : 'disabled' ?>>
            <?php endfor; ?>
          </div>
          <p class="timer<?= $remaining > 0 ? '' : ' out' ?>" id="timer" aria-live="polite"><?= $remaining > 0 ? 'Code expires in <span id="left"></span>' : 'This code has expired' ?></p>
          <button type="submit" class="btn btn-primary" id="verifyBtn" <?= $hasCode && $remaining > 0 ? '' : 'disabled' ?>>Verify and sign in</button>
        </form>

        <p class="resend">Didn't get it? Check spam, or
          <form method="POST" action="<?= h3_e($THIS_PAGE) ?>"><button type="submit" name="resend" value="1" id="resendBtn" <?= $resendIn > 0 ? 'disabled' : '' ?>>resend the code<?= $resendIn > 0 ? " in {$resendIn}s" : '' ?></button></form>
        </p>
        <a class="other" href="<?= h3_e($THIS_PAGE) ?>?cancel=1">Use a different account</a>
      </div>
    </div>
  </section>
</main>

<script>
(function () {
  const boxes = [...document.querySelectorAll('#boxes input')], hidden = document.getElementById('otpValue');
  const form = document.getElementById('otpForm'), btn = document.getElementById('verifyBtn');
  const sync = () => { hidden.value = boxes.map(b => b.value).join(''); return hidden.value.length === 6; };
  boxes.forEach((b, i) => {
    b.addEventListener('input', () => {
      b.value = b.value.replace(/\D/g, '').slice(-1);
      if (b.value && boxes[i + 1]) boxes[i + 1].focus();
      if (sync()) { btn.disabled = true; btn.textContent = 'Checking…'; form.submit(); }   // auto-submit on the 6th digit
    });
    b.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !b.value && boxes[i - 1]) { boxes[i - 1].focus(); boxes[i - 1].value = ''; }
      if (e.key === 'ArrowLeft' && boxes[i - 1]) boxes[i - 1].focus();
      if (e.key === 'ArrowRight' && boxes[i + 1]) boxes[i + 1].focus();
    });
    b.addEventListener('paste', e => {                       // paste the whole code into any box
      const digits = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
      if (!digits) return; e.preventDefault();
      boxes.forEach((x, k) => x.value = digits[k] || '');
      (boxes[digits.length] || boxes[5]).focus();
      if (sync()) { btn.disabled = true; btn.textContent = 'Checking…'; form.submit(); }
    });
  });
  form.addEventListener('submit', e => { if (!sync()) { e.preventDefault(); (boxes.find(b => !b.value) || boxes[0]).focus(); } });

  // countdowns: code expiry + resend wait
  let left = <?= (int)$remaining ?>, wait = <?= (int)$resendIn ?>;
  const fmt = s => Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
  const leftEl = document.getElementById('left'), timer = document.getElementById('timer'), rb = document.getElementById('resendBtn');
  if (leftEl) leftEl.textContent = fmt(left);
  const t = setInterval(() => {
    if (left > 0) {
      left--;
      if (leftEl) leftEl.textContent = fmt(left);
      if (left === 0) { timer.classList.add('out'); timer.textContent = 'This code has expired'; boxes.forEach(b => b.disabled = true); btn.disabled = true; }
    }
    if (wait > 0) { wait--; rb.textContent = wait ? `resend the code in ${wait}s` : 'resend the code'; rb.disabled = wait > 0; }
    if (left <= 0 && wait <= 0) clearInterval(t);
  }, 1000);
})();
</script>

<?php include __DIR__ . '/footer3.php'; ?>