<?php
/* =====================================================================
   ARAKU TRIBE - user_orders3.php  (My orders)
   Same data and rules as user_orders.php:
     - orders + order_items for the signed-in customer, newest first
     - cancel only when status is NOT processing / out for delivery /
       delivered (and not already cancelled); emails a confirmation
   Fix: cancelling is a confirmed button with a security token
   (the old ?action=cancel link could be triggered by other sites).
   Uses header3.php + footer3.php.
   ===================================================================== */
session_start();
include 'db1.php';

// Dual Mode / SPA detection: check for AJAX header or parameter
$isAjax       = (($_POST['ajax'] ?? '') === '1') || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_GET['ajax']) && $_GET['ajax'] === '1');

if (empty($_SESSION['user_id'])) {                      // not signed in: sign in first, then come back here
    $_SESSION['redirect_after_login'] = basename(__FILE__);
    if ($isAjax) {
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

$NON_CANCELLABLE = ['processing', 'out for delivery', 'delivered'];
$MAIL_FROM = 'no-reply@yourcoffeeshop.com';   // must be a valid sender on your server (same as before)

require_once 'currency_helper.php';
$displayCurrency = resolve_display_currency($conn);
$currencyInfo    = $CURRENCY_INFO[$displayCurrency];

function o3_items($conn, $orderId) {
    $items = [];
    $st = $conn->prepare("SELECT product_name, quantity, price FROM order_items WHERE order_id = ?");
    if ($st) { $st->bind_param("i", $orderId); $st->execute(); $items = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close(); }
    return $items;
}
function o3_cancel_email($to, $orderId, $name, $items, $total, $ci, $from) {
    $m = fn($usd) => htmlspecialchars($ci['symbol']) . number_format($usd * $ci['rate'], 2);
    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr><td style="padding:10px 0;border-bottom:1px solid #EDE2D6;font-size:14px;color:#242424">' . htmlspecialchars($it['product_name'])
              .  '<br><span style="font-size:12px;color:#77716C">' . (int)$it['quantity'] . ' units</span></td>'
              .  '<td align="right" style="padding:10px 0;border-bottom:1px solid #EDE2D6;font-size:14px;color:#242424">' . $m($it['price'] * $it['quantity']) . '</td></tr>';
    }
    $html = '<!doctype html><html><body style="margin:0;background:#F7EADB"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7EADB;padding:32px 12px"><tr><td align="center">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:20px;overflow:hidden">'
      . '<tr><td style="background:#3B2317;padding:22px 28px"><span style="font-family:Georgia,serif;font-size:22px;color:#FFF7ED">Araku Tribe</span></td></tr>'
      . '<tr><td style="padding:28px;font-family:Arial,Helvetica,sans-serif;color:#242424">'
      .   '<p style="margin:0 0 6px;font-size:16px">Hi ' . htmlspecialchars($name ?: 'there') . ',</p>'
      .   '<h1 style="margin:0 0 10px;font-family:Georgia,serif;font-weight:400;font-size:24px;color:#6B3E26">Order #' . (int)$orderId . ' is cancelled</h1>'
      .   '<p style="margin:0 0 18px;font-size:15px;line-height:1.55;color:#5E5751">We\'ve cancelled this order as you asked. If you\'d already paid, your refund will be started and should reach you in 5–7 business days.</p>'
      .   '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows
      .   '<tr><td style="padding:14px 0 0;font-weight:700;font-size:15px">Cancelled total</td><td align="right" style="padding:14px 0 0;font-weight:700;font-size:15px;color:#6B3E26">' . $m($total) . '</td></tr></table>'
      .   '<p style="margin:22px 0 0;font-size:13.5px;color:#5E5751">Questions? Just reply to this email or write to info@arakutribe.com.</p>'
      . '</td></tr></table></td></tr></table></body></html>';
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: Araku Tribe <{$from}>\r\nReply-To: {$from}\r\nX-Mailer: PHP/" . phpversion();
    return @mail($to, 'Order #' . (int)$orderId . ' cancelled - Araku Tribe', $html, $headers);
}

/* ---------- cancel (POST + token) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
        $_SESSION['orders_flash'] = ['bad', 'Your session expired. Please try cancelling again.'];
    } else {
        $st = $conn->prepare("SELECT o.order_status, o.total_amount, u.email, u.firstname FROM orders o JOIN users u ON o.user_id = u.user_id WHERE o.order_id = ? AND o.user_id = ?");
        $st->bind_param("ii", $orderId, $user_id); $st->execute();
        $od = $st->get_result()->fetch_assoc(); $st->close();
        $status = strtolower($od['order_status'] ?? '');
        if (!$od) {
            $_SESSION['orders_flash'] = ['bad', 'We couldn\'t find that order on your account.'];
        } elseif ($status === 'cancelled') {
            $_SESSION['orders_flash'] = ['ok', "Order #$orderId was already cancelled."];
        } elseif (in_array($status, $NON_CANCELLABLE, true)) {
            $_SESSION['orders_flash'] = ['bad', "Order #$orderId can't be cancelled any more because it's " . $status . '. Contact us and we\'ll help.'];
        } else {
            $up = $conn->prepare("UPDATE orders SET order_status = 'cancelled' WHERE order_id = ? AND user_id = ?");
            $up->bind_param("ii", $orderId, $user_id);
            if ($up->execute()) {
                $sent = o3_cancel_email($od['email'], $orderId, $od['firstname'], o3_items($conn, $orderId), $od['total_amount'], $currencyInfo, $MAIL_FROM);
                $_SESSION['orders_flash'] = ['ok', "Order #$orderId has been cancelled." . ($sent ? ' We\'ve emailed you a confirmation.' : '')];
            } else {
                error_log('Cancel order failed: ' . $conn->error);
                $_SESSION['orders_flash'] = ['bad', 'Something went wrong cancelling that order. Please try again.'];
            }
            $up->close();
        }
    }
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'url' => 'user_orders3.php']);
        exit;
    }
    header('Location: user_orders3.php');
    exit;
}

/* ---------- load orders ---------- */
$orders = [];
$st = $conn->prepare("SELECT order_id, total_amount, order_status, order_date, payment_method, shipping_address FROM orders WHERE user_id = ? ORDER BY order_date DESC");
if ($st) {
    $st->bind_param("i", $user_id); $st->execute();
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $o) {
        $status = strtolower((string)$o['order_status']);
        $items  = array_map(fn($it) => [
            'name'  => (string)$it['product_name'],
            'qty'   => (int)$it['quantity'],
            'price' => round((float)$it['price'] * $currencyInfo['rate'], 2),
            'sub'   => round((float)$it['price'] * (int)$it['quantity'] * $currencyInfo['rate'], 2),
        ], o3_items($conn, (int)$o['order_id']));
        $ts = strtotime((string)$o['order_date']);
        $orders[] = [
            'id'      => (int)$o['order_id'],
            'total'   => round((float)$o['total_amount'] * $currencyInfo['rate'], 2),
            'status'  => $status,
            'date'    => $ts ? date('j M Y', $ts) : (string)$o['order_date'],
            'time'    => $ts ? date('g:i A', $ts) : '',
            'payment' => (string)$o['payment_method'],
            'address' => (string)$o['shipping_address'],
            'items'   => $items,
            'canCancel' => $status !== 'cancelled' && !in_array($status, $NON_CANCELLABLE, true),
        ];
    }
    $st->close();
}
$u = $conn->prepare("SELECT firstname, email, profile_picture FROM users WHERE user_id = ? LIMIT 1");
$u->bind_param("i", $user_id); $u->execute(); $me = $u->get_result()->fetch_assoc() ?: []; $u->close();
$conn->close();

