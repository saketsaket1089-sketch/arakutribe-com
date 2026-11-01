<?php
/* =====================================================================
   ARAKU TRIBE - process_order3.php  (Checkout step 2: delivery details)
   Same logic as process_order.php: needs $_SESSION['order_data'] (from
   cart3.php or the chat agent; guests from the agent allowed via
   order_data['guest_email']), validates the same fields, saves
   $_SESSION['shipping_details'] and goes to dummy_payment3.php.
   Saved addresses from past orders can be reused with one tap.
   Fixes: no mbstring needed; security token; errors next to each field;
   your account email is always accepted (new emails follow the
   Gmail/Yahoo rule). Uses header3.php + footer3.php.
   ===================================================================== */
session_start();
include 'db1.php';
if (!isset($conn) || !($conn instanceof mysqli)) { $conn = new mysqli($host, $user, $pass, $db); }
require_once 'currency_helper.php';
$displayCurrency = resolve_display_currency($conn);
$currencyInfo    = $CURRENCY_INFO[$displayCurrency];

function po3_log($m) { @file_put_contents('app_errors.log', '[' . date('Y-m-d H:i:s') . '] [USER:' . ($_SESSION['user_id'] ?? 'GUEST') . "] [PROCESS_ORDER3] $m\n", FILE_APPEND); }

// Dual Mode / SPA detection: check for AJAX header or parameter
$isAjax       = (($_POST['ajax'] ?? '') === '1') || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_GET['ajax']) && $_GET['ajax'] === '1');

