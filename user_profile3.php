<?php
/* =====================================================================
   ARAKU TRIBE - user_profile3.php  (My profile)
   Same data as user_profile.php (users: name, email, phone, area,
   landmark, zip code, profile picture). Fix: checks sign-in BEFORE
   anything is shown (the old page included the header first).
   Edit -> edit_profile3.php. Uses header3.php + footer3.php.
   ===================================================================== */
session_start();
include 'db1.php';
if (empty($_SESSION['user_id'])) {                      // not signed in: sign in first, then come back here
    $_SESSION['redirect_after_login'] = basename(__FILE__);
    if ((($_POST['ajax'] ?? '') === '1') || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_GET['ajax']) && $_GET['ajax'] === '1')) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'redirect', 'url' => 'coffee_login3.php']);
        exit;
    }
    header('Location: coffee_login3.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];
if (empty($_SESSION['csrf3'])) $_SESSION['csrf3'] = bin2hex(random_bytes(16));   // protects forms from other sites
$CSRF = $_SESSION['csrf3'];
if (!isset($conn) || !($conn instanceof mysqli)) { $conn = new mysqli($host, $user, $pass, $db); }
if ($conn->connect_error) { die('Database connection failed. Please try again in a moment.'); }

$st = $conn->prepare("SELECT firstname, lastname, email, phonenumber, area, landmark, zipcode, profile_picture FROM users WHERE user_id = ? LIMIT 1");
$st->bind_param("i", $user_id); $st->execute(); $me = $st->get_result()->fetch_assoc(); $st->close();

$orderCount = 0; $activeCount = 0;
$oc = $conn->prepare("SELECT order_status FROM orders WHERE user_id = ?");
if ($oc) { $oc->bind_param("i", $user_id); $oc->execute(); $r = $oc->get_result();
    while ($row = $r->fetch_assoc()) { $orderCount++; if (!in_array(strtolower((string)$row['order_status']), ['delivered', 'cancelled'], true)) $activeCount++; }
    $oc->close(); }
$wishCount = 0;
$wc = $conn->prepare("SELECT COUNT(*) AS n FROM wishlist WHERE user_id = ?");
if ($wc) { $wc->bind_param("i", $user_id); $wc->execute(); $wishCount = (int)($wc->get_result()->fetch_assoc()['n'] ?? 0); $wc->close(); }
$conn->close();

$me = $me ?: [];
$acctFirst = trim((string)($me['firstname'] ?? $_SESSION['user_name'] ?? ''));
$acctEmail = (string)($me['email'] ?? $_SESSION['user_email'] ?? '');
$avatarInitial = strtoupper(substr($acctFirst !== '' ? $acctFirst : 'A', 0, 1));
$avatarSrc = (!empty($me['profile_picture']) && is_file('uploads/profile_pictures/' . $me['profile_picture'])) ? 'uploads/profile_pictures/' . $me['profile_picture'] : '';
$countryCode = $_SESSION['country_code'] ?? '+91';
$v = fn($k) => trim((string)($me[$k] ?? ''));
$missing = array_filter(['phone number' => $v('phonenumber'), 'area' => $v('area'), 'zip code' => $v('zipcode')], fn($x) => $x === '');
$saved = (($_GET['status'] ?? '') === 'success');

$PAGE_TITLE = 'My profile - Araku Tribe';
$ACTIVE_NAV = '';

// Dual Mode / SPA detection: check for AJAX header or parameter
$isAjax       = (($_POST['ajax'] ?? '') === '1') || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_GET['ajax']) && $_GET['ajax'] === '1');
$isPartial    = isset($_GET['partial']) && $_GET['partial'] === '1';

