<?php
/* =====================================================================
   ARAKU TRIBE - header3.php
   Self-contained: its own CSS, PHP and JavaScript are all in this file.
   Uses only your existing backend files (db1.php, currency_helper.php,
   cart_status.php), the same way the old header.php did.

   How to use on any page:
       <?php
       $PAGE_TITLE = 'Coffee Cups - Araku Tribe';   // optional
       $ACTIVE_NAV = 'cup';                         // optional: shows a tab on bag, cup, mug, tshirt, offers or combos
       $TAB_STYLE  = 'brown';                       // optional: 'brown' (default), 'cream' or 'dark'
       include 'header3.php';
       ?>
       ... page content ...
       <?php include 'footer3.php'; ?>

   On the page's own JavaScript you can call:
       A3.openCart(notice)  A3.refreshCart(notice)  A3.toast('text')
       A3.setCartCount(n)   A3.setWishCount(n)      A3.openDrawer(id)  A3.closeDrawers()
   and set window.onSiteSearch = q => {...} to show header-search results on that page.

   SEARCH: the header search matches product id, name, category and any extra product
   columns listed in $h3Want below (description, colour, size, price...). While you type,
   the textbox itself completes the word in grey-selected text (press Right arrow / End to
   accept, Backspace to remove it) and a suggestion list opens underneath.
   ===================================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf3'])) $_SESSION['csrf3'] = bin2hex(random_bytes(16));   // used by the quick contact form

/* ---------- SETTINGS (header + footer) ---------- */
// Every link in the header, footer and phone menu. When a "3" version of a page
// is ready (e.g. coffee_cup3.php), change it here once.
$H3_LINKS = [
    'home'      => 'index.php',
    'shop'      => 'products3.php',          // all products page (top "Products" tab + "Shop" breadcrumbs)
    'offers'    => 'offers3.php',
    'hotdeals'  => 'hotdeals3.php',          // HOT DEALS page (all deals first, then the whole catalogue)
    'combos'    => 'combos3.php',
    'new'       => 'index.php#newSection',
    'bag'       => 'coffee_bag3.php',
    'cup'       => 'coffee_cup3.php',
    'mug'       => 'coffee_mug3.php',
    'tshirt'    => 'coffee_tshirt3.php',
    'about'     => 'coffee_about3.php',
    'franchise' => 'coffee_franchise3.php',
    'contact'   => 'coffee_contact3.php',
    'agent'     => 'araku_agent3.php',
    'cart'      => 'cart3.php',
    'wishlist'  => 'wishlist3.php',
    'login'     => 'coffee_login3.php',
    'signup'    => 'coffee_signup3.php',
    'profile'   => 'user_profile3.php',
    'orders'    => 'user_orders3.php',
    'logout'    => 'logout_user3.php',       // signs out fully, then back to the home page
    'terms'     => 'terms_and_conditions3.php',
    'privacy'   => 'privacy_policy3.php',
];

/* ---------- ORDER-THROUGH MARKETPLACES (logos beside the menu tabs) ----------
   Change the 'url' of each one to your real product / store page whenever you like.
   Remove a line to hide it, or add new ones. 'bg' = button colour, 'fg' = text colour.
   Add 'logo' => 'images/amazon.png' to any line to show its real logo (square PNG/SVG);
   without it a coloured letter badge is shown. Set $H3_MARKET_SHOW = false to hide them all. */
$H3_MARKET_SHOW  = true;
$H3_MARKET_LABEL = 'Order';
/* Araku Tribe's own icon, shown FIRST in the "Order through" row (links to the shop page) */
$H3_MARKET_BRAND = ['name' => 'Arakutribe', 'url' => $H3_LINKS['shop'], 'logo' => 'araku_icon.png', 'bg' => '#2A1910'];   // 'bg' = tile colour behind the logo
 
/* Brand-coloured tiles: 'bg' = tile colour, 'fg' = colour of the icon or wordmark */
$H3_MARKET_STYLE = [
    'Amazon'    => ['bg' => '#131921', 'fg' => '#FF9900'],
    'Flipkart'  => ['bg' => '#2874F0', 'fg' => '#FFFFFF'],
    'Blinkit'   => ['bg' => '#F8CB46', 'fg' => '#1C1C1C'],
    'Swiggy'    => ['bg' => '#FC8019', 'fg' => '#FFFFFF'],
    'Zepto'     => ['bg' => '#5B1E8B', 'fg' => '#FFFFFF'],
    'BigBasket' => ['bg' => '#84C225', 'fg' => '#FFFFFF'],
    'JioMart'   => ['bg' => '#0078AD', 'fg' => '#FFFFFF'],
];
/* 'icon' = SVG path of the real brand mark (24x24) drawn in 'color'; 'word' = a text wordmark used where no
   icon exists (Blinkit, Zepto, JioMart). To use an official logo file instead, add 'logo' => 'images/zepto.png'
   (it replaces icon/word). Change each 'url' to your real store page. */
$H3_MARKETS = [
    ['name' => 'Amazon',    'url' => 'https://www.amazon.in',            'color' => '#FF9900', 'icon' => 'M.045 18.02c.072-.116.187-.124.348-.022 3.636 2.11 7.594 3.166 11.87 3.166 2.852 0 5.668-.533 8.447-1.595l.315-.14c.138-.06.234-.1.293-.13.226-.088.39-.046.525.13.12.174.09.336-.12.48-.256.19-.6.41-1.006.654-1.244.743-2.64 1.316-4.185 1.726a17.617 17.617 0 01-10.951-.577 17.88 17.88 0 01-5.43-3.35c-.1-.074-.151-.15-.151-.22 0-.047.021-.09.051-.13zm6.565-6.218c0-1.005.247-1.863.743-2.577.495-.71 1.17-1.25 2.04-1.615.796-.335 1.756-.575 2.912-.72.39-.046 1.033-.103 1.92-.174v-.37c0-.93-.105-1.558-.3-1.875-.302-.43-.78-.65-1.44-.65h-.182c-.48.046-.896.196-1.246.46-.35.27-.575.63-.675 1.096-.06.3-.206.465-.435.51l-2.52-.315c-.248-.06-.372-.18-.372-.39 0-.046.007-.09.022-.15.247-1.29.855-2.25 1.82-2.88.976-.616 2.1-.975 3.39-1.05h.54c1.65 0 2.957.434 3.888 1.29.135.15.27.3.405.48.12.165.224.314.283.45.075.134.15.33.195.57.06.254.105.42.135.51.03.104.062.3.076.615.01.313.02.493.02.553v5.28c0 .376.06.72.165 1.036.105.313.21.54.315.674l.51.674c.09.136.136.256.136.36 0 .12-.06.226-.18.314-1.2 1.05-1.86 1.62-1.963 1.71-.165.135-.375.15-.63.045a6.062 6.062 0 01-.526-.496l-.31-.347a9.391 9.391 0 01-.317-.42l-.3-.435c-.81.886-1.603 1.44-2.4 1.665-.494.15-1.093.227-1.83.227-1.11 0-2.04-.343-2.76-1.034-.72-.69-1.08-1.665-1.08-2.94l-.05-.076zm3.753-.438c0 .566.14 1.02.425 1.364.285.34.675.512 1.155.512.045 0 .106-.007.195-.02.09-.016.134-.023.166-.023.614-.16 1.08-.553 1.424-1.178.165-.28.285-.58.36-.91.09-.32.12-.59.135-.8.015-.195.015-.54.015-1.005v-.54c-.84 0-1.484.06-1.92.18-1.275.36-1.92 1.17-1.92 2.43l-.035-.02zm9.162 7.027c.03-.06.075-.11.132-.17.362-.243.714-.41 1.05-.5a8.094 8.094 0 011.612-.24c.14-.012.28 0 .41.03.65.06 1.05.168 1.172.33.063.09.099.228.099.39v.15c0 .51-.149 1.11-.424 1.8-.278.69-.664 1.248-1.156 1.68-.073.06-.14.09-.197.09-.03 0-.06 0-.09-.012-.09-.044-.107-.12-.064-.24.54-1.26.806-2.143.806-2.64 0-.15-.03-.27-.087-.344-.145-.166-.55-.257-1.224-.257-.243 0-.533.016-.87.046-.363.045-.7.09-1 .135-.09 0-.148-.014-.18-.044-.03-.03-.036-.047-.02-.077 0-.017.006-.03.02-.063v-.06z'],
    ['name' => 'Flipkart',  'url' => 'https://www.flipkart.com',         'color' => '#2874F0', 'icon' => 'M3.833 1.333a.993.993 0 0 0-.333.061V1c0-.551.449-1 1-1h14.667c.551 0 1 .449 1 1v.333H3.833zm17.334 2.334H2.833c-.551 0-1 .449-1 1V23c0 .551.449 1 1 1h7.3l1.098-5.645h-2.24c-.051 0-5.158-.241-5.158-.241l4.639-.327-.078-.366-1.978-.285 1.882-.158-.124-.449-3.075-.467s3.341-.373 3.392-.373h3.232l.247-1.331c.289-1.616.945-2.807 1.973-3.693 1.033-.892 2.344-1.332 3.937-1.332.643 0 1.053.151 1.231.463.118.186.201.516.279.859.074.352.14.671.095.903-.057.345-.461.465-1.197.465h-.253c-1.327 0-2.134.763-2.405 2.31l-.243 1.355h1.54c.574 0 .781.402.622 1.306-.17.941-.539 1.36-1.111 1.36H14.9L13.804 24h7.362c.551 0 1-.449 1-1V4.667a1 1 0 0 0-.999-1zM20.5 2.333A.334.334 0 0 0 20.167 2H3.833a.334.334 0 0 0-.333.333V3h17v-.667z'],
    ['name' => 'Blinkit',   'url' => 'https://blinkit.com',              'bg' => '#F8CB46', 'color' => '#1C1C1C', 'word' => 'blinkit'],
    ['name' => 'Swiggy',    'url' => 'https://www.swiggy.com/instamart', 'color' => '#FC8019', 'icon' => 'M12.034 24c-.376-.411-2.075-2.584-3.95-5.513-.547-.916-.901-1.63-.833-1.814.178-.48 3.355-.743 4.333-.308.298.132.29.307.29.409 0 .44-.022 1.619-.022 1.619a.441.441 0 1 0 .883-.002l-.005-2.939c0-.255-.278-.319-.331-.329-.511-.002-1.548-.006-2.661-.006-2.457 0-3.006.101-3.423-.172-.904-.591-2.383-4.577-2.417-6.819C3.849 4.964 5.723 2.225 8.362.868A8.13 8.13 0 0 1 12.026 0c4.177 0 7.617 3.153 8.075 7.209l.001.011c.084.981-5.321 1.189-6.39.904-.164-.044-.206-.212-.206-.284L13.5 4.996a.442.442 0 0 0-.884.002l.009 3.866a.33.33 0 0 0 .268.32l3.354-.001c1.79 0 2.542.207 3.042.588.333.254.461.739.349 1.37C18.633 16.755 12.273 23.71 12.034 24z'],
    ['name' => 'Zepto',     'url' => 'https://www.zeptonow.com',         'bg' => '#FFFFFF', 'color' => '#5B1E8B', 'word' => 'zepto'],
    ['name' => 'BigBasket', 'url' => 'https://www.bigbasket.com',        'color' => '#84C225', 'icon' => 'M11.543 12.989a3.352 3.352 0 0 0-.871-1.375 2.251 2.251 0 0 0-1.474-.503 2.276 2.276 0 0 0-1.541.536 2.953 2.953 0 0 0-.973 1.375 5.609 5.609 0 0 0-.335 1.845 5.42 5.42 0 0 0 .335 1.809c.192.548.528 1.034.973 1.407.441.339.985.516 1.541.503a2.08 2.08 0 0 0 1.945-1.072h-.002a4.92 4.92 0 0 0 .67-2.649 6.477 6.477 0 0 0-.268-1.876zM20.381.033H3.587A3.584 3.584 0 0 0 0 3.585V20.38a3.59 3.59 0 0 0 3.587 3.586h16.794c1.986 0 3.601-1.6 3.619-3.586V3.585A3.614 3.614 0 0 0 20.381.033zM10.64 3.034h1.911v5.744a3.47 3.47 0 0 0-1.911-.584v-5.16zm-.438 17.93a5.586 5.586 0 0 1-2.278-.369 3.522 3.522 0 0 1-1.174-.804 4.18 4.18 0 0 1-.335-.536l-.033-.134v.302C6.281 21.266 3.4 20.931 3.4 20.931V3.034h3.15v7.442h.034a3.675 3.675 0 0 1 1.541-1.309 4.825 4.825 0 0 1 2.079-.435 4.197 4.197 0 0 1 2.649.871 5.263 5.263 0 0 1 1.675 2.278v-.001c.372.951.565 1.963.569 2.984a8.418 8.418 0 0 1-.571 2.984 5.47 5.47 0 0 1-1.675 2.278 4.161 4.161 0 0 1-2.649.838zm9.828-3.111a5.47 5.47 0 0 1-1.675 2.278 4.173 4.173 0 0 1-2.649.838 5.587 5.587 0 0 1-2.278-.37 4.85 4.85 0 0 1-.362-.186c.444-.36.837-.778 1.168-1.245.353.127.726.189 1.101.185 2.38 0 3.15-2.502 3.15-4.484 0-1.96-.721-4.523-3.15-4.523a3.058 3.058 0 0 0-1.021.163 6.349 6.349 0 0 0-1.017-1.166c.107-.063.218-.121.331-.176a4.72 4.72 0 0 1 6.403 2.715l-.001.004c.373.951.566 1.963.571 2.984a8.409 8.409 0 0 1-.571 2.983z'],
    ['name' => 'JioMart',   'url' => 'https://www.jiomart.com',          'bg' => '#0078AD', 'color' => '#FFFFFF', 'word' => 'JioMart'],
];
$H3_MIN_QTY      = 100;
$H3_LOGO_IMAGE   = 'araku_logo.png';            // one logo for header, footer and phones (phones show just the head); '' = text wordmark
$H3_LOGO_FOOTER  = $H3_LOGO_IMAGE;
$H3_WELCOME_CODE = '';                          // e.g. 'WELCOME10' - only once cart.php really accepts it
$H3_WELCOME_TEXT = '10% off your first order';
$H3_CATS = ['bag' => 'Coffee Bags', 'cup' => 'Coffee Cups', 'mug' => 'Coffee Mugs', 'tshirt' => 'T-Shirts'];
$H3_NAV  = ['bag' => 'Bags', 'cup' => 'Cups', 'mug' => 'Mugs', 'tshirt' => 'T-shirts', 'offers' => 'Offers', 'combos' => 'Combos'];

// SESSION TIMEOUT (signed-in customers only). After this many minutes with no activity
// they are signed out. A warning with a countdown appears $H3_IDLE_WARN_SECONDS before that,
// with "Stay signed in" and "Sign out" buttons. Activity in any open tab counts.
$H3_IDLE_MINUTES      = 15;
$H3_IDLE_WARN_SECONDS = 120;   // 2-minute warning

$PAGE_TITLE       = $PAGE_TITLE       ?? 'Araku Tribe - Araku Valley coffee, cups, mugs and tees';
$PAGE_DESCRIPTION = $PAGE_DESCRIPTION ?? 'Shop Araku Valley coffee bags, branded coffee cups, mugs and T-shirts from Araku Tribe.';
$ACTIVE_NAV       = $ACTIVE_NAV       ?? '';
$TAB_STYLE        = $TAB_STYLE        ?? 'brown';   // 'brown' (default), 'cream' (joins a cream page top) or 'dark'

/* ---------- SITE SETTINGS (table site_settings, edited on admin_settings3.php) ----------
   Returns key => value. No table yet = [] and the site keeps its built-in look. */
if (!function_exists('h3_settings')) {
    function h3_settings() {
        static $set = null;
        if ($set !== null) return $set;
        $set = [];
        try {
            include __DIR__ . '/db1.php';
            mysqli_report(MYSQLI_REPORT_OFF);
            $c = new mysqli($host, $user, $pass, $db, $port);
            if (!$c->connect_error) {
                $r = $c->query("SELECT setting_key, setting_value, css_var FROM site_settings WHERE NOT (setting_key LIKE 'c\\_%' AND input_type IN ('text','textarea','image','link'))");
                if ($r) while ($row = $r->fetch_assoc()) $set[$row['setting_key']] = $row;
                $c->close();
            }
        } catch (\Throwable $e) {}
        return $set;
    }
    function h3_set($key, $fallback) { $s = h3_settings(); return isset($s[$key]) ? $s[$key]['setting_value'] : $fallback; }
}
$H3_IDLE_MINUTES      = (int)h3_set('s_idle', $H3_IDLE_MINUTES);
$H3_IDLE_WARN_SECONDS = (int)h3_set('s_warn', $H3_IDLE_WARN_SECONDS);

