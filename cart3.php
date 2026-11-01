<?php
/* =====================================================================
   ARAKU TRIBE - cart3.php
   One page for the whole cart: shows it, changes quantities, removes
   items and starts checkout. Replaces cart.php + update_cart.php with
   the SAME logic:
     - logged-in users: DB cart (copied into $_SESSION['cart'])
     - guests: $_SESSION['cart']
     - quantity changes in steps of 100, never below 100
     - checkout: guests -> login page, logged-in -> process_order.php
     - COMBO SETS stay ONE line: stored in the same cart as product_id
       "COMBO:ID1+ID2", so sign-in, quantity buttons and "empty cart after
       payment" work unchanged. At checkout each combo becomes its products.
     - Combo coupon box; never applied to a combo with a product on offer.
   Uses header3.php + footer3.php; its CSS and JS are in this file.
   ===================================================================== */
session_start();
include 'db1.php';

/* ---------- settings ---------- */
$QTY_STEP       = 100;                  // same as update_cart.php
$MIN_QTY        = 100;
$LOGIN_PAGE     = 'coffee_login3.php';  // sign in; verify_user_otp3.php moves the guest cart into the account
$CHECKOUT_PAGE  = 'process_order3.php'; // next step: delivery details
$THIS_PAGE      = 'cart3.php';

// COMBOS. Offers come from the `offers` table and combo coupons from the `coupons` table.
$COMBO_DISCOUNT_PERCENT = 0;    // keep the same as combos3.php

$is_logged_in = isset($_SESSION['user_id']);
$user_id      = $is_logged_in ? $_SESSION['user_id'] : null;
$isAjax       = (($_POST['ajax'] ?? '') === '1');

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("DB Connection failed: " . $conn->connect_error); }

/* live combo coupons from the database: code => % off */
$COMBO_COUPONS = [];
try {
    $cr = $conn->query("SELECT coupon_code, discount_percent FROM coupons WHERE applies_to = 'combo' AND is_active = 1
                        AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at > NOW())");
    if ($cr) while ($c = $cr->fetch_assoc()) $COMBO_COUPONS[strtoupper($c['coupon_code'])] = (float)$c['discount_percent'];
} catch (\Throwable $e) {}
/* every product with its live offer (offers table), cached for this request */
function cart3_products($conn) {
    static $all = null;
    if ($all !== null) return $all;
    $all = [];
    try {
        $res = $conn->query("SELECT p.product_id, p.product_name, p.price, p.image, p.category, p.quantity, o.discount_type, o.discount_value
            FROM products p LEFT JOIN offers o ON o.offer_code = p.offer_code AND o.is_active = 1
             AND (o.starts_at IS NULL OR o.starts_at <= NOW()) AND (o.ends_at IS NULL OR o.ends_at > NOW())");
    } catch (\Throwable $e) { $res = false; }
    if (!$res) $res = $conn->query("SELECT product_id, product_name, price, image, category, quantity FROM products");
    if ($res) while ($r = $res->fetch_assoc()) {
        $r['unit'] = (float)$r['price'];                                    // price actually charged
        if (($r['discount_type'] ?? '') === 'percent' && $r['discount_value'] > 0 && $r['discount_value'] < 100)
            $r['unit'] = round($r['unit'] * (1 - $r['discount_value'] / 100), 2);
        $all[(string)$r['product_id']] = $r;
    }
    return $all;
}

/* ---------------------------------------------------------------------
   0. COMBOS: add a set (from combos3 / index3 / hotdeals3) and the coupon
   --------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['combo_add']) || isset($_POST['combo_coupon']))) {
    header('Content-Type: application/json');
    if (isset($_POST['combo_coupon'])) {                         // apply ('' removes); checked when the cart loads
        $code = strtoupper(trim((string)$_POST['combo_coupon']));
        if ($code === '') { unset($_SESSION['combo_coupon']); echo json_encode(['status' => 'success']); exit; }
        if (!isset($COMBO_COUPONS[$code])) { echo json_encode(['status' => 'error', 'message' => "That coupon code isn't valid."]); exit; }
        $_SESSION['combo_coupon'] = $code;
        echo json_encode(['status' => 'success']); exit;
    }
    $ids = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)$_POST['combo_add'])))));
    $pid = 'COMBO:' . implode('+', $ids);
    $ok  = count($ids) >= 2 && count($ids) <= 4;
    $found = [];
    if ($ok) {                                                   // every product must exist and be in stock
        $st = $conn->prepare("SELECT product_id, category, image FROM products WHERE quantity >= ? AND product_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
        $st->bind_param('i' . str_repeat('s', count($ids)), $MIN_QTY, ...$ids); $st->execute(); $r = $st->get_result();
        while ($row = $r->fetch_assoc()) $found[(string)$row['product_id']] = $row;
        $st->close(); $ok = count($found) === count($ids);
    }
    if (!$ok) { echo json_encode(['status' => 'error', 'message' => 'This combo is not available right now.']); exit; }
    if ($is_logged_in) {
        $col = $conn->query("SHOW COLUMNS FROM cart LIKE 'product_id'");       // make sure a combo id fits
        if ($col && ($c = $col->fetch_assoc()) && preg_match('/char\((\d+)\)/i', $c['Type'], $m) && (int)$m[1] < 80)
            $conn->query("ALTER TABLE cart MODIFY product_id VARCHAR(100) NOT NULL");
        $st = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?");
        $st->bind_param("iis", $QTY_STEP, $user_id, $pid); $st->execute(); $done = $st->affected_rows > 0; $st->close();
        if (!$done) { $st = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)"); $st->bind_param("isi", $user_id, $pid, $MIN_QTY); $st->execute(); $st->close(); }
    } else {
        $q = (int)($_SESSION['cart'][$pid]['quantity'] ?? 0);
        $short = ['bag' => 'Coffee', 'cup' => 'cup', 'mug' => 'mug', 'tshirt' => 'tee'];
        $name  = ucfirst(implode(' + ', array_map(fn($id) => $short[strtolower($found[$id]['category'])] ?? 'item', $ids))) . ' combo';
        $_SESSION['cart'][$pid] = ['product_name' => $name, 'price' => 0, 'image' => $found[$ids[0]]['image'], 'quantity' => $q ? $q + $QTY_STEP : $MIN_QTY];
    }
    echo json_encode(['status' => 'success']); exit;
}

/* ---------------------------------------------------------------------
   1. CART UPDATES (was update_cart.php): increase / decrease / delete
   --------------------------------------------------------------------- */
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['action'])
    && in_array($_POST['action'], ['increase', 'decrease', 'delete'], true)) {
    $product_id = (string)$_POST['product_id'];
    $action     = $_POST['action'];

    if ($is_logged_in) {
        $quantity = null;
        $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("is", $user_id, $product_id);
        $stmt->execute();
        $stmt->bind_result($quantity);
        $stmt->fetch();
        $stmt->close();

        if ($quantity !== null) {
            $new_quantity = null;
            if ($action === 'increase') {
                $new_quantity = $quantity + $QTY_STEP;
            } elseif ($action === 'decrease' && $quantity > $QTY_STEP) {
                $new_quantity = $quantity - $QTY_STEP;
            } elseif ($action === 'delete') {
                $del = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                $del->bind_param("is", $user_id, $product_id);
                $del->execute();
                $del->close();
                $flash = 'Item removed from your cart.';
            }
            if ($new_quantity !== null) {
                $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                $upd->bind_param("iis", $new_quantity, $user_id, $product_id);
                $upd->execute();
                $upd->close();
            }
        }
    } else {
        if (isset($_SESSION['cart'][$product_id])) {
            $current = (int)$_SESSION['cart'][$product_id]['quantity'];
            if ($action === 'increase') {
                $_SESSION['cart'][$product_id]['quantity'] = $current + $QTY_STEP;
            } elseif ($action === 'decrease' && $current > $QTY_STEP) {
                $_SESSION['cart'][$product_id]['quantity'] = $current - $QTY_STEP;
            } elseif ($action === 'delete') {
                unset($_SESSION['cart'][$product_id]);
                $flash = 'Item removed from your cart.';
            }
        }
    }
    if (!$isAjax) {                       // normal form post: back to the cart (no resubmit on refresh)
        if ($flash) $_SESSION['cart3_flash'] = $flash;
        $conn->close();
        header("Location: $THIS_PAGE");
        exit;
    }
}

