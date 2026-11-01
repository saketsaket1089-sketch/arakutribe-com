<?php
/* =====================================================================
   ARAKU TRIBE - coffee_signup3.php
   Same rules as coffee_signup.php:
     CAPTCHA, email must end with .com, 10-digit phone, passwords match,
     password: 8+ characters with upper, lower, number and symbol,
     email must not already be registered -> INSERT into users
   Shows every problem next to its field instead of a pop-up.
   Uses header3.php + footer3.php.
   ===================================================================== */
session_start();
include 'db1.php';
if (!isset($conn) || !($conn instanceof mysqli)) { $conn = new mysqli($host, $user, $pass, $db); }

$LOGIN_PAGE    = 'coffee_login3.php';
$GOOGLE_PAGE   = 'user_login_google.php';
$CAPTCHA_IMAGE = 'generate_captcha.php';
$EMAIL_MUST_END_WITH = '.com';      // same rule as before; set to '' to allow any valid email (.in, .org ...)

$f = ['firstname' => '', 'lastname' => '', 'email' => trim((string)($_GET['email'] ?? '')), 'phonenumber' => ''];
$errors = [];
$formError = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['firstname', 'lastname', 'email', 'phonenumber'] as $k) $f[$k] = trim($_POST[$k] ?? '');
    $password        = $_POST['password'] ?? '';
    $repeat_password = $_POST['repeat_password'] ?? '';
    $captcha_input   = trim($_POST['captcha'] ?? '');

    if (!isset($_SESSION['captcha']) || strtolower($captcha_input) !== strtolower($_SESSION['captcha'])) {
        $errors['captcha'] = 'The characters didn\'t match. Please try the new image.';
    }
    unset($_SESSION['captcha']);   // one try per image
    if ($f['firstname'] === '') $errors['firstname'] = 'Please enter your first name.';
    if ($f['lastname'] === '')  $errors['lastname']  = 'Please enter your last name.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || ($EMAIL_MUST_END_WITH !== '' && !str_ends_with(strtolower($f['email']), $EMAIL_MUST_END_WITH))) {
        $errors['email'] = $EMAIL_MUST_END_WITH !== '' ? "Please enter a valid email ending with {$EMAIL_MUST_END_WITH}." : 'Please enter a valid email.';
    }
    if (!preg_match('/^[0-9]{10}$/', $f['phonenumber'])) $errors['phonenumber'] = 'Phone number must be exactly 10 digits.';
    if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $errors['password'] = 'Use at least 8 characters with an uppercase letter, a lowercase letter, a number and a symbol.';
    }
    if ($password !== $repeat_password) $errors['repeat_password'] = 'Passwords don\'t match.';

    if (!$errors) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $f['email']);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($exists) {
            $errors['email'] = 'This email is already registered.';
            $formError = 'exists';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO users (firstname, lastname, email, phonenumber, password) VALUES (?, ?, ?, ?, ?)");
            $insert->bind_param("sssss", $f['firstname'], $f['lastname'], $f['email'], $f['phonenumber'], $hashedPassword);
            if ($insert->execute()) $done = true;
            else $formError = 'Sign-up failed. Please try again.';
            $insert->close();
        }
    }
}

