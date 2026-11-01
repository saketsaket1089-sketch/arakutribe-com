<?php
/* =====================================================================
   ARAKU TRIBE - dummy_payment3.php  (Checkout step 3: payment)
   Same logic as dummy_payment.php (SIMULATED gateway: no real charge):
     - choose UPI / card / Amazon Pay / net banking / cash on delivery,
       format-checked only, card details are never stored
     - inserts orders + order_items, clears the site cart (not for agent
       orders), emails the confirmation through agent_mailer_config.php
     - tells the chat agent the order is placed (localStorage)
   Fixes:
     - each order can only be placed once (one-time token), so a refresh
       or double-click can't create a duplicate order
     - order_items now records the real product ID (it was always 0)
     - database errors go to the log, not onto the screen
     - after placing, the page reloads to a confirmation (no resubmit)
   Uses header3.php + footer3.php.
   ===================================================================== */
session_start();
include 'db1.php';
if (!isset($conn) || !($conn instanceof mysqli)) { $conn = new mysqli($host, $user, $pass, $db); }
require_once 'currency_helper.php';
$displayCurrency = resolve_display_currency($conn);
$currencyInfo    = $CURRENCY_INFO[$displayCurrency];
require_once 'agent_mailer_config.php';      // get_agent_mailer(): the SMTP setup that works on your host

$PAYMENT = [
    'upi'        => ['label' => 'UPI',                 'sub' => 'Google Pay, PhonePe, Paytm…'],
    'card'       => ['label' => 'Debit / credit card', 'sub' => 'Visa, Mastercard, RuPay'],
    'netbanking' => ['label' => 'Net banking',         'sub' => 'All major banks'],
    'amazonpay'  => ['label' => 'Amazon Pay',          'sub' => 'Wallet linked to your mobile'],
    'cod'        => ['label' => 'Cash on delivery',    'sub' => 'Pay when it arrives'],
];
$BANKS = ['sbi' => 'State Bank of India', 'hdfc' => 'HDFC Bank', 'icici' => 'ICICI Bank', 'axis' => 'Axis Bank', 'pnb' => 'Punjab National Bank', 'other' => 'Other bank'];
$money = fn($usd) => htmlspecialchars($currencyInfo['symbol']) . number_format($usd * $currencyInfo['rate'], 2);

// Dual Mode / SPA detection: check for AJAX header or parameter
$isAjax       = (($_POST['ajax'] ?? '') === '1') || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_GET['ajax']) && $_GET['ajax'] === '1');

