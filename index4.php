<?php
/* =====================================================================
   ARAKU TRIBE - index4.php  (redesign test page)
   ---------------------------------------------------------------------
   Uses header3.php (top) and footer3.php (bottom); everything else for
   the home page (its CSS, PHP and JavaScript) is in this one file.
   Backend files used: db1.php, currency_helper.php, add_to_cart.php,
   cart_status.php, wishlist_toggle.php.
   ===================================================================== */
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
$HOT_DEALS_URL = 'hotdeals3.php';

// FRESH HOME PAGE ON EVERY REFRESH: each visit to this page moves one step through a rotation,
// so the opening hero product, the featured product of each range, the order of the spotlight
// cards and the product section shown first all change. Our products always come first,
// then the AI Agent. false = the page always looks the same.
$ROTATE_ON_REFRESH = true;

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
];
$SLIDE_SECONDS  = 4;   // index2.php moves to the next slide every 4 seconds
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
// COLLAGE ONLY (this is index4.php): every slide shows nothing but the product-photo collage -
// no headline, text, buttons, side tiles, photo or "Scroll" hint on top of it. The slides MOVE like the
// slider in index2.php: they slide sideways (not fade), one slide every $SLIDE_SECONDS seconds, pausing
// while the pointer is over the slider or a finger is on it; round white arrows at the sides and small dots
// at the bottom. false = same slider as index4.php.
$HERO_COLLAGE_ONLY = true;
// Share of the hero photo's width taken by the coffee-pouring photo (the rest is its built-in cup/bag column).
// In 'clear' mode the darkening stops here. Nudge it (e.g. 0.70 or 0.74) if the edge misses the white line.
$HERO_PHOTO_SPLIT = 0.72;
// Lines between the two left tiles and the photo: same white line as the cup/bag column built into the photo.
// Nudge the width (e.g. '4px' or '8px') until it matches the line on the right.
$HERO_TILE_LINE_WIDTH = '6px';
$HERO_TILE_LINE_COLOR = '#FFFFFF';
$LOGO_IMAGE = ''; // e.g. 'img/logo/logo_dark.png' - leave empty to use the text wordmark (your current logo is made for a dark header)

