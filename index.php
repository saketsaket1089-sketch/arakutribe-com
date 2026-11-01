<?php
/* =====================================================================
   ARAKU TRIBE - index.php  (redesign test page)
   ---------------------------------------------------------------------
   Uses header3.php (top) and footer3.php (bottom); everything else for
   the home page (its CSS, PHP and JavaScript) is in this one file.
   Backend files used: db1.php, currency_helper.php, add_to_cart.php,
   cart_status.php, wishlist_toggle.php.
   ===================================================================== */
session_start();
$loggedInUserId = $_SESSION['user_id'] ?? null;

/* ---------------------------------------------------------------------
   DUAL MODE / SPA (AJAX) ROUTING CHECK
   --------------------------------------------------------------------- */
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || isset($_GET['ajax']) 
          || isset($_POST['ajax']);

include 'db1.php';
mysqli_report(MYSQLI_REPORT_OFF);            // we check errors ourselves below
$conn = new mysqli($host, $user, $pass, $db, $port);
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
$ORDER_ITEMS_TABLE = 'order_items'; // used for real Best Sellers; section hides if not found

// OFFERS - real discounts only. Everything listed here is shown to customers, so add_to_cart.php /
// cart.php must charge the same price. The struck-through price is always your real current price.
//   'ATMUG001' => ['type' => 'percent', 'value' => 20],   // 20% off the current price
//   'ATBAG003' => ['type' => 'bogo'],                     // buy 1 get 1 free (checkout must add the free units)
$OFFERS = [];   // filled from the database (offers table) below - add offers there, not here
// While $OFFERS is empty, localhost shows labelled SAMPLE offers so you can see the design.
// Samples never appear on a real domain.
$PREVIEW_OFFERS_ON_LOCALHOST = false;   // offers now come from the database$COMBO_DISCOUNT_PERCENT = 0;   // real % off each combo set (checkout must apply it); 0 = normal combined price
$WELCOME_CODE = '';            // e.g. 'WELCOME10' - set it only once cart.php really accepts the code$WELCOME_TEXT = '10% off your first order';
$AI_AGENT_URL = 'araku_agent3.php';$HOT_DEALS_URL = 'hotdeals3.php';

// FRESH HOME PAGE ON EVERY REFRESH: each visit to this page moves one step through a rotation,
// so the opening hero product, the featured product of each range, the order of the spotlight
// cards and the product section shown first all change. Our products always come first,
// then the AI Agent. false = the page always looks the same.
$ROTATE_ON_REFRESH = true;

// Order of this array = display order everywhere (BA doc: Bags, Cups, Mugs, T-Shirts)
$CATEGORIES = [
    'bag'    => ['label' => 'Coffee Bags', 'page' => 'coffee_bag3.php',    'blurb' => 'Araku Valley  Premium coffee powder, packed in eco-friendly wheat bags.',
                 'kicker' => 'Fresh from the valley',  'perks' => ['Araku Valley origin', 'Hand-picked beans', 'Eco-friendly wheat bag']],
    'cup'    => ['label' => 'Coffee Cups', 'page' => 'coffee_cup3.php',    'blurb' => 'Branded cups for cafés, takeaway counters and events.',
                 'kicker' => 'Made for takeaway',      'perks' => ['Araku Tribe branding', 'Built for takeaway', 'Bulk orders for cafés']],
    'mug'    => ['label' => 'Coffee Mugs', 'page' => 'coffee_mug3.php',    'blurb' => 'Everyday mugs made for a slow morning brew.',
                 'kicker' => 'For the slow morning',   'perks' => ['Araku Tribe design', 'Everyday use', 'Pairs with our premium coffee']],
    'tshirt' => ['label' => 'T-Shirts',    'page' => 'coffee_tshirt3.php', 'blurb' => 'Tribe T-Shirts for people who plan their day around premium coffee.',
                 'kicker' => 'Wear the Tribe',         'perks' => ['Araku Tribe design', 'Everyday wear', 'Pairs with a mug']],
];

// Your existing theme images (paths from index.php)
$IMAGES = [
    'hero'      => 'img/gallery/portfolio_img_001.png',
    'story'     => 'img/features/about_img.png',
    'lifestyle' => 'img/gallery/portfolio_img_003.png',
    'insta'     => [
        'img/gallery/portfolio_img_001.png', 'img/gallery/portfolio_img_002.png',
        'img/gallery/portfolio_img_003.png', 'img/gallery/portfolio_img_004.png',
        'img/gallery/portfolio_img_005.png', 'img/gallery/portfolio_img_006.png',
    ],
];
// HERO SLIDER: slide 1 plays this video if the file exists (MP4, muted, ~10-20s loop, under 8 MB).
// Until you upload one, slide 1 shows the first backdrop photo instead.
$HERO_VIDEO     = 'video/araku_hero.mp4';
$HERO_BACKDROPS = [  // lifestyle photos behind each slide (slide 1 = brand slide, then one per category)
    'img/gallery/portfolio_img_001.png', 'img/gallery/portfolio_img_002.png',
    'img/gallery/portfolio_img_004.png', 'img/gallery/portfolio_img_005.png',
    'img/gallery/portfolio_img_006.png',
];$SLIDE_SECONDS  = 6;
// Opening slide: two product tiles on the LEFT, matching the cup + bag tiles built into the right of the hero photo.
// Leave 'img' empty to use that category's featured product photo, or point to your own image.
// Optional 'position' (e.g. 'center 68%') moves the framing if part of the photo gets cut off.
$HERO_SIDE_TILES = [
    ['cat' => 'mug',    'img' => ''],
    ['cat' => 'tshirt', 'img' => 'tshirt_lifestyle.png', 'position' => 'center 68%'],
];
// Opening slide, RIGHT side: two tiles exactly like the left ones (same size, same thin lines),
// so both sides match. They replace the cup + bag column built into the hero photo (that part of
// the photo is trimmed off). Same options as above. Make it [] to go back to the photo's own column.
$HERO_RIGHT_TILES = [
    ['cat' => 'cup', 'img' => ''],
    ['cat' => 'bag', 'img' => ''],
];
// 'clear'  = product tiles on both sides shown as-is; only the coffee-pouring photo is darkened for the text
// 'merged' = one even colour grade + darkening over the whole opening screen
$HERO_LOOK = 'clear';
// HERO COLLAGE FILL: true = the product-photo collage covers the WHOLE background of the slide area
// (replacing the static photo), false = the older layout (collage on one side only).
$HERO_COLLAGE_FILL = true;
// Share of the hero photo's width taken by the coffee-pouring photo (the rest is its built-in cup/bag column).
// In 'clear' mode the darkening stops here. Nudge it (e.g. 0.70 or 0.74) if the edge misses the white line.
$HERO_PHOTO_SPLIT = 0.72;
// Lines between the two left tiles and the photo: same white line as the cup/bag column built into the photo.
// Nudge the width (e.g. '4px' or '8px') until it matches the line on the right.
$HERO_TILE_LINE_WIDTH = '6px';$HERO_TILE_LINE_COLOR = '#FFFFFF';
$LOGO_IMAGE = ''; // e.g. 'img/logo/logo_dark.png' - leave empty to use the text wordmark (your current logo is made for a dark header)

$TESTIMONIALS = [
    ['name' => 'Ramesh Varma',  'place' => 'Bean & Brew, Vizag',          'text' => 'Since we switched to Araku beans, our customers keep talking about the aroma and smooth taste.'],
    ['name' => 'Sneha Rao',     'place' => 'The Koffee House, Vizag',     'text' => 'The organic beans give us a flavour customers can\'t get elsewhere, and the cups feel premium.'],
    ['name' => 'Vikram Shetty', 'place' => 'Coastal Brews, Vizag',        'text' => 'We wanted a brand that matches our values: ethical sourcing, great beans and lovely cups.'],
];
$POSTS = [
    ['img' => 'img/blog/inner_b1.png', 'date' => '12 Jan 2026', 'title' => 'How  Araku premium coffee is still grown the traditional way',  'url' => 'coffee_about3.php'],
    ['img' => 'img/blog/inner_b2.png', 'date' => '25 Jan 2026', 'title' => 'Farming in harmony with the land, forest and community',                     'url' => 'coffee_about3.php'],
    ['img' => 'img/blog/inner_b4.png', 'date' => '2 Feb 2026',  'title' => 'From the heart of the Araku Valley to your cup',       'url' => 'coffee_about3.php'],
];

/* ---------------------------------------------------------------------
   PRODUCTS
   --------------------------------------------------------------------- */
$products = [];$byCat    = [];
// products + their live offer from the database (offers table, linked by products.offer_code)
$OFFER_SQL = "SELECT p.*, o.discount_type AS o_type, o.discount_value AS o_value FROM products p
    LEFT JOIN offers o ON o.offer_code = p.offer_code AND o.is_active = 1
     AND (o.starts_at IS NULL OR o.starts_at <= NOW()) AND (o.ends_at IS NULL OR o.ends_at > NOW())
    ORDER BY p.category ASC, p.product_id DESC";
try { $res = $conn->query($OFFER_SQL); } catch (\Throwable $e) {$res = false; }
if (!$res) $res =$conn->query("SELECT * FROM products ORDER BY category ASC, product_id DESC");   // offers tables missing: no offers
if ($res) {
    while ($row =$res->fetch_assoc()) {
        if (!empty($row['o_type'])) $OFFERS[(string)$row['product_id']] = ['type' => $row['o_type'], 'value' => (float)$row['o_value']];
        $cat = strtolower($row['category']);
        // same rule as index.php: image filename must match the product ID
        $pattern = '/^' . preg_quote($row['product_id'], '/') . '\.(jpg|jpeg|png|gif|webp)$/i';
        if (!preg_match($pattern,$row['image'])) continue;

        $meta =$CATEGORIES[$cat] ?? ['label' => ucwords(str_replace('_', ' ',$cat)), 'page' => 'index.php#shop'];
        $p = [
            'id'    => (string)$row['product_id'],
            'name'  => (string)$row['product_name'],
            'cat'   => $cat,
            'price' => round((float)$row['price'] * $currencyInfo['rate'], 2),
            'stock' => (int)$row['quantity'],
            'img'   => "img/shop/{$row['category']}/{$row['image']}",
            'url'   => $meta['page'] . '#product-' . rawurlencode($row['product_id']),
            'isNew' => false,
            'isBest'=> false,
        ];
        $byCat[$cat][] = count($products);
        $products[] =$p;
    }
}
// "New" = newest N per category (rows are already sorted product_id DESC)
foreach ($byCat as $cat =>$idxs) {
    foreach (array_slice($idxs, 0, $NEW_PER_CATEGORY) as$i) $products[$i]['isNew'] = true;
}