/* ---------- order placed: show the confirmation (also on refresh) ---------- */
if (isset($_GET['placed']) && !empty($_SESSION['order_done']) && (int)$_GET['placed'] === (int)$_SESSION['order_done']['id']) {
    $done = $_SESSION['order_done'];
    $PAGE_TITLE = 'Order placed - Araku Tribe';
    
    $isPartial = isset($_GET['partial']) && $_GET['partial'] === '1';
    if ($isPartial) {
        ?>
        <main id="spa-main-content">
          <div class="wrap ty">
            <div class="big"><svg viewBox="0 0 24 24"><path d="m5 12 5 5 9-10"/></svg></div>
            <h1>Thank you<?= $done['name'] !== '' ? ', ' . h3_e(trim(explode(' ', $done['name'])[0])) : '' ?>! Your order is placed.</h1>
            <p class="lede">We'll start packing it fresh in the Araku Valley. You can follow it any time from My orders.</p>
            <div class="meta"><div>Order number<b>#<?= (int)$done['id'] ?></b></div><div>Payment<b><?= h3_e($done['method']) ?></b></div><div>Status<b><?= h3_e($done['pay_status'] === 'Pending' ? 'Pay on delivery' : 'Paid') ?></b></div></div>
            <?php if ($done['email_sent']): ?><p class="mail ok">A confirmation has been emailed to <?= h3_e($done['email']) ?>.</p>
            <?php else: ?><p class="mail warn">Your order is placed, but we couldn't send the confirmation email. Your order number is #<?= (int)$done['id'] ?>.</p><?php endif; ?>
            <div class="items"><ul>
              <?php foreach ($done['items'] as $it): ?><li><span><?= h3_e($it['name']) ?> × <?= (int)$it['qty'] ?></span><b><?= h3_e($it['sub']) ?></b></li><?php endforeach; ?>
              <li class="tot"><span>Total</span><span><?= h3_e($done['total']) ?></span></li></ul></div>
            <div class="acts">
              <?php if (!empty($_SESSION['user_id'])): ?><a class="btn btn-primary" href="<?= h3_link('orders') ?>" data-spa-link>Track my order</a><?php endif; ?>
              <a class="btn btn-outline" href="<?= h3_link('shop') ?>" data-spa-link>Continue shopping</a>
            </div>
          </div>
        </main>
        <script>
        try { localStorage.setItem('arakubot_pending_order_message', <?= json_encode(json_encode($done['bot'])) ?>); } catch (e) {}
        </script>
        <?php
        exit;
    }

    include __DIR__ . '/header3.php';
    ?>
    <style>
    .ty{max-width:680px;margin:0 auto;padding:48px 0 88px;text-align:center}
    .ty .big{width:84px;height:84px;margin:0 auto 18px;border-radius:26px;display:flex;align-items:center;justify-content:center;background:var(--green-soft);color:var(--green);animation:pop .5s cubic-bezier(.2,.7,.2,1)}
    .ty .big svg{width:40px;height:40px;stroke:currentColor;fill:none;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round}
    @keyframes pop{from{transform:scale(.6);opacity:0}to{transform:none;opacity:1}}
    .ty h1{font-size:clamp(2rem,3.6vw,2.8rem)}
    .ty .lede{margin:8px auto 0;max-width:52ch}
    .ty .meta{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:26px 0 0;text-align:left}
    .ty .meta div{padding:12px 14px;border-radius:14px;background:var(--cream);font-size:12.5px;color:var(--muted)}
    .ty .meta b{display:block;margin-top:2px;font-size:15px;color:var(--brown-deep)}
    .ty .mail{margin:16px 0 0;padding:12px 14px;border-radius:14px;font-size:14px;font-weight:600}
    .ty .mail.ok{background:var(--green-soft);color:var(--green)}
    .ty .mail.warn{background:#FFF1D6;color:#6A4A12}
    .ty .items{margin:18px 0 0;padding:16px 18px;border:1px solid var(--line);border-radius:18px;text-align:left}
    .ty .items li{display:flex;justify-content:space-between;gap:12px;padding:6px 0;font-size:14.5px}
    .ty .items ul{list-style:none;margin:0;padding:0}
    .ty .items .tot{border-top:1px dashed var(--line);margin-top:6px;padding-top:12px;font-weight:700;font-size:16px}
    .ty .acts{display:flex;justify-content:center;gap:12px;flex-wrap:wrap;margin-top:26px}
    @media (max-width:560px){ .ty .meta{grid-template-columns:1fr} }
    @media (prefers-reduced-motion:reduce){ .ty .big{animation:none} }
    </style>
    <main id="spa-main-content"><div class="wrap ty">
      <div class="big"><svg viewBox="0 0 24 24"><path d="m5 12 5 5 9-10"/></svg></div>
      <h1>Thank you<?= $done['name'] !== '' ? ', ' . h3_e(trim(explode(' ', $done['name'])[0])) : '' ?>! Your order is placed.</h1>
      <p class="lede">We'll start packing it fresh in the Araku Valley. You can follow it any time from My orders.</p>
      <div class="meta"><div>Order number<b>#<?= (int)$done['id'] ?></b></div><div>Payment<b><?= h3_e($done['method']) ?></b></div><div>Status<b><?= h3_e($done['pay_status'] === 'Pending' ? 'Pay on delivery' : 'Paid') ?></b></div></div>
      <?php if ($done['email_sent']): ?><p class="mail ok">A confirmation has been emailed to <?= h3_e($done['email']) ?>.</p>
      <?php else: ?><p class="mail warn">Your order is placed, but we couldn't send the confirmation email. Your order number is #<?= (int)$done['id'] ?>.</p><?php endif; ?>
      <div class="items"><ul>
        <?php foreach ($done['items'] as $it): ?><li><span><?= h3_e($it['name']) ?> × <?= (int)$it['qty'] ?></span><b><?= h3_e($it['sub']) ?></b></li><?php endforeach; ?>
        <li class="tot"><span>Total</span><span><?= h3_e($done['total']) ?></span></li></ul></div>
      <div class="acts">
        <?php if (!empty($_SESSION['user_id'])): ?><a class="btn btn-primary" href="<?= h3_link('orders') ?>" data-spa-link>Track my order</a><?php endif; ?>
        <a class="btn btn-outline" href="<?= h3_link('shop') ?>" data-spa-link>Continue shopping</a>
      </div>
    </div></main>
    <script>
    try { localStorage.setItem('arakubot_pending_order_message', <?= json_encode(json_encode($done['bot'])) ?>); } catch (e) {}
    </script>
    <?php include __DIR__ . '/footer3.php'; exit;
}

/* ---------- guard: checkout must have started ---------- */
if (!isset($_SESSION['shipping_details']) || !isset($_SESSION['order_data']['total'])) {
    $PAGE_TITLE = 'Checkout - Araku Tribe';
    $isPartial = isset($_GET['partial']) && $_GET['partial'] === '1';
    if ($isPartial) {
        ?>
        <main id="spa-main-content">
          <div class="wrap" style="max-width:560px;text-align:center;padding:64px 0 96px">
            <h1 style="font-size:2rem">Your checkout has expired</h1>
            <p class="lede" style="margin:10px auto 24px">This can happen after a while or once an order is placed. Your cart is still saved.</p>
            <a class="btn btn-primary" href="<?= h3_link('cart') ?>" data-spa-link>Back to my cart</a>
          </div>
        </main>
        <?php
        exit;
    }
    include __DIR__ . '/header3.php';
    echo '<main id="spa-main-content"><div class="wrap" style="max-width:560px;text-align:center;padding:64px 0 96px"><h1 style="font-size:2rem">Your checkout has expired</h1>'
       . '<p class="lede" style="margin:10px auto 24px">This can happen after a while or once an order is placed. Your cart is still saved.</p>'
       . '<a class="btn btn-primary" href="' . h3_link('cart') . '" data-spa-link>Back to my cart</a></div></main>';
    include __DIR__ . '/footer3.php'; exit;
}

$sd = $_SESSION['shipping_details'];
$total_amount = (float)$_SESSION['order_data']['total'];
$items        = $_SESSION['order_data']['items'] ?? [];
$user_email   = $sd['email'] ?? ($_SESSION['user_email'] ?? '');
$user_name    = $sd['name'] ?? ($_SESSION['user_name'] ?? 'Customer');
$fromAgent    = ($_SESSION['order_source'] ?? '') === 'agent';
$join = fn(...$p) => implode(', ', array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 'N/A'));
$full_address = $join($sd['address'] ?? '', $sd['city'] ?? '', $sd['state'] ?? '', $sd['zipcode'] ?? '');
$full_billing = $join($sd['billing_address'] ?? ($sd['address'] ?? ''), $sd['billing_city'] ?? ($sd['city'] ?? ''), $sd['billing_state'] ?? ($sd['state'] ?? ''), $sd['billing_zipcode'] ?? ($sd['zipcode'] ?? ''));