$is_guest_checkout = !empty($_SESSION['order_data']['guest_email']);
if (!isset($_SESSION['user_id']) && !$is_guest_checkout) {
    $_SESSION['redirect_after_login'] = 'cart3.php';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'redirect', 'url' => 'coffee_login3.php']);
        exit;
    }
    header('Location: coffee_login3.php'); exit;
}
if (!isset($_SESSION['order_data']['total']) || empty($_SESSION['order_data']['items'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'redirect', 'url' => 'cart3.php']);
        exit;
    }
    header('Location: cart3.php'); exit;
}
if (empty($_SESSION['csrf3'])) $_SESSION['csrf3'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf3'];

$COUNTRY_CODES = ['+91' => 'India (+91)', '+1' => 'USA (+1)', '+44' => 'UK (+44)', '+61' => 'Australia (+61)', '+971' => 'UAE (+971)',
                  '+65' => 'Singapore (+65)', '+60' => 'Malaysia (+60)', '+49' => 'Germany (+49)', '+33' => 'France (+33)', '+81' => 'Japan (+81)'];
$ALLOWED_EMAIL_DOMAINS = ['gmail.com', 'yahoo.com', 'yahoo.co.in', 'yahoo.co.uk', 'yahoo.com.au'];   // same rule as before; [] = any domain
$STATES = ['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat','Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala',
           'Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura',
           'Uttar Pradesh','Uttarakhand','West Bengal','Delhi','Jammu and Kashmir','Ladakh','Puducherry','Chandigarh'];

$accountEmail = (string)($_SESSION['user_email'] ?? '');
$total = (float)$_SESSION['order_data']['total'];
$items = $_SESSION['order_data']['items'];

$d = $_SESSION['shipping_details'] ?? [
    'name' => (string)($_SESSION['user_name'] ?? ''), 'email' => $accountEmail ?: (string)($_SESSION['order_data']['guest_email'] ?? ''),
    'country_code' => $_SESSION['country_code'] ?? '+91', 'phone' => '', 'address' => (string)($_SESSION['order_data']['shipping_address'] ?? ''),
    'city' => '', 'state' => '', 'zipcode' => '', 'billing_same' => '1', 'billing_address' => '', 'billing_city' => '', 'billing_state' => '', 'billing_zipcode' => '',
];

/* prefill from the profile the first time (phone, area, zip) */
if (empty($_SESSION['shipping_details']) && !empty($_SESSION['user_id'])) {
    $st = $conn->prepare("SELECT firstname, lastname, phonenumber, area, zipcode FROM users WHERE user_id = ? LIMIT 1");
    if ($st) { $uid = (int)$_SESSION['user_id']; $st->bind_param("i", $uid); $st->execute(); $u = $st->get_result()->fetch_assoc() ?: []; $st->close();
        if ($d['name'] === '' || strpos($d['name'], ' ') === false) $d['name'] = trim(($u['firstname'] ?? $d['name']) . ' ' . ($u['lastname'] ?? ''));
        if ($d['phone'] === '' && !empty($u['phonenumber'])) { $d['phone'] = preg_replace('/\D/', '', $u['phonenumber']); if (strlen($d['phone']) > 10) $d['phone'] = substr($d['phone'], -10); }
        if ($d['address'] === '' && !empty($u['area'])) $d['address'] = $u['area'];
        if ($d['zipcode'] === '' && !empty($u['zipcode'])) $d['zipcode'] = $u['zipcode'];
    }
}

/* saved addresses from past orders (same as before) */
$saved = [];
if (!empty($_SESSION['user_id'])) {
    $st = $conn->prepare("SELECT DISTINCT shipping_address FROM orders WHERE user_id = ? AND shipping_address IS NOT NULL AND shipping_address <> '' ORDER BY order_date DESC LIMIT 5");
    if ($st) { $uid = (int)$_SESSION['user_id']; $st->bind_param("i", $uid); $st->execute(); $r = $st->get_result(); while ($row = $r->fetch_assoc()) $saved[] = $row['shipping_address']; $st->close(); }
} elseif ($is_guest_checkout) {
    $st = $conn->prepare("SELECT DISTINCT shipping_address FROM orders WHERE guest_email = ? AND shipping_address IS NOT NULL AND shipping_address <> '' ORDER BY order_date DESC LIMIT 5");
    if ($st) { $ge = $_SESSION['order_data']['guest_email']; $st->bind_param("s", $ge); $st->execute(); $r = $st->get_result(); while ($row = $r->fetch_assoc()) $saved[] = $row['shipping_address']; $st->close(); }
}
$saved = array_values(array_unique($saved));

$errors = []; $formError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_shipping'])) {
    foreach (['name','email','country_code','address','city','state','zipcode','billing_address','billing_city','billing_state','billing_zipcode'] as $k) $d[$k] = trim((string)($_POST[$k] ?? ''));
    $d['email'] = filter_var($d['email'], FILTER_SANITIZE_EMAIL);
    $d['phone'] = preg_replace('/\D/', '', (string)($_POST['phone'] ?? ''));
    if (strlen($d['phone']) === 12 && substr($d['phone'], 0, 2) === '91') $d['phone'] = substr($d['phone'], 2);   // +91 98765 43210
    elseif (strlen($d['phone']) === 11 && $d['phone'][0] === '0') $d['phone'] = substr($d['phone'], 1);          // 098765 43210
    $d['billing_same'] = isset($_POST['billing_same']) ? '1' : '0';
    if ($d['billing_same'] === '1') { $d['billing_address'] = $d['address']; $d['billing_city'] = $d['city']; $d['billing_state'] = $d['state']; $d['billing_zipcode'] = $d['zipcode']; }
    $shipStateExempt = ($_POST['shipping_from_saved'] ?? '0') === '1';
    $billStateExempt = ($_POST['billing_from_saved'] ?? '0') === '1';

    if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) $formError = 'Your session expired. Please try again.';
    $letters = "/^[a-zA-Z\\s.'\\-]+$/";   // letters, spaces, dots, apostrophes, hyphens
    if ($d['name'] === '') $errors['name'] = 'Please enter your full name.';
    elseif (!preg_match($letters, $d['name'])) $errors['name'] = 'Use letters only (spaces, dots and hyphens are fine).';
    if ($d['email'] === '') $errors['email'] = 'Please enter your email.';
    elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address.';
    elseif ($ALLOWED_EMAIL_DOMAINS && strcasecmp($d['email'], $accountEmail) !== 0 && !in_array(strtolower(substr(strrchr($d['email'], '@'), 1)), $ALLOWED_EMAIL_DOMAINS, true))
        $errors['email'] = 'Please use a Gmail or Yahoo address, or your account email.';
    if ($d['phone'] !== '' && !preg_match('/^\d{10}$/', $d['phone'])) $errors['phone'] = 'Phone number must be exactly 10 digits.';
    if (!array_key_exists($d['country_code'], $COUNTRY_CODES)) $errors['phone'] = 'Please choose a country code from the list.';
    if ($d['address'] === '') $errors['address'] = 'Please enter your street address.';
    if ($d['city'] === '') $errors['city'] = 'Please enter your city.';
    elseif (!preg_match($letters, $d['city'])) $errors['city'] = 'Use letters only (spaces, dots and hyphens are fine).';
    if ($d['state'] === '' && !$shipStateExempt) $errors['state'] = 'Please choose your state.';
    elseif ($d['state'] !== '' && !preg_match($letters, $d['state'])) $errors['state'] = 'Use letters only.';
    if (!preg_match('/^\d{4,10}$/', $d['zipcode'])) $errors['zipcode'] = 'Use numbers only, 4 to 10 digits.';
    if ($d['billing_same'] !== '1') {
        if ($d['billing_address'] === '') $errors['billing_address'] = 'Please enter the billing street address.';
        if ($d['billing_city'] === '' || !preg_match($letters, $d['billing_city'])) $errors['billing_city'] = 'Please enter a billing city (letters only).';
        if ($d['billing_state'] === '' && !$billStateExempt) $errors['billing_state'] = 'Please choose the billing state.';
        elseif ($d['billing_state'] !== '' && !preg_match($letters, $d['billing_state'])) $errors['billing_state'] = 'Use letters only.';
        if (!preg_match('/^\d{4,10}$/', $d['billing_zipcode'])) $errors['billing_zipcode'] = 'Use numbers only, 4 to 10 digits.';
    }
    if (!$errors && !$formError) {
        $_SESSION['shipping_details'] = $d;
        $_SESSION['country_code'] = $d['country_code'];
        po3_log('Shipping details validated -> dummy_payment3.php');
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'redirect', 'url' => 'dummy_payment3.php']);
            exit;
        }
        header('Location: dummy_payment3.php'); exit;
    }
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'errors' => $errors, 'formError' => $formError]);
        exit;
    }
    po3_log('Validation failed: ' . implode(', ', array_keys($errors)) . ($formError ? ' (token)' : ''));
}
$conn->close();

$money = fn($usd) => htmlspecialchars($currencyInfo['symbol']) . number_format($usd * $currencyInfo['rate'], 2);
$PAGE_TITLE = 'Delivery details - Araku Tribe';

