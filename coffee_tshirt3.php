<?php
/* =====================================================================
   ARAKU TRIBE - coffee_tshirt3.php  (T-Shirts)
   Uses header3.php + footer3.php; everything else is in this file.
   Same backend as the old coffee_tshirt.php: products WHERE category = 'tshirt',
   wishlist, add_to_cart.php (min 100 units), cart_status.php, wishlist_toggle.php.
   ===================================================================== */
$PAGE_CATEGORY = 'tshirt';   // which range this page lists
session_start();
$loggedInUserId = $_SESSION['user_id'] ?? null;

include 'db1.php';
mysqli_report(MYSQLI_REPORT_OFF);            // we check errors ourselves below
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
$conn->set_charset('utf8mb4');

require_once 'currency_helper.php';
$displayCurrency = resolve_display_currency($conn);
$currencyInfo    = $CURRENCY_INFO[$displayCurrency];

/* ---------------------------------------------------------------------
   SETTINGS - edit these freely
   --------------------------------------------------------------------- */
$MIN_QTY          = 100;    // site policy: minimum order per product
$MASK_PRICES      = false;  // true = show "####" like the current index.php
$LOW_STOCK_AT     = 300;    // show "Few left" below this stock level
$NEW_PER_CATEGORY = 2;      // newest N products (by product_id) per category get the "New" badge
$BEST_PER_CATEGORY = 3;   // top N best sellers (by units ordered) per category get the \"Best seller\" badge
$READY_STOCK_AT   = 500;  // \"Ready for 500+ units\" filter: products with at least this much stock
$ORDER_ITEMS_TABLE = 'order_items'; // used for real Best Sellers; section hides if not found

// OFFERS - real discounts only. Everything listed here is shown to customers, so add_to_cart.php /
// cart.php must charge the same price. The struck-through price is always your real current price.
//   'ATMUG001' => ['type' => 'percent', 'value' => 20],   // 20% off the current price
//   'ATBAG003' => ['type' => 'bogo'],                     // buy 1 get 1 free (checkout must add the free units)
$OFFERS = [];   // filled from the database (offers table) below - add offers there, not here
// While $OFFERS is empty, localhost shows labelled SAMPLE offers so you can see the design.
// Samples never appear on a real domain.
$PREVIEW_OFFERS_ON_LOCALHOST = false;   // offers now come from the database
$COMBO_DISCOUNT_PERCENT = 0;   // real % off each combo set (checkout must apply it); 0 = normal combined price
$WELCOME_CODE = '';            // e.g. 'WELCOME10' - set it only once cart.php really accepts the code
$WELCOME_TEXT = '10% off your first order';
$AI_AGENT_URL = 'araku_agent3.php';
$COMBO_PAGE   = 'combos3.php';   // where the "Make my combo" button (top right) goes - change it if your combo page has another name

// Order of this array = display order everywhere (BA doc: Bags, Cups, Mugs, T-Shirts)
$CATEGORIES = [
    'bag'    => ['label' => 'Coffee Bags', 'page' => 'coffee_bag3.php',    'blurb' => 'Araku Valley coffee powder, packed in eco-friendly wheat bags.',
                 'kicker' => 'Fresh from the valley',  'perks' => ['Araku Valley origin', 'Hand-picked beans', 'Eco-friendly wheat bag']],
    'cup'    => ['label' => 'Coffee Cups', 'page' => 'coffee_cup3.php',    'blurb' => 'Branded cups for cafés, takeaway counters and events.',
                 'kicker' => 'Made for takeaway',      'perks' => ['Araku Tribe branding', 'Built for takeaway', 'Bulk orders for cafés']],
    'mug'    => ['label' => 'Coffee Mugs', 'page' => 'coffee_mug3.php',    'blurb' => 'Everyday mugs made for a slow morning brew.',
                 'kicker' => 'For the slow morning',   'perks' => ['Araku Tribe design', 'Everyday use', 'Pairs with our coffee']],
    'tshirt' => ['label' => 'T-Shirts',    'page' => 'coffee_tshirt3.php', 'blurb' => 'Tribe tees for people who plan their day around coffee.',
                 'kicker' => 'Wear the Tribe',         'perks' => ['Araku Tribe design', 'Everyday wear', 'Pairs with a mug']],
];


/* ---------------------------------------------------------------------
   PRODUCTS
   --------------------------------------------------------------------- */
$products = [];
$byCat    = [];
// products + their live offer from the database (offers table, linked by products.offer_code)
$OFFER_SQL = "SELECT p.*, o.discount_type AS o_type, o.discount_value AS o_value FROM products p
    LEFT JOIN offers o ON o.offer_code = p.offer_code AND o.is_active = 1
     AND (o.starts_at IS NULL OR o.starts_at <= NOW()) AND (o.ends_at IS NULL OR o.ends_at > NOW())
    ORDER BY p.category ASC, p.product_id DESC";
try { $res = $conn->query($OFFER_SQL); } catch (\Throwable $e) { $res = false; }
if (!$res) $res = $conn->query("SELECT * FROM products ORDER BY category ASC, product_id DESC");   // offers tables missing: no offers
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['o_type'])) $OFFERS[(string)$row['product_id']] = ['type' => $row['o_type'], 'value' => (float)$row['o_value']];
        $cat = strtolower($row['category']);
        // same rule as index.php: image filename must match the product ID
        $pattern = '/^' . preg_quote($row['product_id'], '/') . '\.(jpg|jpeg|png|gif|webp)$/i';
        if (!preg_match($pattern, $row['image'])) continue;

        $meta = $CATEGORIES[$cat] ?? ['label' => ucwords(str_replace('_', ' ', $cat)), 'page' => 'index.php#shop'];
        $p = [
            'id'    => (string)$row['product_id'],
            'name'  => (string)$row['product_name'],
            'cat'   => $cat,
            'price' => round((float)$row['price'] * $currencyInfo['rate'], 2),
            'stock' => (int)$row['quantity'],
            'img'   => "img/shop/{$row['category']}/{$row['image']}",
            'url'   => $meta['page'] . '#product-' . rawurlencode($row['product_id']),
            'isNew' => !empty($row['is_new']),          // optional manual flag column products.is_new (1 = New)
            'isBest'=> !empty($row['is_best_seller']),  // optional manual flag column products.is_best_seller (1 = Best seller)
        ];
        $byCat[$cat][] = count($products);
        $products[] = $p;
    }
}
// "New" = newest N per category (rows are already sorted product_id DESC)
foreach ($byCat as $cat => $idxs) {
    foreach (array_slice($idxs, 0, $NEW_PER_CATEGORY) as $i) $products[$i]['isNew'] = true;
}

// Best sellers from real order data, only if the table exists
$bestIds = [];
$t = $conn->real_escape_string($ORDER_ITEMS_TABLE);
$check = $conn->query("SHOW TABLES LIKE '$t'");
if ($check && $check->num_rows > 0) {
    // top $BEST_PER_CATEGORY sellers in EACH category, so every range page has real best sellers
    $bs = $conn->query("SELECT product_id, SUM(quantity) AS sold FROM `$t` GROUP BY product_id HAVING sold > 0 ORDER BY sold DESC");
    $catOf = [];
    foreach ($products as $pp) $catOf[$pp['id']] = $pp['cat'];
    $taken = [];
    if ($bs) while ($r = $bs->fetch_assoc()) {
        $pid = (string)$r['product_id'];
        $c = $catOf[$pid] ?? null;
        if ($c === null || ($taken[$c] ?? 0) >= $BEST_PER_CATEGORY) continue;
        $taken[$c] = ($taken[$c] ?? 0) + 1;
        $bestIds[] = $pid;
    }
}
foreach ($products as &$p) { if (in_array($p['id'], $bestIds, true)) $p['isBest'] = true; }
unset($p);

// Category tiles: count + first product image as cover
$catList = [];
foreach ($CATEGORIES as $key => $meta) {
    $idxs = $byCat[$key] ?? [];
    $catList[] = $meta + [
        'key'   => $key,
        'count' => count($idxs),
        'cover' => $idxs ? $products[$idxs[0]]['img'] : '',
    ];
}


/* ---------------------------------------------------------------------
   OFFERS (real, or labelled samples on localhost)
   --------------------------------------------------------------------- */
$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1'], true);
$offersPreview = false;
$offerMap = $OFFERS;
if (!$offerMap && $PREVIEW_OFFERS_ON_LOCALHOST && $isLocal) {
    $offersPreview = true;
    $tiers = [10, 15, 20, 25];
    foreach ($products as $p) {
        if ($p['stock'] <= 0) continue;
        $seed = crc32($p['id']);
        if ($seed % 3) continue;                       // roughly one in three products
        $offerMap[$p['id']] = ($seed >> 4) % 4 === 0 ? ['type' => 'bogo'] : ['type' => 'percent', 'value' => $tiers[($seed >> 6) % 4]];
        if (count($offerMap) >= 8) break;
    }
}
foreach ($products as &$p) {
    $o = $offerMap[$p['id']] ?? null;
    if (!$o) continue;
    if (($o['type'] ?? '') === 'percent' && ($o['value'] ?? 0) > 0 && $o['value'] < 100) {
        $p['offer'] = ['type' => 'percent', 'value' => (int)$o['value'], 'final' => round($p['price'] * (1 - $o['value'] / 100), 2)];
    } elseif (($o['type'] ?? '') === 'bogo') {
        $p['offer'] = ['type' => 'bogo'];
    }
}
unset($p);

/* ---------------------------------------------------------------------
   CART + WISHLIST STATE
   --------------------------------------------------------------------- */