/* ---------- SESSION TIMEOUT: server-side check (the browser warning is in the script below) ---------- */
if (!empty($_SESSION['user_id'])) {
    $h3IdleLimit = max(2, (int)$H3_IDLE_MINUTES) * 60;
    $_SESSION['h3_idle_limit'] = $h3IdleLimit;              // session_ping3.php uses the same limit
    $h3Last = (int)($_SESSION['last_activity'] ?? time());
    if (time() - $h3Last > $h3IdleLimit) {                  // idle too long: sign out, then home with a note
        $h3Out = 'logout_user3.php?timeout=1';
        if (!headers_sent()) header('Location: ' . $h3Out);
        else echo '<script>location.replace(' . json_encode($h3Out) . ')</script>';
        exit;
    }
    $_SESSION['last_activity'] = time();
}

if (!function_exists('h3_e')) {
    function h3_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
    function h3_link($k) { global $H3_LINKS; return h3_e($H3_LINKS[$k] ?? '#'); }
}
/* The Araku robot (used by the floating chat button in footer3.php and on araku_agent3.php).
   Its colour comes from the CSS variable --bot, so the VIBGYOR colour show is pure CSS. */
if (!function_exists('h3_bot_svg')) {
    function h3_bot_svg($cls = '') {
        return '<svg class="a3-bot-svg ' . $cls . '" viewBox="0 0 120 120" aria-hidden="true" focusable="false">'
          . '<line class="b-ant" x1="60" y1="27" x2="60" y2="13"/><circle class="b-bulb" cx="60" cy="10" r="6"/>'
          . '<rect class="b-ear" x="12" y="47" width="11" height="22" rx="5"/><rect class="b-ear" x="97" y="47" width="11" height="22" rx="5"/>'
          . '<rect class="b-head" x="20" y="25" width="80" height="62" rx="24"/>'
          . '<rect class="b-face" x="31" y="38" width="58" height="37" rx="15"/>'
          . '<g class="b-eyes"><ellipse cx="48" cy="55" rx="6.5" ry="7.5"/><ellipse cx="72" cy="55" rx="6.5" ry="7.5"/></g>'
          . '<path class="b-smile" d="M50 65 Q60 72 70 65"/>'
          . '<circle class="b-cheek" cx="40" cy="66" r="3"/><circle class="b-cheek" cx="80" cy="66" r="3"/>'
          . '<rect class="b-body" x="38" y="88" width="44" height="26" rx="11"/>'
          . '<path class="b-heart" d="M60 108c-5-3.4-8-6.2-8-9.4a3.9 3.9 0 0 1 8-1.6 3.9 3.9 0 0 1 8 1.6c0 3.2-3 6-8 9.4Z"/>'
          . '<path class="b-arm b-arm-l" d="M38 96 Q26 100 24 110"/><path class="b-arm b-arm-r" d="M82 96 Q96 90 100 78"/>'
          . '</svg>';
    }
}

/* ---------- DATA (own connection, so the page's $conn is never touched) ---------- */
include __DIR__ . '/db1.php';
require_once __DIR__ . '/currency_helper.php';
$h3Uid = $_SESSION['user_id'] ?? null;
$h3WishCount = 0;
$h3Products = [];
$h3Currency = 'INR';
/* Extra product columns the header search may read (only the ones that really exist in your
   products table are used, so a missing column never breaks the page). Add more names here
   if your table has e.g. 'flavour' or 'roast'. Keep private columns (cost, supplier) OUT of this list:
   everything listed here is sent to the browser for searching. */
