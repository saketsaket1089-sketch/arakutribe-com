<?php
/* =====================================================================
   ARAKU TRIBE - wishlist3.php
   Same logic as wishlist.php:
     - logged-in users: wishlist table (newest first)
     - guests: IDs saved in this browser (localStorage 'guestWishlist'),
       product details looked up through ?ajax=ids
     - add to cart: add_to_cart.php, 100 units
     - remove: wishlist_toggle.php (logged in) / localStorage (guest)
   Uses header3.php + footer3.php; its CSS and JS are in this file.
   ===================================================================== */
session_start();

$MIN_QTY      = 100;
$MASK_PRICES  = false;   // true = show "####" instead of prices
$LOW_STOCK_AT = 300;
$CAT_PAGES = ['bag' => 'coffee_bag3.php', 'cup' => 'coffee_cup3.php', 'mug' => 'coffee_mug3.php', 'tshirt' => 'coffee_tshirt3.php'];
$CAT_LABEL = ['bag' => 'Coffee Bags', 'cup' => 'Coffee Cups', 'mug' => 'Coffee Mugs', 'tshirt' => 'T-Shirts'];

// one product row -> what the page shows
function wl3_item($row, $rate, $pages, $labels) {
    $cat = strtolower($row['category']);
    return [
        'id'    => (string)$row['product_id'],
        'name'  => (string)($row['product_name'] ?? $row['product_id']),
        'cat'   => $cat,
        'label' => $labels[$cat] ?? ucfirst($cat),
        'img'   => "img/shop/" . $row['category'] . "/" . $row['image'],
        'price' => round((float)$row['price'] * $rate, 2),
        'stock' => (int)$row['quantity'],
        'url'   => ($pages[$cat] ?? 'index.php') . '#product-' . rawurlencode($row['product_id']),
    ];
}

/* ---------------------------------------------------------------------
   AJAX MODE (guests): product details for the IDs saved in the browser
   --------------------------------------------------------------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'ids') {
    header('Content-Type: application/json');
    include 'db1.php';
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }
    require_once 'currency_helper.php';
    $rate = $CURRENCY_INFO[resolve_display_currency($conn)]['rate'];

    $ids = array_values(array_filter(array_map('trim', explode(',', $_GET['ids'] ?? ''))));
    if (empty($ids)) { echo json_encode(['products' => []]); exit; }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id IN ($placeholders)");
    $stmt->bind_param(str_repeat('s', count($ids)), ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $byId = [];
    while ($row = $result->fetch_assoc()) $byId[(string)$row['product_id']] = wl3_item($row, $rate, $CAT_PAGES, $CAT_LABEL);
    $stmt->close();
    $conn->close();

    // keep the order the guest saved them in (newest first)
    $products = [];
    foreach (array_reverse($ids) as $id) if (isset($byId[$id])) $products[] = $byId[$id];
    echo json_encode(['products' => $products]);
    exit;
}

/* ---------------------------------------------------------------------
   NORMAL PAGE
   --------------------------------------------------------------------- */
$loggedInUserId = $_SESSION['user_id'] ?? null;
include 'db1.php';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
require_once 'currency_helper.php';
$displayCurrency = resolve_display_currency($conn);
$currencyInfo    = $CURRENCY_INFO[$displayCurrency];