$cart = [];
if ($loggedInUserId) {
    $st = $conn->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ?");
    if ($st) {
        $st->bind_param("i", $loggedInUserId);
        $st->execute();
        $r = $st->get_result();
        while ($row = $r->fetch_assoc()) $cart[(string)$row['product_id']] = (int)$row['quantity'];
        $st->close();
    }
} elseif (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $pid => $item) $cart[(string)$pid] = (int)($item['quantity'] ?? 0);
}

$wishlist = [];
if ($loggedInUserId) {
    $st = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    if ($st) {
        $st->bind_param("i", $loggedInUserId);
        $st->execute();
        $r = $st->get_result();
        while ($row = $r->fetch_assoc()) $wishlist[] = (string)$row['product_id'];
        $st->close();
    }
}

$currencyOptions = [];
foreach ($CURRENCY_INFO as $code => $info) $currencyOptions[] = ['code' => $code, 'symbol' => $info['symbol']];


// Ready-made combos that include this range (so "Add set" works from here too)
$combos = [];
$bagIdx = $byCat['bag'] ?? [];
foreach ([['mug', 'Coffee + mug'], ['cup', 'Coffee + cup'], ['tshirt', 'Coffee + tee']] as $n => [$cat, $title]) {
    if (isset($bagIdx[$n]) && !empty($byCat[$cat])) $combos[] = ['title' => $title, 'ids' => [$products[$bagIdx[$n]]['id'], $products[$byCat[$cat][0]]['id']]];
}
$conn->close();

$page = [
    'user'          => ['loggedIn' => (bool)$loggedInUserId, 'name' => $_SESSION['user_name'] ?? ''],
    'currency'      => ['code' => $displayCurrency, 'symbol' => $currencyInfo['symbol'], 'options' => $currencyOptions],
    'maskPrices'    => $MASK_PRICES,
    'minQty'        => $MIN_QTY,
    'lowStockAt'    => $LOW_STOCK_AT,
    'readyAt'       => $READY_STOCK_AT,
    'categories'    => $catList,
    'products'      => $products,
    'bestIds'       => $bestIds,
    'combos'        => $combos,
    'pageCat'       => $PAGE_CATEGORY,
    'cart'          => (object)$cart,
    'wishlist'      => $wishlist,
];
$pageJson = json_encode($page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$catMeta  = $CATEGORIES[$PAGE_CATEGORY] ?? ['label' => 'T-Shirts', 'perks' => []];

$PAGE_TITLE       = 'T-shirts - Araku Tribe';
$PAGE_DESCRIPTION = 'Araku Tribe tees for people who plan their day around coffee.';
$ACTIVE_NAV       = $PAGE_CATEGORY;
$TAB_STYLE        = 'brown';
$H3_BOT_FLY       = true;     // the AI Agent robot flies in here (at most every 5 minutes)

// Check Dual Mode (SPA AJAX request)
$is_spa_request = (!empty($_SERVER['HTTP_X_SPA']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['spa']));

ob_start();
include __DIR__ . '/header3.php';
?>
<style>
/* ============ coffee_tshirt3.php: product cards + quick view (same as home) ============ */
.section{padding:88px 0}
.section-cream{background:var(--cream)}
.section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:36px;flex-wrap:wrap}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:24px 20px}
.card{--tint:#F3E9DD;position:relative;display:flex;flex-direction:column;min-width:0;background:#fff;border-radius:22px;padding:10px;
  box-shadow:0 1px 2px rgba(59,35,23,.06),0 14px 34px -22px rgba(59,35,23,.45);transition:transform .35s cubic-bezier(.2,.7,.2,1),box-shadow .35s}
