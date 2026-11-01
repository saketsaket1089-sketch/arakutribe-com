<?php
/* =====================================================================
   ARAKU TRIBE - chatbot_api.php   (REBUILT, stage 1)
   The backend of the AI Agent chat (araku_agent3.php). Every answer is JSON.

   WORKS in this version (uses only the products and cart tables and the same
   session cart as add_to_cart.php):
     init, get_items, add_to_cart, get_cart_summary, get_saved_addresses (empty)
   Proceed to buy: hands the visitor over to the cart page (cart3.php), which
   does the checkout.

   NOT in this version (answers with a polite message until they are rebuilt, they
   need your orders / users tables and your email settings):
     check_email_and_send_otp, verify_guest_otp, place_order, lookup_order,
     send_cancel_otp, cancel_order, submit_support_request
   If you find your original file, simply put it back over this one.
   ===================================================================== */
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ob_start();                                   // anything printed by accident is thrown away, so the JSON stays clean
session_start();
header('Content-Type: application/json; charset=utf-8');

$IS_LOCAL = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
function out(array $a) {
    while (ob_get_level()) ob_end_clean();
    echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function fail($msg, $debug = '') { global $IS_LOCAL; $a = ['success' => false, 'error' => $msg]; if ($IS_LOCAL && $debug !== '') $a['debug'] = $debug; out($a); }
set_exception_handler(function ($e) { fail('Something went wrong. Please try again.', $e->getMessage()); });
register_shutdown_function(function () {           // a missing file or a fatal error still answers in JSON
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) fail('Something went wrong. Please try again.', $e['message'] . ' in ' . basename($e['file']) . ':' . $e['line']);
});

/* ---------- SETTINGS ---------- */
$MIN_QTY    = 100;      // minimum order per product, sold in steps of this many units
$PAGE_SIZE  = 5;        // products per "Show 5 more"
$CHECKOUT   = 'cart3.php';   // where "Proceed to buy" sends the visitor
$LABELS     = ['bag' => 'Coffee Bags', 'cup' => 'Coffee Cups', 'mug' => 'Coffee Mugs', 'tshirt' => 'T-Shirts'];   // display order

/* ---------- database + currency ---------- */
include 'db1.php';
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) fail('The shop is not available right now. Please try again shortly.', 'DB: ' . $conn->connect_error);
$conn->set_charset('utf8mb4');
$cur = ['symbol' => '$', 'rate' => 1.0];
if (is_file(__DIR__ . '/currency_helper.php')) {
    require_once 'currency_helper.php';
    $code = resolve_display_currency($conn);
    if (isset($CURRENCY_INFO[$code])) $cur = ['symbol' => $CURRENCY_INFO[$code]['symbol'], 'rate' => (float)$CURRENCY_INFO[$code]['rate']];
}

$action = $_REQUEST['action'] ?? '';
$loggedIn = !empty($_SESSION['user_id']);
$userId   = $loggedIn ? (int)$_SESSION['user_id'] : 0;

/* products that can be shown: the image file name must match the product ID (same rule as every shop page) */
function load_products($conn, $category = null) {
    $rows = [];
    $res = $conn->query("SELECT product_id, product_name, price, quantity, image, category FROM products ORDER BY product_id DESC");
    if (!$res) return $rows;
    while ($r = $res->fetch_assoc()) {
        if (!preg_match('/^' . preg_quote($r['product_id'], '/') . '\.(jpg|jpeg|png|gif|webp)$/i', (string)$r['image'])) continue;
        if ($category !== null && strtolower($r['category']) !== $category) continue;
        $rows[] = $r;
    }
    return $rows;
}
/* the visitor's cart: the database for signed-in visitors, the session for guests (same as add_to_cart.php) */
function cart_lines($conn, $loggedIn, $userId) {
    $lines = [];
    if ($loggedIn) {
        $st = $conn->prepare("SELECT c.product_id, c.quantity, p.product_name, p.price FROM cart c JOIN products p ON c.product_id = p.product_id WHERE c.user_id = ?");
        if ($st) { $st->bind_param('i', $userId); $st->execute(); $r = $st->get_result(); while ($row = $r->fetch_assoc()) $lines[] = ['product_id' => $row['product_id'], 'product_name' => $row['product_name'], 'quantity' => (int)$row['quantity'], 'price' => (float)$row['price']]; $st->close(); }
    } elseif (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $pid => $it) $lines[] = ['product_id' => (string)$pid, 'product_name' => (string)($it['product_name'] ?? $pid), 'quantity' => (int)($it['quantity'] ?? 0), 'price' => (float)($it['price'] ?? 0)];
    }
    return $lines;
}
function cart_total($lines) { $t = 0; foreach ($lines as $l) $t += $l['price'] * $l['quantity']; return round($t, 2); }

