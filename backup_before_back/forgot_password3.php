<?php
/* =====================================================================
   ARAKU TRIBE - forgot_password3.php
   Same as forgot_password.php: the form posts to your existing
   process_forgot_password.php, which sends the reset link and sets
   $_SESSION['error_message'] / ['success_message'].
   (In process_forgot_password.php, change its redirect back to
   forgot_password3.php so the messages show on this page.)
   Uses header3.php + footer3.php.
   ===================================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();

$PROCESS_PAGE = 'process_forgot_password3.php';
$LOGIN_PAGE   = 'coffee_login3.php';

$error_message   = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message'], $_SESSION['success_message']);
$emailValue = trim((string)($_GET['email'] ?? ''));

$PAGE_TITLE = 'Reset your password - Araku Tribe';
$ACTIVE_NAV = '';
include __DIR__ . '/header3.php';
?>
<style>
/* ============ forgot_password3.php ============ */

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

.otp-card{max-width:500px;margin:0 auto;background:#fff;border-radius:30px;padding:40px 40px 34px;box-shadow:0 30px 70px -40px rgba(59,35,23,.6)}
.otp-icon{width:72px;height:72px;margin:0 auto 18px;border-radius:22px;display:flex;align-items:center;justify-content:center;color:var(--brown);background:var(--cream)}
.otp-icon svg{width:32px;height:32px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.otp-card h1{text-align:center;font-size:clamp(1.9rem,3vw,2.4rem)}
.otp-card .sub{text-align:center;margin:8px 0 24px;color:var(--muted)}
.ok{display:flex;gap:10px;margin:0 0 18px;padding:12px 14px;border-radius:14px;background:var(--green-soft);color:var(--green);font-size:14px;font-weight:600}
.otp-card .btn-primary{width:100%;padding:15px;font-size:16px}
.otp-card .switch{text-align:center}
.tips{margin:22px 0 0;padding:14px 16px;border-radius:16px;background:var(--cream);font-size:13.5px;color:var(--brown-deep)}
.tips b{display:block;margin-bottom:4px}
@media (max-width:520px){ .otp-card{padding:28px 20px 26px;border-radius:24px} }
</style>

<main>
  <section class="auth">
    <div class="wrap">
      <div class="otp-card">
        <div class="otp-icon"><svg viewBox="0 0 24 24"><rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15.5" r="1.3"/></svg></div>
        <h1>Forgot your password?</h1>
        <p class="sub">Enter the email you signed up with and we'll send you a link to set a new one.</p>

        <?php if ($error_message): ?><p class="err" role="alert"><?= h3_e($error_message) ?></p><?php endif; ?>
        <?php if ($success_message): ?><p class="ok" role="status"><?= h3_e($success_message) ?></p><?php endif; ?>

        <form action="<?= h3_e($PROCESS_PAGE) ?>" method="POST" id="fpForm">
          <div class="field"><label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= h3_e($emailValue) ?>" autocomplete="email" required autofocus></div>
          <button type="submit" class="btn btn-primary" id="fpBtn">Send reset link</button>
        </form>
        <div class="tips"><b>Can't find the email?</b>Check your spam or promotions folder. The link can take a minute or two to arrive.</div>
        <p class="switch">Remembered it? <a href="<?= h3_e($LOGIN_PAGE) ?>">Back to sign in</a></p>
      </div>
    </div>
  </section>
</main>
<script>
document.getElementById('fpForm').addEventListener('submit', e => {
  const em = document.getElementById('email');
  if (!em.value.trim() || !em.checkValidity()) { e.preventDefault(); em.focus(); if (window.A3) A3.toast('Please enter a valid email.'); return; }
  const b = document.getElementById('fpBtn'); b.disabled = true; b.textContent = 'Sending…';
});
</script>

<?php include __DIR__ . '/footer3.php'; ?>