.card:hover{transform:translateY(-5px);box-shadow:0 2px 4px rgba(59,35,23,.06),0 26px 46px -24px rgba(59,35,23,.55)}
.card[data-cat="bag"]{--tint:#EEDFCF}
.card[data-cat="cup"]{--tint:#F6EEE3}
.card[data-cat="mug"]{--tint:#E2ECE5}
.card[data-cat="tshirt"]{--tint:#EDE5DA}
.card-media{position:relative;aspect-ratio:4/5;border-radius:16px;overflow:hidden;background:var(--tint)}
.card-media>a{display:block;height:100%}
.card-media>a img{width:100%;height:100%;object-fit:contain;padding:11%;mix-blend-mode:multiply;transition:transform .6s cubic-bezier(.2,.7,.2,1)}
.card:hover .card-media>a img{transform:scale(1.07)}
.badges{position:absolute;top:12px;left:12px;display:flex;flex-direction:column;gap:6px;align-items:flex-start;z-index:1}
.badge{font-size:12px;font-weight:700;padding:5px 10px;border-radius:999px;background:#fff;color:var(--brown)}
.badge-new{background:var(--green);color:#fff}
.badge-best{background:var(--gold);color:var(--brown-deep)}
.badge-deal{background:var(--orange-cta);color:#fff}
.badge-low{background:#fff;color:var(--red)}
.badge-out{background:var(--ink);color:#fff}
.wish{position:absolute;top:10px;right:10px;z-index:1;width:38px;height:38px;border-radius:999px;border:0;background:rgba(255,255,255,.9);display:flex;align-items:center;justify-content:center;color:var(--brown);transition:transform .2s}
.wish:hover{transform:scale(1.08)}
.wish .ico{width:19px;height:19px}
.wish[aria-pressed="true"]{color:var(--red)}
.wish[aria-pressed="true"] .ico{fill:currentColor}
/* cart icon: sits right under the wishlist heart, shows how many units are already in the cart */
.cart-ico{position:absolute;top:54px;right:10px;z-index:1;width:38px;height:38px;border-radius:999px;border:0;background:rgba(255,255,255,.9);display:flex;align-items:center;justify-content:center;color:var(--brown);transition:transform .2s,background-color .2s}
.cart-ico::before{content:"";width:19px;height:19px;background:currentColor;-webkit-mask:var(--bag-ico) center/contain no-repeat;mask:var(--bag-ico) center/contain no-repeat}
.cart-ico:hover:not(:disabled){transform:scale(1.08)}
.cart-ico.in-cart{background:var(--green);color:#fff}
.cart-ico:disabled{opacity:.55;cursor:not-allowed}
.cart-qty{position:absolute;right:-5px;bottom:-5px;min-width:20px;height:20px;padding:0 5px;border-radius:999px;background:var(--brown-deep);color:#fff;font-size:11px;font-weight:700;line-height:1;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 2px #fff}
.quick{position:absolute;left:50%;bottom:12px;z-index:1;transform:translate(-50%,8px);border:0;border-radius:999px;background:rgba(255,255,255,.95);padding:9px 18px;font-weight:600;font-size:13.5px;color:var(--brown);white-space:nowrap;opacity:0;transition:opacity .2s,transform .2s;box-shadow:0 6px 16px -8px rgba(59,35,23,.5)}
.card:hover .quick,.quick:focus-visible{opacity:1;transform:translate(-50%,0)}
.card-body{padding:14px 6px 4px;display:flex;flex-direction:column;flex:1}
.card-cat{display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;color:var(--muted);margin:0 0 5px}
.card-cat::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--brown-soft)}
.card[data-cat="mug"] .card-cat::before{background:var(--green)}
.card[data-cat="cup"] .card-cat::before{background:var(--gold)}
.card-title{font-family:var(--serif);font-weight:400;font-size:1.08rem;color:var(--brown-deep);line-height:1.3;margin:0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.card-title a{text-decoration:none}
.card-title a:hover{color:var(--orange-cta)}
.card-id{margin:4px 0 0;font-size:12px;letter-spacing:.02em;color:var(--muted)}
.qv-id{margin:0;font-size:13px;letter-spacing:.02em;color:var(--muted)}
.price{margin:8px 0 0;font-weight:700;font-size:17px;color:var(--ink);display:flex;align-items:baseline;flex-wrap:wrap;gap:4px 8px}
.price span{font-weight:500;font-size:13px;color:var(--muted)}
.price s{font-weight:500;font-size:14px;color:var(--muted)}
.price .deal{color:var(--orange-cta)}
.price-note{margin:3px 0 0;font-size:12.5px;font-weight:600;color:var(--green)}
.moq{margin:3px 0 12px;font-size:12.5px;color:var(--muted)}
/* the text block sits in the centre of the card, while the lines inside it stay left-aligned */
.card-info{width:fit-content;max-width:100%;margin:0 auto;text-align:left}
.add{margin-top:auto;width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 14px;border-radius:999px;border:0;background:var(--brown-deep);color:var(--cream);font-weight:700;font-size:14.5px;transition:background-color .2s}
.add::before{content:"";width:17px;height:17px;background:currentColor;-webkit-mask:var(--bag-ico) center/contain no-repeat;mask:var(--bag-ico) center/contain no-repeat}
.add:hover{background:var(--orange-cta);color:#fff}
.add.in-cart{background:var(--green);color:#fff}
.add:disabled{background:var(--cream-2);color:var(--muted);cursor:not-allowed}
.add:disabled::before{display:none}
.head-side{display:flex;align-items:center;gap:18px}
.eyebrow{display:inline-flex;align-items:center;gap:8px;margin:0 0 10px;font-weight:600;font-size:14px;color:var(--orange-cta)}
.eyebrow .ico{width:16px;height:16px}
.preview-note{display:flex;gap:10px;align-items:flex-start;margin:0 0 24px;padding:12px 16px;border-radius:var(--r-md);background:#FFF1D6;border:1px dashed #D9A441;color:#6A4A12;font-size:14px}
.preview-note strong{white-space:nowrap}
.chips{display:flex;gap:8px;flex-wrap:wrap;margin:-12px 0 28px}
.chip{border:1.5px solid var(--line);background:#fff;border-radius:999px;padding:7px 14px;font-weight:600;font-size:13.5px;color:var(--ink)}
.chip:hover{border-color:var(--brown-soft)}
.chip[aria-pressed="true"]{background:var(--cream);border-color:var(--brown);color:var(--brown)}
.chip[aria-pressed="true"]::before{content:"✓ "}
.empty{grid-column:1/-1;text-align:center;padding:48px 16px;background:var(--cream);border-radius:var(--r-lg)}
.empty h3{font-size:1.4rem}
.empty p{color:var(--muted)}
.more-row{display:flex;justify-content:center;margin-top:40px}
.combo-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
.combo{position:relative;display:flex;flex-direction:column;background:#fff;border-radius:24px;padding:12px 12px 22px;box-shadow:0 1px 2px rgba(59,35,23,.06),0 14px 34px -22px rgba(59,35,23,.45);transition:transform .35s cubic-bezier(.2,.7,.2,1)}
.combo:hover{transform:translateY(-5px)}
.combo-imgs{position:relative;display:grid;grid-template-columns:1fr 1fr;gap:6px;border-radius:18px;overflow:hidden}
.combo-imgs div{aspect-ratio:1/1;background:#EEDFCF}
.combo-imgs div+div{background:#E2ECE5}
.combo-imgs img{width:100%;height:100%;object-fit:contain;padding:12%;mix-blend-mode:multiply}
.combo-plus{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:40px;height:40px;border-radius:999px;background:#fff;color:var(--brown);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:20px;box-shadow:var(--shadow)}
.combo .badges{top:24px;left:24px}
.combo h3{font-size:1.35rem;margin:18px 10px 0}
.combo ul{list-style:none;padding:0;margin:8px 10px 14px;font-size:14px;color:var(--muted)}
.combo li{padding:2px 0}
.combo .price{margin:0 10px 16px}
.combo .add{width:auto;margin:auto 10px 0}
dialog.qv{border:0;border-radius:var(--r-lg);padding:0;width:min(860px,94vw);max-height:92vh;box-shadow:0 30px 80px -20px rgba(36,24,17,.5)}
dialog.qv::backdrop{background:rgba(36,24,17,.5)}
.qv-inner{display:grid;grid-template-columns:1fr 1fr}
.qv-img{background:var(--cream);aspect-ratio:1/1}
.qv-img img{width:100%;height:100%;object-fit:cover}
.qv-info{padding:34px 30px;display:flex;flex-direction:column;gap:6px;position:relative}
.qv-close{position:absolute;top:12px;right:12px}
.qv-info h2{font-size:1.7rem;padding-right:36px}
.qv-info .price{font-size:1.4rem}
.stock{font-size:14px;font-weight:600;color:var(--green)}
.stock.low{color:var(--red)}
.stepper{display:inline-flex;align-items:center;border:1.5px solid var(--line);border-radius:999px;margin:14px 0 6px;width:max-content}
.stepper button{width:44px;height:44px;border:0;background:none;font-size:20px;color:var(--brown)}
.stepper button:disabled{color:var(--line);cursor:not-allowed}
.stepper output{min-width:70px;text-align:center;font-weight:700}
.qv-info .btn{margin-top:10px}
.qv-info .link{margin-top:14px;align-self:flex-start}
@media (max-width:1260px){
  .grid{grid-template-columns:repeat(3,1fr)}
}
@media (max-width:860px){
  .combo-grid{grid-template-columns:1fr 1fr}
  .qv-inner{grid-template-columns:1fr}
  .qv-img{aspect-ratio:4/3}
}
@media (max-width:680px){
  .card{padding:8px;border-radius:18px}
  .card-title{font-size:1rem}
  .add{font-size:13.5px;padding:11px 10px}
  .combo-grid{grid-template-columns:1fr}
  .section{padding:60px 0}
  .grid{grid-template-columns:repeat(2,1fr);gap:22px 12px}
  .quick{display:none}
}
/* ============ top line: "Home / All products" - the navigation line IS the page heading ============ */
.crumb-bar{background:var(--cream);padding:14px 0}
.crumbs{display:flex;flex-wrap:wrap;align-items:baseline;gap:10px;margin:0;font-size:14px;color:var(--muted)}
.crumb-bar .crumbs a,.crumb-bar .crumbs a:link,.crumb-bar .crumbs a:visited{color:var(--cream)!important;background:var(--brown)!important;text-decoration:none;font-weight:600;padding:5px 14px;border-radius:999px;transition:background-color .2s,color .2s}
.crumb-bar .crumbs a:hover,.crumb-bar .crumbs a:focus-visible{background:var(--brown-deep)!important;color:var(--cream)!important}
.crumbs .sep{color:var(--muted);opacity:.7}
.crumbs h1{margin:0;font-family:var(--serif);font-weight:400;font-size:clamp(1.35rem,2.4vw,1.75rem);line-height:1.1;color:var(--brown-deep)}
.crumb-row{display:flex;align-items:center;justify-content:space-between;gap:10px 20px;flex-wrap:wrap}
.crumb-actions{display:flex;align-items:center;gap:10px;flex:none}
/* "Make my combo": bright orange glow, a little larger than before, with a slow shine sweep */
.combo-btn{position:relative;overflow:hidden;display:inline-flex;align-items:center;gap:9px;flex:none;min-height:46px;padding:0 22px;border-radius:999px;
  background:linear-gradient(135deg,#F9731A 0%,#EE5A1A 50%,#DC3F14 100%);color:#fff;font-weight:800;font-size:15.5px;line-height:1.2;letter-spacing:.01em;text-decoration:none;white-space:nowrap;
  text-shadow:0 1px 2px rgba(110,28,0,.4);box-shadow:inset 0 0 0 1px rgba(255,255,255,.28),0 10px 24px -8px rgba(238,90,26,.85),0 0 22px -4px rgba(249,115,26,.6);
  transition:transform .2s,box-shadow .2s,filter .2s}
.combo-btn:hover{color:#fff;transform:translateY(-2px);filter:brightness(1.07);box-shadow:inset 0 0 0 1px rgba(255,255,255,.34),0 14px 28px -8px rgba(238,90,26,.9),0 0 28px -2px rgba(249,115,26,.75)}
.combo-btn::after{content:"";position:absolute;top:0;bottom:0;left:-60%;width:38%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.5),transparent);transform:skewX(-20deg);animation:comboShine 3.8s ease-in-out infinite;pointer-events:none}
@keyframes comboShine{0%,55%{left:-60%}100%{left:135%}}
.combo-btn .ico{width:18px;height:18px}
@media (prefers-reduced-motion:reduce){ .combo-btn::after{animation:none;display:none} }
/* "Close": same height as the combo button, plain outline */
.close-btn{display:inline-flex;align-items:center;gap:8px;flex:none;min-height:46px;padding:0 20px;border-radius:999px;border:2px solid var(--brown-deep);background:#fff;color:var(--brown-deep);
  font:inherit;font-weight:700;font-size:15.5px;line-height:1.2;text-decoration:none;white-space:nowrap;cursor:pointer;transition:background-color .2s,color .2s}
.close-btn:hover{background:var(--brown-deep);color:#fff}
.close-btn[hidden]{display:none}
.close-btn .ico{width:17px;height:17px;stroke-width:2.2}
@media (max-width:520px){.crumb-actions{flex-wrap:wrap}}

/* ============ shop layout: left filters + results ============ */
/* .crumb-bar > .wrap and .shop are BOTH ".wrap", so the navigation line and the filter + product block
   always have exactly the same width and the same left and right edges */
.shop{display:grid;grid-template-columns:272px minmax(0,1fr);gap:32px;align-items:start;padding-top:24px;padding-bottom:88px}
.filters{position:sticky;top:96px;max-height:calc(100vh - 112px);overflow:auto;background:#fff;border:1px solid var(--line);border-radius:22px;padding:4px 20px 18px;scrollbar-width:thin}
.f-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:16px 0 12px}
.f-head h2{font-size:1.3rem}
.f-clear{border:0;background:none;padding:4px 0;font-weight:600;font-size:14px;color:var(--orange-cta)}
.f-clear:disabled{color:var(--muted);opacity:.6;cursor:default}
.f-close{display:none}
.f-search{position:relative;margin:2px 0 6px}
.f-search input{width:100%;height:42px;border:1.5px solid var(--line);border-radius:999px;padding:0 14px 0 38px;background:var(--cream);font:inherit;font-size:14.5px;color:var(--ink)}
.f-search input:focus{outline:none;border-color:var(--brown-soft);background:#fff}
.f-search .ico{position:absolute;left:13px;top:11px;width:18px;height:18px;color:var(--muted)}
.f-group{border-top:1px solid var(--line);padding:4px 0}
.f-group summary{list-style:none;display:flex;align-items:center;justify-content:space-between;padding:12px 0;font-weight:700;font-size:15px;color:var(--brown-deep);cursor:pointer}
.f-group summary::-webkit-details-marker{display:none}
.f-group summary::after{content:"";width:9px;height:9px;border-right:2px solid var(--muted);border-bottom:2px solid var(--muted);transform:rotate(45deg) translateY(-3px);transition:transform .2s}
.f-group[open] summary::after{transform:rotate(-135deg) translateY(-1px)}
.f-group summary .n{margin-left:auto;margin-right:12px;min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:var(--brown);color:#fff;font-size:11.5px;display:inline-flex;align-items:center;justify-content:center}
.f-opts{padding:0 0 10px}
.f-opt{display:flex;align-items:center;gap:10px;padding:6px 0;font-size:14.5px;cursor:pointer}
.f-opt input{flex:none;width:18px;height:18px;margin:0;accent-color:var(--brown)}
.f-opt small{margin-left:auto;font-size:12.5px;color:var(--muted)}
.f-opt.off small{opacity:.55}
.f-swatch{flex:none;width:10px;height:10px;border-radius:50%}
.f-price{padding:4px 0 14px}
.range{position:relative;height:30px;margin:4px 6px 10px}
.range .track{position:absolute;left:0;right:0;top:13px;height:4px;border-radius:4px;background:var(--line)}
.range .fill{position:absolute;top:13px;height:4px;border-radius:4px;background:var(--brown)}
.range input{position:absolute;inset:0;width:100%;margin:0;background:none;pointer-events:none;-webkit-appearance:none;appearance:none}
.range input::-webkit-slider-thumb{-webkit-appearance:none;pointer-events:auto;width:20px;height:20px;border-radius:50%;background:#fff;border:2px solid var(--brown);box-shadow:0 2px 6px rgba(59,35,23,.3);cursor:pointer}
.range input::-moz-range-thumb{pointer-events:auto;width:16px;height:16px;border-radius:50%;background:#fff;border:2px solid var(--brown);cursor:pointer}
.range input::-webkit-slider-runnable-track{background:none}
.price-boxes{display:flex;align-items:center;gap:8px}
.price-boxes label{flex:1;display:flex;align-items:center;gap:4px;height:40px;border:1.5px solid var(--line);border-radius:12px;padding:0 10px;font-size:13px;color:var(--muted)}
.price-boxes input{width:100%;border:0;background:none;font:inherit;font-size:14px;color:var(--ink);outline:none;-moz-appearance:textfield}
.price-boxes input::-webkit-outer-spin-button,.price-boxes input::-webkit-inner-spin-button{-webkit-appearance:none}
.bands{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.bands button{border:1.5px solid var(--line);background:#fff;border-radius:999px;padding:6px 11px;font-size:12.5px;font-weight:600;color:var(--ink)}
.bands button:hover{border-color:var(--brown-soft)}
.bands button[aria-pressed="true"]{background:var(--brown);border-color:var(--brown);color:#fff}
.f-foot{display:none}

.results{min-width:0;scroll-margin-top:110px}
/* pagination */
.pager{display:flex;justify-content:center;align-items:center;flex-wrap:wrap;gap:6px;margin-top:40px}
.pager:empty{display:none}
.pager button{min-width:42px;height:42px;padding:0 12px;border-radius:999px;border:1.5px solid var(--line);background:#fff;font-weight:700;font-size:14.5px;color:var(--ink);display:inline-flex;align-items:center;justify-content:center;gap:6px;transition:border-color .2s,background-color .2s}
.pager button:hover:not(:disabled){border-color:var(--brown-soft)}
.pager button[aria-current="page"]{background:var(--brown);border-color:var(--brown);color:var(--cream)}
.pager button:disabled{opacity:.4;cursor:default}
.pager .pn{padding:0 16px}
.pager .pn .ico{width:16px;height:16px}
.pager .gap{padding:0 2px;color:var(--muted)}
.mini-pager{display:flex;align-items:center;gap:6px;font-size:14px;color:var(--muted)}
.mini-pager:empty{display:none}
.mini-pager span{margin-right:4px;white-space:nowrap}
.mini-pager button{width:36px;height:36px;border-radius:999px;border:1.5px solid var(--line);background:#fff;color:var(--brown);display:inline-flex;align-items:center;justify-content:center}
.mini-pager button:hover:not(:disabled){border-color:var(--brown)}
.mini-pager button:disabled{opacity:.4;cursor:default}
.mini-pager .ico{width:16px;height:16px}
.results-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.results-bar .r-count{margin:0;font-weight:600;color:var(--brown-deep)}
.results-bar .r-count span{font-weight:500;color:var(--muted)}
.results-bar .sort{margin-left:auto;display:flex;align-items:center;gap:8px;font-size:14px;color:var(--muted)}
.results-bar select{height:42px;border:1.5px solid var(--line);border-radius:999px;padding:0 14px;background:#fff;font:inherit;font-size:14.5px;color:var(--ink)}
.filter-btn{display:none;align-items:center;gap:8px;height:42px;padding:0 16px;border-radius:999px;border:1.5px solid var(--brown);background:#fff;font-weight:700;font-size:14.5px;color:var(--brown)}
.filter-btn .n{min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:var(--orange-cta);color:#fff;font-size:11.5px;display:inline-flex;align-items:center;justify-content:center}
.filter-btn .n[hidden]{display:none}
.f-chips{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 20px}
.f-chips:empty{display:none}
.f-chip{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:999px;padding:7px 10px 7px 13px;background:var(--cream-2);font-weight:600;font-size:13px;color:var(--brown)}
.f-chip:hover{background:#EEDCC7}
.f-chip .ico{width:14px;height:14px;stroke-width:2.2}
.f-chip.all{background:none;color:var(--orange-cta);padding-left:4px}
.results .grid{grid-template-columns:repeat(3,minmax(0,1fr))}
.f-scrim{position:fixed;inset:0;z-index:105;background:rgba(36,24,17,.45);opacity:0;pointer-events:none;transition:opacity .25s}
.f-scrim.open{opacity:1;pointer-events:auto}

@media (max-width:960px){
  .shop{grid-template-columns:1fr;padding-top:16px}
  .filters{position:fixed;top:0;left:0;bottom:0;z-index:110;width:min(360px,90vw);max-height:none;border-radius:0 22px 22px 0;padding-bottom:0;transform:translateX(-105%);visibility:hidden;transition:transform .3s ease,visibility 0s linear .3s;display:flex;flex-direction:column}
  .filters.open{transform:none;visibility:visible;transition:transform .3s ease}
  .f-body{flex:1;overflow:auto}
  .f-close{display:inline-flex}
  .f-foot{display:flex;gap:10px;padding:14px 0 18px;border-top:1px solid var(--line);background:#fff}
  .f-foot .btn{flex:1}
  .filter-btn{display:inline-flex}
}
@media (max-width:680px){
  .results .grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 12px}
  .pager .pn span{display:none}
  .pager .pn{padding:0 12px}
  .mini-pager span{display:none}
  .results-bar .sort span{display:none}
}
/* ============ category page only ============ */
.card.flash{animation:flash 1.6s ease-out 2}
@keyframes flash{0%,100%{box-shadow:0 1px 2px rgba(59,35,23,.06),0 14px 34px -22px rgba(59,35,23,.45)}50%{box-shadow:0 0 0 3px var(--orange-cta),0 14px 34px -22px rgba(59,35,23,.45)}}
.pair{padding:20px 0 88px}
.pair .grid{grid-template-columns:repeat(4,minmax(0,1fr))}
#qvBuy{margin-top:8px}
@media (max-width:1100px){ .pair .grid{grid-template-columns:repeat(2,minmax(0,1fr))} }
@media (prefers-reduced-motion:reduce){ .card.flash{animation:none;box-shadow:0 0 0 3px var(--orange-cta)} }
</style>

<main>
  <!-- the navigation line: it is also the page heading -->
  <div class="crumb-bar">
    <div class="wrap crumb-row">
      <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>" data-spa="link">Home</a><span class="sep" aria-hidden="true">/</span><a href="<?= h3_link('shop') ?>" data-spa="link">Shop</a><span class="sep" aria-hidden="true">/</span><h1 aria-current="page"><?= h3_e($catMeta['label']) ?></h1></nav>
      <div class="crumb-actions" id="crumbActions">
        <a class="close-btn" id="pgClose" href="<?= h3_link('home') ?>" data-spa="link"><svg class="ico" aria-hidden="true"><use href="#i-x"/></svg>Back to previous page</a>
        <a class="combo-btn" href="<?= htmlspecialchars($COMBO_PAGE, ENT_QUOTES) ?>" data-spa="link"><svg class="ico" aria-hidden="true"><use href="#i-spark"/></svg>Make my combo</a>
      </div>
    </div>
  </div>

  <!-- filter bar + products, straight under it, same width as the navigation line -->
  <div class="wrap shop" id="catalogue">
<div class="f-scrim" id="fScrim"></div>
<aside class="filters" id="filters" aria-label="Filters">
  <div class="f-head">
    <h2>Filters</h2>
    <button type="button" class="f-clear" id="fClear" disabled>Clear all</button>
    <button type="button" class="icon-btn f-close" id="fClose" aria-label="Close filters"><svg class="ico"><use href="#i-x"/></svg></button>
  </div>
  <div class="f-body" id="fBody"></div>
  <div class="f-foot"><button type="button" class="btn btn-primary" id="fShow">Show results</button></div>
</aside>

    <section class="results" id="results" aria-label="<?= h3_e($catMeta['label']) ?>">
      <div class="results-bar">
        <button type="button" class="filter-btn" id="fBtn" aria-controls="filters" aria-expanded="false"><svg class="ico" style="width:18px;height:18px"><use href="#i-menu"/></svg>Filters <span class="n" id="fBtnN" hidden>0</span></button>
        <p class="r-count" id="rCount" aria-live="polite"></p>
        <label class="sort"><span>Sort by</span><select id="sortSel"></select></label>
        <div class="mini-pager" id="pagerTop"></div>
      </div>
      <div class="f-chips" id="fChips"></div>
      <div class="grid" id="grid"></div>
      <nav class="pager" id="pager" aria-label="Pages"></nav>
    </section>
  </div>

  <section class="pair" id="pairSection" hidden>
    <div class="wrap">
      <div class="section-head"><div><p class="eyebrow">Goes well with</p><h2>From our other ranges</h2><p class="lede">Coffee, cups, mugs and tees that pair with these.</p></div>
        <a class="link" href="<?= h3_link('combos') ?>" data-spa="link">See combos</a></div>
      <div class="grid" id="pairGrid"></div>
    </div>
  </section>
</main>

<!-- Quick view -->
<dialog class="qv" id="qv" aria-labelledby="qvTitle">
  <div class="qv-inner">
    <div class="qv-img"><img id="qvImg" alt=""></div>
    <div class="qv-info">
      <button class="icon-btn qv-close" id="qvClose" aria-label="Close quick view"><svg class="ico"><use href="#i-x"/></svg></button>
      <p class="card-cat" id="qvCat"></p>
      <h2 id="qvTitle"></h2>
      <p class="qv-id" id="qvId"></p>
      <p class="price" id="qvPrice"></p>
      <p class="stock" id="qvStock"></p>
      <div class="stepper" role="group" aria-label="Quantity">
        <button type="button" id="qvMinus" aria-label="Decrease quantity">−</button>
        <output id="qvQty" aria-live="polite"></output>
        <button type="button" id="qvPlus" aria-label="Increase quantity">+</button>
      </div>
      <p class="moq" id="qvMoq"></p>
      <button class="btn btn-primary" id="qvAdd">Add to cart</button>
      <button class="btn btn-outline" id="qvBuy" type="button">Buy now</button><a id="qvLink" href="#" hidden></a>
    </div>
  </div>
</dialog>


<script>
const DATA = <?= $pageJson ?>;
</script>
<script>
(function () {
'use strict';
const $  = (s, el = document) => el.querySelector(s);
const $$ = (s, el = document) => [...el.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const icon = id => `<svg class="ico" aria-hidden="true"><use href="#i-${id}"/></svg>`;

const MIN = DATA.minQty;
const CATS = Object.fromEntries(DATA.categories.map(c => [c.key, c]));
const BY_ID = Object.fromEntries(DATA.products.map((p, i) => [p.id, Object.assign(p, { order: i })]));
const cart = new Map(Object.entries(DATA.cart || {}).map(([k, v]) => [k, +v]));
const wish = new Set(DATA.wishlist || []);

/* ---------- guest wishlist (same localStorage key as index.php) ---------- */
function guestWish() { try { return JSON.parse(localStorage.getItem('guestWishlist') || '[]'); } catch (e) { return []; } }
if (!DATA.user.loggedIn) guestWish().forEach(id => wish.add(String(id)));

/* ---------- formatting ---------- */
function money(v) {
  const s = Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  return DATA.currency.symbol + (DATA.maskPrices ? s.replace(/\d/g, '#') : s);
}
const catLabel = k => (CATS[k] ? CATS[k].label : k);

$$('[data-minqty]').forEach(el => el.textContent = MIN);

/* ---------- product card ---------- */
const effPrice = p => (p.offer && p.offer.type === 'percent') ? p.offer.final : p.price;
function priceHTML(p, unit = 'per unit') {
  if (p.offer && p.offer.type === 'percent')
    return `<p class="price"><span class="deal">${money(p.offer.final)}</span><s>${money(p.price)}</s><span>${unit}</span></p>`;
  return `<p class="price">${money(p.price)} <span>${unit}</span></p>` + (p.offer && p.offer.type === 'bogo' ? '<p class="price-note">Buy 1, get 1 free</p>' : '');
}
function offerBadge(p) {
  if (!p.offer) return '';
  return `<span class="badge badge-deal">${p.offer.type === 'percent' ? p.offer.value + '% off' : 'Buy 1 Get 1'}</span>`;
}
function cardHTML(p, opts = {}) {
  const out = p.stock <= 0, low = !out && p.stock < DATA.lowStockAt, inCart = cart.get(p.id) || 0;
  const badges = [
    offerBadge(p),
    p.isNew  ? '<span class="badge badge-new">New</span>' : '',
    p.isBest ? '<span class="badge badge-best">Best seller</span>' : '',
    out ? '<span class="badge badge-out">Sold out</span>' : low ? '<span class="badge badge-low">Few left</span>' : '',
  ].join('');
  return `<article class="card" data-id="${esc(p.id)}" data-cat="${esc(p.cat)}">
    <div class="card-media">
      <a href="${esc(p.url)}" tabindex="-1" data-qv="${esc(p.id)}"><img src="${esc(p.img)}" alt="${esc(p.name)}" loading="lazy" width="400" height="500"></a>
      <div class="badges">${badges}</div>
      ${opts.rank ? `<span class="rank" aria-hidden="true">${String(opts.rank).padStart(2, '0')}</span>` : ''}
      <button class="wish" data-wish="${esc(p.id)}" aria-pressed="${wish.has(p.id)}" aria-label="Save ${esc(p.name)} to wishlist">${icon('heart')}</button>
      ${cartIcon(p, inCart, out)}
      <button class="quick" data-quick="${esc(p.id)}">Quick view</button>
    </div>
    <div class="card-body">
      <div class="card-info">
        <p class="card-cat">${esc(catLabel(p.cat))}</p>
        <h3 class="card-title"><a href="${esc(p.url)}" data-qv="${esc(p.id)}">${esc(p.name)}</a></h3>
        <p class="card-id">ID: ${esc(p.id)}</p>
        ${priceHTML(p)}
        <p class="moq">Min. order ${MIN} units</p>
      </div>
      ${addBtn(p, inCart, out)}
    </div>
  </article>`;
}
// the round cart icon under the heart: adds one step (MIN units), turns green and shows the units already in the cart
function cartIcon(p, inCart, out) {
  if (out) return `<button class="cart-ico" disabled aria-label="${esc(p.name)} is sold out" title="Sold out"></button>`;
  const label = inCart ? `${inCart} units of ${p.name} in cart. Add ${MIN} more` : `Add ${MIN} units of ${p.name} to cart`;
  return `<button class="cart-ico${inCart ? ' in-cart' : ''}" data-add="${esc(p.id)}" aria-label="${esc(label)}" title="${esc(label)}">${inCart ? `<span class="cart-qty">${inCart}</span>` : ''}</button>`;
}
function addBtn(p, inCart, out) {
  if (out) return `<button class="add" disabled>Sold out</button>`;
  return inCart
    ? `<button class="add in-cart" data-add="${esc(p.id)}">Add more (${inCart} in cart)</button>`
    : `<button class="add" data-add="${esc(p.id)}">Add to cart</button>`;
}
function refreshCards(id) {
  const p = BY_ID[id]; if (!p) return;
  $$(`.card[data-id="${CSS.escape(id)}"], .stack-card[data-id="${CSS.escape(id)}"]`).forEach(card => {
    const old = $('.add', card);
    if (old) old.outerHTML = addBtn(p, cart.get(id) || 0, p.stock <= 0);
    const ico = $('.cart-ico', card);
    if (ico) ico.outerHTML = cartIcon(p, cart.get(id) || 0, p.stock <= 0);
  });
}

/* ---------- search (typo-tolerant) ---------- */
const SYN = { coffee:'bag', powder:'bag', beans:'bag', bean:'bag', bag:'bag', bags:'bag',
  cup:'cup', cups:'cup', mug:'mug', mugs:'mug', tshirt:'tshirt', 'tshirts':'tshirt', 't-shirt':'tshirt', tee:'tshirt', tees:'tshirt', shirt:'tshirt', shirts:'tshirt' };
const GIFT_WORDS = ['gift', 'gifts', 'combo', 'combos', 'set', 'bundle'];

function lev(a, b) { // small edit distance for typo tolerance
  if (Math.abs(a.length - b.length) > 2) return 9;
  const d = Array.from({ length: a.length + 1 }, (_, i) => [i]);
  for (let j = 1; j <= b.length; j++) d[0][j] = j;
  for (let i = 1; i <= a.length; i++) for (let j = 1; j <= b.length; j++)
    d[i][j] = Math.min(d[i-1][j] + 1, d[i][j-1] + 1, d[i-1][j-1] + (a[i-1] === b[j-1] ? 0 : 1));
  return d[a.length][b.length];
}
function matchScore(p, q) {
  const tokens = q.toLowerCase().replace(/[^a-z0-9\s-]/g, ' ').split(/\s+/).filter(Boolean);
  if (!tokens.length) return 1;
  const hay = (p.name + ' ' + catLabel(p.cat)).toLowerCase();
  const words = hay.split(/[^a-z0-9]+/).filter(Boolean);
  let score = 0;
  for (const t of tokens) {
    let s = 0;
    let syn = SYN[t];
    if (!syn && t.length > 3) { const k = Object.keys(SYN).find(k => k.length > 3 && lev(k, t) <= 1); if (k) syn = SYN[k]; }
    if (syn && syn === p.cat) s = 3;
    else if (hay.includes(t)) s = 2;
    else if (t.length > 3 && words.some(w => lev(w, t) <= 1)) s = 1;
    if (!s) return 0;
    score += s;
  }
  return score;
}
function isGiftQuery(q) { return q.toLowerCase().split(/\s+/).some(t => GIFT_WORDS.includes(t)); }

/* ---------- toast + cart drawer: provided by header3.php (window.A3) ---------- */
const toast = msg => A3.toast(msg);
const openDrawer = id => A3.openDrawer(id);
const closeDrawers = () => A3.closeDrawers();

/* ---------- reading the server's reply ----------
   add_to_cart.php, wishlist_toggle.php and cart3.php should answer with JSON. If PHP prints a warning/notice
   before it, or a whole error page comes back, the plain res.json() fails with "Unexpected token <".
   This reads the JSON part when there is one, and otherwise shows what the server really said. */
async function readJson(res) {
  const text = await res.text();
  try { return JSON.parse(text); } catch (e) {}
  const i = text.search(/\{\s*"status"/);                              // a warning printed before the JSON: use the JSON part
  if (i >= 0) {
    for (const end of [text.length, text.lastIndexOf('}') + 1]) { try { return JSON.parse(text.slice(i, end)); } catch (e) {} }
  }
  console.error('The server did not answer with JSON (HTTP ' + res.status + '). It sent:\n' + text.slice(0, 1500));
  const plain = text.replace(/<style[\s\S]*?<\/style>|<script[\s\S]*?<\/script>/gi, ' ').replace(/<[^>]*>/g, ' ').replace(/&[a-z#0-9]+;/gi, ' ').replace(/\s+/g, ' ').trim().slice(0, 160);
  throw new Error(plain ? 'Server said: ' + plain : 'The server sent an unexpected reply (HTTP ' + res.status + '). Please try again.');
}

/* ---------- cart ---------- */

async function addToCart(id, qty) {
  const body = 'action=add&product_id=' + encodeURIComponent(id) + '&quantity=' + qty + '&redirect_to=&ajax=1';
  const res = await fetch('add_to_cart.php', { method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
  const data = await readJson(res);
  if (data.status !== 'success') throw new Error(data.message || 'Could not add to cart');
  const before = cart.get(id) || 0;
  cart.set(id, before + qty);
  refreshCards(id);
  return { id, added: qty, total: before + qty, first: before === 0 };
}
/* message shown after every add: explains the 100-unit minimum and step size */
function addedMessage(r) {
  const name = (BY_ID[r.id] || {}).name || 'this item';
  return r.first
    ? { title: `Added ${r.added} units of ${name}`, note: `Our minimum order is ${MIN} units per product${r.added === MIN ? `, so we've added ${MIN}` : ''}. Add again to increase in steps of ${MIN}.` }
    : { title: `Added ${r.added} more units of ${name}`, note: `You now have ${r.total} units. Quantity goes up in steps of ${MIN} units.` };
}
function notify(msg) { lastNotice = msg; }  // shown at the top of the cart drawer that opens next
let lastNotice = null;
// a whole combo set goes into the cart as ONE line (cart3.php keeps it together)
async function addCombo(ids) {
  const d = await fetch('cart3.php', { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ combo_add: ids.join(',') }) }).then(readJson);
  if (d.status !== 'success') throw new Error(d.message || 'Could not add this combo.');
}
async function refreshCart() { const n = lastNotice; lastNotice = null; await A3.refreshCart(n); }
async function handleAdd(btn, id, qty) {
  btn.disabled = true;
  let done;
  try { done = await addToCart(id, qty); }                      // step 1: really put it in the cart
  catch (e) { toast(e.message); btn.disabled = false; return; }  // not added: say why, allow another try
  const msg = addedMessage(done); notify(msg);
  try { await refreshCart(); openDrawer('cartDrawer'); }         // step 2: refresh the cart panel
  catch (e) {                                                    // the item IS in the cart; only the panel failed, so do not report a failed add
    console.error('Added to cart, but the cart panel could not be refreshed:', e);
    toast(msg.title);
  }
}

/* ---------- wishlist ---------- */
function paintWish(id) { $$(`[data-wish="${CSS.escape(id)}"]`).forEach(b => b.setAttribute('aria-pressed', wish.has(id))); }
A3.setWishCount(wish.size);
async function toggleWish(id) {
  if (!DATA.user.loggedIn) {
    wish.has(id) ? wish.delete(id) : wish.add(id);
    try { localStorage.setItem('guestWishlist', JSON.stringify([...wish])); } catch (e) {}
    paintWish(id); A3.setWishCount(wish.size);
    toast(wish.has(id) ? 'Saved to wishlist' : 'Removed from wishlist');
    return;
  }
  try {
    const res = await fetch('wishlist_toggle.php', { method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'product_id=' + encodeURIComponent(id) });
    const data = await readJson(res);
    if (data.status === 'added') wish.add(id); else if (data.status === 'removed') wish.delete(id);
    else throw new Error(data.message || 'Sign in to use your wishlist');
    paintWish(id); A3.setWishCount(wish.size);
    toast(data.status === 'added' ? 'Saved to wishlist' : 'Removed from wishlist');
  } catch (e) { toast(e.message); }
}

/* ---------- quick view ---------- */
const qv = $('#qv'); let qvItem = null, qvQty = MIN;
function paintQty() {
  $('#qvQty').textContent = qvQty;
  $('#qvMinus').disabled = qvQty <= MIN;
  $('#qvPlus').disabled = qvQty + MIN > qvItem.stock;
  $('#qvAdd').textContent = (cart.get(qvItem.id) ? 'Add more to cart' : 'Add to cart');
}
function openQuick(id) {
  const p = BY_ID[id]; if (!p) return; qvItem = p; qvQty = MIN;
  $('#qvImg').src = p.img; $('#qvImg').alt = p.name;
  $('#qvCat').textContent = catLabel(p.cat);
  $('#qvTitle').textContent = p.name;
  if ($('#qvId')) $('#qvId').textContent = 'ID: ' + p.id;
  $('#qvPrice').outerHTML = priceHTML(p).replace('<p class="price"', '<p class="price" id="qvPrice"');
  const st = $('#qvStock');
  st.className = 'stock' + (p.stock < DATA.lowStockAt ? ' low' : '');
  st.textContent = p.stock <= 0 ? 'Sold out' : p.stock < DATA.lowStockAt ? `Only ${p.stock} units left` : 'In stock, ready to ship';
  $('#qvMoq').textContent = `Sold in steps of ${MIN} units.`;
  $('#qvLink').href = p.url;
  const canBuy = p.stock >= MIN;
  $('#qvAdd').disabled = !canBuy; $('.stepper', qv).hidden = !canBuy;
  if (canBuy) paintQty(); else $('#qvAdd').textContent = p.stock <= 0 ? 'Sold out' : `Fewer than ${MIN} units left`;
  qv.showModal();
}
$('#qvMinus').addEventListener('click', () => { qvQty -= MIN; paintQty(); });
$('#qvPlus').addEventListener('click', () => { qvQty += MIN; paintQty(); });
$('#qvClose').addEventListener('click', () => qv.close());
qv.addEventListener('click', e => { if (e.target === qv) qv.close(); });
$('#qvAdd').addEventListener('click', async () => {
  const btn = $('#qvAdd'); btn.disabled = true;
  let done;
  try { done = await addToCart(qvItem.id, qvQty); }
  catch (e) { toast(e.message); btn.disabled = false; return; }
  const msg = addedMessage(done); notify(msg); qv.close();
  try { await refreshCart(); openDrawer('cartDrawer'); }
  catch (e) { console.error('Added to cart, but the cart panel could not be refreshed:', e); toast(msg.title); }
  finally { btn.disabled = false; }
});

/* ---------- one delegated click handler for all cards ---------- */
document.addEventListener('click', e => {
  const qa  = e.target.closest('[data-qv]');    if (qa && !(e.metaKey || e.ctrlKey || e.shiftKey)) { e.preventDefault(); return openQuick(qa.dataset.qv); }
  const add = e.target.closest('[data-add]');   if (add)  return handleAdd(add, add.dataset.add, MIN);
  const w   = e.target.closest('[data-wish]');  if (w)    return toggleWish(w.dataset.wish);
  const q   = e.target.closest('[data-quick]'); if (q)    return openQuick(q.dataset.quick);
  const cb  = e.target.closest('[data-combo]');
  if (cb) {
    const c = DATA.combos[+cb.dataset.combo]; cb.disabled = true;
    (async () => {
      try {
        await addCombo(c.ids);
        notify({ title: `Added the ${c.title.toLowerCase()} combo`, note: `${MIN} sets (${MIN} units of each item). Have a combo coupon? Apply it in your cart.` });
        await refreshCart(); openDrawer('cartDrawer');
      }
      catch (err) { toast(err.message); } finally { cb.disabled = false; }
    })();
    return;
  }
});

/* ---------- filter engine (left sidebar, chips, sort, URL) ---------- */
// cfg: { items, groups:[{key,label,options:[{value,label,swatch}],test(item,values)}], priceOf, match(item,q),
//        sorts:[{value,label,cmp}], render(item), gridId, emptyHTML(), perPage }
function niceRound(v) { const m = Math.pow(10, Math.max(0, Math.floor(Math.log10(Math.max(v, 1))) - 1)); return Math.round(v / m) * m; }
const roundMoney = v => DATA.currency.symbol + (DATA.maskPrices ? '####' : Math.round(v).toLocaleString('en-IN'));
function FilterPage(cfg) {
  const per = cfg.perPage || 12;
  const st = { q: '', sort: cfg.sorts[0].value, min: null, max: null, sel: {}, page: 1 };
  cfg.groups.forEach(g => st.sel[g.key] = new Set());
  const prices = cfg.items.map(cfg.priceOf);
  const LO = prices.length ? Math.floor(Math.min(...prices)) : 0, HI = prices.length ? Math.ceil(Math.max(...prices)) : 0;
  // read state from the address bar (plain comma lists, e.g. ?cat=bag,mug)
  const qs = new URLSearchParams(location.search);
  cfg.groups.forEach(g => (qs.get(g.key) || '').split(',').filter(Boolean).forEach(v => st.sel[g.key].add(v)));
  if (qs.get('min')) st.min = +qs.get('min');
  if (qs.get('max')) st.max = +qs.get('max');
  if (qs.get('q')) st.q = qs.get('q');
  if (cfg.sorts.some(s => s.value === qs.get('sort'))) st.sort = qs.get('sort');
  if (+qs.get('page') > 1) st.page = Math.floor(+qs.get('page'));

  const side = $('#filters'), body = $('#fBody');
  function passes(it, skip) {
    if (st.q && cfg.match(it, st.q) <= 0) return false;
    if (skip !== 'price') {
      const p = cfg.priceOf(it);
      if (st.min != null && p < st.min) return false;
      if (st.max != null && p > st.max) return false;
    }
    for (const g of cfg.groups) {
      if (g.key === skip) continue;
      const vals = [...st.sel[g.key]];
      if (vals.length && !g.test(it, vals)) return false;
    }
    return true;
  }
  function results() {
    const list = cfg.items.filter(it => passes(it));
    const s = cfg.sorts.find(x => x.value === st.sort);
    if (s && s.cmp) list.sort(s.cmp);
    return list;
  }
  const bands = (() => {   // three price bands from the real prices
    if (prices.length < 3 || HI - LO < 3) return [];
    const sorted = [...prices].sort((a, b) => a - b);
    const a = niceRound(sorted[Math.floor(sorted.length / 3)]), b = niceRound(sorted[Math.floor(sorted.length * 2 / 3)]);
    if (!(a > LO && b > a && b < HI)) return [];
    return [{ min: null, max: a, label: `Under ${roundMoney(a)}` }, { min: a, max: b, label: `${roundMoney(a)} – ${roundMoney(b)}` }, { min: b, max: null, label: `${roundMoney(b)} and above` }];
  })();
  function activeCount() { return cfg.groups.reduce((n, g) => n + st.sel[g.key].size, 0) + (st.min != null || st.max != null ? 1 : 0) + (st.q ? 1 : 0); }

  function renderSide() {
    const open = new Set($$('.f-group[open]', body).map(d => d.dataset.key));
    const first = !body.dataset.ready;
    body.innerHTML = `<div class="f-search">${icon('search')}<label class="sr-only" for="fq">Search in results</label>
        <input id="fq" type="search" placeholder="Search in results" value="${esc(st.q)}" autocomplete="off"></div>`
      + cfg.groups.map(g => {
        // show only options that at least one product in this list really matches; hide the rest (and empty groups)
        const vis = g.options.filter(o => cfg.items.some(it => g.test(it, [o.value])));
        if (!vis.length) return '';
        const opts = vis.map(o => {
          const on = st.sel[g.key].has(o.value);
          const n = cfg.items.filter(it => passes(it, g.key) && g.test(it, [o.value])).length;
          return `<label class="f-opt${!n && !on ? ' off' : ''}"><input type="checkbox" data-g="${g.key}" value="${esc(o.value)}" ${on ? 'checked' : ''}>
            ${o.swatch ? `<span class="f-swatch" style="background:${o.swatch}"></span>` : ''}${esc(o.label)}<small>${n}</small></label>`;
        }).join('');
        const isOpen = first ? (g.open !== false) : open.has(g.key);
        const n = st.sel[g.key].size;
        return `<details class="f-group" data-key="${g.key}" ${isOpen ? 'open' : ''}><summary>${esc(g.label)}${n ? `<span class="n">${n}</span>` : ''}</summary><div class="f-opts">${opts}</div></details>`;
      }).join('')
      + (HI > LO ? `<details class="f-group" data-key="price" ${first || open.has('price') ? 'open' : ''}><summary>Price per ${esc(cfg.priceUnit || 'unit')}${st.min != null || st.max != null ? '<span class="n">1</span>' : ''}</summary>
        <div class="f-price">
          <div class="range"><div class="track"></div><div class="fill" id="rFill"></div>
            <input type="range" id="rMin" min="${LO}" max="${HI}" step="1" value="${st.min ?? LO}" aria-label="Minimum price">
            <input type="range" id="rMax" min="${LO}" max="${HI}" step="1" value="${st.max ?? HI}" aria-label="Maximum price"></div>
          <div class="price-boxes"><label>${esc(DATA.currency.symbol)}<input type="number" id="pMin" min="${LO}" max="${HI}" value="${st.min ?? LO}" aria-label="Minimum price"></label><span>–</span>
            <label>${esc(DATA.currency.symbol)}<input type="number" id="pMax" min="${LO}" max="${HI}" value="${st.max ?? HI}" aria-label="Maximum price"></label></div>
          ${bands.length ? `<div class="bands">${bands.map((b, i) => `<button type="button" data-band="${i}" aria-pressed="${st.min === b.min && st.max === b.max}">${esc(b.label)}</button>`).join('')}</div>` : ''}
        </div></details>` : '');
    body.dataset.ready = '1';
    paintRange();
  }
  function paintRange() {
    const a = $('#rMin'), b = $('#rMax'), f = $('#rFill'); if (!a) return;
    const lo = +a.value, hi = +b.value, span = (HI - LO) || 1;
    f.style.left = ((Math.min(lo, hi) - LO) / span * 100) + '%';
    f.style.right = (100 - (Math.max(lo, hi) - LO) / span * 100) + '%';
  }
  function chipsHTML() {
    const chips = [];
    if (st.q) chips.push({ k: 'q', label: `“${st.q}”` });
    cfg.groups.forEach(g => st.sel[g.key].forEach(v => {
      const o = g.options.find(x => x.value === v); chips.push({ k: g.key, v, label: o ? o.label : v });
    }));
    if (st.min != null || st.max != null) chips.push({ k: 'price', label: `${roundMoney(st.min ?? LO)} – ${roundMoney(st.max ?? HI)}` });
    return chips.map(c => `<button type="button" class="f-chip" data-chip="${c.k}" data-v="${esc(c.v || '')}">${esc(c.label)}${icon('x')}</button>`).join('')
      + (chips.length > 1 ? `<button type="button" class="f-chip all" data-chip="all">Clear all</button>` : '');
  }
  function syncURL() {
    const p = new URLSearchParams();
    cfg.groups.forEach(g => { if (st.sel[g.key].size) p.set(g.key, [...st.sel[g.key]].join(',')); });
    if (st.min != null) p.set('min', st.min);
    if (st.max != null) p.set('max', st.max);
    if (st.q) p.set('q', st.q);
    if (st.sort !== cfg.sorts[0].value) p.set('sort', st.sort);
    if (st.page > 1) p.set('page', st.page);
    const cur = new URLSearchParams(location.search);
    if (cur.get('set_currency')) p.set('set_currency', cur.get('set_currency'));
    history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p : '') + location.hash);
  }
  function pagerHTML(pages) {
    if (pages <= 1) return '';
    const cur = st.page, nums = [];
    for (let n = 1; n <= pages; n++) {
      if (n === 1 || n === pages || Math.abs(n - cur) <= 1) nums.push(n);
      else if (nums[nums.length - 1] !== '…') nums.push('…');
    }
    return `<button type="button" class="pn" data-page="${cur - 1}" ${cur === 1 ? 'disabled' : ''} aria-label="Previous page">${icon('left')}<span>Previous</span></button>`
      + nums.map(n => n === '…' ? '<span class="gap" aria-hidden="true">…</span>'
          : `<button type="button" data-page="${n}" ${n === cur ? 'aria-current="page"' : ''} aria-label="Page ${n}">${n}</button>`).join('')
      + `<button type="button" class="pn" data-page="${cur + 1}" ${cur === pages ? 'disabled' : ''} aria-label="Next page"><span>Next</span>${icon('right')}</button>`;
  }
  function renderResults() {
    const list = results(), grid = $('#' + cfg.gridId), n = activeCount();
    const pages = Math.max(1, Math.ceil(list.length / per));
    if (st.page > pages) st.page = pages;
    const start = (st.page - 1) * per, slice = list.slice(start, start + per);
    const noun = list.length === 1 ? (cfg.noun || 'item') : (cfg.nounPlural || 'items');
    $('#rCount').innerHTML = list.length
      ? (pages > 1 ? `Showing ${start + 1}–${start + slice.length} of ${list.length} ${noun}` : `${list.length} ${noun}`) + (n ? ' <span>match your filters</span>' : '')
      : `0 ${noun}`;
    $('#fChips').innerHTML = chipsHTML();
    $('#fBtnN').textContent = n; $('#fBtnN').hidden = !n;
    $('#fClear').disabled = !n;
    $('#fShow').textContent = `Show ${list.length} ${noun}`;
    grid.innerHTML = list.length ? slice.map(cfg.render).join('') : cfg.emptyHTML(n > 0);
    $('#pager').innerHTML = pagerHTML(pages);
    $('#pagerTop').innerHTML = pages > 1
      ? `<span>Page ${st.page} of ${pages}</span><button type="button" data-page="${st.page - 1}" ${st.page === 1 ? 'disabled' : ''} aria-label="Previous page">${icon('left')}</button><button type="button" data-page="${st.page + 1}" ${st.page === pages ? 'disabled' : ''} aria-label="Next page">${icon('right')}</button>`
      : '';
    syncURL();
    return list;
  }
  function goPage(nPage, scroll = true) {
    st.page = Math.max(1, nPage); renderResults();
    if (scroll) $('#results').scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
  }
  function update() { st.page = 1; renderSide(); renderResults(); }
  function clearAll() { cfg.groups.forEach(g => st.sel[g.key].clear()); st.min = st.max = null; st.q = ''; update(); }

  // events
  body.addEventListener('change', e => {
    const t = e.target;
    if (t.dataset.g) { t.checked ? st.sel[t.dataset.g].add(t.value) : st.sel[t.dataset.g].delete(t.value); update(); }
    else if (t.id === 'pMin' || t.id === 'pMax') {
      let a = +$('#pMin').value || LO, b = +$('#pMax').value || HI; if (a > b) [a, b] = [b, a];
      st.min = a > LO ? a : null; st.max = b < HI ? b : null; update();
    } else if (t.id === 'rMin' || t.id === 'rMax') {
      let a = +$('#rMin').value, b = +$('#rMax').value; if (a > b) [a, b] = [b, a];
      st.min = a > LO ? a : null; st.max = b < HI ? b : null; update();
    }
  });
  body.addEventListener('input', e => {
    if (e.target.id === 'rMin' || e.target.id === 'rMax') { paintRange(); $('#pMin').value = Math.min(+$('#rMin').value, +$('#rMax').value); $('#pMax').value = Math.max(+$('#rMin').value, +$('#rMax').value); }
    if (e.target.id === 'fq') { clearTimeout(body._t); body._t = setTimeout(() => { st.q = e.target.value.trim(); st.page = 1; renderResults(); refreshCounts(); }, 220); }
  });
  function refreshCounts() {   // update counts without re-rendering the search box (keeps typing focus)
    const q = $('#fq'); const pos = q ? q.selectionStart : 0; renderSide(); const q2 = $('#fq'); if (q2) { q2.focus(); q2.setSelectionRange(pos, pos); }
  }
  body.addEventListener('click', e => {
    const b = e.target.closest('[data-band]'); if (!b) return;
    const band = bands[+b.dataset.band], same = st.min === band.min && st.max === band.max;
    st.min = same ? null : band.min; st.max = same ? null : band.max; update();
  });
  $('#fChips').addEventListener('click', e => {
    const c = e.target.closest('[data-chip]'); if (!c) return;
    const k = c.dataset.chip;
    if (k === 'all') return clearAll();
    if (k === 'q') st.q = ''; else if (k === 'price') st.min = st.max = null; else st.sel[k].delete(c.dataset.v);
    update();
  });
  $('#fClear').addEventListener('click', clearAll);
  $('#sortSel').innerHTML = cfg.sorts.map(s => `<option value="${s.value}" ${s.value === st.sort ? 'selected' : ''}>${esc(s.label)}</option>`).join('');
  $('#sortSel').addEventListener('change', e => { st.sort = e.target.value; st.page = 1; renderResults(); });
  ['#pager', '#pagerTop'].forEach(sel => $(sel).addEventListener('click', e => {
    const b = e.target.closest('[data-page]'); if (b && !b.disabled) goPage(+b.dataset.page);
  }));
  // phone: sidebar becomes a slide-in panel
  const scrim = $('#fScrim');
  const openF = () => { side.classList.add('open'); scrim.classList.add('open'); document.body.style.overflow = 'hidden'; $('#fBtn').setAttribute('aria-expanded', 'true'); };
  const closeF = () => { side.classList.remove('open'); scrim.classList.remove('open'); document.body.style.overflow = ''; $('#fBtn').setAttribute('aria-expanded', 'false'); };
  $('#fBtn').addEventListener('click', openF);
  $('#fClose').addEventListener('click', closeF);
  $('#fShow').addEventListener('click', () => { closeF(); $('#results').scrollIntoView({ behavior: 'smooth' }); });
  scrim.addEventListener('click', closeF);
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && side.classList.contains('open')) closeF(); });

  renderSide(); renderResults();
  return {
    search(q) { st.q = q; update(); $('#results').scrollIntoView({ behavior: 'smooth' }); },
    refresh: renderResults,
    // open the page that holds an item (for links like #product-ID); returns false if filtered out
    reveal(test) {
      const idx = results().findIndex(test); if (idx < 0) return false;
      goPage(Math.floor(idx / per) + 1, false); return true;
    },
  };
}

/* ---------- Close button beside "Make my combo" ----------
   goes back to the page the visitor came from, or Home when there is none. If the site header has already put a
   "Close" button into this top line, that one is kept instead and moved here, so there is never a second one. */
(function () {
  const bar = $('.crumb-bar'), actions = $('#crumbActions'), mine = $('#pgClose');
  if (!bar || !actions || !mine) return;
  mine.addEventListener('click', e => {
    try { if (document.referrer && new URL(document.referrer).origin === location.origin && history.length > 1) { e.preventDefault(); history.back(); } } catch (err) {}
  });
  const isClose = el => el !== mine && !actions.contains(el) && /^(✕|×|x)?\s*close$/i.test((el.textContent || '').replace(/\s+/g, ' ').trim());
  const LABEL = 'Back to previous page', closeRe = /^(✕|×|x)?\s*close$/i;
  function relabel(el) {
    const w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
    for (let n = w.nextNode(); n; n = w.nextNode()) {
      if (/close/i.test(n.nodeValue) && n.nodeValue.trim().length <= 8) n.nodeValue = n.nodeValue.replace(/(✕|×)?\s*close/i, LABEL);
    }
    if (/close/i.test(el.getAttribute('aria-label') || '')) el.setAttribute('aria-label', LABEL);
  }
  function adopt() {
    const other = [...bar.querySelectorAll('button, a')].find(isClose);
    if (other) { actions.insertBefore(other, actions.firstChild); mine.hidden = true; }
    [...actions.querySelectorAll('button, a')].forEach(el => {
      if (el !== mine && closeRe.test((el.textContent || '').replace(/\s+/g, ' ').trim())) relabel(el);
    });
  }
  adopt();
  new MutationObserver(adopt).observe(bar, { childList: true, subtree: true });
})();

/* ---------- category page ---------- */
const CAT = DATA.pageCat;
const items = DATA.products.filter(p => p.cat === CAT);

const offerKey = p => !p.offer ? '' : p.offer.type === 'percent' ? String(p.offer.value) : 'bogo';
const allOffered = DATA.products.filter(p => p.offer);   // offers across the WHOLE shop, so every page lists the same options
const offerOpts = [
  { value: 'any', label: 'On offer' },
  ...[...new Set(allOffered.filter(p => p.offer.type === 'percent').map(p => p.offer.value))].sort((a, b) => b - a).map(v => ({ value: String(v), label: `${v}% off` })),
  { value: 'bogo', label: 'Buy 1 Get 1' },
];
const fp = FilterPage({
  items, gridId: 'grid', noun: 'product', nounPlural: 'products', perPage: 9,
  priceOf: p => effPrice(p),
  match: matchScore,
  render: p => cardHTML(p),
  groups: [
    { key: 'deal', label: 'Deals', options: offerOpts, test: (p, v) => !!p.offer && v.some(x => x === 'any' || x === offerKey(p)) },
    { key: 'avail', label: 'Availability', options: [{ value: 'in', label: 'In stock' }, { value: 'plenty', label: `Ready for ${DATA.readyAt}+ units` }],
      test: (p, v) => v.every(x => x === 'in' ? p.stock > 0 : p.stock >= DATA.readyAt) },
    { key: 'tag', label: 'Highlights', options: [{ value: 'new', label: 'New arrivals' }, { value: 'best', label: 'Best sellers' }],
      test: (p, v) => v.some(x => x === 'new' ? p.isNew : p.isBest) },
  ].filter(g => g.options.length),
  sorts: [
    { value: 'featured', label: 'Featured', cmp: (a, b) => ((b.stock > 0) - (a.stock > 0)) || (b.isBest - a.isBest) || (!!b.offer - !!a.offer) || a.order - b.order },
    { value: 'lowtohigh', label: 'Price: low to high', cmp: (a, b) => effPrice(a) - effPrice(b) },
    { value: 'hightolow', label: 'Price: high to low', cmp: (a, b) => effPrice(b) - effPrice(a) },
    { value: 'newest', label: 'Newest', cmp: (a, b) => (b.isNew - a.isNew) || a.order - b.order },
    { value: 'quantity_desc', label: 'Most in stock', cmp: (a, b) => b.stock - a.stock },
    { value: 'name', label: 'Name: A to Z', cmp: (a, b) => a.name.localeCompare(b.name) },
  ],
  emptyHTML: filtered => filtered
    ? `<div class="empty"><h3>No products match these filters</h3><p>Try removing a filter, or clear them all.</p><p><button type="button" class="btn btn-outline" onclick="document.getElementById('fClear').click()">Clear all filters</button></p></div>`
    : `<div class="empty"><h3>New products are on the way</h3><p>Nothing in this range just yet. Have a look at our other ranges in the menu above.</p></div>`,
});
window.onSiteSearch = q => fp.search(q);

// "From our other ranges": best sellers and new items from the other categories
const pairs = DATA.products.filter(p => p.cat !== CAT && p.stock > 0)
  .sort((a, b) => (b.isBest - a.isBest) || (b.isNew - a.isNew) || a.order - b.order);
const pick = []; const seen = {};
for (const p of pairs) { if ((seen[p.cat] = (seen[p.cat] || 0) + 1) <= 2) pick.push(p); if (pick.length >= 4) break; }
if (pick.length) { $('#pairGrid').innerHTML = pick.map(p => cardHTML(p)).join(''); $('#pairSection').hidden = false; }

// links like coffee_cup3.php#product-ATCUP001 scroll to that product and highlight it
(function () {
  const m = location.hash.match(/^#product-(.+)$/); if (!m) return;
  const id = decodeURIComponent(m[1]);
  if (!fp.reveal(p => p.id === id)) return;            // jump to the page that holds this product
  const card = $(`#grid .card[data-id="${CSS.escape(id)}"]`);
  if (!card) return;
  card.id = 'product-' + id;
  setTimeout(() => { card.scrollIntoView({ behavior: 'smooth', block: 'center' }); card.classList.add('flash'); }, 150);
})();

// quick view "Buy now": add the chosen quantity, then go straight to the cart (like the old Buy button)
$('#qvBuy').addEventListener('click', async () => {
  const btn = $('#qvBuy'); if ($('#qvAdd').disabled || !qvItem) return; btn.disabled = true;
  try { await addToCart(qvItem.id, qvQty); location.href = (A3.links && A3.links.cart) || 'cart.php'; }
  catch (e) { toast(e.message); btn.disabled = false; }
});
})();
</script>

<?php
include __DIR__ . '/footer3.php';
$html_content = ob_get_clean();

if ($is_spa_request) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'title' => $PAGE_TITLE,
        'html'  => $html_content
    ]);
    exit;
}

echo $html_content;
?>