$h3Want = ['description', 'short_description', 'details', 'color', 'colour', 'size', 'material', 'tags', 'keywords', 'brand', 'subcategory', 'type', 'price'];
try {
    $h3conn = new mysqli($host, $user, $pass, $db, $port);
    if (!$h3conn->connect_error) {
        // Logged-in users: copy the DB cart into the session (same as the old header.php)
        if ($h3Uid) {
            $st = $h3conn->prepare("SELECT c.product_id, c.quantity, p.product_name, p.price, p.image
                                    FROM cart c JOIN products p ON c.product_id = p.product_id WHERE c.user_id = ?");
            if ($st) {
                $st->bind_param("i", $h3Uid);
                $st->execute();
                $r = $st->get_result();
                $_SESSION['cart'] = [];
                while ($row = $r->fetch_assoc()) {
                    $_SESSION['cart'][$row['product_id']] = [
                        'product_name' => $row['product_name'],
                        'price'        => (float)($row['price'] ?? 0),
                        'image'        => $row['image'],
                        'quantity'     => (int)($row['quantity'] ?? 0),
                    ];
                }
                $st->close();
            }
            $st = $h3conn->prepare("SELECT COUNT(*) AS total FROM wishlist WHERE user_id = ?");
            if ($st) {
                $st->bind_param("i", $h3Uid);
                $st->execute();
                $h3WishCount = (int)($st->get_result()->fetch_assoc()['total'] ?? 0);
                $st->close();
            }
        }
        // Product list for the header search (id, name, category + the extra columns that exist)
        $h3ExtraNames = [];
        $cr = $h3conn->query("SHOW COLUMNS FROM products");
        if ($cr) while ($c = $cr->fetch_assoc()) {
            if (in_array(strtolower($c['Field']), $h3Want, true)) $h3ExtraNames[] = $c['Field'];
        }
        $h3Sel = 'product_id, product_name, category, image';
        foreach ($h3ExtraNames as $n) $h3Sel .= ', `' . str_replace('`', '', $n) . '`';
        $res = $h3conn->query("SELECT $h3Sel FROM products");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (empty($row['image'])) continue;
                if (!preg_match('/^' . preg_quote($row['product_id'], '/') . '\.(jpg|jpeg|png|gif|webp)$/i', $row['image'])) continue;
                $cat = strtolower($row['category']);
                $kw = [];
                foreach ($h3ExtraNames as $n) {
                    $v = $row[$n] ?? '';
                    if ($v === null || $v === '') continue;
                    $kw[] = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string)$v))), 0, 160);
                }
                $h3Products[] = [
                    'id'   => (string)$row['product_id'],
                    'name' => (string)($row['product_name'] ?? $row['product_id']),
                    'cat'  => $cat,
                    'kw'   => mb_substr(implode(' ', $kw), 0, 500),
                    'img'  => "img/shop/{$row['category']}/{$row['image']}",
                    'url'  => ($H3_LINKS[$cat] ?? 'coffee_products.php') . '#product-' . rawurlencode($row['product_id']),
                ];
            }
        }
        $h3Currency = resolve_display_currency($h3conn);
        $h3conn->close();
    }
} catch (\Throwable $e) {
    // header still renders if the database is unavailable
}
if (empty($_SESSION['csrf3'])) $_SESSION['csrf3'] = bin2hex(random_bytes(16));   // used by the quick contact form
$h3Csrf = $_SESSION['csrf3'];
$h3Restored = !empty($_SESSION['account_restored']); unset($_SESSION['account_restored']);
$h3First = trim(explode(' ', (string)($_SESSION['user_name'] ?? ''))[0] ?? '');
$h3Initial = $h3First !== '' ? strtoupper(substr($h3First, 0, 1)) : 'A';
$h3Email = (string)($_SESSION['user_email'] ?? '');
$h3CartCount = (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) ? count($_SESSION['cart']) : 0;
$h3Js = json_encode([
    'loggedIn' => (bool)$h3Uid,
    'links'    => $H3_LINKS,
    'cats'     => $H3_CATS,
    'products' => $h3Products,
    'idleMinutes' => (int)$H3_IDLE_MINUTES,
    'idle'     => $h3Uid ? [
        'limit'  => max(2, (int)$H3_IDLE_MINUTES) * 60,
        'warn'   => max(30, min((int)$H3_IDLE_WARN_SECONDS, max(2, (int)$H3_IDLE_MINUTES) * 60 - 30)),
        'ping'   => 'session_ping3.php',
        'logout' => ($H3_LINKS['logout'] ?? 'logout_user3.php') . '?timeout=1',
    ] : null,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

/* ---------- DUAL MODE & SPA (AJAX) CHECK ---------- */

?>
<?php
/* ---------- PAGE CONTENT (admin settings > page tabs) ----------
   Every text row, link and image path of every page can be edited in admin_settings3.php.
   Rows are stored in site_settings with a page name ('global' = header/footer on all pages).
   This filter swaps in ONLY the rows that were changed, in one query, when the page is sent.
   No changed rows = the page goes out exactly as written. */
if (!function_exists('h3_cms_rows')) {
    function h3_cms_rows() {
        $page = preg_replace('/\.php$/', '', basename($_SERVER['SCRIPT_NAME'] ?? ''));
        $rows = [];
        try {
            include __DIR__ . '/db1.php';
            mysqli_report(MYSQLI_REPORT_OFF);
            $c = new mysqli($host, $user, $pass, $db);
            if (!$c->connect_error) {
                $st = $c->prepare("SELECT input_type, setting_value, default_value FROM site_settings
                                   WHERE page IN ('global', ?) AND setting_key LIKE 'c\\_%' AND input_type IN ('text','textarea','image','link')
                                   AND setting_value <> default_value");
                if ($st) { $st->bind_param('s', $page); $st->execute(); $res = $st->get_result();
                           while ($r = $res->fetch_assoc()) $rows[] = $r; $st->close(); }
                $c->close();
            }
        } catch (\Throwable $e) {}
        usort($rows, fn($a, $b) => strlen($b['default_value']) - strlen($a['default_value']));   // longest first
        return $rows;
    }
    function h3_cms_filter($html) {
        $rows = h3_cms_rows();
        if (!$rows) return $html;
        // split off <script> blocks: HTML text is swapped outside them, whole quoted strings inside them
        $parts = preg_split('~(<script\b[^>]*>.*?</script>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($rows as $r) {
            $d = $r['default_value']; $v = $r['setting_value'];
            if ($d === '') continue;
            $dRaw = html_entity_decode($d, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $vRaw = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($r['input_type'] === 'image' || $r['input_type'] === 'link') {
                // every form the path appears in (attribute, JavaScript string, page data), one pass
                $attr = $r['input_type'] === 'image' ? 'src' : 'href';
                $vh = htmlspecialchars($vRaw, ENT_QUOTES, 'UTF-8');
                $map = [$attr . '="' . $d . '"' => $attr . '="' . $vh . '"', $attr . "='" . $d . "'" => $attr . "='" . $vh . "'",
                        json_encode($dRaw) => json_encode($vRaw), "'" . $dRaw . "'" => "'" . addslashes($vRaw) . "'",
                        '"' . $dRaw . '"' => '"' . addslashes($vRaw) . '"',
                        $attr . '="' . htmlspecialchars($dRaw, ENT_QUOTES, 'UTF-8') . '"' => $attr . '="' . $vh . '"'];
                foreach ($parts as &$pt) $pt = strtr($pt, $map);
                unset($pt);
                continue;
            }
            // HTML text: whole text pieces and text attributes, however the page wrote the characters
            $forms = array_unique([$d, $dRaw, htmlspecialchars($dRaw, ENT_NOQUOTES, 'UTF-8'), htmlspecialchars($dRaw, ENT_QUOTES, 'UTF-8'), htmlspecialchars($dRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8')]);
            $vh = htmlspecialchars($vRaw, ENT_QUOTES, 'UTF-8');
            $rep = addcslashes($vh, '\\$');
            // inside scripts: the same words as a whole quoted string, but never a comparison or an object key
            $jsS = addcslashes(str_replace('</', '<\/', $vRaw), "\\'\n\r");
            $jsD = addcslashes(str_replace('</', '<\/', $vRaw), "\\\"\n\r");
            $jq  = preg_quote($dRaw, '~');
            $guardL = '(?<!===)(?<!!==)(?<!==)(?<!!=)(?<!case )(?<!\[)';
            $guardR = '(?!\s*(?::|===|!==|==|!=|\]))';
            foreach ($parts as $i => &$pt) {
                if ($i % 2) {                                              // a <script> block
                    if (strlen($dRaw) < 3) continue;
                    $pt = preg_replace('~' . $guardL . "(\s*)'" . $jq . "'" . $guardR . '~u', '${1}\'' . addcslashes($jsS, '\\$') . '\'', $pt) ?? $pt;
                    $pt = preg_replace('~' . $guardL . '(\s*)"' . $jq . '"' . $guardR . '~u', '${1}"' . addcslashes($jsD, '\\$') . '"', $pt) ?? $pt;
                    $pt = preg_replace('~(>[ \t\r\n]*)' . $jq . '([ \t\r\n]*<)~u', '${1}' . addcslashes($jsS === $vRaw ? $vRaw : htmlspecialchars($vRaw, ENT_QUOTES, 'UTF-8'), '\\$') . '${2}', $pt) ?? $pt;   // HTML inside template strings
                } else {
                    foreach ($forms as $f) {
                        $q = preg_quote($f, '~');
                        $pt = preg_replace('~(>[ \t\r\n]*)' . $q . '([ \t\r\n]*<)~u', '${1}' . $rep . '${2}', $pt) ?? $pt;
                        $pt = preg_replace('~\b(alt|title|placeholder|aria-label|content)="' . $q . '"~u', '$1="' . $rep . '"', $pt) ?? $pt;
                    }
                }
            }
            unset($pt);
        }
        return implode('', $parts);
    }
}
ob_start('h3_cms_filter');
$h3Page = basename($_SERVER['SCRIPT_NAME'] ?? '');
$h3Top  = ['home' => 'Home', 'about' => 'About', 'shop' => 'Products', 'franchise' => 'Franchise', 'contact' => 'Contact us'];
$h3TopOn = $h3Page === basename($H3_LINKS['home']) ? 'home'
         : ($h3Page === basename($H3_LINKS['about']) ? 'about'
         : ($h3Page === basename($H3_LINKS['franchise']) ? 'franchise'
         : ($h3Page === basename($H3_LINKS['contact']) ? 'contact'
         : (isset($H3_NAV[$ACTIVE_NAV]) || in_array($ACTIVE_NAV, ['hotdeals', 'agent'], true)
            || in_array($h3Page, array_map('basename', [$H3_LINKS['shop'] ?? '', $H3_LINKS['cart'] ?? '', $H3_LINKS['wishlist'] ?? '', $H3_LINKS['agent'] ?? '', $H3_LINKS['hotdeals'] ?? '']), true) ? 'shop' : ''))));
header('Vary: X-Requested-With');   // same URL returns a fragment or a full page, so caches must keep them apart

$isSpaRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
             || isset($_GET['ajax']) || isset($_POST['ajax']);
if ($isSpaRequest) {
    header('X-Nav-Top: '       . $h3TopOn);
    header('X-Nav-Active: '    . $ACTIVE_NAV);
    header('X-Nav-Tab-Style: ' . $TAB_STYLE);
    header('X-Page-Title: '    . rawurlencode($PAGE_TITLE));
    return;
}
?>
<!doctype html>
<?php
$h3Fx = [];
foreach (['fx_shine' => 'fx-no-shine', 'fx_pulse' => 'fx-no-pulse', 'fx_rainbow' => 'fx-no-rainbow', 'fx_flame' => 'fx-no-flame',
          'fx_sparkles' => 'fx-no-sparkles', 'fx_lift' => 'fx-no-lift', 'fx_robot3d' => 'fx-no-robot3d'] as $h3k => $h3cls)
    if (h3_set($h3k, '1') === '0') $h3Fx[] = $h3cls;
$h3Pop = h3_set('b_pop', 'none');
if (in_array($h3Pop, ['lift', 'grow', 'bounce'], true)) $h3Fx[] = 'btn-pop-' . $h3Pop;   // button pop on hover
if (h3_set('fx_btnspark', '0') === '1') $h3Fx[] = 'fx-btn-sparkle';                     // shine on main buttons
if (h3_set('th_one_bg', '0') === '1')  $h3Fx[] = 'thumb-one-bg';
if (h3_set('ts_btn_case', 'normal') === 'CAPITALS') $h3Fx[] = 'btn-caps';
if (h3_set('h_sticky', '1') === '1') $h3Fx[] = 'sticky-top';                        // top bar stays visible with the menu               // button text in capitals
if (h3_set('ts_top_case', 'normal') === 'CAPITALS') $h3Fx[] = 'top-caps';               // top links in capitals
if (h3_set('ts_card_font', 'body') === 'heading')   $h3Fx[] = 'card-serif';             // product names in the heading font                        // one background for all product images
if (h3_set('lb_eyebrow_case', 'normal') === 'CAPITALS') $h3Fx[] = 'eyebrow-caps';      // small labels in capitals
if (h3_set('lb_head_style', 'normal') === 'italic')     $h3Fx[] = 'head-italic';       // slanted headings
$h3Case = h3_set('lb_menu_case', 'normal');
if ($h3Case === 'CAPITALS') $h3Fx[] = 'menu-caps'; elseif ($h3Case === 'Capitalised') $h3Fx[] = 'menu-capz';
?>
<html lang="en"<?= $h3Fx ? ' class="' . implode(' ', $h3Fx) . '"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title id="pageTitle"><?= h3_e($PAGE_TITLE) ?></title>
<meta name="description" content="<?= h3_e($PAGE_DESCRIPTION) ?>">
<link rel="shortcut icon" href="img/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Young+Serif&display=swap" rel="stylesheet">
<style>
/* ============ header3.php styles (colours, fonts, buttons, announcement bar, header) ============ */
:root{
  --brown:#6B3E26;  --brown-deep:#3B2317; --brown-soft:#8A5A3F;
  --orange:#F26A2E; --orange-cta:#C9501A; --orange-cta-hover:#AF4413; /* cta shade keeps white text at 4.5:1 */
  --green:#2F6B4F;  --green-soft:#E6EFE9;
  --cream:#FFF7ED;  --cream-2:#F7EADB;
  --gold:#F4B63D;   --red:#D9363E;
  --ink:#17110D;    --muted:#5B4B41;   /* darker = brighter contrast, easier to read */ --line:#EDE2D6; --white:#FFFFFF;
  --serif:'Young Serif', Georgia, 'Times New Roman', serif;
  --sans:'Figtree', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  --r-sm:8px; --r-md:12px; --r-lg:18px;
  --wrap:1240px;
  --shadow:0 1px 2px rgba(59,35,23,.06), 0 8px 24px -12px rgba(59,35,23,.18);
}
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:120px}
html,body{overflow-x:clip}
body{margin:0;font-family:var(--sans);font-size:16px;font-weight:500;line-height:1.55;color:var(--ink);background:var(--white);-webkit-font-smoothing:antialiased}
img{max-width:100%;height:auto;display:block}   /* height:auto keeps every photo in proportion (no stretching) */
a{color:inherit}
button{font:inherit;color:inherit;cursor:pointer}
:focus-visible{outline:2px solid var(--orange-cta);outline-offset:3px;border-radius:4px}
.wrap{max-width:var(--wrap);margin:0 auto;padding:0 24px}
.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.ico{width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round;flex:none}
h1,h2{text-wrap:balance}
h1,h2,h3{font-family:var(--serif);font-weight:700;color:var(--brown);margin:0;letter-spacing:-.01em}
h2{font-size:clamp(1.7rem,2.6vw,2.3rem);line-height:1.15}
.lede{color:var(--muted);max-width:56ch;margin:.5rem 0 0}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1.5px solid transparent;border-radius:999px;padding:13px 26px;font-weight:600;font-size:15px;text-decoration:none;transition:background-color .2s,color .2s,border-color .2s}
.btn-primary{background:var(--orange-cta);color:#fff}
.btn-primary:hover{background:var(--orange-cta-hover)}
.btn:disabled{background:var(--cream-2);color:var(--muted);border-color:transparent;cursor:not-allowed}
.btn-outline{border-color:var(--brown);color:var(--brown);background:transparent}
.btn-outline:hover{background:var(--brown);color:#fff}
.btn-ghost-light{border-color:rgba(255,247,237,.6);color:var(--cream)}
.btn-ghost-light:hover{background:var(--cream);color:var(--brown-deep)}
.link{color:var(--brown);font-weight:600;text-decoration:underline;text-underline-offset:4px;text-decoration-thickness:1px}
.link:hover{color:var(--orange-cta)}
.announce{background:var(--brown-deep);color:var(--cream);font-size:13.5px}
.announce .wrap{display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:38px}
.announce p{margin:0}
.announce a{color:var(--gold);text-decoration:none;font-weight:600}
.announce a:hover{text-decoration:underline}
.announce .hello{color:var(--gold);font-weight:700}
.announce .wrap{align-items:stretch}
.top-nav{display:flex;align-items:flex-end;gap:4px;align-self:stretch}
.top-nav a{display:flex;align-items:center;white-space:nowrap;height:32px;margin-top:6px;padding:0 15px;border-radius:12px 12px 0 0;color:var(--cream);font-weight:700;font-size:13.5px;text-decoration:none;transition:background .2s,color .2s}
.top-nav a:hover{background:rgba(255,247,237,.12);text-decoration:none;color:#fff}
.top-nav a[aria-current="page"]{background:#fff;color:var(--brown-deep);box-shadow:0 -4px 12px -6px rgba(0,0,0,.4)}
.top-nav a[aria-current="page"]::before{content:"";display:block;width:6px;height:6px;margin-right:7px;border-radius:50%;background:var(--orange-cta)}
.top-nav .hello{align-self:center;margin-left:12px}
.announce-links{align-self:center}
.help-link{white-space:nowrap;display:inline-flex;align-items:center;gap:6px;border:0;background:none;padding:0;font:inherit;font-weight:600;color:inherit;cursor:pointer}
.help-link .ico{width:15px;height:15px}
.help-link:hover{color:var(--gold)}
.announce-links{display:flex;align-items:center;gap:18px}
.show-sm{display:none}
.announce select{background:transparent;color:var(--cream);border:1px solid rgba(255,247,237,.35);border-radius:999px;padding:3px 8px;font-size:12.5px}
.announce select option{color:var(--ink)}
.site-header{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.96);backdrop-filter:saturate(1.2) blur(8px);border-bottom:1px solid var(--line)}
.head-row{display:flex;align-items:center;gap:20px;height:84px}
.brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--brown);flex:none}
.brand-mark{width:34px;height:34px}
.brand-name{font-family:var(--serif);font-size:1.45rem;line-height:1}
.brand img{width:auto;display:block}
.brand .logo-box{display:flex;align-items:center;overflow:hidden}   /* no background: the logo sits straight on the header */
.brand .logo-box img{height:68px;width:auto;max-width:none;display:block}   /* same height as the HOT DEALS + AI AGENT buttons */
.main-nav a{padding-left:9px!important;padding-right:9px!important}
.head-row{gap:16px}
@media (max-width:680px){
  .brand{order:-1}                                   /* phones: full logo at the far left, menu button on the right */
  .brand{margin-right:auto}
  .brand .logo-box img{height:40px}
  .menu-btn{order:3}
  .top-nav{gap:2px;overflow-x:auto;scrollbar-width:none;flex:1;min-width:0}
  .top-nav::-webkit-scrollbar{display:none}
  .top-nav a{padding:0 8px;font-size:12px;white-space:nowrap}
  .top-nav a[aria-current="page"]::before{display:none}
  .announce p{display:none}
}
.main-nav{display:flex;gap:4px;flex:1}
.main-nav a{white-space:nowrap;padding:8px 11px;border-radius:999px;text-decoration:none;font-weight:800;font-size:15.5px;color:var(--ink)}
.main-nav a:hover{background:var(--cream)}
.main-nav a[aria-current="page"]{color:var(--brown);font-weight:700}
/* selected-page tab (only on pages that set $ACTIVE_NAV) */
.site-header{--tab-bg:var(--brown);--tab-ink:var(--cream);--tab-line:var(--brown);--tab-top:var(--gold)}
.site-header.tab-cream{--tab-bg:var(--cream);--tab-ink:var(--brown);--tab-line:var(--line);--tab-top:var(--orange-cta)}
.site-header.tab-dark{--tab-bg:#2A1910;--tab-ink:#fff;--tab-line:#2A1910;--tab-top:var(--gold)}
.site-header.has-tab{border-bottom:2px solid var(--tab-line)}
.has-tab .main-nav{align-self:stretch;align-items:flex-end}
.has-tab .main-nav a{position:relative;display:flex;align-items:center;height:54px;margin-bottom:-2px;border-radius:14px 14px 0 0}
.has-tab .main-nav a:hover{background:none;color:var(--orange-cta)}
.has-tab .main-nav a[aria-current="page"]{z-index:1;background:var(--tab-bg);color:var(--tab-ink);box-shadow:0 -6px 16px -10px rgba(59,35,23,.55)}
.has-tab .main-nav a[aria-current="page"]:hover{color:var(--tab-ink)}
.has-tab .main-nav a[aria-current="page"]::before{content:"";position:absolute;top:0;left:22%;right:22%;height:3px;border-radius:0 0 4px 4px;background:var(--tab-top)}
.has-tab .main-nav a[aria-current="page"]::after{content:"";position:absolute;bottom:0;left:-14px;right:-14px;height:14px;pointer-events:none;
  background:radial-gradient(circle at 0 0,transparent 13.5px,var(--tab-bg) 14px) left bottom/14px 14px no-repeat,
             radial-gradient(circle at 100% 0,transparent 13.5px,var(--tab-bg) 14px) right bottom/14px 14px no-repeat}
.head-actions{display:flex;align-items:center;gap:6px}
.icon-btn{position:relative;display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:999px;border:0;background:transparent;color:var(--brown);text-decoration:none}
.icon-btn:hover{background:var(--cream)}
.count{position:absolute;top:3px;right:1px;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:var(--orange-cta);color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center}
.count[hidden]{display:none}
.menu-btn{display:none}
.search{position:relative;width:min(220px,15vw)}
.search input{width:100%;height:44px;border:1.5px solid var(--line);border-radius:999px;padding:0 16px 0 42px;background:var(--cream);font:inherit;font-size:15px;color:var(--ink)}
.search input:focus{outline:none;border-color:var(--brown-soft);background:#fff}
.search input::selection{background:rgba(201,80,26,.22);color:var(--ink)}   /* the auto-completed part of the word */
.search .ico{position:absolute;left:14px;top:11px;width:20px;height:20px;color:var(--muted)}
.suggest{position:absolute;top:calc(100% + 8px);left:0;right:0;min-width:320px;max-height:70vh;overflow:auto;background:#fff;border:1px solid var(--line);border-radius:var(--r-md);box-shadow:var(--shadow);padding:8px;z-index:60}
.suggest[hidden]{display:none}
.suggest-item{display:flex;align-items:center;gap:12px;padding:8px;border-radius:var(--r-sm);text-decoration:none}
.suggest-item[aria-selected="true"],.suggest-item:hover{background:var(--cream)}
.suggest-item img{width:44px;height:44px;border-radius:6px;object-fit:contain;padding:3px;background:var(--cream-2);mix-blend-mode:multiply}
.suggest-item small{display:block;color:var(--muted);font-size:12.5px}
.suggest-item mark{background:none;color:var(--orange-cta);font-weight:800}
.suggest-all{display:block;width:100%;margin-top:4px;padding:10px;border:0;border-top:1px solid var(--line);background:none;text-align:left;font-weight:600;color:var(--brown)}
.suggest-empty{padding:10px;color:var(--muted);font-size:14px}
.suggest-empty a{color:var(--brown);font-weight:600}
/* account menu: brown top band + white list */
.account{position:relative}
.account summary{list-style:none;cursor:pointer}
.account summary::-webkit-details-marker{display:none}
.acct-btn{display:inline-flex;align-items:center;gap:8px;height:44px;padding:0 6px;border-radius:999px;color:var(--brown)}
.acct-btn:hover,.account[open] .acct-btn{background:var(--cream)}
.avatar{flex:none;width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--brown);color:var(--cream);font-weight:700;font-size:15px;box-shadow:0 0 0 2px #fff,0 0 0 3.5px var(--gold)}
.acct-name{font-weight:600;font-size:14.5px;color:var(--brown-deep);max-width:110px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding-right:6px}
.acct-name small{display:block;font-weight:500;font-size:11.5px;color:var(--muted);line-height:1}
.account-menu{position:absolute;right:-6px;top:calc(100% + 10px);z-index:60;width:280px;background:#fff;border-radius:18px;overflow:hidden;
  box-shadow:0 0 0 1px rgba(59,35,23,.08),0 24px 48px -18px rgba(36,24,17,.45);animation:menuIn .18s ease-out}
.account-menu::before{content:"";position:absolute;top:-6px;right:20px;width:12px;height:12px;background:var(--brown-deep);transform:rotate(45deg)}
@keyframes menuIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
.acct-top{position:relative;display:flex;align-items:center;gap:12px;padding:16px 18px;color:var(--cream);background:radial-gradient(120% 140% at 100% 0%,#8A4F2B 0%,rgba(138,79,43,0) 60%),var(--brown-deep)}
.acct-top .avatar{width:42px;height:42px;font-size:18px;box-shadow:0 0 0 2px var(--gold)}
.acct-top strong{display:block;color:#fff;font-size:16px}
.acct-top small{display:block;font-size:12.5px;color:#E6D5C4;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.acct-top.guest small{white-space:normal;max-width:none}
.acct-list{padding:8px}
.acct-list a{display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:12px;text-decoration:none;font-weight:600;font-size:15px;color:var(--ink)}
.acct-list a .ico{width:20px;height:20px;color:var(--brown)}
.acct-list a small{margin-left:auto;font-weight:600;font-size:12px;color:var(--muted)}
.acct-list a:hover,.acct-list a:focus-visible{background:var(--cream);color:var(--brown)}
.acct-list hr{border:0;border-top:1px solid var(--line);margin:6px 4px}
.acct-list a.out{color:var(--red)}
.acct-list a.out .ico{color:var(--red)}
.acct-list a.out:hover{background:#FBE9E9}
.acct-guest{padding:18px}
.acct-guest p{margin:0 0 14px;font-size:14px;color:var(--muted)}
.acct-guest .btn{width:100%;margin-bottom:8px}
.acct-name{display:none}   /* greeting lives in the top bar instead, so the header keeps room for search */
@media (prefers-reduced-motion:reduce){ .account-menu{animation:none} }
:root{--bag-ico:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 8h14l-1 12H6L5 8Z'/%3E%3Cpath d='M9 8V6a3 3 0 0 1 6 0v2'/%3E%3C/svg%3E")}
.btn-lg{padding:16px 30px;font-size:16px}
.orb{flex:none;width:32px;height:32px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;color:#fff;background:radial-gradient(circle at 30% 28%,#FFE3A3 0%,#F4A13D 38%,#D2561C 70%,#7A2E0E 100%);box-shadow:inset 0 -2px 6px rgba(0,0,0,.25)}
.orb .ico{width:16px;height:16px;stroke-width:2}
/* ---------- AI AGENT button (robot + bold sparkly text) ---------- */
.agent-btn{position:relative;flex:none;display:inline-flex;align-items:center;gap:9px;padding:5px 16px 5px 5px;border-radius:999px;text-decoration:none;color:#fff;white-space:nowrap;isolation:isolate;
  background:linear-gradient(135deg,#2A1910 0%,#6B3E26 100%);box-shadow:0 10px 22px -10px rgba(217,54,62,.75),0 0 0 0 rgba(242,106,46,.6);transition:transform .2s,box-shadow .2s;animation:agentGlow 2.4s ease-in-out infinite}
.agent-btn::before{content:"";position:absolute;inset:-2.5px;z-index:-1;border-radius:inherit;padding:2.5px;
  background:conic-gradient(from var(--spin,0deg),#8B00FF,#4B0082,#0066FF,#00B140,#FFD400,#FF7A00,#E3121B,#8B00FF);
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude;animation:spin 3s linear infinite}
@property --spin{syntax:'<angle>';initial-value:0deg;inherits:false}
@keyframes spin{to{--spin:360deg}}
@keyframes agentGlow{0%,100%{box-shadow:0 10px 22px -10px rgba(217,54,62,.75),0 0 0 0 rgba(227,18,27,.45)}50%{box-shadow:0 12px 26px -10px rgba(217,54,62,.9),0 0 0 7px rgba(227,18,27,0)}}
.agent-btn:hover{transform:translateY(-1px) scale(1.03)}
.agent-btn .bot-ico{flex:none;width:36px;height:36px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:radial-gradient(circle at 35% 30%,#FF6B6B 0%,#E3121B 55%,#8E0A10 100%);box-shadow:inset 0 -2px 6px rgba(0,0,0,.3),0 0 12px rgba(227,18,27,.6)}
.agent-btn .bot-ico svg{width:26px;height:26px;fill:none;stroke:#fff;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;animation:botNod 2.4s ease-in-out infinite;transform-origin:50% 80%}
@keyframes botNod{0%,100%{transform:rotate(0)}20%{transform:rotate(-10deg)}40%{transform:rotate(8deg)}60%{transform:rotate(0)}}
.agent-btn .t strong{display:block;font-size:15px;line-height:1.1;font-weight:800;letter-spacing:.06em;text-transform:uppercase;
  background:linear-gradient(90deg,#FFD400,#fff 30%,#FF7A00 50%,#fff 70%,#FFD400);background-size:200% auto;-webkit-background-clip:text;background-clip:text;color:transparent;animation:shine 2.2s linear infinite}
.agent-btn .t small{display:block;font-size:11.5px;line-height:1.2;color:#F4D9B8;font-weight:600}
@keyframes shine{to{background-position:-200% center}}
/* twinkling sparkles on the AI Agent and HOT DEALS buttons */
.sparkle{position:absolute;width:10px;height:10px;pointer-events:none;z-index:2;background:#fff;
  clip-path:polygon(50% 0,61% 39%,100% 50%,61% 61%,50% 100%,39% 61%,0 50%,39% 39%);filter:drop-shadow(0 0 3px #FFD400);animation:twinkle 1.8s ease-in-out infinite}
.sparkle.s1{top:-4px;right:12px}
.sparkle.s2{bottom:-3px;right:38%;width:8px;height:8px;animation-delay:.6s}
.sparkle.s3{top:2px;left:30%;width:7px;height:7px;animation-delay:1.2s}
@keyframes twinkle{0%,100%{transform:scale(0) rotate(0);opacity:0}50%{transform:scale(1) rotate(90deg);opacity:1}}
/* ---------- HOT DEALS button ---------- */
.hot-btn{position:relative;flex:none;display:inline-flex;align-items:center;gap:7px;padding:9px 16px 9px 11px;border-radius:999px;text-decoration:none;color:#fff;white-space:nowrap;
  font-weight:800;font-size:14.5px;letter-spacing:.06em;text-transform:uppercase;
  background:linear-gradient(110deg,#E3121B 0%,#FF4D00 35%,#FF9500 50%,#FF4D00 65%,#E3121B 100%);background-size:220% 100%;
  box-shadow:0 8px 20px -8px rgba(227,18,27,.85);text-shadow:0 1px 2px rgba(120,0,0,.45);animation:hotFlow 2.6s linear infinite,hotPulse 1.6s ease-in-out infinite;transition:transform .2s}
.hot-btn:hover{transform:translateY(-1px) scale(1.04)}
.hot-btn[aria-current="page"]{outline:3px solid var(--gold);outline-offset:2px}
.hot-btn .flame{width:20px;height:20px;flex:none;fill:#FFE066;stroke:#fff;stroke-width:1.2;filter:drop-shadow(0 0 4px #FFD400);animation:flick .9s ease-in-out infinite alternate;transform-origin:50% 90%}
@keyframes hotFlow{to{background-position:-220% 0}}
@keyframes hotPulse{0%,100%{box-shadow:0 8px 20px -8px rgba(227,18,27,.85),0 0 0 0 rgba(255,77,0,.55)}50%{box-shadow:0 8px 20px -8px rgba(227,18,27,.85),0 0 0 8px rgba(255,77,0,0)}}
@keyframes flick{from{transform:scale(1) rotate(-4deg)}to{transform:scale(1.15) rotate(5deg)}}
.hot-btn .sparkle.s1{right:6px}
/* ---------- BIG header buttons (HOT DEALS + AI AGENT) ---------- */
.agent-btn{min-height:68px;padding:4px 22px 4px 6px;gap:12px;background:radial-gradient(120% 140% at 100% 0%,#8B00FF 0%,rgba(139,0,255,0) 55%),radial-gradient(100% 140% at 0% 100%,#0066FF 0%,rgba(0,102,255,0) 60%),#2A1150}
.agent-btn::before{inset:-3px;padding:3px}
.agent-btn .t strong{font-size:24px;font-weight:900;letter-spacing:.07em}
.agent-btn .t .say{position:relative;display:block;height:17px;overflow:hidden;font-size:13px;font-weight:700;color:#FFE9A8}
.agent-btn .t .say span{display:block;animation:sayFlip 6s ease-in-out infinite}
@keyframes sayFlip{0%,42%{transform:translateY(0)}50%,92%{transform:translateY(-17px)}100%{transform:translateY(0)}}
.bot-3d{position:relative;flex:none;width:68px;height:68px;margin:-8px 0 -8px -4px;border-radius:50%;--bot:#E3121B;perspective:300px;
  background:radial-gradient(circle at 50% 35%,#fff 0%,#FFE6D6 70%,#FFC9A8 100%);box-shadow:0 0 0 3px #FFD400,0 0 22px rgba(255,59,59,.85)}
.bot-3d .a3-bot-svg{position:absolute;inset:5px 6px 7px;width:auto;height:auto;transform-style:preserve-3d;animation:bot3d 3.2s ease-in-out infinite,redGlow 2s ease-in-out infinite}
@keyframes bot3d{0%,100%{transform:rotateY(-28deg) translateY(0)}25%{transform:rotateY(0) translateY(-4px)}50%{transform:rotateY(28deg) translateY(0)}75%{transform:rotateY(0) translateY(-4px)}}
.bot-3d .a3-bot-svg .b-arm-r{animation:wave .9s ease-in-out infinite}
.bot-3d .hi{position:absolute;top:-12px;right:-20px;padding:2px 8px;border-radius:999px 999px 999px 4px;background:#FFD400;color:#2A1150;font:900 12px/1.4 var(--sans);font-style:normal;box-shadow:0 4px 10px -4px rgba(0,0,0,.5);animation:hiPop 3s ease-in-out infinite}
@keyframes hiPop{0%,60%,100%{transform:scale(1)}70%{transform:scale(1.25) rotate(-8deg)}80%{transform:scale(1)}}
.hot-btn{min-height:68px;padding:0 32px 0 20px;gap:12px;font-size:24px;font-weight:900;letter-spacing:.08em}
.hot-btn .flame{width:40px;height:40px}
/* desktop: two rows - logo + the big buttons on top, the shop tabs underneath */
@media (min-width:1261px){
  .head-row{flex-wrap:wrap;height:auto;padding-top:12px;row-gap:4px}
  .hot-btn{margin-left:auto;order:1} .agent-btn{order:1} .search{order:2} .head-actions{order:3}
  .main-nav{order:10;flex:1 0 100%;height:52px;align-items:center;gap:6px}
  .has-tab .main-nav{align-self:auto;align-items:flex-end}
  .has-tab .main-nav a{height:50px}
  .main-nav a{padding-left:14px!important;padding-right:14px!important;font-size:16px}
}
@media (max-width:1260px) and (min-width:681px){
  .hot-btn{margin-left:auto;min-height:56px;font-size:18px;padding:0 20px 0 14px} .hot-btn .flame{width:30px;height:30px}
  .agent-btn{min-height:56px;padding-right:18px} .agent-btn .t strong{font-size:18px} .agent-btn .t .say{display:none}
  .bot-3d{width:56px;height:56px}
  .brand .logo-box img{height:56px}
  .head-row{gap:12px}
}
@media (max-width:920px) and (min-width:681px){
  .hot-btn{font-size:15px;padding:0 14px 0 10px;min-height:50px} .hot-btn .flame{width:24px;height:24px}
  .agent-btn{min-height:50px;padding-right:14px;gap:8px} .agent-btn .t strong{font-size:15px}
  .bot-3d{width:48px;height:48px}
  .brand .logo-box img{height:50px}
  .head-row{gap:6px}
  .hot-btn .flame{display:none} .hot-btn{padding:0 12px}
  .head-actions .icon-btn{width:40px}
}
@media (max-width:860px) and (min-width:681px){ .brand .logo-box img{height:40px} }
@media (max-width:770px) and (min-width:681px){ .agent-btn .t{display:none} .agent-btn{padding:4px 10px 4px 4px} }
@media (max-width:1260px){
  .search{width:auto}
  .main-nav{display:none}
  .menu-btn{display:inline-flex}
  .has-tab .head-row{height:auto;flex-wrap:wrap;row-gap:8px;padding-top:12px}
  .site-header.has-tab{border-bottom:0}
  .has-tab .main-nav{display:flex;order:10;flex:1 0 100%;align-self:auto;height:48px;gap:0;overflow-x:auto;scrollbar-width:none;margin:0 -24px;padding:0 24px;box-shadow:inset 0 -2px 0 var(--tab-line)}
  .has-tab .main-nav::-webkit-scrollbar{display:none}
  .has-tab .main-nav a{height:44px;margin-bottom:0;padding:0 16px;font-size:14.5px}
  .search{width:auto;flex:1}
}
@media (max-width:680px){
  .wrap{padding:0 16px}
  .announce .hide-sm{display:none}
  .announce .show-sm{display:inline}
  .head-row{height:auto;flex-wrap:wrap;gap:8px 4px;padding-top:10px;padding-bottom:10px}
  .has-tab .head-row{padding-bottom:0}
  .has-tab .main-nav{margin:0 -16px;padding:0 16px}
  .brand{flex:1}
  .search{order:3;flex:1 1 200px}
  .agent-btn{order:5;padding:4px 12px 4px 4px}
  .agent-btn .t small{display:none}
  .agent-btn .bot-ico{width:30px;height:30px}
  .agent-btn .bot-ico svg{width:22px;height:22px}
  .agent-btn .t strong{font-size:13.5px}
  .hot-btn{order:4;padding:8px 12px 8px 9px;font-size:13px}
  .hot-btn,.agent-btn{flex:1 1 0;justify-content:center;min-height:54px}
  .hot-btn{font-size:16px;padding:0 10px} .hot-btn .flame{width:24px;height:24px}
  .agent-btn .t strong{font-size:16px} .agent-btn .t .say{display:none}
  .bot-3d{width:46px;height:46px;margin:-4px 0}
  .bot-3d .hi{font-size:10px;right:-12px;top:-9px}
  .hot-btn .flame{width:17px;height:17px}
  .search{flex:1 1 100%}
}
/* desktops: the search folds into a round button and opens over the menu when clicked,
   so the menu, HOT DEALS and AI AGENT all fit on one row */
@media (min-width:681px){
  .search{width:44px;height:44px;flex:none}
  .search input{position:absolute;right:0;top:0;width:44px;padding:0 0 0 42px;color:transparent;cursor:pointer;z-index:61;transition:width .25s ease,background-color .2s}
  .search input::placeholder{color:transparent}
  .search .ico{z-index:62;pointer-events:none;color:var(--brown)}
  .search:focus-within input{width:min(340px,30vw);padding-right:16px;color:var(--ink);cursor:text;background:#fff;border-color:var(--brown-soft);box-shadow:var(--shadow)}
  .search:focus-within input::placeholder{color:var(--muted)}
  .search .suggest{left:auto;right:0;width:min(380px,34vw)}
}
@media (max-width:1440px) and (min-width:1261px){
  .agent-btn .t small{display:none}
  .main-nav a{padding-left:7px!important;padding-right:7px!important;font-size:14.5px}
}
@media (max-width:1100px) and (min-width:681px){ .agent-btn .t small{display:none} }
/* ---------- The Araku robot (shared by footer3.php's chat button and araku_agent3.php) ----------
   --bot is its body colour. On arrival it flies around cycling through VIBGYOR
   (violet, indigo, blue, green, yellow, orange) and settles on RED. */
@property --bot{syntax:'<color>';initial-value:#E3121B;inherits:true}
.a3-bot-svg{display:block;width:100%;height:100%;overflow:visible}
.a3-bot-svg .b-head,.a3-bot-svg .b-body,.a3-bot-svg .b-ear{fill:var(--bot,#E3121B);stroke:#fff;stroke-width:3}
.a3-bot-svg .b-face{fill:#1C1033}
.a3-bot-svg .b-eyes{fill:#7CF7FF;transform-origin:60px 55px;animation:blink 4s infinite}
.a3-bot-svg .b-smile{fill:none;stroke:#7CF7FF;stroke-width:3.5;stroke-linecap:round}
.a3-bot-svg .b-cheek{fill:#FF7AB8;opacity:.85}
.a3-bot-svg .b-ant{stroke:#fff;stroke-width:3.5;stroke-linecap:round}
.a3-bot-svg .b-bulb{fill:#FFD400;stroke:#fff;stroke-width:2;animation:bulb 1.2s ease-in-out infinite alternate}
.a3-bot-svg .b-heart{fill:#fff}
.a3-bot-svg .b-arm{fill:none;stroke:var(--bot,#E3121B);stroke-width:7;stroke-linecap:round}
.a3-bot-svg .b-arm-r{transform-origin:82px 96px;animation:wave 1.6s ease-in-out infinite}
@keyframes blink{0%,92%,100%{transform:scaleY(1)}95%{transform:scaleY(.1)}}
@keyframes bulb{from{fill:#FFD400;filter:drop-shadow(0 0 2px #FFD400)}to{fill:#FF3B3B;filter:drop-shadow(0 0 7px #FF3B3B)}}
@keyframes wave{0%,100%{transform:rotate(0)}30%{transform:rotate(-22deg)}60%{transform:rotate(12deg)}}
@keyframes vibgyor{0%{--bot:#8B00FF}14%{--bot:#4B0082}28%{--bot:#0066FF}42%{--bot:#00B140}57%{--bot:#FFD400}71%{--bot:#FF7A00}86%,100%{--bot:#E3121B}}
@keyframes rainbowHalo{0%{filter:drop-shadow(0 0 10px #8B00FF)}16%{filter:drop-shadow(0 0 10px #4B0082)}33%{filter:drop-shadow(0 0 10px #0066FF)}50%{filter:drop-shadow(0 0 10px #00B140)}66%{filter:drop-shadow(0 0 10px #FFD400)}83%{filter:drop-shadow(0 0 10px #FF7A00)}100%{filter:drop-shadow(0 0 12px #E3121B)}}
@keyframes redGlow{0%,100%{filter:drop-shadow(0 0 6px rgba(227,18,27,.75))}50%{filter:drop-shadow(0 0 16px rgba(255,59,59,.95))}}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
/* ---------- session timeout warning ---------- */
.a3-idle{position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(36,24,17,.55);backdrop-filter:blur(3px)}
.a3-idle[hidden]{display:none}
.a3-idle-box{width:min(420px,100%);background:#fff;border-radius:22px;padding:26px 24px 22px;text-align:center;box-shadow:0 30px 80px -20px rgba(36,24,17,.6)}
.a3-idle-box h2{font-size:1.45rem}
.a3-idle-box p{margin:8px 0 0;color:var(--muted);font-size:15px}
.a3-idle-time{display:block;margin:16px auto 4px;font-family:var(--serif);font-size:2.6rem;line-height:1;color:var(--red)}
.a3-idle-bar{height:6px;border-radius:6px;background:var(--cream-2);overflow:hidden;margin:12px 0 18px}
.a3-idle-bar i{display:block;height:100%;background:linear-gradient(90deg,var(--red),var(--orange));transition:width 1s linear}
.a3-idle-btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.a3-idle-btns .btn{flex:1 1 150px}
/* ---------- Top navigation (Home, About, Products...): gold-rimmed pills with a shine ---------- */
.announce .wrap{min-height:52px;align-items:stretch}
.top-nav{align-self:stretch;align-items:flex-end;gap:8px}
.top-nav a:not([aria-current]){margin-bottom:9px}
.top-nav a{position:relative;overflow:hidden;height:34px;margin:0;padding:0 16px;border-radius:999px;border:2px solid rgba(244,182,61,.75);
  background:linear-gradient(135deg,rgba(255,255,255,.10),rgba(255,255,255,.02));color:#FFF4E2;font-weight:800;font-size:14px;letter-spacing:.02em;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.15);transition:transform .2s,background .2s,color .2s,box-shadow .2s}
.top-nav a:not([aria-current])::after{content:"";position:absolute;top:0;bottom:0;left:-60%;width:40%;pointer-events:none;
  background:linear-gradient(105deg,transparent,rgba(255,230,160,.45),transparent);transform:skewX(-20deg);animation:navShine 5s ease-in-out infinite}
.top-nav a:nth-child(2)::after{animation-delay:.5s}.top-nav a:nth-child(3)::after{animation-delay:1s}.top-nav a:nth-child(4)::after{animation-delay:1.5s}.top-nav a:nth-child(5)::after{animation-delay:2s}
.top-nav a:hover{transform:translateY(-1px);color:#fff;text-decoration:none;background:linear-gradient(135deg,#C9501A,#8A4B2A);border-color:#FFD400;box-shadow:0 0 0 3px rgba(255,212,0,.25),0 6px 16px -6px rgba(201,80,26,.9)}
.top-nav a[aria-current="page"]{overflow:visible;height:43px;padding:0 20px;border:0;border-radius:14px 14px 0 0;color:#2A1910;font-size:15px;font-weight:900;
  background:#fff;box-shadow:inset 0 4px 0 #FF9F1C,0 -6px 18px -6px rgba(255,190,40,.75);animation:topGlow 2.4s ease-in-out infinite}
.top-nav a[aria-current="page"]::before{content:"";display:block;width:8px;height:8px;margin-right:8px;border-radius:50%;background:#E3121B;box-shadow:0 0 0 3px rgba(227,18,27,.2);animation:dotPulse 1.4s ease-in-out infinite}
.top-nav a[aria-current="page"]::after{content:"";position:absolute;bottom:0;left:-14px;right:-14px;height:14px;pointer-events:none;    /* curved corners: the tab flows into the white header */
  background:radial-gradient(circle at 0 0,transparent 13.5px,#fff 14px) left bottom/14px 14px no-repeat,radial-gradient(circle at 100% 0,transparent 13.5px,#fff 14px) right bottom/14px 14px no-repeat}
.top-nav a[aria-current="page"]:hover{color:#2A1910;transform:none;background:#fff}
@keyframes topGlow{0%,100%{box-shadow:inset 0 4px 0 #FF9F1C,0 -6px 16px -8px rgba(255,190,40,.6)}50%{box-shadow:inset 0 4px 0 #FFD400,0 -8px 24px -6px rgba(255,190,40,.95)}}
@keyframes dotPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.35)}}
/* Help & contact matches */
.announce .help-link{height:34px;padding:0 14px;border-radius:999px;border:2px solid rgba(244,182,61,.75);background:rgba(255,255,255,.06);color:#FFE6A8;font-weight:800}
.announce .help-link:hover{background:linear-gradient(135deg,#C9501A,#8A4B2A);color:#fff;border-color:#FFD400;text-decoration:none}
.announce select{height:34px;border:2px solid rgba(244,182,61,.75)!important;font-weight:800;color:#FFF4E2}
@media (max-width:680px){
  .announce .wrap{min-height:46px}
  .top-nav{gap:6px;padding:6px 14px 0 0}
  .top-nav a{height:30px;padding:0 11px;font-size:12.5px;flex:none}
  .top-nav a:not([aria-current]){margin-bottom:7px}
  .top-nav a[aria-current="page"]{height:38px;padding:0 13px;font-size:13px}
}
/* ---------- Header icons (search, account, wishlist, cart): big bold brown buttons ---------- */
.head-row{--ib:54px}
.head-actions{gap:10px}
.head-actions .icon-btn,.head-actions .acct-btn,.search .ico-btn-face{width:var(--ib);height:var(--ib)}
.head-actions .icon-btn,.head-actions .acct-btn{justify-content:center;padding:0;border:2.5px solid #F4B63D;border-radius:50%;color:#FFF4E2;
  background:linear-gradient(135deg,#8A4B2A 0%,#6B3E26 55%,#4A2A1A 100%);box-shadow:0 8px 18px -8px rgba(107,62,38,.9),inset 0 1px 0 rgba(255,255,255,.25);transition:transform .2s,box-shadow .2s,background .2s}
.head-actions .icon-btn .ico{width:27px;height:27px;stroke-width:2.3}
.head-actions .icon-btn:hover,.head-actions .acct-btn:hover,.head-actions .account[open] .icon-btn,.head-actions .account[open] .acct-btn{transform:translateY(-2px);color:#fff;
  background:linear-gradient(135deg,#C9501A 0%,#8A4B2A 60%,#6B3E26 100%);box-shadow:0 10px 22px -8px rgba(201,80,26,.9),0 0 0 3px rgba(244,182,61,.35)}
.head-actions .acct-btn .avatar{width:100%;height:100%;background:none;color:#FFF4E2;font-size:22px;font-weight:900;box-shadow:none}
.head-actions .count{top:-6px;right:-6px;min-width:24px;height:24px;padding:0 6px;font-size:13px;font-weight:900;background:#E3121B;border:2.5px solid #fff;box-shadow:0 4px 10px -4px rgba(227,18,27,.9)}
/* the folded search looks the same until it is opened */
.search input{width:var(--ib)!important;height:var(--ib);border:2.5px solid #F4B63D;background:linear-gradient(135deg,#8A4B2A 0%,#6B3E26 55%,#4A2A1A 100%);box-shadow:0 8px 18px -8px rgba(107,62,38,.9)}
.search{width:var(--ib)!important;height:var(--ib)!important}
.search .ico{width:27px;height:27px;stroke-width:2.3;color:#FFF4E2!important;left:13px!important;top:50%!important;transform:translateY(-50%)}
.search:focus-within input{width:min(360px,32vw)!important;background:#fff;border-color:#F4B63D}
.search:focus-within .ico{color:var(--brown)!important}
@media (max-width:1260px){ .head-row{--ib:48px} .head-actions .icon-btn .ico,.search .ico{width:24px;height:24px} .search .ico{left:11px!important} }
@media (max-width:680px){
  .head-row{--ib:40px} .head-actions{gap:5px}
  .brand .logo-box img{height:34px!important}
  .menu-btn{width:var(--ib);height:var(--ib);border:2.5px solid #F4B63D;border-radius:50%;color:#FFF4E2;background:linear-gradient(135deg,#8A4B2A 0%,#6B3E26 55%,#4A2A1A 100%)}
  .head-actions .icon-btn .ico{width:22px;height:22px}
  .head-actions .count{min-width:20px;height:20px;font-size:11.5px;top:-5px;right:-5px}
  /* phones show a full-width search bar: keep it light */
  .search,.search input{width:100%!important;height:46px!important}
  .search input{background:var(--cream);border:2px solid #E8C9A8;box-shadow:none}
  .search .ico{color:var(--brown)!important;left:14px!important}
}
/* ---------- Shop menu (Bags, Cups, Mugs...): bright brown buttons with a gold shine ---------- */
.site-header .main-nav a{position:relative;overflow:hidden;display:inline-flex;align-items:center;height:42px;padding:0 20px!important;border:2px solid #F4B63D;border-radius:999px;
  background:linear-gradient(135deg,#8A4B2A 0%,#6B3E26 55%,#4A2A1A 100%);color:#FFF4E2;font-weight:800;letter-spacing:.02em;text-shadow:0 1px 2px rgba(0,0,0,.35);
  box-shadow:0 6px 16px -8px rgba(107,62,38,.9),inset 0 1px 0 rgba(255,255,255,.25);transition:transform .2s,box-shadow .2s,background .2s}
.site-header .main-nav a:not([aria-current])::after{content:"";position:absolute;top:0;bottom:0;left:-60%;width:40%;pointer-events:none;
  background:linear-gradient(105deg,transparent,rgba(255,230,160,.55),transparent);transform:skewX(-20deg);animation:navShine 4.5s ease-in-out infinite}
.site-header .main-nav a:nth-child(2)::after{animation-delay:.4s}.site-header .main-nav a:nth-child(3)::after{animation-delay:.8s}
.site-header .main-nav a:nth-child(4)::after{animation-delay:1.2s}.site-header .main-nav a:nth-child(5)::after{animation-delay:1.6s}.site-header .main-nav a:nth-child(6)::after{animation-delay:2s}
@keyframes navShine{0%,55%{left:-60%}85%,100%{left:130%}}
.site-header .main-nav{gap:10px}
.site-header .main-nav a{flex:none}   /* keep full size; on phones the row scrolls sideways */
.site-header .main-nav a:hover{transform:translateY(-2px);color:#fff;background:linear-gradient(135deg,#C9501A 0%,#8A4B2A 60%,#6B3E26 100%);
  box-shadow:0 10px 22px -8px rgba(201,80,26,.9),0 0 0 3px rgba(244,182,61,.35)}
.site-header .main-nav a[aria-current="page"]{color:#fff;border-color:#FFD400;background:linear-gradient(135deg,#C9501A 0%,#6B3E26 100%);box-shadow:0 0 0 3px rgba(255,212,0,.35),0 10px 22px -8px rgba(201,80,26,.9)}
.site-header.has-tab .main-nav a:not([aria-current]){height:42px;margin-bottom:6px;border-radius:999px}
.site-header.has-tab .main-nav a[aria-current="page"]{overflow:visible;border-color:#FFD400;border-bottom:0;border-radius:14px 14px 0 0;height:52px;margin-bottom:-2px;
  background:linear-gradient(180deg,#C9501A 0%,#6B3E26 100%)}   /* ends in brown so it joins the brown line below */
@media (max-width:1260px){
  .site-header.has-tab .main-nav{gap:6px;align-items:center}
  .site-header.has-tab .main-nav a:not([aria-current]){height:34px;margin:0;padding:0 14px!important}
  .site-header.has-tab .main-nav a[aria-current="page"]{height:44px;align-self:flex-end}
}
/* ============ SITE SETTINGS WIRING: every value below comes from admin_settings3.php
   (the value after the comma is today's look, used when no setting is saved) ============ */
body{background:var(--page-bg,#fff);font-size:var(--fs-base,16px);font-weight:var(--fw-body,500)}
h1,h2,h3{font-weight:var(--fw-head,700);color:var(--head-color,var(--brown))}
.announce{background:var(--topbar-bg,var(--brown-deep))}
.top-nav a{color:var(--topbar-text,#FFF4E2);font-size:var(--topnav-fs,14px)}
.top-nav a[aria-current="page"]{color:#2A1910}
.site-header{background:var(--header-bg,rgba(255,255,255,.96))}
.site-header .main-nav a{height:var(--nav-h,42px);font-size:var(--nav-fs,15.5px);color:var(--nav-text,#FFF4E2);border-color:var(--nav-border,#F4B63D);
  background:linear-gradient(135deg,var(--nav-from,#8A4B2A) 0%,var(--nav-to,#4A2A1A) 100%)}
.site-header .main-nav a:hover,.site-header .main-nav a[aria-current="page"]{background:linear-gradient(135deg,var(--nav-hover,#C9501A) 0%,var(--nav-to,#4A2A1A) 100%)}
.site-header.has-tab .main-nav a[aria-current="page"]{background:linear-gradient(180deg,var(--nav-hover,#C9501A) 0%,var(--brown) 100%)}
.site-header.has-tab .main-nav a:not([aria-current]){height:var(--nav-h,42px)}
@media (min-width:1261px){
  .brand .logo-box img{height:var(--logo-h,68px)}
  .head-row{--ib:var(--icon-btn,54px)}
  .hot-btn,.agent-btn{min-height:var(--hb-h,68px)}
  .hot-btn,.agent-btn .t strong{font-size:var(--hb-fs,24px)}
}
/* header buttons + text colours (admin settings > Header buttons / Text colours / Text styles) */
.hot-btn{color:var(--hot-fg,#fff);background:linear-gradient(110deg,var(--hot-a,#E3121B) 0%,var(--hot-b,#FF4D00) 35%,var(--hot-c,#FF9500) 50%,var(--hot-b,#FF4D00) 65%,var(--hot-a,#E3121B) 100%);background-size:220% 100%}
.hot-btn .flame,.big-flame,.hot-hero h1 .fl{fill:var(--flame,#FFE066)}
.agent-btn{background:radial-gradient(120% 140% at 100% 0%,#8B00FF 0%,rgba(139,0,255,0) 55%),radial-gradient(100% 140% at 0% 100%,#0066FF 0%,rgba(0,102,255,0) 60%),var(--agent-bg,#2A1150)}
.agent-btn .t strong{background-image:linear-gradient(90deg,var(--agent-t1,#FFD400),#fff 30%,var(--agent-t2,#FF7A00) 50%,#fff 70%,var(--agent-t1,#FFD400))}
.agent-btn .t .say{color:var(--agent-sub,#FFE9A8)}
.top-nav a[aria-current="page"]{color:var(--toptab-fg,#2A1910);box-shadow:inset 0 4px 0 var(--toptab-line,#FF9F1C),0 -6px 18px -6px rgba(255,190,40,.75)}
.acct-list a{color:var(--acct-fg,var(--ink))}
.top-caps .top-nav a{text-transform:uppercase;letter-spacing:.06em}
/* top bar + menu stay at the top together while scrolling (admin settings > Header) */
.sticky-top .announce{position:sticky;top:0;z-index:51}
.sticky-top .site-header{top:var(--annh,52px)}
/* special effects switched off on the admin page */
.fx-no-shine .main-nav a:not([aria-current])::after,.fx-no-shine .top-nav a:not([aria-current])::after{display:none!important}
.fx-no-pulse .hot-btn,.fx-no-pulse .agent-btn,.fx-no-pulse .count,.fx-no-pulse .a3-chat-badge,.fx-no-pulse .a3-chat-btn::after,.fx-no-pulse .top-nav a[aria-current],.fx-no-pulse .a3-chat-label{animation:none!important}
.fx-no-rainbow .agent-btn::before,.fx-no-rainbow .a3-chat-btn::before,.fx-no-rainbow .pr-bot::before,.fx-no-rainbow .ag-hero-bot .ring{display:none!important}
.fx-no-flame .flame,.fx-no-flame .big-flame,.fx-no-flame .fl{animation:none!important}
.fx-no-sparkles .sparkle{display:none!important}
.fx-no-lift *:hover{transform:none!important}
.fx-no-robot3d .bot-3d .a3-bot-svg{animation:none!important}

/* ---------- ORDER THROUGH: logos beside the menu tabs (logo on top, name underneath) ---------- */
/* ---------- ORDER THROUGH: brand-coloured tiles, same fonts as the site ---------- */
.site-header .main-nav .mk-wrap{flex:none;display:flex;align-items:center;gap:14px;margin-left:30px;padding-left:26px;border-left:2px solid rgba(107,62,38,.22);align-self:center;font-family:var(--sans)}
.site-header .main-nav .mk-title{font-family:var(--serif);font-weight:400;font-size:13px;letter-spacing:.02em;text-transform:none;color:var(--brown);line-height:1.1;text-align:center;white-space:nowrap}
.site-header .main-nav .mk-list{display:flex;align-items:flex-start;gap:10px;margin:0;padding:0;list-style:none}
.site-header .main-nav .mk-list li{margin:0;padding:0}
.site-header .main-nav a.mk-item{display:flex!important;flex-direction:column;align-items:center;justify-content:flex-start;gap:3px;height:auto!important;margin:0!important;padding:0!important;
  border:0!important;border-radius:10px!important;background:none!important;box-shadow:none!important;color:var(--brown-deep)!important;
  font-family:var(--sans)!important;font-size:11px!important;font-weight:800!important;letter-spacing:.01em!important;line-height:1.1;text-decoration:none;text-shadow:none!important;transition:transform .18s}
.site-header .main-nav a.mk-item::before,.site-header .main-nav a.mk-item::after{display:none!important;content:none!important}
.site-header .main-nav a.mk-item:hover{transform:translateY(-3px);background:none!important;color:var(--orange-cta)!important}
.site-header .main-nav a.mk-item:hover .mk-logo{box-shadow:0 8px 16px -6px rgba(59,35,23,.65);border-color:var(--gold)}
.site-header .main-nav a.mk-item:focus-visible{outline:3px solid var(--brown-deep);outline-offset:2px}
.mk-logo{width:52px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fff;border:1.5px solid rgba(59,35,23,.18);box-shadow:0 3px 8px -4px rgba(59,35,23,.6);transition:box-shadow .18s,border-color .18s}
.mk-logo svg{height:32px;width:auto;max-width:calc(100% - 10px);display:block}
.mk-logo svg.mk-word{height:auto;width:calc(100% - 8px);max-width:none}
.mk-logo svg.mk-word text{font-family:var(--sans)}
.mk-logo img{max-height:100%;max-width:100%;object-fit:contain;display:block;padding:2px}
/* Araku Tribe's own tile now uses exactly the same tile style as Amazon, Flipkart, etc. (see the rules below) */
.mk-name{white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;font-family:var(--sans);font-size:10.5px}
/* every tile (Araku Tribe included) has the same size, border, radius and logo fit */
.mk-logo{width:100%;height:auto;aspect-ratio:1/1;border:2px solid rgba(59,35,23,.28);border-radius:12px;background:#fff;box-shadow:0 4px 10px -4px rgba(59,35,23,.65)}
.mk-logo img{width:100%;height:100%;max-width:none;max-height:none;object-fit:contain;padding:5px 7px}
.mk-own .mk-name{color:inherit}
/* Araku Tribe tile: the complete picture is shown inside the box, nothing is cropped. */
.mk-own .mk-logo{border-color:#2A1910}   /* border matches the picture, so no light line where they meet */
.mk-own .mk-logo img{object-fit:contain;padding:0;transform:scale(var(--own-zoom,1))}   /* whole picture shown, nothing cropped */
/* --own-zoom stays at 1 so no part of the logo is cut. Only raise it (e.g. 1.1) if you want it larger and accept some trimming. */
/* the row is right-aligned and shrinks to fit, so the last logo ends exactly under the cart icon and never goes past it */
.main-nav{min-width:0}
.site-header .main-nav .mk-wrap{margin-left:auto;min-width:0;flex:1 1 0px;justify-content:flex-end;padding-left:20px;gap:12px;
  max-width:calc(145px + 12px + 22px + var(--mk-n,8) * 66px + (var(--mk-n,8) - 1) * 8px)}   /* never wider than arrow + tiles at full size, so the last tile stops at the cart's right edge */
.site-header .main-nav .mk-list{min-width:0;flex:var(--mk-n,8) 1 0px;gap:8px;align-items:center;justify-content:flex-end}
.site-header .main-nav .mk-list li{flex:1 1 0px;min-width:0;max-width:66px}   /* every tile gets the same width; the arrow shrinks in step with them */
/* store name shown under each logo */
.site-header .main-nav a.mk-item .mk-name{position:static;display:block;width:100%;text-align:center;font-size:11px;font-weight:900;-webkit-text-stroke:.35px currentColor;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--brown-deep)}
/* ORDER badge: a transparent glass right-arrow in parrot green with bold ORDER text inside */
.site-header .main-nav .mk-title{position:relative;display:inline-flex;align-items:center;justify-content:center;flex:2.2 1 0px;min-width:0;white-space:nowrap;max-width:145px;min-height:0;padding:0;margin:0;border-radius:0;background:none;box-shadow:none;text-shadow:none;
  line-height:0;letter-spacing:0;text-transform:none;animation:mkNudge 1.1s ease-in-out infinite alternate;filter:drop-shadow(0 6px 8px rgba(18,110,50,.35)) drop-shadow(0 0 7px rgba(37,211,102,.45))}
.site-header .main-nav .mk-title .mk-glass{display:block;width:100%;height:auto;aspect-ratio:160/60;overflow:visible}
.site-header .main-nav .mk-title .mk-glass text{font-family:var(--sans);font-weight:900;letter-spacing:.06em}
.fx-no-pulse .mk-title{animation:none!important}
@media (prefers-reduced-motion:reduce){.site-header .main-nav .mk-title{animation:none}}
@media (min-width:1261px){ .main-nav{height:94px!important} }
.site-header .main-nav a.mk-item{width:100%}
@media (max-width:1260px){
  /* sideways-scrolling tab row on tablets/phones: tiles keep a fixed size */
  .site-header .main-nav .mk-wrap{flex:none;max-width:none;margin-left:16px}
  .site-header .main-nav .mk-title{flex:none;width:145px}
  .site-header .main-nav .mk-list{flex:none}
  .site-header .main-nav .mk-list li{flex:0 0 66px;max-width:none}
}
/* desktop: tiles share the row equally, so store names are a touch smaller and never get cut */
@media (min-width:1261px){
  .site-header .main-nav a.mk-item .mk-name{font-size:10px;overflow:visible;text-overflow:clip}
}
@media (max-width:1100px){
  .site-header .main-nav .mk-wrap{margin-left:16px;padding-left:14px;gap:10px}
  .site-header .main-nav .mk-list{gap:8px}
  .site-header .main-nav .mk-title{display:none}
}
@media (max-width:680px){
  .site-header .main-nav .mk-wrap{margin-left:10px;padding-left:10px}
}

/* currency box sat too high: the <form> kept an empty text-line gap under the select */
#currencyForm{display:flex;align-items:center;margin:0;padding:0}
#currencyForm select{display:block;margin:0;line-height:1;padding-top:0;padding-bottom:0}
.announce-links{align-self:center}
/* ---------- PRODUCT TEXT CENTRED (index + product listings) ----------
   Covers the usual product-card class names. If your index.php uses a different class,
   add it to this list. */
.product-card,.product,.products .card,.pcard,.p-card,.prod-card,.product-item,.product-box,.item-card,.grid .card,
.product-card *,.product *,.pcard *,.p-card *,.prod-card *,.product-item *,.product-box *,.item-card *,
.product-info,.product-details,.product-body,.card-body,.card-content,
.product-name,.product-title,.product-price,.product-category,.product-desc,.prod-name,.prod-title,.prod-price,.p-name,.p-title,.p-price,.item-name,.item-price{text-align:center}
.product-info,.product-details,.product-body,.card-body,.card-content,.product-card,.pcard,.p-card,.prod-card,.product-item,.product-box,.item-card{display:flex;flex-direction:column;align-items:center;justify-content:center}
.product-price,.prod-price,.p-price,.item-price,.price{justify-content:center}
.product-card img,.pcard img,.p-card img,.prod-card img,.product-item img,.product-box img,.item-card img{margin-left:auto;margin-right:auto}
@media (prefers-reduced-motion:reduce){
  *{transition:none!important;scroll-behavior:auto!important;animation:none!important}
}
</style>
<?php
$h3Fonts = ['Figtree' => "'Figtree', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif", 'Young Serif' => "'Young Serif', Georgia, 'Times New Roman', serif",
            'system-ui' => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif", 'Arial' => 'Arial, Helvetica, sans-serif', 'Verdana' => 'Verdana, Geneva, sans-serif',
            'Trebuchet MS' => "'Trebuchet MS', Helvetica, sans-serif", 'Georgia' => "Georgia, 'Times New Roman', serif", 'Times New Roman' => "'Times New Roman', Times, serif"];
$h3Vars = '';
foreach (h3_settings() as $h3row) {
    if (empty($h3row['css_var']) || !preg_match('/^--[a-z0-9-]+$/', $h3row['css_var'])) continue;
    $h3val = (string)$h3row['setting_value'];
    if (in_array($h3row['css_var'], ['--sans', '--serif'], true)) $h3val = $h3Fonts[$h3val] ?? null;
    elseif (!preg_match('/^(#[0-9a-fA-F]{3,8}|\d+(\.\d+)?(px|rem|em|%)?|rgba?\([\d\s.,%]+\))$/', $h3val)) $h3val = null;
    if ($h3val !== null) $h3Vars .= $h3row['css_var'] . ':' . $h3val . ';';
}
$h3Shadows = ['none' => 'none', 'soft' => '0 6px 16px -8px rgba(59,35,23,.55)', 'strong' => '0 14px 28px -10px rgba(59,35,23,.85)',
              'glow' => '0 0 0 4px rgba(244,182,61,.35),0 8px 22px -6px rgba(201,80,26,.75)'];
$h3Map = [
    '--btn-shadow'   => $h3Shadows[h3_set('b_shadow', '')] ?? null,
    '--thumb-shadow' => $h3Shadows[h3_set('th_shadow', '')] ?? null,
    '--thumb-fit'    => ['contain' => 'contain', 'cover' => 'cover'][h3_set('th_fit', '')] ?? null,
    '--thumb-zoom'   => ['none' => '1', 'zoom' => '1.07', 'strong' => '1.15'][h3_set('th_zoom', '')] ?? null,
    '--menu-ls'      => ['normal' => '.02em', 'wide' => '.05em', 'wider' => '.09em'][h3_set('lb_menu_space', '')] ?? null,
];
if (h3_set('th_fit', 'contain') === 'cover') $h3Map['--thumb-pad'] = '0%';   // "fill the box" has no empty space around it
foreach ($h3Map as $h3var => $h3vv) if ($h3vv !== null) $h3Vars .= $h3var . ':' . $h3vv . ';';
if ($h3Vars !== '') echo '<style id="site-settings">:root{' . $h3Vars . "}</style>\n";
?>
</head>
<body>

<!-- SVG icon set (one consistent line family), shared by header, footer and page -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
  <symbol id="i-heart" viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z"/></symbol>
  <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></symbol>
  <symbol id="i-bag" viewBox="0 0 24 24"><path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></symbol>
  <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
  <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol>
  <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11Z"/><circle cx="12" cy="10" r="2.2"/></symbol>
  <symbol id="i-leaf" viewBox="0 0 24 24"><path d="M5 19c0-8 5-13 14-14 0 9-5 14-13 14"/><path d="M5 19l7-7"/></symbol>
  <symbol id="i-hand" viewBox="0 0 24 24"><path d="M8 12V6a1.5 1.5 0 0 1 3 0v5M11 11V4.5a1.5 1.5 0 0 1 3 0V11M14 11V6a1.5 1.5 0 0 1 3 0v7a7 7 0 0 1-7 7 6 6 0 0 1-5-3l-2-4a1.5 1.5 0 0 1 2.6-1.5L8 14"/></symbol>
  <symbol id="i-box" viewBox="0 0 24 24"><path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5v-9Z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/></symbol>
  <symbol id="i-bean" viewBox="0 0 32 32"><ellipse cx="16" cy="16" rx="10" ry="13" transform="rotate(28 16 16)"/><path d="M11 6c6 5 4 14 10 20"/></symbol>
  <symbol id="i-spark" viewBox="0 0 24 24"><path d="M12 3.5l1.9 5.1 5.1 1.9-5.1 1.9L12 17.5l-1.9-5.1L5 10.5l5.1-1.9L12 3.5Z"/><path d="M18.5 15.5l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8.8-2Z"/></symbol>
  <symbol id="i-left" viewBox="0 0 24 24"><path d="M15 5l-7 7 7 7"/></symbol>
  <symbol id="i-right" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></symbol>
  <symbol id="i-pause" viewBox="0 0 24 24"><path d="M9 5v14M15 5v14"/></symbol>
  <symbol id="i-play" viewBox="0 0 24 24"><path d="M7 5l12 7-12 7V5Z"/></symbol>
  <symbol id="i-fb" viewBox="0 0 24 24"><path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H7v4h3v7h4v-7h3l1-4h-4V8Z"/></symbol>
  <symbol id="i-ig" viewBox="0 0 24 24"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r=".6"/></symbol>
  <symbol id="i-logout" viewBox="0 0 24 24"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 16l-4-4 4-4M6 12h10"/></symbol>
  <symbol id="i-orders" viewBox="0 0 24 24"><rect x="5" y="3.5" width="14" height="17" rx="2.5"/><path d="M9 8h6M9 12h6M9 16h4"/></symbol>
  <symbol id="i-chat" viewBox="0 0 24 24"><path d="M5 5h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-7l-5 4v-4H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/></symbol>
  <symbol id="i-phone" viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></symbol>
  <symbol id="i-tw" viewBox="0 0 24 24"><path d="M4 4l16 16M20 4 4 20"/></symbol>
  <symbol id="i-robot" viewBox="0 0 24 24"><path d="M12 2.5v3"/><circle cx="12" cy="2.3" r=".9"/><rect x="4.5" y="5.5" width="15" height="12" rx="4"/><circle cx="9.3" cy="11" r="1.3"/><circle cx="14.7" cy="11" r="1.3"/><path d="M9.5 14.3h5"/><path d="M2.5 10v4M21.5 10v4"/><path d="M8.5 17.5v3h7v-3"/></symbol>
  <symbol id="i-flame" viewBox="0 0 24 24"><path d="M12 22c4.4 0 7-2.9 7-6.6 0-3.3-2-5.6-3.6-7.4-.4 1.6-1.3 2.7-2.4 3.1.4-3.3-.9-6.5-3.8-8.6.2 3.1-1.1 5.1-2.6 6.8C5.2 11.1 5 12.9 5 15.4 5 19.1 7.6 22 12 22Z"/><path d="M12 22c-1.9 0-3-1.3-3-3 0-1.8 1.5-2.8 2-4.5 1 1 1.5 1.8 1.6 2.9.6-.3 1.1-.9 1.3-1.6.8.9 1.1 1.9 1.1 3.2 0 1.7-1.1 3-3 3Z" fill="#FFF3B0"/></symbol>
</svg>

<div class="announce">
  <div class="wrap">
    <nav class="top-nav" aria-label="Site">
      <?php foreach ($h3Top as $h3k => $h3label): ?>
        <a href="<?= h3_link($h3k) ?>" data-nav="<?= h3_e($h3k) ?>"<?= $h3TopOn === $h3k ? ' aria-current="page"' : '' ?>><?= h3_e($h3label) ?></a>
      <?php endforeach; ?>
      <?php if ($h3Uid && $h3First !== ''): ?><strong class="hello hide-sm">Hello, <?= h3_e($h3First) ?>!</strong><?php endif; ?>
    </nav>
    <div class="announce-links">
      <button type="button" class="help-link hide-sm" data-contact aria-controls="contactDrawer" aria-expanded="false"><svg class="ico" aria-hidden="true"><use href="#i-chat"/></svg>Help &amp; contact</button>
      <?php if ($H3_WELCOME_CODE !== ''): ?>
        <span class="hide-sm">First order? Use <strong style="color:var(--gold)"><?= h3_e($H3_WELCOME_CODE) ?></strong> for <?= h3_e($H3_WELCOME_TEXT) ?></span>
      <?php endif; ?>
      <form method="GET" id="currencyForm">
        <?php foreach ($_GET as $h3k => $h3val): if ($h3k === 'set_currency' || is_array($h3val)) continue; ?>
          <input type="hidden" name="<?= h3_e($h3k) ?>" value="<?= h3_e($h3val) ?>">
        <?php endforeach; ?>
        <label class="sr-only" for="currency">Currency</label>
        <select id="currency" name="set_currency" onchange="this.form.submit()">
          <?php foreach (($CURRENCY_INFO ?? []) as $h3code => $h3info): ?>
            <option value="<?= h3_e($h3code) ?>" <?= $h3code === $h3Currency ? 'selected' : '' ?>><?= h3_e($h3info['symbol']) ?> <?= h3_e($h3code) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>
</div>

<header class="site-header<?= isset($H3_NAV[$ACTIVE_NAV]) ? ' has-tab' : '' ?><?= in_array($TAB_STYLE, ['cream', 'dark'], true) ? ' tab-' . $TAB_STYLE : '' ?>">
  <div class="wrap head-row">
    <button class="icon-btn menu-btn" id="menuBtn" aria-label="Open menu" aria-controls="menuDrawer" aria-expanded="false"><svg class="ico"><use href="#i-menu"/></svg></button>

    <a class="brand" href="<?= h3_link('home') ?>" aria-label="Araku Tribe home">
      <?php if ($H3_LOGO_IMAGE !== ''): ?>
        <span class="logo-box"><img src="<?= h3_e($H3_LOGO_IMAGE) ?>" alt="Araku Tribe Coffee" width="1824" height="422"></span>
      <?php else: ?>
        <svg class="brand-mark ico" aria-hidden="true"><use href="#i-bean"/></svg><span class="brand-name">Araku Tribe</span>
      <?php endif; ?>
    </a>

    <nav class="main-nav" aria-label="Main">
      <?php foreach ($H3_NAV as $h3key => $h3label): ?>
        <a href="<?= h3_link($h3key) ?>" data-nav="<?= h3_e($h3key) ?>"<?= $ACTIVE_NAV === $h3key ? ' aria-current="page"' : '' ?>><?= h3_e($h3label) ?></a>
      <?php endforeach; ?>
            <?php if (!empty($H3_MARKET_SHOW) && !empty($H3_MARKETS)): ?>
      <div class="mk-wrap" role="group" aria-label="<?= h3_e($H3_MARKET_LABEL) ?>" style="--mk-n:<?= (int)(count(array_filter($H3_MARKETS, fn($m) => !empty($m['name']))) + (!empty($H3_MARKET_BRAND) ? 1 : 0)) ?>">
        <?php $h3ol = mb_strtoupper($H3_MARKET_LABEL, 'UTF-8'); $h3ofs = mb_strlen($h3ol) <= 5 ? 19 : (mb_strlen($h3ol) <= 7 ? 15 : 12); ?>
        <span class="mk-title">
          <svg class="mk-glass" viewBox="0 0 160 60" role="img" aria-label="<?= h3_e($H3_MARKET_LABEL) ?>" focusable="false">
            <defs>
              <!-- WhatsApp green and parrot green flowing across the arrow, like the HOT DEALS button -->
              <linearGradient id="mkGFill" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="160" y2="0" spreadMethod="repeat">
                <stop offset="0" stop-color="#25D366"/>
                <stop offset=".5" stop-color="#8EE02B"/>
                <stop offset="1" stop-color="#25D366"/>
                <animateTransform attributeName="gradientTransform" type="translate" from="0 0" to="-160 0" dur="2.6s" repeatCount="indefinite"/>
              </linearGradient>
            </defs>
            <path d="M6 18H100V4L156 30L100 56V42H6Z" fill="url(#mkGFill)" stroke="#12803A" stroke-opacity=".6" stroke-width="1.6" stroke-linejoin="round"/>
            <text x="53" y="<?= 30 + (int)round($h3ofs * .36) ?>" text-anchor="middle" font-size="<?= $h3ofs ?>" font-weight="900" fill="#073D12" stroke="#073D12" stroke-width="1.1" stroke-linejoin="round"><?= h3_e($h3ol) ?></text>
          </svg>
        </span>
        <ul class="mk-list">
          <?php if (!empty($H3_MARKET_BRAND)): ?>
          <li><a class="mk-item mk-own" href="<?= h3_e($H3_MARKET_BRAND['url']) ?>" aria-label="<?= h3_e($H3_MARKET_BRAND['name']) ?> store">
            <span class="mk-logo" style="background:<?= h3_e($H3_MARKET_BRAND['bg'] ?? '#FFFFFF') ?>" aria-hidden="true"><img src="<?= h3_e($H3_MARKET_BRAND['logo']) ?>" alt=""></span>
            <span class="mk-name"><?= h3_e($H3_MARKET_BRAND['name']) ?></span>
          </a></li>
          <?php endif; ?>
          <?php foreach ($H3_MARKETS as $h3m): if (empty($h3m['name'])) continue;
                $h3st = $H3_MARKET_STYLE[$h3m['name']] ?? [];
                $h3bg = $h3st['bg'] ?? ($h3m['bg'] ?? '#FFFFFF');
                $h3fg = $h3st['fg'] ?? ($h3m['color'] ?? '#6B3E26'); ?>
          <li><a class="mk-item" href="<?= h3_e($h3m['url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" data-no-spa
                 aria-label="Order on <?= h3_e($h3m['name']) ?> (opens in a new tab)" title="Order on <?= h3_e($h3m['name']) ?>">
            <span class="mk-logo" style="background:<?= h3_e($h3bg) ?>" aria-hidden="true"><?php
              if (!empty($h3m['logo'])): ?><img src="<?= h3_e($h3m['logo']) ?>" alt="" loading="lazy"><?php
              elseif (!empty($h3m['icon'])): ?><svg viewBox="0 0 24 24" focusable="false"><path fill="<?= h3_e($h3fg) ?>" d="<?= h3_e($h3m['icon']) ?>"/></svg><?php
              else: ?><svg class="mk-word" viewBox="0 0 60 24" focusable="false"><text x="30" y="17.5" text-anchor="middle" font-weight="900" font-size="<?= strlen($h3m['word'] ?? $h3m['name']) > 6 ? 15.5 : 19 ?>" fill="<?= h3_e($h3fg) ?>"><?= h3_e($h3m['word'] ?? $h3m['name']) ?></text></svg><?php
              endif; ?></span>
            <span class="mk-name"><?= h3_e($h3m['name']) ?></span>
          </a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </nav>

    <div class="search" role="search">
      <svg class="ico"><use href="#i-search"/></svg>
      <label class="sr-only" for="q">Search products by name, ID or anything about them</label>
      <input id="q" type="search" placeholder="Search products or ID" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="search" aria-controls="suggest" aria-expanded="false" aria-autocomplete="both">
      <div class="suggest" id="suggest" role="listbox" hidden></div>
    </div>

    <a class="hot-btn" data-nav="hotdeals" href="<?= h3_link('hotdeals') ?>"<?= $ACTIVE_NAV === 'hotdeals' ? ' aria-current="page"' : '' ?>><svg class="flame" aria-hidden="true"><use href="#i-flame"/></svg>Hot deals<i class="sparkle s1"></i><i class="sparkle s2"></i></a>

    <a class="agent-btn" data-nav="agent" href="<?= h3_link('agent') ?>"<?= $ACTIVE_NAV === 'agent' ? ' aria-current="page"' : '' ?>><span class="bot-3d" aria-hidden="true"><?= h3_bot_svg() ?><em class="hi">Hi!</em></span><span class="t"><strong>AI Agent</strong><small class="say"><span>Hi! I'm here to help 👋</span><span>Click me! Order in one chat</span></small></span><i class="sparkle s1"></i><i class="sparkle s2"></i><i class="sparkle s3"></i></a>

    <div class="head-actions">
      <?php if ($h3Uid): ?>
        <details class="account">
          <summary class="acct-btn" aria-label="Your account">
            <span class="avatar" aria-hidden="true"><?= h3_e($h3Initial) ?></span>
            <span class="acct-name"><small>Hello,</small><?= h3_e($h3First !== '' ? $h3First : 'there') ?></span>
          </summary>
          <div class="account-menu">
            <div class="acct-top">
              <span class="avatar" aria-hidden="true"><?= h3_e($h3Initial) ?></span>
              <span><strong>Hello, <?= h3_e($h3First !== '' ? $h3First : 'there') ?></strong><?php if ($h3Email !== ''): ?><small><?= h3_e($h3Email) ?></small><?php endif; ?></span>
            </div>
            <nav class="acct-list" aria-label="Account">
              <a href="<?= h3_link('profile') ?>"><svg class="ico"><use href="#i-user"/></svg>My profile</a>
              <a href="<?= h3_link('orders') ?>"><svg class="ico"><use href="#i-orders"/></svg>My orders<small>Track &amp; manage</small></a>
              <a href="<?= h3_link('wishlist') ?>"><svg class="ico"><use href="#i-heart"/></svg>Wishlist<?php if ($h3WishCount): ?><small><?= (int)$h3WishCount ?> saved</small><?php endif; ?></a>
              <a href="<?= h3_link('cart') ?>"><svg class="ico"><use href="#i-bag"/></svg>Cart<?php if ($h3CartCount): ?><small><?= (int)$h3CartCount ?> <?= $h3CartCount === 1 ? 'product' : 'products' ?></small><?php endif; ?></a>
              <hr>
              <a class="out" href="<?= h3_link('logout') ?>"><svg class="ico"><use href="#i-logout"/></svg>Log out</a>
            </nav>
          </div>
        </details>
      <?php else: ?>
        <details class="account">
          <summary class="icon-btn" aria-label="Sign in or create an account"><svg class="ico"><use href="#i-user"/></svg></summary>
          <div class="account-menu">
            <div class="acct-top guest"><span><strong>Welcome to Araku Tribe</strong><small>Sign in to track orders and check out faster</small></span></div>
            <div class="acct-guest">
              <a class="btn btn-primary" href="<?= h3_link('login') ?>">Sign in</a>
              <a class="btn btn-outline" href="<?= h3_link('signup') ?>">Create an account</a>
            </div>
            <nav class="acct-list" aria-label="Account" style="padding-top:0">
              <a href="<?= h3_link('wishlist') ?>"><svg class="ico"><use href="#i-heart"/></svg>Wishlist</a>
              <a href="<?= h3_link('cart') ?>"><svg class="ico"><use href="#i-bag"/></svg>Cart</a>
            </nav>
          </div>
        </details>
      <?php endif; ?>
      <a class="icon-btn" href="<?= h3_link('wishlist') ?>" aria-label="Wishlist"><svg class="ico"><use href="#i-heart"/></svg><span class="count" id="wishCount"<?= $h3WishCount ? '' : ' hidden' ?>><?= $h3WishCount ?></span></a>
      <button class="icon-btn" id="cartBtn" aria-label="Open cart" aria-controls="cartDrawer" aria-expanded="false"><svg class="ico"><use href="#i-bag"/></svg><span class="count" id="cartCount"<?= $h3CartCount ? '' : ' hidden' ?>><?= $h3CartCount ?></span></button>
    </div>
  </div>
</header>

<?php if ($h3Uid): ?>
<!-- Session timeout warning (signed-in customers only) -->
<div class="a3-idle" id="a3Idle" role="alertdialog" aria-modal="true" aria-labelledby="a3IdleTitle" aria-describedby="a3IdleText" hidden>
  <div class="a3-idle-box">
    <h2 id="a3IdleTitle">Are you still there?</h2>
    <p id="a3IdleText">For your security we'll sign you out soon because there's been no activity.</p>
    <span class="a3-idle-time" id="a3IdleTime" aria-live="assertive">2:00</span>
    <div class="a3-idle-bar"><i id="a3IdleBar" style="width:100%"></i></div>
    <div class="a3-idle-btns">
      <button type="button" class="btn btn-primary" id="a3IdleStay">Stay signed in</button>
      <a class="btn btn-outline" href="<?= h3_link('logout') ?>">Sign out now</a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- SPA Dynamic Content Ingestion Container -->
<div id="spa-main">

<script>
/* ============ header3.php script: search, cart drawer, menu, counts. Exposes window.A3 ============ */
(function () {
'use strict';
const H3 = <?= $h3Js ?>;
const $  = (s, el = document) => el.querySelector(s);
const $$ = (s, el = document) => [...el.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const catLabel = k => H3.cats[k] || k;

function setCount(id, n) { const el = document.getElementById(id); if (el) { el.textContent = n; el.hidden = !n; } }
if (!H3.loggedIn) {   // guest wishlist lives in this browser (same key as the old pages)
  try { setCount('wishCount', JSON.parse(localStorage.getItem('guestWishlist') || '[]').length); } catch (e) {}
}

/* ---------- toast ---------- */
function toast(msg) {
  const t = $('#toast'); if (!t) return;
  t.textContent = msg; t.classList.add('show');
  clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('show'), 4000);
}

/* ---------- drawers (cart + phone menu live in footer3.php) ---------- */
let lastFocus = null;
function openDrawer(id) {
  const d = document.getElementById(id); if (!d) return;
  lastFocus = document.activeElement;
  d.classList.add('open'); $('#scrim') && $('#scrim').classList.add('open');
  document.body.style.overflow = 'hidden';
  $$(`[aria-controls="${id}"]`).forEach(b => b.setAttribute('aria-expanded', 'true'));
  setTimeout(() => { const c = $('#' + id + ' [data-close]'); if (c) c.focus(); }, 50);
}
function closeDrawers() {
  $$('.drawer.open').forEach(d => d.classList.remove('open'));
  $('#scrim') && $('#scrim').classList.remove('open'); document.body.style.overflow = '';
  $$('[aria-controls$="Drawer"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
  if (lastFocus) { lastFocus.focus(); lastFocus = null; }
}

/* ---------- cart drawer (reads cart_status.php) ---------- */
// notice = optional { title, note } shown at the top, e.g. after adding to cart
async function refreshCart(notice) {
  const body = $('#cartBody'); if (!body) return;
  try {
    const res = await fetch('cart_status.php', { credentials: 'same-origin' });
    const data = await res.json();
    setCount('cartCount', data.itemCount || 0);
    if (!data.items || !data.items.length) {
      body.innerHTML = `<div class="cart-empty"><h3>Your cart is empty</h3><p>Start with a bag of Araku coffee.</p><a class="btn btn-primary" href="${esc(H3.links.shop)}" data-close>Shop the collection</a></div>`;
      return;
    }
    const top = notice ? `<div class="cart-notice" role="status"><strong>${esc(notice.title)}</strong><span>${esc(notice.note || '')}</span></div>` : '';
    body.innerHTML = top + data.items.map(it => `<div class="cart-line"><img src="${esc(it.image)}" alt="">
      <div><strong>${esc(it.name)}</strong><span>${it.quantity} units</span></div></div>`).join('');
  } catch (e) {
    body.innerHTML = '<p class="cart-empty">Cart details could not load. Open the cart page to see your items.</p>';
  }
}
async function openCart(notice) { openDrawer('cartDrawer'); await refreshCart(notice); }

$('#cartBtn').addEventListener('click', () => openCart());
$('#menuBtn').addEventListener('click', () => openDrawer('menuDrawer'));
document.addEventListener('click', e => {
  if (e.target.id === 'scrim' || e.target.closest('[data-close]')) closeDrawers();
  const acc = $('.account'); if (acc && !acc.contains(e.target)) acc.removeAttribute('open');
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeDrawers(); const acc = $('.account[open]'); if (acc) { acc.removeAttribute('open'); acc.querySelector('summary').focus(); } } });

/* ---------- header search ----------
   Matches product ID, name, category and any extra product info (description, colour, size, price...),
   tolerates one-letter typos, and completes your word INSIDE the textbox as you type
   (the completed part is selected: Right arrow / End accepts it, Backspace removes it). */
const SYN = { coffee:'bag', powder:'bag', beans:'bag', bean:'bag', bag:'bag', bags:'bag',
  cup:'cup', cups:'cup', mug:'mug', mugs:'mug', tshirt:'tshirt', tshirts:'tshirt', 't-shirt':'tshirt', tee:'tshirt', tees:'tshirt', shirt:'tshirt', shirts:'tshirt' };
const GIFT_WORDS = ['gift', 'gifts', 'combo', 'combos', 'set', 'bundle'];
const norm = s => String(s ?? '').toLowerCase();
const squash = s => norm(s).replace(/[^a-z0-9]/g, '');
function lev(a, b) {
  if (Math.abs(a.length - b.length) > 2) return 9;
  const d = Array.from({ length: a.length + 1 }, (_, i) => [i]);
  for (let j = 1; j <= b.length; j++) d[0][j] = j;
  for (let i = 1; i <= a.length; i++) for (let j = 1; j <= b.length; j++)
    d[i][j] = Math.min(d[i-1][j] + 1, d[i][j-1] + 1, d[i-1][j-1] + (a[i-1] === b[j-1] ? 0 : 1));
  return d[a.length][b.length];
}
// prepare every product once (lower-case copies, word lists)
H3.products.forEach(p => {
  p._id = norm(p.id); p._idc = squash(p.id); p._name = norm(p.name);
  p._cat = norm(catLabel(p.cat)); p._kw = norm(p.kw);
  p._hay = [p._id, p._name, p._cat, p._kw].join(' ');
  p._words = [...new Set(p._hay.split(/[^a-z0-9]+/).filter(Boolean))];
});
function tokenScore(p, t) {
  const tc = squash(t);
  if (p._id === t || (tc && p._idc === tc)) return 10;                 // exact product id
  if (p._id.startsWith(t) || (tc.length > 1 && p._idc.startsWith(tc))) return 7;   // start of the id
  if (p._name.startsWith(t)) return 6;                                  // start of the name
  if (p._words.some(w => w.startsWith(t))) return 5;                    // start of any word (name, category, info)
  let syn = SYN[t];
  if (!syn && t.length > 3) { const k = Object.keys(SYN).find(k => k.length > 3 && lev(k, t) <= 1); if (k) syn = SYN[k]; }
  if (syn && syn === p.cat) return 4;                                   // "coffee", "tee", "mugs"...
  if (t.length > 1 && p._hay.includes(t)) return 3;                     // anywhere inside (id pieces, description...)
  if (t.length > 3 && p._words.some(w => lev(w, t) <= 1)) return 1;     // typo
  return 0;
}
function matchScore(p, q) {
  const tokens = norm(q).replace(/[^a-z0-9\s-]/g, ' ').split(/\s+/).filter(Boolean);
  if (!tokens.length) return 1;
  let score = 0;
  for (const t of tokens) { const s = tokenScore(p, t); if (!s) return 0; score += s; }
  return score;
}
const isGiftQuery = q => norm(q).split(/\s+/).some(t => GIFT_WORDS.includes(t));

// words and phrases the textbox can complete to (most common first)
const COMP = new Map();
function addComp(text, w) {
  text = String(text || '').trim(); if (text.length < 2) return;
  const k = norm(text), e = COMP.get(k);
  if (e) e.w += w; else COMP.set(k, { text, w });
}
H3.products.forEach(p => {
  addComp(p.name, 3); addComp(p.id, 2);
  p.name.split(/[^A-Za-z0-9]+/).forEach(w => { if (w.length > 2 && !/^\d+$/.test(w)) addComp(w, 2); });
  (p.kw || '').split(/[^A-Za-z]+/).forEach(w => { if (w.length > 3 && w.length < 20) addComp(w, 1); });
});
Object.values(H3.cats).forEach(c => addComp(c, 4));
const COMP_LIST = [...COMP.entries()];
function completion(typed) {
  const lv = norm(typed);
  if (!lv.trim()) return '';
  let best = null;
  for (const [k, e] of COMP_LIST) {
    if (k.length <= lv.length || !k.startsWith(lv)) continue;
    if (!best || e.w > best.w || (e.w === best.w && k.length < best.k.length)) best = { k, w: e.w, text: e.text };
  }
  return best && norm(best.text) === best.k ? best.text.slice(typed.length) : '';
}
const hilite = (s, q) => {
  const t = norm(q).trim().split(/\s+/).filter(x => x.length > 0).sort((a, b) => b.length - a.length)[0];
  const i = t ? norm(s).indexOf(t) : -1;
  return i < 0 ? esc(s) : esc(s.slice(0, i)) + '<mark>' + esc(s.slice(i, i + t.length)) + '</mark>' + esc(s.slice(i + t.length));
};

(function () {
  const input = $('#q'), box = $('#suggest');
  let active = -1, typed = '';
  function close() { box.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; }
  function exactProduct(q) {
    const t = norm(q).trim(), tc = squash(q);
    return t ? H3.products.find(p => p._id === t || (tc && p._idc === tc)) : null;
  }
  function runSearch(q) {
    close(); q = q.trim(); if (!q) return;
    const hit = exactProduct(q);                                                  // typed a product ID: go straight to it
    if (hit && typeof window.onSiteSearch !== 'function') { location.href = hit.url; return; }
    if (typeof window.onSiteSearch === 'function') return window.onSiteSearch(q); // page shows its own results
    const [path, hash] = (H3.links.shop || 'index.php').split('#');
    location.href = path + '?q=' + encodeURIComponent(q) + (hash ? '#' + hash : '');
  }
  function show(q) {
    q = (q || '').trim();
    if (q.length < 1) return close();
    const items = H3.products.map(p => [p, matchScore(p, q)]).filter(x => x[1] > 0)
      .sort((a, b) => b[1] - a[1] || a[0].name.length - b[0].name.length).slice(0, 8).map(x => x[0]);
    active = -1;
    if (!items.length) {
      box.innerHTML = isGiftQuery(q)
        ? `<div class="suggest-empty">Gifts live in our <a href="${esc(H3.links.combos)}">combos</a>.</div>`
        : `<div class="suggest-empty">No matches for “${esc(q)}”. Try ${Object.keys(H3.cats).map(k => `<a href="${esc(H3.links[k] || '#')}">${esc(H3.cats[k].toLowerCase())}</a>`).join(', ')}.</div>`;
    } else {
      box.innerHTML = items.map((p, i) => `<a class="suggest-item" role="option" id="sg${i}" aria-selected="false" href="${esc(p.url)}">
          <img src="${esc(p.img)}" alt=""><span>${hilite(p.name, q)}<small>${esc(catLabel(p.cat))} · ID ${hilite(p.id, q)}</small></span></a>`).join('')
        + `<button type="button" class="suggest-all">See all results for “${esc(q)}”</button>`;
      $('.suggest-all', box).addEventListener('click', () => runSearch(q));
    }
    box.hidden = false; input.setAttribute('aria-expanded', 'true');
  }
  function highlight(i) {
    const opts = $$('.suggest-item', box); if (!opts.length) return;
    active = (i + opts.length) % opts.length;
    opts.forEach((o, n) => o.setAttribute('aria-selected', n === active));
    input.setAttribute('aria-activedescendant', 'sg' + active);
    opts[active].scrollIntoView({ block: 'nearest' });
  }
  // complete the word inside the textbox: the added letters are selected, so typing replaces them
  function autocomplete() {
    const v = input.value;
    if (!v || input.selectionStart !== v.length || input.selectionEnd !== v.length) return;
    const tail = completion(v);
    if (!tail) return;
    input.value = v + tail;
    input.setSelectionRange(v.length, v.length + tail.length);
  }
  input.addEventListener('input', e => {
    typed = input.value;
    show(typed);
    const t = e.inputType || '';
    if (e.isComposing || t.startsWith('delete') || t === 'historyUndo' || t === 'historyRedo') return;   // never re-complete while deleting
    autocomplete();
  });
  input.addEventListener('focus', () => { typed = input.value; show(input.value); });
  input.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown') { e.preventDefault(); highlight(active + 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
    else if (e.key === 'Escape') close();
    else if (e.key === 'Tab' && input.selectionStart !== input.selectionEnd) {   // Tab accepts the completion too
      e.preventDefault(); const n = input.value.length; input.setSelectionRange(n, n); show(input.value);
    }
    else if (e.key === 'Enter') {
      e.preventDefault();
      const opts = $$('.suggest-item', box);
      if (active >= 0 && opts[active]) location.href = opts[active].href; else if (input.value.trim()) runSearch(input.value);
    }
  });
  document.addEventListener('click', e => { if (!e.target.closest('.search')) close(); });
})();

// clicking the tab of the page you're already on scrolls back to the top instead of reloading
document.addEventListener('click', e => {
  const cur = e.target.closest('.main-nav a[aria-current="page"]');
  if (!cur || e.metaKey || e.ctrlKey || e.shiftKey) return;
  e.preventDefault();
  window.scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
});

<?php if ($h3Restored): ?>setTimeout(() => toast('Welcome back! Your account deletion has been cancelled.'), 400);<?php endif; ?>

// after signing out (logout_user3.php -> index.php?signed_out=1)
(function () {
  const u = new URL(location.href);
  if (!u.searchParams.has('signed_out')) return;
  u.searchParams.delete('signed_out'); history.replaceState(null, '', u.pathname + u.search + u.hash);
  setTimeout(() => toast("You've signed out. See you soon!"), 300);
})();

// signed out automatically after a period of no activity (logout_user3.php?timeout=1 -> index.php?timed_out=1)
(function () {
  const u = new URL(location.href);
  if (!u.searchParams.has('timed_out')) return;
  u.searchParams.delete('timed_out'); history.replaceState(null, '', u.pathname + u.search + u.hash);
  setTimeout(() => toast(`You were signed out after ${H3.idleMinutes} minutes without activity. Please sign in again.`), 300);
})();

/* ---------- SESSION TIMEOUT: warn, then sign out (signed-in customers only) ----------
   Any click, key, scroll or touch counts as activity, in this tab or any other open tab.
   While active, the server session is kept fresh with a light ping (at most once a minute). */
(function () {
  const I = H3.idle, box = document.getElementById('a3Idle');
  if (!I || !box) return;
  const KEY = 'a3_last_active', now = () => Date.now();
  let last = now(), lastPing = now(), warnOn = false, lastWrite = 0;
  const save = () => { try { localStorage.setItem(KEY, String(last)); } catch (e) {} };
  const shared = () => { try { return Math.max(last, +localStorage.getItem(KEY) || 0); } catch (e) { return last; } };
  save();
  async function ping() {
    lastPing = now();
    try {
      const r = await fetch(I.ping + '?t=' + lastPing, { credentials: 'same-origin', cache: 'no-store' });
      const d = await r.json();
      if (d && d.ok === false) kick();
    } catch (e) { /* offline: the timer below still applies */ }
  }
  function kick() { location.replace(I.logout); }
  function activity() {
    if (warnOn) return;                                   // during the warning, only "Stay signed in" counts
    last = now();
    if (last - lastWrite > 4000) { lastWrite = last; save(); }
    if (last - lastPing > 60000) ping();
  }
  function hideWarn() { warnOn = false; box.hidden = true; }
  const fmt = sec => Math.floor(sec / 60) + ':' + String(sec % 60).padStart(2, '0');
  function check() {
    const left = Math.ceil(I.limit - (now() - shared()) / 1000);
    if (left <= 0) { clearInterval(timer); return kick(); }
    if (left <= I.warn) {
      if (!warnOn) { warnOn = true; box.hidden = false; setTimeout(() => document.getElementById('a3IdleStay').focus(), 30); }
      document.getElementById('a3IdleTime').textContent = fmt(left);
      document.getElementById('a3IdleBar').style.width = Math.max(0, left / I.warn * 100) + '%';
    } else if (warnOn) hideWarn();                         // activity in another tab
  }
  document.getElementById('a3IdleStay').addEventListener('click', () => { warnOn = false; activity(); lastWrite = 0; save(); hideWarn(); ping(); });
  ['pointerdown', 'keydown', 'scroll', 'wheel', 'touchstart', 'mousemove'].forEach(ev => addEventListener(ev, activity, { passive: true, capture: true }));
  const timer = setInterval(check, 1000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
})();

// sticky top bar: keep the menu exactly under it (its height changes on phones)
(function () {
  const bar = document.querySelector('.announce'); if (!bar || !document.documentElement.classList.contains('sticky-top')) return;
  const set = () => document.documentElement.style.setProperty('--annh', bar.offsetHeight + 'px');
  set(); addEventListener('resize', set);
  if (window.ResizeObserver) new ResizeObserver(set).observe(bar);
})();

// phones: scroll the tab row so the selected page's tab is visible
(function () {
  const nav = $('.main-nav'), cur = nav && $('[aria-current="page"]', nav);
  if (cur && nav.scrollWidth > nav.clientWidth) nav.scrollLeft = cur.offsetLeft - (nav.clientWidth - cur.offsetWidth) / 2;
})();

(function () {
  // remember every listener a page script registers, so it can be removed when leaving the page
  const pageListeners = [];
  [document, window].forEach(t => {
    const orig = t.addEventListener;
    t.addEventListener = function (type, fn, opts) {
      const cs = document.currentScript;
      if (cs && cs.closest && cs.closest('#spa-container')) pageListeners.push([t, type, fn, opts]);
      return orig.call(t, type, fn, opts);
    };
  });
  function releasePageListeners() {
    pageListeners.splice(0).forEach(([t, type, fn, opts]) => t.removeEventListener(type, fn, opts));
  }
  if (window.__spaReady) return;
  window.__spaReady = true;

  // only these need a real page load (they change the signed-in state or return files)
  const FULL_RELOAD = /(logout|user_login_google|verify_user_otp|user_profile|admin|\.pdf|\.zip)/i;

  function runScripts(box) {
    box.querySelectorAll('script').forEach(old => {
      const s = document.createElement('script');
      [...old.attributes].forEach(a => s.setAttribute(a.name, a.value));
      s.textContent = old.textContent;
      old.replaceWith(s);
    });
  }

  async function load(url, push) {
  const box = document.getElementById('spa-container');
  try {
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
    const next = doc.getElementById('spa-container');
    if (!next) throw new Error('No #spa-container in response');

    window.onSiteSearch = null;
    releasePageListeners();
    box.innerHTML = next.innerHTML;
    runScripts(box);
    syncHeader(res);                         // <- moves the highlight

    const t = res.headers.get('X-Page-Title'); if (t) document.title = decodeURIComponent(t);
    if (next.dataset.title) document.title = next.dataset.title;

    if (push) {
      history.pushState({}, '', res.url);
      const n = new URL(res.url);
      lastPath = n.pathname + n.search;
    }

    const hash = new URL(res.url).hash;
    const target = hash && document.querySelector(hash);
    if (target) target.scrollIntoView();
    else window.scrollTo({ top: 0, behavior: 'instant' });

    if (window.A3) A3.closeDrawers();
  } catch (err) {
    console.error('SPA navigation failed, falling back:', err);
    location.href = url;
  }
}

function syncHeader(res) {
  const top    = res.headers.get('X-Nav-Top') || '';
  const active = res.headers.get('X-Nav-Active') || '';
  const style  = res.headers.get('X-Nav-Tab-Style') || 'brown';
  const set = (a, on) => on ? a.setAttribute('aria-current', 'page') : a.removeAttribute('aria-current');

  document.querySelectorAll('.top-nav a[data-nav]').forEach(a => set(a, a.dataset.nav === top));
  document.querySelectorAll('.main-nav a[data-nav]').forEach(a => set(a, a.dataset.nav === active));
  document.querySelectorAll('.hot-btn, .agent-btn').forEach(a => set(a, a.dataset.nav === active));

  const header = document.querySelector('.site-header');
  if (header) {
    header.classList.toggle('has-tab', !!document.querySelector('.main-nav a[data-nav="' + active + '"]'));
    header.classList.toggle('tab-cream', style === 'cream');
    header.classList.toggle('tab-dark',  style === 'dark');
  }
}

  document.addEventListener('click', e => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = e.target.closest('a[href]');
    if (!a || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-no-spa')) return;
    const u = new URL(a.href, location.href);
    if (u.origin !== location.origin || FULL_RELOAD.test(u.pathname)) return;
    if (u.pathname === location.pathname && u.search === location.search && u.hash) return; // in-page anchor
    if (/^(javascript|mailto|tel):/i.test(a.getAttribute('href'))) return;
    e.preventDefault();
    load(u.href, true);
  });

  let lastPath = location.pathname + location.search;

    addEventListener('popstate', () => {
      const p = location.pathname + location.search;
      if (p === lastPath) return;          // hash-only change: let the browser scroll to the anchor
      lastPath = p;
      load(location.href, false);
    });
})();

/* ---------- Help & contact panel (markup in footer3.php) ---------- */
document.addEventListener('click', e => {
  const b = e.target.closest('[data-contact]'); if (!b) return;
  e.preventDefault(); closeDrawers();
  const sel = document.getElementById('qcTopic'); if (sel && b.dataset.topic) sel.value = b.dataset.topic;
  setTimeout(() => openDrawer('contactDrawer'), 10);
});
document.addEventListener('submit', async e => {
  const f = e.target.closest('#qcForm'); if (!f) return;
  e.preventDefault();
  const need = ['qcName', 'qcEmail', 'qcMsg'].map(id => document.getElementById(id)).find(i => !i.value.trim());
  if (need) { need.focus(); toast('Please fill in your name, email and message.'); return; }
  const btn = f.querySelector('button[type="submit"]'), out = document.getElementById('qcOut');
  btn.disabled = true; btn.textContent = 'Sending…'; out.hidden = true;
  try {
    const fd = new FormData(f); fd.set('ajax', '1');
    const res = await fetch(H3.links.contact || 'coffee_contact3.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    out.hidden = false; out.className = 'qc-out ' + (data.status === 'success' ? 'ok' : 'bad'); out.textContent = data.message;
    if (data.status === 'success') { f.reset(); f.hidden = true; document.getElementById('qcAgain').hidden = false; }
  } catch (err) { out.hidden = false; out.className = 'qc-out bad'; out.textContent = "We couldn't send that. Please email us instead."; }
  finally { btn.disabled = false; btn.textContent = 'Send message'; }
});
document.addEventListener('click', e => {
  if (!e.target.closest('#qcAgain')) return;
  document.getElementById('qcForm').hidden = false; document.getElementById('qcAgain').hidden = true; document.getElementById('qcOut').hidden = true;
});

window.A3 = {
  links: H3.links,
  toast, openDrawer, closeDrawers, refreshCart, openCart,
  setCartCount: n => setCount('cartCount', n),
  setWishCount: n => setCount('wishCount', n),
};
})();
</script>