$wishlistItems = [];
if ($loggedInUserId) {
    $stmt = $conn->prepare("SELECT p.* FROM wishlist w JOIN products p ON p.product_id = w.product_id
                            WHERE w.user_id = ? ORDER BY w.created_at DESC");
    $stmt->bind_param("i", $loggedInUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $wishlistItems[] = wl3_item($row, $currencyInfo['rate'], $CAT_PAGES, $CAT_LABEL);
    $stmt->close();
}
$conn->close();

$firstName = $loggedInUserId ? trim(explode(' ', $_SESSION['user_name'] ?? 'Customer')[0]) : '';
$cartQty = [];
foreach (($_SESSION['cart'] ?? []) as $pid => $it) $cartQty[(string)$pid] = (int)($it['quantity'] ?? 0);

$page = [
    'loggedIn'   => (bool)$loggedInUserId,
    'items'      => $wishlistItems,
    'symbol'     => $currencyInfo['symbol'],
    'maskPrices' => $MASK_PRICES,
    'minQty'     => $MIN_QTY,
    'lowStockAt' => $LOW_STOCK_AT,
    'cart'       => (object)$cartQty,
];
$pageJson = json_encode($page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

$PAGE_TITLE = 'My wishlist - Araku Tribe';
$ACTIVE_NAV = '';

// Check Dual Mode (SPA AJAX request)
$is_spa_request = (!empty($_SERVER['HTTP_X_SPA']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['spa']));

ob_start();
include __DIR__ . '/header3.php';
?>
<style>
/* ============ wishlist3.php ============ */
.wl-hero{background:var(--cream);padding:28px 0 34px}
.crumbs{display:flex;gap:8px;margin:0 0 14px;font-size:13.5px;color:var(--muted)}
.crumbs a{color:var(--muted);text-decoration:none}
.crumbs a:hover{color:var(--brown)}
.crumbs span[aria-current]{color:var(--brown);font-weight:600}
.wl-hero .row{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap}
.wl-hero h1{font-size:clamp(2rem,3.6vw,3rem)}
.wl-hero .lede{margin-top:8px}
.guest-note{display:flex;align-items:center;gap:12px;margin:18px 0 0;padding:12px 16px;border-radius:16px;background:#fff;font-size:14px;color:var(--brown-deep);max-width:640px}
.guest-note .ico{flex:none;width:20px;height:20px;color:var(--orange-cta)}
.guest-note a{color:var(--orange-cta);font-weight:700}
.wl-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:28px 0 18px}
.wl-bar .wl-count{margin:0;font-weight:700;color:var(--brown-deep)}
.wl-bar .wl-count span{font-weight:500;color:var(--muted)}
.wl-bar .right{margin-left:auto;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.wl-bar select{height:42px;border:1.5px solid var(--line);border-radius:999px;padding:0 14px;background:#fff;font:inherit;font-size:14.5px;color:var(--ink)}
.wl-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px 18px;padding-bottom:80px}
.wcard{--tint:#F3E9DD;position:relative;display:flex;flex-direction:column;background:#fff;border-radius:22px;padding:10px;box-shadow:0 1px 2px rgba(59,35,23,.06),0 14px 34px -22px rgba(59,35,23,.45);transition:transform .35s cubic-bezier(.2,.7,.2,1),opacity .3s}
.wcard:hover{transform:translateY(-4px)}
.wcard.gone{opacity:0;transform:scale(.96)}
.wcard[data-cat="bag"]{--tint:#EEDFCF}.wcard[data-cat="cup"]{--tint:#F6EEE3}.wcard[data-cat="mug"]{--tint:#E2ECE5}.wcard[data-cat="tshirt"]{--tint:#EDE5DA}
.wmedia{position:relative;display:block;aspect-ratio:4/5;border-radius:16px;overflow:hidden;background:var(--tint)}
.wmedia img{width:100%;height:100%;object-fit:contain;padding:11%;mix-blend-mode:multiply;transition:transform .6s cubic-bezier(.2,.7,.2,1)}
.wcard:hover .wmedia img{transform:scale(1.06)}
.wx{position:absolute;top:10px;right:10px;width:38px;height:38px;border-radius:999px;border:0;background:rgba(255,255,255,.92);color:var(--red);display:flex;align-items:center;justify-content:center}
.wx .ico{width:19px;height:19px;fill:currentColor}
.wx:hover{transform:scale(1.08)}
.wbadge{position:absolute;top:12px;left:12px;padding:5px 10px;border-radius:999px;font-size:12px;font-weight:700;background:#fff;color:var(--red)}
.wbadge.out{background:var(--ink);color:#fff}
.wbody{padding:14px 6px 4px;display:flex;flex-direction:column;flex:1}
.wcat{margin:0 0 5px;font-size:12.5px;font-weight:600;color:var(--muted)}
.wbody h3{font-size:1.08rem;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.wbody h3 a{text-decoration:none}
.wbody h3 a:hover{color:var(--orange-cta)}
.wid{margin:4px 0 0;font-size:12px;letter-spacing:.02em;color:var(--muted)}
.wprice{margin:8px 0 0;font-weight:700;font-size:17px}
.wprice span{font-weight:500;font-size:13px;color:var(--muted)}
.wmoq{margin:3px 0 12px;font-size:12.5px;color:var(--muted)}
.wadd{margin-top:auto;width:100%;padding:12px;border-radius:999px;border:0;background:var(--brown-deep);color:var(--cream);font-weight:700;font-size:14.5px}
.wadd:hover{background:var(--orange-cta);color:#fff}
.wadd.in{background:var(--green);color:#fff}
.wadd:disabled{background:var(--cream-2);color:var(--muted);cursor:not-allowed}
.wremove{margin:8px auto 2px;border:0;background:none;font-size:13px;font-weight:600;color:var(--muted);text-decoration:underline;text-underline-offset:3px}
.wremove:hover{color:var(--red)}
.wl-empty{grid-column:1/-1;text-align:center;padding:56px 20px;background:#fff;border:1px solid var(--line);border-radius:24px}
.wl-empty .big{width:76px;height:76px;margin:0 auto 16px;border-radius:24px;display:flex;align-items:center;justify-content:center;background:var(--cream);color:var(--red)}
.wl-empty .big .ico{width:34px;height:34px}
.wl-empty h2{font-size:1.8rem}
.wl-empty p{color:var(--muted);margin:8px 0 20px}
.wl-empty .acts{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}
.skeleton{grid-column:1/-1;padding:40px;text-align:center;color:var(--muted)}

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
@media (max-width:1100px){ .wl-grid{grid-template-columns:repeat(3,minmax(0,1fr))} }
@media (max-width:760px){ .wl-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 12px} .wcard{padding:8px;border-radius:18px} .wbody h3{font-size:1rem} .wl-bar .right{margin-left:0;width:100%} .wl-bar .right .btn{flex:1} }
</style>

<main>
  <section class="wl-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>" data-spa="link">Home</a><span aria-hidden="true">/</span><span aria-current="page">Wishlist</span></nav>
      <div class="row">
        <div>
          <h1>My wishlist<?= $firstName !== '' ? ', ' . h3_e($firstName) : '' ?></h1>
          <p class="lede">Everything you've saved with the heart. Add to cart whenever you're ready.</p>
        </div>
        <a class="btn btn-outline" href="<?= h3_link('shop') ?>" data-spa="link">Shop more</a>
      </div>
      <?php if (!$loggedInUserId): ?>
        <p class="guest-note"><svg class="ico"><use href="#i-heart"/></svg>
          <span>Your wishlist is saved in this browser only. <a href="<?= h3_link('login') ?>" data-spa="link">Sign in</a> to keep it on every device.</span></p>
      <?php endif; ?>
    </div>
  </section>

  <div class="wrap">
    <div class="wl-bar" id="wlBar" hidden>
      <p class="wl-count" id="wlCount"></p>
      <div class="right">
        <label class="sr-only" for="wlSort">Sort</label>
        <select id="wlSort">
          <option value="recent">Recently saved</option>
          <option value="price-asc">Price: low to high</option>
          <option value="price-desc">Price: high to low</option>
          <option value="name">Name: A to Z</option>
        </select>
        <button type="button" class="btn btn-primary" id="addAll">Add all to cart</button>
      </div>
    </div>
    <div class="wl-grid" id="wlGrid" aria-live="polite"><div class="skeleton">Loading your wishlist…</div></div>
  </div>
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
const DATA = <?= $pageJson ?>;
</script>
<script>
(function () {
'use strict';
const $ = (s, el = document) => el.querySelector(s);
const $$ = (s, el = document) => [...el.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const MIN = DATA.minQty;
const cart = new Map(Object.entries(DATA.cart || {}).map(([k, v]) => [k, +v]));
const money = v => { const s = Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); return DATA.symbol + (DATA.maskPrices ? s.replace(/\d/g, '#') : s); };
const toast = m => window.A3 ? A3.toast(m) : alert(m);
let items = [];

function guestIds() { try { return JSON.parse(localStorage.getItem('guestWishlist') || '[]').map(String); } catch (e) { return []; } }

function addLabel(p) {
  if (p.stock <= 0) return 'Sold out';
  const q = cart.get(p.id) || 0;
  return q ? `Add ${MIN} more (${q} in cart)` : 'Add to cart';
}
function cardHTML(p) {
  const out = p.stock <= 0, low = !out && p.stock < DATA.lowStockAt;
  return `<article class="wcard" data-id="${esc(p.id)}" data-cat="${esc(p.cat)}">
    <a class="wmedia" href="${esc(p.url)}" data-qv="${esc(p.id)}"><img src="${esc(p.img)}" alt="${esc(p.name)}" loading="lazy"></a>
    ${out ? '<span class="wbadge out">Sold out</span>' : low ? '<span class="wbadge">Few left</span>' : ''}
    <button type="button" class="wx" data-remove="${esc(p.id)}" aria-label="Remove ${esc(p.name)} from wishlist"><svg class="ico"><use href="#i-heart"/></svg></button>
    <div class="wbody">
      <p class="wcat">${esc(p.label)}</p>
      <h3><a href="${esc(p.url)}" data-qv="${esc(p.id)}">${esc(p.name)}</a></h3>
      <p class="wid">ID: ${esc(p.id)}</p>
      <p class="wprice">${money(p.price)} <span>per unit</span></p>
      <p class="wmoq">Min. order ${MIN} units</p>
      <button type="button" class="wadd${cart.get(p.id) ? ' in' : ''}" data-add="${esc(p.id)}" ${out ? 'disabled' : ''}>${addLabel(p)}</button>
      <button type="button" class="wremove" data-remove="${esc(p.id)}">Remove</button>
    </div>
  </article>`;
}
function sorted() {
  const s = $('#wlSort').value, list = items.slice();
  if (s === 'price-asc') list.sort((a, b) => a.price - b.price);
  else if (s === 'price-desc') list.sort((a, b) => b.price - a.price);
  else if (s === 'name') list.sort((a, b) => a.name.localeCompare(b.name));
  return list;
}
function render() {
  const grid = $('#wlGrid');
  if (window.A3) A3.setWishCount(items.length);
  if (!items.length) {
    $('#wlBar').hidden = true;
    grid.innerHTML = `<div class="wl-empty"><div class="big"><svg class="ico" style="fill:currentColor"><use href="#i-heart"/></svg></div>
      <h2>Your wishlist is empty</h2><p>Tap the heart on any product to save it here for later.</p>
      <div class="acts"><a class="btn btn-primary" href="${esc((A3.links || {}).shop || 'index.php')}" data-spa="link">Browse products</a>
      <a class="btn btn-outline" href="${esc((A3.links || {}).offers || 'offers3.php')}" data-spa="link">See offers</a></div></div>`;
    return;
  }
  const avail = items.filter(p => p.stock > 0).length;
  $('#wlBar').hidden = false;
  $('#wlCount').innerHTML = `${items.length} saved ${items.length === 1 ? 'item' : 'items'}${avail < items.length ? ` <span>(${avail} in stock)</span>` : ''}`;
  $('#addAll').hidden = !avail;
  $('#addAll').textContent = avail > 1 ? `Add all ${avail} to cart` : 'Add to cart';
  grid.innerHTML = sorted().map(cardHTML).join('');
}

/* ---------- load: logged in = from the page, guest = look up the saved IDs ---------- */
async function load() {
  if (DATA.loggedIn) { items = DATA.items; render(); return; }
  const ids = guestIds();
  if (!ids.length) { items = []; render(); return; }
  try {
    const res = await fetch('wishlist3.php?ajax=ids&ids=' + encodeURIComponent(ids.join(',')));
    const data = await res.json();
    items = data.products || [];
    render();
  } catch (e) {
    $('#wlGrid').innerHTML = '<div class="wl-empty"><h2>Could not load your wishlist</h2><p>Please refresh the page to try again.</p></div>';
  }
}

/* ---------- actions ---------- */
async function addToCart(id, qty = MIN) {
  const res = await fetch('add_to_cart.php', { method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=add&product_id=' + encodeURIComponent(id) + '&quantity=' + qty + '&redirect_to=&ajax=1' });
  const data = await res.json();
  if (data.status !== 'success') throw new Error(data.message || 'Could not add to cart');
  cart.set(id, (cart.get(id) || 0) + qty);
}
async function remove(id) {
  if (DATA.loggedIn) {
    const res = await fetch('wishlist_toggle.php', { method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'product_id=' + encodeURIComponent(id) });
    const data = await res.json();
    if (data.status !== 'removed' && data.status !== 'added') throw new Error(data.message || 'Could not update your wishlist');
  } else {
    localStorage.setItem('guestWishlist', JSON.stringify(guestIds().filter(x => x !== id)));
  }
  items = items.filter(p => p.id !== id);
}


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
const qv = MiniQV({ minQty: MIN, lowStockAt: DATA.lowStockAt, money, add: (id, q) => addToCart(id, q),
  done: (p, q) => { render(); if (window.A3) A3.openCart({ title: `Added ${q} units of ${p.name}`, note: `You now have ${cart.get(p.id)} units in your cart.` }); } });

document.addEventListener('click', async e => {
  const qa = e.target.closest('[data-qv]');
  if (qa && !(e.metaKey || e.ctrlKey || e.shiftKey)) { e.preventDefault(); const p = items.find(x => x.id === qa.dataset.qv); if (p) qv.open(p); return; }
  const add = e.target.closest('[data-add]');
  if (add) {
    add.disabled = true; add.textContent = 'Adding…';
    try {
      const first = !cart.get(add.dataset.add);
      await addToCart(add.dataset.add);
      const p = items.find(x => x.id === add.dataset.add);
      if (window.A3) A3.openCart({ title: `Added ${MIN} ${first ? '' : 'more '}units of ${p ? p.name : 'this item'}`,
        note: first ? `Our minimum order is ${MIN} units per product. Add again to increase in steps of ${MIN}.` : `You now have ${cart.get(add.dataset.add)} units.` });
      render();
    } catch (err) { toast(err.message); add.disabled = false; render(); }
    return;
  }
  const rm = e.target.closest('[data-remove]');
  if (rm) {
    const card = rm.closest('.wcard'); if (card) card.classList.add('gone');
    try { await remove(rm.dataset.remove); setTimeout(render, 250); toast('Removed from your wishlist'); }
    catch (err) { if (card) card.classList.remove('gone'); toast(err.message); }
  }
});
$('#wlSort').addEventListener('change', render);
$('#addAll').addEventListener('click', async () => {
  const btn = $('#addAll'), list = items.filter(p => p.stock > 0);
  btn.disabled = true; btn.textContent = 'Adding…';
  let ok = 0;
  for (const p of list) { try { await addToCart(p.id); ok++; } catch (err) {} }
  btn.disabled = false;
  render();
  if (ok && window.A3) A3.openCart({ title: `Added ${ok} ${ok === 1 ? 'product' : 'products'} from your wishlist`, note: `That's ${MIN} units of each. Change quantities in the cart.` });
  else if (!ok) toast('Could not add these items right now.');
});

load();
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