/* ---------------------------------------------------------------------
   2. LOAD THE CART (was cart.php)
   --------------------------------------------------------------------- */
function cart3_load($conn, $is_logged_in, $user_id) {
    $items = [];
    if ($is_logged_in) {
        $stmt = $conn->prepare("SELECT c.product_id, c.quantity, p.product_name, p.price, p.image
                                FROM cart c JOIN products p ON c.product_id = p.product_id WHERE c.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[(string)$row['product_id']] = [
                'product_name' => $row['product_name'],
                'price'        => (float)$row['price'],
                'image'        => $row['image'],
                'quantity'     => (int)$row['quantity'],
            ];
        }
        $stmt->close();
        $stmt = $conn->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ? AND product_id LIKE 'COMBO:%'");
        $stmt->bind_param("i", $user_id); $stmt->execute(); $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $items[(string)$row['product_id']] = ['quantity' => (int)$row['quantity']];
        $stmt->close();
    } else {
        $items = $_SESSION['cart'] ?? [];
        foreach ($items as $id => $item) {
            $items[$id]['price']    = (float)($item['price'] ?? 0);
            $items[$id]['quantity'] = (int)($item['quantity'] ?? 0);
        }
    }
    $prod = cart3_products($conn);
    foreach ($items as $id => $it)                               // single products: charge the live offer price
        if (strpos($id, 'COMBO:') !== 0 && isset($prod[$id])) $items[$id]['price'] = $prod[$id]['unit'];
    $items = cart3_price_combos($conn, $items);
    if ($is_logged_in) $_SESSION['cart'] = $items;   // keep the session in sync for the header / other pages
    return $items;
}
/* Combo lines: name, set price (after offers, combo discount and coupon) and the products inside */
function cart3_price_combos($conn, $items) {
    global $COMBO_COUPONS, $COMBO_DISCOUNT_PERCENT;
    $keys = array_filter(array_keys($items), fn($k) => strpos($k, 'COMBO:') === 0);
    if (!$keys) return $items;
    $prod = cart3_products($conn);
    $code = $_SESSION['combo_coupon'] ?? '';
    $pct  = $COMBO_COUPONS[$code] ?? 0;
    $short = ['bag' => 'Coffee', 'cup' => 'cup', 'mug' => 'mug', 'tshirt' => 'tee'];
    foreach ($keys as $k) {
        $lines = []; $sum = 0; $onOffer = []; $names = [];
        foreach (explode('+', substr($k, 6)) as $id) {
            if (!isset($prod[$id])) { unset($items[$k]); continue 2; }       // product gone: drop the combo
            $p = $prod[$id]; $unit = $p['unit']; $o = !empty($p['discount_type']);
            if ($o) $onOffer[] = $p['product_name'];
            $lines[] = ['id' => $id, 'name' => $p['product_name'], 'unit' => $unit, 'image' => $p['image'], 'offer' => $o];
            $names[] = $short[strtolower($p['category'])] ?? $p['category'];
            $sum += $unit;
        }
        $set = round($sum * (1 - $COMBO_DISCOUNT_PERCENT / 100), 2);
        $off = ($pct && !$onOffer) ? round($set * $pct / 100, 2) : 0;
        $items[$k] = ['product_name' => ucfirst(implode(' + ', $names)) . ' combo', 'price' => $set - $off, 'image' => $lines[0]['image'],
                      'quantity' => (int)$items[$k]['quantity'], 'combo' => ['lines' => $lines, 'set' => $set, 'off' => $off, 'onOffer' => $onOffer]];
    }
    return $items;
}
function cart3_category($image) {         // same image-prefix rule as cart.php
    if (strpos($image, 'ATCUP') === 0) return 'cup';
    if (strpos($image, 'ATMUG') === 0) return 'mug';
    if (strpos($image, 'ATBAG') === 0) return 'bag';
    if (strpos($image, 'ATTSH') === 0) return 'tshirt';
    return 'default';
}
function cart3_image($image) {
    $cat  = cart3_category($image);
    $path = "img/shop/{$cat}/{$image}";
    if (file_exists($path)) return $path;
    if (file_exists("img/shop/{$cat}/default.png")) return "img/shop/{$cat}/default.png";
    return 'img/default.png';
}
function cart3_total($items) { $t = 0; foreach ($items as $it) $t += $it['price'] * $it['quantity']; return $t; }

$cart_items = cart3_load($conn, $is_logged_in, $user_id);
$total      = cart3_total($cart_items);

