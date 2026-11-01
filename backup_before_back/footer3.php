<?php
/* =====================================================================
   ARAKU TRIBE - footer3.php (Persistent Layout Shell & Footer)
   ---------------------------------------------------------------------
   Architecture: Pure PHP 8, Vanilla JS ES6+, Mobile-First CSS3.
   Manages footer links, universal drawers (Cart, Menu, Contact),
   floating AI Agent chat integration, and mobile view optimizations.
   ===================================================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf3'])) {
    $_SESSION['csrf3'] = bin2hex(random_bytes(16));
}
$h3Csrf = $_SESSION['csrf3'];

if (!function_exists('h3_e')) {
    function h3_e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('h3_link')) {
    function h3_link($k) { global $H3_LINKS; return h3_e($H3_LINKS[$k] ?? '#'); }
}

$H3_BOT_FLY_ALL_PAGES = true;
$H3_BOT_REFLY_MIN     = 5;
$H3_BOT_MESSAGES      = [
    'Order coffee, cups &amp; mugs in <b>one chat</b>. Try me!',
    '<b>🔥 Hot deals</b> are live. Want me to find the best one for you?',
    'Need <b>100+ units</b> for your café? I can set it up in a minute.',
    'Track or cancel an order in seconds. Just ask me!',
    'Skip the browsing. Tell me what you need and I\'ll fill your cart.',
];

if (function_exists('h3_set')) {
    $H3_BOT_REFLY_MIN = (int)h3_set('r_refly', $H3_BOT_REFLY_MIN);
    if (h3_set('r_fly', '1') === '0') $H3_BOT_FLY_ALL_PAGES = false;
}

$h3BotLabel  = function_exists('h3_set') ? h3_set('r_label', 'Click me!') : 'Click me!';
$h3BotBubble = !function_exists('h3_set') || h3_set('r_bubble', '1') !== '0';
$h3ShowBot   = empty($H3_HIDE_BOT) && function_exists('h3_bot_svg') && (!function_exists('h3_set') || h3_set('r_show', '1') !== '0');
$h3BotFly    = $H3_BOT_FLY_ALL_PAGES || (!empty($H3_BOT_FLY) && (!function_exists('h3_set') || h3_set('r_fly', '1') !== '0'));
?>
<style>
/* ============ Persistent Footer, Drawers & Floating Chat Styles ============ */
.mnav-agent{text-decoration:none;display:flex!important;align-items:center;gap:12px;margin:14px 0 4px;padding:12px!important;border-radius:16px;background:var(--brown-deep);color:#fff!important}
.mnav-agent small{display:block;font-size:12.5px;color:#F4D9B8}
.site-footer{background:var(--footer-bg,var(--brown-deep));color:var(--footer-text,#DCC9B8);font-size:14.5px}
.foot-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:40px;padding-top:64px;padding-bottom:48px}
.site-footer h3{font-family:var(--sans);font-size:15px;font-weight:700;color:#fff;margin:0 0 14px}
.site-footer ul{list-style:none;padding:0;margin:0;display:grid;gap:9px}
.site-footer a{text-decoration:none;color:#DCC9B8}
.site-footer a:hover{color:#fff}
.foot-brand .brand{color:var(--cream);margin-bottom:14px}
.foot-brand .brand{display:inline-flex;padding:10px 16px;border-radius:18px;background:var(--cream);box-shadow:0 10px 24px -16px rgba(0,0,0,.6)}
.foot-logo{height:52px;width:auto;max-width:100%;display:block}
.foot-brand p{margin:0 0 6px}
.socials{display:flex;gap:10px;margin-top:16px}
.socials a{width:38px;height:38px;border-radius:999px;border:1px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center}
.socials .ico{width:18px;height:18px}
.foot-base{border-top:1px solid rgba(255,255,255,.1)}
.foot-base .wrap{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px 16px;padding-top:18px;padding-bottom:18px;font-size:13.5px}
.foot-base .copy{justify-self:start}
.foot-base .powered{justify-self:center;display:inline-flex;align-items:center;gap:8px;font-weight:600}
.site-footer .foot-base .dc-link{display:inline-flex;align-items:center;gap:6px;padding:6px 16px;border-radius:999px;border:2px solid var(--gold);
  color:var(--gold);font-weight:900;letter-spacing:.06em;text-decoration:none;transition:background .2s,color .2s,transform .2s}
.site-footer .foot-base .dc-link:hover,.site-footer .foot-base .dc-link:focus-visible{background:var(--gold);color:var(--brown-deep);transform:translateY(-1px)}
.foot-base .dc-link svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}

@media (max-width:680px){
  .foot-base .wrap{grid-template-columns:1fr;justify-items:center;text-align:center;padding-bottom:96px}
  .foot-base .copy{justify-self:center}
}

.scrim{position:fixed;inset:0;background:rgba(36,24,17,.45);z-index:90;opacity:0;pointer-events:none;transition:opacity .25s}
.scrim.open{opacity:1;pointer-events:auto}
.drawer{position:fixed;top:0;bottom:0;right:0;width:min(420px,100%);background:#fff;z-index:100;display:flex;flex-direction:column;transform:translateX(100%);transition:transform .3s ease;visibility:hidden}
.drawer.left{right:auto;left:0;transform:translateX(-100%)}
.drawer.open{transform:none;visibility:visible}
.drawer-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--line)}
.drawer-head h2{font-size:1.4rem}
.drawer-body{flex:1;overflow-y:auto;padding:12px 22px}
.drawer-foot{padding:18px 22px;border-top:1px solid var(--line);display:grid;gap:10px;background:var(--cream)}
.drawer-foot p{margin:0;font-size:13.5px;color:var(--muted)}

.cart-line{display:flex;gap:14px;align-items:center;padding:14px 0;border-bottom:1px solid var(--line)}
.cart-line img{width:64px;height:64px;border-radius:var(--r-sm);object-fit:contain;padding:5px;background:var(--cream);mix-blend-mode:multiply}
.cart-line strong{display:block;font-weight:600;font-size:15px;line-height:1.3}
.cart-line span{font-size:13.5px;color:var(--muted)}
.cart-notice{display:grid;gap:4px;margin:6px 0 4px;padding:14px 16px;border-radius:var(--r-md);background:var(--green-soft);border-left:4px solid var(--green);font-size:14px;color:#3E4A43}
.cart-notice strong{color:var(--green);font-size:15px}
.cart-empty{text-align:center;padding:40px 8px;color:var(--muted)}
.cart-empty h3{font-size:1.3rem;margin-bottom:6px}

.mnav-group{padding:16px 0;border-bottom:1px solid var(--line)}
.mnav-group h3{font-family:var(--sans);font-size:13px;font-weight:600;color:var(--muted);margin:0 0 8px}
.mnav-group a{display:block;padding:9px 0;text-decoration:none;font-size:16.5px;font-weight:500}

.toast{position:fixed;left:50%;bottom:28px;transform:translate(-50%,16px);background:var(--brown-deep);color:var(--cream);padding:12px 20px;border-radius:999px;font-weight:600;font-size:14.5px;opacity:0;pointer-events:none;transition:opacity .2s,transform .2s;z-index:120;box-shadow:var(--shadow)}
.toast.show{opacity:1;transform:translate(-50%,0)}

@media (max-width:1260px){ .foot-grid{grid-template-columns:1fr 1fr} }
@media (max-width:680px){ .foot-grid{grid-template-columns:1fr} }

.qc-lead{margin:0 0 14px;font-size:14px;color:var(--muted)}
.qc-ways{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px}
.qc-ways a{display:flex;flex-direction:column;align-items:center;gap:6px;padding:12px 6px;border-radius:14px;background:var(--cream);text-decoration:none;font-weight:700;font-size:13px;color:var(--brown)}
.qc-ways a .ico{width:20px;height:20px}
.qc-ways a:hover{background:var(--cream-2)}
.qc-ways a.ai{background:var(--brown-deep);color:var(--gold)}
.qc-track{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;margin-bottom:16px;border-radius:14px;border:1px solid var(--line);text-decoration:none;font-weight:600;font-size:14px;color:var(--ink)}
.qc-track span{color:var(--orange-cta);font-weight:700}
.qc-form{display:grid;gap:6px}
.qc-form[hidden]{display:none}
.qc-form label{margin-top:6px;font-weight:600;font-size:13.5px;color:var(--brown-deep)}
.qc-form input,.qc-form select,.qc-form textarea{width:100%;border:1.5px solid var(--line);border-radius:12px;padding:10px 12px;background:var(--cream);font:inherit;font-size:15px;color:var(--ink)}
.qc-form input,.qc-form select{height:44px;padding:0 12px}
.qc-form input:focus,.qc-form select:focus,.qc-form textarea:focus{outline:none;border-color:var(--brown-soft);background:#fff}
.qc-form .btn{margin-top:10px;width:100%}
.qc-out{margin:12px 0;padding:12px 14px;border-radius:12px;font-weight:600;font-size:14px}
.qc-out.ok{background:var(--green-soft);color:var(--green)}
.qc-out.bad{background:#FBE4E4;color:#9B2626}
.qc-full{display:block;margin-top:14px;text-align:center;font-weight:600;font-size:14px;color:var(--brown)}

/* Floating AI Chat Assistant & Robot Styles */
.a3-chat{position:fixed;right:22px;bottom:22px;z-index:85;display:flex;flex-direction:column;align-items:flex-end;gap:10px;pointer-events:none}
.a3-chat>*{pointer-events:auto}
.a3-chat-btn{position:relative;display:block;width:78px;height:78px;border-radius:50%;text-decoration:none;
  background:radial-gradient(circle at 50% 38%,#fff 0%,#FFF1E6 60%,#FFD9C2 100%);box-shadow:0 14px 30px -10px rgba(227,18,27,.7)}
.a3-chat-btn::before{content:"";position:absolute;inset:-5px;border-radius:50%;padding:4px;
  background:conic-gradient(from var(--spin,0deg),#8B00FF,#4B0082,#0066FF,#00B140,#FFD400,#FF7A00,#E3121B,#8B00FF);
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude;animation:spin 2.4s linear infinite}
.a3-chat-btn::after{content:"";position:absolute;inset:-5px;border-radius:50%;border:3px solid rgba(227,18,27,.55);animation:ping 2s cubic-bezier(0,0,.2,1) infinite}
@keyframes ping{0%{transform:scale(1);opacity:.9}80%,100%{transform:scale(1.55);opacity:0}}
.a3-chat-bot{position:absolute;inset:6px 8px 8px;--bot:#E3121B;animation:redGlow 2s ease-in-out infinite,bob 2.6s ease-in-out infinite}
.a3-chat-badge{position:absolute;top:-2px;right:-2px;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:#E3121B;color:#fff;font:800 12px/22px var(--sans);text-align:center;box-shadow:0 0 0 3px #fff}
.a3-chat-bubble{position:relative;max-width:260px;padding:13px 34px 13px 15px;border-radius:18px 18px 4px 18px;background:#fff;color:var(--ink);font-size:14px;line-height:1.45;cursor:pointer;
  box-shadow:0 18px 40px -16px rgba(36,24,17,.55),0 0 0 2px #E3121B}
.a3-chat-bubble[hidden]{display:none}
.a3-chat-bubble strong{display:block;color:#E3121B;font-size:14.5px;margin-bottom:2px}
.a3-chat-bubble b{color:var(--brown-deep)}
.a3-chat-bubble .x{position:absolute;top:6px;right:6px;width:24px;height:24px;border:0;border-radius:50%;background:var(--cream);color:var(--muted);font-size:16px;line-height:1;cursor:pointer}
.a3-chat-label{font:800 11px/1 var(--sans);letter-spacing:.08em;text-transform:uppercase;color:#fff;background:#E3121B;padding:5px 9px;border-radius:999px;box-shadow:0 6px 14px -6px rgba(227,18,27,.8);margin-right:7px}
.a3-chat-fly{display:flex;align-items:center;transform-origin:100% 100%}

@media (max-width:680px){
  .a3-chat{right:14px;bottom:14px}
  .a3-chat-btn{width:64px;height:64px}
  .a3-chat-bubble{max-width:210px;font-size:13px;padding:10px 30px 10px 12px}
  .a3-chat:not(.flying) .a3-chat-label{display:none}
}
</style>

<footer class="site-footer">
  <div class="wrap foot-grid">
    <div class="foot-brand">
      <a class="brand" href="<?= h3_link('home') ?>" data-spa-link aria-label="Araku Tribe Coffee home">
        <?php if (!empty($H3_LOGO_FOOTER)): ?>
          <img src="<?= h3_e($H3_LOGO_FOOTER) ?>" alt="Araku Tribe Coffee" class="foot-logo">
        <?php else: ?>
          <svg class="brand-mark ico"><use href="#i-bean"/></svg><span class="brand-name">Araku Tribe</span>
        <?php endif; ?>
      </a>
      <p>Araku Tribe Coffee, Araku Valley, Visakhapatnam</p>
      <p><a href="tel:+917893836529">+91 78938 36529</a></p>
      <p><a href="mailto:info@arakutribe.com">info@arakutribe.com</a></p>
      <div class="socials">
        <a href="https://www.facebook.com/profile.php?id=61572935911579" target="_blank" rel="noopener" aria-label="Facebook"><svg class="ico"><use href="#i-fb"/></svg></a>
        <a href="https://www.instagram.com/araku_tribe" target="_blank" rel="noopener" aria-label="Instagram"><svg class="ico"><use href="#i-ig"/></svg></a>
        <a href="https://x.com/university94155" target="_blank" rel="noopener" aria-label="X"><svg class="ico"><use href="#i-tw"/></svg></a>
      </div>
    </div>
    <div>
      <h3>Shop</h3>
      <ul>
        <li><a href="<?= h3_link('bag') ?>" data-spa-link>Coffee bags</a></li>
        <li><a href="<?= h3_link('cup') ?>" data-spa-link>Coffee cups</a></li>
        <li><a href="<?= h3_link('mug') ?>" data-spa-link>Coffee mugs</a></li>
        <li><a href="<?= h3_link('tshirt') ?>" data-spa-link>T-shirts</a></li>
        <li><a href="<?= h3_link('hotdeals') ?>" data-spa-link style="color:#FFB36B;font-weight:700">🔥 Hot deals</a></li>
        <li><a href="<?= h3_link('offers') ?>" data-spa-link>Offers</a></li>
        <li><a href="<?= h3_link('combos') ?>" data-spa-link>Gifts &amp; combos</a></li>
      </ul>
    </div>
    <div>
      <h3>Help</h3>
      <ul>
        <li><a href="<?= h3_link('orders') ?>" data-spa-link>Track my order</a></li>
        <li><a href="<?= h3_link('cart') ?>" class="open-cart-drawer">My cart</a></li>
        <li><a href="<?= h3_link('wishlist') ?>" data-spa-link>My wishlist</a></li>
        <li><a href="<?= h3_link('contact') ?>" data-spa-link>Contact us</a></li>
        <li><a href="<?= h3_link('agent') ?>" class="open-agent-drawer">AI Agent: express checkout</a></li>
      </ul>
    </div>
    <div>
      <h3>Araku Tribe</h3>
      <ul>
        <li><a href="<?= h3_link('home') ?>" data-spa-link>Home</a></li>
        <li><a href="<?= h3_link('about') ?>" data-spa-link>About</a></li>
        <li><a href="<?= h3_link('contact') ?>" data-spa-link>Contact us</a></li>
        <li><a href="<?= h3_link('franchise') ?>" data-spa-link>Franchise</a></li>
        <li><a href="<?= h3_link('terms') ?>" data-spa-link>Terms &amp; conditions</a></li>
        <li><a href="<?= h3_link('privacy') ?>" data-spa-link>Privacy policy</a></li>
      </ul>
    </div>
  </div>
  <div class="foot-base">
    <div class="wrap">
      <span class="copy">&copy; <?= date('Y') ?> Araku Tribe. All rights reserved.</span>
      <span class="powered">Powered by
        <a class="dc-link" href="https://www.facebook.com/profile.php?id=61582627243027" target="_blank" rel="noopener" title="DC (opens in a new tab)">DC
          <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
          <span class="sr-only">(opens in a new tab)</span>
        </a>
      </span>
      <span aria-hidden="true"></span>
    </div>
  </div>
</footer>

<!-- Cart Drawer Container -->
<div class="scrim" id="scrim"></div>
<aside class="drawer" id="cartDrawer" aria-labelledby="cartTitle" role="dialog" aria-modal="true">
  <div class="drawer-head">
    <h2 id="cartTitle">Your cart</h2>
    <button class="icon-btn" data-close aria-label="Close cart"><svg class="ico"><use href="#i-x"/></svg></button>
  </div>
  <div class="drawer-body" id="cartBody"></div>
  <div class="drawer-foot">
    <p>Prices, coupons and delivery are shown on the cart page.</p>
    <a class="btn btn-primary" href="<?= h3_link('cart') ?>" data-spa-link>Go to checkout</a>
    <button class="btn btn-outline" data-close>Keep shopping</button>
  </div>
</aside>

<!-- Phone Menu Drawer -->
<aside class="drawer left" id="menuDrawer" aria-label="Menu" role="dialog" aria-modal="true">
  <div class="drawer-head">
    <h2>Menu</h2>
    <button class="icon-btn" data-close aria-label="Close menu"><svg class="ico"><use href="#i-x"/></svg></button>
  </div>
  <div class="drawer-body">
    <a class="mnav-agent open-agent-drawer" href="<?= h3_link('agent') ?>">
      <span class="orb" style="background:radial-gradient(circle at 35% 30%,#FF6B6B 0%,#E3121B 55%,#8E0A10 100%)"><svg class="ico" style="stroke:#fff"><use href="#i-robot"/></svg></span>
      <span><strong>AI Agent</strong><small>Order in one chat</small></span>
    </a>
    <a class="mnav-agent mnav-hot" href="<?= h3_link('hotdeals') ?>" data-spa-link style="background:linear-gradient(110deg,#E3121B,#FF4D00 60%,#FF9500)">
      <span class="orb" style="background:rgba(255,255,255,.2)"><svg class="ico" style="fill:#FFE066;stroke:#fff"><use href="#i-flame"/></svg></span>
      <span><strong>HOT DEALS</strong><small style="color:#FFE9D6">All offers first, then everything</small></span>
    </a>
    <div class="mnav-group">
      <h3>Shop</h3>
      <a href="<?= h3_link('shop') ?>" data-spa-link data-close>Shop all</a>
      <a href="<?= h3_link('bag') ?>" data-spa-link>Coffee bags</a>
      <a href="<?= h3_link('cup') ?>" data-spa-link>Coffee cups</a>
      <a href="<?= h3_link('mug') ?>" data-spa-link>Coffee mugs</a>
      <a href="<?= h3_link('tshirt') ?>" data-spa-link>T-shirts</a>
    </div>
    <div class="mnav-group">
      <h3>Collections</h3>
      <a href="<?= h3_link('new') ?>" data-spa-link data-close>New arrivals</a>
      <a href="<?= h3_link('offers') ?>" data-spa-link data-close>Offers</a>
      <a href="<?= h3_link('combos') ?>" data-spa-link data-close>Gifts &amp; combos</a>
    </div>
    <div class="mnav-group">
      <h3>Story</h3>
      <a href="<?= h3_link('about') ?>" data-spa-link>About</a>
      <a href="<?= h3_link('franchise') ?>" data-spa-link>Franchise for cafés</a>
    </div>
    <div class="mnav-group">
      <h3>Support</h3>
      <a href="<?= h3_link('orders') ?>" data-spa-link>Track my order</a>
      <a href="<?= h3_link('contact') ?>" data-spa-link>Contact us</a>
      <a href="#" data-contact>Help &amp; quick message</a>
    </div>
  </div>
</aside>

<!-- Help & Contact Panel Drawer -->
<aside class="drawer" id="contactDrawer" aria-labelledby="qcTitle" role="dialog" aria-modal="true">
  <div class="drawer-head">
    <h2 id="qcTitle">Help &amp; contact</h2>
    <button class="icon-btn" data-close aria-label="Close"><svg class="ico"><use href="#i-x"/></svg></button>
  </div>
  <div class="drawer-body">
    <p class="qc-lead">We usually reply within 24 hours. For anything urgent, call us.</p>
    <div class="qc-ways">
      <a href="tel:+917893836529"><svg class="ico"><use href="#i-phone"/></svg><span>Call</span></a>
      <a href="mailto:info@arakutribe.com"><svg class="ico"><use href="#i-mail"/></svg><span>Email</span></a>
      <a href="<?= h3_link('agent') ?>" class="ai open-agent-drawer"><svg class="ico"><use href="#i-robot"/></svg><span>AI Agent</span></a>
    </div>
    <a class="qc-track" href="<?= h3_link('orders') ?>" data-spa-link>Where's my order? <span>Track it</span></a>
    <form id="qcForm" class="qc-form" novalidate>
      <input type="hidden" name="csrf" value="<?= h3_e($h3Csrf) ?>">
      <div class="trap" aria-hidden="true" style="position:absolute;left:-9999px"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <label for="qcTopic">What's it about?</label>
      <select id="qcTopic" name="topic">
        <option value="general">General question</option>
        <option value="order">Help with an order</option>
        <option value="bulk">Bulk or café orders (Min 100 units)</option>
        <option value="franchise">Franchise</option>
        <option value="feedback">Feedback</option>
      </select>
      <label for="qcName">Your name</label>
      <input id="qcName" name="name" autocomplete="name" maxlength="80" value="<?= h3_e(!empty($_SESSION['user_id']) ? ($_SESSION['user_name'] ?? '') : '') ?>">
      <label for="qcEmail">Email</label>
      <input id="qcEmail" name="email" type="email" autocomplete="email" value="<?= h3_e(!empty($_SESSION['user_id']) ? ($_SESSION['user_email'] ?? '') : '') ?>">
      <label for="qcMsg">Message</label>
      <textarea id="qcMsg" name="message" rows="4" maxlength="3000" placeholder="How can we help?"></textarea>
      <button type="submit" class="btn btn-primary">Send message</button>
    </form>
    <p class="qc-out" id="qcOut" role="status" hidden></p>
    <button type="button" class="btn btn-outline" id="qcAgain" hidden style="width:100%">Send another message</button>
    <a class="qc-full" href="<?= h3_link('contact') ?>" data-spa-link>Open the full contact page</a>
  </div>
</aside>

<!-- Dedicated Araku Agent Slide-Over Drawer Container -->
<aside class="drawer" id="araku-agent-drawer" aria-label="Araku AI Agent" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="drawer-head">
    <h2>Araku AI Agent</h2>
    <button class="icon-btn" data-close aria-label="Close AI Agent"><svg class="ico"><use href="#i-x"/></svg></button>
  </div>
  <div class="drawer-body" id="araku-agent-drawer-content">
    <div style="text-align:center;padding:40px;color:var(--muted)">Loading Assistant...</div>
  </div>
</aside>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>
/* =====================================================================
   ASYNCHRONOUS AJAX/FETCH INTERCEPTION & SPA ROUTER IN FOOTER
   ===================================================================== */
(function () {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];

  // Toast notification helper
  window.A3 = window.A3 || {};
  window.A3.toast = function(msg) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
  };

  // Drawer Management Functions
  function openDrawer(drawerId, fetchUrl) {
    const drawer = document.getElementById(drawerId);
    const scrim = document.getElementById('scrim');
    if (!drawer) return;

    if (scrim) scrim.classList.add('open');
    drawer.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');

    if (fetchUrl) {
      const contentId = drawerId === 'araku-agent-drawer' ? 'araku-agent-drawer-content' : (drawerId === 'cartDrawer' ? 'cartBody' : null);
      const contentBox = contentId ? document.getElementById(contentId) : null;
      if (contentBox) {
        fetch(fetchUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(res => res.ok ? res.text() : '<div style="padding:20px;color:var(--red)">Failed to load content.</div>')
          .then(html => { contentBox.innerHTML = html; })
          .catch(() => { contentBox.innerHTML = '<div style="padding:20px;color:var(--red)">Network error.</div>'; });
      }
    }
  }

  function closeAllDrawers() {
    const scrim = document.getElementById('scrim');
    if (scrim) scrim.classList.remove('open');
    $$('.drawer').forEach(d => {
      d.classList.remove('open');
      d.setAttribute('aria-hidden', 'true');
    });
  }

  window.A3.openDrawer = openDrawer;
  window.A3.closeDrawers = closeAllDrawers;

  // SPA Fragment Loader Engine
  async function loadFragment(url, updateHistory = true) {
    const targetContainer = $('#app-main-content') || $('main');
    if (!targetContainer) {
      window.location.href = url;
      return;
    }

    targetContainer.style.opacity = '0.5';
    targetContainer.style.pointerEvents = 'none';

    try {
      const parsedUrl = new URL(url, window.location.origin);
      parsedUrl.searchParams.set('ajax', '1');

      const response = await fetch(parsedUrl.toString(), {
        headers: { 
          'X-Requested-With': 'XMLHttpRequest',
          'X-SPA-Request': '1'
        }
      });

      if (!response.ok) throw new Error('Fragment load failure');

      const html = await response.text();
      targetContainer.innerHTML = html;

      if (updateHistory) {
        history.pushState({ spaUrl: url }, '', url);
      }

      window.scrollTo({ top: 0, behavior: 'smooth' });

      const currentPath = new URL(url, window.location.origin).pathname;
      $$('[data-spa-link]').forEach(el => {
        const linkPath = new URL(el.getAttribute('href'), window.location.origin).pathname;
        if (linkPath === currentPath) {
          el.setAttribute('aria-current', 'page');
        } else {
          el.removeAttribute('aria-current');
        }
      });
    } catch (err) {
      window.location.href = url;
    } finally {
      targetContainer.style.opacity = '1';
      targetContainer.style.pointerEvents = 'auto';
    }
  }

  // Global Event Listener Delegation for SPA Links and Drawers
  document.addEventListener('click', function (e) {
    // 1. Data SPA Link Interceptor
    const spaLink = e.target.closest('[data-spa-link]');
    if (spaLink) {
      const href = spaLink.getAttribute('href');
      if (href && !href.startsWith('#') && !href.startsWith('javascript:')) {
        e.preventDefault();
        closeAllDrawers();
        loadFragment(href);
        return;
      }
    }

    // 2. AI Agent Drawer Interceptor
    const agentTrigger = e.target.closest('.open-agent-drawer');
    if (agentTrigger) {
      e.preventDefault();
      closeAllDrawers();
      openDrawer('araku-agent-drawer', 'araku_agent3.php?ajax=1');
      return;
    }

    // 3. Cart Drawer Interceptor
    const cartTrigger = e.target.closest('.open-cart-drawer');
    if (cartTrigger) {
      e.preventDefault();
      closeAllDrawers();
      openDrawer('cartDrawer', 'cart3.php?ajax=1');
      return;
    }

    // 4. Contact / Help Drawer Interceptor
    const contactTrigger = e.target.closest('[data-contact]');
    if (contactTrigger) {
      e.preventDefault();
      closeAllDrawers();
      openDrawer('contactDrawer');
      return;
    }

    // 5. Menu Drawer Trigger
    const menuTrigger = e.target.closest('#menuBtn, [data-menu-trigger]');
    if (menuTrigger) {
      e.preventDefault();
      openDrawer('menuDrawer');
      return;
    }

    // 6. Close Drawers Triggers
    if (e.target.closest('[data-close], #scrim')) {
      closeAllDrawers();
    }
  });

  window.addEventListener('popstate', function (e) {
    if (e.state && e.state.spaUrl) {
      loadFragment(e.state.spaUrl, false);
    } else {
      loadFragment(window.location.href, false);
    }
  });

  // Breadcrumb back button enhancements
  const c = document.querySelector('.crumbs'); 
  if (c) {
    const x = document.createElement('button');
    x.type = 'button'; 
    x.className = 'crumb-close'; 
    x.setAttribute('aria-label', 'Close and go back');
    x.innerHTML = '<b aria-hidden="true">✕</b><span>Close</span>';
    x.addEventListener('click', () => {
      let same = false; 
      try { 
        same = !!document.referrer && new URL(document.referrer).origin === location.origin; 
      } catch (e) {}
      if (same && history.length > 1) {
        history.back(); 
      } else {
        loadFragment(<?= json_encode($H3_LINKS['home'] ?? 'index.php') ?>);
      }
    });
    c.appendChild(x);
  }

  // Global AJAX Form Submission Handling for Quick Contact
  document.addEventListener('submit', async e => {
    const f = e.target.closest('#qcForm'); 
    if (!f) return;
    e.preventDefault();
    const need = ['qcName', 'qcEmail', 'qcMsg'].map(id => document.getElementById(id)).find(i => !i.value.trim());
    if (need) { 
      need.focus(); 
      window.A3.toast('Please fill in your name, email and message.');
      return; 
    }
    const btn = f.querySelector('button[type="submit"]'), out = document.getElementById('qcOut');
    btn.disabled = true; 
    btn.textContent = 'Sending…'; 
    out.hidden = true;
    try {
      const fd = new FormData(f); 
      fd.set('ajax', '1');
      const res = await fetch('coffee_contact3.php', { 
        method: 'POST', 
        body: fd, 
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await res.json();
      out.hidden = false; 
      out.className = 'qc-out ' + (data.status === 'success' ? 'ok' : 'bad'); 
      out.textContent = data.message;
      if (data.status === 'success') { 
        f.reset(); 
        f.hidden = true; 
        document.getElementById('qcAgain').hidden = false; 
      }
    } catch (err) { 
      out.hidden = false; 
      out.className = 'qc-out bad'; 
      out.textContent = "We couldn't send that. Please email us directly."; 
    } finally { 
      btn.disabled = false; 
      btn.textContent = 'Send message'; 
    }
  });

  document.addEventListener('click', e => {
    if (!e.target.closest('#qcAgain')) return;
    document.getElementById('qcForm').hidden = false; 
    document.getElementById('qcAgain').hidden = true; 
    document.getElementById('qcOut').hidden = true;
  });
})();
</script>

<?php if ($h3ShowBot): ?>
<!-- Floating AI Agent Assistant Widget -->
<div class="a3-chat" id="a3Chat">
  <div class="a3-chat-bubble" id="a3ChatBubble" role="status" hidden>
    <button type="button" class="x" id="a3ChatX" aria-label="Hide message">×</button>
    <strong>Hi! I'm your Araku AI Agent 👋</strong>
    <span id="a3ChatMsg"></span>
  </div>
  <div class="a3-chat-fly">
    <span class="a3-chat-label" aria-hidden="true"><?= h3_e($h3BotLabel) ?></span>
    <a class="a3-chat-btn open-agent-drawer" href="<?= h3_link('agent') ?>" aria-label="Open AI Support Agent">
      <span class="a3-chat-bot"><?= h3_bot_svg() ?></span>
      <span class="a3-chat-badge" aria-hidden="true">1</span>
    </a>
  </div>
</div>
<script>
(function () {
  'use strict';
  const root = document.getElementById('a3Chat'); 
  if (!root) return;
  const MSGS = <?= json_encode(array_values($H3_BOT_MESSAGES), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  const bubble = document.getElementById('a3ChatBubble');
  const msgEl = document.getElementById('a3ChatMsg');
  if (msgEl) {
    msgEl.innerHTML = MSGS[Math.floor(Math.random() * MSGS.length)] || '';
  }
  const ss = (k, v) => { try { if (v === undefined) return sessionStorage.getItem(k); sessionStorage.setItem(k, v); } catch (e) { return null; } };

  function showBubble() {
    if (!<?= $h3BotBubble ? 'true' : 'false' ?> || ss('a3_bubble_off')) return;
    if (bubble) {
      bubble.hidden = false;
      setTimeout(() => { if (bubble) bubble.hidden = true; }, 10000);
    }
  }
  if (bubble) {
    bubble.addEventListener('click', e => { 
      if (!e.target.closest('.x')) {
        const drawer = document.getElementById('araku-agent-drawer');
        if (drawer && window.A3 && typeof window.A3.openDrawer === 'function') {
          window.A3.openDrawer('araku-agent-drawer', 'araku_agent3.php?ajax=1');
        }
      }
    });
    const xBtn = document.getElementById('a3ChatX');
    if (xBtn) {
      xBtn.addEventListener('click', e => { 
        e.stopPropagation(); 
        bubble.hidden = true; 
        ss('a3_bubble_off', '1'); 
      });
    }
  }
  setTimeout(showBubble, 1200);
})();
</script>
<?php endif; ?>

</body>
</html>