switch ($action) {

case 'init': {
    $counts = [];
    foreach (load_products($conn) as $r) { $k = strtolower($r['category']); $counts[$k] = ($counts[$k] ?? 0) + 1; }
    $cats = [];
    foreach ($LABELS as $k => $label) if (!empty($counts[$k])) { $cats[] = ['value' => $k, 'label' => $label, 'count' => $counts[$k]]; unset($counts[$k]); }
    foreach ($counts as $k => $n) $cats[] = ['value' => $k, 'label' => ucwords(str_replace('_', ' ', $k)), 'count' => $n];
    out(['success' => true, 'total' => array_sum(array_column($cats, 'count')), 'categories' => $cats, 'is_logged_in' => $loggedIn,
         'currency_symbol' => $cur['symbol'], 'currency_rate' => $cur['rate']]);
}

case 'get_items': {
    $category = strtolower(trim($_REQUEST['category'] ?? ''));
    $offset   = max(0, (int)($_REQUEST['offset'] ?? 0));
    if ($category === '') fail('Please choose a category.');
    $all = array_values(array_filter(load_products($conn, $category), fn($r) => (int)$r['quantity'] >= $MIN_QTY));   // only what can really be ordered
    $slice = array_slice($all, $offset, $PAGE_SIZE);
    $items = array_map(fn($r) => ['product_id' => (string)$r['product_id'], 'product_name' => (string)$r['product_name'], 'price' => (float)$r['price'],
        'minimum_quantity' => $MIN_QTY, 'image_path' => 'img/shop/' . $r['category'] . '/' . $r['image']], $slice);
    out(['success' => true, 'items' => $items, 'has_more' => ($offset + $PAGE_SIZE) < count($all), 'next_offset' => $offset + $PAGE_SIZE]);
}

case 'add_to_cart': {
    $pid = trim($_POST['id'] ?? '');
    $qty = (int)($_POST['qty'] ?? 0);
    if ($pid === '' || $qty <= 0) fail('Missing product or quantity.');
    if ($qty % $MIN_QTY !== 0) fail("Quantity must be in steps of $MIN_QTY units.");
    $st = $conn->prepare("SELECT product_name, price, image, quantity FROM products WHERE product_id = ?");
    $st->bind_param('s', $pid); $st->execute(); $p = $st->get_result()->fetch_assoc(); $st->close();
    if (!$p) fail('That product could not be found.');
    $have = 0;
    foreach (cart_lines($conn, $loggedIn, $userId) as $l) if ($l['product_id'] === $pid) $have = $l['quantity'];
    if ($have + $qty > (int)$p['quantity']) fail('Sorry, only ' . max(0, (int)$p['quantity'] - $have) . ' more units are available.');
    if ($loggedIn) {
        $chk = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $chk->bind_param('is', $userId, $pid); $chk->execute(); $exists = $chk->get_result()->num_rows > 0; $chk->close();
        if ($exists) { $u = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?"); $u->bind_param('iis', $qty, $userId, $pid); $u->execute(); $u->close(); }
        else { $i = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)"); $i->bind_param('isi', $userId, $pid, $qty); $i->execute(); $i->close(); }
        $_SESSION['cart'] = [];                                  // keep the session copy in step, like add_to_cart.php does
        foreach (cart_lines($conn, true, $userId) as $l) {
            $img = $conn->prepare("SELECT image FROM products WHERE product_id = ?"); $img->bind_param('s', $l['product_id']); $img->execute(); $ir = $img->get_result()->fetch_assoc(); $img->close();
            $_SESSION['cart'][$l['product_id']] = ['product_name' => $l['product_name'], 'price' => $l['price'], 'image' => $ir['image'] ?? '', 'quantity' => $l['quantity']];
        }
    } else {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
        if (isset($_SESSION['cart'][$pid])) $_SESSION['cart'][$pid]['quantity'] += $qty;
        else $_SESSION['cart'][$pid] = ['product_name' => $p['product_name'], 'price' => (float)$p['price'], 'image' => $p['image'], 'quantity' => $qty];
    }
    $lines = cart_lines($conn, $loggedIn, $userId);
    out(['success' => true, 'subtotal' => cart_total($lines), 'cart_count' => count($lines)]);
}

case 'get_cart_summary': {
    $lines = cart_lines($conn, $loggedIn, $userId);
    if (!$lines) out(['success' => true, 'items' => [], 'total' => 0]);
    out(['success' => true, 'items' => array_map(fn($l) => ['product_name' => $l['product_name'], 'quantity' => $l['quantity'], 'price' => $l['price']], $lines), 'total' => cart_total($lines)]);
}

case 'get_saved_addresses':
    out(['success' => true, 'addresses' => []]);          // stage 2: needs your orders/addresses tables

case 'stage_for_payment_page': {
    $_SESSION['agent_checkout'] = ['shipping_address' => trim($_POST['shipping_address'] ?? ''), 'billing_address' => trim($_POST['billing_address'] ?? ''), 'time' => time()];
    out(['success' => true, 'redirect' => $CHECKOUT]);     // the cart page does the checkout for now
}

case 'check_email_and_send_otp':
    out(['success' => true, 'match_type' => 'none']);      // treated as a new guest: no email code is sent in this version

case 'place_order':
case 'verify_guest_otp':
case 'lookup_order':
case 'send_cancel_otp':
case 'cancel_order':
case 'submit_support_request':
    fail('This part of the chat is being set up again. Please use the cart page to finish your order, or email info@arakutribe.com and we will help.');

default:
    fail('Unknown request.');
}