// combo coupon: how much it saved, or why it can't be used
$couponCode = $_SESSION['combo_coupon'] ?? ''; $couponOff = 0; $couponMsg = ''; $hasCombo = false; $blocked = [];
foreach ($cart_items as $it) if (!empty($it['combo'])) {
    $hasCombo = true; $couponOff += $it['combo']['off'] * $it['quantity'];
    if ($it['combo']['onOffer']) $blocked[] = $it['product_name'];
}
if ($couponCode !== '' && $hasCombo && !$couponOff) $couponMsg = "Coupon $couponCode can't be used: every combo in your cart has a product that's already on offer.";
elseif ($couponOff && $blocked) $couponMsg = 'Not applied to ' . implode(', ', $blocked) . ' (a product in it is already on offer).';

require_once 'currency_helper.php';
$displayCurrency = resolve_display_currency($conn);
$currencyInfo    = $CURRENCY_INFO[$displayCurrency];

/* ---------------------------------------------------------------------
   3. CHECKOUT (same as cart.php)
   --------------------------------------------------------------------- */
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed_to_checkout'])) {
    if (!empty($cart_items) && $total > 0) {
        if (!$is_logged_in) {
            $_SESSION['redirect_after_login'] = $THIS_PAGE;
            $conn->close();
            header("Location: $LOGIN_PAGE");
            exit;
        }
        $orderItems = [];                         // keep each product's ID so order_items records what was bought
        foreach ($cart_items as $pid => $it) {
            if (empty($it['combo'])) { $orderItems[] = ['product_id' => (string)$pid] + $it; continue; }
            $c = $it['combo']; $ratio = $c['set'] > 0 ? $it['price'] / array_sum(array_column($c['lines'], 'unit')) : 1; $left = $it['price'];
            foreach ($c['lines'] as $i => $l) {                     // each product at its share of the combo price
                $unit = $i === count($c['lines']) - 1 ? round($left, 2) : round($l['unit'] * $ratio, 2); $left -= $unit;
                $orderItems[] = ['product_id' => $l['id'], 'product_name' => $l['name'] . ' (' . $it['product_name'] . ')', 'price' => $unit, 'image' => $l['image'], 'quantity' => $it['quantity']];
            }
        }
        unset($_SESSION['combo_coupon']);
        $_SESSION['order_data'] = [
            'items'        => $orderItems,
            'total'        => $total,
            'user_id'      => $user_id,
            'shipping_fee' => 0,
        ];
        unset($_SESSION['shipping_details']);
        unset($_SESSION['cart']);
        $conn->close();
        header("Location: $CHECKOUT_PAGE");
        exit;
    }
    $error_msg = 'Your cart is empty. Please add items first.';
}

$CAT_PAGES = ['bag' => 'coffee_bag3.php', 'cup' => 'coffee_cup3.php', 'mug' => 'coffee_mug3.php', 'tshirt' => 'coffee_tshirt3.php'];
$CAT_LABEL = ['bag' => 'Coffee Bags', 'cup' => 'Coffee Cups', 'mug' => 'Coffee Mugs', 'tshirt' => 'T-Shirts', 'default' => 'Araku Tribe'];

/* ---------- suggestions: a few in-stock products not already in the cart ---------- */
$suggest = [];
$res = $conn->query("SELECT product_id, product_name, price, image, category, quantity FROM products WHERE quantity > 0");
if ($res) {
    $byCat = [];
    while ($row = $res->fetch_assoc()) {
        if (empty($row['image']) || isset($cart_items[$row['product_id']])) continue;
        if (!preg_match('/^' . preg_quote($row['product_id'], '/') . '\.(jpg|jpeg|png|gif|webp)$/i', $row['image'])) continue;
        $byCat[strtolower($row['category'])][] = $row;
    }
    $inCart = array_unique(array_map(fn($it) => cart3_category($it['image'] ?? ''), $cart_items));
    uksort($byCat, fn($a, $b) => (in_array($a, $inCart, true) <=> in_array($b, $inCart, true)));   // other ranges first
    while (count($suggest) < 4 && $byCat) {
        foreach ($byCat as $c => &$rows) {
            if ($rows) $suggest[] = array_shift($rows);
            if (!$rows) unset($byCat[$c]);
            if (count($suggest) >= 4) break;
        }
        unset($rows);
    }
}

$suggestData = array_map(fn($r) => [
    'id' => (string)$r['product_id'], 'name' => (string)$r['product_name'], 'cat' => strtolower($r['category']),
    'label' => $CAT_LABEL[strtolower($r['category'])] ?? '', 'img' => "img/shop/{$r['category']}/{$r['image']}",
    'price' => round((float)$r['price'] * $currencyInfo['rate'], 2), 'stock' => (int)$r['quantity'],
], $suggest);

$wishlistIds = [];
if ($is_logged_in) {
    $wl = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    if ($wl) { $wl->bind_param("i", $user_id); $wl->execute(); $r = $wl->get_result(); while ($row = $r->fetch_assoc()) $wishlistIds[] = (string)$row['product_id']; $wl->close(); }
}
$conn->close();

/* ---------------------------------------------------------------------
   4. HTML PARTS (used for the page and for instant updates)
   --------------------------------------------------------------------- */
function cart3_money($usd, $ci) { return htmlspecialchars($ci['symbol']) . number_format($usd * $ci['rate'], 2); }