// Dual Mode Partial Fragment check for SPA router (e.g. ?partial=1 or header X-Partial: true)
$isPartial = isset($_GET['partial']) && $_GET['partial'] === '1';
if ($isPartial) {
    function po3_err_p($e, $k) { return isset($e[$k]) ? '<p class="ferr" id="' . $k . 'Err">' . h3_e($e[$k]) . '</p>' : ''; }
    function po3_inv_p($e, $k) { return isset($e[$k]) ? ' aria-invalid="true" aria-describedby="' . $k . 'Err"' : ''; }
    function po3_short_p($s, $n = 55) { return strlen($s) > $n ? substr($s, 0, $n - 1) . '…' : $s; }
    function po3_cat_p($it) {
        $k = strtoupper((string)($it['product_id'] ?? $it['image'] ?? ''));
        foreach (['ATCUP' => 'cup', 'ATMUG' => 'mug', 'ATBAG' => 'bag', 'ATTSH' => 'tshirt'] as $p => $c) if (strpos($k, $p) === 0) return $c;
        return 'bag';
    }
    function po3_img_p($it) {
        if (!empty($it['image_path'])) return $it['image_path'];
        $img = (string)($it['image'] ?? ''); if ($img === '') return 'img/default.png';
        return strpos($img, '/') !== false ? $img : 'img/shop/' . po3_cat_p($it) . '/' . $img;
    }
    ?>
    <datalist id="states"><?php foreach ($STATES as $s): ?><option value="<?= h3_e($s) ?>"><?php endforeach; ?></datalist>

    <main id="spa-main-content">
      <section class="co-hero">
        <div class="wrap">
          <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>" data-spa-link>Home</a><span aria-hidden="true">/</span><a href="<?= h3_link('cart') ?>" data-spa-link>Cart</a><span aria-hidden="true">/</span><span aria-current="page">Delivery details</span></nav>
          <div class="row"><h1>Where should we deliver?</h1><ol class="steps" aria-label="Checkout steps"><li class="done"><a href="<?= h3_link('cart') ?>" data-spa-link><b>✓</b><span class="lbl">Cart</span></a></li><li class="bar done" aria-hidden="true"></li><li class="on"><b>2</b><span class="lbl">Details</span></li><li class="bar" aria-hidden="true"></li><li class=""><b>3</b><span class="lbl">Payment</span></li></ol></div>
        </div>
      </section>

      <?php if (function_exists('render_currency_notice')): ?><div class="wrap" style="padding-top:20px"><?= render_currency_notice($displayCurrency) ?></div><?php endif; ?>
      <form class="wrap co" method="POST" action="process_order3.php" id="shipForm" novalidate>
        <input type="hidden" name="csrf" value="<?= h3_e($CSRF) ?>">
        <input type="hidden" name="submit_shipping" value="1">
        <input type="hidden" id="shippingFromSaved" name="shipping_from_saved" value="0">
        <input type="hidden" id="billingFromSaved" name="billing_from_saved" value="0">
        <div>
          <?php if ($formError): ?><p class="flash bad" role="alert"><?= h3_e($formError) ?></p><?php endif; ?>
          <div class="err-sum" id="errSum" role="alert" tabindex="-1" <?= $errors ? '' : 'hidden' ?>>
            <strong>Please fix these to continue:</strong>
            <ul id="errList"><?php foreach ($errors as $k => $m): ?><li><a href="#<?= h3_e($k) ?>"><?= h3_e($m) ?></a></li><?php endforeach; ?></ul>
          </div>

          <section class="panel">
            <h2><span class="n">1</span>Your details</h2>
            <div class="two">
              <div class="field"><label for="name">Full name</label><input id="name" name="name" value="<?= h3_e($d['name']) ?>" autocomplete="name" required data-letters<?= po3_inv_p($errors, 'name') ?>><?= po3_err_p($errors, 'name') ?></div>
              <div class="field"><label for="email">Email <small>(for your order confirmation)</small></label><input type="email" id="email" name="email" value="<?= h3_e($d['email']) ?>" autocomplete="email" required<?= po3_inv_p($errors, 'email') ?>><?= po3_err_p($errors, 'email') ?></div>
            </div>
            <div class="field"><label for="phone">Phone <small>(optional, for the delivery partner)</small></label>
              <div class="phone"><select name="country_code" aria-label="Country code"><?php foreach ($COUNTRY_CODES as $c => $l): ?><option value="<?= h3_e($c) ?>" <?= $d['country_code'] === $c ? 'selected' : '' ?>><?= h3_e($l) ?></option><?php endforeach; ?></select>
                <input type="tel" id="phone" name="phone" value="<?= h3_e($d['phone']) ?>" inputmode="numeric" maxlength="10" placeholder="10-digit number" autocomplete="tel-national" data-digits<?= po3_inv_p($errors, 'phone') ?>></div><?= po3_err_p($errors, 'phone') ?></div>
          </section>

          <section class="panel">
            <h2><span class="n">2</span>Delivery address</h2>
            <?php if ($saved): ?>
              <p class="chips-label">Use an address from a past order</p>
              <div class="chips" data-for="shipping">
                <?php foreach ($saved as $a): ?><button type="button" data-addr="<?= h3_e($a) ?>" aria-pressed="false" title="<?= h3_e($a) ?>"><?= h3_e(po3_short_p($a)) ?></button><?php endforeach; ?>
                <button type="button" class="new" data-addr="">+ New address</button>
              </div>
            <?php endif; ?>
            <div class="field"><label for="address">Street address</label><input id="address" name="address" value="<?= h3_e($d['address']) ?>" placeholder="House no., street, area" autocomplete="street-address" required<?= po3_inv_p($errors, 'address') ?>><?= po3_err_p($errors, 'address') ?></div>
            <div class="three">
              <div class="field"><label for="city">City</label><input id="city" name="city" value="<?= h3_e($d['city']) ?>" autocomplete="address-level2" required data-letters<?= po3_inv_p($errors, 'city') ?>><?= po3_err_p($errors, 'city') ?></div>
              <div class="field"><label for="state">State</label><input id="state" name="state" list="states" value="<?= h3_e($d['state']) ?>" autocomplete="address-level1" data-letters<?= po3_inv_p($errors, 'state') ?>><?= po3_err_p($errors, 'state') ?></div>
              <div class="field"><label for="zipcode">PIN / zip code</label><input id="zipcode" name="zipcode" value="<?= h3_e($d['zipcode']) ?>" inputmode="numeric" maxlength="10" autocomplete="postal-code" required data-digits<?= po3_inv_p($errors, 'zipcode') ?>><?= po3_err_p($errors, 'zipcode') ?></div>
            </div>
          </section>

          <section class="panel">
            <h2><span class="n">3</span>Billing address</h2>
            <label class="check"><input type="checkbox" id="billingSame" name="billing_same" value="1" <?= $d['billing_same'] === '1' ? 'checked' : '' ?>>Same as delivery address</label>
            <div id="billingBox" <?= $d['billing_same'] === '1' ? 'hidden' : '' ?> style="margin-top:16px">
              <?php if ($saved): ?>
                <div class="chips" data-for="billing"><?php foreach ($saved as $a): ?><button type="button" data-addr="<?= h3_e($a) ?>" aria-pressed="false" title="<?= h3_e($a) ?>"><?= h3_e(po3_short_p($a)) ?></button><?php endforeach; ?></div>
              <?php endif; ?>
              <div class="field"><label for="billing_address">Street address</label><input id="billing_address" name="billing_address" value="<?= h3_e($d['billing_address']) ?>"<?= po3_inv_p($errors, 'billing_address') ?>><?= po3_err_p($errors, 'billing_address') ?></div>
              <div class="three">
                <div class="field"><label for="billing_city">City</label><input id="billing_city" name="billing_city" value="<?= h3_e($d['billing_city']) ?>" data-letters<?= po3_inv_p($errors, 'billing_city') ?>><?= po3_err_p($errors, 'billing_city') ?></div>
                <div class="field"><label for="billing_state">State</label><input id="billing_state" name="billing_state" list="states" value="<?= h3_e($d['billing_state']) ?>" data-letters<?= po3_inv_p($errors, 'billing_state') ?>><?= po3_err_p($errors, 'billing_state') ?></div>
                <div class="field"><label for="billing_zipcode">PIN / zip code</label><input id="billing_zipcode" name="billing_zipcode" value="<?= h3_e($d['billing_zipcode']) ?>" inputmode="numeric" maxlength="10" data-digits<?= po3_inv_p($errors, 'billing_zipcode') ?>><?= po3_err_p($errors, 'billing_zipcode') ?></div>
              </div>
            </div>
          </section>
        </div>

        <aside class="sum" aria-label="Order summary">
          <h2>Order details <small><?= count($items) ?> <?= count($items) === 1 ? 'product' : 'products' ?></small></h2>
          <ul class="sum-rich">
            <?php foreach ($items as $it): $q = (int)($it['quantity'] ?? 0); $pr = (float)($it['price'] ?? 0); $pid = (string)($it['product_id'] ?? ''); ?>
              <li data-cat="<?= h3_e(po3_cat_p($it)) ?>">
                <span class="im"><img src="<?= h3_e(po3_img_p($it)) ?>" alt="" loading="lazy"></span>
                <span class="tx"><b><?= h3_e($it['product_name'] ?? 'Product') ?></b><?php if ($pid !== ''): ?><small>ID: <?= h3_e($pid) ?></small><?php endif; ?><small><?= $q ?> units × <?= $money($pr) ?></small></span>
                <span class="pr"><?= $money($pr * $q) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <dl class="sum-rows">
            <div><dt>Subtotal</dt><dd><?= $money($total) ?></dd></div>
            <div><dt>Total units</dt><dd><?= array_sum(array_map(fn($i) => (int)($i['quantity'] ?? 0), $items)) ?></dd></div>
            <div><dt>Delivery</dt><dd class="muted">Confirmed with your order</dd></div>
          </dl>
          <div class="sum-total"><span>Total</span><strong><?= $money($total) ?></strong></div>
          <button type="submit" class="btn btn-primary" id="goPay" form="shipForm">Continue to payment</button>
          <a class="back" href="<?= h3_link('cart') ?>" data-spa-link>Back to cart</a>
          <ul class="trust"><li>Your details are only used for this order</li><li>Packed fresh in the Araku Valley</li></ul>
        </aside>
      </form>
    </main>

    <script>
    if (window.initProcessOrderPage) { window.initProcessOrderPage(); }
    </script>
    <?php
    exit;
}

