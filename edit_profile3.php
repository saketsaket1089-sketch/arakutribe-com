<?php
/* =====================================================================
   ARAKU TRIBE - edit_profile3.php  (Edit profile)
   Same rules as edit_profile.php:
     required: first & last name (letters/spaces), email, 10-digit phone,
     area, zip code (4-10 digits); landmark optional; country code from
     the list; new emails must be Gmail or Yahoo; photo JPG/PNG up to 2 MB
   Fixes: email can't be one another account already uses; your CURRENT
   email is always accepted (so Google sign-ups with other domains can
   still save); security token on the form; errors shown per field.
   On success -> user_profile3.php?status=success
   ===================================================================== */
session_start();
include 'db1.php';
if (empty($_SESSION['user_id'])) {                      // not signed in: sign in first, then come back here
    $_SESSION['redirect_after_login'] = basename(__FILE__);
    header('Location: coffee_login3.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];
if (empty($_SESSION['csrf3'])) $_SESSION['csrf3'] = bin2hex(random_bytes(16));   // protects forms from other sites
$CSRF = $_SESSION['csrf3'];
if (!isset($conn) || !($conn instanceof mysqli)) { $conn = new mysqli($host, $user, $pass, $db); }
if ($conn->connect_error) { die('Database connection failed. Please try again in a moment.'); }

$COUNTRY_CODES = [
    '+91' => 'India (+91)', '+1' => 'USA (+1)', '+44' => 'UK (+44)', '+61' => 'Australia (+61)', '+971' => 'UAE (+971)',
    '+65' => 'Singapore (+65)', '+60' => 'Malaysia (+60)', '+49' => 'Germany (+49)', '+33' => 'France (+33)', '+81' => 'Japan (+81)',
];
$ALLOWED_EMAIL_DOMAINS = ['gmail.com', 'yahoo.com', 'yahoo.co.in', 'yahoo.co.uk', 'yahoo.com.au'];   // for NEW emails; [] = any domain
$UPLOAD_DIR = 'uploads/profile_pictures/';
$MAX_PIC    = 2 * 1024 * 1024;

$st = $conn->prepare("SELECT firstname, lastname, email, phonenumber, area, landmark, zipcode, profile_picture FROM users WHERE user_id = ? LIMIT 1");
$st->bind_param("i", $user_id); $st->execute(); $me = $st->get_result()->fetch_assoc() ?: []; $st->close();

$f = [];
foreach (['firstname', 'lastname', 'email', 'phonenumber', 'area', 'landmark', 'zipcode'] as $k) $f[$k] = (string)($me[$k] ?? '');
$f['country_code'] = $_SESSION['country_code'] ?? '+91';
$errors = []; $formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f['firstname']    = trim($_POST['firstname'] ?? '');
    $f['lastname']     = trim($_POST['lastname'] ?? '');
    $f['email']        = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $f['country_code'] = trim($_POST['country_code'] ?? '+91');
    $f['phonenumber']  = preg_replace('/\D/', '', trim($_POST['phonenumber'] ?? ''));
    $f['area']         = trim($_POST['area'] ?? '');
    $f['landmark']     = trim($_POST['landmark'] ?? '');
    $f['zipcode']      = trim($_POST['zipcode'] ?? '');

    if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) $formError = 'Your session expired. Please try saving again.';

    if ($f['firstname'] === '') $errors['firstname'] = 'First name is required.';
    elseif (!preg_match('/^[a-zA-Z\s]+$/', $f['firstname'])) $errors['firstname'] = 'Use letters and spaces only.';
    if ($f['lastname'] === '') $errors['lastname'] = 'Last name is required.';
    elseif (!preg_match('/^[a-zA-Z\s]+$/', $f['lastname'])) $errors['lastname'] = 'Use letters and spaces only.';

    $emailChanged = strcasecmp($f['email'], (string)($me['email'] ?? '')) !== 0;
    if ($f['email'] === '') $errors['email'] = 'Email is required.';
    elseif (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address.';
    elseif ($emailChanged && $ALLOWED_EMAIL_DOMAINS && !in_array(strtolower(substr(strrchr($f['email'], '@'), 1)), $ALLOWED_EMAIL_DOMAINS, true)) $errors['email'] = 'New emails must be Gmail or Yahoo addresses.';
    elseif ($emailChanged) {
        $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1");
        $chk->bind_param("si", $f['email'], $user_id); $chk->execute();
        if ($chk->get_result()->num_rows > 0) $errors['email'] = 'Another account already uses this email.';
        $chk->close();
    }
    if ($f['phonenumber'] === '') $errors['phonenumber'] = 'Phone number is required.';
    elseif (!preg_match('/^\d{10}$/', $f['phonenumber'])) $errors['phonenumber'] = 'Phone number must be exactly 10 digits.';
    if (!array_key_exists($f['country_code'], $COUNTRY_CODES)) $errors['phonenumber'] = 'Please choose a country code from the list.';
    if ($f['area'] === '') $errors['area'] = 'Area is required.';
    if ($f['zipcode'] === '') $errors['zipcode'] = 'Zip code is required.';
    elseif (!preg_match('/^\d{4,10}$/', $f['zipcode'])) $errors['zipcode'] = 'Use numbers only, 4 to 10 digits.';

    $newPic = null;
    if (!$errors && !$formError && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_picture'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $info = $file['error'] === UPLOAD_ERR_OK ? @getimagesize($file['tmp_name']) : false;
        if ($file['error'] !== UPLOAD_ERR_OK) $errors['profile_picture'] = 'The photo didn\'t upload. Please try again.';
        elseif (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) || !in_array($ext, ['jpg', 'jpeg', 'png'], true)) $errors['profile_picture'] = 'Please choose a JPG or PNG image.';
        elseif ($file['size'] > $MAX_PIC) $errors['profile_picture'] = 'The photo must be smaller than 2 MB.';
        else {
            if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0755, true);
            $newPic = uniqid('pp_') . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
            if (!move_uploaded_file($file['tmp_name'], $UPLOAD_DIR . $newPic)) { $errors['profile_picture'] = 'Sorry, the photo couldn\'t be saved.'; $newPic = null; }
        }
    }

    if (!$errors && !$formError) {
        $sql = "UPDATE users SET firstname = ?, lastname = ?, email = ?, phonenumber = ?, area = ?, landmark = ?, zipcode = ?" . ($newPic ? ", profile_picture = ?" : "") . " WHERE user_id = ?";
        $up = $conn->prepare($sql);
        if ($newPic) $up->bind_param("ssssssssi", $f['firstname'], $f['lastname'], $f['email'], $f['phonenumber'], $f['area'], $f['landmark'], $f['zipcode'], $newPic, $user_id);
        else         $up->bind_param("sssssssi",  $f['firstname'], $f['lastname'], $f['email'], $f['phonenumber'], $f['area'], $f['landmark'], $f['zipcode'], $user_id);
        if ($up->execute()) {
            $_SESSION['country_code'] = $f['country_code'];
            $_SESSION['user_name']    = $f['firstname'];
            $_SESSION['user_email']   = $f['email'];
            $up->close(); $conn->close();
            header('Location: user_profile3.php?status=success');
            exit;
        }
        error_log('Profile update failed: ' . $up->error);
        $formError = 'We couldn\'t save your changes. Please try again.';
        $up->close();
    }
}
$conn->close();