$TESTIMONIALS = [
    ['name' => 'Ramesh Varma',  'place' => 'Bean & Brew, Vizag',          'text' => 'Since we switched to Araku beans our customers keep talking about the aroma and the smooth taste. The branded cups lifted our takeaway sales too.'],
    ['name' => 'Sneha Rao',     'place' => 'The Koffee House, Vizag',     'text' => 'The organic beans give us a flavour customers can\'t get elsewhere, and the cups give our takeaway coffee a premium feel.'],
    ['name' => 'Vikram Shetty', 'place' => 'Coastal Brews, Vizag',        'text' => 'We wanted a brand that matches our sustainability values. Ethical sourcing, great beans, and cups people love to photograph.'],
];
$POSTS = [
    ['img' => 'img/blog/inner_b1.png', 'date' => '12 Jan 2026', 'title' => 'How Araku coffee is still grown the traditional way',  'url' => 'coffee_about3.php'],
    ['img' => 'img/blog/inner_b2.png', 'date' => '25 Jan 2026', 'title' => 'Farming in harmony with the land',                     'url' => 'coffee_about3.php'],
    ['img' => 'img/blog/inner_b4.png', 'date' => '2 Feb 2026',  'title' => 'From the heart of the Araku Valley to your cup',       'url' => 'coffee_about3.php'],
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

        $meta = $CATEGORIES[$cat] ?? ['label' => ucwords(str_replace('_', ' ', $cat)), 'page' => 'index4.php#shop'];
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
    $bs = $conn->query("SELECT product_id, SUM(quantity) AS sold FROM `$t` GROUP BY product_id ORDER BY sold DESC LIMIT 8");
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
$pairs  = [['mug', 'Coffee + mug'], ['cup', 'Coffee + cup'], ['tshirt', 'Coffee + tee']];
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
$PAGE_TITLE       = 'Araku Tribe - Araku Valley coffee, cups, mugs and tees';
$PAGE_DESCRIPTION = 'Shop Araku Valley coffee bags, branded coffee cups, mugs and T-shirts from Araku Tribe.';
$ACTIVE_NAV       = '';       // home page: no tab, keeps the original header look
$H3_BOT_FLY       = true;     // the AI Agent robot flies around on every visit to the home page
include __DIR__ . '/header3.php';
?>
<style>
/* ============ index4.php styles (home page sections, product cards, quick view) ============ */
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
/* TEXT-ONLY MASK: no panel. A dark backing hugs each LINE of words (like subtitles) with a soft feathered
   edge, so only the letters are backed and every product photo around the text stays fully visible. */
.slide:has(.hs-collage.fill.ready) .slide-copy{position:relative;text-shadow:none}
.slide:has(.hs-collage.fill.ready) .slide-copy h1,
.slide:has(.hs-collage.fill.ready) .slide-copy h2{line-height:1.22}
.slide:has(.hs-collage.fill.ready) .slide-copy .slide-text,
.slide:has(.hs-collage.fill.ready) .slide-copy .slide-price{line-height:1.75}
.slide:has(.hs-collage.fill.ready) .slide-copy .hl{background:rgba(34,20,12,.86);padding:.05em .34em;border-radius:.2em;
  -webkit-box-decoration-break:clone;box-decoration-break:clone;box-shadow:0 0 16px 3px rgba(34,20,12,.55)}
.slide:has(.hs-collage.fill.ready) .slide-copy .kicker{background:rgba(34,20,12,.86);backdrop-filter:none;box-shadow:0 0 14px 2px rgba(34,20,12,.5)}
.slide:has(.hs-collage.fill.ready) .slide-copy .btn-ghost-light{background:rgba(34,20,12,.86)!important;color:#fff!important;border-color:rgba(255,247,237,.55)!important}
.slide:has(.hs-collage.fill.ready) .slide-copy::before{display:none}
/* the opening slide has no picture beside its text, so the panel can use the whole column (not the narrow half) */
.brand-slide:has(.hs-collage.fill.ready) .slide-inner{grid-template-columns:1fr}
/* slide controls: with nothing darkening the collage, their small labels and buttons get their own dark backing so they stay readable */
.hero-slider:has(.hs-collage.fill) .hs-dots{flex:0 1 auto;margin-right:auto;background:rgba(42,25,16,.9);border-radius:999px;padding:6px 24px 12px}
.hero-slider:has(.hs-collage.fill) .hs-dot{color:rgba(255,247,237,.8)}
@media (min-width:681px){ .hero-slider:has(.hs-collage.fill) .hs-dot{flex:0 0 auto;white-space:nowrap;min-width:84px;padding-right:6px} .hero-slider:has(.hs-collage.fill) .hs-dots{gap:12px} }
.hero-slider:has(.hs-collage.fill) .hs-dot[aria-selected="true"]{color:#fff}
.hero-slider:has(.hs-collage.fill) .hs-btn{background:rgba(42,25,16,.9);border-color:rgba(255,247,237,.3)}
/* ---------- COLLAGE ONLY + MOVING SLIDER (index4) ---------- */
.slide.collage-only .slide-bg{opacity:0}                      /* backup photo hidden; it only shows if no product photo loads at all */
.slide.collage-only.no-collage .slide-bg{opacity:1}
#hero.collage-only .scroll-cue{display:none}
/* the slides sit side by side in one track that slides left, exactly one slide per step (like index2.php) */
#hero.collage-only #slides{position:absolute;inset:0;display:flex;transition:transform .8s cubic-bezier(.4,0,.2,1);will-change:transform}
#hero.collage-only .slide{position:relative;inset:auto;flex:0 0 100%;height:100%;opacity:1;visibility:visible;transition:none}
@media (prefers-reduced-motion:reduce){ #hero.collage-only #slides{transition:none} }
/* index2-style controls: round white arrows at the sides, small round dots at the bottom, a small pause button */
#hero.collage-only .hs-bar{top:0;bottom:0;pointer-events:none}
#hero.collage-only .hs-bar .wrap{max-width:none;height:100%;padding:0!important;display:block;position:static}
#hero.collage-only .hs-btn{pointer-events:auto;position:absolute;background:rgba(255,255,255,.85);color:#333;border:0;box-shadow:0 2px 8px rgba(0,0,0,.3);backdrop-filter:none}
#hero.collage-only .hs-btn:hover{background:#fff}
#hero.collage-only #hsPrev,#hero.collage-only #hsNext{top:50%;transform:translateY(-50%);width:44px;height:44px}
#hero.collage-only #hsPrev{left:12px}
#hero.collage-only #hsNext{right:12px}
#hero.collage-only #hsPause{right:16px;bottom:12px;width:34px;height:34px}
#hero.collage-only .hs-dots{position:absolute;left:50%;bottom:12px;transform:translateX(-50%);flex:none;display:flex;gap:8px;margin:0;padding:0;background:none;pointer-events:auto}
#hero.collage-only .hs-dot{flex:none;width:9px;height:9px;min-width:0;padding:0;border-radius:50%;background:rgba(255,255,255,.5);font-size:0;color:transparent;transition:background-color .2s,transform .2s}
#hero.collage-only .hs-dot::before,#hero.collage-only .hs-dot i{display:none}
#hero.collage-only .hs-dot[aria-selected="true"]{background:#fff;transform:scale(1.25)}
@media (max-width:680px){ #hero.collage-only #hsPrev,#hero.collage-only #hsNext{width:34px;height:34px} }

/* ---------- PRODUCT TILES (like index2.php): a set of products per slide, shadow on the right and top ---------- */
#hero.collage-only{background:var(--cream,#FFF7ED)}   /* the site's cream, like its cream sections */
#hero.collage-only{height:auto!important;min-height:0!important;max-height:none!important}
#hero.collage-only #slides{position:relative;inset:auto}
#hero.collage-only .slide{height:auto}
.hs-showcase{position:relative;display:grid;grid-template-columns:repeat(4,1fr);gap:24px;width:100%;max-width:1180px;margin:0 auto;
  padding:24px 24px 46px;box-sizing:border-box;opacity:0;transition:opacity .6s ease}
.hs-showcase.ready{opacity:1}
.ht{position:relative;display:block;min-width:0;aspect-ratio:1/1;overflow:hidden;border-radius:14px;background:var(--cream-2,#F3E7DA);cursor:pointer;
  box-shadow:6px -6px 14px rgba(70,40,20,.5),-3px 3px 8px rgba(70,40,20,.28);transition:transform .3s ease}
.ht:hover,.ht:focus-visible{transform:translateY(-10px) scale(1.02);z-index:2;outline:0}
.ht img{width:100%;height:100%;object-fit:cover;display:block;border-radius:14px;transition:transform .35s ease}
.ht:hover img{transform:scale(1.08)}
.ht-cap{position:absolute;left:6px;right:6px;bottom:6px;background:linear-gradient(to top,var(--cream,#FFF7ED) 75%,rgba(255,247,237,0));color:var(--brown-deep,#2A1910);
  padding:10px 10px 8px;border-radius:8px;opacity:0;transform:translateY(8px);transition:opacity .25s ease,transform .25s ease;display:flex;align-items:center;overflow:hidden}
.ht:hover .ht-cap,.ht:focus-within .ht-cap{opacity:1;transform:none}
.ht-line{display:flex;flex-direction:column;gap:2px;width:100%;min-width:0;line-height:1.3}
.ht-name{font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ht-meta{font-size:12px;font-weight:700;white-space:normal;overflow-wrap:anywhere}
.ht-id{font-weight:700}
.ht-price{font-weight:800}
.ht-icons{position:absolute;top:16px;right:16px;z-index:3;display:flex;flex-direction:column;gap:8px;opacity:0;transform:scale(.8);transition:opacity .2s ease,transform .2s ease}
.ht:hover .ht-icons,.ht:focus-within .ht-icons{opacity:1;transform:none}
.ht-wish,.ht-cart{position:relative;width:42px;height:42px;border-radius:50%;border:0;padding:0;background:rgba(255,255,255,.9);color:#666;cursor:pointer;
  display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.15);transition:color .2s ease,background-color .2s ease}
.ht-wish[aria-pressed="true"]{color:#e63946}
.ht-cart:hover{background:#fff;color:var(--brown-deep,#2A1910)}
.ht-cart.added{background:#2F6B4F;color:#fff}
.ht-cart:disabled{opacity:.6;cursor:progress}
.ht-qty{position:absolute;bottom:-4px;right:-4px;background:var(--brown-deep,#2A1910);color:#fff;font-size:10px;font-weight:700;line-height:1;padding:2px 5px;border-radius:10px;min-width:16px;text-align:center}
@media (hover:none){ .ht-icons{opacity:1;transform:none} }      /* touch screens have no hover: keep the heart and cart reachable */
@media (max-width:991px){ .hs-showcase{grid-template-columns:repeat(3,1fr);gap:18px} }
@media (max-width:575px){ .hs-showcase{grid-template-columns:repeat(2,1fr);gap:12px;padding:16px 14px 44px} .ht-wish,.ht-cart{width:36px;height:36px} .ht-icons{top:10px;right:10px} }
.ht-cart.soldout{opacity:.45;cursor:not-allowed}
/* dots need to be dark on the light background */
#hero.collage-only .hs-dot{background:rgba(107,62,38,.3)}
#hero.collage-only .hs-dot[aria-selected="true"]{background:var(--brown,#6B3E26)}
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
.combo h3{font-size:1.35rem;margin:18px 10px 0}
.combo ul{list-style:none;padding:0;margin:8px 10px 14px;font-size:14px;color:var(--muted)}
.combo li{padding:2px 0}
.combo .price{margin:0 10px 16px}
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
.quote blockquote{margin:0 0 22px;font-family:var(--serif);font-size:1.12rem;line-height:1.55;color:var(--brown-deep)}
.quote figcaption{margin-top:auto;font-size:14px;color:var(--muted)}
.quote figcaption strong{display:block;color:var(--ink);font-size:15px}
.posts{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.post{text-decoration:none}
.post .media{aspect-ratio:3/2;border-radius:var(--r-md);overflow:hidden;background:var(--cream)}
.post img{width:100%;height:100%;object-fit:cover;transition:transform .5s}
.post:hover img{transform:scale(1.04)}
.post time{display:block;margin-top:14px;font-size:13.5px;color:var(--muted)}
.post h3{font-size:1.25rem;margin-top:4px;line-height:1.3}
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
        <span><h2>Hot deals</h2><p id="prHotText">Our best prices on coffee, cups, mugs and tees, all on one page.</p><span class="go">Grab the deals <svg class="ico"><use href="#i-right"/></svg></span></span>
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
        <div><p class="eyebrow">Better together</p><h2 id="comboTitle">Gifts &amp; combos</h2><p class="lede">Coffee paired with something to drink it from. Each set adds <span data-minqty></span> of both items.</p></div>
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
        <p class="lede">Araku coffee is grown by the valley's tribal communities using methods passed down for generations. We bring it to you without shortcuts.</p>
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
        <div><h2 id="jTitle">From the Araku journal</h2><p class="lede">Coffee, culture and the valley it comes from.</p></div>
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
        <h2 id="b2bTitle">Run a café? Serve Araku Tribe coffee.</h2>
        <p>We supply premium Araku coffee powder, branded cups and coffee machines to partner shops, with the branding support to match.</p>
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
const DATA = <?= $pageJson ?>;
// slider backgrounds (admin settings > Home slider)
DATA.heroBg = <?= json_encode([
    'mode' => function_exists('h3_set') ? h3_set('hs_bg', 'collage') : 'collage',
    'dim'  => function_exists('h3_set') ? (int)h3_set('hs_dim', '72') : 72,
    'move' => function_exists('h3_set') ? h3_set('hs_move', '1') === '1' : true,
    'fill' => (bool)$HERO_COLLAGE_FILL || (bool)$HERO_COLLAGE_ONLY,
    'only' => (bool)$HERO_COLLAGE_ONLY,
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
      <p class="card-cat">${esc(catLabel(p.cat))}</p>
      <h3 class="card-title"><a href="${esc(p.url)}" data-qv="${esc(p.id)}">${esc(p.name)}</a></h3>
      <p class="card-id">ID: ${esc(p.id)}</p>
      ${priceHTML(p)}
      <p class="moq">Min. order ${MIN} units</p>
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
  $$(`.ht-cart[data-add="${CSS.escape(id)}"]`).forEach(b => {
    const q = cart.get(id) || 0; b.disabled = false; b.classList.toggle('added', q > 0);
    let badge = $('.ht-qty', b); if (q > 0) { if (!badge) { badge = document.createElement('span'); badge.className = 'ht-qty'; b.appendChild(badge); } badge.textContent = q; }
  });
}
// a hero tile opens its range page (like index2.php); the heart and cart buttons inside it do their own thing
document.addEventListener('click', e => { const t = e.target.closest('.ht'); if (t && !e.target.closest('button') && t.dataset.href) location.href = t.dataset.href; });
document.addEventListener('keydown', e => { const t = e.target.closest && e.target.closest('.ht'); if (t && e.target === t && e.key === 'Enter' && t.dataset.href) location.href = t.dataset.href; });

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
      const finish = ok => { if (!done) { done = true; res(ok ? { src: p.img, cat: p.cat, id: p.id } : null); } };
      im.onload = () => finish(im.naturalWidth > 0); im.onerror = () => finish(false);
      setTimeout(() => finish(im.complete && im.naturalWidth > 0), 6000);       // very slow photo: leave it out
      im.src = p.img;
    }))).then(r => r.filter(Boolean));
  }
  let GOOD = [];
  const HT_HEART = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 21s-7.5-4.6-9.6-9.2C1 8.6 2.7 5 6.1 5c2 0 3.3 1 3.9 2.1h.0C10.6 6 11.9 5 13.9 5c3.4 0 5.1 3.6 3.7 6.8C19.5 16.4 12 21 12 21z"/></svg>';
  const HT_CART = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M7 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm10 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM1 2v2h2l3.6 7.6-1.4 2.4A2 2 0 0 0 7 17h12v-2H7.4l1.1-2h7.5a2 2 0 0 0 1.7-1l3.6-6.5A1 1 0 0 0 20.4 4H5.2l-.9-2H1z"/></svg>';
  // which products make up this slide: the opening slide mixes every range, a range slide only that range;
  // a fresh random order on every refresh; products that cannot be bought (stock under the minimum order) go last
  function pickProducts(cat, i, n) {
    const rnd = rng(SEED + i * 7919), seen = new Set(), pool = [];
    GOOD.forEach(g => { const p = BY_ID[g.id]; if (p && !seen.has(p.id)) { seen.add(p.id); pool.push(p); } });
    // products that can be ordered (stock at or above the minimum) come first, then the ones with less stock
    const ordered = arr => shuffle(arr.filter(p => p.stock >= MIN), rnd).concat(shuffle(arr.filter(p => p.stock < MIN), rnd));
    if (cat) {
      const own = ordered(pool.filter(p => p.cat === cat));
      if (own.length >= n) return own.slice(0, n);
      return own.concat(ordered(pool.filter(p => p.cat !== cat))).slice(0, n);   // short range: top up from the other ranges so the slide is still a full set
    }
    const by = {}; pool.forEach(p => (by[p.cat] = by[p.cat] || []).push(p));
    const groups = Object.values(by).map(ordered), out = [];
    for (let k = 0; out.length < n && groups.some(g => g.length); k++) { const g = groups[k % groups.length]; if (g.length) out.push(g.shift()); }
    return out;
  }
  const htTile = p => {
    const q = cart.get(p.id) || 0, page = (CATS[p.cat] || {}).page || p.url;
    return `<div class="ht" role="link" tabindex="0" data-href="${esc(page)}" aria-label="${esc(p.name)}">
      <div class="ht-icons">
        <button type="button" class="ht-wish" data-wish="${esc(p.id)}" aria-pressed="${wish.has(p.id)}" aria-label="Save ${esc(p.name)} to wishlist">${HT_HEART}</button>
        <button type="button" class="ht-cart${q ? ' added' : ''}${p.stock < MIN ? ' soldout' : ''}" data-add="${esc(p.id)}"${p.stock < MIN ? ` disabled title="Fewer than ${MIN} units in stock"` : ''} aria-label="Add ${esc(p.name)} to cart">${HT_CART}${q ? `<span class="ht-qty">${q}</span>` : ''}</button>
      </div>
      <img src="${esc(p.img)}" alt="${esc(p.name)}" decoding="async">
      <span class="ht-cap"><span class="ht-line"><span class="ht-name">${esc(p.name)}</span><span class="ht-meta"><span class="ht-id">ID : ${esc(p.id)}</span> \u2014 <span class="ht-price">${esc(DATA.currency.symbol)}####</span></span></span></span>
    </div>`;
  };
  function paintShowcase(box) {
    const slide = box.closest('.slide');
    const list = pickProducts(box.dataset.cat, +box.dataset.i, 12);               // 12 products per slide, like index2.php
    if (!list.length) { if (slide) slide.classList.add('no-collage'); box.remove(); return; }   // no usable product: keep the slide's photo
    box.innerHTML = list.map(htTile).join('');
    requestAnimationFrame(() => box.classList.add('ready'));
  }
  function paint(box) {
    const { cols, rows, n } = gridSize(box, box.dataset.cat);
    const tiles = tilesFor(box.dataset.cat, +box.dataset.i, GOOD, cols, n);
    if (!tiles.length) { const sl = box.closest('.slide'); if (sl) sl.classList.add('no-collage'); box.remove(); return; }                    // no working photo for this range: keep the slide's normal photo
    const grid = box.querySelector('.hs-grid');
    grid.style.setProperty('--cols', cols); grid.style.setProperty('--rows', rows);
    grid.innerHTML = tiles.map(src => `<span><img src="${esc(src)}" alt="" decoding="async"></span>`).join('');
    requestAnimationFrame(() => box.classList.add('ready'));
  }
  function fillCollages() {
    loadPhotos().then(good => { GOOD = good; $$('.hs-collage').forEach(paint); $$('.hs-showcase').forEach(paintShowcase); });
    let t; addEventListener('resize', () => { clearTimeout(t); t = setTimeout(() => GOOD.length && ($$('.hs-collage').forEach(paint), $$('.hs-showcase').forEach(paintShowcase)), 300); });   // new size: refill
  }
  const useCollage = HB.mode !== 'photo' || HB.only;   // collage-only always uses the collage

  $('#slides').innerHTML = slides.map((s, i) => {
    const photo = `<img class="slide-bg" src="${esc(bg(i))}" alt="" ${i ? 'loading="lazy"' : 'fetchpriority="high"'}>`;
    const media = useCollage ? photo + collage(s.brand ? '' : s.c.key, i) : (s.brand && H.video)
      ? `<video class="slide-bg" autoplay muted loop playsinline preload="metadata" poster="${esc(bg(0))}"><source src="${esc(H.video)}" type="video/mp4"></video>`
      : `<img class="slide-bg" src="${esc(bg(i))}" alt="" ${i ? 'loading="lazy"' : 'fetchpriority="high"'}>`;
    const copy = s.brand
      ? `<div class="slide-copy">
           <p class="kicker"><svg class="ico"><use href="#i-pin"/></svg>Araku Valley, Andhra Pradesh</p>
           <h1><span class="hl">Valley coffee, and everything you drink it from.</span></h1>
           <p class="slide-text"><span class="hl">Hand-picked Araku coffee in eco-friendly wheat bags, plus cups, mugs and tees that carry the Tribe with you.</span></p>
           <div class="slide-ctas"><a class="btn btn-primary" href="#spotlight">Shop the collection</a><a class="btn btn-ghost-light" href="coffee_about3.php">Explore our story</a></div>
         </div>`
      : `<div class="slide-copy">
           <p class="kicker">${esc(s.c.kicker || s.c.label)}</p>
           <h2><span class="hl">${esc(s.p.name)}</span></h2>
           <p class="slide-text"><span class="hl">${esc(s.c.blurb)}</span></p>
           <p class="slide-price"><span class="hl"><strong>${money(effPrice(s.p))}</strong>${s.p.offer && s.p.offer.type === 'percent' ? `<s>${money(s.p.price)}</s> ` : ''}per unit, min. ${MIN} units</span></p>
           <div class="slide-ctas"><a class="btn btn-primary" href="${esc(s.p.url)}">Shop now</a><a class="btn btn-ghost-light" href="${esc(s.c.page)}">View all ${esc(s.c.label.toLowerCase())}</a></div>
         </div>
         <figure class="slide-figure"><div class="frame"><img src="${esc(s.p.img)}" alt="${esc(s.p.name)}" loading="lazy"></div><figcaption>${esc(s.c.label)}</figcaption></figure>`;
    const attrs = `role="group" aria-roledescription="slide" aria-label="${i + 1} of ${slides.length}" ${i ? 'aria-hidden="true"' : ''}`;
    if (useCollage && HB.only) return `<div class="slide collage-only${i === 0 ? ' active' : ''}" ${attrs}>${photo}<div class="hs-showcase" data-cat="${esc(s.brand ? '' : s.c.key)}" data-i="${i}"></div></div>`;   // index4: a set of real product tiles and nothing else
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
  if (useCollage && HB.only) root.classList.add('collage-only');

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
    cur = next;
    if (HB.only) $('#slides').style.transform = 'translateX(' + (-next * 100) + '%)';   // slide sideways, like index2.php
    schedule();
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
  if (DATA.offersPreview) $('#offerNote').innerHTML = `<p class="preview-note"><strong>Design preview</strong><span>These are sample offers shown only on localhost so you can see this section. They aren't real and won't appear on your live site. Add real offers in <code>$OFFERS</code> at the top of index4.php.</span></p>`;
  $('#offerRail').innerHTML = rotate(offerItems, ROT || 0).map(p => cardHTML(p)).join('');   // starts on a different deal each refresh
  setupRail('offerRail');
}
/* ---------- catalogue: filter / sort / search ---------- */
const state = { cat: 'all', sort: 'rec', inStock: false, offer: false, combo: false, band: '', q: '', limit: 12 };
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
      <p>${gift ? 'Our coffee combos make ready-to-give sets.' : 'Try a different word, or browse a category below.'}</p>
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

/* ---------- combos ---------- */
$('#comboGrid').innerHTML = (DATA.combos || []).map((c, i) => {
  const [a, b] = c.ids.map(id => BY_ID[id]);
  if (!a || !b) return '';
  const out = a.stock <= 0 || b.stock <= 0, sum = effPrice(a) + effPrice(b), d = DATA.comboDiscount || 0;
  const price = d > 0
    ? `<p class="price"><span class="deal">${money(sum * (1 - d / 100))}</span><s>${money(sum)}</s><span>per set</span></p>`
    : `<p class="price">${money(sum)} <span>per set</span></p>`;
  return `<article class="combo">
    <div class="combo-imgs"><div><img src="${esc(a.img)}" alt="${esc(a.name)}" loading="lazy"></div><div><img src="${esc(b.img)}" alt="${esc(b.name)}" loading="lazy"></div><span class="combo-plus" aria-hidden="true">+</span></div>
    ${d > 0 ? `<div class="badges"><span class="badge badge-deal">Save ${d}%</span></div>` : ''}
    <h3>${esc(c.title)}</h3>
    <ul><li>${esc(a.name)}</li><li>${esc(b.name)}</li></ul>
    ${price}
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
    offers.length ? `<b>${offers.length} products</b> on offer${pct.length ? `, up to <b>${Math.max(...pct)}% off</b>` : ''}. Every deal on one page.` : 'Our best prices on coffee, cups, mugs and tees, all on one page.',
    offers.some(p => p.offer.type === 'bogo') ? '<b>Buy 1 Get 1</b> on selected products. See every deal first, then the whole range.' : 'See every deal first, then the whole range in one catalogue.',
    'Coffee, cups, mugs and tees at our <b>lowest prices</b>. Grab them while they last.',
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

<?php include __DIR__ . '/footer3.php'; ?>