$error = ''; $method = 'upi';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $method = array_key_exists($_POST['payment_method'] ?? '', $PAYMENT) ? $_POST['payment_method'] : 'cod';
    /* one-time token: a refresh / double-click can't place the same order twice */
    $token = (string)($_POST['pay_token'] ?? '');
    if ($token === '' || empty($_SESSION['pay_token']) || !hash_equals($_SESSION['pay_token'], $token)) {
        $error = 'This page was already submitted. Please check My orders before trying again.';
    } else {
        $detail = ''; $f = fn($k) => trim((string)($_POST[$k] ?? ''));
        switch ($method) {
            case 'card':
                $num = preg_replace('/\s+/', '', $f('card_number'));
                if (!preg_match('/^[0-9]{13,19}$/', $num)) $error = 'Please enter a valid card number.';
                elseif (!preg_match('/^(0[1-9]|1[0-2])\/[0-9]{2}$/', $f('card_expiry'))) $error = 'Please enter the expiry date as MM/YY.';
                elseif (!preg_match('/^[0-9]{3,4}$/', $f('card_cvv'))) $error = 'Please enter a valid CVV.';
                elseif ($f('card_name') === '') $error = 'Please enter the name on the card.';
                else $detail = 'Card ending ' . substr($num, -4);
                break;
            case 'upi':
                if (!preg_match('/^[\w.\-]{2,}@[a-zA-Z]{2,}$/', $f('upi_id'))) $error = 'Please enter a valid UPI ID, like name@bank.'; else $detail = 'UPI: ' . $f('upi_id');
                break;
            case 'netbanking':
                if (!isset($BANKS[$f('netbanking_bank')])) $error = 'Please choose your bank.'; else $detail = $BANKS[$f('netbanking_bank')];
                break;
            case 'amazonpay':
                if (!preg_match('/^[0-9]{10}$/', $f('amazonpay_mobile'))) $error = 'Please enter the 10-digit mobile number linked to Amazon Pay.'; else $detail = 'Amazon Pay ' . substr($f('amazonpay_mobile'), 0, 2) . '••••••' . substr($f('amazonpay_mobile'), -2);
                break;
        }
        if (!$error) {
            unset($_SESSION['pay_token']);                 // used up
            $payment_status = $method === 'cod' ? 'Pending' : 'Paid';
            $order_status   = 'Placed';
            $user_id        = (int)($_SESSION['user_id'] ?? 0);
            $conn->begin_transaction();
            try {
                $ins = $conn->prepare("INSERT INTO orders (user_id, total_amount, shipping_address, billing_address, order_status, payment_status, order_date, payment_method, currency) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                $ins->bind_param("idssssss", $user_id, $total_amount, $full_address, $full_billing, $order_status, $payment_status, $method, $displayCurrency);
                $ins->execute();
                $order_id = (int)$conn->insert_id;
                $ins->close();
                /* order_items.product_id: store the real product ID (text like ATBAG003), unless the column is numeric */
                $pidIsInt = false;
                try { $col = $conn->query("SHOW COLUMNS FROM order_items LIKE 'product_id'"); if ($col && ($c = $col->fetch_assoc())) $pidIsInt = (bool)preg_match('/int/i', $c['Type'] ?? ''); } catch (\Throwable $e) {}
                $it = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
                foreach ($items as $item) {
                    $pid = (string)($item['product_id'] ?? ''); $pname = (string)($item['product_name'] ?? 'Product');
                    $qty = (int)($item['quantity'] ?? 1); $price = (float)($item['price'] ?? 0);
                    if ($pidIsInt) { $pidv = (int)$pid; $it->bind_param("iisid", $order_id, $pidv, $pname, $qty, $price); }
                    else           { $it->bind_param("issid", $order_id, $pid, $pname, $qty, $price); }
                    $it->execute();
                }
                $it->close();
                if (!$fromAgent && $user_id) { $cl = $conn->prepare("DELETE FROM cart WHERE user_id = ?"); $cl->bind_param("i", $user_id); $cl->execute(); $cl->close(); }
                $conn->commit();
            } catch (\Throwable $e) {
                $conn->rollback();
                error_log('Place order failed: ' . $e->getMessage());
                $order_id = 0;
                $error = "We couldn't place your order just now. Nothing has been charged. Please try again in a moment.";
                $_SESSION['pay_token'] = bin2hex(random_bytes(16));
            }

            if ($order_id) {
                unset($_SESSION['cart']);
                /* confirmation email (same mailer as before, restyled) */
                $rows = ''; $summary = [];
                foreach ($items as $item) {
                    $sub = $money(($item['price'] ?? 0) * ($item['quantity'] ?? 1));
                    $summary[] = ['name' => (string)($item['product_name'] ?? 'Product'), 'qty' => (int)($item['quantity'] ?? 1), 'sub' => html_entity_decode($sub)];
                    $rows .= '<tr><td style="padding:10px 0;border-bottom:1px solid #EDE2D6;font-size:14px">' . htmlspecialchars($item['product_name'] ?? '') . '<br><span style="font-size:12px;color:#77716C">' . (int)($item['quantity'] ?? 1) . ' units × ' . $money($item['price'] ?? 0) . '</span></td>'
                          .  '<td align="right" style="padding:10px 0;border-bottom:1px solid #EDE2D6;font-size:14px">' . $sub . '</td></tr>';
                }
                $label = $PAYMENT[$method]['label'];
                $note  = $method === 'cod' ? 'Please keep the amount ready. You\'ll pay when your order arrives.' : 'We\'ve received your payment.';
                $html = '<!doctype html><html><body style="margin:0;background:#F7EADB"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7EADB;padding:28px 12px"><tr><td align="center">'
                  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#fff;border-radius:20px;overflow:hidden">'
                  . '<tr><td style="background:#3B2317;padding:20px 26px"><span style="font-family:Georgia,serif;font-size:21px;color:#FFF7ED">Araku Tribe</span><span style="font-family:Arial,sans-serif;font-size:12px;color:#F4B63D;padding-left:10px">Order confirmation</span></td></tr>'
                  . '<tr><td style="padding:26px;font-family:Arial,Helvetica,sans-serif;color:#242424">'
                  . '<p style="margin:0 0 6px;font-size:16px">Hi ' . htmlspecialchars(trim(explode(' ', $user_name)[0])) . ',</p>'
                  . '<h1 style="margin:0 0 10px;font-family:Georgia,serif;font-weight:400;font-size:24px;color:#6B3E26">Thank you! Order #' . $order_id . ' is placed.</h1>'
                  . '<p style="margin:0 0 18px;font-size:15px;line-height:1.55;color:#5E5751">' . $note . ' We\'ll let you know when it\'s on its way.</p>'
                  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF7ED;border-radius:14px"><tr><td style="padding:14px 16px;font-size:13.5px;line-height:1.7;color:#3B2317">'
                  . '<b>Payment:</b> ' . htmlspecialchars($label) . ($detail ? ' (' . htmlspecialchars($detail) . ')' : '') . ' · ' . $payment_status . '<br><b>Delivering to:</b> ' . htmlspecialchars($full_address) . '<br><b>Billing:</b> ' . htmlspecialchars($full_billing) . '</td></tr></table>'
                  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px">' . $rows
                  . '<tr><td style="padding:14px 0 0;font-weight:700;font-size:16px">Total</td><td align="right" style="padding:14px 0 0;font-weight:700;font-size:16px;color:#6B3E26">' . $money($total_amount) . '</td></tr></table>'
                  . '<p style="margin:22px 0 0;font-size:13.5px;color:#5E5751">Questions? Just reply to this email.</p></td></tr></table></td></tr></table></body></html>';
                $sent = false;
                if ($user_email !== '') {
                    try {
                        $mail = get_agent_mailer(false);
                        $mail->addAddress($user_email, $user_name);
                        $mail->CharSet = 'UTF-8';
                        $mail->Subject = "Order #$order_id confirmed - Araku Tribe";
                        $mail->Body    = $html;
                        $mail->AltBody = "Hi $user_name, your Araku Tribe order #$order_id is placed ($label). Total: " . html_entity_decode($money($total_amount));
                        $mail->send(); $sent = true;
                    } catch (\Throwable $e) { error_log("Order #$order_id email failed: " . $e->getMessage()); }
                }
                $_SESSION['order_done'] = [
                    'id' => $order_id, 'name' => $user_name, 'email' => $user_email, 'method' => $label, 'pay_status' => $payment_status,
                    'email_sent' => $sent, 'items' => $summary, 'total' => html_entity_decode($money($total_amount)),
                    'bot' => ['order_id' => $order_id, 'total' => $total_amount, 'payment_label' => $label, 'email' => $user_email, 'email_sent' => $sent],
                ];
                unset($_SESSION['shipping_details'], $_SESSION['order_data'], $_SESSION['order_source']);
                $conn->close();
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'redirect', 'url' => 'dummy_payment3.php?placed=' . $order_id]);
                    exit;
                }
                header('Location: dummy_payment3.php?placed=' . $order_id); exit;
            }
        }
    }
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $error]);
        exit;
    }
}
if (empty($_SESSION['pay_token'])) $_SESSION['pay_token'] = bin2hex(random_bytes(16));
$conn->close();