$acctFirst = trim((string)($me['firstname'] ?? $_SESSION['user_name'] ?? ''));
$acctEmail = (string)($me['email'] ?? '');
$avatarInitial = strtoupper(substr($acctFirst !== '' ? $acctFirst : 'A', 0, 1));
$avatarSrc = (!empty($me['profile_picture']) && is_file($UPLOAD_DIR . $me['profile_picture'])) ? $UPLOAD_DIR . $me['profile_picture'] : '';

$PAGE_TITLE = 'Edit profile - Araku Tribe';
$ACTIVE_NAV = '';
include __DIR__ . '/header3.php';
function ep3_err($e, $k) { return isset($e[$k]) ? '<p class="ferr" id="' . $k . 'Err">' . h3_e($e[$k]) . '</p>' : ''; }
function ep3_inv($e, $k) { return isset($e[$k]) ? ' aria-invalid="true" aria-describedby="' . $k . 'Err"' : ''; }
?>
<style>
/* ============ edit_profile3.php ============ */

.acct-hero{background:var(--cream);padding:28px 0 0}
.crumbs{display:flex;gap:8px;margin:0 0 14px;font-size:13.5px;color:var(--muted)}
.crumbs a{color:var(--muted);text-decoration:none}
.crumbs a:hover{color:var(--brown)}
.crumbs span[aria-current]{color:var(--brown);font-weight:600}
.acct-head{display:flex;align-items:center;gap:18px;flex-wrap:wrap}
.big-avatar{flex:none;width:72px;height:72px;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:var(--brown);color:var(--cream);font-family:var(--serif);font-size:30px;box-shadow:0 0 0 3px #fff,0 0 0 5px var(--gold)}
.big-avatar img{width:100%;height:100%;object-fit:cover}
.acct-head h1{font-size:clamp(1.9rem,3.4vw,2.7rem)}
.acct-head p{margin:4px 0 0;color:var(--muted)}
.acct-tabs{display:flex;gap:4px;margin-top:24px;overflow-x:auto;scrollbar-width:none}
.acct-tabs::-webkit-scrollbar{display:none}
.acct-tabs a{display:inline-flex;align-items:center;gap:8px;white-space:nowrap;padding:12px 18px;border-radius:14px 14px 0 0;text-decoration:none;font-weight:600;font-size:15px;color:var(--ink)}
.acct-tabs a .ico{width:18px;height:18px;color:var(--brown)}
.acct-tabs a:hover{color:var(--orange-cta)}
.acct-tabs a[aria-current="page"]{background:#fff;color:var(--brown)}
.acct-tabs a.out{margin-left:auto;color:var(--red)}
.acct-tabs a.out .ico{color:var(--red)}
.acct-body{background:#fff;padding:32px 0 88px}
.flash{display:flex;gap:10px;align-items:center;margin:0 0 20px;padding:13px 16px;border-radius:14px;font-weight:600;font-size:14.5px}
.flash.ok{background:var(--green-soft);color:var(--green)}
.flash.bad{background:#FBE4E4;color:#9B2626}
.panel{background:#fff;border:1px solid var(--line);border-radius:22px;padding:24px}
.panel h2{font-size:1.35rem;display:flex;align-items:center;gap:10px}
.panel h2 .ico{width:22px;height:22px;color:var(--brown)}
@media (max-width:680px){ .acct-tabs a{padding:10px 14px;font-size:14px} .acct-tabs a.out{margin-left:0} .panel{padding:18px;border-radius:18px} }

.ep{max-width:760px}
.ep .panel{margin-bottom:18px}
.ep h2{font-size:1.3rem;margin-bottom:16px}
.two{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.field{margin-bottom:14px}
.field label{display:block;margin:0 0 6px;font-weight:600;font-size:14px;color:var(--brown-deep)}
.field label small{font-weight:500;color:var(--muted)}
.field input,.field select{width:100%;height:50px;border:1.5px solid var(--line);border-radius:14px;padding:0 14px;background:var(--cream);font:inherit;font-size:15.5px;color:var(--ink)}
.field input:focus,.field select:focus{outline:none;border-color:var(--brown-soft);background:#fff}
.field input[aria-invalid="true"]{border-color:#D98A8A;background:#FFF6F6}
.field .hint{margin:5px 0 0;font-size:12.5px;color:var(--muted)}
.ferr{margin:6px 0 0;font-size:13px;font-weight:600;color:#9B2626}
.phone{display:grid;grid-template-columns:170px 1fr;gap:8px}
.pic{display:flex;align-items:center;gap:18px}
.pic .big-avatar{width:88px;height:88px;font-size:34px;cursor:pointer}
.pic .acts{display:flex;flex-direction:column;gap:6px}
.pic .acts label{display:inline-flex;align-items:center;gap:8px;width:max-content;padding:10px 16px;border-radius:999px;border:1.5px solid var(--brown);color:var(--brown);font-weight:700;font-size:14px;cursor:pointer}
.pic .acts label:hover{background:var(--cream)}
.pic .acts small{color:var(--muted);font-size:12.5px}
.pic input{position:absolute;opacity:0;width:1px;height:1px}
.save-bar{position:sticky;bottom:0;z-index:30;display:flex;gap:10px;justify-content:flex-end;padding:14px 0;background:linear-gradient(0deg,#fff 70%,rgba(255,255,255,0))}
.save-bar .btn{padding:14px 26px}
@media (max-width:620px){ .two{grid-template-columns:1fr} .phone{grid-template-columns:1fr} .save-bar .btn{flex:1} }
</style>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-camera" viewBox="0 0 24 24"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></symbol>
</svg>

<main>

  <section class="acct-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>">Home</a><span aria-hidden="true">/</span><span>My account</span><span aria-hidden="true">/</span><span aria-current="page">Edit profile</span></nav>
      <div class="acct-head">
        <span class="big-avatar" aria-hidden="true"><?php if (!empty($avatarSrc)): ?><img src="<?= h3_e($avatarSrc) ?>" alt=""><?php else: ?><?= h3_e($avatarInitial) ?><?php endif; ?></span>
        <div><h1>Edit profile</h1><p><?= h3_e($acctEmail) ?></p></div>
      </div>
      <nav class="acct-tabs" aria-label="My account">
        <a href="<?= h3_link('profile') ?>" aria-current="page"><svg class="ico"><use href="#i-user"/></svg>Profile</a>
        <a href="<?= h3_link('orders') ?>"><svg class="ico"><use href="#i-orders"/></svg>Orders</a>
        <a href="<?= h3_link('wishlist') ?>"><svg class="ico"><use href="#i-heart"/></svg>Wishlist</a>
        <a href="<?= h3_link('cart') ?>"><svg class="ico"><use href="#i-bag"/></svg>Cart</a>
        <a class="out" href="<?= h3_link('logout') ?>"><svg class="ico"><use href="#i-logout"/></svg>Log out</a>
      </nav>
    </div>
  </section>

  <section class="acct-body">
    <div class="wrap">
      <?php if ($formError): ?><p class="flash bad" role="alert"><?= h3_e($formError) ?></p>
      <?php elseif ($errors): ?><p class="flash bad" role="alert">Please fix the highlighted fields below.</p><?php endif; ?>

      <form class="ep" method="POST" action="edit_profile3.php" enctype="multipart/form-data" novalidate id="epForm">
        <input type="hidden" name="csrf" value="<?= h3_e($CSRF) ?>">

        <section class="panel">
          <h2>Profile photo</h2>
          <div class="pic">
            <label class="big-avatar" for="pp" id="ppBox" aria-label="Change photo"><?php if ($avatarSrc): ?><img src="<?= h3_e($avatarSrc) ?>" alt="" id="ppImg"><?php else: ?><span id="ppInitial"><?= h3_e($avatarInitial) ?></span><?php endif; ?></label>
            <div class="acts">
              <label for="pp"><svg class="ico" style="width:18px;height:18px"><use href="#i-camera"/></svg>Choose a photo</label>
              <small>JPG or PNG, up to 2 MB</small>
              <input type="file" id="pp" name="profile_picture" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
              <?= ep3_err($errors, 'profile_picture') ?><p class="ferr" id="ppErr2" hidden></p>
            </div>
          </div>
        </section>

        <section class="panel">
          <h2>Personal details</h2>
          <div class="two">
            <div class="field"><label for="firstname">First name</label><input id="firstname" name="firstname" value="<?= h3_e($f['firstname']) ?>" autocomplete="given-name" required data-letters<?= ep3_inv($errors, 'firstname') ?>><?= ep3_err($errors, 'firstname') ?></div>
            <div class="field"><label for="lastname">Last name</label><input id="lastname" name="lastname" value="<?= h3_e($f['lastname']) ?>" autocomplete="family-name" required data-letters<?= ep3_inv($errors, 'lastname') ?>><?= ep3_err($errors, 'lastname') ?></div>
          </div>
          <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= h3_e($f['email']) ?>" autocomplete="email" required<?= ep3_inv($errors, 'email') ?>>
            <?= ep3_err($errors, 'email') ?: '<p class="hint">If you change it, use a Gmail or Yahoo address.</p>' ?></div>
          <div class="field"><label for="phonenumber">Phone number</label>
            <div class="phone">
              <select name="country_code" aria-label="Country code"><?php foreach ($COUNTRY_CODES as $code => $label): ?><option value="<?= h3_e($code) ?>" <?= $f['country_code'] === $code ? 'selected' : '' ?>><?= h3_e($label) ?></option><?php endforeach; ?></select>
              <input type="tel" id="phonenumber" name="phonenumber" value="<?= h3_e($f['phonenumber']) ?>" inputmode="numeric" maxlength="10" placeholder="10-digit number" autocomplete="tel-national" required data-digits<?= ep3_inv($errors, 'phonenumber') ?>>
            </div><?= ep3_err($errors, 'phonenumber') ?></div>
        </section>

        <section class="panel">
          <h2>Delivery address</h2>
          <div class="field"><label for="area">Area / street</label><input id="area" name="area" value="<?= h3_e($f['area']) ?>" placeholder="e.g. 12 MG Road, Dwaraka Nagar" autocomplete="street-address" required<?= ep3_inv($errors, 'area') ?>><?= ep3_err($errors, 'area') ?></div>
          <div class="two">
            <div class="field"><label for="landmark">Landmark <small>(optional)</small></label><input id="landmark" name="landmark" value="<?= h3_e($f['landmark']) ?>" placeholder="e.g. Near City Mall"></div>
            <div class="field"><label for="zipcode">Zip / PIN code</label><input id="zipcode" name="zipcode" value="<?= h3_e($f['zipcode']) ?>" inputmode="numeric" maxlength="10" placeholder="e.g. 530016" autocomplete="postal-code" required data-digits<?= ep3_inv($errors, 'zipcode') ?>><?= ep3_err($errors, 'zipcode') ?></div>
          </div>
        </section>

        <div class="save-bar">
          <a class="btn btn-outline" href="user_profile3.php">Cancel</a>
          <button type="submit" class="btn btn-primary" id="saveBtn">Save changes</button>
        </div>
      </form>
    </div>
  </section>
</main>

<script>
(function () {
  document.querySelectorAll('[data-letters]').forEach(i => i.addEventListener('input', () => { i.value = i.value.replace(/[^a-zA-Z\s]/g, ''); }));
  document.querySelectorAll('[data-digits]').forEach(i => i.addEventListener('input', () => { i.value = i.value.replace(/\D/g, '').slice(0, +i.maxLength || 10); }));
  const pp = document.getElementById('pp'), box = document.getElementById('ppBox'), err = document.getElementById('ppErr2');
  pp.addEventListener('change', () => {
    err.hidden = true; const f = pp.files[0]; if (!f) return;
    if (!['image/jpeg', 'image/png'].includes(f.type)) { err.textContent = 'Please choose a JPG or PNG image.'; err.hidden = false; pp.value = ''; return; }
    if (f.size > 2 * 1024 * 1024) { err.textContent = 'The photo must be smaller than 2 MB.'; err.hidden = false; pp.value = ''; return; }
    const r = new FileReader(); r.onload = e => { box.innerHTML = `<img src="${e.target.result}" alt="">`; }; r.readAsDataURL(f);
  });
  document.getElementById('epForm').addEventListener('submit', e => {
    const bad = ['firstname', 'lastname', 'email', 'phonenumber', 'area', 'zipcode'].map(id => document.getElementById(id)).find(i => !i.value.trim());
    if (bad) { e.preventDefault(); bad.focus(); if (window.A3) A3.toast('Please fill in all required fields.'); return; }
    const b = document.getElementById('saveBtn'); b.disabled = true; b.textContent = 'Saving…';
  });
  const firstBad = document.querySelector('[aria-invalid="true"]'); if (firstBad) firstBad.focus();
})();
</script>

<?php include __DIR__ . '/footer3.php'; ?>