$acctFirst = trim((string)($me['firstname'] ?? $_SESSION['user_name'] ?? ''));
$acctEmail = (string)($me['email'] ?? $_SESSION['user_email'] ?? '');
$avatarInitial = strtoupper(substr($acctFirst !== '' ? $acctFirst : 'A', 0, 1));
$avatarSrc = (!empty($me['profile_picture']) && is_file('uploads/profile_pictures/' . $me['profile_picture'])) ? 'uploads/profile_pictures/' . $me['profile_picture'] : '';
$flash = $_SESSION['orders_flash'] ?? null; unset($_SESSION['orders_flash']);

$PAGE_TITLE = 'My orders - Araku Tribe';
$ACTIVE_NAV = '';

// Dual Mode Partial Fragment check for SPA router (e.g. ?partial=1 or header X-Partial: true)
$isPartial = isset($_GET['partial']) && $_GET['partial'] === '1';
if ($isPartial) {
    ?>
    <main id="spa-main-content">

      <section class="acct-hero">
        <div class="wrap">
          <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>" data-spa-link>Home</a><span aria-hidden="true">/</span><span>My account</span><span aria-hidden="true">/</span><span aria-current="page">Orders</span></nav>
          <div class="acct-head">
            <span class="big-avatar" aria-hidden="true"><?php if (!empty($avatarSrc)): ?><img src="<?= h3_e($avatarSrc) ?>" alt=""><?php else: ?><?= h3_e($avatarInitial) ?><?php endif; ?></span>
            <div><h1>My orders</h1><p><?= h3_e($acctEmail) ?></p></div>
          </div>
          <nav class="acct-tabs" aria-label="My account">
            <a href="<?= h3_link('profile') ?>" data-spa-link><svg class="ico"><use href="#i-user"/></svg>Profile</a>
            <a href="<?= h3_link('orders') ?>" aria-current="page" data-spa-link><svg class="ico"><use href="#i-orders"/></svg>Orders</a>
            <a href="<?= h3_link('wishlist') ?>" data-spa-link><svg class="ico"><use href="#i-heart"/></svg>Wishlist</a>
            <a href="<?= h3_link('cart') ?>" data-spa-link><svg class="ico"><use href="#i-bag"/></svg>Cart</a>
            <a class="out" href="<?= h3_link('logout') ?>" data-spa-link><svg class="ico"><use href="#i-logout"/></svg>Log out</a>
          </nav>
        </div>
      </section>

      <section class="acct-body">
        <div class="wrap">
          <?php if ($flash): ?><p class="flash <?= $flash[0] === 'ok' ? 'ok' : 'bad' ?>" role="status"><?= h3_e($flash[1]) ?></p><?php endif; ?>
          <?php if (function_exists('render_currency_notice')) echo render_currency_notice($displayCurrency); ?>

          <div class="o-bar" id="oBar" hidden>
            <div class="o-filters" id="oFilters" role="group" aria-label="Show orders"></div>
            <label class="o-search"><svg class="ico"><use href="#i-search"/></svg><span class="sr-only">Search orders</span>
              <input type="search" id="oSearch" placeholder="Search by order number or product"></label>
          </div>
          <div class="orders" id="orders"></div>

          <div class="help-strip">
            <span class="orb"><svg class="ico"><use href="#i-spark"/></svg></span>
            <div><strong>Need help with an order?</strong><span>The AI Agent can track, cancel or answer questions in one chat.</span></div>
            <a class="btn" href="<?= h3_link('agent') ?>" data-spa-link>Ask the AI Agent</a>
          </div>
        </div>
      </section>
    </main>

    <dialog class="od" id="od" aria-labelledby="odTitle">
      <div class="od-head"><h2 id="odTitle"></h2><button type="button" class="icon-btn" id="odClose" aria-label="Close"><svg class="ico"><use href="#i-x"/></svg></button></div>
      <div class="od-body" id="odBody"></div>
      <div class="od-foot" id="odFoot"></div>
    </dialog>

    <dialog class="od" id="oc" aria-labelledby="ocTitle" style="width:min(460px,94vw)">
      <div class="od-head"><h2 id="ocTitle">Cancel this order?</h2><button type="button" class="icon-btn" data-oc-close aria-label="Close"><svg class="ico"><use href="#i-x"/></svg></button></div>
      <div class="od-body"><p id="ocText" style="margin:0"></p><p style="margin:10px 0 0;font-size:14px;color:var(--muted)">If you've already paid, your refund will be started and should reach you in 5–7 business days.</p></div>
      <form method="POST" action="user_orders3.php" class="od-foot" id="cancelForm">
        <input type="hidden" name="action" value="cancel"><input type="hidden" name="csrf" value="<?= h3_e($CSRF) ?>"><input type="hidden" name="order_id" id="ocId">
        <button type="button" class="btn btn-outline" data-oc-close>Keep my order</button>
        <button type="submit" class="btn btn-cancel" id="ocGo">Yes, cancel order</button>
      </form>
    </dialog>

    <script>
    if (window.initUserOrdersPage) { window.initUserOrdersPage(); }
    </script>
    <?php
    exit;
}