$PAGE_TITLE = 'Payment - Araku Tribe';

// Dual Mode Partial Fragment check for SPA router (e.g. ?partial=1 or header X-Partial: true)
$isPartial = isset($_GET['partial']) && $_GET['partial'] === '1';
if ($isPartial) {
    function dp3_cat_p($it) {
        $k = strtoupper((string)($it['product_id'] ?? $it['image'] ?? ''));
        foreach (['ATCUP' => 'cup', 'ATMUG' => 'mug', 'ATBAG' => 'bag', 'ATTSH' => 'tshirt'] as $p => $c) if (strpos($k, $p) === 0) return $c;
        return 'bag';
    }
    function dp3_img_p($it) {
        if (!empty($it['image_path'])) return $it['image_path'];
        $img = (string)($it['image'] ?? ''); if ($img === '') return 'img/default.png';
        return strpos($img, '/') !== false ? $img : 'img/shop/' . dp3_cat_p($it) . '/' . $img;
    }
    ?>
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="pm-upi" viewBox="0 0 24 24"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10 18h4"/></symbol>
      <symbol id="pm-card" viewBox="0 0 24 24"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6 15h4"/></symbol>
      <symbol id="pm-netbanking" viewBox="0 0 24 24"><path d="M3 10 12 4l9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 20h18"/></symbol>
      <symbol id="pm-amazonpay" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="13" rx="3"/><path d="M16 12.5h2M3 10h18"/></symbol>
      <symbol id="pm-cod" viewBox="0 0 24 24"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9v.01M18 15v.01"/></symbol>
    </svg>

    <main id="spa-main-content">
      <section class="co-hero">
        <div class="wrap">
          <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>" data-spa-link>Home</a><span aria-hidden="true">/</span><a href="<?= h3_link('cart') ?>" data-spa-link>Cart</a><span aria-hidden="true">/</span><a href="process_order3.php" data-spa-link>Delivery details</a><span aria-hidden="true">/</span><span aria-current="page">Payment</span></nav>
          <div class="row"><h1>How would you like to pay?</h1><ol class="steps" aria-label="Checkout steps"><li class="done"><a href="<?= h3_link('cart') ?>" data-spa-link><b>✓</b><span class="lbl">Cart</span></a></li><li class="bar done" aria-hidden="true"></li><li class="done"><a href="process_order3.php" data-spa-link><b>✓</b><span class="lbl">Details</span></a></li><li class="bar done" aria-hidden="true"></li><li class="on"><b>3</b><span class="lbl">Payment</span></li></ol></div>
        </div>
      </section>

      <?php if (function_exists('render_currency_notice')): ?><div class="wrap" style="padding-top:20px"><?= render_currency_notice($displayCurrency) ?></div><?php endif; ?>
      <form class="wrap co" method="POST" action="dummy_payment3.php" id="payForm" novalidate autocomplete="off">
        <input type="hidden" name="pay_token" value="<?= h3_e($_SESSION['pay_token']) ?>">
        <input type="hidden" name="place_order" value="1">
        <div>
          <?php if ($error): ?><p class="flash bad" role="alert"><?= h3_e($error) ?></p><?php endif; ?>
          <section class="panel">
            <h2><span class="n">1</span>Payment method</h2>
            <p class="sim">This is a test checkout. No real payment is taken.</p>
            <fieldset class="methods"><legend class="sr-only">Payment method</legend>
              <?php foreach ($PAYMENT as $k => $p): ?>
                <label class="method"><input type="radio" name="payment_method" value="<?= h3_e($k) ?>" <?= $k === $method ? 'checked' : '' ?>>
                  <span class="ic"><svg><use href="#pm-<?= h3_e($k) ?>"/></svg></span><span><strong><?= h3_e($p['label']) ?></strong><small><?= h3_e($p['sub']) ?></small></span><span class="dot" aria-hidden="true"></span></label>
                <?php if ($k === 'upi'): ?><div class="pm" data-pm="upi"><div class="field" style="margin:0"><label for="upi_id">UPI ID</label><input id="upi_id" name="upi_id" placeholder="yourname@bank" autocapitalize="off" spellcheck="false"></div></div><?php endif; ?>
                <?php if ($k === 'card'): ?><div class="pm" data-pm="card">
                  <div class="field"><label for="card_number">Card number</label><input id="card_number" name="card_number" inputmode="numeric" maxlength="23" placeholder="1234 5678 9012 3456" autocomplete="cc-number"></div>
                  <div class="two"><div class="field"><label for="card_expiry">Expiry</label><input id="card_expiry" name="card_expiry" inputmode="numeric" maxlength="5" placeholder="MM/YY" autocomplete="cc-exp"></div>
                    <div class="field"><label for="card_cvv">CVV</label><input type="password" id="card_cvv" name="card_cvv" inputmode="numeric" maxlength="4" placeholder="•••" autocomplete="cc-csc"></div></div>
                  <div class="field" style="margin:0"><label for="card_name">Name on card</label><input id="card_name" name="card_name" autocomplete="cc-name"></div></div><?php endif; ?>
                <?php if ($k === 'netbanking'): ?><div class="pm" data-pm="netbanking"><div class="field" style="margin:0"><label for="netbanking_bank">Your bank</label><select id="netbanking_bank" name="netbanking_bank"><option value="">Choose your bank</option><?php foreach ($BANKS as $bk => $bl): ?><option value="<?= h3_e($bk) ?>"><?= h3_e($bl) ?></option><?php endforeach; ?></select></div></div><?php endif; ?>
                <?php if ($k === 'amazonpay'): ?><div class="pm" data-pm="amazonpay"><div class="field" style="margin:0"><label for="amazonpay_mobile">Mobile number linked to Amazon Pay</label><input type="tel" id="amazonpay_mobile" name="amazonpay_mobile" inputmode="numeric" maxlength="10" placeholder="10-digit mobile number"></div></div><?php endif; ?>
              <?php endforeach; ?>
            </fieldset>
          </section>
          <section class="panel">
            <h2><span class="n">2</span>Delivering to</h2>
            <div class="addr">
              <div><a href="process_order3.php" data-spa-link>Change</a><b>Delivery address</b><?= h3_e($full_address) ?></div>
              <div><a href="process_order3.php" data-spa-link>Change</a><b>Billing address</b><?= h3_e($full_billing) ?></div>
              <?php if ($user_email !== ''): ?><div><b>Confirmation email</b><?= h3_e($user_email) ?></div><?php endif; ?>
            </div>
          </section>
        </div>

        <aside class="sum" aria-label="Order summary">
          <h2>Order details <small><?= count($items) ?> <?= count($items) === 1 ? 'product' : 'products' ?></small></h2>
          <ul class="sum-rich">
            <?php foreach ($items as $it): $q = (int)($it['quantity'] ?? 0); $pr = (float)($it['price'] ?? 0); $pid = (string)($it['product_id'] ?? ''); ?>
              <li data-cat="<?= h3_e(dp3_cat_p($it)) ?>">
                <span class="im"><img src="<?= h3_e(dp3_img_p($it)) ?>" alt="" loading="lazy"></span>
                <span class="tx"><b><?= h3_e($it['product_name'] ?? 'Product') ?></b><?php if ($pid !== ''): ?><small>ID: <?= h3_e($pid) ?></small><?php endif; ?><small><?= $q ?> units × <?= $money($pr) ?></small></span>
                <span class="pr"><?= $money($pr * $q) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <dl class="sum-rows">
            <div><dt>Subtotal</dt><dd><?= $money($total_amount) ?></dd></div>
            <div><dt>Total units</dt><dd><?= array_sum(array_map(fn($i) => (int)($i['quantity'] ?? 0), $items)) ?></dd></div>
            <div><dt>Delivery</dt><dd class="muted">Confirmed with your order</dd></div>
          </dl>
          <div class="sum-total"><span>Total</span><strong><?= $money($total_amount) ?></strong></div>
          <button type="submit" class="btn btn-primary" id="placeBtn" form="payForm">Place order · <?= $money($total_amount) ?></button>
          <a class="back" href="process_order3.php" data-spa-link>Back to delivery details</a>
          <ul class="trust"><li>Card details are never stored</li><li>Your order is only placed once, even if you tap twice</li></ul>
        </aside>
      </form>
    </main>

    <script>
    if (window.initDummyPaymentPage) { window.initDummyPaymentPage(); }
    </script>
    <?php
    exit;
}