function cart3_items_html($items, $ci, $step, $min, $pages, $labels) {
    ob_start();
    foreach ($items as $id => $item):
        $cat  = cart3_category($item['image'] ?? '');
        $img  = cart3_image($item['image'] ?? '');
        $sub  = $item['price'] * $item['quantity'];
        $link = isset($pages[$cat]) ? $pages[$cat] . '#product-' . rawurlencode($id) : '#';
        $e    = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    ?>
    <article class="line" data-id="<?= $e($id) ?>" data-cat="<?= $e($cat) ?>">
      <?php if (!empty($item['combo'])): ?>
      <span class="line-img combo-pics"><?php foreach ($item['combo']['lines'] as $l): ?><img src="<?= $e(cart3_image($l['image'])) ?>" alt=""><?php endforeach; ?></span>
      <?php else: ?>
      <a class="line-img" href="<?= $e($link) ?>"><img src="<?= $e($img) ?>" alt="<?= $e($item['product_name']) ?>"></a>
      <?php endif; ?>
      <div class="line-info">
        <?php if ($c = $item['combo'] ?? null): ?>
        <p class="line-cat"><span class="combo-tag">Combo set</span></p>
        <h3><?= $e($item['product_name']) ?></h3>
        <ul class="combo-items"><?php foreach ($c['lines'] as $l): ?><li><?= $e($l['name']) ?> · <?= cart3_money($l['unit'], $ci) ?><?= $l['offer'] ? ' <em>On offer</em>' : '' ?></li><?php endforeach; ?></ul>
        <p class="line-unit"><?= cart3_money($item['price'], $ci) ?> <span>per set</span><?= $c['off'] ? ' <s>' . cart3_money($c['set'], $ci) . '</s> <b class="cp-ok">Coupon applied</b>' : '' ?></p>
        <?php if ($c['onOffer']): ?><p class="cp-no">No coupon on this combo: <?= $e(implode(', ', $c['onOffer'])) ?> is already on offer.</p><?php endif; ?>
        <?php else: ?>
        <p class="line-cat"><?= $e($labels[$cat] ?? '') ?></p>
        <h3><a href="<?= $e($link) ?>"><?= $e($item['product_name']) ?></a></h3>
        <p class="line-id">ID: <?= $e($id) ?></p>
        <p class="line-unit"><?= cart3_money($item['price'], $ci) ?> <span>per unit</span></p>
        <?php endif; ?>
      </div>
      <div class="line-qty">
        <form method="post" action="cart3.php" class="cart-form stepper" aria-label="Quantity for <?= $e($item['product_name']) ?>">
          <input type="hidden" name="product_id" value="<?= $e($id) ?>">
          <button type="submit" name="action" value="decrease" <?= $item['quantity'] <= $min ? 'disabled' : '' ?> aria-label="Remove <?= (int)$step ?> units">−</button>
          <output><?= (int)$item['quantity'] ?></output>
          <button type="submit" name="action" value="increase" aria-label="Add <?= (int)$step ?> units">+</button>
        </form>
        <small><?= !empty($item['combo']) ? 'sets (units of each item)' : 'units' ?>, in steps of <?= (int)$step ?></small>
      </div>
      <div class="line-sub">
        <strong><?= cart3_money($sub, $ci) ?></strong>
        <div class="line-actions">
          <?php if (empty($item['combo'])): ?><button type="button" class="line-save" data-save="<?= $e($id) ?>">Move to wishlist</button><?php endif; ?>
          <form method="post" action="cart3.php" class="cart-form">
            <input type="hidden" name="product_id" value="<?= $e($id) ?>">
            <button type="submit" name="action" value="delete" class="line-remove">Remove</button>
          </form>
        </div>
      </div>
    </article>
    <?php endforeach;
    return ob_get_clean();
}
function cart3_summary_html($items, $total, $ci, $logged) {
    global $couponCode, $couponOff, $couponMsg, $hasCombo;
    $units = array_sum(array_map(fn($i) => (int)$i['quantity'] * (isset($i['combo']) ? count($i['combo']['lines']) : 1), $items));
    ob_start(); ?>
    <dl class="sum-rows">
      <div><dt>Products</dt><dd><?= count($items) ?></dd></div>
      <div><dt>Total units</dt><dd><?= number_format($units) ?></dd></div>
      <div><dt>Subtotal</dt><dd><?= cart3_money($total + $couponOff, $ci) ?></dd></div>
      <?php if ($couponOff): ?><div class="disc"><dt>Coupon <?= htmlspecialchars($couponCode) ?></dt><dd>−<?= cart3_money($couponOff, $ci) ?></dd></div><?php endif; ?>
      <div><dt>Delivery</dt><dd class="muted">Confirmed at the next step</dd></div>
    </dl>
    <div class="sum-total"><span>Total</span><strong><?= cart3_money($total, $ci) ?></strong></div>
    <?php if ($hasCombo): ?>
    <div class="coupon-box">
      <?php if ($couponOff): ?>
        <p class="cp-ok">✓ <?= htmlspecialchars($couponCode) ?> applied <button type="button" data-coupon="">Remove</button></p>
      <?php else: ?>
        <label for="cpCode">Have a combo coupon?</label>
        <div class="cp-row"><input id="cpCode" autocomplete="off" placeholder="Enter code"><button type="button" class="btn btn-outline" data-coupon="apply">Apply</button></div>
      <?php endif; ?>
      <p class="cp-msg" id="cpMsg"><?= htmlspecialchars($couponMsg) ?></p>
    </div>
    <?php endif; ?>
    <?php if (!$logged): ?><p class="sum-note">You'll sign in or create an account before paying. Your cart stays saved.</p><?php endif; ?>
    <?php return ob_get_clean();
}

$itemsHtml   = cart3_items_html($cart_items, $currencyInfo, $QTY_STEP, $MIN_QTY, $CAT_PAGES, $CAT_LABEL);
$summaryHtml = cart3_summary_html($cart_items, $total, $currencyInfo, $is_logged_in);

/* ---------- instant updates: send back the refreshed parts ---------- */
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'status'  => 'success',
        'message' => $flash,
        'count'   => count($cart_items),
        'empty'   => empty($cart_items),
        'items'   => $itemsHtml,
        'summary' => $summaryHtml,
        'total'   => cart3_money($total, $currencyInfo),
    ]);
    exit;
}

$flash = $_SESSION['cart3_flash'] ?? '';
unset($_SESSION['cart3_flash']);

