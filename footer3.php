<?php
/* =====================================================================
   ARAKU TRIBE - footer3.php
   Self-contained: its own CSS is in this file. Holds the footer, the
   cart drawer, the phone menu and the toast message. The behaviour for
   these (open/close, cart contents) is in header3.php's script, so a
   page only needs:  <?php include 'footer3.php'; ?>  at the very end.
   Links come from $H3_LINKS in header3.php.
   ===================================================================== */
if (!function_exists('h3_link')) {   // footer used without header3.php: fall back to plain links
    function h3_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
    function h3_link($k) { global $H3_LINKS; return h3_e($H3_LINKS[$k] ?? '#'); }
}

/* ---------- FLOATING AI AGENT CHAT BUTTON (bottom-right on every page) ----------
   A robot that flies around the screen in VIBGYOR colours, then sits in the corner
   glowing red with a message. Clicking it opens the AI Agent page.
   A page can set (before including footer3.php):
     $H3_HIDE_BOT = true;   // hide it (araku_agent3.php does this - the chat is already there)
     $H3_BOT_FLY  = true;   // let it fly around on this page (index.php + the 4 product pages do this) */
$H3_BOT_FLY_ALL_PAGES = true;   // true = it flies around on EVERY page; false = only pages that set $H3_BOT_FLY
$H3_BOT_REFLY_MIN     = 5;      // it flies on EVERY page load and refresh, and again every 5 minutes
                                // while someone stays on the same page (0 = only when a page loads)
