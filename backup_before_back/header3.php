<?php
/* =====================================================================
   ARAKU TRIBE - header3.php   (fixed version)
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
       A3.go(url)           - opens a page through the SPA (no full reload)
       A3.goBack(fallback)  - goes back to the previous page (falls back to Home)
   and set window.onSiteSearch = q => {...} to show header-search results on that page.

   GLOBAL SEARCH: the header search opens PAGES and PRODUCTS.
       "contact" / "help"        -> Contact page          "bag" / "coffee"  -> Coffee Bags page
       "cups", "mugs", "tshirt"  -> that category page    "offers", "combos", "hot deals", "agent"...
       "<product id>"            -> that product          product names / description words -> suggestions
   While you type, the textbox completes the word in selected text (Right arrow / End / Tab
   accepts, Backspace removes it) and a suggestion list opens underneath. Enter opens the
   highlighted suggestion, or an exact page / product-ID match, or shows search results.

   CLOSE / BACK BUTTONS: put  data-back  (or class "page-close") on any close (X) button of a page
   and it returns to the previous page. Optional fallback:  data-fallback="products3.php"
   Drawer close buttons keep using  data-close  (they only close the drawer).

   AGENT ROBOT: sways continuously and does a full 360 degree spin every 2 minutes
   (settings in the "BOT" constant in the script below).
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
/* page results in the global search (Contact, Bags, Offers...) */
.suggest-head{padding:6px 8px 2px;font-size:11.5px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
.suggest-page .pg-ico{flex:none;width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#FFF4E2;
  background:linear-gradient(135deg,#8A4B2A 0%,#4A2A1A 100%);box-shadow:inset 0 0 0 2px #F4B63D}
.suggest-page .pg-ico .ico{width:21px;height:21px;position:static;color:inherit;stroke-width:2}
.suggest-page .pg-go{margin-left:auto;flex:none;color:var(--brown);display:flex}
.suggest-page .pg-go .ico{width:18px;height:18px;position:static;color:inherit}
/* close (X) button inside the search */
.search-x{display:none;position:absolute;right:7px;top:50%;transform:translateY(-50%);width:34px;height:34px;padding:0;border:0;border-radius:50%;
  align-items:center;justify-content:center;background:transparent;color:var(--brown);z-index:63}
.search-x:hover{background:var(--cream-2)}
.search-x .ico{position:static;width:18px;height:18px;color:inherit;stroke-width:2.4}
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
/* FIX: the robot now has an explicit box (it used to rely on width/height:auto on an absolutely positioned SVG,
   which some browsers size wrongly, so the 3D turn never showed). */
.bot-3d .a3-bot-svg{position:absolute;left:6px;top:5px;width:calc(100% - 12px);height:calc(100% - 12px);transform-origin:50% 50%;transform-style:preserve-3d;will-change:transform;
  animation:bot3d 3.2s ease-in-out infinite,redGlow 2s ease-in-out infinite}
@keyframes bot3d{0%,100%{transform:rotateY(-32deg) translateY(0)}25%{transform:rotateY(0) translateY(-4px)}50%{transform:rotateY(32deg) translateY(0)}75%{transform:rotateY(0) translateY(-4px)}}
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
/* search close (X): desktop shows it while the search is open; phones show it once something is typed */
@media (min-width:681px){
  .search:focus-within input{padding-right:46px}
  .search:focus-within .search-x{display:flex}
}
@media (max-width:680px){
  .search.has-text input{padding-right:46px}
  .search.has-text .search-x{display:flex}
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
/* FIX: "reduce motion" used to switch off EVERY animation, including the robot, so it never turned on
   computers with that setting. The robot is now exempt (everything else still respects the setting). */
@media (prefers-reduced-motion:reduce){
  *:not(.bot-3d):not(.bot-3d *){transition:none!important;scroll-behavior:auto!important;animation:none!important}
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
    </nav>

    <div class="search" role="search">
      <svg class="ico"><use href="#i-search"/></svg>
      <label class="sr-only" for="q">Search pages, products, product ID or anything about them</label>
      <input id="q" type="search" placeholder="Search pages or products" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="search" aria-controls="suggest" aria-expanded="false" aria-autocomplete="both">
      <button type="button" class="search-x" id="searchX" aria-label="Close search"><svg class="ico"><use href="#i-x"/></svg></button>
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

/* ---------- navigation helpers ----------
   go(url)      opens a page THROUGH the SPA (a hidden link is clicked, so the SPA click handler below
                does the fetch; login/logout/etc. still do a full load because of its FULL_RELOAD rule).
   goBack(fb)   returns to the previous page. History entries made by the SPA carry a depth counter
                (history.state.a3); if there is nothing to go back to, it opens fb (default: Home). */
function go(url) {
  const a = document.createElement('a');
  a.href = url; a.style.display = 'none';
  document.body.appendChild(a); a.click(); a.remove();
}
function goBack(fallback) {
  const st = history.state;
  if (st && st.a3 > 0) { history.back(); return; }
  try {
    if (document.referrer && new URL(document.referrer).origin === location.origin && history.length > 1) { history.back(); return; }
  } catch (e) {}
  go(fallback || H3.links.home || 'index.php');
}
// every history entry gets a depth number (0 = the page the visitor landed on)
try { if (!history.state || history.state.a3 == null) history.replaceState(Object.assign({}, history.state || {}, { a3: 0 }), ''); } catch (e) {}

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
  const acc = $('.account');
  if (acc && !acc.contains(e.target)) acc.removeAttribute('open');
  else if (acc && e.target.closest('.account-menu a')) acc.removeAttribute('open');   // FIX: menu closes after you pick a link
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeDrawers(); const acc = $('.account[open]'); if (acc) { acc.removeAttribute('open'); acc.querySelector('summary').focus(); } } });

/* ---------- CLOSE / BACK buttons ----------
   Any element with  data-back,  data-close-page,  .page-close  or  .close-page  goes back to the previous page.
   (Elements that also have data-close only close a drawer, as before.) Optional: data-fallback="url". */
document.addEventListener('click', e => {
  const b = e.target.closest('[data-back],[data-close-page],.page-close,.close-page');
  if (!b || b.hasAttribute('data-close')) return;
  e.preventDefault();
  goBack(b.getAttribute('data-fallback') || '');
});

/* ---------- GLOBAL SEARCH ----------
   Opens PAGES (contact, bags, cups, offers...) and PRODUCTS (by ID, name, category or any extra product info).
   Tolerates one-letter typos and completes your word INSIDE the textbox as you type
   (the completed part is selected: Right arrow / End / Tab accepts it, Backspace removes it). */
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

/* The pages the search can open. k = key in $H3_LINKS, t = title, d = hint, i = icon, w = words people may type.
   Add a line here when you add a page. */
const PAGES = [
  { k:'home',      t:'Home',            d:'Back to the home page',          i:'leaf',   w:['home','homepage','home page','main','start','index','araku tribe','araku'] },
  { k:'new',       t:'New arrivals',    d:'See what is new',                i:'spark',  w:['new','new arrivals','newest','latest','new products','new arrival'] },
  { k:'about',     t:'About us',        d:'Our story and the tribe',        i:'hand',   w:['about','about us','story','our story','tribe','who we are','company'] },
  { k:'shop',      t:'All products',    d:'Browse the whole collection',    i:'bag',    w:['products','product','all products','shop','store','catalogue','catalog','collection','buy'] },
  { k:'bag',       t:'Coffee Bags',     d:'Araku Valley coffee bags',       i:'bean',   w:['bag','bags','coffee','coffee bag','coffee bags','beans','coffee beans','powder','coffee powder','packet'] },
  { k:'cup',       t:'Coffee Cups',     d:'Branded coffee cups',            i:'bean',   w:['cup','cups','coffee cup','coffee cups','paper cup','paper cups'] },
  { k:'mug',       t:'Coffee Mugs',     d:'Branded coffee mugs',            i:'bean',   w:['mug','mugs','coffee mug','coffee mugs'] },
  { k:'tshirt',    t:'T-Shirts',        d:'Araku Tribe T-shirts',           i:'hand',   w:['tshirt','tshirts','t-shirt','t-shirts','t shirt','t shirts','tee','tees','shirt','shirts','apparel','clothes'] },
  { k:'offers',    t:'Offers',          d:'Current offers and discounts',   i:'spark',  w:['offer','offers','discount','discounts','sale','coupon','coupons'] },
  { k:'hotdeals',  t:'Hot deals',       d:'Today\u2019s hottest deals',     i:'flame',  w:['hot deals','hot deal','hotdeals','deals','deal','hot'] },
  { k:'combos',    t:'Combos',          d:'Gift sets and bundles',          i:'box',    w:['combo','combos','gift','gifts','gift set','gift sets','bundle','bundles','set','sets'] },
  { k:'franchise', t:'Franchise',       d:'Become an Araku Tribe partner',  i:'pin',    w:['franchise','franchisee','partner','partnership','business','dealer','dealership','distributor','open a cafe'] },
  { k:'contact',   t:'Contact us',      d:'Help, phone and email',          i:'phone',  w:['contact','contact us','contact page','help','support','reach us','call','phone','email','enquiry','inquiry','customer care','customer service'] },
  { k:'agent',     t:'AI Agent',        d:'Order in one chat',              i:'robot',  w:['agent','ai agent','ai','assistant','chat','chatbot','bot','robot'] },
  { k:'cart',      t:'Cart',            d:'Your shopping cart',             i:'bag',    w:['cart','my cart','basket','shopping cart','checkout','check out'] },
  { k:'wishlist',  t:'Wishlist',        d:'Your saved products',            i:'heart',  w:['wishlist','wish list','my wishlist','favourites','favorites','favourite','favorite','saved','wishes'] },
  { k:'orders',    t:'My orders',       d:'Track and manage your orders',   i:'orders', w:['orders','order','my orders','order history','track','track order','tracking','my order'] },
  { k:'profile',   t:'My profile',      d:'Your account details',           i:'user',   w:['profile','my profile','account','my account','settings'] },
  { k:'login',     t:'Sign in',         d:'Log in to your account',         i:'user',   w:['login','log in','sign in','signin','sign-in'], guestOnly:true },
  { k:'signup',    t:'Create account',  d:'Sign up as a new customer',      i:'user',   w:['signup','sign up','register','registration','create account','new account','join'], guestOnly:true },
  { k:'logout',    t:'Log out',         d:'Sign out of your account',       i:'logout', w:['logout','log out','sign out','signout'], userOnly:true },
  { k:'terms',     t:'Terms & conditions', d:'Our terms of use',            i:'orders', w:['terms','terms and conditions','terms & conditions','conditions','tnc','t&c'] },
  { k:'privacy',   t:'Privacy policy',  d:'How we handle your data',        i:'orders', w:['privacy','privacy policy','policy','data','cookies'] },
].filter(p => H3.links[p.k] && !(p.guestOnly && H3.loggedIn) && !(p.userOnly && !H3.loggedIn));
PAGES.forEach(p => {
  if (H3.cats[p.k]) p.t = H3.cats[p.k];                                   // category pages use your category names
  p.url = H3.links[p.k];
  p._keys = [...new Set([norm(p.t), ...p.w.map(norm)])];
});
const FILLER = new Set(['page','pages','the','go','to','open','show','me','section','tab','link','take','see','view','visit','of','a']);
// score how well a typed query matches a page: 12 exact, 11 same letters, 8 starts with, 5 words start with, 3 typo
function pageScore(pg, q) {
  let t = norm(q).replace(/[^a-z0-9\s&-]/g, ' ').split(/\s+/).filter(Boolean);
  const t2 = t.filter(x => !FILLER.has(x)); if (t2.length) t = t2;
  const phrase = t.join(' '); if (!phrase) return 0;
  const sp = squash(phrase);
  let best = 0;
  for (const k of pg._keys) {
    if (k === phrase) best = Math.max(best, 12);
    else if (sp && squash(k) === sp) best = Math.max(best, 11);
    else if (phrase.length >= 2 && k.startsWith(phrase)) best = Math.max(best, 8);
    else if (t.every(w => k.split(/[\s-]+/).some(kw => kw.startsWith(w)))) best = Math.max(best, 5);
    else if (phrase.length > 3 && lev(k, phrase) <= (phrase.length >= 7 ? 2 : 1)) best = Math.max(best, 3);
  }
  return best;
}
const pageMatches = q => PAGES.map(p => [p, pageScore(p, q)]).filter(x => x[1] > 0).sort((a, b) => b[1] - a[1]);

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
PAGES.forEach(p => { addComp(p.t, 6); p.w.forEach(w => { if (w.length > 2) addComp(w, 3); }); });   // page names complete too
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
  const input = $('#q'), box = $('#suggest'), wrap = input.closest('.search'), xBtn = $('#searchX');
  let active = -1;
  function close() { box.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; }
  function exactProduct(q) {
    const t = norm(q).trim(), tc = squash(q);
    return t ? H3.products.find(p => p._id === t || (tc && p._idc === tc)) : null;
  }
  // decide where an Enter press should go: product ID > exact page > the only page that starts with the text
  function resolve(q) {
    const hit = exactProduct(q);
    if (hit) return hit.url;
    const pm = pageMatches(q);
    if (pm.length) {
      if (pm[0][1] >= 11) return pm[0][0].url;
      const strong = pm.filter(x => x[1] >= 8);
      if (strong.length === 1) return strong[0][0].url;
    }
    return null;
  }
  function finish() { input.value = ''; wrap.classList.remove('has-text'); close(); input.blur(); }
  function runSearch(q) {
    close(); q = q.trim(); if (!q) return;
    const url = resolve(q);
    if (url) { go(url); finish(); return; }                                       // a page or a product ID: open it
    if (typeof window.onSiteSearch === 'function') return window.onSiteSearch(q); // this page shows its own results
    const [path, hash] = (H3.links.shop || 'index.php').split('#');
    go(path + '?q=' + encodeURIComponent(q) + (hash ? '#' + hash : ''));
  }
  function show(q) {
    q = (q || '').trim();
    if (q.length < 1) return close();
    const pages = pageMatches(q).slice(0, 4).map(([p, s]) => ({ type: 'page', p, s }));
    const prods = H3.products.map(p => [p, matchScore(p, q)]).filter(x => x[1] > 0)
      .sort((a, b) => b[1] - a[1] || a[0].name.length - b[0].name.length).slice(0, 8).map(([p, s]) => ({ type: 'product', p, s }));
    const items = [...pages, ...prods].sort((a, b) => b.s - a.s || (a.type === 'page' ? -1 : 1)).slice(0, 8);
    active = -1;
    if (!items.length) {
      box.innerHTML = isGiftQuery(q)
        ? `<div class="suggest-empty">Gifts live in our <a href="${esc(H3.links.combos)}">combos</a>.</div>`
        : `<div class="suggest-empty">No matches for “${esc(q)}”. Try ${Object.keys(H3.cats).map(k => `<a href="${esc(H3.links[k] || '#')}">${esc(H3.cats[k].toLowerCase())}</a>`).join(', ')}, or <a href="${esc(H3.links.contact || '#')}">contact us</a>.</div>`;
    } else {
      box.innerHTML = items.map((it, i) => it.type === 'page'
        ? `<a class="suggest-item suggest-page" role="option" id="sg${i}" aria-selected="false" href="${esc(it.p.url)}">
            <span class="pg-ico"><svg class="ico" aria-hidden="true"><use href="#i-${esc(it.p.i)}"/></svg></span>
            <span>${hilite(it.p.t, q)}<small>Page · ${esc(it.p.d)}</small></span>
            <span class="pg-go"><svg class="ico" aria-hidden="true"><use href="#i-right"/></svg></span></a>`
        : `<a class="suggest-item" role="option" id="sg${i}" aria-selected="false" href="${esc(it.p.url)}">
            <img src="${esc(it.p.img)}" alt=""><span>${hilite(it.p.name, q)}<small>${esc(catLabel(it.p.cat))} · ID ${hilite(it.p.id, q)}</small></span></a>`).join('')
        + (prods.length ? `<button type="button" class="suggest-all">See all product results for “${esc(q)}”</button>` : '');
      const all = $('.suggest-all', box);
      if (all) all.addEventListener('click', () => {
        close(); if (typeof window.onSiteSearch === 'function') return window.onSiteSearch(q);
        const [path, hash] = (H3.links.shop || 'index.php').split('#');
        go(path + '?q=' + encodeURIComponent(q) + (hash ? '#' + hash : ''));
      });
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
    wrap.classList.toggle('has-text', !!input.value);
    show(input.value);                                  // suggestions use only what was really typed
    const t = e.inputType || '';
    if (e.isComposing || t.startsWith('delete') || t === 'historyUndo' || t === 'historyRedo') return;   // never re-complete while deleting
    autocomplete();
  });
  input.addEventListener('focus', () => show(input.value));
  input.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown') { e.preventDefault(); highlight(active + 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
    else if (e.key === 'Escape') { if (!box.hidden) close(); else input.blur(); }
    else if (e.key === 'Tab' && input.selectionStart !== input.selectionEnd) {   // Tab accepts the completion too
      e.preventDefault(); const n = input.value.length; input.setSelectionRange(n, n); show(input.value);
    }
    else if (e.key === 'Enter') {
      e.preventDefault();
      const opts = $$('.suggest-item', box);
      if (active >= 0 && opts[active]) { go(opts[active].getAttribute('href')); finish(); }
      else if (input.value.trim()) runSearch(input.value);
    }
  });
  // choosing a suggestion with the mouse/finger: the SPA handles the link, we just tidy the search
  box.addEventListener('click', e => { if (e.target.closest('a.suggest-item')) setTimeout(finish, 0); });
  // the X: clear and close the search (mousedown keeps focus so it works on every browser)
  xBtn.addEventListener('mousedown', e => e.preventDefault());
  xBtn.addEventListener('click', () => { input.value = ''; wrap.classList.remove('has-text'); close(); input.blur(); });
  document.addEventListener('click', e => { if (!e.target.closest('.search')) close(); });
})();