include __DIR__ . '/header3.php';
function dp3_cat($it) {                       // same image-prefix rule as the cart
    $k = strtoupper((string)($it['product_id'] ?? $it['image'] ?? ''));
    foreach (['ATCUP' => 'cup', 'ATMUG' => 'mug', 'ATBAG' => 'bag', 'ATTSH' => 'tshirt'] as $p => $c) if (strpos($k, $p) === 0) return $c;
    return 'bag';
}
function dp3_img($it) {
    if (!empty($it['image_path'])) return $it['image_path'];
    $img = (string)($it['image'] ?? ''); if ($img === '') return 'img/default.png';
    return strpos($img, '/') !== false ? $img : 'img/shop/' . dp3_cat($it) . '/' . $img;
}
?>
<style>
/* ============ dummy_payment3.php ============ */

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

.sim{display:flex;gap:10px;align-items:center;margin:0 0 16px;padding:12px 14px;border-radius:14px;background:#FFF1D6;color:#6A4A12;font-size:14px;font-weight:600}
.methods{display:grid;gap:10px;margin:0 0 18px;padding:0;border:0}
.method{position:relative;display:flex;align-items:center;gap:14px;padding:14px 16px;border:1.5px solid var(--line);border-radius:16px;cursor:pointer;transition:border-color .15s,background .15s}
.method:hover{border-color:var(--brown-soft)}
.method input{position:absolute;opacity:0}
.method .ic{flex:none;width:44px;height:44px;border-radius:12px;background:var(--cream);color:var(--brown);display:flex;align-items:center;justify-content:center}
.method .ic svg{width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.method strong{display:block;color:var(--brown-deep)}
.method small{display:block;color:var(--muted);font-size:13px}
.method .dot{margin-left:auto;width:22px;height:22px;border-radius:50%;border:2px solid var(--line)}
.method:has(input:checked){border-color:var(--brown);background:#FFFBF6}
.method:has(input:checked) .dot{border-color:var(--brown);box-shadow:inset 0 0 0 5px #fff;background:var(--brown)}
.method:has(input:focus-visible){outline:3px solid var(--gold);outline-offset:2px}
.pm{padding:16px;border-radius:16px;background:var(--cream);margin:-4px 0 6px}
.pm[hidden]{display:none}
.pm .field input,.pm .field select{background:#fff}
.addr{display:grid;gap:10px;font-size:14.5px}
.addr div{padding:12px 14px;border-radius:14px;background:var(--cream)}
.addr b{display:block;font-size:12.5px;color:var(--muted);font-weight:600}
.addr a{float:right;font-weight:700;font-size:13px;color:var(--orange-cta)}
</style>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="pm-upi" viewBox="0 0 24 24"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10 18h4"/></symbol>
  <symbol id="pm-card" viewBox="0 0 24 24"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6 15h4"/></symbol>
  <symbol id="pm-netbanking" viewBox="0 0 24 24"><path d="M3 10 12 4l9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 20h18"/></symbol>
  <symbol id="pm-amazonpay" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="13" rx="3"/><path d="M16 12.5h2M3 10h18"/></symbol>
  <symbol id="pm-cod" viewBox="0 0 24 24"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9v.01M18 15v.01"/></symbol>
</svg>

<main id="spa-main-content">
  <section class="co-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>" data-spa-link>Home</a><span aria-hidden="true">/</span><a href="<?= h3_link('cart') ?>" data-spa-link>Cart</a><span aria-hidden="true">/</span><a href="process_order3.php" data-spa-link>Delivery details</a><span aria-hidden="true">/</span><span aria-current="page">Payment</span></nav>
      <div class="row"><h1>How would you like to pay?</h1><ol class="steps" aria-label="Checkout steps"><li class="done"><a href="<?= h3_link('cart') ?>" data-spa-link><b>✓</b><span class="lbl">Cart</span></a></li><li class="bar done" aria-hidden="true"></li><li class="done"><a href="process_order3.php" data-spa-link><b>✓</b><span class="lbl">Details</span></a></li><li class="bar done" aria-hidden="true"></li><li class="on"><b>3</b><span class="lbl">Payment</span></li></ol></div>
    </div>
  </section>

  <?php if (function_exists('render_currency_notice')): ?><div class="wrap" style="padding-top:20px"><?= render_currency_notice($displayCurrency) ?></div><?php endif; ?>
  <form class="wrap co" method="POST" action="dummy_payment3.php" id="payForm" novalidate autocomplete="off">
    <input type="hidden" name="pay_token" value="<?= h3_e($_SESSION['pay_token']) ?>">
    <input type="hidden" name="place_order" value="1">
    <div>
      <?php if ($error): ?><p class="flash bad" role="alert"><?= h3_e($error) ?></p><?php endif; ?>
      <section class="panel">
        <h2><span class="n">1</span>Payment method</h2>
        <p class="sim">This is a test checkout. No real payment is taken.</p>
        <fieldset class="methods"><legend class="sr-only">Payment method</legend>
          <?php foreach ($PAYMENT as $k => $p): ?>
            <label class="method"><input type="radio" name="payment_method" value="<?= h3_e($k) ?>" <?= $k === $method ? 'checked' : '' ?>>
              <span class="ic"><svg><use href="#pm-<?= h3_e($k) ?>"/></svg></span><span><strong><?= h3_e($p['label']) ?></strong><small><?= h3_e($p['sub']) ?></small></span><span class="dot" aria-hidden="true"></span></label>
            <?php if ($k === 'upi'): ?><div class="pm" data-pm="upi"><div class="field" style="margin:0"><label for="upi_id">UPI ID</label><input id="upi_id" name="upi_id" placeholder="yourname@bank" autocapitalize="off" spellcheck="false"></div></div><?php endif; ?>
            <?php if ($k === 'card'): ?><div class="pm" data-pm="card">
              <div class="field"><label for="card_number">Card number</label><input id="card_number" name="card_number" inputmode="numeric" maxlength="23" placeholder="1234 5678 9012 3456" autocomplete="cc-number"></div>
              <div class="two"><div class="field"><label for="card_expiry">Expiry</label><input id="card_expiry" name="card_expiry" inputmode="numeric" maxlength="5" placeholder="MM/YY" autocomplete="cc-exp"></div>
                <div class="field"><label for="card_cvv">CVV</label><input type="password" id="card_cvv" name="card_cvv" inputmode="numeric" maxlength="4" placeholder="•••" autocomplete="cc-csc"></div></div>
              <div class="field" style="margin:0"><label for="card_name">Name on card</label><input id="card_name" name="card_name" autocomplete="cc-name"></div></div><?php endif; ?>
            <?php if ($k === 'netbanking'): ?><div class="pm" data-pm="netbanking"><div class="field" style="margin:0"><label for="netbanking_bank">Your bank</label><select id="netbanking_bank" name="netbanking_bank"><option value="">Choose your bank</option><?php foreach ($BANKS as $bk => $bl): ?><option value="<?= h3_e($bk) ?>"><?= h3_e($bl) ?></option><?php endforeach; ?></select></div></div><?php endif; ?>
            <?php if ($k === 'amazonpay'): ?><div class="pm" data-pm="amazonpay"><div class="field" style="margin:0"><label for="amazonpay_mobile">Mobile number linked to Amazon Pay</label><input type="tel" id="amazonpay_mobile" name="amazonpay_mobile" inputmode="numeric" maxlength="10" placeholder="10-digit mobile number"></div></div><?php endif; ?>
          <?php endforeach; ?>
        </fieldset>
      </section>
      <section class="panel">
        <h2><span class="n">2</span>Delivering to</h2>
        <div class="addr">
          <div><a href="process_order3.php" data-spa-link>Change</a><b>Delivery address</b><?= h3_e($full_address) ?></div>
          <div><a href="process_order3.php" data-spa-link>Change</a><b>Billing address</b><?= h3_e($full_billing) ?></div>
          <?php if ($user_email !== ''): ?><div><b>Confirmation email</b><?= h3_e($user_email) ?></div><?php endif; ?>
        </div>
      </section>
    </div>

    <aside class="sum" aria-label="Order summary">
      <h2>Order details <small><?= count($items) ?> <?= count($items) === 1 ? 'product' : 'products' ?></small></h2>
      <ul class="sum-rich">
        <?php foreach ($items as $it): $q = (int)($it['quantity'] ?? 0); $pr = (float)($it['price'] ?? 0); $pid = (string)($it['product_id'] ?? ''); ?>
          <li data-cat="<?= h3_e(dp3_cat($it)) ?>">
            <span class="im"><img src="<?= h3_e(dp3_img($it)) ?>" alt="" loading="lazy"></span>
            <span class="tx"><b><?= h3_e($it['product_name'] ?? 'Product') ?></b><?php if ($pid !== ''): ?><small>ID: <?= h3_e($pid) ?></small><?php endif; ?><small><?= $q ?> units × <?= $money($pr) ?></small></span>
            <span class="pr"><?= $money($pr * $q) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <dl class="sum-rows">
        <div><dt>Subtotal</dt><dd><?= $money($total_amount) ?></dd></div>
        <div><dt>Total units</dt><dd><?= array_sum(array_map(fn($i) => (int)($i['quantity'] ?? 0), $items)) ?></dd></div>
        <div><dt>Delivery</dt><dd class="muted">Confirmed with your order</dd></div>
      </dl>
      <div class="sum-total"><span>Total</span><strong><?= $money($total_amount) ?></strong></div>
      <button type="submit" class="btn btn-primary" id="placeBtn" form="payForm">Place order · <?= $money($total_amount) ?></button>
      <a class="back" href="process_order3.php" data-spa-link>Back to delivery details</a>
      <ul class="trust"><li>Card details are never stored</li><li>Your order is only placed once, even if you tap twice</li></ul>
    </aside>
  </form>
</main>

<script>
window.initDummyPaymentPage = function() {
  const form = document.getElementById('payForm');
  if (!form) return;
  const show = () => { 
    const checkedRadio = form.querySelector('input[name="payment_method"]:checked');
    if (!checkedRadio) return;
    const m = checkedRadio.value; 
    form.querySelectorAll('.pm').forEach(p => { p.hidden = p.dataset.pm !== m; }); 
  };
  form.querySelectorAll('input[name="payment_method"]').forEach(r => r.addEventListener('change', show)); show();
  
  const cn = document.getElementById('card_number'); 
  if (cn) cn.addEventListener('input', () => { cn.value = cn.value.replace(/\D/g, '').slice(0, 19).replace(/(.{4})/g, '$1 ').trim(); });
  
  const ex = document.getElementById('card_expiry'); 
  if (ex) ex.addEventListener('input', () => { let v = ex.value.replace(/\D/g, '').slice(0, 4); if (v.length > 2) v = v.slice(0, 2) + '/' + v.slice(2); ex.value = v; });
  
  ['card_cvv', 'amazonpay_mobile'].forEach(id => { const i = document.getElementById(id); if (i) i.addEventListener('input', () => { i.value = i.value.replace(/\D/g, ''); }); });
  
  form.addEventListener('submit', async e => {
    const checkedRadio = form.querySelector('input[name="payment_method"]:checked');
    if (!checkedRadio) return;
    const m = checkedRadio.value;
    const fieldsMap = { upi: ['upi_id'], card: ['card_number', 'card_expiry', 'card_cvv', 'card_name'], netbanking: ['netbanking_bank'], amazonpay: ['amazonpay_mobile'], cod: [] };
    const need = (fieldsMap[m] || []).map(id => document.getElementById(id)).find(i => i && !i.value.trim());
    
    if (need) { 
      e.preventDefault(); 
      need.focus(); 
      if (window.A3) A3.toast('Please fill in your payment details.'); 
      return; 
    }
    
    const b = document.getElementById('placeBtn'); 
    if (b) { b.disabled = true; b.textContent = 'Placing your order…'; }

    // Handle SPA/AJAX submission if SPA router is active
    if (window.spaNavigate) {
      e.preventDefault();
      const formData = new FormData(form);
      formData.append('ajax', '1');
      try {
        const res = await fetch('dummy_payment3.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const data = await res.json();
        if (data.status === 'redirect') {
          window.spaNavigate(data.url);
        } else if (data.status === 'error') {
          if (b) { b.disabled = false; b.textContent = 'Place order'; }
          if (window.A3) A3.toast(data.message || 'Please check your payment details.');
        }
      } catch (err) {
        form.submit();
      }
    }
  });
};
window.initDummyPaymentPage();
</script>

<?php include __DIR__ . '/footer3.php'; ?>