$H3_BOT_MESSAGES = [            // one is picked at random on each page load
    'Order coffee, cups &amp; mugs in <b>one chat</b>. Try me!',
    '<b>🔥 Hot deals</b> are live. Want me to find the best one for you?',
    'Need <b>100+ units</b> for your café? I can set it up in a minute.',
    'Track or cancel an order in seconds. Just ask me!',
    'Skip the browsing. Tell me what you need and I\'ll fill your cart.',
];
if (function_exists('h3_set')) {                     // values from admin_settings3.php (AI robot section)
    $H3_BOT_REFLY_MIN = (int)h3_set('r_refly', $H3_BOT_REFLY_MIN);
    if (h3_set('r_fly', '1') === '0') $H3_BOT_FLY_ALL_PAGES = false;
}
$h3BotLabel  = function_exists('h3_set') ? h3_set('r_label', 'Click me!') : 'Click me!';
$h3BotBubble = !function_exists('h3_set') || h3_set('r_bubble', '1') !== '0';
$h3ShowBot = empty($H3_HIDE_BOT) && function_exists('h3_bot_svg') && (!function_exists('h3_set') || h3_set('r_show', '1') !== '0');
$h3BotFly  = $H3_BOT_FLY_ALL_PAGES || (!empty($H3_BOT_FLY) && (!function_exists('h3_set') || h3_set('r_fly', '1') !== '0'));
?>
<style>
/* ============ footer3.php styles (footer, cart drawer, phone menu, toast) ============ */
.mnav-agent{text-decoration:none;display:flex!important;align-items:center;gap:12px;margin:14px 0 4px;padding:12px!important;border-radius:16px;background:var(--brown-deep);color:#fff!important}
.mnav-agent small{display:block;font-size:12.5px;color:#F4D9B8}
.site-footer{background:var(--footer-bg,var(--brown-deep));color:var(--footer-text,#DCC9B8);font-size:14.5px}
.foot-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:40px;padding-top:64px;padding-bottom:48px}
.site-footer h3{font-family:var(--sans);font-size:15px;font-weight:700;color:#fff;margin:0 0 14px}
.site-footer ul{list-style:none;padding:0;margin:0;display:grid;gap:9px}
.site-footer a{text-decoration:none;color:#DCC9B8}
.site-footer a:hover{color:#fff}
.foot-brand .brand{color:var(--cream);margin-bottom:14px}
.foot-brand .brand{display:inline-flex;padding:10px 16px;border-radius:18px;background:var(--cream);box-shadow:0 10px 24px -16px rgba(0,0,0,.6)}   /* cream badge: the brown logo stands out on the dark footer */
.foot-logo{height:52px;width:auto;max-width:100%;display:block}
/* quick contact panel */
.foot-brand p{margin:0 0 6px}
.socials{display:flex;gap:10px;margin-top:16px}
.socials a{width:38px;height:38px;border-radius:999px;border:1px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center}
.socials .ico{width:18px;height:18px}
.foot-base{border-top:1px solid rgba(255,255,255,.1)}
.foot-base .wrap{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px 16px;padding-top:18px;padding-bottom:18px;font-size:13.5px}
.foot-base .copy{justify-self:start}
.foot-base .powered{justify-self:center;display:inline-flex;align-items:center;gap:8px;font-weight:600}
/* "Powered by DC": centred, clearly a link */
.site-footer .foot-base .dc-link{display:inline-flex;align-items:center;gap:6px;padding:6px 16px;border-radius:999px;border:2px solid var(--gold);
  color:var(--gold);font-weight:900;letter-spacing:.06em;text-decoration:none;transition:background .2s,color .2s,transform .2s}
.site-footer .foot-base .dc-link:hover,.site-footer .foot-base .dc-link:focus-visible{background:var(--gold);color:var(--brown-deep);transform:translateY(-1px)}
.foot-base .dc-link svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
@media (max-width:680px){
  .foot-base .wrap{grid-template-columns:1fr;justify-items:center;text-align:center;padding-bottom:96px}   /* room for the floating robot */
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
@media (max-width:1260px){
  .foot-grid{grid-template-columns:1fr 1fr}
}
@media (max-width:680px){
  .foot-grid{grid-template-columns:1fr}
}
.qc-lead{margin:0 0 14px;font-size:14px;color:var(--muted)}
.qc-ways{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px}
.qc-ways a{display:flex;flex-direction:column;align-items:center;gap:6px;padding:12px 6px;border-radius:14px;background:var(--cream);text-decoration:none;font-weight:700;font-size:13px;color:var(--brown)}
.qc-ways a .ico{width:20px;height:20px}
.qc-ways a:hover{background:var(--cream-2)}
.qc-ways a.ai{background:var(--brown-deep);color:var(--gold)}
.qc-track{display:flex;justify-between:space-between;align-items:center;padding:12px 14px;margin-bottom:16px;border-radius:14px;border:1px solid var(--line);text-decoration:none;font-weight:600;font-size:14px;color:var(--ink)}
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

/* ---------- IMAGE FIT FIX (all pages) ----------
   Product photos are packshots, so they must never be cropped, zoomed or stretched:
   show the whole product on a soft tint instead. Lifestyle photos keep "cover". */
.qv-img,.mqv-img{display:flex;align-items:center;justify-content:center;background:#F3E9DD}
.qv-img img,.mqv-img img{width:100%;height:100%;object-fit:contain;padding:9%;mix-blend-mode:multiply}
.slide-figure .frame img{object-fit:contain;padding:8%;mix-blend-mode:multiply}

/* ---------- Product cards (all pages): bolder, brighter text ---------- */
.card-title{font-family:var(--sans)!important;font-weight:800!important;font-size:1.06rem;color:var(--card-title,#2A1150)!important;letter-spacing:-.005em}
.card-cat{font-weight:800!important;color:var(--card-cat,#C9501A)!important;text-transform:uppercase;letter-spacing:.05em;font-size:12px!important}
.card-id{font-weight:600;color:#6B5A4E!important}
.card .price{font-weight:900;font-size:18.5px;color:var(--price,#17110D)}
.card .price .deal{color:var(--price-deal,#D9141C)}
.card .price span{font-weight:700;color:#5B4B41}
.price-note{font-weight:800!important;color:#0E8A4A!important}
/* product thumbnails (admin settings > Thumbnails); values after the comma = today's look */
.card-media{border-radius:var(--thumb-radius,16px);box-shadow:var(--thumb-shadow,none)}
.card-media>a img{object-fit:var(--thumb-fit,contain);padding:var(--thumb-pad,11%)}
.card:hover .card-media>a img{transform:scale(var(--thumb-zoom,1.07))}
.thumb-one-bg .card-media{background:var(--thumb-bg,#F3E9DD)!important}

/* ---------- ICONS, LABELS, BADGES, FILTERS & CHIPS (admin settings) ----------
   This file loads last, so these win over the header and page styles.
   The value after each comma is today's look (used when nothing is saved). */
.ico{width:var(--ico-size,22px);height:var(--ico-size,22px);stroke-width:var(--ico-stroke,1.7)}
.head-actions .icon-btn,.head-actions .acct-btn{color:var(--hicon-fg,#FFF4E2);border-color:var(--hicon-rim,#F4B63D);
  background:linear-gradient(135deg,var(--hicon-from,#8A4B2A) 0%,var(--hicon-to,#4A2A1A) 100%)}
.head-actions .acct-btn .avatar{color:var(--hicon-fg,#FFF4E2)}
.head-actions .count{background:var(--count-bg,#E3121B);color:var(--count-fg,#fff)}
@media (min-width:681px){
  .search input{border-color:var(--hicon-rim,#F4B63D);background:linear-gradient(135deg,var(--hicon-from,#8A4B2A) 0%,var(--hicon-to,#4A2A1A) 100%)}
  .search:focus-within input{background:#fff}
  .search .ico{color:var(--hicon-fg,#FFF4E2)!important}
  .search:focus-within .ico{color:var(--brown)!important}
}
@media (max-width:680px){
  .menu-btn{color:var(--hicon-fg,#FFF4E2);border-color:var(--hicon-rim,#F4B63D);background:linear-gradient(135deg,var(--hicon-from,#8A4B2A) 0%,var(--hicon-to,#4A2A1A) 100%)}
}
.announce .help-link{color:var(--help-fg,#FFE6A8)}
.wish{color:var(--wish-fg,var(--brown))}
.wish[aria-pressed="true"]{color:var(--wish-on,var(--red))}
.socials a{color:var(--social-fg,#DCC9B8)}
.socials a:hover{color:var(--social-hover,#fff)}
.eyebrow{color:var(--eyebrow,var(--orange-cta))}
.eyebrow-caps .eyebrow{text-transform:uppercase;letter-spacing:.07em}
.lede{color:var(--lede,var(--muted))}
.head-italic h1,.head-italic h2,.head-italic h3{font-style:italic}
.site-header .main-nav a,.top-nav a{letter-spacing:var(--menu-ls,.02em)}
.menu-caps .main-nav a,.menu-caps .top-nav a{text-transform:uppercase}
.menu-capz .main-nav a,.menu-capz .top-nav a{text-transform:capitalize}
.badge-new{background:var(--bd-new-bg,var(--green));color:var(--bd-new-fg,#fff)}
.badge-best{background:var(--bd-best-bg,var(--gold));color:var(--bd-best-fg,var(--brown-deep))}
.badge-deal{background:var(--bd-deal-bg,var(--orange-cta));color:var(--bd-deal-fg,#fff)}
.badge-low{background:var(--bd-low-bg,#fff);color:var(--bd-low-fg,var(--red))}
.f-group summary{color:var(--fl-head,var(--brown-deep))}
.f-opt{color:var(--fl-text,var(--ink))}
.f-opt small{color:var(--fl-count,var(--muted))}
.f-opt input{accent-color:var(--fl-check,var(--brown))}
.bands button{background:var(--chip-bg,#fff);color:var(--chip-fg,var(--ink))}
.bands button[aria-pressed="true"]{background:var(--chip-on-bg,var(--brown));border-color:var(--chip-on-bg,var(--brown));color:var(--chip-on-fg,#fff)}
.ranges a{color:var(--range-fg,var(--brown))}
.ranges a[aria-current="page"]{background:var(--range-on-bg,var(--brown));color:var(--range-on-fg,var(--cream))}

/* ---------- Breadcrumbs (all pages): bold, every step highlighted ---------- */
.crumbs{align-items:center;gap:6px;font-size:14px}
.crumbs a{font-weight:700;color:var(--brown);background:#F6EADF;padding:5px 13px;border-radius:999px;text-decoration:none;transition:background .2s,color .2s}
.crumbs a:hover{background:var(--brown);color:#fff}
.crumbs span[aria-hidden]{font-weight:800;color:var(--brown-soft)}
.crumbs span[aria-current]{font-weight:800;color:#fff;background:linear-gradient(90deg,var(--crumb-from,#E3121B),var(--crumb-to,#FF7A00));padding:5px 13px;border-radius:999px;box-shadow:0 6px 14px -8px rgba(227,18,27,.8)}

/* text colours (admin settings > Text colours / Text styles); values after the comma = today's look */
.crumbs a{color:var(--crumb-link-fg,var(--brown));background:var(--crumb-link-bg,#F6EADF)}
.crumbs span[aria-current]{color:var(--crumb-cur-fg,#fff)}
.site-footer h3{color:var(--footer-head,#fff)}
.site-footer a:hover{color:var(--footer-hover,#fff)}
.card-serif .card-title{font-family:var(--serif)!important;font-weight:700!important}
/* ============ ALL BUTTONS, ALL PAGES (admin settings > Buttons / Page buttons / Text styles) ============
   These load last on every page, so they win over each page's own button styles. "body" makes them a
   little stronger than page rules. The value after each comma is today's look (used when nothing is saved). */
body .btn{min-height:var(--btn-md-h,48px);padding-top:0;padding-bottom:0;font-size:var(--btn-fs,15px);font-weight:var(--btn-fw,600);border-radius:var(--btn-radius,999px);transition:background .2s,color .2s,transform .2s,box-shadow .2s}
body .btn-lg{min-height:var(--btn-lg-h,54px)}
body .btn-sm{min-height:var(--btn-sm-h,38px);padding:0 16px;font-size:calc(var(--btn-fs,15px) - 1px)}
/* main buttons: Add to cart (product page), Shop the collection, Proceed to checkout, Place order, Sign in, Send message... */
body .btn-primary,body .sc-ctas .add{background:var(--btn-bg,var(--orange-cta));border-color:var(--btn-bg,var(--orange-cta));color:var(--btn-fg,#fff);box-shadow:var(--btn-shadow,none)}
body .btn-primary:hover,body .sc-ctas .add:hover{background:var(--btn-hover-bg,var(--orange-cta-hover));border-color:var(--btn-hover-bg,var(--orange-cta-hover));color:var(--btn-hover-fg,#fff)}
/* outline buttons: Buy now, Continue shopping, Clear all filters... */
body .btn-outline{border-color:var(--btn-out,var(--brown));color:var(--btn-out,var(--brown));box-shadow:var(--btn-shadow,none)}
body .btn-outline:hover{background:var(--btn-out-hover-bg,var(--brown));color:var(--btn-out-hover-fg,#fff);border-color:var(--btn-out-hover-bg,var(--brown))}
/* light outline buttons on dark areas: Explore our story, Track or cancel an order */
body .btn-ghost-light{border-color:var(--pbtn-gh-fg,rgba(255,247,237,.6));color:var(--pbtn-gh-fg,var(--cream));box-shadow:var(--btn-shadow,none)}
body .btn-ghost-light:hover{background:var(--pbtn-gh-hbg,var(--cream));border-color:var(--pbtn-gh-hbg,var(--cream));color:var(--pbtn-gh-hfg,var(--brown-deep))}
/* card "Add to cart" (all product cards + combo sets) */
body .add{background:var(--pbtn-add-bg,var(--brown-deep));color:var(--pbtn-add-fg,var(--cream));border-radius:var(--btn-radius,999px);box-shadow:var(--btn-shadow,none);transition:background .2s,color .2s,transform .2s,box-shadow .2s}
body .add:hover{background:var(--pbtn-add-hbg,var(--orange-cta));color:var(--pbtn-add-hfg,#fff)}
body .add.in-cart,body .add.in-cart:hover{background:var(--pbtn-in-bg,var(--green));color:var(--pbtn-in-fg,#fff)}
body .sc-ctas .add.in-cart{background:#fff;border-color:#fff;color:var(--green)}
body .add:disabled{background:var(--cream-2);color:var(--muted);box-shadow:none}
/* Quick view pill + slider arrows */
body .quick{background:var(--pbtn-q-bg,rgba(255,255,255,.95));color:var(--pbtn-q-fg,var(--brown))}
body .hs-btn{color:var(--pbtn-arrow-fg,#fff)}
/* "Shop the range" tabs on the home page use the chip colours */
body .tab{background:var(--chip-bg,#fff);color:var(--chip-fg,var(--ink))}
body .tab[aria-pressed="true"]{background:var(--chip-on-bg,var(--brown));border-color:var(--chip-on-bg,var(--brown));color:var(--chip-on-fg,#fff)}
/* pop on hover (not Quick view: it is positioned with its own movement) */
.btn-pop-lift :is(.btn,.add,.tab):hover:not(:disabled){transform:translateY(-3px)}
.btn-pop-grow :is(.btn,.add,.tab):hover:not(:disabled){transform:scale(1.06)}
.btn-pop-bounce :is(.btn,.add,.tab):hover:not(:disabled){animation:btnBounce .55s ease}
@keyframes btnBounce{0%{transform:translateY(0)}30%{transform:translateY(-6px)}55%{transform:translateY(0)}75%{transform:translateY(-3px)}100%{transform:translateY(0)}}
/* capitals */
.btn-caps :is(.btn,.add){text-transform:uppercase;letter-spacing:.05em}
/* sparkle (golden shine) on main buttons */
.fx-btn-sparkle :is(.btn-primary,.sc-ctas .add){position:relative;overflow:hidden;isolation:isolate}
.fx-btn-sparkle :is(.btn-primary,.sc-ctas .add)::after{content:"";position:absolute;top:0;bottom:0;left:-60%;width:40%;z-index:-1;pointer-events:none;
  background:linear-gradient(105deg,transparent,rgba(255,236,170,.65),transparent);transform:skewX(-20deg);animation:navShine 3.6s ease-in-out infinite}
/* space between big sections (admin settings > Page); was 88px */
.section{padding-top:var(--sec-space,56px);padding-bottom:var(--sec-space,56px)}
/* home page only: a small "fingerprint" gap between every section (admin settings > Page > Home page section gap) */
main:has(> #promoRibbon) > section:not(.hero-slider):not(.b2b){padding-top:var(--home-gap,12px);padding-bottom:var(--home-gap,12px)}
main:has(> #promoRibbon) > .promo-ribbon{padding-top:var(--home-gap,12px)}

/* ---------- Floating AI Agent chat button ---------- */
.a3-chat{position:fixed;right:22px;bottom:22px;z-index:85;display:flex;flex-direction:column;align-items:flex-end;gap:10px;pointer-events:none}
.a3-chat>*{pointer-events:auto}
.a3-chat-btn{position:relative;display:block;width:78px;height:78px;border-radius:50%;text-decoration:none;
  background:radial-gradient(circle at 50% 38%,#fff 0%,#FFF1E6 60%,#FFD9C2 100%);box-shadow:0 14px 30px -10px rgba(227,18,27,.7)}
.a3-chat-btn::before{content:"";position:absolute;inset:-5px;border-radius:50%;padding:4px;
  background:conic-gradient(from var(--spin,0deg),#8B00FF,#4B0082,#0066FF,#00B140,#FFD400,#FF7A00,#E3121B,#8B00FF);
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude;animation:spin 2.4s linear infinite}
.a3-chat-btn::after{content:"";position:absolute;inset:-5px;border-radius:50%;border:3px solid rgba(227,18,27,.55);animation:ping 2s cubic-bezier(0,0,.2,1) infinite}
@keyframes clickPulse{from{transform:scale(1)}to{transform:scale(1.12)}}
@keyframes ping{0%{transform:scale(1);opacity:.9}80%,100%{transform:scale(1.55);opacity:0}}
.a3-chat-bot{position:absolute;inset:6px 8px 8px;--bot:#E3121B;animation:redGlow 2s ease-in-out infinite,bob 2.6s ease-in-out infinite}
.a3-chat-badge{position:absolute;top:-2px;right:-2px;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:#E3121B;color:#fff;font:800 12px/22px var(--sans);text-align:center;box-shadow:0 0 0 3px #fff;animation:hotPulse 1.6s ease-in-out infinite}
.a3-chat-btn .sparkle.s1{top:-8px;right:auto;left:-4px}
.a3-chat-btn .sparkle.s2{bottom:-6px;right:-6px}
.a3-chat-btn .sparkle.s3{top:30%;left:auto;right:-12px}
.a3-chat-btn:hover .a3-chat-bot{animation-duration:1s,.8s}
.a3-chat-label{animation:clickPulse 1.4s ease-in-out infinite alternate;font:800 11px/1 var(--sans);letter-spacing:.08em;text-transform:uppercase;color:#fff;background:#E3121B;padding:5px 9px;border-radius:999px;box-shadow:0 6px 14px -6px rgba(227,18,27,.8);margin-right:7px}
/* speech bubble */
.a3-chat-bubble{position:relative;max-width:260px;padding:13px 34px 13px 15px;border-radius:18px 18px 4px 18px;background:#fff;color:var(--ink);font-size:14px;line-height:1.45;cursor:pointer;
  box-shadow:0 18px 40px -16px rgba(36,24,17,.55),0 0 0 2px #E3121B;transform-origin:100% 100%;animation:bubbleIn .45s cubic-bezier(.2,1.4,.4,1) both}
.a3-chat-bubble[hidden]{display:none}
.a3-chat-bubble strong{display:block;color:#E3121B;font-size:14.5px;margin-bottom:2px}
.a3-chat-bubble b{color:var(--brown-deep)}
.a3-chat-bubble .x{position:absolute;top:6px;right:6px;width:24px;height:24px;border:0;border-radius:50%;background:var(--cream);color:var(--muted);font-size:16px;line-height:1;cursor:pointer}
.a3-chat-bubble .x:hover{background:var(--cream-2);color:var(--ink)}
@keyframes bubbleIn{from{opacity:0;transform:scale(.4) translateY(10px)}to{opacity:1;transform:none}}
/* the flight: starts top-left, loops around the screen in VIBGYOR, then sits in the corner in RED */
.a3-chat.flying{z-index:150}
.a3-chat.flying .a3-chat-fly{animation:botFly 4.6s cubic-bezier(.45,.05,.4,1) both}
.a3-chat.flying .a3-chat-bot{animation:vibgyor 4.6s linear both,rainbowHalo .6s linear infinite}
.a3-chat.flying.js-path .a3-chat-fly{animation:none}                  /* the path from the AI Agent button is drawn by the script */
.a3-chat.flying.js-path .a3-chat-bot{animation:vibgyor 7.6s linear both,rainbowHalo .6s linear infinite}
.a3-chat.flying .a3-chat-badge{opacity:0}
.a3-chat.flying .a3-chat-label{font-size:13px;padding:7px 12px;background:linear-gradient(90deg,#E3121B,#FF7A00,#8B00FF);animation:clickPulse .6s ease-in-out infinite alternate}
.a3-chat.landing .a3-chat-fly{animation:botSit .6s cubic-bezier(.3,1.6,.5,1) both}
.a3-chat.popin .a3-chat-fly{animation:botPop .6s cubic-bezier(.3,1.6,.5,1) both}
.a3-chat-fly{display:flex;align-items:center;transform-origin:100% 100%}
@keyframes botFly{
  0%  {transform:translate(calc(-100vw + 140px),calc(-100vh + 220px)) scale(.5) rotate(-25deg);opacity:0}
  8%  {opacity:1}
  20% {transform:translate(-62vw,-72vh) scale(1.25) rotate(14deg)}
  36% {transform:translate(-22vw,-50vh) scale(1.05) rotate(-12deg)}
  52% {transform:translate(-58vw,-24vh) scale(1.35) rotate(340deg)}
  68% {transform:translate(-30vw,-62vh) scale(1.1) rotate(372deg)}
  84% {transform:translate(-9vw,-20vh) scale(1.15) rotate(352deg)}
  94% {transform:translate(0,-36px) scale(1.05) rotate(360deg)}
  100%{transform:translate(0,0) scale(1) rotate(360deg);opacity:1}}
@keyframes botSit{0%{transform:scale(1.2,.75)}45%{transform:scale(.9,1.12)}75%{transform:scale(1.05,.95)}100%{transform:none}}
@keyframes botPop{from{transform:scale(0) rotate(-90deg);opacity:0}to{transform:none;opacity:1}}
@media (max-width:680px){
  .a3-chat{right:14px;bottom:14px}
  .a3-chat-btn{width:64px;height:64px}
  .a3-chat-bubble{max-width:210px;font-size:13px;padding:10px 30px 10px 12px}
  .a3-chat:not(.flying) .a3-chat-label{display:none}
}
@media (prefers-reduced-motion:reduce){ .a3-chat-bubble{animation:none} }
.a3-chat-bubble{color:var(--bubble-fg,var(--ink));box-shadow:0 18px 40px -16px rgba(36,24,17,.55),0 0 0 2px var(--bubble-title,#E3121B)}
.a3-chat-bubble strong{color:var(--bubble-title,#E3121B)}
.a3-chat-label{background:var(--bot-tag-bg,#E3121B);color:var(--bot-tag-fg,#fff)}
.a3-chat.flying .a3-chat-label{color:var(--bot-tag-fg,#fff)}
</style>

<footer class="site-footer">
  <div class="wrap foot-grid">
    <div class="foot-brand">
      <a class="brand" href="<?= h3_link('home') ?>" aria-label="Araku Tribe Coffee home">
        <?php if (!empty($H3_LOGO_FOOTER)): ?><img src="<?= h3_e($H3_LOGO_FOOTER) ?>" alt="Araku Tribe Coffee" class="foot-logo">
        <?php else: ?><svg class="brand-mark ico"><use href="#i-bean"/></svg><span class="brand-name">Araku Tribe</span><?php endif; ?>
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
    <div><h3>Shop</h3><ul>
      <li><a href="<?= h3_link('bag') ?>">Coffee bags</a></li>
      <li><a href="<?= h3_link('cup') ?>">Coffee cups</a></li>
      <li><a href="<?= h3_link('mug') ?>">Coffee mugs</a></li>
      <li><a href="<?= h3_link('tshirt') ?>">T-shirts</a></li>
      <li><a href="<?= h3_link('hotdeals') ?>" style="color:#FFB36B;font-weight:700">🔥 Hot deals</a></li>
      <li><a href="<?= h3_link('offers') ?>">Offers</a></li>
      <li><a href="<?= h3_link('combos') ?>">Gifts &amp; combos</a></li>
    </ul></div>
    <div><h3>Help</h3><ul>
      <li><a href="<?= h3_link('orders') ?>">Track my order</a></li>
      <li><a href="<?= h3_link('cart') ?>">My cart</a></li>
      <li><a href="<?= h3_link('wishlist') ?>">My wishlist</a></li>
      <li><a href="<?= h3_link('contact') ?>">Contact us</a></li>
      <li><a href="<?= h3_link('agent') ?>">AI Agent: express checkout</a></li>
    </ul></div>
    <div><h3>Araku Tribe</h3><ul>
      <li><a href="<?= h3_link('home') ?>">Home</a></li>
      <li><a href="<?= h3_link('about') ?>">About</a></li>
      <li><a href="<?= h3_link('contact') ?>">Contact us</a></li>
      <li><a href="<?= h3_link('franchise') ?>">Franchise</a></li>
      <li><a href="<?= h3_link('terms') ?>">Terms &amp; conditions</a></li>
      <li><a href="<?= h3_link('privacy') ?>">Privacy policy</a></li>
    </ul></div>
  </div>
  <div class="foot-base"><div class="wrap">
    <span class="copy">© <?= date('Y') ?> Araku Tribe. All rights reserved.</span>
    <span class="powered">Powered by
      <a class="dc-link" href="https://www.facebook.com/profile.php?id=61582627243027" target="_blank" rel="noopener" title="DC (opens in a new tab)">DC
        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg><span class="sr-only">(opens in a new tab)</span></a>
    </span>
    <span aria-hidden="true"></span>
  </div></div>
</footer>

<!-- Cart drawer -->
<div class="scrim" id="scrim"></div>
<aside class="drawer" id="cartDrawer" aria-labelledby="cartTitle" role="dialog" aria-modal="true">
  <div class="drawer-head"><h2 id="cartTitle">Your cart</h2><button class="icon-btn" data-close aria-label="Close cart"><svg class="ico"><use href="#i-x"/></svg></button></div>
  <div class="drawer-body" id="cartBody"></div>
  <div class="drawer-foot">
    <p>Prices, coupons and delivery are shown on the cart page.</p>
    <a class="btn btn-primary" href="<?= h3_link('cart') ?>">Go to checkout</a>
    <button class="btn btn-outline" data-close>Keep shopping</button>
  </div>
</aside>

<!-- Phone menu drawer (grouped: Shop, Collections, Story, Support) -->
<aside class="drawer left" id="menuDrawer" aria-label="Menu" role="dialog" aria-modal="true">
  <div class="drawer-head"><h2>Menu</h2><button class="icon-btn" data-close aria-label="Close menu"><svg class="ico"><use href="#i-x"/></svg></button></div>
  <div class="drawer-body">
    <a class="mnav-agent" href="<?= h3_link('agent') ?>"><span class="orb" style="background:radial-gradient(circle at 35% 30%,#FF6B6B 0%,#E3121B 55%,#8E0A10 100%)"><svg class="ico" style="stroke:#fff"><use href="#i-robot"/></svg></span><span><strong>AI Agent</strong><small>Order in one chat</small></span></a>
    <a class="mnav-agent mnav-hot" href="<?= h3_link('hotdeals') ?>" style="background:linear-gradient(110deg,#E3121B,#FF4D00 60%,#FF9500)"><span class="orb" style="background:rgba(255,255,255,.2)"><svg class="ico" style="fill:#FFE066;stroke:#fff"><use href="#i-flame"/></svg></span><span><strong>HOT DEALS</strong><small style="color:#FFE9D6">All offers first, then everything</small></span></a>
    <div class="mnav-group"><h3>Shop</h3>
      <a href="<?= h3_link('shop') ?>" data-close>Shop all</a>
      <a href="<?= h3_link('bag') ?>">Coffee bags</a>
      <a href="<?= h3_link('cup') ?>">Coffee cups</a>
      <a href="<?= h3_link('mug') ?>">Coffee mugs</a>
      <a href="<?= h3_link('tshirt') ?>">T-shirts</a>
    </div>
    <div class="mnav-group"><h3>Collections</h3>
      <a href="<?= h3_link('new') ?>" data-close>New arrivals</a>
      <a href="<?= h3_link('offers') ?>" data-close>Offers</a>
      <a href="<?= h3_link('combos') ?>" data-close>Gifts &amp; combos</a>
    </div>
    <div class="mnav-group"><h3>Story</h3>
      <a href="<?= h3_link('about') ?>">About</a>
      <a href="<?= h3_link('franchise') ?>">Franchise for cafés</a>
    </div>
    <div class="mnav-group"><h3>Support</h3>
      <a href="<?= h3_link('orders') ?>">Track my order</a>
      <a href="<?= h3_link('contact') ?>">Contact us</a>
      <a href="#" data-contact>Help &amp; quick message</a>
    </div>
  </div>
</aside>

<!-- Help & contact panel (opened from "Help & contact" in the header) -->
<aside class="drawer" id="contactDrawer" aria-labelledby="qcTitle" role="dialog" aria-modal="true">
  <div class="drawer-head"><h2 id="qcTitle">Help &amp; contact</h2><button class="icon-btn" data-close aria-label="Close"><svg class="ico"><use href="#i-x"/></svg></button></div>
  <div class="drawer-body">
    <p class="qc-lead">We usually reply within 24 hours. For anything urgent, call us.</p>
    <div class="qc-ways">
      <a href="tel:+917893836529"><svg class="ico"><use href="#i-phone"/></svg><span>Call</span></a>
      <a href="mailto:info@arakutribe.com"><svg class="ico"><use href="#i-mail"/></svg><span>Email</span></a>
      <a href="<?= h3_link('agent') ?>" class="ai"><svg class="ico"><use href="#i-robot"/></svg><span>AI Agent</span></a>
    </div>
    <a class="qc-track" href="<?= h3_link('orders') ?>">Where's my order? <span>Track it</span></a>
    <form id="qcForm" class="qc-form" novalidate>
      <input type="hidden" name="csrf" value="<?= h3_e($h3Csrf ?? '') ?>">
      <div class="trap" aria-hidden="true" style="position:absolute;left:-9999px"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <label for="qcTopic">What's it about?</label>
      <select id="qcTopic" name="topic"><option value="general">General question</option><option value="order">Help with an order</option><option value="bulk">Bulk or café orders</option><option value="franchise">Franchise</option><option value="feedback">Feedback</option></select>
      <label for="qcName">Your name</label><input id="qcName" name="name" autocomplete="name" maxlength="80" value="<?= h3_e(!empty($_SESSION['user_id']) ? ($_SESSION['user_name'] ?? '') : '') ?>">
      <label for="qcEmail">Email</label><input id="qcEmail" name="email" type="email" autocomplete="email" value="<?= h3_e(!empty($_SESSION['user_id']) ? ($_SESSION['user_email'] ?? '') : '') ?>">
      <label for="qcMsg">Message</label><textarea id="qcMsg" name="message" rows="4" maxlength="3000" placeholder="How can we help?"></textarea>
      <button type="submit" class="btn btn-primary">Send message</button>
    </form>
    <p class="qc-out" id="qcOut" role="status" hidden></p>
    <button type="button" class="btn btn-outline" id="qcAgain" hidden style="width:100%">Send another message</button>
    <a class="qc-full" href="<?= h3_link('contact') ?>">Open the full contact page</a>
  </div>
</aside>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<?php if ($h3ShowBot): ?>
<!-- Floating AI Agent chat button -->
<div class="a3-chat" id="a3Chat">
  <div class="a3-chat-bubble" id="a3ChatBubble" role="status" hidden>
    <button type="button" class="x" id="a3ChatX" aria-label="Hide message">×</button>
    <strong>Hi! I'm your Araku AI Agent 👋</strong>
    <span id="a3ChatMsg"></span>
  </div>
  <div class="a3-chat-fly">
    <span class="a3-chat-label" aria-hidden="true"><?= h3_e($h3BotLabel) ?></span>
    <a class="a3-chat-btn" href="<?= h3_link('agent') ?>" aria-label="Chat with the Araku AI Agent">
      <span class="a3-chat-bot"><?= h3_bot_svg() ?></span>
      <span class="a3-chat-badge" aria-hidden="true">1</span>
      <i class="sparkle s1"></i><i class="sparkle s2"></i><i class="sparkle s3"></i>
    </a>
  </div>
</div>
<script>
(function () {
  'use strict';
  const root = document.getElementById('a3Chat'); if (!root) return;
  const MSGS = <?= json_encode(array_values($H3_BOT_MESSAGES), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  const FLY = <?= json_encode($h3BotFly) ?>, REFLY = <?= (int)$H3_BOT_REFLY_MIN ?> * 60000;
  const bubble = document.getElementById('a3ChatBubble'), link = root.querySelector('.a3-chat-btn');
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const ss = (k, v) => { try { if (v === undefined) return sessionStorage.getItem(k); sessionStorage.setItem(k, v); } catch (e) { return null; } };
  document.getElementById('a3ChatMsg').innerHTML = MSGS[Math.floor(Math.random() * MSGS.length)] || '';

  function showBubble() {
    if (<?= $h3BotBubble ? 'false' : 'true' ?> || ss('a3_bubble_off')) return;   // bubble switched off on the admin page
    bubble.hidden = false;
    clearTimeout(showBubble.t); showBubble.t = setTimeout(() => { bubble.hidden = true; }, matchMedia('(max-width: 680px)').matches ? 6000 : 12000);   // shorter on phones
  }
  bubble.addEventListener('click', e => { if (!e.target.closest('.x')) location.href = link.href; });
  document.getElementById('a3ChatX').addEventListener('click', e => { e.stopPropagation(); bubble.hidden = true; ss('a3_bubble_off', '1'); });
  link.addEventListener('mouseenter', () => { if (bubble.hidden) { bubble.hidden = false; } });

  if (reduce || !FLY) { if (!reduce) root.classList.add('popin'); setTimeout(showBubble, 700); return; }
  let busy = false;
  // the flight: out of the AI AGENT button in the header, right to left round all four corners,
  // back to the button, a spin, then down to its seat in the corner
  const flyer = root.querySelector('.a3-chat-fly'), DUR = 7600;
  function pathFrames() {
    const btn = document.querySelector('.agent-btn');
    const b = btn && btn.getBoundingClientRect(); if (!b || !b.width) return null;
    const me = link.getBoundingClientRect(), mx = me.left + me.width / 2, my = me.top + me.height / 2;
    const W = innerWidth, H = innerHeight, m = Math.min(90, W * .12);
    const at = (x, y, sc, rot) => ({ transform: `translate(${x - mx}px,${y - my}px) scale(${sc}) rotate(${rot}deg)` });
    const ax = b.left + b.width / 2, ay = b.top + b.height / 2;
    return [
      { ...at(ax, ay, .3, 0), opacity: 0, offset: 0 },
      { ...at(ax, ay, .8, 0), opacity: 1, offset: .05 },
      { ...at(m, m + 40, 1.15, -20), offset: .2 },            // top-left
      { ...at(m, H - m, 1.25, -10), offset: .34 },            // bottom-left
      { ...at(W - m, H - m - 30, 1.15, 10), offset: .5 },     // bottom-right
      { ...at(W - m, m + 40, 1.2, 0), offset: .64 },          // top-right
      { ...at(ax, ay, 1, 0), offset: .74 },                   // back at the AI AGENT button
      { ...at(ax, ay, 1.15, 360), offset: .84 },              // spin there
      { transform: 'translate(0,0) scale(1) rotate(360deg)', opacity: 1, offset: 1 },   // its seat
    ];
  }
  function flyAround() {                                 // fly around the screen, then sit in the corner
    if (busy || document.hidden) return;
    busy = true; bubble.hidden = true;
    root.classList.remove('landing', 'popin', 'js-path'); void root.offsetWidth;   // restart the animation
    const frames = flyer.animate ? pathFrames() : null;
    let dur = 4650;
    if (frames) { root.classList.add('js-path'); dur = DUR; flyer.style.transformOrigin = '50% 50%'; flyer.animate(frames, { duration: DUR, easing: 'ease-in-out' }); }
    root.classList.add('flying');
    setTimeout(() => {
      root.classList.remove('flying', 'js-path'); flyer.style.transformOrigin = ''; root.classList.add('landing');
      setTimeout(() => { root.classList.remove('landing'); busy = false; showBubble(); }, 650);
    }, dur);
  }
  flyAround();                                           // every page load and refresh
  if (REFLY > 0) setInterval(flyAround, REFLY);          // and again every few minutes on the same page
})();
</script>
<?php endif; ?>

</body>
</html>