/* ---------- AGENT ROBOT: full 360 degree spin every 2 minutes ----------
   The robot also sways all the time (CSS). This adds the big spin on top, using the Web Animations API,
   so it works even when CSS animations are switched off by the browser/OS or the admin "3D robot" option.
   first = first spin after the page loads (ms, 0 = none)   every = time between spins (ms) */
(function () {
  const BOT = { first: 3500, every: 2 * 60 * 1000, duration: 1700, respectReducedMotion: false };
  const svg = document.querySelector('.agent-btn .bot-3d .a3-bot-svg');
  if (!svg || !svg.animate) return;
  function spin() {
    if (document.hidden) return;
    if (BOT.respectReducedMotion && matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    svg.animate([
      { transform: 'rotateY(0deg) scale(1)' },
      { transform: 'rotateY(180deg) scale(1.15)', offset: .5 },
      { transform: 'rotateY(360deg) scale(1)' }
    ], { duration: BOT.duration, easing: 'ease-in-out' });
  }
  if (BOT.first > 0) setTimeout(spin, BOT.first);
  setInterval(spin, BOT.every);
  window.A3spinBot = spin;      // handy for testing: type A3spinBot() in the browser console
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
  u.searchParams.delete('signed_out'); history.replaceState(history.state, '', u.pathname + u.search + u.hash);
  setTimeout(() => toast("You've signed out. See you soon!"), 300);
})();

// signed out automatically after a period of no activity (logout_user3.php?timeout=1 -> index.php?timed_out=1)
(function () {
  const u = new URL(location.href);
  if (!u.searchParams.has('timed_out')) return;
  u.searchParams.delete('timed_out'); history.replaceState(history.state, '', u.pathname + u.search + u.hash);
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

/* ============ SPA (unchanged, except that each pushed history entry now stores a depth number
   {a3: n} so goBack() knows when there is a previous page inside the site) ============ */
(function () {
  if (window.__spaReady) return;
  window.__spaReady = true;

  const FULL_RELOAD = /(logout|login|signup|user_profile|admin|\.pdf|\.zip)/i; // pages that change header state

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
    box.innerHTML = next.innerHTML;
    runScripts(box);
    syncHeader(res);                         // <- moves the highlight

    const t = res.headers.get('X-Page-Title'); if (t) document.title = decodeURIComponent(t);
    if (next.dataset.title) document.title = next.dataset.title;

    if (push) {
      history.pushState({ a3: ((history.state && history.state.a3) || 0) + 1 }, '', res.url);
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
  toast, openDrawer, closeDrawers, refreshCart, openCart, go, goBack,
  setCartCount: n => setCount('cartCount', n),
  setWishCount: n => setCount('wishCount', n),
};
})();
</script>