// Best sellers from real order data, only if the table exists
$bestIds = [];$t = $conn->real_escape_string($ORDER_ITEMS_TABLE);
$check =$conn->query("SHOW TABLES LIKE '$t'");
if ($check &&$check->num_rows > 0) {
    $bs =$conn->query("SELECT product_id, SUM(quantity) AS sold FROM `$t` GROUP BY product_id ORDER BY sold DESC LIMIT 8");
    if ($bs) while ($r = $bs->fetch_assoc()) $bestIds[] = (string)$r['product_id'];
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

// Rotation step for this page view (counts up on every refresh, per visitor)
$rot = 0;
if ($ROTATE_ON_REFRESH) {
    $_SESSION['home_rotation'] = (int)($_SESSION['home_rotation'] ?? -1) + 1;
    $rot = $_SESSION['home_rotation'];
}

// One featured product per category (hero slides + spotlight cards).
// Rotation on: steps through the in-stock products of each range, a different one each refresh.
// Rotation off: in-stock best seller first, then first in-stock product, then anything.
$featured = [];
$catKeys = array_keys($CATEGORIES);
foreach ($CATEGORIES as $key => $meta) {
    $idxs = $byCat[$key] ?? [];
    if (!$idxs) continue;
    $inStock = array_values(array_filter($idxs, function ($i) use ($products) { return $products[$i]['stock'] > 0; }));
    $pick = null;
    if ($ROTATE_ON_REFRESH && $inStock) {
        $pick = $inStock[($rot + array_search($key, $catKeys)) % count($inStock)];
    } else {
        foreach ($idxs as $i) if ($products[$i]['isBest'] && $products[$i]['stock'] > 0) { $pick = $i; break; }
        if ($pick === null && $inStock) $pick = $inStock[0];
        if ($pick === null) $pick = $idxs[0];
    }
    $featured[$key] = $products[$pick]['id'];
}

// Combos built from real products: coffee bag + mug / cup / tee
$combos = [];
$bagIdx = $byCat['bag'] ?? [];
$pairs  = [['mug', 'Coffee Bag + Coffee Mug'], ['cup', 'Coffee Bag + Coffee Cup'], ['tshirt', 'Coffee Bag+ T-Shirt']];
foreach ($pairs as $n => [$cat, $title]) {
    if (isset($bagIdx[$n]) && !empty($byCat[$cat])) {
        $combos[] = ['title' => $title, 'ids' => [$products[$bagIdx[$n]]['id'], $products[$byCat[$cat][0]]['id']]];
    }
}

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
$conn->close();

/* Find a hero tile image even if its extension or capital letters differ
   (e.g. "tshirt_lifestyle.png" also matches Tshirt_Lifestyle.JPG or tshirt_lifestyle.png.png). */
function findImage($name) {
    if ($name === '') return ['path' => '', 'found' => true];
    if (is_file(__DIR__ . '/' . $name)) return ['path' => $name, 'found' => true];
    $dir  = dirname($name) === '.' ? '' : dirname($name) . '/';
    $want = strtolower(preg_replace('/(\.(png|jpe?g|webp|gif))+$/i', '', basename($name)));
    $files = @scandir(__DIR__ . '/' . $dir) ?: [];
    foreach ($files as $f) {
        $stem = strtolower(preg_replace('/(\.(png|jpe?g|webp|gif))+$/i', '', $f));
        if ($stem === $want && preg_match('/\.(png|jpe?g|webp|gif)$/i', $f)) return ['path' => $dir . $f, 'found' => true];
    }
    return ['path' => $name, 'found' => false];
}
// Built-in copy of the T-shirt photo, used when the image file is missing or broken.
$EMBEDDED_IMAGES = [
    'tshirt' => 'data:image/jpeg;base64,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',
];

function isRealImage($path) {   // true only for a readable, non-empty image file
    $full = __DIR__ . '/' . $path;
    return is_file($full) && filesize($full) > 1024 && @getimagesize($full) !== false;
}
$heroTileNotes = [];
foreach ([&$HERO_SIDE_TILES, &$HERO_RIGHT_TILES] as &$tileSet)
foreach ($tileSet as &$tile) {
    if (empty($tile['img'])) continue;
    $r = findImage($tile['img']);
    if ($r['found'] && isRealImage($r['path'])) { $tile['img'] = $r['path']; continue; }
    $heroTileNotes[] = ($r['found'] ? 'Hero tile image is empty or not a real image: ' : 'Hero tile image not found: ')
                     . __DIR__ . '/' . $r['path'] . (isset($EMBEDDED_IMAGES[$tile['cat']]) ? ' (using the built-in copy)' : '');
    $tile['img'] = $EMBEDDED_IMAGES[$tile['cat']] ?? '';   // built-in copy, else the product photo
}
unset($tile, $tileSet);

$currencyOptions = [];
foreach ($CURRENCY_INFO as $code => $info) $currencyOptions[] = ['code' => $code, 'symbol' => $info['symbol']];

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

$page = [
    'user'       => ['loggedIn' => (bool)$loggedInUserId, 'name' => $_SESSION['user_name'] ?? ''],
    'currency'   => ['code' => $displayCurrency, 'symbol' => $currencyInfo['symbol'], 'options' => $currencyOptions],
    'maskPrices' => $MASK_PRICES,
    'minQty'     => $MIN_QTY,
    'lowStockAt' => $LOW_STOCK_AT,
    'logo'       => $LOGO_IMAGE,
    'categories' => $catList,
    'products'   => $products,
    'bestIds'    => $bestIds,
    'combos'     => $combos,
    'cart'       => (object)$cart,
    'wishlist'   => $wishlist,
    'images'     => $IMAGES,
    'featured'   => (object)$featured,
    'hero'       => [
        'video'     => is_file(__DIR__ . '/' . $HERO_VIDEO) ? $HERO_VIDEO : '',
        'backdrops' => $HERO_BACKDROPS,
        'seconds'   => $SLIDE_SECONDS,
        'sideTiles' => $HERO_SIDE_TILES,
        'rightTiles'=> $HERO_RIGHT_TILES,
        'notes'     => $heroTileNotes,
        'look'      => $HERO_LOOK,
        'split'     => $HERO_PHOTO_SPLIT,
        'line'      => ['w' => $HERO_TILE_LINE_WIDTH, 'c' => $HERO_TILE_LINE_COLOR],
    ],
    'testimonials' => $TESTIMONIALS,
    'posts'      => $POSTS,
    'offersPreview' => $offersPreview,
    'comboDiscount' => max(0, min(90, (int)$COMBO_DISCOUNT_PERCENT)),
    'welcome'    => ['code' => $WELCOME_CODE, 'text' => $WELCOME_TEXT],
    'agentUrl'   => $AI_AGENT_URL,
    'hotUrl'     => $HOT_DEALS_URL,
    'rotation'   => ['on' => (bool)$ROTATE_ON_REFRESH, 'n' => $rot],
    'query'      => $_GET,
];
$pageJson = json_encode($page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>
<?php
$PAGE_TITLE       = 'Araku Tribe - Araku Valley premiumcoffee, cups, mugs and t-shirts';
$PAGE_DESCRIPTION = 'Shop Araku Valley premium coffee bags, branded coffee cups, mugs and T-shirts from Araku Tribe.';
$ACTIVE_NAV       = '';       // home page: no tab, keeps the original header look
$H3_BOT_FLY       = true;     // the AI Agent robot flies around on every visit to the home page

 
include __DIR__ . '/header3.php';

?>
<!-- SPA Container Start -->
<div id="spa-container" data-title="<?= h3_e($PAGE_TITLE) ?>" data-tab-style="<?= h3_e($TAB_STYLE ?? 'brown') ?>">
<style>
/* ============ index.php styles (home page sections, product cards, quick view) ============ */
.hero-slider{position:relative;height:min(84vh,760px);min-height:540px;overflow:hidden;background:var(--brown-deep);color:#fff;isolation:isolate}
.slide{position:absolute;inset:0;opacity:0;visibility:hidden;transition:opacity .9s ease,visibility 0s linear .9s}
.slide.active{opacity:1;visibility:visible;transition:opacity .9s ease}
.slide-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
/* ---------- PRODUCT SHOWCASE slide backgrounds ----------
   A calm "showcase wall": a few large, straight product tiles on the RIGHT side of the slide,
   fading smoothly into a solid dark area behind the text. No tilt, no busy small tiles. */
.hs-collage{position:absolute;inset:0;overflow:hidden;opacity:0;transition:opacity .8s ease;
  background:radial-gradient(90% 120% at 85% 50%,color-mix(in srgb,var(--hs-tint) 38%,#2A1910) 0%,#24150D 70%)}
.hs-collage.ready{opacity:1}                          /* shown only when every tile has a loaded photo */
.hs-grid{position:absolute;top:0;bottom:0;right:0;width:var(--hs-w,60%);box-sizing:border-box;padding:clamp(18px,3vw,40px);
  display:grid;grid-template-columns:repeat(var(--cols,3),1fr);grid-template-rows:repeat(var(--rows,3),1fr);gap:var(--hs-gap,16px)}
.hs-grid span{position:relative;min-height:0;border-radius:22px;overflow:hidden;background:#F3E7DA;
  box-shadow:0 18px 40px -22px rgba(0,0,0,.75),inset 0 0 0 1px rgba(255,255,255,.35)}
.hs-grid span:nth-child(even){background:#EFE1D1}
.hs-grid img{width:100%;height:100%;object-fit:contain;padding:12%;mix-blend-mode:multiply;filter:saturate(.92)}
/* gentle "breathing" instead of a moving grid */
.hs-collage.move .hs-grid img{animation:hsBreath 14s ease-in-out infinite alternate}
.hs-collage.move .hs-grid span:nth-child(3n+2) img{animation-delay:-5s}
.hs-collage.move .hs-grid span:nth-child(3n) img{animation-delay:-9s}
@keyframes hsBreath{from{transform:scale(1)}to{transform:scale(1.06)}}
/* the fade: solid dark behind the text, opening up towards the products on the right */
.hs-mask{position:absolute;inset:0;pointer-events:none;
  background:linear-gradient(90deg,rgba(30,17,10,var(--hs-a)) 0%,rgba(30,17,10,var(--hs-a)) 34%,rgba(30,17,10,calc(var(--hs-a) * .55)) 50%,rgba(30,17,10,calc(var(--hs-a) * .12)) 72%,rgba(30,17,10,0) 100%)}
.brand-slide .hs-grid{--hs-w:58%}
/* range slides (all except Araku Tribe): the wall sits on the LEFT as a soft, blurred backdrop
   behind the text; the big featured product stays clear on the right */
.hs-collage.one .hs-grid{--hs-w:62%;left:0;right:auto}               /* unmasked: clear, sharp product tiles */
.hs-collage.one{background:radial-gradient(90% 120% at 15% 50%,color-mix(in srgb,var(--hs-tint) 38%,#2A1910) 0%,#24150D 70%)}
/* only a light shade right behind the text (strength follows "Collage darkness"), clear everywhere else */
.hs-collage.one .hs-mask{background:linear-gradient(90deg,rgba(30,17,10,calc(var(--hs-a) * .25)) 0%,rgba(30,17,10,0) 55%)}
/* no box around the text: a soft, feathered shadow (no edges) sits behind the words, and the
   letters get their own shadow, so the clear product tiles stay visible around them */
.slide:has(.hs-collage.one.ready) .slide-copy{position:relative;isolation:isolate;
  text-shadow:0 2px 6px rgba(20,11,6,.9),0 6px 26px rgba(20,11,6,.75)}
.slide:has(.hs-collage.one.ready) .slide-copy::before{content:"";position:absolute;z-index:-1;inset:-22% -30% -22% -40%;pointer-events:none;
  background:radial-gradient(closest-side,rgba(24,13,8,.78) 0%,rgba(24,13,8,.6) 45%,rgba(24,13,8,.28) 72%,rgba(24,13,8,0) 100%)}
.bm-main .hs-collage.ready ~ .bm-shade{display:none}   /* the collage brings its own mask */
.hs-collage.ready + .shade{display:none}
@media (max-width:860px){
  .hs-grid,.brand-slide .hs-grid,.hs-collage.one .hs-grid{--hs-w:100%;--hs-gap:10px;padding:14px;opacity:.5;filter:none}
  .hs-collage.one .hs-grid{opacity:1}                                  /* unmasked on phones too: the text has its own panel */
  .hs-collage.one .hs-mask{background:none}
  .hs-mask{background:linear-gradient(0deg,rgba(30,17,10,var(--hs-a)) 0%,rgba(30,17,10,calc(var(--hs-a) * .8)) 55%,rgba(30,17,10,calc(var(--hs-a) * .45)) 100%)!important}
}
@media (prefers-reduced-motion:reduce){ .hs-collage.move .hs-grid img{animation:none} }
/* ---------- FULL-BLEED collage (fill mode): the product wall covers the whole slide background ----------
   No dark gradient, no dimming and no halo anywhere: every part of the slider shows the products
   at full colour and looks the same from edge to edge. The words sit on their own solid panel
   instead, so they stay easy to read without grading the collage. */
.hs-collage.fill{background:#24150D}
.hs-collage.fill .hs-grid{--hs-w:100%;--hs-gap:12px;left:0;right:0;padding:12px;opacity:1}
.hs-collage.fill .hs-grid span{border-radius:14px;background:#3A2417;box-shadow:0 14px 30px -14px rgba(0,0,0,.8)}
.hs-collage.fill .hs-grid img{object-fit:cover;padding:0;mix-blend-mode:normal;filter:none}
.hs-collage.fill .hs-mask{display:none}
.slide:has(.hs-collage.fill.ready) .bm-veil{display:none}
.slide:has(.hs-collage.fill.ready) .slide-copy{position:relative;background:rgba(255,247,237,.97);padding:clamp(24px,2.8vw,40px);
  border-radius:24px;box-shadow:0 20px 48px -20px rgba(42,25,16,.55);border:0;text-shadow:none}
/* light cream panel, so the words switch to the brand's dark brown / orange */
.slide:has(.hs-collage.fill.ready) .slide-copy h1,
.slide:has(.hs-collage.fill.ready) .slide-copy h2{color:var(--brown-deep,#2A1910)}
.slide:has(.hs-collage.fill.ready) .slide-copy .slide-text{color:#5A4636}
.slide:has(.hs-collage.fill.ready) .slide-copy .slide-price{color:#5A4636}
.slide:has(.hs-collage.fill.ready) .slide-copy .slide-price strong{color:var(--brown-deep,#2A1910)}
.slide:has(.hs-collage.fill.ready) .slide-copy .kicker{background:rgba(201,80,26,.12);color:#A23F10;backdrop-filter:none}
.slide:has(.hs-collage.fill.ready) .slide-copy .kicker .ico{color:#C9501A}
.slide:has(.hs-collage.fill.ready) .slide-copy .btn-ghost-light{color:var(--brown-deep,#2A1910)!important;border-color:rgba(42,25,16,.35)!important;background:transparent!important}
.slide:has(.hs-collage.fill.ready) .slide-copy .btn-ghost-light:hover{background:rgba(42,25,16,.08)!important}
.slide:has(.hs-collage.fill.ready) .slide-copy::before{display:none}
/* the opening slide has no picture beside its text, so the panel can use the whole column (not the narrow half) */
.brand-slide:has(.hs-collage.fill.ready) .slide-inner{grid-template-columns:1fr}
/* slide controls: with nothing darkening the collage, their small labels and buttons get their own dark backing so they stay readable */
.hero-slider:has(.hs-collage.fill) .hs-dots{flex:0 1 auto;margin-right:auto;background:rgba(42,25,16,.9);border-radius:999px;padding:6px 24px 12px}
.hero-slider:has(.hs-collage.fill) .hs-dot{color:rgba(255,247,237,.8)}
@media (min-width:681px){ .hero-slider:has(.hs-collage.fill) .hs-dot{flex:0 0 auto;white-space:nowrap;min-width:84px;padding-right:6px} .hero-slider:has(.hs-collage.fill) .hs-dots{gap:12px} }
.hero-slider:has(.hs-collage.fill) .hs-dot[aria-selected="true"]{color:#fff}
.hero-slider:has(.hs-collage.fill) .hs-btn{background:rgba(42,25,16,.9);border-color:rgba(255,247,237,.3)}
@media (max-width:860px){ .hs-collage.fill .hs-grid{opacity:1} }
.slide.active img.slide-bg{animation:drift calc(var(--secs,6s) + 2s) ease-out both}
@keyframes drift{from{transform:scale(1.1)}to{transform:scale(1)}}
.shade{position:absolute;inset:0;background:linear-gradient(90deg,rgba(30,17,10,.86) 0%,rgba(30,17,10,.55) 45%,rgba(30,17,10,.18) 100%)}
.slide-inner{position:relative;height:100%;display:grid;grid-template-columns:1.1fr .9fr;align-items:center;gap:48px;padding-bottom:70px}
.slide-copy{max-width:600px}
.kicker{display:inline-flex;align-items:center;gap:8px;margin:0 0 18px;padding:6px 14px;border-radius:999px;background:rgba(255,247,237,.14);color:var(--cream);font-size:14px;font-weight:600;backdrop-filter:blur(6px)}
.kicker .ico{width:16px;height:16px;color:var(--gold)}
.slide h1,.slide h2{color:#fff;font-size:clamp(2.3rem,5.2vw,4.4rem);line-height:1.04}
.slide-text{margin:18px 0 0;font-size:1.1rem;color:#EBDDCF;max-width:48ch}
.slide-price{margin:18px 0 0;font-size:1.05rem;color:var(--cream)}
.slide-price strong{font-size:1.45rem;color:#fff;margin-right:6px}
.slide-ctas{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}
.slide.active .slide-copy>*{animation:rise .8s cubic-bezier(.2,.7,.2,1) both}
.slide.active .slide-copy>*:nth-child(2){animation-delay:.08s}
.slide.active .slide-copy>*:nth-child(3){animation-delay:.16s}
.slide.active .slide-copy>*:nth-child(4){animation-delay:.24s}
.slide.active .slide-copy>*:nth-child(5){animation-delay:.32s}
@keyframes rise{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:none}}
.slide-figure{justify-self:end;margin:0;width:min(420px,34vw);position:relative}
.slide-figure .frame{aspect-ratio:4/5;border-radius:999px 999px 26px 26px;overflow:hidden;background:var(--cream);box-shadow:0 40px 80px -30px rgba(0,0,0,.7)}
.slide-figure img{width:100%;height:100%;object-fit:cover}
.slide.active .slide-figure{animation:float-in 1s cubic-bezier(.2,.7,.2,1) .1s both}
@keyframes float-in{from{opacity:0;transform:translateY(40px) scale(.96)}to{opacity:1;transform:none}}
.slide-figure figcaption{position:absolute;left:-28px;bottom:28px;background:#fff;color:var(--brown);border-radius:14px;padding:10px 16px;font-weight:700;font-size:14px;box-shadow:var(--shadow)}
.hs-bar{position:absolute;left:0;right:0;bottom:26px;z-index:3}
.hs-bar .wrap{display:flex;align-items:center;gap:14px}
.hs-dots{display:flex;gap:8px;flex:1}
.hs-dot{position:relative;flex:0 1 150px;height:auto;border:0;background:none;padding:10px 0 0;text-align:left;color:rgba(255,247,237,.65);font-size:13px;font-weight:600}
.hs-dot::before,.hs-dot i{content:"";position:absolute;top:0;left:0;height:3px;border-radius:3px}
.hs-dot::before{right:0;background:rgba(255,247,237,.28)}
.hs-dot i{width:0;background:var(--cream)}
.hs-dot[aria-selected="true"]{color:#fff}
.hs-dot[aria-selected="true"] i{animation:fill var(--secs,6s) linear forwards}
.hero-slider.paused .hs-dot i{animation-play-state:paused}
@keyframes fill{to{width:100%}}
.hs-btn{width:44px;height:44px;border-radius:999px;border:1px solid rgba(255,247,237,.35);background:rgba(30,17,10,.25);color:#fff;display:inline-flex;align-items:center;justify-content:center;backdrop-filter:blur(6px)}
.hs-btn:hover{background:rgba(255,247,237,.18)}
.hs-btn .ico{width:20px;height:20px}
.scroll-cue{position:absolute;right:24px;bottom:96px;z-index:3;writing-mode:vertical-rl;color:rgba(255,247,237,.7);font-size:12.5px;letter-spacing:.08em;text-decoration:none;display:flex;align-items:center;gap:10px}
.scroll-cue::after{content:"";width:1px;height:48px;background:linear-gradient(rgba(255,247,237,.7),transparent)}
.hero-slider{--bm-col:clamp(200px,22vw,400px);--bm-gap:6px;--bm-pad:clamp(28px,4vw,72px)}
.hero-slider.has-tiles .hs-bar .wrap{max-width:none;padding-left:calc(var(--bm-col) + var(--bm-gap) + var(--bm-pad))}
.bm{position:absolute;inset:0;display:grid;grid-template-columns:1fr;gap:var(--bm-gap);background:var(--bm-line,#fff);overflow:hidden}
.bm.has-col{grid-template-columns:var(--bm-col) 1fr}
.slide.active .bm{animation:drift calc(var(--secs,6s) + 2s) ease-out both}
.slide.active .bm img.slide-bg{animation:none}
.bm-col{display:grid;grid-template-rows:1fr 1fr;gap:var(--bm-gap);min-height:0}
.bm-main{position:relative;overflow:hidden;min-width:0}
.bm-tile{display:block;overflow:hidden;min-height:0;background:#E6DFD6}
.bm-tile img{width:100%;height:100%;object-fit:cover;padding:0;mix-blend-mode:normal}   /* full-bleed, like the tiles on the right of the photo */
.bm-shade,.bm-veil{position:absolute;pointer-events:none}
.look-clear .bm-shade{top:0;bottom:0;left:0;right:calc((1 - var(--split, .72)) * 100%);
  background:linear-gradient(90deg,rgba(30,17,10,.82) 0%,rgba(30,17,10,.62) 55%,rgba(30,17,10,.4) 100%)}
.look-clear .bm-veil{display:none}
.look-merged .bm-shade{display:none}
.look-merged .bm img{filter:sepia(.3) saturate(.85) brightness(.92) contrast(1.04)}
.look-merged .bm-veil{inset:0;z-index:1;background:rgba(30,17,10,.56)}
.look-merged .slide-copy{text-shadow:0 2px 18px rgba(0,0,0,.35)}
.bm-inner{max-width:none;padding-left:calc(var(--bm-col) + var(--bm-gap) + var(--bm-pad));pointer-events:none;z-index:2}
.bm-inner .slide-copy{max-width:560px;pointer-events:auto}
/* tiles on BOTH sides: same width, same thin lines; the photo's own cup/bag column is trimmed off */
.bm.has-col.has-right{grid-template-columns:var(--bm-col) 1fr var(--bm-col)}
.bm.has-right .bm-main .slide-bg{width:calc(100% / var(--split, .72));max-width:none;right:auto;object-position:left center}
.bm.has-right .bm-shade{right:0!important}
.brand-slide.has-right .bm-inner{padding-right:calc(var(--bm-col) + var(--bm-gap) + var(--bm-pad))}
.hero-slider.has-right .hs-bar .wrap{padding-right:calc(var(--bm-col) + var(--bm-gap) + 20px)}
.brand-slide h1{font-size:clamp(2.2rem,4vw,3.8rem)}
.spot{padding:96px 0 40px}
.stack{display:grid;gap:28px;margin-top:8px}
.stack-card{position:sticky;top:calc(100px + var(--i) * 18px);display:grid;grid-template-columns:1.05fr 1fr;min-height:520px;border-radius:28px;overflow:hidden;transform-origin:50% 0;will-change:transform;box-shadow:0 -10px 40px -24px rgba(30,17,10,.45)}
.sc-media{position:relative;padding:28px;display:flex}
.sc-media a{flex:1;border-radius:20px;overflow:hidden;background:rgba(255,255,255,.1)}
.sc-media img{width:100%;height:100%;object-fit:cover;transition:transform .7s}
.stack-card:hover .sc-media img{transform:scale(1.04)}
.sc-media .badges{top:44px;left:44px}
.sc-info{padding:36px 40px 32px 16px;display:flex;flex-direction:column}
.sc-cat{display:flex;justify-content:space-between;gap:12px;margin:0 0 14px;font-weight:600;font-size:14.5px}
.sc-cat span{opacity:.7;font-weight:500}
.stack-card h3{font-size:clamp(1.7rem,2.6vw,2.3rem);line-height:1.1;color:inherit}
.sc-blurb{margin:10px 0 0;opacity:.85;max-width:44ch}
.sc-perks{list-style:none;padding:0;margin:16px 0 0;display:flex;flex-wrap:wrap;gap:8px}
.sc-perks li{padding:7px 13px;border-radius:999px;font-size:13.5px;font-weight:600;border:1px solid currentColor;opacity:.85}
.sc-id{margin:6px 0 0;font-size:13px;letter-spacing:.03em;opacity:.7}
.sc-facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:16px 0 0}
.sc-facts div{padding:9px 12px;border-radius:14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14)}
.theme-cup .sc-facts div{background:rgba(255,255,255,.6);border-color:rgba(59,35,23,.08)}
.sc-facts dt{font-size:12px;opacity:.72}
.sc-facts dd{margin:3px 0 0;font-weight:700;font-size:15px}
.sc-more-row{margin-top:16px}
.sc-more-row p{margin:0 0 8px;font-size:13px;font-weight:700;opacity:.8}
.sc-thumbs{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
.sc-thumb{display:grid;grid-template-columns:44px minmax(0,1fr);column-gap:8px;align-items:center;padding:6px;border-radius:14px;background:rgba(255,255,255,.08)!important;border:0;padding:0;background:none;color:inherit;text-align:left;min-width:0}
.sc-thumb img{grid-row:span 2;width:44px;height:44px;object-fit:contain;padding:4px;border-radius:10px;background:#fff;transition:transform .25s}
.theme-cup .sc-thumb{background:rgba(255,255,255,.6)!important}
.sc-thumb:hover img{transform:translateY(-3px)}
.sc-thumb span{margin-top:0;font-size:12px;font-weight:600;line-height:1.3;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden}
.sc-thumb small{font-size:12px;opacity:.75}
@media (min-width:861px) and (max-height:820px){ .sc-more-row{display:none} }   /* short laptop screens: keep the whole card on screen */
.sc-buy{margin-top:auto;padding-top:18px;display:flex;align-items:baseline;gap:14px;flex-wrap:wrap}
.sc-buy .price{margin:0;font-size:1.6rem;color:inherit}
.sc-buy .price span{color:inherit;opacity:.7}
.sc-stock{font-size:14px;font-weight:600;opacity:.85}
.sc-ctas{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.sc-ctas .add{width:auto;flex:1;min-width:190px;padding:14px 22px;background:var(--orange-cta);border-color:var(--orange-cta);color:#fff}
.sc-ctas .add:hover{background:var(--orange-cta-hover);border-color:var(--orange-cta-hover)}
.sc-ctas .add.in-cart{background:#fff;border-color:#fff;color:var(--green)}
.sc-ctas .sc-quick{border:1.5px solid currentColor;background:transparent;color:inherit;border-radius:999px;padding:13px 22px;font-weight:700;font-size:14.5px}
.sc-more{margin-top:14px;align-self:flex-start;color:inherit;font-weight:700;text-decoration:underline;text-underline-offset:5px;text-decoration-thickness:1px}
.theme-bag{background:var(--brown);color:var(--cream)}
.theme-cup{background:var(--cream-2);color:var(--brown-deep)}
.theme-cup h3{color:var(--brown)}
.theme-mug{background:var(--green);color:#F0F6F2}
.theme-tshirt{background:var(--brown-deep);color:var(--cream)}
.theme-cup .sc-media a{background:#fff}
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
.card .card-body{align-items:stretch;justify-content:flex-start}
/* every line is left-justified; the script below sets margin-left so the block starts exactly at the Add-to-cart icon (centred fallback until it runs) */
.card .card-info{width:fit-content;max-width:100%;margin:0 auto;text-align:left}
.card .card-info.aligned{width:auto;margin-right:0}
.card .card-info,.card .card-info *{text-align:left}
.card .card-info .card-cat{justify-content:flex-start}
/* ID, price and min-order all use the same text size, whatever the currency or offer */
.card .card-info .card-id,.card .card-info .moq,.card .card-info .price,.card .card-info .price span,.card .card-info .price s,.card .card-info .price-note{font-size:13.5px;line-height:1.35}
.card .card-info .price{justify-content:flex-start;align-items:baseline;flex-wrap:wrap;gap:2px 6px;margin:6px 0 0;font-weight:700;font-variant-numeric:tabular-nums}
.card .card-info .price span,.card .card-info .price s{font-weight:500}
.card .card-info .price .deal{font-weight:700}
.card .card-info .price-note{margin:2px 0 0}
.card .card-info .moq{margin:3px 0 12px}
.combo .add{align-self:center}
.combo .card-info{width:100%;max-width:100%;margin:0 auto;text-align:center}
.add{margin-top:auto;width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 14px;border-radius:999px;border:0;background:var(--brown-deep);color:var(--cream);font-weight:700;font-size:14.5px;transition:background-color .2s}
.add::before{content:"";width:17px;height:17px;background:currentColor;-webkit-mask:var(--bag-ico) center/contain no-repeat;mask:var(--bag-ico) center/contain no-repeat}
.add:hover{background:var(--orange-cta);color:#fff}
.add.in-cart{background:var(--green);color:#fff}
.add:disabled{background:var(--cream-2);color:var(--muted);cursor:not-allowed}
.add:disabled::before{display:none}
.rail{display:grid;grid-auto-flow:column;grid-auto-columns:calc((100% - 3 * 20px) / 4);gap:20px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;padding:8px 4px 30px;margin:-8px -4px -30px;scrollbar-width:none}
.rail::-webkit-scrollbar{display:none}
.rail>*{scroll-snap-align:start}
.rail-nav{display:flex;gap:8px}
.rail-nav[hidden]{display:none}
.rail-nav button{width:46px;height:46px;border-radius:999px;border:1.5px solid var(--line);background:#fff;color:var(--brown);display:inline-flex;align-items:center;justify-content:center;transition:background-color .2s,border-color .2s}
.rail-nav button:hover:not(:disabled){border-color:var(--brown);background:var(--cream)}
.rail-nav button:disabled{opacity:.35;cursor:default}
.rail-nav .ico{width:20px;height:20px}
.head-side{display:flex;align-items:center;gap:18px}
.eyebrow{display:inline-flex;align-items:center;gap:8px;margin:0 0 10px;font-weight:600;font-size:14px;color:var(--orange-cta)}
.eyebrow .ico{width:16px;height:16px}
.section-dark{background:var(--brown-deep);color:var(--cream)}
.section-dark h2{color:#fff}
.section-dark .lede{color:#D9C6B4}
.section-dark .rail-nav button{background:transparent;border-color:rgba(255,247,237,.3);color:var(--cream)}
.section-dark .rail-nav button:hover:not(:disabled){background:rgba(255,247,237,.1);border-color:var(--cream)}
.section-dark .link{color:var(--cream)}
.rank{position:absolute;left:14px;bottom:6px;z-index:1;font-family:var(--serif);font-size:3.4rem;line-height:1;color:var(--brown);opacity:.85;pointer-events:none}
.section-dark .badge-best{display:none}
.offers-sec{background:linear-gradient(180deg,#fff 0%,var(--cream) 100%)}
.preview-note{display:flex;gap:10px;align-items:flex-start;margin:0 0 24px;padding:12px 16px;border-radius:var(--r-md);background:#FFF1D6;border:1px dashed #D9A441;color:#6A4A12;font-size:14px}
.preview-note strong{white-space:nowrap}
.agent-sec{padding:72px 0 8px}
.agent-card{position:relative;display:grid;grid-template-columns:1.05fr .95fr;gap:40px;align-items:center;padding:56px;border-radius:32px;overflow:hidden;color:var(--cream);
  background:radial-gradient(120% 90% at 100% 0%,#8A4F2B 0%,rgba(138,79,43,0) 55%),radial-gradient(90% 80% at 0% 100%,#2F6B4F 0%,rgba(47,107,79,0) 60%),var(--brown-deep)}
.agent-card::after{content:"";position:absolute;inset:0;pointer-events:none;opacity:.12;mix-blend-mode:overlay;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E")}
.agent-card h2{color:#fff;font-size:clamp(2rem,3.6vw,3rem)}
.agent-card .lede{color:#E6D5C4;font-size:1.06rem}
.agent-kicker{display:inline-flex;align-items:center;gap:8px;margin:0 0 16px;padding:6px 14px 6px 8px;border-radius:999px;background:rgba(255,247,237,.12);font-weight:600;font-size:14px}
.agent-kicker .orb{width:24px;height:24px}
.agent-steps{list-style:none;padding:0;margin:26px 0 30px;display:grid;gap:12px}
.agent-steps li{display:flex;align-items:center;gap:12px;font-weight:500}
.agent-steps b{flex:none;width:30px;height:30px;border-radius:999px;background:rgba(255,247,237,.14);display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--gold)}
.agent-ctas{display:flex;flex-wrap:wrap;align-items:center;gap:12px}
.agent-demo{position:relative;z-index:1;background:rgba(255,247,237,.96);color:var(--ink);border-radius:24px;padding:18px;box-shadow:0 30px 60px -30px rgba(0,0,0,.7);max-width:430px;justify-self:end;width:100%}
.demo-head{display:flex;align-items:center;gap:10px;padding:0 4px 14px;border-bottom:1px solid var(--line);margin-bottom:14px}
.demo-head strong{display:block;font-size:14.5px;color:var(--brown-deep)}
.demo-head small{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--green)}
.demo-head small::before{content:"";width:7px;height:7px;border-radius:50%;background:#3DBE7A}
.demo-msgs{display:grid;gap:10px;min-height:300px;align-content:start}
.bub{max-width:82%;padding:10px 14px;border-radius:16px;font-size:14px;line-height:1.45;opacity:0;transform:translateY(8px);transition:opacity .4s,transform .4s}
.bub.show{opacity:1;transform:none}
.bub.bot{background:#fff;border-radius:4px 16px 16px 16px;box-shadow:0 2px 10px -4px rgba(59,35,23,.25)}
.bub.me{justify-self:end;background:var(--brown);color:#fff;border-radius:16px 4px 16px 16px}
.bub.plain{background:none;box-shadow:none;padding:0;max-width:100%}
.bub-chips{display:flex;flex-wrap:wrap;gap:6px}
.bub-chips span{padding:6px 12px;border-radius:999px;border:1.5px solid var(--gold);font-size:12.5px;font-weight:700;color:var(--brown)}
.bub-chips span.go{background:var(--orange-cta);border-color:var(--orange-cta);color:#fff}
.bub-prod{display:flex;gap:10px;align-items:center}
.bub-prod img{width:48px;height:48px;border-radius:10px;object-fit:contain;background:#F6EEE3;mix-blend-mode:multiply}
.bub-prod small{display:block;color:var(--muted)}
.chips{display:flex;gap:8px;flex-wrap:wrap;margin:-12px 0 28px}
.chip{border:1.5px solid var(--line);background:#fff;border-radius:999px;padding:7px 14px;font-weight:600;font-size:13.5px;color:var(--ink)}
.chip:hover{border-color:var(--brown-soft)}
.chip[aria-pressed="true"]{background:var(--cream);border-color:var(--brown);color:var(--brown)}
.chip[aria-pressed="true"]::before{content:"✓ "}
.toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:28px;padding-bottom:18px;border-bottom:1px solid var(--line)}
.tabs{min-width:0;max-width:100%;display:flex;gap:8px;overflow-x:auto;scrollbar-width:none;padding:2px}
.tabs::-webkit-scrollbar{display:none}
.tab{white-space:nowrap;border:1.5px solid var(--line);background:#fff;border-radius:999px;padding:8px 16px;font-weight:600;font-size:14.5px;color:var(--ink)}
.tab:hover{border-color:var(--brown-soft)}
.tab[aria-pressed="true"]{background:var(--brown);border-color:var(--brown);color:#fff}
.tab small{font-weight:500;opacity:.75;margin-left:4px}
.tools{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.toggle{display:flex;align-items:center;gap:8px;font-size:14.5px;font-weight:500}
.toggle input{width:18px;height:18px;accent-color:var(--brown)}
.select{height:42px;border:1.5px solid var(--line);border-radius:999px;padding:0 14px;background:#fff;font:inherit;font-size:14.5px;color:var(--ink)}
.active-query{display:flex;align-items:center;gap:10px;margin:-10px 0 24px;font-size:15px;color:var(--muted)}
.active-query[hidden]{display:none}
.active-query button{border:0;background:var(--cream);border-radius:999px;padding:5px 12px;font-weight:600;color:var(--brown)}
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
.combo h3{font-size:1.15rem;line-height:1.35;margin:18px 10px 0;text-align:center}
.combo ul{list-style:none;padding:0;margin:8px 10px 14px;font-size:14px;color:var(--muted)}
.combo li{padding:2px 0}
.combo .price{margin:10px 10px 16px;justify-content:center;text-align:center}
.combo .add{width:auto;margin:auto 10px 0}
.story{background:var(--green);color:#EAF2EC}
.story .wrap{display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center}
.story h2{color:#fff}
.story .lede{color:#CFE0D5}
.story-img{border-radius:var(--r-lg);overflow:hidden;aspect-ratio:5/4;background:#24563F}
.story-img img{width:100%;height:100%;object-fit:cover}
.values{list-style:none;padding:0;margin:32px 0;display:grid;gap:18px}
.values li{display:flex;gap:14px;align-items:flex-start}
.values .dot{flex:none;width:40px;height:40px;border-radius:999px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;color:var(--gold)}
.values strong{display:block;color:#fff;font-weight:600}
.values span{font-size:14.5px;color:#CFE0D5}
.quotes{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.quote{background:#fff;border:1px solid var(--line);border-radius:var(--r-lg);padding:28px;margin:0;display:flex;flex-direction:column}
.quote blockquote{margin:0 0 22px;font-family:var(--serif);font-size:1.12rem;line-height:1.55;color:var(--brown-deep);display:-webkit-box;-webkit-line-clamp:3;line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;height:calc(1.12rem*1.55*3)}
.quote figcaption{margin-top:auto;font-size:14px;color:var(--muted)}
.quote figcaption strong{display:block;color:var(--ink);font-size:15px}
.posts{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.post{text-decoration:none}
.post .media{aspect-ratio:3/2;border-radius:var(--r-md);overflow:hidden;background:var(--cream)}
.post img{width:100%;height:100%;object-fit:cover;transition:transform .5s}
.post:hover img{transform:scale(1.04)}
.post time{display:block;margin-top:14px;font-size:13.5px;color:var(--muted)}
.post h3{font-size:1.25rem;margin-top:4px;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;height:calc(1.25rem*1.3*2)}
.post:hover h3{color:var(--orange-cta)}
.insta{display:grid;grid-template-columns:repeat(6,1fr);gap:8px}
.insta a{aspect-ratio:1/1;overflow:hidden;border-radius:var(--r-sm);background:var(--cream)}
.insta img{width:100%;height:100%;object-fit:cover;transition:opacity .2s}
.insta a:hover img{opacity:.85}
.b2b{background:var(--brown);color:var(--cream)}
.b2b .wrap{display:flex;align-items:center;justify-content:space-between;gap:32px;padding-top:56px;padding-bottom:56px;flex-wrap:wrap}
.b2b h2{color:#fff}
.b2b p{margin:.5rem 0 0;color:#E9D8C8;max-width:58ch}
.b2b-ctas{display:flex;gap:12px;flex-wrap:wrap}
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
  .rail{grid-auto-columns:calc((100% - 2 * 20px) / 3)}
  .grid{grid-template-columns:repeat(3,1fr)}
}
@media (max-width:860px){
  .hero-slider{height:88svh;min-height:600px;max-height:760px}
  .slide-inner{grid-template-columns:1fr;grid-template-rows:auto auto;align-content:end;gap:22px;padding-top:28px;padding-bottom:96px}
  .slide-figure{grid-row:1;justify-self:start;width:min(210px,52vw)}
  .slide-figure figcaption{left:auto;right:-60px;bottom:18px}
  .shade{background:linear-gradient(0deg,rgba(30,17,10,.92) 0%,rgba(30,17,10,.6) 55%,rgba(30,17,10,.3) 100%)}
  .scroll-cue{display:none}
  .bm.has-col{grid-template-columns:1fr}
  .bm-col{display:none}
  .bm.has-col.has-right{grid-template-columns:1fr}
  .bm.has-right .bm-main .slide-bg{width:100%;object-position:center}
  .brand-slide.has-right .bm-inner{padding-right:16px}
  .hero-slider.has-right .hs-bar .wrap{padding-right:88px}
  .bm-inner{padding-left:16px}
  .look-clear .bm-shade{right:0;background:linear-gradient(0deg,rgba(30,17,10,.9) 0%,rgba(30,17,10,.6) 55%,rgba(30,17,10,.3) 100%)}
  .hero-slider.has-tiles .hs-bar .wrap{padding-left:16px}
  .stack-card{position:relative;top:auto;grid-template-columns:1fr;min-height:0;transform:none!important}
  .sc-media{padding:16px 16px 0;aspect-ratio:4/3}
  .sc-info{padding:24px 22px 28px}
  .quotes,.posts{grid-template-columns:1fr}
  .combo-grid{grid-template-columns:1fr 1fr}
  .agent-card{grid-template-columns:1fr}
  .agent-demo{justify-self:start}
  .story .wrap{grid-template-columns:1fr;gap:36px}
  .insta{grid-template-columns:repeat(3,1fr)}
  .qv-inner{grid-template-columns:1fr}
  .qv-img{aspect-ratio:4/3}
}
@media (max-width:680px){
  .hs-dot{font-size:0}
  .hero-slider{height:78svh;min-height:560px}
  .slide-inner{gap:16px;padding-bottom:88px}
  .slide h1,.slide h2{font-size:2.05rem}
  .kicker{margin-bottom:12px;font-size:13px}
  .slide-text{font-size:1rem;margin-top:10px}
  .slide-inner:has(.slide-figure) .slide-text{display:none}
  .brand-slide .slide-text{display:none}
  .brand-slide h1{font-size:2.1rem}
  .slide-figure{width:150px}
  .slide-figure figcaption{display:none}
  .slide-price{margin-top:10px}
  .slide-ctas{margin-top:18px;gap:8px}
  .slide-ctas .btn{padding:11px 18px;font-size:14.5px}
  .hs-btn{width:38px;height:38px}
  .hs-dots{gap:6px}
  .rail{grid-auto-columns:72%;gap:14px}
  .rail-nav{display:none!important}
  .agent-card{grid-template-columns:1fr;padding:30px 22px;border-radius:24px}
  .agent-demo{justify-self:stretch}
  .card{padding:8px;border-radius:18px}
  .card-title{font-size:1rem}
  .add{font-size:13.5px;padding:11px 10px}
  .combo-grid{grid-template-columns:1fr}
  .section{padding:60px 0}
  .grid{grid-template-columns:repeat(2,1fr);gap:22px 12px}
  .quick{display:none}
  .toolbar{flex-direction:column;align-items:stretch}
  .tools{justify-content:space-between}
}
/* ============ HOT DEALS + AI AGENT ribbon (right under the hero) ============ */
.promo-ribbon{padding:28px 0 0}
.pr-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.promo-ribbon.swap .pr-hot{order:2}
.pr-tile{position:relative;display:flex;align-items:center;gap:18px;min-height:132px;padding:20px 24px;border-radius:26px;text-decoration:none;color:#fff;overflow:hidden;isolation:isolate;transition:transform .25s}
.pr-tile:hover{transform:translateY(-3px) scale(1.01)}
.pr-tile h2{color:#fff;font-family:var(--sans);font-weight:900;font-size:clamp(1.35rem,2.2vw,1.9rem);letter-spacing:.04em;text-transform:uppercase;line-height:1.05}
.pr-tile p{margin:6px 0 0;font-size:15px;color:rgba(255,255,255,.92);max-width:38ch}
.pr-tile .go{display:inline-flex;align-items:center;gap:6px;margin-top:10px;padding:7px 14px;border-radius:999px;background:#fff;font-weight:800;font-size:13.5px}
.pr-tile .go .ico{width:16px;height:16px}
.pr-hot{background:linear-gradient(110deg,#B80F16 0%,#E3121B 30%,#FF4D00 60%,#FF9500 100%);background-size:200% 100%;animation:hotFlow 5s linear infinite;box-shadow:0 22px 40px -22px rgba(227,18,27,.9)}
.pr-hot .go{color:#E3121B}
.pr-hot .big-flame{flex:none;width:78px;height:78px;fill:#FFE066;stroke:#fff;stroke-width:.8;filter:drop-shadow(0 0 12px #FFD400);animation:flick .8s ease-in-out infinite alternate;transform-origin:50% 90%}
.pr-bot{background:radial-gradient(120% 120% at 100% 0%,#8B00FF 0%,rgba(139,0,255,0) 45%),radial-gradient(100% 120% at 0% 100%,#0066FF 0%,rgba(0,102,255,0) 55%),#2A1150;box-shadow:0 22px 40px -22px rgba(75,0,130,.9)}
.pr-bot::before{content:"";position:absolute;inset:0;z-index:-1;border-radius:inherit;padding:3px;background:conic-gradient(from var(--spin,0deg),#8B00FF,#4B0082,#0066FF,#00B140,#FFD400,#FF7A00,#E3121B,#8B00FF);
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude;animation:spin 3s linear infinite}
.pr-bot .go{color:#4B0082}
.pr-bot .pr-robot{flex:none;width:92px;height:92px;--bot:#E3121B;animation:redGlow 2s ease-in-out infinite,bob 2.6s ease-in-out infinite}
.pr-tile .sparkle{width:14px;height:14px}
.pr-tile .sparkle.s1{top:14px;right:22px}
.pr-tile .sparkle.s2{bottom:16px;right:34%}
.pr-tile .sparkle.s3{top:30%;left:46%}
/* make the home page AI Agent card pop too */
.agent-card{box-shadow:0 0 0 3px #E3121B,0 30px 60px -30px rgba(227,18,27,.7)}
.agent-kicker .orb{background:radial-gradient(circle at 35% 30%,#FF6B6B 0%,#E3121B 55%,#8E0A10 100%)}
/* keep the slider buttons clear of the floating AI Agent robot in the corner */
@media (max-width:1560px){ .hero-slider .hs-bar .wrap{padding-right:150px} .scroll-cue{display:none} }
@media (max-width:680px){ .hero-slider .hs-bar .wrap{padding-right:88px} }
@media (max-width:860px){ .pr-grid{grid-template-columns:1fr} .promo-ribbon.swap .pr-hot{order:0} }
@media (max-width:520px){ .pr-tile{gap:12px;padding:16px 18px;min-height:0} .pr-hot .big-flame{width:56px;height:56px} .pr-bot .pr-robot{width:66px;height:66px} }
</style>

<main>
  <!-- HERO SLIDER (video + one product per category) -->
  <section class="hero-slider" id="hero" aria-roledescription="carousel" aria-label="Featured products">
    <div id="slides"></div>
    <div class="hs-bar">
      <div class="wrap">
        <div class="hs-dots" id="hsDots" role="tablist" aria-label="Choose a slide"></div>
        <button class="hs-btn" id="hsPause" aria-label="Pause slideshow"><svg class="ico"><use href="#i-pause"/></svg></button>
        <button class="hs-btn" id="hsPrev" aria-label="Previous slide"><svg class="ico"><use href="#i-left"/></svg></button>
        <button class="hs-btn" id="hsNext" aria-label="Next slide"><svg class="ico"><use href="#i-right"/></svg></button>
      </div>
    </div>
    <a class="scroll-cue" href="#spotlight">Scroll</a>
  </section>

  <!-- HOT DEALS + AI AGENT ribbon (their order swaps on every refresh) -->
  <section class="promo-ribbon" id="promoRibbon" aria-label="Hot deals and AI Agent">
    <div class="wrap pr-grid">
      <a class="pr-tile pr-hot" href="<?= h3_link('hotdeals') ?>">
        <svg class="big-flame" aria-hidden="true"><use href="#i-flame"/></svg>
        <span><h2>Hot deals</h2><p id="prHotText">Our best prices on premium coffee, cups, mugs and t-shirts, all on one page.</p><span class="go">Grab the deals <svg class="ico"><use href="#i-right"/></svg></span></span>
        <i class="sparkle s1"></i><i class="sparkle s2"></i><i class="sparkle s3"></i>
      </a>
      <a class="pr-tile pr-bot" href="<?= h3_link('agent') ?>">
        <span class="pr-robot" aria-hidden="true"><?= h3_bot_svg() ?></span>
        <span><h2>Ask our AI Agent</h2><p id="prBotText">Skip the browsing. Tell the agent what you need and check out in one chat.</p><span class="go">Start chatting <svg class="ico"><use href="#i-right"/></svg></span></span>
        <i class="sparkle s1"></i><i class="sparkle s2"></i><i class="sparkle s3"></i>
      </a>
    </div>
  </section>

  <!-- AI AGENT: express checkout -->
  <section class="agent-sec" id="agent" aria-labelledby="agentTitle">
    <div class="wrap">
      <div class="agent-card">
        <div style="position:relative;z-index:1">
          <p class="agent-kicker"><span class="orb"><svg class="ico" style="stroke:#fff"><use href="#i-robot"/></svg></span>Araku AI Agent</p>
          <h2 id="agentTitle">Express checkout, all in one chat.</h2>
          <p class="lede">Tell the agent what you need. It finds the products, sets the quantity, takes your address and gets you to payment, without browsing a single page.</p>
          <ol class="agent-steps">
            <li><b>1</b>Pick your products in the chat</li>
            <li><b>2</b><span>Choose the quantity in steps of <span data-minqty></span></span></li>
            <li><b>3</b>Add your address and pay</li>
          </ol>
          <div class="agent-ctas">
            <a class="btn btn-primary btn-lg" href="<?= h3_link('agent') ?>">Start express checkout</a>
            <a class="btn btn-ghost-light" href="<?= h3_link('agent') ?>">Track or cancel an order</a>
          </div>
        </div>
        <div class="agent-demo" aria-hidden="true">
          <div class="demo-head"><span class="orb"><svg class="ico"><use href="#i-spark"/></svg></span><div><strong>Araku AI Agent</strong><small>Online</small></div></div>
          <div class="demo-msgs" id="demoMsgs"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- SPOTLIGHT: one product from each category, stacking cards -->
  <section class="spot" id="spotlight" aria-labelledby="spotTitle">
    <div class="wrap">
      <div class="section-head">
        <div><h2 id="spotTitle">One from every range</h2><p class="lede">A favourite from each collection. Keep scrolling to flip through them.</p></div>
      </div>
      <div class="stack" id="stack"></div>
    </div>
  </section>

  <!-- OFFERS (real offers from $OFFERS, or labelled samples on localhost) -->
  <section class="section offers-sec" aria-labelledby="offTitle" id="offers" hidden>
    <div class="wrap">
      <div class="section-head">
        <div><p class="eyebrow"><svg class="ico"><use href="#i-spark"/></svg>Limited time</p><h2 id="offTitle">Offers this week</h2><p class="lede">Real savings on selected products. The price you see is the price you pay.</p></div>
        <div class="head-side"><a class="link" href="<?= h3_link('hotdeals') ?>">See all hot deals</a><div class="rail-nav" data-rail="offerRail"></div></div>
      </div>
      <div id="offerNote"></div>
      <div class="rail" id="offerRail"></div>
    </div>
  </section>

  <!-- NEW ARRIVALS -->
  <section class="section section-cream" aria-labelledby="newTitle" id="newSection">
    <div class="wrap">
      <div class="section-head">
        <div><p class="eyebrow" style="color:var(--green)">Just in</p><h2 id="newTitle">New arrivals</h2><p class="lede">The latest additions to each range.</p></div>
        <div class="head-side"><button class="link" style="background:none;border:0" data-sort-jump="newest">Shop everything new</button><div class="rail-nav" data-rail="newRail"></div></div>
      </div>
      <div class="rail" id="newRail"></div>
    </div>
  </section>

  <!-- BEST SELLERS (only shown when real order data exists) -->
  <section class="section section-dark" aria-labelledby="bestTitle" id="bestSection" hidden>
    <div class="wrap">
      <div class="section-head">
        <div><p class="eyebrow" style="color:var(--gold)">Most ordered</p><h2 id="bestTitle">Best sellers</h2><p class="lede">Ranked by what our customers order most.</p></div>
        <div class="rail-nav" data-rail="bestRail"></div>
      </div>
      <div class="rail" id="bestRail"></div>
    </div>
  </section>

  <!-- CATALOGUE -->
  <section class="section" aria-labelledby="shopTitle" id="shop">
    <div class="wrap">
      <div class="section-head">
        <div><h2 id="shopTitle">Shop the range</h2><p class="lede" id="shopCount"></p></div>
      </div>
      <div class="toolbar">
        <div class="tabs" id="tabs" role="group" aria-label="Filter by category"></div>
        <div class="tools">
          <label class="sr-only" for="sort">Sort by</label>
          <select class="select" id="sort">
            <option value="rec">Recommended</option>
            <option value="newest">Newest</option>
            <option value="price-asc">Price: low to high</option>
            <option value="price-desc">Price: high to low</option>
            <option value="stock">Most in stock</option>
            <option value="name">Name: A to Z</option>
          </select>
        </div>
      </div>
      <div class="chips" id="chips" role="group" aria-label="Refine"></div>
      <div class="active-query" id="activeQuery" hidden><span></span><button type="button" id="clearQuery">Clear search</button></div>
      <div class="grid" id="catalogGrid" aria-live="polite"></div>
      <div class="more-row"><button class="btn btn-outline" id="moreBtn" hidden>Show more products</button></div>
    </div>
  </section>

  <!-- COMBOS -->
  <section class="section section-cream" aria-labelledby="comboTitle" id="combos">
    <div class="wrap">
      <div class="section-head">
        <div><p class="eyebrow">Better together</p><h2 id="comboTitle">Gifts &amp; combos</h2><p class="lede">Premium 
          coffee paired with something to drink it from. Each set adds <span data-minqty></span> of both items.</p></div>
        <a class="link" href="<?= h3_link('combos') ?>#build">Build your own combo</a>
      </div>
      <div class="combo-grid" id="comboGrid"></div>
    </div>
  </section>

  <!-- STORY -->
  <section class="section story" aria-labelledby="storyTitle">
    <div class="wrap">
      <div class="story-img"><img id="storyImg" alt="Coffee plantations in the Araku Valley" loading="lazy"></div>
      <div>
        <h2 id="storyTitle">Every bag starts with a farmer in the hills.</h2>
        <p class="lede"> Araku premium coffee is grown by the valley's tribal communities using methods passed down for generations. We bring it to you without shortcuts.</p>
        <ul class="values">
          <li><span class="dot"><svg class="ico"><use href="#i-hand"/></svg></span><div><strong>Hand-picked beans</strong><span>Harvested by Araku tribal farmers, cherry by cherry.</span></div></li>
          <li><span class="dot"><svg class="ico"><use href="#i-leaf"/></svg></span><div><strong>Grown the natural way</strong><span>No chemical fertilisers or pesticides on the farms.</span></div></li>
          <li><span class="dot"><svg class="ico"><use href="#i-box"/></svg></span><div><strong>Packed with care</strong><span>Sealed in eco-friendly wheat bags to keep the aroma in.</span></div></li>
        </ul>
        <a class="btn btn-ghost-light" href="coffee_about3.php">Read our story</a>
      </div>
    </div>
  </section>

  <!-- REVIEWS -->
  <section class="section" aria-labelledby="revTitle">
    <div class="wrap">
      <div class="section-head"><div><h2 id="revTitle">What our café partners say</h2></div></div>
      <div class="quotes" id="quotes"></div>
    </div>
  </section>

  <!-- JOURNAL -->
  <section class="section section-cream" aria-labelledby="jTitle">
    <div class="wrap">
      <div class="section-head">
        <div><h2 id="jTitle">From the Araku journal</h2><p class="lede">Premium coffee, culture and the valley it comes from.</p></div>
        <a class="link" href="coffee_about3.php">Read all stories</a>
      </div>
      <div class="posts" id="posts"></div>
    </div>
  </section>

  <!-- INSTAGRAM -->
  <section class="section" aria-labelledby="igTitle">
    <div class="wrap">
      <div class="section-head">
        <div><h2 id="igTitle">Life with the Tribe</h2><p class="lede">Follow us on Instagram for new drops and café stories.</p></div>
        <a class="link" href="https://www.instagram.com/araku_tribe" target="_blank" rel="noopener">Follow @araku_tribe</a>
      </div>
      <div class="insta" id="insta"></div>
    </div>
  </section>

  <!-- FRANCHISE (separate B2B journey) -->
  <section class="b2b" aria-labelledby="b2bTitle">
    <div class="wrap">
      <div>
        <h2 id="b2bTitle">Run a café? Serve Araku Tribe Premium Coffee.</h2>
        <p>We supply Araku premium coffee powder, branded cups and coffee machines to partner shops, with the branding support to match.</p>
      </div>
      <div class="b2b-ctas">
        <a class="btn btn-primary" href="coffee_franchise3.php">Explore franchise</a>
        <a class="btn btn-ghost-light" href="coffee_contact3.php">Talk to our team</a>
      </div>
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
      <a class="link" id="qvLink" href="#">See full details</a>
    </div>
  </div>
</dialog>


<script>
window.DATA = <?= $pageJson ?>;
// slider backgrounds (admin settings > Home slider)
DATA.heroBg = <?= json_encode([
    'mode' => function_exists('h3_set') ? h3_set('hs_bg', 'collage') : 'collage',
    'dim'  => function_exists('h3_set') ? (int)h3_set('hs_dim', '72') : 72,
    'move' => function_exists('h3_set') ? h3_set('hs_move', '1') === '1' : true,
    'fill' => (bool)$HERO_COLLAGE_FILL,
]) ?>;
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
const ROT = (DATA.rotation && DATA.rotation.on) ? (DATA.rotation.n || 0) : null;   // null = no rotation
const rotate = (arr, by) => arr.length ? arr.slice(by % arr.length).concat(arr.slice(0, by % arr.length)) : arr;

/* ---------- static fills ---------- */
$$('[data-minqty]').forEach(el => el.textContent = MIN);
$('#storyImg').src = DATA.images.story;

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
function addBtn(p, inCart, out) {
  if (out) return `<button class="add" disabled>Sold out</button>`;
  return inCart
    ? `<button class="add in-cart" data-add="${esc(p.id)}">Add more (${inCart} in cart)</button>`
    : `<button class="add" data-add="${esc(p.id)}">Add to cart</button>`;
}
function refreshCards(id) {
  const p = BY_ID[id]; if (!p) return;
  $$(`.card[data-id="${CSS.escape(id)}"], .stack-card[data-id="${CSS.escape(id)}"]`).forEach(card => {
    const old = $('.add', card); if (!old) return;
    old.outerHTML = addBtn(p, cart.get(id) || 0, p.stock <= 0);
  });
}

/* ---------- hero slider ---------- */
(function () {
  const H = DATA.hero, secs = H.seconds || 6, root = $('#hero');
  root.style.setProperty('--secs', secs + 's');
  if (H.line) { root.style.setProperty('--bm-gap', H.line.w); root.style.setProperty('--bm-line', H.line.c); }
  const R = ROT || 0, nb = H.backdrops.length;
  // backdrop photo: the Araku Tribe slide always keeps photo 1 (it has the cup/bag column built in);
  // the product slides take the other photos, shifted one step on every refresh
  const bg = i => (i === 0 || nb < 2 ? H.backdrops[0] : H.backdrops[1 + (i - 1 + R) % (nb - 1)]) || DATA.images.hero;
  let slides = [{ brand: true }];
  DATA.categories.forEach(c => { const p = BY_ID[(DATA.featured || {})[c.key]]; if (p) slides.push({ c, p }); });
  // Araku Tribe slide stays first; the product slides after it start from a different range every refresh
  if (ROT !== null && slides.length > 2) slides = [slides[0], ...rotate(slides.slice(1), ROT)];

  /* PRODUCT COLLAGE backgrounds: Araku Tribe slide = every product; a range slide = only that range,
     under a mask tinted in the range colour. "photo" mode (admin settings) keeps the old photos. */
  const HB = DATA.heroBg || { mode: 'collage', dim: 72, move: true };
  const TINT = { bag: '#8A5A3F', cup: '#C9501A', mug: '#2F6B4F', tshirt: '#2A1150' };
  // random but repeatable within one page load: a new mix on every refresh
  const rng = seed => () => { seed = (seed + 0x6D2B79F5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  const shuffle = (arr, rnd) => { const x = arr.slice(); for (let k = x.length - 1; k > 0; k--) { const j = Math.floor(rnd() * (k + 1)); [x[k], x[j]] = [x[j], x[k]]; } return x; };
  const SEED = (Date.now() ^ (Math.random() * 1e9)) | 0;
  // the empty collage frame for each slide; it is filled (and faded in) once its photos have loaded
  const collage = (cat, i) => {
    const a = Math.max(0, Math.min(95, HB.dim)) / 100;
    return `<div class="hs-collage${cat ? ' one' : ' all'}${HB.fill ? ' fill' : ''}${HB.move ? ' move' : ''}" data-cat="${esc(cat)}" data-i="${i}" aria-hidden="true" style="--hs-tint:${cat ? (TINT[cat] || '#6B3E26') : '#3B2317'};--hs-a:${a}">
      <div class="hs-grid"></div><i class="hs-mask"></i></div>`;
  };
  // tiles: no two neighbours (left or above) are the same photo; the Araku Tribe slide mixes every range
  // how many tiles cover this collage completely: the grid is the slide plus 22% each side across and 30% up/down
  // a few LARGE tiles that exactly fill the showcase area (right side; whole width on phones)
  function gridSize(box, cat) {
    const small = innerWidth <= 860;
    const W = (box.clientWidth || root.clientWidth) * (small || HB.fill ? 1 : (cat ? 0.6 : 0.58));
    const H = box.clientHeight || root.clientHeight;
    const rows = small ? 3 : (cat ? 3 : 3);
    const cols = Math.max(2, Math.round(W / (H / rows)));             // roughly square tiles
    return { cols, rows, n: cols * rows };
  }
  function tilesFor(cat, i, good, cols, n) {
    const rnd = rng(SEED + i * 7919);
    const list = cat ? good.filter(g => g.cat === cat) : good;
    if (!list.length) return [];
    const out = []; let bag = [];
    for (let k = 0; k < n; k++) {
      if (!bag.length) bag = shuffle(list, rnd);                   // a fresh random order each round
      const left = k % cols ? out[k - 1] : null, up = out[k - cols] || null;
      // best: a different photo AND (on the Araku Tribe slide) a different range than left and above
      let pick = bag.findIndex(g => g.src !== left?.src && g.src !== up?.src && (cat || (g.cat !== left?.cat && g.cat !== up?.cat)));
      if (pick < 0) pick = bag.findIndex(g => g.src !== left?.src && g.src !== up?.src);
      if (pick < 0) pick = 0;
      out.push(bag.splice(pick, 1)[0]);
    }
    return out.map(g => g.src);
  }
  // load every product photo once; only photos that really load are used (no blank or broken tiles)
  function loadPhotos() {
    const seen = new Set(), list = [];
    DATA.products.filter(p => p.img && p.stock > 0).forEach(p => { if (!seen.has(p.img)) { seen.add(p.img); list.push(p); } });
    return Promise.all(list.map(p => new Promise(res => {
      const im = new Image(); let done = false;
      const finish = ok => { if (!done) { done = true; res(ok ? { src: p.img, cat: p.cat } : null); } };
      im.onload = () => finish(im.naturalWidth > 0); im.onerror = () => finish(false);
      setTimeout(() => finish(im.complete && im.naturalWidth > 0), 6000);       // very slow photo: leave it out
      im.src = p.img;
    }))).then(r => r.filter(Boolean));
  }
  let GOOD = [];
  function paint(box) {
    const { cols, rows, n } = gridSize(box, box.dataset.cat);
    const tiles = tilesFor(box.dataset.cat, +box.dataset.i, GOOD, cols, n);
    if (!tiles.length) { box.remove(); return; }                    // no working photo for this range: keep the slide's normal photo
    const grid = box.querySelector('.hs-grid');
    grid.style.setProperty('--cols', cols); grid.style.setProperty('--rows', rows);
    grid.innerHTML = tiles.map(src => `<span><img src="${esc(src)}" alt="" decoding="async"></span>`).join('');
    requestAnimationFrame(() => box.classList.add('ready'));
  }
  function fillCollages() {
    loadPhotos().then(good => { GOOD = good; $$('.hs-collage').forEach(paint); });
    let t; addEventListener('resize', () => { clearTimeout(t); t = setTimeout(() => GOOD.length && $$('.hs-collage').forEach(paint), 300); });   // new size: refill
  }
  const useCollage = HB.mode !== 'photo';

  $('#slides').innerHTML = slides.map((s, i) => {
    const photo = `<img class="slide-bg" src="${esc(bg(i))}" alt="" ${i ? 'loading="lazy"' : 'fetchpriority="high"'}>`;
    const media = useCollage ? photo + collage(s.brand ? '' : s.c.key, i) : (s.brand && H.video)
      ? `<video class="slide-bg" autoplay muted loop playsinline preload="metadata" poster="${esc(bg(0))}"><source src="${esc(H.video)}" type="video/mp4"></video>`
      : `<img class="slide-bg" src="${esc(bg(i))}" alt="" ${i ? 'loading="lazy"' : 'fetchpriority="high"'}>`;
    const copy = s.brand
      ? `<div class="slide-copy">
           <p class="kicker"><svg class="ico"><use href="#i-pin"/></svg>Araku Valley, Andhra Pradesh</p>
           <h1>Araku Valley premium coffee, and everything you drink it from.</h1>
           <p class="slide-text">Hand-picked Araku Valley premium coffee in eco-friendly wheat bags, plus cups, mugs and t-shirts that carry the Tribe with you.</p>
           <div class="slide-ctas"><a class="btn btn-primary" href="#spotlight">Shop the collection</a><a class="btn btn-ghost-light" href="coffee_about3.php">Explore our story</a></div>
         </div>`
      : `<div class="slide-copy">
           <p class="kicker">${esc(s.c.kicker || s.c.label)}</p>
           <h2>${esc(s.p.name)}</h2>
           <p class="slide-text">${esc(s.c.blurb)}</p>
           <p class="slide-price"><strong>${money(effPrice(s.p))}</strong>${s.p.offer && s.p.offer.type === 'percent' ? `<s>${money(s.p.price)}</s> ` : ''}per unit, min. ${MIN} units</p>
           <div class="slide-ctas"><a class="btn btn-primary" href="${esc(s.p.url)}">Shop now</a><a class="btn btn-ghost-light" href="${esc(s.c.page)}">View all ${esc(s.c.label.toLowerCase())}</a></div>
         </div>
         <figure class="slide-figure"><div class="frame"><img src="${esc(s.p.img)}" alt="${esc(s.p.name)}" loading="lazy"></div><figcaption>${esc(s.c.label)}</figcaption></figure>`;
    const attrs = `role="group" aria-roledescription="slide" aria-label="${i + 1} of ${slides.length}" ${i ? 'aria-hidden="true"' : ''}`;
    if (s.brand) {
      // tiles around the text show a different picture on every refresh:
      // your own tile photo (if set), then each in-stock product of that range in turn
      const tileHTML = (list, offset) => (list || []).map((t, k) => {
        const c = CATS[t.cat]; if (!c) return '';
        const ti = k + offset;
        const pics = [...(t.img ? [{ src: t.img, own: true }] : []),
          ...DATA.products.filter(p => p.cat === t.cat && p.stock > 0).map(p => ({ src: p.img, own: false }))];
        const fp = BY_ID[(DATA.featured || {})[t.cat]];
        const pick = (ROT !== null && pics.length) ? pics[(ROT + ti) % pics.length]
          : { src: t.img || (fp && fp.img) || c.cover, own: !!t.img };
        if (!pick.src) return '';
        return `<a class="bm-tile${pick.own ? '' : ' is-product'}" href="${esc(c.page)}" aria-label="Shop ${esc(c.label)}"><img src="${esc(pick.src)}" alt="" fetchpriority="high"${pick.own && t.position ? ` style="object-position:${esc(t.position)}"` : ''}></a>`;
      }).join('');
      const tiles = tileHTML(H.sideTiles, 0), right = tileHTML(H.rightTiles, (H.sideTiles || []).length);
      const look = H.look === 'merged' ? 'merged' : 'clear';
      return `<div class="slide brand-slide look-${look}${i === 0 ? ' active' : ''}${right ? ' has-right' : ''}" ${attrs} style="--split:${Number(H.split) || .72}">
        <div class="bm${tiles || right ? ' has-col' : ''}${right ? ' has-right' : ''}">${tiles ? `<div class="bm-col">${tiles}</div>` : ''}<div class="bm-main">${media}<div class="bm-shade"></div></div>${right ? `<div class="bm-col">${right}</div>` : ''}</div>
        <div class="bm-veil"></div>
        <div class="wrap slide-inner${tiles ? ' bm-inner' : ''}">${copy}</div></div>`;
    }
    return `<div class="slide${i === 0 ? ' active' : ''}" ${attrs}>
      ${media}<div class="shade"></div><div class="wrap slide-inner">${copy}</div></div>`;
  }).join('');
  if (useCollage) fillCollages();                              // product collages fade in once their photos have loaded

  const els = $$('.slide', root);
  root.classList.toggle('has-tiles', !!$('.bm-col', root));
  root.classList.toggle('has-right', !!$('.bm.has-right', root));
  (H.notes || []).forEach(n => console.warn('[Araku] ' + n));
  $$('.bm-tile img', root).forEach(im => im.addEventListener('error', () =>
    console.warn('[Araku] Hero tile image failed to load: ' + im.src)));
  // CLEAR look: end the darkening exactly on the photo's built-in white line, whatever the crop
  (function fitShade() {
    const main = $('.look-clear .bm-main', root), img = main && $('img.slide-bg', main), shade = main && $('.bm-shade', main);
    if (!img || !shade || main.closest('.has-right')) return;   // photo already trimmed: shade covers it all
    const split = Number(H.split) || .72;
    function fit() {
      if (matchMedia('(max-width: 860px)').matches || !img.naturalWidth) { shade.style.right = ''; return; }
      const cw = main.clientWidth, ch = main.clientHeight;
      const w = Math.max(cw, ch * img.naturalWidth / img.naturalHeight);   // object-fit: cover, centred
      const line = (cw - w) / 2 + split * w;
      shade.style.right = Math.max(0, cw - line) + 'px';
    }
    img.complete ? fit() : img.addEventListener('load', fit);
    addEventListener('resize', fit);
  })();
  $('#hsDots').innerHTML = slides.map((s, i) =>
    `<button class="hs-dot" role="tab" aria-selected="${i === 0}" aria-label="Slide ${i + 1}: ${esc(s.brand ? 'Araku Tribe' : s.c.label)}"><i></i>${esc(s.brand ? 'Araku Tribe' : s.c.label)}</button>`).join('');
  const dots = $$('.hs-dot', root);
  if (slides.length < 2) { $('.hs-bar', root).hidden = true; return; }

  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let cur = 0, timer = null, paused = reduce, hover = false, visible = true;

  function go(n) {
    const next = (n + slides.length) % slides.length;
    if (next === cur) return;
    els[cur].classList.remove('active'); els[cur].setAttribute('aria-hidden', 'true');
    els[next].classList.add('active'); els[next].removeAttribute('aria-hidden');
    dots.forEach((d, i) => {           // restart the progress fill on the new dot
      d.setAttribute('aria-selected', i === next);
      const bar = $('i', d); bar.style.animation = 'none'; void bar.offsetWidth; bar.style.animation = '';
    });
    const bi = slides.findIndex(x => x.brand), v = bi >= 0 && $('video', els[bi]); if (v) next === bi ? v.play().catch(() => {}) : v.pause();
    cur = next; schedule();
  }
  function schedule() {
    clearTimeout(timer);
    const running = !paused && !hover && visible;
    root.classList.toggle('paused', !running);
    if (running) timer = setTimeout(() => go(cur + 1), secs * 1000);
  }
  // progress fill resumes from where it paused, so restart the timer for the remaining part
  $('#hsNext').addEventListener('click', () => go(cur + 1));
  $('#hsPrev').addEventListener('click', () => go(cur - 1));
  dots.forEach((d, i) => d.addEventListener('click', () => go(i)));
  const pauseBtn = $('#hsPause');
  function paintPause() {
    pauseBtn.setAttribute('aria-label', paused ? 'Play slideshow' : 'Pause slideshow');
    $('use', pauseBtn).setAttribute('href', paused ? '#i-play' : '#i-pause');
  }
  pauseBtn.addEventListener('click', () => { paused = !paused; paintPause(); schedule(); });
  root.addEventListener('mouseenter', () => { hover = true; schedule(); });
  root.addEventListener('mouseleave', () => { hover = false; schedule(); });
  root.addEventListener('focusin', () => { hover = true; schedule(); });
  root.addEventListener('focusout', () => { hover = false; schedule(); });
  root.addEventListener('keydown', e => { if (e.key === 'ArrowRight') go(cur + 1); if (e.key === 'ArrowLeft') go(cur - 1); });
  let x0 = null;
  root.addEventListener('touchstart', e => { x0 = e.touches[0].clientX; }, { passive: true });
  root.addEventListener('touchend', e => {
    if (x0 === null) return; const dx = e.changedTouches[0].clientX - x0; x0 = null;
    if (Math.abs(dx) > 50) go(cur + (dx < 0 ? 1 : -1));
  });
  new IntersectionObserver(([en]) => { visible = en.isIntersecting; schedule(); }, { threshold: .25 }).observe(root);
  document.addEventListener('visibilitychange', () => { visible = !document.hidden; schedule(); });
  paintPause(); schedule();
})();

/* ---------- spotlight stack: one product per category ---------- */
(function () {
  const cats = ROT !== null ? rotate(DATA.categories, ROT) : DATA.categories;   // a different range on top each refresh
  const cards = cats.map((c, i) => {
    const p = BY_ID[(DATA.featured || {})[c.key]]; if (!p) return '';
    const out = p.stock <= 0, low = !out && p.stock < DATA.lowStockAt;
    return `<article class="stack-card theme-${esc(c.key)}" data-id="${esc(p.id)}" style="--i:${i}">
      <div class="sc-media">
        <a href="${esc(p.url)}" tabindex="-1" data-qv="${esc(p.id)}"><img src="${esc(p.img)}" alt="${esc(p.name)}" loading="lazy"></a>
        <div class="badges">${p.isBest ? '<span class="badge badge-best">Best seller</span>' : p.isNew ? '<span class="badge badge-new">New</span>' : ''}</div>
      </div>
      <div class="sc-info">
        <p class="sc-cat">${esc(c.label)}<span>${c.count} ${c.count === 1 ? 'product' : 'products'}</span></p>
        <h3><a href="${esc(p.url)}" style="text-decoration:none" data-qv="${esc(p.id)}">${esc(p.name)}</a></h3>
        <p class="sc-id">ID: ${esc(p.id)}</p>
        <p class="sc-blurb">${esc(c.blurb)}</p>
        <ul class="sc-perks">${(c.perks || []).map(x => `<li>${esc(x)}</li>`).join('')}</ul>
        <dl class="sc-facts">
          <div><dt>Min. order</dt><dd>${MIN} units</dd></div>
          <div><dt>Available</dt><dd>${out ? 'Sold out' : p.stock.toLocaleString('en-IN') + ' units'}</dd></div>
          <div><dt>In this range</dt><dd>${c.count} ${c.count === 1 ? 'product' : 'products'}</dd></div>
        </dl>
        ${(() => {
          const more = DATA.products.filter(x => x.cat === c.key && x.id !== p.id && x.stock > 0).slice(0, 4);
          return more.length ? `<div class="sc-more-row"><p>More in this range</p><div class="sc-thumbs">${more.map(m =>
            `<button type="button" class="sc-thumb" data-quick="${esc(m.id)}" aria-label="Quick view ${esc(m.name)}"><img src="${esc(m.img)}" alt="" loading="lazy"><span>${esc(m.name)}</span><small>${money(effPrice(m))}</small></button>`).join('')}</div></div>` : '';
        })()}
        <div class="sc-buy">${priceHTML(p)}
          <span class="sc-stock">${out ? 'Sold out' : low ? `Only ${p.stock} units left` : `In stock, min. ${MIN} units`}</span></div>
        <div class="sc-ctas">${addBtn(p, cart.get(p.id) || 0, out)}<button class="sc-quick" data-quick="${esc(p.id)}">Quick view</button></div>
        <a class="sc-more" href="${esc(c.page)}">View all ${c.count} ${esc(c.label.toLowerCase())}</a>
      </div>
    </article>`;
  }).join('');
  $('#stack').innerHTML = cards;
  const els = $$('.stack-card');
  if (!els.length) { $('#spotlight').hidden = true; return; }

  // as the next card slides over, gently shrink and dim the one underneath
  const mq = matchMedia('(min-width: 861px) and (prefers-reduced-motion: no-preference)');
  let ticking = false;
  function update() {
    ticking = false;
    els.forEach((el, i) => {
      const next = els[i + 1];
      if (!mq.matches || !next) { el.style.transform = ''; el.style.filter = ''; return; }
      const a = el.getBoundingClientRect(), b = next.getBoundingClientRect();
      const t = Math.min(1, Math.max(0, (a.bottom - b.top) / a.height));
      el.style.transform = `scale(${1 - t * 0.06})`;
      el.style.filter = `brightness(${1 - t * 0.25})`;
    });
  }
  addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
  addEventListener('resize', update); update();
})();

/* ---------- merch rows (rails) ---------- */
function setupRail(id) {
  const rail = $('#' + id), nav = $(`[data-rail="${id}"]`);
  if (!rail || !nav) return;
  nav.innerHTML = `<button type="button" aria-label="Scroll left">${icon('left')}</button><button type="button" aria-label="Scroll right">${icon('right')}</button>`;
  const [prev, next] = $$('button', nav);
  const step = () => rail.clientWidth * 0.9;
  prev.addEventListener('click', () => rail.scrollBy({ left: -step() }));
  next.addEventListener('click', () => rail.scrollBy({ left: step() }));
  const paint = () => {
    prev.disabled = rail.scrollLeft < 4;
    next.disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 4;
    nav.hidden = rail.scrollWidth <= rail.clientWidth + 4;
  };
  rail.addEventListener('scroll', paint, { passive: true }); addEventListener('resize', paint); paint();
}
const newItems = rotate(DATA.products.filter(p => p.isNew).slice(0, 10), ROT || 0);   // starts on a different product each refresh
if (newItems.length) { $('#newRail').innerHTML = newItems.map(p => cardHTML(p)).join(''); setupRail('newRail'); }
else $('#newSection').hidden = true;
const bestItems = (DATA.bestIds || []).map(id => BY_ID[id]).filter(Boolean);
if (bestItems.length) {
  $('#bestSection').hidden = false;
  $('#bestRail').innerHTML = bestItems.map((p, i) => cardHTML(p, { rank: i + 1 })).join('');
  setupRail('bestRail');
}
const offerItems = DATA.products.filter(p => p.offer && p.stock > 0);
if (offerItems.length) {
  $('#offers').hidden = false;
  if (DATA.offersPreview) $('#offerNote').innerHTML = `<p class="preview-note"><strong>Design preview</strong><span>These are sample offers shown only on localhost so you can see this section. They aren't real and won't appear on your live site. Add real offers in <code>$OFFERS</code> at the top of index.php.</span></p>`;
  $('#offerRail').innerHTML = rotate(offerItems, ROT || 0).map(p => cardHTML(p)).join('');   // starts on a different deal each refresh
  setupRail('offerRail');
}
/* ---------- catalogue: filter / sort / search ---------- */
const state = { cat: 'all', sort: 'rec', inStock: false, offer: false, combo: false, band: '', q: '', limit: 12 };
const SYN = { coffee:'bag', powder:'bag', beans:'bag', bean:'bag', bag:'bag', bags:'bag',
  cup:'cup', cups:'cup', mug:'mug', mugs:'mug', tshirt:'tshirt', 'tshirts':'tshirt', 't-shirt':'tshirt', tshirt:'tshirt', tshirt:'tshirt', shirt:'tshirt', shirts:'tshirt' };
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

function filtered() {
  let list = DATA.products.slice();
  if (state.cat !== 'all') list = list.filter(p => p.cat === state.cat);
  if (state.inStock) list = list.filter(p => p.stock > 0);
  if (state.offer) list = list.filter(p => p.offer);
  if (state.combo) list = list.filter(p => COMBO_IDS.has(p.id));
  if (state.band === 'low') list = list.filter(p => effPrice(p) < PRICE_SPLIT);
  if (state.band === 'high') list = list.filter(p => effPrice(p) >= PRICE_SPLIT);
  if (state.q) list = list.map(p => [p, matchScore(p, state.q)]).filter(x => x[1] > 0).sort((a, b) => b[1] - a[1]).map(x => x[0]);
  const s = state.sort;
  if (s === 'price-asc') list.sort((a, b) => effPrice(a) - effPrice(b));
  else if (s === 'price-desc') list.sort((a, b) => effPrice(b) - effPrice(a));
  else if (s === 'stock') list.sort((a, b) => b.stock - a.stock);
  else if (s === 'name') list.sort((a, b) => a.name.localeCompare(b.name));
  else if (s === 'newest') list.sort((a, b) => (b.isNew - a.isNew) || a.order - b.order);
  else if (!state.q) list.sort((a, b) => ((b.stock > 0) - (a.stock > 0)) || (b.isBest - a.isBest) || a.order - b.order);
  return list;
}
const COMBO_IDS = new Set((DATA.combos || []).flatMap(c => c.ids));
const PRICE_SPLIT = (() => {   // one split point from your real prices, rounded to a friendly number
  const ps = DATA.products.map(effPrice).sort((a, b) => a - b);
  if (ps.length < 2) return Infinity;
  const mid = ps[Math.floor(ps.length / 2)], mag = Math.pow(10, Math.max(0, Math.floor(Math.log10(mid)) - 1));
  return Math.max(mag, Math.round(mid / mag) * mag);
})();
const roundMoney = v => DATA.currency.symbol + (DATA.maskPrices ? '####' : Math.round(v).toLocaleString('en-IN'));
function renderChips() {
  const chips = [
    { key: 'inStock', label: 'In stock' },
    DATA.products.some(p => p.offer) ? { key: 'offer', label: 'On offer' } : null,
    COMBO_IDS.size ? { key: 'combo', label: 'In a combo' } : null,
    isFinite(PRICE_SPLIT) ? { key: 'band:low', label: `Under ${roundMoney(PRICE_SPLIT)}` } : null,
    isFinite(PRICE_SPLIT) ? { key: 'band:high', label: `${roundMoney(PRICE_SPLIT)} and above` } : null,
  ].filter(Boolean);
  $('#chips').innerHTML = chips.map(c => {
    const on = c.key.startsWith('band:') ? state.band === c.key.slice(5) : !!state[c.key];
    return `<button type="button" class="chip" data-chip="${c.key}" aria-pressed="${on}">${esc(c.label)}</button>`;
  }).join('');
}
$('#chips').addEventListener('click', e => {
  const b = e.target.closest('[data-chip]'); if (!b) return;
  const k = b.dataset.chip;
  if (k.startsWith('band:')) state.band = state.band === k.slice(5) ? '' : k.slice(5); else state[k] = !state[k];
  state.limit = 12; renderChips(); renderCatalog();
});
$$('[data-chip-jump]').forEach(b => b.addEventListener('click', () => {
  state[b.dataset.chipJump] = true; state.cat = 'all'; renderTabs(); renderChips(); renderCatalog(); $('#shop').scrollIntoView();
}));
function renderTabs() {
  const counts = DATA.categories.map(c => ({ key: c.key, label: c.label, n: c.count }));
  $('#tabs').innerHTML = [{ key: 'all', label: 'All', n: DATA.products.length }, ...counts].map(t =>
    `<button class="tab" data-tab="${t.key}" aria-pressed="${state.cat === t.key}">${esc(t.label)}<small>${t.n}</small></button>`).join('');
}
function renderCatalog() {
  const list = filtered();
  const grid = $('#catalogGrid');
  $('#shopCount').textContent = `${list.length} ${list.length === 1 ? 'product' : 'products'}${state.cat !== 'all' ? ' in ' + catLabel(state.cat) : ''}`;
  const aq = $('#activeQuery');
  aq.hidden = !state.q;
  $('span', aq).textContent = state.q ? `Results for “${state.q}”` : '';
  if (!list.length) {
    const gift = state.q && isGiftQuery(state.q);
    grid.innerHTML = `<div class="empty"><h3>${gift ? 'Looking for a gift?' : 'No products match that yet'}</h3>
      <p>${gift ? 'Our premium coffee combos make ready-to-give sets.' : 'Try a different word, or browse a category below.'}</p>
      <p>${gift ? '<a class="btn btn-primary" href="#combos">See gifts &amp; combos</a>' :
        DATA.categories.map(c => `<a class="link" href="${esc(c.page)}" style="margin:0 10px">${esc(c.label)}</a>`).join('')}</p></div>`;
    $('#moreBtn').hidden = true;
    return;
  }
  grid.innerHTML = list.slice(0, state.limit).map(cardHTML).join('');
  $('#moreBtn').hidden = list.length <= state.limit;
}
$('#tabs').addEventListener('click', e => {
  const b = e.target.closest('[data-tab]'); if (!b) return;
  state.cat = b.dataset.tab; state.limit = 12; renderTabs(); renderCatalog();
});
$('#sort').addEventListener('change', e => { state.sort = e.target.value; renderCatalog(); });
$('#moreBtn').addEventListener('click', () => { state.limit += 12; renderCatalog(); });
$('#clearQuery').addEventListener('click', () => { state.q = ''; $('#q').value = ''; renderCatalog(); });
$$('[data-sort-jump]').forEach(b => b.addEventListener('click', () => {
  state.sort = b.dataset.sortJump; $('#sort').value = state.sort; state.cat = 'all'; renderTabs(); renderCatalog();
  $('#shop').scrollIntoView();
}));

/* ---------- align each card's text with its Add-to-cart icon (button stays untouched) ---------- */
function alignCardText() {
  document.querySelectorAll('.card').forEach(card => {
    const btn = card.querySelector('.card-body > .add'), info = card.querySelector('.card-info'), body = card.querySelector('.card-body');
    if (!btn || !info || !body || !btn.firstChild || !btn.offsetWidth) return;
    const r = document.createRange(); r.selectNodeContents(btn);
    const t = r.getBoundingClientRect(), bs = getComputedStyle(btn, '::before');
    const iconW = parseFloat(bs.width) || 17, gap = parseFloat(getComputedStyle(btn).columnGap) || 8;
    const iconLeft = t.left - gap - iconW;                       // where the bag icon starts
    const bodyLeft = body.getBoundingClientRect().left + parseFloat(getComputedStyle(body).paddingLeft);
    info.classList.add('aligned');
    info.style.marginLeft = Math.max(0, Math.round((iconLeft - bodyLeft) * 10) / 10) + 'px';
  });
}
let alignQueued = false;
const queueAlign = () => { if (alignQueued) return; alignQueued = true; requestAnimationFrame(() => { alignQueued = false; alignCardText(); }); };
new MutationObserver(queueAlign).observe(document.body, { childList: true, subtree: true, characterData: true });
addEventListener('resize', queueAlign); addEventListener('load', queueAlign);
if (document.fonts && document.fonts.ready) document.fonts.ready.then(queueAlign);
queueAlign();

/* ---------- combos ---------- */
const comboName = (a, b) => {
  const kind = { mug: 'Mug', cup: 'Cup', tshirt: 'T-Shirt' }[b.cat] || 'cup';
  return `Coffee Bag + Coffee ${kind}`;
};
$('#comboGrid').innerHTML = (DATA.combos || []).map((c, i) => {
  const [a, b] = c.ids.map(id => BY_ID[id]);
  if (!a || !b) return '';
  const out = a.stock <= 0 || b.stock <= 0, sum = effPrice(a) + effPrice(b), d = DATA.comboDiscount || 0;
  const price = d > 0
    ? `<p class="price"><span class="deal">${money(sum * (1 - d / 100))}</span><s>${money(sum)}</s></p>`
    : `<p class="price">${money(sum)}</p>`;
  return `<article class="combo">
    <div class="combo-imgs"><div><img src="${esc(a.img)}" alt="${esc(a.name)}" loading="lazy"></div><div><img src="${esc(b.img)}" alt="${esc(b.name)}" loading="lazy"></div><span class="combo-plus" aria-hidden="true">+</span></div>
    ${d > 0 ? `<div class="badges"><span class="badge badge-deal">Save ${d}%</span></div>` : ''}
    <div class="card-info">
      <h3>${esc(comboName(a, b))}</h3>
      ${price}
    </div>
    <button class="add" data-combo="${i}" ${out ? 'disabled' : ''}>${out ? 'Currently unavailable' : 'Add set to cart'}</button>
  </article>`;
}).join('') || '<p class="lede">Combos appear here once coffee bags and drinkware are in stock.</p>';

/* ---------- AI agent: links, welcome code, chat preview ---------- */
(function agentDemo() {
  const box = $('#demoMsgs'); if (!box) return;
  const cupKey = CATS.cup ? 'cup' : (DATA.categories[0] || {}).key;
  const fp = BY_ID[(DATA.featured || {})[cupKey]];
  const qty = MIN * 2;
  const msgs = [
    ['bot', `Hi, I'm <strong>Araku AI Agent</strong>. What would you like today?`],
    ['bot plain', `<div class="bub-chips"><span>Shop for products</span><span>Customer support</span></div>`],
    ['me', esc(fp ? catLabel(fp.cat) : 'Coffee cups')],
    fp ? ['bot', `<div class="bub-prod"><img src="${esc(fp.img)}" alt=""><div><strong>${esc(fp.name)}</strong><small>${money(effPrice(fp))} per unit</small></div></div>`] : null,
    ['me', `${qty} units`],
    ['bot', `Added ${qty} units to your cart. Ready to check out?<div class="bub-chips" style="margin-top:8px"><span class="go">Proceed to buy</span><span>Keep shopping</span></div>`],
  ].filter(Boolean);
  box.innerHTML = msgs.map(([who, html]) => `<div class="bub ${who}">${html}</div>`).join('');
  const bubs = $$('.bub', box);
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce) { bubs.forEach(b => b.classList.add('show')); return; }
  new IntersectionObserver(([en], obs) => {
    if (!en.isIntersecting) return; obs.disconnect();
    bubs.forEach((b, i) => setTimeout(() => b.classList.add('show'), 350 + i * 650));
  }, { threshold: .4 }).observe(box);
})();

/* ---------- stories, reviews, instagram ---------- */
$('#quotes').innerHTML = DATA.testimonials.map(t =>
  `<figure class="quote"><blockquote>${esc(t.text)}</blockquote><figcaption><strong>${esc(t.name)}</strong>${esc(t.place)}</figcaption></figure>`).join('');
$('#posts').innerHTML = DATA.posts.map(p =>
  `<a class="post" href="${esc(p.url)}"><div class="media"><img src="${esc(p.img)}" alt="" loading="lazy"></div><time>${esc(p.date)}</time><h3>${esc(p.title)}</h3></a>`).join('');
$('#insta').innerHTML = DATA.images.insta.map(src =>
  `<a href="https://www.instagram.com/araku_tribe" target="_blank" rel="noopener" aria-label="Araku Tribe on Instagram"><img src="${esc(src)}" alt="" loading="lazy"></a>`).join('');

/* ---------- toast + cart drawer: provided by header3.php (window.A3) ---------- */
const toast = msg => A3.toast(msg);
const openDrawer = id => A3.openDrawer(id);
const closeDrawers = () => A3.closeDrawers();

/* ---------- cart ---------- */

async function addToCart(id, qty) {
  const body = 'action=add&product_id=' + encodeURIComponent(id) + '&quantity=' + qty + '&redirect_to=&ajax=1';
  const res = await fetch('add_to_cart.php', { method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
  const data = await res.json();
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
  const d = await fetch('cart3.php', { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ combo_add: ids.join(',') }) }).then(r => r.json());
  if (d.status !== 'success') throw new Error(d.message || 'Could not add this combo.');
}
async function refreshCart() { const n = lastNotice; lastNotice = null; await A3.refreshCart(n); }
async function handleAdd(btn, id, qty) {
  btn.disabled = true;
  try { notify(addedMessage(await addToCart(id, qty))); await refreshCart(); openDrawer('cartDrawer'); }
  catch (e) { toast(e.message); btn.disabled = false; }
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
    const data = await res.json();
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
  try { notify(addedMessage(await addToCart(qvItem.id, qvQty))); qv.close(); await refreshCart(); openDrawer('cartDrawer'); }
  catch (e) { toast(e.message); } finally { btn.disabled = false; }
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

/* ---------- FRESH ON EVERY REFRESH: ribbon text + section order ---------- */
(function () {
  const offers = DATA.products.filter(p => p.offer && p.stock > 0);
  const pct = offers.filter(p => p.offer.type === 'percent').map(p => p.offer.value);
  const r = ROT || 0;
  const hotLines = [
    offers.length ? `<b>${offers.length} products</b> on offer${pct.length ? `, up to <b>${Math.max(...pct)}% off</b>` : ''}. Every deal on one page.` : 'Our best prices on premium coffee, cups, mugs and t-shirts, all on one page.',
    offers.some(p => p.offer.type === 'bogo') ? '<b>Buy 1 Get 1</b> on selected products. See every deal first, then the whole range.' : 'See every deal first, then the whole range in one catalogue.',
    'Premium coffee, cups, mugs and t-shirts at our <b>lowest prices</b>. Grab them while they last.',
  ];
  const fp = BY_ID[(DATA.featured || {})[(DATA.categories[r % Math.max(1, DATA.categories.length)] || {}).key]];
  const botLines = [
    'Skip the browsing. Tell the agent what you need and <b>check out in one chat</b>.',
    fp ? `Want <b>${esc(fp.name)}</b>? The agent can add ${MIN} units and take you to payment.` : 'The agent finds products, sets quantities and takes you to payment.',
    'Track, cancel or re-order in seconds. <b>No forms, no waiting.</b>',
  ];
  $('#prHotText').innerHTML = hotLines[r % hotLines.length];
  $('#prBotText').innerHTML = botLines[r % botLines.length];
  // fixed order under the hero: Hot Deals + AI Agent banner, how the AI Agent works,
  // "One from every range", Best sellers, then Offers and New arrivals
  let prev = $('#agent');
  $('#promoRibbon').after(prev);
  ['spotlight', 'bestSection', 'offers', 'newSection'].forEach(id => {
    const el = document.getElementById(id); if (!el) return;
    prev.after(el); prev = el;
  });
  if (ROT !== null) $('#promoRibbon').classList.toggle('swap', r % 2 === 1);   // banner tiles swap sides each refresh

})();

/* ---------- header search on the home page: show results in "Shop the range" ---------- */
window.onSiteSearch = q => {
  state.q = q; state.cat = 'all'; state.limit = 12;
  renderTabs(); renderChips(); renderCatalog(); $('#shop').scrollIntoView();
};

/* ---------- boot ---------- */
if (DATA.query && DATA.query.q) { state.q = String(DATA.query.q); $('#q').value = state.q; }
if (DATA.query && CATS[DATA.query.cat]) state.cat = DATA.query.cat;
renderTabs(); renderChips(); renderCatalog();
})();
</script>

</div>
<!-- SPA Container End -->

<?php 
if (!$isAjax) {
    include __DIR__ . '/footer3.php'; 
}
?>