if ($isPartial) {
    function pf3_row_p($label, $value, $empty = 'Not added yet') {
        return '<div class="kv"><dt>' . h3_e($label) . '</dt><dd' . ($value === '' ? ' class="none"' : '') . '>' . h3_e($value !== '' ? $value : $empty) . '</dd></div>';
    }
    ?>
    <main id="spa-main-content" data-tab-style="brown">
      <section class="acct-hero">
        <div class="wrap">
          <style>
/* ---- page navigation bar (breadcrumb pills + back button), same look as the Products / About pages ---- */
.pg-crumb-row{display:flex;align-items:center;justify-content:space-between;gap:10px 20px;flex-wrap:wrap;margin:0 0 14px}
.pg-crumb-row .crumbs{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:0;font-size:13.5px;color:var(--muted)}
.pg-crumb-row .crumbs a,.pg-crumb-row .crumbs a:visited{display:inline-flex;align-items:center;padding:5px 14px;border-radius:999px;background:var(--brown);color:var(--cream);font-weight:600;font-size:13.5px;line-height:1.4;text-decoration:none;transition:background-color .2s}
.pg-crumb-row .crumbs a:hover{background:var(--brown-deep);color:var(--cream)}
.pg-crumb-row .crumbs .sep{color:var(--muted)}
.pg-crumb-row .crumbs span.cur,.pg-crumb-row .crumbs span[aria-current="page"]{display:inline-flex;align-items:center;padding:5px 14px!important;border-radius:999px!important;background:linear-gradient(90deg,#E3131B 0%,#F4510B 50%,#FE7402 100%)!important;border:0!important;box-shadow:none!important;text-shadow:none!important;margin:0!important;font-size:13.5px;font-weight:700;line-height:1.4;color:#fff!important}
.close-btn{display:inline-flex;align-items:center;gap:8px;flex:none;min-height:46px;padding:0 20px;border-radius:999px;border:2px solid var(--brown-deep);background:#fff;color:var(--brown-deep);
  font:inherit;font-weight:700;font-size:15.5px;line-height:1.2;text-decoration:none;white-space:nowrap;cursor:pointer;transition:background-color .2s,color .2s}
.close-btn:hover{background:var(--brown-deep);color:#fff}
.close-btn .ico{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
@media (max-width:560px){.close-btn{min-height:42px;padding:0 16px;font-size:14.5px}}
</style>
<div class="pg-crumb-row"><nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>" data-spa-link>Home</a><span class="sep" aria-hidden="true">/</span><a class="mid" href="<?= h3_link('profile') ?>" data-spa-link>My account</a><span class="sep" aria-hidden="true">/</span><span class="cur" aria-current="page">Profile</span></nav><a class="close-btn" id="pgClose" href="<?= h3_link('home') ?>" data-spa-link onclick="try{if(document.referrer&&new URL(document.referrer).origin===location.origin&&history.length>1){event.preventDefault();event.stopPropagation();history.back();}}catch(e){}"><svg class="ico" aria-hidden="true" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>Back to previous page</a></div>
          <div class="acct-head">
            <span class="big-avatar" aria-hidden="true"><?php if (!empty($avatarSrc)): ?><img src="<?= h3_e($avatarSrc) ?>" alt=""><?php else: ?><?= h3_e($avatarInitial) ?><?php endif; ?></span>
            <div><h1>Hello, <?= h3_e($acctFirst !== '' ? $acctFirst : 'there') ?></h1><p><?= h3_e($acctEmail) ?></p></div>
          </div>
          <nav class="acct-tabs" aria-label="My account">
            <a href="<?= h3_link('profile') ?>" aria-current="page" data-spa-link><svg class="ico"><use href="#i-user"/></svg>Profile</a>
            <a href="<?= h3_link('orders') ?>" data-spa-link><svg class="ico"><use href="#i-orders"/></svg>Orders</a>
            <a href="<?= h3_link('wishlist') ?>" data-spa-link><svg class="ico"><use href="#i-heart"/></svg>Wishlist</a>
            <a href="<?= h3_link('cart') ?>" data-spa-link><svg class="ico"><use href="#i-bag"/></svg>Cart</a>
            <a class="out" href="<?= h3_link('logout') ?>" data-spa-link><svg class="ico"><use href="#i-logout"/></svg>Log out</a>
          </nav>
        </div>
      </section>

      <section class="acct-body">
        <div class="wrap">
          <?php if ($saved): ?><p class="flash ok" role="status">Your profile has been updated.</p><?php endif; ?>
          <?php if (!$me): ?><p class="flash bad" role="alert">We couldn't load your profile right now. Please refresh the page.</p><?php endif; ?>

          <div class="p-grid">
            <div class="p-col">
              <section class="panel" aria-labelledby="pd">
                <div class="head"><h2 id="pd"><svg class="ico"><use href="#i-user"/></svg>Personal details</h2><a href="edit_profile3.php" data-spa-link>Edit</a></div>
                <dl class="kvs">
                  <?= pf3_row_p('First name', $v('firstname')) ?>
                  <?= pf3_row_p('Last name', $v('lastname')) ?>
                  <?= pf3_row_p('Email', $v('email')) ?>
                  <?= pf3_row_p('Phone', $v('phonenumber') !== '' ? $countryCode . ' ' . $v('phonenumber') : '') ?>
                </dl>
              </section>

              <section class="panel" aria-labelledby="sa">
                <div class="head"><h2 id="sa"><svg class="ico"><use href="#i-pin"/></svg>Delivery address</h2><a href="edit_profile3.php#area" data-spa-link>Edit</a></div>
                <?php if ($missing): ?>
                  <p class="nudge">Add your <?= h3_e(implode(', ', array_keys($missing))) ?> so checkout is quicker. <a href="edit_profile3.php" data-spa-link>Add now</a></p>
                <?php endif; ?>
                <dl class="kvs">
                  <?= pf3_row_p('Area / street', $v('area')) ?>
                  <?= pf3_row_p('Landmark', $v('landmark'), 'None') ?>
                  <?= pf3_row_p('Zip code', $v('zipcode')) ?>
                </dl>
              </section>
            </div>

            <div class="p-col">
              <section class="panel" aria-label="Your activity">
                <div class="stats">
                  <a href="<?= h3_link('orders') ?>" data-spa-link><b><?= (int)$orderCount ?></b><span>Orders</span></a>
                  <a href="<?= h3_link('orders') ?>" data-spa-link><b><?= (int)$activeCount ?></b><span>In progress</span></a>
                  <a href="<?= h3_link('wishlist') ?>" data-spa-link><b><?= (int)$wishCount ?></b><span>Saved</span></a>
                </div>
              </section>

              <section class="panel" aria-labelledby="sec">
                <div class="head"><h2 id="sec"><svg class="ico"><use href="#i-shield"/></svg>Sign-in &amp; security</h2></div>
                <ul class="sec">
                  <li><svg class="ico"><use href="#i-shield"/></svg>Two-step sign-in is on: password, then a code by email.</li>
                  <li><svg class="ico"><use href="#i-key"/></svg>Password<a href="forgot_password3.php?email=<?= rawurlencode($acctEmail) ?>" data-spa-link>Change</a></li>
                </ul>
              </section>

              <section class="panel danger" aria-labelledby="dz">
                <h2 id="dz">Delete account</h2>
                <p>Deleting your account deactivates it straight away, with a 30-day grace period to change your mind. See the <a href="terms_deletion3.php" data-spa-link>account deletion terms</a>.</p>
                <a class="btn" href="delete_account3.php" data-spa-link>Delete my account</a>
              </section>
            </div>
          </div>
        </div>
      </section>
    </main>
    <?php
    exit;
}

$TAB_STYLE = 'brown';   // brown header tabs, same as the About / Products / Profile pages
include __DIR__ . '/header3.php';
function pf3_row($label, $value, $empty = 'Not added yet') {
    return '<div class="kv"><dt>' . h3_e($label) . '</dt><dd' . ($value === '' ? ' class="none"' : '') . '>' . h3_e($value !== '' ? $value : $empty) . '</dd></div>';
}
?>
<style>
/* ============ user_profile3.php ============ */

.acct-hero{background:var(--cream);padding:28px 0 0}
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

.p-grid{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:20px;align-items:start}
.p-col{display:grid;gap:20px}
.panel .head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
.panel .head a{font-weight:700;font-size:14px;color:var(--orange-cta);text-decoration:none}
.panel .head a:hover{text-decoration:underline}
.kvs{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:0}
.kv{padding:12px 14px;border-radius:14px;background:var(--cream)}
.kv dt{font-size:12.5px;color:var(--muted)}
.kv dd{margin:3px 0 0;font-weight:600;color:var(--brown-deep);word-break:break-word}
.kv dd.none{font-weight:500;color:var(--muted);font-style:italic}
.nudge{display:flex;gap:12px;align-items:center;margin:0 0 16px;padding:12px 14px;border-radius:14px;background:#FFF1D6;color:#6A4A12;font-size:14px}
.nudge a{margin-left:auto;font-weight:700;color:#6A4A12}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.stats a{display:block;padding:14px;border-radius:16px;border:1px solid var(--line);text-decoration:none;text-align:center;transition:border-color .2s}
.stats a:hover{border-color:var(--brown-soft)}
.stats b{display:block;font-family:var(--serif);font-weight:400;font-size:1.8rem;color:var(--brown)}
.stats span{font-size:13px;color:var(--muted)}
.sec{display:grid;gap:10px;margin:0;padding:0;list-style:none}
.sec li{display:flex;gap:12px;align-items:center;padding:12px 14px;border-radius:14px;background:var(--cream);font-size:14.5px}
.sec li .ico{flex:none;width:20px;height:20px;color:var(--green)}
.sec li a{margin-left:auto;font-weight:700;font-size:13.5px;color:var(--orange-cta)}
.danger{border-color:#F0C9C9}
.danger h2{color:#9B2626}
.danger p{margin:6px 0 14px;font-size:14px;color:var(--muted)}
.danger .btn{border:1.5px solid #E7B9B9;color:#9B2626;background:#fff}
.danger .btn:hover{background:#FBE9E9}
@media (max-width:960px){ .p-grid{grid-template-columns:1fr} }
@media (max-width:520px){ .kvs{grid-template-columns:1fr} }
/* ---- page navigation bar (breadcrumb pills + back button), same look as the Products / About pages ---- */
.pg-crumb-row{display:flex;align-items:center;justify-content:space-between;gap:10px 20px;flex-wrap:wrap;margin:0 0 14px}
.pg-crumb-row .crumbs{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:0;font-size:13.5px;color:var(--muted)}
.pg-crumb-row .crumbs a,.pg-crumb-row .crumbs a:visited{display:inline-flex;align-items:center;padding:5px 14px;border-radius:999px;background:var(--brown);color:var(--cream);font-weight:600;font-size:13.5px;line-height:1.4;text-decoration:none;transition:background-color .2s}
.pg-crumb-row .crumbs a:hover{background:var(--brown-deep);color:var(--cream)}
.pg-crumb-row .crumbs .sep{color:var(--muted)}
.pg-crumb-row .crumbs span.cur,.pg-crumb-row .crumbs span[aria-current="page"]{display:inline-flex;align-items:center;padding:5px 14px!important;border-radius:999px!important;background:linear-gradient(90deg,#E3131B 0%,#F4510B 50%,#FE7402 100%)!important;border:0!important;box-shadow:none!important;text-shadow:none!important;margin:0!important;font-size:13.5px;font-weight:700;line-height:1.4;color:#fff!important}
.close-btn{display:inline-flex;align-items:center;gap:8px;flex:none;min-height:46px;padding:0 20px;border-radius:999px;border:2px solid var(--brown-deep);background:#fff;color:var(--brown-deep);
  font:inherit;font-weight:700;font-size:15.5px;line-height:1.2;text-decoration:none;white-space:nowrap;cursor:pointer;transition:background-color .2s,color .2s}
.close-btn:hover{background:var(--brown-deep);color:#fff}
.close-btn .ico{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
@media (max-width:560px){.close-btn{min-height:42px;padding:0 16px;font-size:14.5px}}
</style>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.5-3 7.8-7 9-4-1.2-7-4.5-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/></symbol>
  <symbol id="i-key" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3"/></symbol>
</svg>

<main id="spa-main-content" data-tab-style="brown">

  <section class="acct-hero">
    <div class="wrap">
      <div class="pg-crumb-row"><nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>" data-spa-link>Home</a><span class="sep" aria-hidden="true">/</span><a class="mid" href="<?= h3_link('profile') ?>" data-spa-link>My account</a><span class="sep" aria-hidden="true">/</span><span class="cur" aria-current="page">Profile</span></nav><a class="close-btn" id="pgClose" href="<?= h3_link('home') ?>" data-spa-link onclick="try{if(document.referrer&&new URL(document.referrer).origin===location.origin&&history.length>1){event.preventDefault();event.stopPropagation();history.back();}}catch(e){}"><svg class="ico" aria-hidden="true" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>Back to previous page</a></div>
      <div class="acct-head">
        <span class="big-avatar" aria-hidden="true"><?php if (!empty($avatarSrc)): ?><img src="<?= h3_e($avatarSrc) ?>" alt=""><?php else: ?><?= h3_e($avatarInitial) ?><?php endif; ?></span>
        <div><h1>Hello, <?= h3_e($acctFirst !== '' ? $acctFirst : 'there') ?></h1><p><?= h3_e($acctEmail) ?></p></div>
      </div>
      <nav class="acct-tabs" aria-label="My account">
        <a href="<?= h3_link('profile') ?>" aria-current="page" data-spa-link><svg class="ico"><use href="#i-user"/></svg>Profile</a>
        <a href="<?= h3_link('orders') ?>" data-spa-link><svg class="ico"><use href="#i-orders"/></svg>Orders</a>
        <a href="<?= h3_link('wishlist') ?>" data-spa-link><svg class="ico"><use href="#i-heart"/></svg>Wishlist</a>
        <a href="<?= h3_link('cart') ?>" data-spa-link><svg class="ico"><use href="#i-bag"/></svg>Cart</a>
        <a class="out" href="<?= h3_link('logout') ?>" data-spa-link><svg class="ico"><use href="#i-logout"/></svg>Log out</a>
      </nav>
    </div>
  </section>

  <section class="acct-body">
    <div class="wrap">
      <?php if ($saved): ?><p class="flash ok" role="status">Your profile has been updated.</p><?php endif; ?>
      <?php if (!$me): ?><p class="flash bad" role="alert">We couldn't load your profile right now. Please refresh the page.</p><?php endif; ?>

      <div class="p-grid">
        <div class="p-col">
          <section class="panel" aria-labelledby="pd">
            <div class="head"><h2 id="pd"><svg class="ico"><use href="#i-user"/></svg>Personal details</h2><a href="edit_profile3.php" data-spa-link>Edit</a></div>
            <dl class="kvs">
              <?= pf3_row('First name', $v('firstname')) ?>
              <?= pf3_row('Last name', $v('lastname')) ?>
              <?= pf3_row('Email', $v('email')) ?>
              <?= pf3_row('Phone', $v('phonenumber') !== '' ? $countryCode . ' ' . $v('phonenumber') : '') ?>
            </dl>
          </section>

          <section class="panel" aria-labelledby="sa">
            <div class="head"><h2 id="sa"><svg class="ico"><use href="#i-pin"/></svg>Delivery address</h2><a href="edit_profile3.php#area" data-spa-link>Edit</a></div>
            <?php if ($missing): ?>
              <p class="nudge">Add your <?= h3_e(implode(', ', array_keys($missing))) ?> so checkout is quicker. <a href="edit_profile3.php" data-spa-link>Add now</a></p>
            <?php endif; ?>
            <dl class="kvs">
              <?= pf3_row('Area / street', $v('area')) ?>
              <?= pf3_row('Landmark', $v('landmark'), 'None') ?>
              <?= pf3_row('Zip code', $v('zipcode')) ?>
            </dl>
          </section>
        </div>

        <div class="p-col">
          <section class="panel" aria-label="Your activity">
            <div class="stats">
              <a href="<?= h3_link('orders') ?>" data-spa-link><b><?= (int)$orderCount ?></b><span>Orders</span></a>
              <a href="<?= h3_link('orders') ?>" data-spa-link><b><?= (int)$activeCount ?></b><span>In progress</span></a>
              <a href="<?= h3_link('wishlist') ?>" data-spa-link><b><?= (int)$wishCount ?></b><span>Saved</span></a>
            </div>
          </section>

          <section class="panel" aria-labelledby="sec">
            <div class="head"><h2 id="sec"><svg class="ico"><use href="#i-shield"/></svg>Sign-in &amp; security</h2></div>
            <ul class="sec">
              <li><svg class="ico"><use href="#i-shield"/></svg>Two-step sign-in is on: password, then a code by email.</li>
              <li><svg class="ico"><use href="#i-key"/></svg>Password<a href="forgot_password3.php?email=<?= rawurlencode($acctEmail) ?>" data-spa-link>Change</a></li>
            </ul>
          </section>

          <section class="panel danger" aria-labelledby="dz">
            <h2 id="dz">Delete account</h2>
            <p>Deleting your account deactivates it straight away, with a 30-day grace period to change your mind. See the <a href="terms_deletion3.php" data-spa-link>account deletion terms</a>.</p>
            <a class="btn" href="delete_account3.php" data-spa-link>Delete my account</a>
          </section>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/footer3.php'; ?>