$PAGE_TITLE = 'Create your account - Araku Tribe';
$ACTIVE_NAV = '';
include __DIR__ . '/header3.php';
function su3_err($errors, $k) { return isset($errors[$k]) ? '<p class="ferr" id="' . $k . 'Err">' . h3_e($errors[$k]) . '</p>' : ''; }
function su3_aria($errors, $k) { return isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="' . $k . 'Err"' : ''; }
?>
<style>
/* ============ coffee_signup3.php ============ */

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

.two{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.ferr{margin:6px 0 0;font-size:13px;font-weight:600;color:#9B2626}
.field input[aria-invalid="true"]{border-color:#D98A8A;background:#FFF6F6}
.phone{position:relative}
.phone span{position:absolute;left:14px;top:15px;font-weight:600;color:var(--muted)}
.phone input{padding-left:52px}
.rules{list-style:none;padding:0;margin:8px 0 0;display:grid;grid-template-columns:1fr 1fr;gap:4px 12px;font-size:12.5px;color:var(--muted)}
.rules li{display:flex;align-items:center;gap:6px}
.rules li::before{content:"";width:14px;height:14px;border-radius:50%;border:1.5px solid var(--line);flex:none}
.rules li.ok{color:var(--green);font-weight:600}
.rules li.ok::before{background:var(--green);border-color:var(--green);box-shadow:inset 0 0 0 3px #fff}
.terms{margin:4px 0 18px;font-size:13px;color:var(--muted)}
.done{text-align:center;padding:20px 0}
.done .big{width:72px;height:72px;margin:0 auto 16px;border-radius:22px;display:flex;align-items:center;justify-content:center;background:var(--green-soft);color:var(--green)}
.done .big svg{width:34px;height:34px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.done p{color:var(--muted);margin:8px 0 22px}
@media (max-width:520px){ .two{grid-template-columns:1fr} .rules{grid-template-columns:1fr} }
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
          <h1>Join the Tribe.</h1>
          <p class="lede">Create an account to order faster and keep everything in one place.</p>
          <ul class="perk-list">
            <li><span class="dot"><svg class="ico"><use href="#i-truck"/></svg></span><div><strong>Track your orders</strong><span>Every order and its status in one place.</span></div></li>
            <li><span class="dot"><svg class="ico"><use href="#i-pinmap"/></svg></span><div><strong>Saved addresses</strong><span>No retyping your delivery details.</span></div></li>
            <li><span class="dot"><svg class="ico"><use href="#i-heart"/></svg></span><div><strong>Wishlist on every device</strong><span>Your saved products follow you.</span></div></li>
            <li><span class="dot"><svg class="ico"><use href="#i-spark"/></svg></span><div><strong>AI Agent checkout</strong><span>Order in one chat with your details ready.</span></div></li>
          </ul>
          <p class="side-note">Already ordering for a café? An account keeps your bulk orders and invoices together.</p>
        </aside>

        <div class="auth-form">
        <?php if ($done): ?>
          <div class="done">
            <div class="big"><svg viewBox="0 0 24 24"><path d="m5 12 5 5 9-10"/></svg></div>
            <h2>Welcome to Araku Tribe, <?= h3_e($f['firstname']) ?>!</h2>
            <p>Your account is ready. Sign in with <b><?= h3_e($f['email']) ?></b> to start ordering.</p>
            <a class="btn btn-primary" href="<?= h3_e($LOGIN_PAGE . '?email=' . rawurlencode($f['email'])) ?>">Sign in now</a>
          </div>
        <?php else: ?>
          <h2>Create your account</h2>
          <p class="sub">It takes less than a minute.</p>
          <?php if ($formError === 'exists'): ?>
            <p class="err" role="alert">This email is already registered. <a href="<?= h3_e($LOGIN_PAGE . '?email=' . rawurlencode($f['email'])) ?>" style="color:inherit;margin-left:4px">Sign in instead</a></p>
          <?php elseif ($formError): ?>
            <p class="err" role="alert"><?= h3_e($formError) ?></p>
          <?php elseif ($errors): ?>
            <p class="err" role="alert">Please fix the highlighted fields below.</p>
          <?php endif; ?>

          <form method="POST" action="coffee_signup3.php" novalidate id="signupForm">
            <div class="two">
              <div class="field"><label for="firstname">First name</label><input id="firstname" name="firstname" value="<?= h3_e($f['firstname']) ?>" autocomplete="given-name" required<?= su3_aria($errors, 'firstname') ?>><?= su3_err($errors, 'firstname') ?></div>
              <div class="field"><label for="lastname">Last name</label><input id="lastname" name="lastname" value="<?= h3_e($f['lastname']) ?>" autocomplete="family-name" required<?= su3_aria($errors, 'lastname') ?>><?= su3_err($errors, 'lastname') ?></div>
            </div>
            <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= h3_e($f['email']) ?>" autocomplete="email" required<?= su3_aria($errors, 'email') ?>><?= su3_err($errors, 'email') ?></div>
            <div class="field"><label for="phonenumber">Phone number</label>
              <div class="phone"><span>+91</span><input type="tel" id="phonenumber" name="phonenumber" value="<?= h3_e($f['phonenumber']) ?>" inputmode="numeric" maxlength="10" autocomplete="tel-national" placeholder="10-digit mobile number" required<?= su3_aria($errors, 'phonenumber') ?>></div><?= su3_err($errors, 'phonenumber') ?></div>
            <div class="field"><label for="password">Password</label>
              <div class="ctrl"><input type="password" id="password" name="password" autocomplete="new-password" required<?= su3_aria($errors, 'password') ?>><button type="button" class="peek" data-peek="password" aria-label="Show password">Show</button></div>
              <ul class="rules" id="rules" aria-live="polite">
                <li data-r="len">8+ characters</li><li data-r="up">An uppercase letter</li>
                <li data-r="low">A lowercase letter</li><li data-r="num">A number</li><li data-r="sym">A symbol (e.g. @ # !)</li>
              </ul><?= su3_err($errors, 'password') ?></div>
            <div class="field"><label for="repeat_password">Repeat password</label>
              <div class="ctrl"><input type="password" id="repeat_password" name="repeat_password" autocomplete="new-password" required<?= su3_aria($errors, 'repeat_password') ?>><button type="button" class="peek" data-peek="repeat_password" aria-label="Show password">Show</button></div>
              <p class="ferr" id="matchErr"<?= isset($errors['repeat_password']) ? '' : ' hidden' ?>><?= h3_e($errors['repeat_password'] ?? "Passwords don't match.") ?></p></div>
            <div class="field"><label for="captcha">Type the characters you see</label>
              <div class="captcha"><span class="img"><img src="<?= h3_e($CAPTCHA_IMAGE) ?>" alt="CAPTCHA image" id="capImg" width="130" height="40">
                <button type="button" class="re" id="capNew" aria-label="Show a new CAPTCHA" title="New CAPTCHA"><svg viewBox="0 0 24 24"><path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/></svg></button></span>
                <input type="text" id="captcha" name="captcha" autocomplete="off" autocapitalize="off" spellcheck="false" required<?= su3_aria($errors, 'captcha') ?>></div><?= su3_err($errors, 'captcha') ?></div>
            <p class="terms">By creating an account you agree to our <a href="<?= h3_link('terms') ?>">terms</a> and <a href="<?= h3_link('privacy') ?>">privacy policy</a>.</p>
            <button type="submit" class="btn btn-primary" id="createBtn">Create account</button>
          </form>
          <div class="or">or</div>
          <a href="<?= h3_e($GOOGLE_PAGE) ?>" class="google"><svg viewBox="0 0 48 48" aria-hidden="true">
              <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/>
              <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/>
              <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/>
              <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/>
            </svg> Sign up with Google</a>
          <p class="switch">Already have an account? <a href="<?= h3_e($LOGIN_PAGE) ?>">Sign in</a></p>
        <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
</main>

<script>
(function () {
  document.querySelectorAll('[data-peek]').forEach(b => b.addEventListener('click', () => {
    const i = document.getElementById(b.dataset.peek), show = i.type === 'password';
    i.type = show ? 'text' : 'password'; b.textContent = show ? 'Hide' : 'Show'; b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  }));
  const pw = document.getElementById('password'), rp = document.getElementById('repeat_password'); if (!pw) return;
  const tests = { len: v => v.length >= 8, up: v => /[A-Z]/.test(v), low: v => /[a-z]/.test(v), num: v => /\d/.test(v), sym: v => /[\W_]/.test(v) };
  const paint = () => document.querySelectorAll('#rules li').forEach(li => li.classList.toggle('ok', tests[li.dataset.r](pw.value)));
  const match = () => { const bad = rp.value !== '' && rp.value !== pw.value; document.getElementById('matchErr').hidden = !bad; };
  pw.addEventListener('input', () => { paint(); match(); }); rp.addEventListener('input', match); paint();
  const ph = document.getElementById('phonenumber'); ph.addEventListener('input', () => { ph.value = ph.value.replace(/\D/g, '').slice(0, 10); });
  const img = document.getElementById('capImg');
  document.getElementById('capNew').addEventListener('click', () => { img.src = <?= json_encode($CAPTCHA_IMAGE) ?> + '?t=' + Date.now(); const c = document.getElementById('captcha'); c.value = ''; c.focus(); });
  img.addEventListener('click', () => document.getElementById('capNew').click());
  document.getElementById('signupForm').addEventListener('submit', () => { const b = document.getElementById('createBtn'); b.disabled = true; b.textContent = 'Creating your account…'; });
  const firstBad = document.querySelector('[aria-invalid="true"]'); if (firstBad) firstBad.focus();
})();
</script>

<?php include __DIR__ . '/footer3.php'; ?>