$PAGE_TITLE = 'Your cart - Araku Tribe';
$ACTIVE_NAV = '';
$H3_HIDE_BOT = true;   // no floating robot on the cart: it covers the coupon box
$TAB_STYLE = 'brown';   // brown header tabs, same as the About / Products / Profile pages
include __DIR__ . '/header3.php';
?>
<style>
/* ============ cart3.php ============ */
.cart-hero{background:var(--cream);padding:28px 0 34px}
.cart-hero .row{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap}
.cart-hero h1{font-size:clamp(2rem,3.6vw,3rem)}
.cart-hero h1 small{font-family:var(--sans);font-size:1rem;font-weight:600;color:var(--muted);margin-left:10px}
.steps{display:flex;align-items:center;gap:10px;list-style:none;margin:0;padding:0;font-size:14px;font-weight:600;color:var(--muted)}
.steps li{display:flex;align-items:center;gap:8px}
.steps li b{width:26px;height:26px;border-radius:50%;border:1.5px solid var(--line);background:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:12.5px}
.steps li.on{color:var(--brown)}
.steps li.on b{background:var(--brown);border-color:var(--brown);color:#fff}
.steps .bar{width:28px;height:2px;background:var(--line);border-radius:2px}
.cart-wrap{display:grid;grid-template-columns:minmax(0,1fr) 370px;gap:32px;align-items:start;padding-top:32px;padding-bottom:40px}
.flash,.cart-error{display:flex;align-items:center;gap:10px;margin:0 0 16px;padding:12px 16px;border-radius:14px;font-weight:600;font-size:14.5px}
.flash{background:var(--green-soft);color:var(--green)}
.cart-error{background:#FBE4E4;color:#9B2626}
.lines{display:grid;gap:14px}
.line{display:grid;grid-template-columns:110px minmax(0,1fr) auto auto;gap:20px;align-items:center;padding:14px;background:#fff;border:1px solid var(--line);border-radius:22px;transition:opacity .2s,box-shadow .2s}
.line:hover{box-shadow:0 14px 30px -24px rgba(59,35,23,.6)}
.line.busy{opacity:.55;pointer-events:none}
.line-img{display:block;aspect-ratio:1/1;border-radius:16px;overflow:hidden;background:#F3E9DD}
.line[data-cat="mug"] .line-img{background:#E2ECE5}.line[data-cat="cup"] .line-img{background:#F6EEE3}.line[data-cat="tshirt"] .line-img{background:#EDE5DA}
.line-img img{width:100%;height:100%;object-fit:contain;padding:10%;mix-blend-mode:multiply}
.line-cat{margin:0 0 3px;font-size:12.5px;font-weight:600;color:var(--muted)}
.line h3{font-size:1.15rem;line-height:1.25}
.line h3 a{text-decoration:none}
.line h3 a:hover{color:var(--orange-cta)}
.line-id{margin:3px 0 0;font-size:12px;color:var(--muted)}
.line-unit{margin:8px 0 0;font-weight:700;font-size:15px}
.line-unit span{font-weight:500;font-size:13px;color:var(--muted)}
.line-qty{display:flex;flex-direction:column;align-items:center;gap:6px}
.line-qty small{font-size:12px;color:var(--muted);white-space:nowrap}
.stepper{display:inline-flex;align-items:center;border:1.5px solid var(--line);border-radius:999px;background:#fff}
.stepper button{width:40px;height:40px;border:0;background:none;font-size:19px;color:var(--brown);border-radius:999px}
.stepper button:hover:not(:disabled){background:var(--cream)}
.stepper button:disabled{color:var(--line);cursor:not-allowed}
.stepper output{min-width:58px;text-align:center;font-weight:700}
.line-sub{text-align:right;min-width:130px}
.line-sub strong{font-size:1.15rem}
.line-actions{display:flex;justify-content:flex-end;gap:14px;margin-top:10px}
.line-actions form{margin:0}
.line-save,.line-remove{border:0;background:none;padding:0;font-size:13.5px;font-weight:600;color:var(--muted);text-decoration:underline;text-underline-offset:3px}
.line-save:hover{color:var(--brown)}
.line-remove:hover{color:var(--red)}
.cart-links{display:flex;flex-wrap:wrap;gap:12px;margin-top:22px}
.summary{position:sticky;top:96px;background:#fff;border:1px solid var(--line);border-radius:24px;padding:22px}
.summary h2{font-size:1.4rem;margin-bottom:12px}
.sum-rows{margin:0;display:grid;gap:10px}
.sum-rows div{display:flex;justify-content:space-between;gap:12px;font-size:14.5px}
.sum-rows dt{color:var(--muted)}
.sum-rows dd{margin:0;font-weight:600}
.sum-rows dd.muted{font-weight:500;color:var(--muted);text-align:right}
.sum-total{display:flex;justify-content:space-between;align-items:baseline;margin:16px 0 0;padding-top:16px;border-top:1px dashed var(--line)}
.sum-total span{font-weight:700}
.sum-total strong{font-size:1.6rem;color:var(--brown-deep)}
.sum-note{margin:10px 0 0;font-size:13px;color:var(--muted)}
.summary form{margin:16px 0 0}
.summary .btn-primary{width:100%;padding:16px;font-size:16px}
.or{display:flex;align-items:center;gap:10px;margin:16px 0;font-size:12.5px;color:var(--muted)}
.or::before,.or::after{content:"";flex:1;height:1px;background:var(--line)}
.agent-pay{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:16px;text-decoration:none;color:#fff;background:linear-gradient(135deg,#3B2317,#6B3E26)}
.agent-pay strong{display:block;font-size:14.5px}
.agent-pay small{display:block;font-size:12.5px;color:#F4D9B8}
.trust{list-style:none;margin:16px 0 0;padding:0;display:grid;gap:8px;font-size:13px;color:var(--muted)}
.trust li{display:flex;align-items:center;gap:8px}
.trust .ico{width:17px;height:17px;color:var(--green)}
/* combo sets + coupon */
.combo-pics{display:flex;align-items:center;justify-content:center}
.combo-pics img{width:52px;height:52px;border-radius:12px;border:2px solid #fff;background:var(--cream);object-fit:contain;padding:3px;mix-blend-mode:multiply}
.combo-pics img+img{margin-left:-20px}
.combo-tag{padding:2px 9px;border-radius:999px;background:var(--brown-deep);color:#fff;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.combo-items{list-style:none;margin:4px 0;padding:0;font-size:13.5px;color:var(--muted)}
.combo-items em{font-style:normal;font-size:11px;font-weight:700;color:#C40F17}
.line-unit s{color:var(--muted)}
.cp-ok{color:#1F7A4D;font-weight:700}
.cp-no{margin:4px 0 0;font-size:13px;font-weight:600;color:#B4410F}
.coupon-box{margin:14px 0 6px;padding:12px;border:1.5px dashed #D9B48F;border-radius:14px;background:#FFF8F1}
.coupon-box label{display:block;font-weight:700;font-size:14px;margin-bottom:6px}
.cp-row{display:flex;gap:8px}
.cp-row input{flex:1;min-width:0;height:42px;padding:0 12px;border:1.5px solid var(--line);border-radius:12px;font:600 15px var(--sans);text-transform:uppercase}
.cp-row .btn{height:42px;padding:0 14px}
.cp-ok button{border:0;background:none;color:var(--muted);text-decoration:underline;cursor:pointer}
.cp-msg{margin:6px 0 0;font-size:13px;color:#B4410F}
.cp-msg:empty{display:none}
.sum-rows .disc dt,.sum-rows .disc dd{color:#1F7A4D;font-weight:700}
.empty-cart{grid-column:1/-1;text-align:center;padding:56px 20px;background:#fff;border:1px solid var(--line);border-radius:24px}
.empty-cart .big{width:76px;height:76px;margin:0 auto 16px;border-radius:24px;display:flex;align-items:center;justify-content:center;background:var(--cream);color:var(--brown)}
.empty-cart .big .ico{width:34px;height:34px}
.empty-cart h2{font-size:1.8rem}
.empty-cart p{color:var(--muted);margin:8px 0 20px}
.empty-cart .acts{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}
.suggest-sec{padding:20px 0 88px}
.suggest-sec h2{font-size:1.6rem;margin-bottom:16px}
.sugg{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
.sg{display:flex;flex-direction:column;background:#fff;border:1px solid var(--line);border-radius:20px;padding:10px}
.sg .im{aspect-ratio:1/1;border-radius:14px;background:#F3E9DD;overflow:hidden}
.sg[data-cat="mug"] .im{background:#E2ECE5}.sg[data-cat="cup"] .im{background:#F6EEE3}.sg[data-cat="tshirt"] .im{background:#EDE5DA}
.sg img{width:100%;height:100%;object-fit:contain;padding:10%;mix-blend-mode:multiply}
.sg h3{font-size:1rem;margin:10px 4px 0;line-height:1.3}
.sg .sid{margin:3px 4px 0;font-size:11.5px;letter-spacing:.02em;color:var(--muted);font-weight:500}
.sg h3 a{text-decoration:none}
.sg p.sp{margin:6px 4px 10px;font-weight:700;font-size:14.5px}
.sg p.sp span{font-weight:500;font-size:12.5px;color:var(--muted)}
.sg button{margin-top:auto;border:0;border-radius:999px;padding:10px;background:var(--brown-deep);color:var(--cream);font-weight:700;font-size:14px}
.sg button:hover{background:var(--orange-cta);color:#fff}
.sg button:disabled{opacity:.6}

/* ============ quick view (same look as the shop pages) ============ */
dialog.mqv{border:0;border-radius:var(--r-lg);padding:0;width:min(820px,94vw);max-height:92vh;box-shadow:0 30px 80px -20px rgba(36,24,17,.5)}
dialog.mqv::backdrop{background:rgba(36,24,17,.5)}
.mqv-in{display:grid;grid-template-columns:1fr 1fr}
.mqv-img{background:#F3E9DD;aspect-ratio:1/1}
.mqv-img[data-cat="mug"]{background:#E2ECE5}.mqv-img[data-cat="cup"]{background:#F6EEE3}.mqv-img[data-cat="tshirt"]{background:#EDE5DA}
.mqv-img img{width:100%;height:100%;object-fit:contain;padding:10%;mix-blend-mode:multiply}
.mqv-info{position:relative;padding:34px 30px;display:flex;flex-direction:column;gap:6px}
.mqv-close{position:absolute;top:12px;right:12px}
.mqv-cat{margin:0;font-size:13px;font-weight:600;color:var(--muted)}
.mqv-info h2{font-size:1.7rem;padding-right:36px}
.mqv-id{margin:0;font-size:13px;letter-spacing:.02em;color:var(--muted)}
.mqv-price{margin:8px 0 0;font-weight:700;font-size:1.4rem}
.mqv-price span{font-weight:500;font-size:13.5px;color:var(--muted)}
.mqv-stock{margin:0;font-size:14px;font-weight:600;color:var(--green)}
.mqv-stock.low{color:var(--red)}
.mqv-step{display:inline-flex;align-items:center;border:1.5px solid var(--line);border-radius:999px;margin:14px 0 6px;width:max-content}
.mqv-step button{width:44px;height:44px;border:0;background:none;font-size:20px;color:var(--brown)}
.mqv-step button:disabled{color:var(--line);cursor:not-allowed}
.mqv-step output{min-width:70px;text-align:center;font-weight:700}
.mqv-note{margin:0;font-size:12.5px;color:var(--muted)}
.mqv-info .btn{margin-top:10px}
@media (max-width:760px){ .mqv-in{grid-template-columns:1fr} .mqv-img{aspect-ratio:4/3} .mqv-info{padding:24px 20px} }
.mobile-pay{display:none}
@media (max-width:1060px){
  .cart-wrap{grid-template-columns:1fr}
  .summary{position:static}
}
@media (max-width:760px){
  .line{grid-template-columns:84px minmax(0,1fr);gap:12px 14px;padding:12px}
  .line-qty{grid-column:1 / span 2;flex-direction:row;justify-content:space-between}
  .line-sub{grid-column:1 / span 2;display:flex;align-items:center;justify-content:space-between;text-align:left;min-width:0}
  .line-actions{margin-top:0}
  .sugg{grid-template-columns:repeat(2,minmax(0,1fr))}
  .steps .lbl{display:none}
  .mobile-pay{position:sticky;bottom:0;z-index:40;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px calc(12px + env(safe-area-inset-bottom));background:#fff;border-top:1px solid var(--line);box-shadow:0 -10px 24px -18px rgba(59,35,23,.5)}
  .mobile-pay small{display:block;font-size:12px;color:var(--muted)}
  .mobile-pay strong{font-size:1.2rem}
  .mobile-pay .btn{padding:13px 20px}
}
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
</svg>

<main data-tab-style="brown">
  <section class="cart-hero">
    <div class="wrap">
      <div class="pg-crumb-row"><nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>">Home</a><span class="sep" aria-hidden="true">/</span><span class="cur" aria-current="page">Cart</span></nav><a class="close-btn" id="pgClose" href="<?= h3_link('home') ?>" onclick="try{if(document.referrer&&new URL(document.referrer).origin===location.origin&&history.length>1){event.preventDefault();event.stopPropagation();history.back();}}catch(e){}"><svg class="ico" aria-hidden="true" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>Back to previous page</a></div>
      <div class="row">
        <h1>Your cart <small id="heroCount"><?= count($cart_items) ? count($cart_items) . (count($cart_items) === 1 ? ' product' : ' products') : '' ?></small></h1>
        <ol class="steps" aria-label="Checkout steps">
          <li class="on"><b>1</b><span class="lbl">Cart</span></li><li class="bar" aria-hidden="true"></li>
          <li><b>2</b><span class="lbl">Details</span></li><li class="bar" aria-hidden="true"></li>
          <li><b>3</b><span class="lbl">Payment</span></li>
        </ol>
      </div>
    </div>
  </section>

  <div class="wrap cart-wrap" id="cartWrap">
    <?php if (!empty($cart_items)): ?>
      <section aria-label="Items in your cart">
        <div id="flash"><?php if ($flash): ?><p class="flash" role="status"><?= h3_e($flash) ?></p><?php endif; ?></div>
        <?php if ($error_msg): ?><p class="cart-error" role="alert"><?= h3_e($error_msg) ?></p><?php endif; ?>
        <div class="lines" id="lines"><?= $itemsHtml ?></div>
        <div class="cart-links">
          <a class="btn btn-outline" href="<?= h3_link('shop') ?>">Continue shopping</a>
          <a class="btn btn-outline" href="<?= h3_link('combos') ?>#build">Build a combo</a>
        </div>
      </section>

      <aside class="summary" aria-label="Order summary">
        <h2>Order summary</h2>
        <div id="summary"><?= $summaryHtml ?></div>
        <form method="POST" action="cart3.php">
          <button type="submit" name="proceed_to_checkout" value="1" class="btn btn-primary">Proceed to checkout</button>
        </form>
        <div class="or">or</div>
        <a class="agent-pay" href="<?= h3_link('agent') ?>"><span class="orb"><svg class="ico"><use href="#i-spark"/></svg></span>
          <span><strong>Express checkout with AI Agent</strong><small>Address and payment in one chat</small></span></a>
        <ul class="trust">
          <li><svg class="ico"><use href="#i-shield"/></svg>Secure checkout</li>
          <li><svg class="ico"><use href="#i-leaf"/></svg>Packed fresh in the Araku Valley</li>
          <li><svg class="ico"><use href="#i-box"/></svg>Minimum <?= (int)$MIN_QTY ?> units per product</li>
        </ul>
      </aside>
    <?php else: ?>
      <div class="empty-cart">
        <?php if ($error_msg): ?><p class="cart-error" role="alert" style="justify-content:center"><?= h3_e($error_msg) ?></p><?php endif; ?>
        <div class="big"><svg class="ico"><use href="#i-bag"/></svg></div>
        <h2>Your cart is empty</h2>
        <p>Start with a bag of Araku Valley coffee, or let the AI Agent put an order together for you.</p>
        <div class="acts">
          <a class="btn btn-primary" href="<?= h3_link('shop') ?>">Shop the collection</a>
          <a class="btn btn-outline" href="<?= h3_link('agent') ?>">Order with AI Agent</a>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($suggest): ?>
  <section class="suggest-sec" aria-labelledby="sgTitle">
    <div class="wrap">
      <h2 id="sgTitle"><?= $cart_items ? 'Add something to go with it' : 'Popular picks to start with' ?></h2>
      <div class="sugg">
        <?php foreach ($suggest as $s): $c = strtolower($s['category']); $link = ($CAT_PAGES[$c] ?? '#') . '#product-' . rawurlencode($s['product_id']); ?>
          <article class="sg" data-cat="<?= h3_e($c) ?>">
            <a class="im" href="<?= h3_e($link) ?>" data-qv="<?= h3_e($s['product_id']) ?>"><img src="<?= h3_e("img/shop/{$s['category']}/{$s['image']}") ?>" alt="<?= h3_e($s['product_name']) ?>" loading="lazy"></a>
            <h3><a href="<?= h3_e($link) ?>" data-qv="<?= h3_e($s['product_id']) ?>"><?= h3_e($s['product_name']) ?></a></h3>
            <p class="sid">ID: <?= h3_e($s['product_id']) ?></p>
            <p class="sp"><?= cart3_money($s['price'], $currencyInfo) ?> <span>per unit</span></p>
            <button type="button" data-add="<?= h3_e($s['product_id']) ?>">Add <?= (int)$MIN_QTY ?> units</button>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($cart_items)): ?>
  <div class="mobile-pay" id="mobilePay">
    <div><small>Total</small><strong id="mobileTotal"><?= cart3_money($total, $currencyInfo) ?></strong></div>
    <form method="POST" action="cart3.php"><button type="submit" name="proceed_to_checkout" value="1" class="btn btn-primary">Checkout</button></form>
  </div>
  <?php endif; ?>
</main>

<dialog class="mqv" id="mqv" aria-labelledby="mqvTitle">
  <div class="mqv-in">
    <div class="mqv-img" id="mqvImgBox"><img id="mqvImg" alt=""></div>
    <div class="mqv-info">
      <button type="button" class="icon-btn mqv-close" id="mqvClose" aria-label="Close quick view"><svg class="ico"><use href="#i-x"/></svg></button>
      <p class="mqv-cat" id="mqvCat"></p>
      <h2 id="mqvTitle"></h2>
      <p class="mqv-id" id="mqvId"></p>
      <p class="mqv-price" id="mqvPrice"></p>
      <p class="mqv-stock" id="mqvStock"></p>
      <div class="mqv-step" role="group" aria-label="Quantity">
        <button type="button" id="mqvMinus" aria-label="Decrease quantity">−</button>
        <output id="mqvQty" aria-live="polite"></output>
        <button type="button" id="mqvPlus" aria-label="Increase quantity">+</button>
      </div>
      <p class="mqv-note" id="mqvNote"></p>
      <button type="button" class="btn btn-primary" id="mqvAdd">Add to cart</button>
    </div>
  </div>
</dialog>

<script>
(function () {
'use strict';
const LOGGED = <?= $is_logged_in ? 'true' : 'false' ?>;
const WISH = new Set(<?= json_encode($wishlistIds) ?>);
const SUGGEST = <?= json_encode($suggestData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
const SYMBOL = <?= json_encode($currencyInfo['symbol']) ?>;
const $ = (s, el = document) => el.querySelector(s);

/* quantity / remove: update instantly instead of reloading the page */
document.addEventListener('submit', async e => {
  const form = e.target.closest('.cart-form'); if (!form) return;
  e.preventDefault();
  const fd = new FormData(form);
  if (e.submitter && e.submitter.name) fd.set(e.submitter.name, e.submitter.value);
  fd.set('ajax', '1');
  const line = form.closest('.line'); if (line) line.classList.add('busy');
  try {
    const res = await fetch('cart3.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (data.empty) { location.reload(); return; }          // show the empty-cart view
    $('#lines').innerHTML = data.items;
    $('#summary').innerHTML = data.summary;
    if ($('#mobileTotal')) $('#mobileTotal').innerHTML = data.total;
    $('#heroCount').textContent = data.count + (data.count === 1 ? ' product' : ' products');
    $('#flash').innerHTML = data.message ? `<p class="flash" role="status">${data.message}</p>` : '';
    if (window.A3) A3.setCartCount(data.count);
  } catch (err) {
    form.submit();                                         // fall back to a normal post
  }
});

/* combo coupon: apply / remove, then reload with the new prices */
document.addEventListener('click', async e => {
  const b = e.target.closest('[data-coupon]'); if (!b) return;
  const code = b.dataset.coupon === 'apply' ? ($('#cpCode').value || '').trim() : '';
  if (b.dataset.coupon === 'apply' && !code) { $('#cpMsg').textContent = 'Type your coupon code first.'; return; }
  b.disabled = true;
  const d = await fetch('cart3.php', { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ combo_coupon: code }) }).then(r => r.json()).catch(() => ({}));
  if (d.status !== 'success') { $('#cpMsg').textContent = d.message || 'Please try again.'; b.disabled = false; return; }
  location.reload();
});
document.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.id === 'cpCode') $('[data-coupon="apply"]').click(); });

/* move to wishlist = save it, then remove it from the cart */
document.addEventListener('click', async e => {
  const b = e.target.closest('[data-save]'); if (!b) return;
  const id = b.dataset.save; b.disabled = true;
  try {
    if (!LOGGED) {
      const ids = JSON.parse(localStorage.getItem('guestWishlist') || '[]');
      if (!ids.includes(id)) ids.push(id);
      localStorage.setItem('guestWishlist', JSON.stringify(ids));
      if (window.A3) A3.setWishCount(ids.length);
    } else if (!WISH.has(id)) {                            // wishlist_toggle.php toggles, so only call it if not saved yet
      const r = await fetch('wishlist_toggle.php', { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'product_id=' + encodeURIComponent(id) });
      const d = await r.json();
      if (d.status !== 'added') throw new Error(d.message || 'Could not save to wishlist');
      WISH.add(id);
    }
    const del = b.closest('.line').querySelector('button[value="delete"]');
    del.form.requestSubmit(del);
    if (window.A3) A3.toast('Moved to your wishlist');
  } catch (err) { if (window.A3) A3.toast(err.message); b.disabled = false; }
});


/* ---------- quick view ---------- */
// opts: { minQty, lowStockAt, money(v), add(id, qty) -> Promise, done(item, qty) }
function MiniQV(opts) {
  const d = document.getElementById('mqv'), g = id => document.getElementById(id);
  const MINQ = opts.minQty;
  let cur = null, qty = MINQ;
  function paint() {
    g('mqvQty').textContent = qty;
    g('mqvMinus').disabled = qty <= MINQ;
    g('mqvPlus').disabled = qty + MINQ > cur.stock;
  }
  function open(p) {
    cur = p; qty = MINQ;
    g('mqvImg').src = p.img; g('mqvImg').alt = p.name; g('mqvImgBox').dataset.cat = p.cat || '';
    g('mqvCat').textContent = p.label || '';
    g('mqvTitle').textContent = p.name;
    g('mqvId').textContent = 'ID: ' + p.id;
    g('mqvPrice').innerHTML = `${opts.money(p.price)} <span>per unit</span>`;
    const st = g('mqvStock'), low = p.stock < opts.lowStockAt;
    st.className = 'mqv-stock' + (low ? ' low' : '');
    st.textContent = p.stock <= 0 ? 'Sold out' : low ? `Only ${p.stock} units left` : 'In stock, ready to ship';
    g('mqvNote').textContent = `Sold in steps of ${MINQ} units.`;
    const can = p.stock >= MINQ;
    g('mqvAdd').disabled = !can; d.querySelector('.mqv-step').hidden = !can;
    g('mqvAdd').textContent = can ? 'Add to cart' : (p.stock <= 0 ? 'Sold out' : `Fewer than ${MINQ} units left`);
    if (can) paint();
    d.showModal();
  }
  g('mqvMinus').addEventListener('click', () => { qty -= MINQ; paint(); });
  g('mqvPlus').addEventListener('click', () => { qty += MINQ; paint(); });
  g('mqvClose').addEventListener('click', () => d.close());
  d.addEventListener('click', e => { if (e.target === d) d.close(); });
  g('mqvAdd').addEventListener('click', async () => {
    const b = g('mqvAdd'); b.disabled = true; b.textContent = 'Adding…';
    try { await opts.add(cur.id, qty); d.close(); opts.done(cur, qty); }
    catch (err) { (window.A3 ? A3.toast : alert)(err.message); }
    finally { b.disabled = false; b.textContent = 'Add to cart'; }
  });
  return { open };
}
const qv = MiniQV({ minQty: <?= (int)$MIN_QTY ?>, lowStockAt: 300,
  money: v => SYMBOL + Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
  add: async (id, q) => {
    const r = await fetch('add_to_cart.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=add&product_id=' + encodeURIComponent(id) + '&quantity=' + q + '&redirect_to=&ajax=1' });
    const d = await r.json(); if (d.status !== 'success') throw new Error(d.message || 'Could not add to cart');
  },
  done: () => location.reload() });
document.addEventListener('click', e => {
  const qa = e.target.closest('[data-qv]'); if (!qa || e.metaKey || e.ctrlKey || e.shiftKey) return;
  const p = SUGGEST.find(x => x.id === qa.dataset.qv); if (!p) return;
  e.preventDefault(); qv.open(p);
});

/* suggestions: add 100 units, then refresh the cart */
document.addEventListener('click', async e => {
  const b = e.target.closest('[data-add]'); if (!b) return;
  b.disabled = true; b.textContent = 'Adding…';
  try {
    const r = await fetch('add_to_cart.php', { method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=add&product_id=' + encodeURIComponent(b.dataset.add) + '&quantity=<?= (int)$MIN_QTY ?>&redirect_to=&ajax=1' });
    const d = await r.json();
    if (d.status !== 'success') throw new Error(d.message || 'Could not add to cart');
    location.reload();
  } catch (err) { if (window.A3) A3.toast(err.message); b.disabled = false; b.textContent = 'Add <?= (int)$MIN_QTY ?> units'; }
});
})();
</script>

<?php include __DIR__ . '/footer3.php'; ?>