include __DIR__ . '/header3.php';
?>
<style>
/* ============ user_orders3.php ============ */

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

.o-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0 0 20px}
.o-filters{display:flex;gap:8px;flex-wrap:wrap}
.o-filters button{border:1.5px solid var(--line);background:#fff;border-radius:999px;padding:8px 16px;font-weight:600;font-size:14px;color:var(--ink)}
.o-filters button small{margin-left:4px;opacity:.65}
.o-filters button:hover{border-color:var(--brown-soft)}
.o-filters button[aria-pressed="true"]{background:var(--brown);border-color:var(--brown);color:#fff}
.o-search{position:relative;margin-left:auto;width:min(280px,100%)}
.o-search input{width:100%;height:42px;border:1.5px solid var(--line);border-radius:999px;padding:0 14px 0 38px;background:var(--cream);font:inherit;font-size:14.5px}
.o-search input:focus{outline:none;border-color:var(--brown-soft);background:#fff}
.o-search .ico{position:absolute;left:13px;top:11px;width:18px;height:18px;color:var(--muted)}
.orders{display:grid;gap:16px}
.order{border:1px solid var(--line);border-radius:22px;background:#fff;overflow:hidden;transition:box-shadow .2s}
.order:hover{box-shadow:0 18px 36px -28px rgba(59,35,23,.6)}
.o-top{display:flex;align-items:center;gap:14px 24px;flex-wrap:wrap;padding:16px 20px;background:var(--cream)}
.o-top div{font-size:13px;color:var(--muted)}
.o-top div b{display:block;font-size:15px;color:var(--brown-deep)}
.o-top .st{margin-left:auto}
.badge-st{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-weight:700;font-size:13px}
.badge-st::before{content:"";width:8px;height:8px;border-radius:50%;background:currentColor}
.st-pending{background:#FFF1D6;color:#8A5A12}.st-processing{background:#E8EEF9;color:#2D4E8A}.st-out{background:#EDE5F6;color:#5B3B8A}
.st-delivered{background:var(--green-soft);color:var(--green)}.st-cancelled{background:#FBE4E4;color:#9B2626}
.o-main{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:20px;align-items:center;padding:18px 20px}
.o-items{margin:0;padding:0;list-style:none;display:grid;gap:6px;font-size:14.5px}
.o-items li{display:flex;gap:8px;align-items:baseline}
.o-items li span{color:var(--muted);font-size:13px;white-space:nowrap}
.o-items li.more{color:var(--muted);font-size:13.5px}
.o-acts{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
.o-acts .btn{padding:11px 18px;font-size:14px}
.btn-cancel{border:1.5px solid #E7B9B9;color:#9B2626;background:#fff}
.btn-cancel:hover{background:#FBE9E9}
.track{display:grid;grid-template-columns:repeat(4,1fr);gap:0;margin:0 20px 18px;padding:0;list-style:none}
.track li{position:relative;padding-top:22px;font-size:12.5px;font-weight:600;color:var(--muted);text-align:center}
.track li::before{content:"";position:absolute;top:4px;left:50%;width:14px;height:14px;margin-left:-7px;border-radius:50%;background:#fff;border:2px solid var(--line);z-index:1}
.track li::after{content:"";position:absolute;top:10px;left:-50%;width:100%;height:2px;background:var(--line)}
.track li:first-child::after{display:none}
.track li.done{color:var(--green)}
.track li.done::before{background:var(--green);border-color:var(--green)}
.track li.done::after{background:var(--green)}
.track li.now{color:var(--brown-deep)}
.track li.now::before{background:#fff;border-color:var(--green);box-shadow:0 0 0 4px var(--green-soft)}
.empty-o{text-align:center;padding:56px 20px;border:1px solid var(--line);border-radius:24px}
.empty-o .big{width:76px;height:76px;margin:0 auto 16px;border-radius:24px;display:flex;align-items:center;justify-content:center;background:var(--cream);color:var(--brown)}
.empty-o .big .ico{width:34px;height:34px}
.empty-o h2{font-size:1.8rem}
.empty-o p{color:var(--muted);margin:8px 0 20px}
.help-strip{display:flex;align-items:center;gap:14px;margin-top:24px;padding:16px 20px;border-radius:20px;color:var(--cream);background:linear-gradient(135deg,#3B2317,#6B3E26)}
.help-strip strong{display:block;color:#fff}
.help-strip span{font-size:14px;color:#E6D5C4}
.help-strip .btn{margin-left:auto;background:#fff;color:var(--brown-deep)}
dialog.od{border:0;border-radius:22px;padding:0;width:min(640px,94vw);max-height:90vh;box-shadow:0 30px 80px -20px rgba(36,24,17,.5)}
dialog.od::backdrop{background:rgba(36,24,17,.5)}
.od-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 22px;background:var(--brown-deep);color:#fff}
.od-head h2{color:#fff;font-size:1.35rem}
.od-head .icon-btn{color:#fff}
.od-head .icon-btn:hover{background:rgba(255,255,255,.12)}
.od-body{padding:20px 22px;overflow:auto}
.od-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:18px}
.od-meta div{padding:10px 12px;border-radius:14px;background:var(--cream);font-size:12.5px;color:var(--muted)}
.od-meta b{display:block;margin-top:2px;font-size:14.5px;color:var(--brown-deep)}
.od-table{width:100%;border-collapse:collapse;font-size:14.5px}
.od-table th{text-align:left;font-size:12.5px;color:var(--muted);font-weight:600;padding:0 0 8px;border-bottom:1px solid var(--line)}
.od-table td{padding:10px 0;border-bottom:1px solid var(--line)}
.od-table .r{text-align:right}
.od-total{display:flex;justify-content:space-between;padding:14px 0 0;font-weight:700;font-size:16px}
.od-addr{margin-top:18px;padding:14px 16px;border-radius:14px;border:1px dashed var(--line);font-size:14px;white-space:pre-line}
.od-addr b{display:block;margin-bottom:4px;font-size:13px;color:var(--muted)}
.od-foot{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;padding:14px 22px;border-top:1px solid var(--line);background:#fff}
@media (max-width:760px){
  .o-search{margin-left:0;width:100%}
  .o-top .st{margin-left:0}
  .o-main{grid-template-columns:1fr}
  .o-acts{justify-content:stretch}.o-acts .btn{flex:1}
  .track li{font-size:11px}
  .od-meta{grid-template-columns:1fr 1fr}
  .help-strip{flex-wrap:wrap}.help-strip .btn{margin-left:0;width:100%}
}
</style>

<main id="spa-main-content">

  <section class="acct-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>" data-spa-link>Home</a><span aria-hidden="true">/</span><span>My account</span><span aria-hidden="true">/</span><span aria-current="page">Orders</span></nav>
      <div class="acct-head">
        <span class="big-avatar" aria-hidden="true"><?php if (!empty($avatarSrc)): ?><img src="<?= h3_e($avatarSrc) ?>" alt=""><?php else: ?><?= h3_e($avatarInitial) ?><?php endif; ?></span>
        <div><h1>My orders</h1><p><?= h3_e($acctEmail) ?></p></div>
      </div>
      <nav class="acct-tabs" aria-label="My account">
        <a href="<?= h3_link('profile') ?>" data-spa-link><svg class="ico"><use href="#i-user"/></svg>Profile</a>
        <a href="<?= h3_link('orders') ?>" aria-current="page" data-spa-link><svg class="ico"><use href="#i-orders"/></svg>Orders</a>
        <a href="<?= h3_link('wishlist') ?>" data-spa-link><svg class="ico"><use href="#i-heart"/></svg>Wishlist</a>
        <a href="<?= h3_link('cart') ?>" data-spa-link><svg class="ico"><use href="#i-bag"/></svg>Cart</a>
        <a class="out" href="<?= h3_link('logout') ?>" data-spa-link><svg class="ico"><use href="#i-logout"/></svg>Log out</a>
      </nav>
    </div>
  </section>

  <section class="acct-body">
    <div class="wrap">
      <?php if ($flash): ?><p class="flash <?= $flash[0] === 'ok' ? 'ok' : 'bad' ?>" role="status"><?= h3_e($flash[1]) ?></p><?php endif; ?>
      <?php if (function_exists('render_currency_notice')) echo render_currency_notice($displayCurrency); ?>

      <div class="o-bar" id="oBar" hidden>
        <div class="o-filters" id="oFilters" role="group" aria-label="Show orders"></div>
        <label class="o-search"><svg class="ico"><use href="#i-search"/></svg><span class="sr-only">Search orders</span>
          <input type="search" id="oSearch" placeholder="Search by order number or product"></label>
      </div>
      <div class="orders" id="orders"></div>

      <div class="help-strip">
        <span class="orb"><svg class="ico"><use href="#i-spark"/></svg></span>
        <div><strong>Need help with an order?</strong><span>The AI Agent can track, cancel or answer questions in one chat.</span></div>
        <a class="btn" href="<?= h3_link('agent') ?>" data-spa-link>Ask the AI Agent</a>
      </div>
    </div>
  </section>
</main>

<dialog class="od" id="od" aria-labelledby="odTitle">
  <div class="od-head"><h2 id="odTitle"></h2><button type="button" class="icon-btn" id="odClose" aria-label="Close"><svg class="ico"><use href="#i-x"/></svg></button></div>
  <div class="od-body" id="odBody"></div>
  <div class="od-foot" id="odFoot"></div>
</dialog>

<dialog class="od" id="oc" aria-labelledby="ocTitle" style="width:min(460px,94vw)">
  <div class="od-head"><h2 id="ocTitle">Cancel this order?</h2><button type="button" class="icon-btn" data-oc-close aria-label="Close"><svg class="ico"><use href="#i-x"/></svg></button></div>
  <div class="od-body"><p id="ocText" style="margin:0"></p><p style="margin:10px 0 0;font-size:14px;color:var(--muted)">If you've already paid, your refund will be started and should reach you in 5–7 business days.</p></div>
  <form method="POST" action="user_orders3.php" class="od-foot" id="cancelForm">
    <input type="hidden" name="action" value="cancel"><input type="hidden" name="csrf" value="<?= h3_e($CSRF) ?>"><input type="hidden" name="order_id" id="ocId">
    <button type="button" class="btn btn-outline" data-oc-close>Keep my order</button>
    <button type="submit" class="btn btn-cancel" id="ocGo">Yes, cancel order</button>
  </form>
</dialog>

<script>
window.initUserOrdersPage = function() {
'use strict';
const ORDERS = <?= json_encode($orders, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
const SYMBOL = <?= json_encode($currencyInfo['symbol']) ?>;
const $ = s => document.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = v => SYMBOL + Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const cap = s => s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
const STEPS = ['Order placed', 'Processing', 'Out for delivery', 'Delivered'];
const stepOf = s => s === 'delivered' ? 3 : s === 'out for delivery' ? 2 : s === 'processing' ? 1 : 0;
const stClass = s => s === 'delivered' ? 'st-delivered' : s === 'cancelled' ? 'st-cancelled' : s === 'processing' ? 'st-processing' : s === 'out for delivery' ? 'st-out' : 'st-pending';
const stLabel = s => s === 'pending' ? 'Order placed' : cap(s || 'placed');
const GROUP = { all: () => true, active: o => !['delivered', 'cancelled'].includes(o.status), delivered: o => o.status === 'delivered', cancelled: o => o.status === 'cancelled' };
let filter = 'all', q = '';

function trackHTML(o) {
  if (o.status === 'cancelled') return '';
  const n = stepOf(o.status);
  return `<ol class="track" aria-label="Delivery progress">${STEPS.map((s, i) => `<li class="${i < n || (i === n && n === 3) ? 'done' : i === n ? 'now' : ''}">${s}</li>`).join('')}</ol>`;
}
function cardHTML(o) {
  const shown = o.items.slice(0, 2), more = o.items.length - shown.length;
  return `<article class="order">
    <div class="o-top">
      <div>Order<b>#${o.id}</b></div><div>Placed on<b>${esc(o.date)}</b></div><div>Total<b>${money(o.total)}</b></div>
      <div class="st"><span class="badge-st ${stClass(o.status)}">${esc(stLabel(o.status))}</span></div>
    </div>
    <div class="o-main">
      <ul class="o-items">${shown.map(it => `<li>${esc(it.name)} <span>× ${it.qty} units</span></li>`).join('')}${more > 0 ? `<li class="more">+ ${more} more ${more === 1 ? 'item' : 'items'}</li>` : ''}${!o.items.length ? '<li class="more">No item details</li>' : ''}</ul>
      <div class="o-acts">
        <button type="button" class="btn btn-outline" data-view="${o.id}">View details</button>
        ${o.canCancel ? `<button type="button" class="btn btn-cancel" data-cancel="${o.id}">Cancel order</button>` : ''}
      </div>
    </div>
    ${trackHTML(o)}
  </article>`;
}
function render() {
  const counts = Object.fromEntries(Object.keys(GROUP).map(k => [k, ORDERS.filter(GROUP[k]).length]));
  const filtersEl = $('#oFilters');
  if (!filtersEl) return;
  filtersEl.innerHTML = [['all', 'All'], ['active', 'In progress'], ['delivered', 'Delivered'], ['cancelled', 'Cancelled']]
    .map(([k, l]) => `<button type="button" data-f="${k}" aria-pressed="${filter === k}">${l}<small>${counts[k]}</small></button>`).join('');
  const term = q.trim().toLowerCase().replace(/^#/, '');
  const list = ORDERS.filter(GROUP[filter]).filter(o => !term || String(o.id).includes(term) || o.items.some(it => it.name.toLowerCase().includes(term)));
  const barEl = $('#oBar');
  if (barEl) barEl.hidden = !ORDERS.length;
  const ordersEl = $('#orders');
  if (!ordersEl) return;
  if (!ORDERS.length) {
    ordersEl.innerHTML = `<div class="empty-o"><div class="big"><svg class="ico"><use href="#i-orders"/></svg></div><h2>No orders yet</h2>
      <p>When you place an order, you'll be able to track it here.</p><a class="btn btn-primary" href="${esc((window.A3 && A3.links.shop) || 'index.php')}" data-spa-link>Start shopping</a></div>`;
    return;
  }
  ordersEl.innerHTML = list.length ? list.map(cardHTML).join('')
    : `<div class="empty-o"><h2>No orders match</h2><p>Try another filter or search.</p></div>`;
}
function openDetails(id) {
  const o = ORDERS.find(x => x.id === id); if (!o) return;
  $('#odTitle').textContent = 'Order #' + o.id;
  $('#odBody').innerHTML = `
    <div class="od-meta"><div>Placed on<b>${esc(o.date)}${o.time ? ', ' + esc(o.time) : ''}</b></div><div>Status<b>${esc(stLabel(o.status))}</b></div><div>Payment<b>${esc(cap(o.payment) || '—')}</b></div></div>
    ${trackHTML(o).replace('class="track"', 'class="track" style="margin:0 0 18px"')}
    <table class="od-table"><thead><tr><th>Product</th><th class="r">Price</th><th class="r">Qty</th><th class="r">Subtotal</th></tr></thead>
    <tbody>${o.items.map(it => `<tr><td>${esc(it.name)}</td><td class="r">${money(it.price)}</td><td class="r">${it.qty}</td><td class="r">${money(it.sub)}</td></tr>`).join('')}</tbody></table>
    <div class="od-total"><span>Total</span><span>${money(o.total)}</span></div>
    ${o.address ? `<div class="od-addr"><b>Delivering to</b>${esc(o.address)}</div>` : ''}`;
  $('#odFoot').innerHTML = (o.canCancel ? `<button type="button" class="btn btn-cancel" data-cancel="${o.id}">Cancel order</button>` : '')
    + `<a class="btn btn-outline" href="${esc((window.A3 && A3.links.agent) || 'araku_agent3.php')}" data-spa-link>Get help</a><button type="button" class="btn btn-primary" data-od-close>Done</button>`;
  $('#od').showModal();
}
function openCancel(id) {
  const o = ORDERS.find(x => x.id === id); if (!o) return;
  if ($('#od').open) $('#od').close();
  $('#ocId').value = o.id;
  $('#ocText').innerHTML = `Order <b>#${o.id}</b> (${o.items.length} ${o.items.length === 1 ? 'item' : 'items'}, ${money(o.total)}) will be cancelled. This can't be undone.`;
  $('#oc').showModal();
}
document.addEventListener('click', e => {
  const f = e.target.closest('[data-f]'); if (f) { filter = f.dataset.f; render(); return; }
  const v = e.target.closest('[data-view]'); if (v) return openDetails(+v.dataset.view);
  const c = e.target.closest('[data-cancel]'); if (c) return openCancel(+c.dataset.cancel);
  if (e.target.closest('[data-od-close]') || e.target.closest('#odClose') || e.target === $('#od')) { const od = $('#od'); if (od) od.close(); }
  if (e.target.closest('[data-oc-close]') || e.target === $('#oc')) { const oc = $('#oc'); if (oc) oc.close(); }
});

const cancelForm = $('#cancelForm');
if (cancelForm) {
  cancelForm.addEventListener('submit', async e => {
    if (window.spaNavigate) {
      e.preventDefault();
      const b = $('#ocGo'); if (b) { b.disabled = true; b.textContent = 'Cancelling…'; }
      const formData = new FormData(cancelForm);
      formData.append('ajax', '1');
      try {
        const res = await fetch('user_orders3.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const data = await res.json();
        const oc = $('#oc'); if (oc) oc.close();
        if (data.status === 'success' || data.url) {
          window.spaNavigate(data.url || 'user_orders3.php');
        }
      } catch (err) {
        cancelForm.submit();
      }
    }
  });
}

const oSearch = $('#oSearch');
if (oSearch) {
  oSearch.addEventListener('input', e => { q = e.target.value; render(); });
}
render();
};
window.initUserOrdersPage();
</script>

<?php include __DIR__ . '/footer3.php'; ?>