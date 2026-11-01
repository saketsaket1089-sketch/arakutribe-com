<?php
/* =====================================================================
   ARAKU TRIBE - delete_account3.php
   Same logic as delete_account.php: confirm with your password, agree to
   the deletion terms, optional reason -> account_status = 'pending_deletion',
   deletion_requested_at = NOW(), signed out. Nothing is erased for
   $GRACE_PERIOD_DAYS days; signing in again restores the account
   (handled in verify_user_otp3.php).
   Fixes: sign-in is checked before anything is shown; security token on
   the form; Google-only accounts (no password) confirm by typing DELETE;
   the "scheduled" confirmation is shown on this page.
   Uses header3.php + footer3.php.
   ===================================================================== */
session_start();
include 'db1.php';
$GRACE_PERIOD_DAYS = 30;

/* after deletion is scheduled the visitor is signed out: show the confirmation */
if (isset($_GET['done'])) {
    $doneUntil = $_SESSION['deletion_until'] ?? '';
    unset($_SESSION['deletion_until']);
    $PAGE_TITLE = 'Account deactivated - Araku Tribe';
    include __DIR__ . '/header3.php';
    ?>
    <style>
    .del-done{max-width:620px;margin:0 auto;padding:56px 0 96px;text-align:center}
    .del-done .big{width:80px;height:80px;margin:0 auto 18px;border-radius:24px;display:flex;align-items:center;justify-content:center;background:var(--cream);color:var(--brown)}
    .del-done .big svg{width:36px;height:36px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
    .del-done h1{font-size:clamp(2rem,3.4vw,2.6rem)}
    .del-done p{color:var(--muted);font-size:1.05rem;margin:10px auto 0;max-width:52ch}
    .del-done .box{margin:26px auto 0;padding:18px 20px;border-radius:18px;background:var(--cream);text-align:left;font-size:15px;color:var(--brown-deep)}
    .del-done .acts{display:flex;justify-content:center;gap:12px;flex-wrap:wrap;margin-top:28px}
    </style>
    <main><div class="wrap del-done">
      <div class="big"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
      <h1>Your account is deactivated</h1>
      <p>We've signed you out. Your details stay safe for <?= (int)$GRACE_PERIOD_DAYS ?> days in case you change your mind.</p>
      <div class="box"><strong>Changed your mind?</strong> Just sign in again<?= $doneUntil ? ' before <b>' . h3_e($doneUntil) . '</b>' : ' within ' . (int)$GRACE_PERIOD_DAYS . ' days' ?> and your account, orders and saved details come back automatically.</div>
      <div class="acts"><a class="btn btn-primary" href="<?= h3_link('login') ?>">Sign in to restore</a><a class="btn btn-outline" href="<?= h3_link('home') ?>">Back to the shop</a></div>
    </div></main>
    <?php include __DIR__ . '/footer3.php'; exit;
}

if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'delete_account3.php';
    header('Location: coffee_login3.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];
if (empty($_SESSION['csrf3'])) $_SESSION['csrf3'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf3'];
if (!isset($conn) || !($conn instanceof mysqli)) { $conn = new mysqli($host, $user, $pass, $db); }
if ($conn->connect_error) { die('Database connection failed. Please try again in a moment.'); }

$st = $conn->prepare("SELECT firstname, lastname, email, password, profile_picture FROM users WHERE user_id = ? LIMIT 1");
$st->bind_param("i", $user_id); $st->execute(); $me = $st->get_result()->fetch_assoc() ?: []; $st->close();
$hasPassword = !empty($me['password']);

$REASONS = ['not_using' => "I'm not using this account any more", 'privacy' => 'Privacy concerns', 'new_account' => 'I want to create a new account',
            'bad_experience' => 'I had a bad experience', 'other' => 'Something else'];
$errors = []; $formError = '';
$f = ['reason' => '', 'reason_other' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_deletion'])) {
    $f['reason']       = (string)($_POST['reason'] ?? '');
    $f['reason_other'] = trim((string)($_POST['reason_other'] ?? ''));
    if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) $formError = 'Your session expired. Please try again.';
    if ($hasPassword) {
        $pw = (string)($_POST['password'] ?? '');
        if ($pw === '') $errors['password'] = 'Please enter your password to confirm.';
        elseif (!password_verify($pw, $me['password'])) $errors['password'] = "That password isn't right. Please try again.";
    } elseif (strtoupper(trim((string)($_POST['confirm_word'] ?? ''))) !== 'DELETE') {
        $errors['confirm_word'] = 'Please type DELETE to confirm.';
    }
    if (!isset($_POST['agree_terms'])) $errors['agree_terms'] = 'Please agree to the account deletion terms to continue.';

    if (!$errors && !$formError) {
        $reason = isset($REASONS[$f['reason']]) ? $f['reason'] : '';
        if ($reason === 'other' && $f['reason_other'] !== '') $reason = 'Other: ' . substr($f['reason_other'], 0, 500);
        $up = $conn->prepare("UPDATE users SET account_status = 'pending_deletion', deletion_requested_at = NOW(), deletion_reason = ? WHERE user_id = ?");
        $up->bind_param("si", $reason, $user_id);
        if ($up->execute()) {
            $up->close(); $conn->close();
            $until = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->modify("+{$GRACE_PERIOD_DAYS} days")->format('j M Y');
            // sign out completely (same as logout_user3.php), keep only the date for the confirmation screen
            $_SESSION = [];
            if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']); }
            session_destroy();
            session_start();
            $_SESSION['deletion_until'] = $until;
            header('Location: delete_account3.php?done=1');
            exit;
        }
        error_log('Schedule deletion failed: ' . $up->error);
        $formError = 'Something went wrong scheduling the deletion. Please try again.';
        $up->close();
    }
}
$conn->close();

$acctFirst = trim((string)($me['firstname'] ?? ''));
$deleteOn  = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->modify("+{$GRACE_PERIOD_DAYS} days")->format('j M Y');

$PAGE_TITLE = 'Delete your account - Araku Tribe';
include __DIR__ . '/header3.php';
function da3_err($e, $k) { return isset($e[$k]) ? '<p class="ferr" id="' . $k . 'Err">' . h3_e($e[$k]) . '</p>' : ''; }
?>
<style>
/* ============ delete_account3.php ============ */
.del-hero{background:var(--cream);padding:28px 0 34px}
.crumbs{display:flex;gap:8px;margin:0 0 14px;font-size:13.5px;color:var(--muted)}
.crumbs a{color:var(--muted);text-decoration:none}
.crumbs a:hover{color:var(--brown)}
.crumbs span[aria-current]{color:var(--brown);font-weight:600}
.del-hero h1{font-size:clamp(2rem,3.6vw,2.8rem)}
.del-hero p{margin:8px 0 0;color:var(--muted);max-width:60ch}
.del-wrap{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:28px;align-items:start;padding-top:32px;padding-bottom:88px}
.panel{background:#fff;border:1px solid var(--line);border-radius:22px;padding:24px}
.panel h2{font-size:1.35rem;margin-bottom:14px}
.timeline{list-style:none;margin:0;padding:0;display:grid;gap:0}
.timeline li{position:relative;padding:0 0 22px 46px}
.timeline li:last-child{padding-bottom:0}
.timeline li::before{content:"";position:absolute;left:15px;top:34px;bottom:2px;width:2px;background:var(--line)}
.timeline li:last-child::before{display:none}
.timeline .dot{position:absolute;left:0;top:0;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;background:var(--cream);color:var(--brown)}
.timeline li.warn .dot{background:#FBE4E4;color:#9B2626}
.timeline strong{display:block;color:var(--brown-deep)}
.timeline span{font-size:14px;color:var(--muted)}
.keep{margin-top:18px;padding:14px 16px;border-radius:16px;background:var(--green-soft);font-size:14px;color:#2F4F3E}
.keep b{color:var(--green)}
.alts{display:grid;gap:8px;margin-top:16px}
.alts a{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:14px;border:1px solid var(--line);text-decoration:none;font-size:14.5px;font-weight:600;color:var(--ink)}
.alts a:hover{border-color:var(--brown-soft);color:var(--brown)}
.alts a span{margin-left:auto;color:var(--muted);font-weight:500;font-size:13px}
.field{margin-bottom:16px}
.field label{display:block;margin:0 0 6px;font-weight:600;font-size:14px;color:var(--brown-deep)}
.field label small{font-weight:500;color:var(--muted)}
.field input,.field select,.field textarea{width:100%;border:1.5px solid var(--line);border-radius:14px;padding:12px 14px;background:var(--cream);font:inherit;font-size:15px;color:var(--ink)}
.field input,.field select{height:50px;padding:0 14px}
.field input:focus,.field select:focus,.field textarea:focus{outline:none;border-color:var(--brown-soft);background:#fff}
.field input[aria-invalid="true"]{border-color:#D98A8A;background:#FFF6F6}
.ferr{margin:6px 0 0;font-size:13px;font-weight:600;color:#9B2626}
.agree{display:flex;gap:10px;align-items:flex-start;padding:14px;border-radius:14px;background:var(--cream);font-size:14px;color:var(--brown-deep);cursor:pointer}
.agree input{flex:none;width:18px;height:18px;margin-top:2px;accent-color:#9B2626}
.flash{margin:0 0 16px;padding:12px 14px;border-radius:14px;background:#FBE4E4;color:#9B2626;font-weight:600;font-size:14px}
.acts{display:flex;gap:10px;justify-content:space-between;flex-wrap:wrap;margin-top:20px}
.btn-danger{background:#9B2626;color:#fff;border-color:#9B2626}
.btn-danger:hover{background:#7E1D1D}
.btn-danger:disabled{background:#E7B9B9;border-color:#E7B9B9}
dialog.cf{border:0;border-radius:22px;padding:0;width:min(440px,92vw);box-shadow:0 30px 80px -20px rgba(36,24,17,.5)}
dialog.cf::backdrop{background:rgba(36,24,17,.5)}
.cf-in{padding:26px 24px 22px}
.cf-in h2{font-size:1.4rem}
.cf-in p{margin:10px 0 0;color:var(--muted);font-size:15px}
.cf-acts{display:flex;gap:10px;justify-content:flex-end;margin-top:22px;flex-wrap:wrap}
@media (max-width:900px){ .del-wrap{grid-template-columns:1fr} .acts .btn{flex:1} }
</style>

<main>
  <section class="del-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>">Home</a><span aria-hidden="true">/</span><a href="<?= h3_link('profile') ?>">My account</a><span aria-hidden="true">/</span><span aria-current="page">Delete account</span></nav>
      <h1>Delete your account</h1>
      <p>We're sorry to see you go<?= $acctFirst !== '' ? ', ' . h3_e($acctFirst) : '' ?>. Please read how this works before you continue.</p>
    </div>
  </section>

  <div class="wrap del-wrap">
    <section class="panel" aria-labelledby="how">
      <h2 id="how">What happens next</h2>
      <ol class="timeline">
        <li><span class="dot">1</span><strong>Today: deactivated</strong><span>You're signed out and the account can't be used to shop.</span></li>
        <li><span class="dot">2</span><strong>Next <?= (int)$GRACE_PERIOD_DAYS ?> days: you can change your mind</strong><span>Sign in any time before <?= h3_e($deleteOn) ?> and everything comes back automatically.</span></li>
        <li class="warn"><span class="dot">3</span><strong>After <?= h3_e($deleteOn) ?>: permanently deleted</strong><span>Your name, email, phone, addresses and photo are erased and can't be recovered.</span></li>
      </ol>
      <p class="keep"><b>What we keep:</b> a record of past orders with your name and contact details removed, as needed for accounting and legal reasons. Details are in our <a href="terms_deletion3.php">account deletion terms</a>.</p>
      <div class="alts">
        <a href="edit_profile3.php">Just want to change your details?<span>Edit profile</span></a>
        <a href="<?= h3_link('contact') ?>">Had a problem with an order?<span>Talk to us</span></a>
      </div>
    </section>

    <section class="panel" aria-labelledby="confirmTitle">
      <h2 id="confirmTitle">Confirm deletion</h2>
      <?php if ($formError): ?><p class="flash" role="alert"><?= h3_e($formError) ?></p><?php endif; ?>
      <form method="POST" action="delete_account3.php" id="delForm" novalidate>
        <input type="hidden" name="csrf" value="<?= h3_e($CSRF) ?>">
        <input type="hidden" name="confirm_deletion" value="1">
        <div class="field"><label for="reason">Why are you leaving? <small>(optional, helps us improve)</small></label>
          <select id="reason" name="reason"><option value="">Choose a reason</option>
            <?php foreach ($REASONS as $k => $label): ?><option value="<?= h3_e($k) ?>" <?= $f['reason'] === $k ? 'selected' : '' ?>><?= h3_e($label) ?></option><?php endforeach; ?>
          </select></div>
        <div class="field" id="otherBox" <?= $f['reason'] === 'other' ? '' : 'hidden' ?>><label for="reason_other">Tell us a little more <small>(optional)</small></label>
          <textarea id="reason_other" name="reason_other" rows="3" maxlength="500"><?= h3_e($f['reason_other']) ?></textarea></div>
        <?php if ($hasPassword): ?>
          <div class="field"><label for="password">Your password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required<?= isset($errors['password']) ? ' aria-invalid="true" aria-describedby="passwordErr"' : '' ?>><?= da3_err($errors, 'password') ?></div>
        <?php else: ?>
          <div class="field"><label for="confirm_word">Type <b>DELETE</b> to confirm <small>(you signed up with Google, so there's no password)</small></label>
            <input id="confirm_word" name="confirm_word" autocomplete="off" autocapitalize="characters" required<?= isset($errors['confirm_word']) ? ' aria-invalid="true" aria-describedby="confirm_wordErr"' : '' ?>><?= da3_err($errors, 'confirm_word') ?></div>
        <?php endif; ?>
        <label class="agree"><input type="checkbox" name="agree_terms" id="agree"><span>I've read the <a href="terms_deletion3.php" target="_blank">account deletion terms</a> and understand my account will be deactivated now and permanently deleted after <?= (int)$GRACE_PERIOD_DAYS ?> days unless I sign in again.</span></label>
        <?= da3_err($errors, 'agree_terms') ?>
        <div class="acts">
          <a class="btn btn-outline" href="<?= h3_link('profile') ?>">Keep my account</a>
          <button type="button" class="btn btn-danger" id="askBtn">Deactivate my account</button>
        </div>
      </form>
    </section>
  </div>
</main>

<dialog class="cf" id="cf" aria-labelledby="cfTitle">
  <div class="cf-in">
    <h2 id="cfTitle">Deactivate your account?</h2>
    <p>You'll be signed out now. If you don't sign in again before <b><?= h3_e($deleteOn) ?></b>, your account will be permanently deleted.</p>
    <div class="cf-acts"><button type="button" class="btn btn-outline" id="cfNo">Keep my account</button><button type="button" class="btn btn-danger" id="cfYes">Yes, deactivate</button></div>
  </div>
</dialog>

<script>
(function () {
  const reason = document.getElementById('reason'), other = document.getElementById('otherBox');
  reason.addEventListener('change', () => { other.hidden = reason.value !== 'other'; });
  const form = document.getElementById('delForm'), cf = document.getElementById('cf');
  document.getElementById('askBtn').addEventListener('click', () => {
    const pw = document.getElementById('password') || document.getElementById('confirm_word');
    if (!pw.value.trim()) { pw.focus(); if (window.A3) A3.toast(pw.id === 'password' ? 'Please enter your password.' : 'Please type DELETE.'); return; }
    if (!document.getElementById('agree').checked) { document.getElementById('agree').focus(); if (window.A3) A3.toast('Please tick the box to agree to the terms.'); return; }
    cf.showModal();
  });
  document.getElementById('cfNo').addEventListener('click', () => cf.close());
  cf.addEventListener('click', e => { if (e.target === cf) cf.close(); });
  document.getElementById('cfYes').addEventListener('click', () => { const b = document.getElementById('cfYes'); b.disabled = true; b.textContent = 'Deactivating…'; form.submit(); });
  const bad = document.querySelector('[aria-invalid="true"]'); if (bad) bad.focus();
})();
</script>

<?php include __DIR__ . '/footer3.php'; ?>