include __DIR__ . '/header3.php';
function po3_err($e, $k) { return isset($e[$k]) ? '<p class="ferr" id="' . $k . 'Err">' . h3_e($e[$k]) . '</p>' : ''; }
function po3_inv($e, $k) { return isset($e[$k]) ? ' aria-invalid="true" aria-describedby="' . $k . 'Err"' : ''; }
function po3_short($s, $n = 55) { return strlen($s) > $n ? substr($s, 0, $n - 1) . '…' : $s; }
function po3_cat($it) {                       // same image-prefix rule as the cart
    $k = strtoupper((string)($it['product_id'] ?? $it['image'] ?? ''));
    foreach (['ATCUP' => 'cup', 'ATMUG' => 'mug', 'ATBAG' => 'bag', 'ATTSH' => 'tshirt'] as $p => $c) if (strpos($k, $p) === 0) return $c;
    return 'bag';
}
function po3_img($it) {
    if (!empty($it['image_path'])) return $it['image_path'];
    $img = (string)($it['image'] ?? ''); if ($img === '') return 'img/default.png';
    return strpos($img, '/') !== false ? $img : 'img/shop/' . po3_cat($it) . '/' . $img;
}
?>
<style>
/* ============ process_order3.php ============ */

.co-hero{background:var(--cream);padding:26px 0 30px}
.crumbs{display:flex;gap:8px;margin:0 0 12px;font-size:13.5px;color:var(--muted)}
.crumbs a{color:var(--muted);text-decoration:none}
.crumbs a:hover{color:var(--brown)}
.crumbs span[aria-current]{color:var(--brown);font-weight:600}
.co-hero .row{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap}
.co-hero h1{font-size:clamp(1.9rem,3.4vw,2.7rem)}
.steps{display:flex;align-items:center;gap:10px;list-style:none;margin:0;padding:0;font-size:14px;font-weight:600;color:var(--muted)}
.steps li{display:flex;align-items:center;gap:8px}
.steps li b{width:26px;height:26px;border-radius:50%;border:1.5px solid var(--line);background:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:12.5px}
.steps li.done{color:var(--green)}
.steps li.done b{background:var(--green);border-color:var(--green);color:#fff}
.steps li.on{color:var(--brown)}
.steps li.on b{background:var(--brown);border-color:var(--brown);color:#fff}
.steps .bar{width:28px;height:2px;background:var(--line);border-radius:2px}
.steps .bar.done{background:var(--green)}
.steps a{color:inherit;text-decoration:none}
.co{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:30px;align-items:start;padding-top:30px;padding-bottom:88px}
.panel{background:#fff;border:1px solid var(--line);border-radius:22px;padding:24px;margin-bottom:18px}
.panel h2{font-size:1.3rem;display:flex;align-items:center;gap:10px;margin-bottom:16px}
.panel h2 .n{width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--cream);font-family:var(--sans);font-size:13px;font-weight:700;color:var(--brown)}
.two{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.three{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:12px}
.field{margin-bottom:14px}
.field label{display:block;margin:0 0 6px;font-weight:600;font-size:14px;color:var(--brown-deep)}
.field label small{font-weight:500;color:var(--muted)}
.field input,.field select{width:100%;height:50px;border:1.5px solid var(--line);border-radius:14px;padding:0 14px;background:var(--cream);font:inherit;font-size:15.5px;color:var(--ink)}
.field input:focus,.field select:focus{outline:none;border-color:var(--brown-soft);background:#fff}
.field input[aria-invalid="true"],.field select[aria-invalid="true"]{border-color:#D98A8A;background:#FFF6F6}
.ferr{margin:6px 0 0;font-size:13px;font-weight:600;color:#9B2626}
.hint{margin:5px 0 0;font-size:12.5px;color:var(--muted)}
.flash{margin:0 0 16px;padding:12px 14px;border-radius:14px;font-weight:600;font-size:14.5px}
.flash.bad{background:#FBE4E4;color:#9B2626}
.flash.ok{background:var(--green-soft);color:var(--green)}
.phone{display:grid;grid-template-columns:170px 1fr;gap:8px}
.chips{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px}
.chips button{max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border:1.5px solid var(--line);background:#fff;border-radius:999px;padding:8px 14px;font-weight:600;font-size:13.5px;color:var(--ink)}
.chips button:hover{border-color:var(--brown-soft)}
.chips button[aria-pressed="true"]{background:var(--brown);border-color:var(--brown);color:#fff}
.chips .new{border-style:dashed;color:var(--muted)}
.chips-label{margin:0 0 8px;font-size:13.5px;font-weight:600;color:var(--muted)}
.check{display:flex;gap:10px;align-items:center;padding:12px 14px;border-radius:14px;background:var(--cream);font-weight:600;font-size:14.5px;cursor:pointer}
.check input{width:18px;height:18px;accent-color:var(--brown)}
.sum{position:sticky;top:96px;background:#fff;border:1px solid var(--line);border-radius:24px;padding:22px}
.sum h2{font-size:1.35rem;margin-bottom:12px}
.sum-items{list-style:none;margin:0;padding:0;display:grid;gap:10px;max-height:320px;overflow:auto}
.sum-items li{display:flex;justify-content:space-between;gap:12px;font-size:14.5px}
.sum-items li span{color:var(--muted);font-size:13px;display:block}
.sum-items li b{white-space:nowrap}
.sum-total{display:flex;justify-content:space-between;align-items:baseline;margin-top:14px;padding-top:14px;border-top:1px dashed var(--line)}
.sum-total strong{font-size:1.5rem;color:var(--brown-deep)}
.sum .btn-primary{width:100%;padding:16px;font-size:16px;margin-top:16px}
.sum .back{display:block;margin-top:12px;text-align:center;font-weight:600;font-size:14px;color:var(--brown)}
.trust{list-style:none;margin:14px 0 0;padding:0;display:grid;gap:6px;font-size:13px;color:var(--muted)}
.err-sum{margin:0 0 18px;padding:14px 16px;border-radius:16px;background:#FBE4E4;color:#9B2626;font-size:14.5px;outline:none}
.err-sum ul{margin:6px 0 0;padding-left:20px}
.err-sum a{color:inherit;font-weight:600}
.sum h2 small{font-family:var(--sans);font-size:13px;font-weight:600;color:var(--muted);margin-left:6px}
.sum-rich{list-style:none;margin:0;padding:0;display:grid;gap:12px;max-height:360px;overflow:auto}
.sum-rich li{display:grid;grid-template-columns:56px minmax(0,1fr) auto;gap:12px;align-items:center}
.sum-rich .im{width:56px;height:56px;border-radius:14px;overflow:hidden;background:#F3E9DD}
.sum-rich li[data-cat="mug"] .im{background:#E2ECE5}.sum-rich li[data-cat="cup"] .im{background:#F6EEE3}.sum-rich li[data-cat="tshirt"] .im{background:#EDE5DA}
.sum-rich img{width:100%;height:100%;object-fit:contain;padding:8%;mix-blend-mode:multiply}
.sum-rich .tx b{display:block;font-size:14px;line-height:1.3;color:var(--brown-deep)}
.sum-rich .tx small{display:block;font-size:12px;color:var(--muted)}
.sum-rich .pr{font-weight:700;font-size:14.5px;white-space:nowrap}
.sum-rows{margin:16px 0 0;padding-top:14px;border-top:1px solid var(--line);display:grid;gap:8px}
.sum-rows div{display:flex;justify-content:space-between;font-size:14px}
.sum-rows dt{color:var(--muted)}.sum-rows dd{margin:0;font-weight:600}.sum-rows dd.muted{font-weight:500;color:var(--muted)}
.trust li::before{content:"✓ ";color:var(--green);font-weight:700}
@media (max-width:1000px){ .co{grid-template-columns:1fr} .sum{position:static} }
@media (max-width:620px){ .two,.three,.phone{grid-template-columns:1fr} .panel{padding:18px} .steps .lbl{display:none} }

</style>
<datalist id="states"><?php foreach ($STATES as $s): ?><option value="<?= h3_e($s) ?>"><?php endforeach; ?></datalist>

<main id="spa-main-content">
  <section class="co-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>" data-spa-link>Home</a><span aria-hidden="true">/</span><a href="<?= h3_link('cart') ?>" data-spa-link>Cart</a><span aria-hidden="true">/</span><span aria-current="page">Delivery details</span></nav>
      <div class="row"><h1>Where should we deliver?</h1><ol class="steps" aria-label="Checkout steps"><li class="done"><a href="<?= h3_link('cart') ?>" data-spa-link><b>✓</b><span class="lbl">Cart</span></a></li><li class="bar done" aria-hidden="true"></li><li class="on"><b>2</b><span class="lbl">Details</span></li><li class="bar" aria-hidden="true"></li><li class=""><b>3</b><span class="lbl">Payment</span></li></ol></div>
    </div>
  </section>

  <?php if (function_exists('render_currency_notice')): ?><div class="wrap" style="padding-top:20px"><?= render_currency_notice($displayCurrency) ?></div><?php endif; ?>
  <form class="wrap co" method="POST" action="process_order3.php" id="shipForm" novalidate>
    <input type="hidden" name="csrf" value="<?= h3_e($CSRF) ?>">
    <input type="hidden" name="submit_shipping" value="1">
    <input type="hidden" id="shippingFromSaved" name="shipping_from_saved" value="0">
    <input type="hidden" id="billingFromSaved" name="billing_from_saved" value="0">
    <div>
      <?php if ($formError): ?><p class="flash bad" role="alert"><?= h3_e($formError) ?></p><?php endif; ?>
      <div class="err-sum" id="errSum" role="alert" tabindex="-1" <?= $errors ? '' : 'hidden' ?>>
        <strong>Please fix these to continue:</strong>
        <ul id="errList"><?php foreach ($errors as $k => $m): ?><li><a href="#<?= h3_e($k) ?>"><?= h3_e($m) ?></a></li><?php endforeach; ?></ul>
      </div>

      <section class="panel">
        <h2><span class="n">1</span>Your details</h2>
        <div class="two">
          <div class="field"><label for="name">Full name</label><input id="name" name="name" value="<?= h3_e($d['name']) ?>" autocomplete="name" required data-letters<?= po3_inv($errors, 'name') ?>><?= po3_err($errors, 'name') ?></div>
          <div class="field"><label for="email">Email <small>(for your order confirmation)</small></label><input type="email" id="email" name="email" value="<?= h3_e($d['email']) ?>" autocomplete="email" required<?= po3_inv($errors, 'email') ?>><?= po3_err($errors, 'email') ?></div>
        </div>
        <div class="field"><label for="phone">Phone <small>(optional, for the delivery partner)</small></label>
          <div class="phone"><select name="country_code" aria-label="Country code"><?php foreach ($COUNTRY_CODES as $c => $l): ?><option value="<?= h3_e($c) ?>" <?= $d['country_code'] === $c ? 'selected' : '' ?>><?= h3_e($l) ?></option><?php endforeach; ?></select>
            <input type="tel" id="phone" name="phone" value="<?= h3_e($d['phone']) ?>" inputmode="numeric" maxlength="10" placeholder="10-digit number" autocomplete="tel-national" data-digits<?= po3_inv($errors, 'phone') ?>></div><?= po3_err($errors, 'phone') ?></div>
      </section>

      <section class="panel">
        <h2><span class="n">2</span>Delivery address</h2>
        <?php if ($saved): ?>
          <p class="chips-label">Use an address from a past order</p>
          <div class="chips" data-for="shipping">
            <?php foreach ($saved as $a): ?><button type="button" data-addr="<?= h3_e($a) ?>" aria-pressed="false" title="<?= h3_e($a) ?>"><?= h3_e(po3_short($a)) ?></button><?php endforeach; ?>
            <button type="button" class="new" data-addr="">+ New address</button>
          </div>
        <?php endif; ?>
        <div class="field"><label for="address">Street address</label><input id="address" name="address" value="<?= h3_e($d['address']) ?>" placeholder="House no., street, area" autocomplete="street-address" required<?= po3_inv($errors, 'address') ?>><?= po3_err($errors, 'address') ?></div>
        <div class="three">
          <div class="field"><label for="city">City</label><input id="city" name="city" value="<?= h3_e($d['city']) ?>" autocomplete="address-level2" required data-letters<?= po3_inv($errors, 'city') ?>><?= po3_err($errors, 'city') ?></div>
          <div class="field"><label for="state">State</label><input id="state" name="state" list="states" value="<?= h3_e($d['state']) ?>" autocomplete="address-level1" data-letters<?= po3_inv($errors, 'state') ?>><?= po3_err($errors, 'state') ?></div>
          <div class="field"><label for="zipcode">PIN / zip code</label><input id="zipcode" name="zipcode" value="<?= h3_e($d['zipcode']) ?>" inputmode="numeric" maxlength="10" autocomplete="postal-code" required data-digits<?= po3_inv($errors, 'zipcode') ?>><?= po3_err($errors, 'zipcode') ?></div>
        </div>
      </section>

      <section class="panel">
        <h2><span class="n">3</span>Billing address</h2>
        <label class="check"><input type="checkbox" id="billingSame" name="billing_same" value="1" <?= $d['billing_same'] === '1' ? 'checked' : '' ?>>Same as delivery address</label>
        <div id="billingBox" <?= $d['billing_same'] === '1' ? 'hidden' : '' ?> style="margin-top:16px">
          <?php if ($saved): ?>
            <div class="chips" data-for="billing"><?php foreach ($saved as $a): ?><button type="button" data-addr="<?= h3_e($a) ?>" aria-pressed="false" title="<?= h3_e($a) ?>"><?= h3_e(po3_short($a)) ?></button><?php endforeach; ?></div>
          <?php endif; ?>
          <div class="field"><label for="billing_address">Street address</label><input id="billing_address" name="billing_address" value="<?= h3_e($d['billing_address']) ?>"<?= po3_inv($errors, 'billing_address') ?>><?= po3_err($errors, 'billing_address') ?></div>
          <div class="three">
            <div class="field"><label for="billing_city">City</label><input id="billing_city" name="billing_city" value="<?= h3_e($d['billing_city']) ?>" data-letters<?= po3_inv($errors, 'billing_city') ?>><?= po3_err($errors, 'billing_city') ?></div>
            <div class="field"><label for="billing_state">State</label><input id="billing_state" name="billing_state" list="states" value="<?= h3_e($d['billing_state']) ?>" data-letters<?= po3_inv($errors, 'billing_state') ?>><?= po3_err($errors, 'billing_state') ?></div>
            <div class="field"><label for="billing_zipcode">PIN / zip code</label><input id="billing_zipcode" name="billing_zipcode" value="<?= h3_e($d['billing_zipcode']) ?>" inputmode="numeric" maxlength="10" data-digits<?= po3_inv($errors, 'billing_zipcode') ?>><?= po3_err($errors, 'billing_zipcode') ?></div>
          </div>
        </div>
      </section>
    </div>

    <aside class="sum" aria-label="Order summary">
      <h2>Order details <small><?= count($items) ?> <?= count($items) === 1 ? 'product' : 'products' ?></small></h2>
      <ul class="sum-rich">
        <?php foreach ($items as $it): $q = (int)($it['quantity'] ?? 0); $pr = (float)($it['price'] ?? 0); $pid = (string)($it['product_id'] ?? ''); ?>
          <li data-cat="<?= h3_e(po3_cat($it)) ?>">
            <span class="im"><img src="<?= h3_e(po3_img($it)) ?>" alt="" loading="lazy"></span>
            <span class="tx"><b><?= h3_e($it['product_name'] ?? 'Product') ?></b><?php if ($pid !== ''): ?><small>ID: <?= h3_e($pid) ?></small><?php endif; ?><small><?= $q ?> units × <?= $money($pr) ?></small></span>
            <span class="pr"><?= $money($pr * $q) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <dl class="sum-rows">
        <div><dt>Subtotal</dt><dd><?= $money($total) ?></dd></div>
        <div><dt>Total units</dt><dd><?= array_sum(array_map(fn($i) => (int)($i['quantity'] ?? 0), $items)) ?></dd></div>
        <div><dt>Delivery</dt><dd class="muted">Confirmed with your order</dd></div>
      </dl>
      <div class="sum-total"><span>Total</span><strong><?= $money($total) ?></strong></div>
      <button type="submit" class="btn btn-primary" id="goPay" form="shipForm">Continue to payment</button>
      <a class="back" href="<?= h3_link('cart') ?>" data-spa-link>Back to cart</a>
      <ul class="trust"><li>Your details are only used for this order</li><li>Packed fresh in the Araku Valley</li></ul>
    </aside>
  </form>
</main>

<script>
window.initProcessOrderPage = function() {
  document.querySelectorAll('[data-letters]').forEach(i => i.addEventListener('input', () => { i.value = i.value.replace(/[^a-zA-Z\s.'\-]/g, ''); }));
  document.querySelectorAll('[data-digits]').forEach(i => i.addEventListener('input', () => { i.value = i.value.replace(/\D/g, '').slice(0, +i.maxLength || 10); }));
  const same = document.getElementById('billingSame'), box = document.getElementById('billingBox');
  if (same && box) {
    same.addEventListener('change', () => { box.hidden = same.checked; });
  }
  // saved address -> street, city, zip (state was never saved separately, so it's left to fill in)
  function parse(full) {
    const parts = full.split(',').map(p => p.trim()).filter(Boolean); let zip = '', city = '';
    if (parts.length && /^\d{4,10}$/.test(parts[parts.length - 1])) zip = parts.pop();
    if (parts.length > 1) city = parts.pop();
    return { street: parts.join(', ') || full, city: city.replace(/[^a-zA-Z\s]/g, ''), zip };
  }
  document.querySelectorAll('.chips').forEach(group => group.addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    const pre = group.dataset.for === 'billing' ? 'billing_' : '', flag = document.getElementById(group.dataset.for === 'billing' ? 'billingFromSaved' : 'shippingFromSaved');
    group.querySelectorAll('button').forEach(x => x.setAttribute('aria-pressed', x === b && b.dataset.addr !== ''));
    const set = (id, v) => { const el = document.getElementById(pre + id); if (el) el.value = v; };
    if (!b.dataset.addr) { ['address', 'city', 'state', 'zipcode'].forEach(k => set(k, '')); if (flag) flag.value = '0'; const addrEl = document.getElementById(pre + 'address'); if (addrEl) addrEl.focus(); return; }
    const p = parse(b.dataset.addr); set('address', p.street); set('city', p.city); set('zipcode', p.zip); if (flag) flag.value = '1';
    const next = ['city', 'state', 'zipcode'].map(k => document.getElementById(pre + k)).find(i => i && !i.value); if (next) next.focus();
  }));
  const LET = /^[a-zA-Z\s.'\-]+$ /;
  const RULES = {
    name:    v => !v ? 'Please enter your full name.' : (!/^[a-zA-Z\s.'\-]+$/.test(v) ? 'Use letters only (spaces, dots and hyphens are fine).' : ''),
    email:   v => !v ? 'Please enter your email.' : (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? 'Please enter a valid email address.' : ''),
    phone:   v => v && v.replace(/\D/g, '').length !== 10 ? 'Phone number must be exactly 10 digits.' : '',
    address: v => !v ? 'Please enter your street address.' : '',
    city:    v => !v ? 'Please enter your city.' : (!/^[a-zA-Z\s.'\-]+$/.test(v) ? 'Use letters only.' : ''),
    state:   v => (!v && document.getElementById('shippingFromSaved').value !== '1') ? 'Please choose your state.' : '',
    zipcode: v => !/^\d{4,10}$/.test(v) ? 'Please enter a PIN / zip code (4 to 10 digits).' : '',
  };
  const BRULES = {
    billing_address: v => !v ? 'Please enter the billing street address.' : '',
    billing_city:    v => !v || !/^[a-zA-Z\s.'\-]+$/.test(v) ? 'Please enter a billing city.' : '',
    billing_state:   v => (!v && document.getElementById('billingFromSaved').value !== '1') ? 'Please choose the billing state.' : '',
    billing_zipcode: v => !/^\d{4,10}$/.test(v) ? 'Please enter the billing PIN / zip code.' : '',
  };
  function mark(id, msg) {
    const el = document.getElementById(id); if (!el) return;
    const wrap = el.closest('.field'); let p = document.getElementById(id + 'Err');
    if (msg) {
      el.setAttribute('aria-invalid', 'true'); el.setAttribute('aria-describedby', id + 'Err');
      if (!p) { p = document.createElement('p'); p.className = 'ferr'; p.id = id + 'Err'; if (wrap) wrap.appendChild(p); }
      p.textContent = msg;
    } else { el.removeAttribute('aria-invalid'); if (p) p.remove(); }
  }
  function check() {
    const list = [];
    const run = rules => Object.entries(rules).forEach(([id, fn]) => { const el = document.getElementById(id); if (!el) return; const m = fn(el.value.trim()); mark(id, m); if (m) list.push([id, m]); });
    run(RULES); 
    const billingSameEl = document.getElementById('billingSame');
    if (billingSameEl && !billingSameEl.checked) run(BRULES); else Object.keys(BRULES).forEach(id => mark(id, ''));
    return list;
  }
  Object.keys(Object.assign({}, RULES, BRULES)).forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('blur', e => {
        const fn = RULES[id] || BRULES[id]; if (e.target.value.trim() || e.target.getAttribute('aria-invalid')) mark(id, fn(e.target.value.trim()));
      });
    }
  });
  const shipForm = document.getElementById('shipForm');
  if (shipForm) {
    shipForm.addEventListener('submit', async e => {
      const list = check(), sum = document.getElementById('errSum');
      if (list.length) {
        e.preventDefault();
        const errListEl = document.getElementById('errList');
        if (errListEl) errListEl.innerHTML = list.map(([id, m]) => `<li><a href="#${id}">${m}</a></li>`).join('');
        if (sum) { sum.hidden = false; sum.scrollIntoView({ behavior: 'smooth', block: 'center' }); sum.focus({ preventScroll: true }); }
        return;
      }
      if (sum) sum.hidden = true;
      const b = document.getElementById('goPay'); 
      if (b) { b.disabled = true; b.textContent = 'Saving…'; }

      // Handle SPA/AJAX submission if SPA router is active
      if (window.spaNavigate) {
        e.preventDefault();
        const formData = new FormData(shipForm);
        formData.append('ajax', '1');
        try {
          const res = await fetch('process_order3.php', { method: 'POST', body: formData, credentials: 'same-origin' });
          const data = await res.json();
          if (data.status === 'redirect') {
            window.spaNavigate(data.url);
          } else if (data.status === 'error') {
            if (b) { b.disabled = false; b.textContent = 'Continue to payment'; }
            // Handle server-side validation errors
            if (sum) {
              sum.hidden = false;
              const errListEl = document.getElementById('errList');
              if (errListEl && data.errors) {
                errListEl.innerHTML = Object.entries(data.errors).map(([id, m]) => `<li><a href="#${id}">${m}</a></li>`).join('');
              }
              sum.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
          }
        } catch (err) {
          shipForm.submit();
        }
      }
    });
  }
  const errSumEl = document.getElementById('errSum');
  if (errSumEl) {
    errSumEl.addEventListener('click', e => { const a = e.target.closest('a'); if (!a) return; e.preventDefault(); const t = document.querySelector(a.getAttribute('href')); if (t) { t.focus(); t.scrollIntoView({ behavior: 'smooth', block: 'center' }); } });
  }
  const bad = document.querySelector('[aria-invalid="true"]'); if (bad) bad.focus();
};
window.initProcessOrderPage();
</script>

<?php include __DIR__ . '/footer3.php'; ?>