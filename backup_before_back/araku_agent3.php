<?php
error_reporting(E_ALL);

// Force errors to be printed to the screen
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
/* =====================================================================
   ARAKU TRIBE - araku_agent3.php  (AI Agent: express checkout)
   The whole chat agent now lives on this page (it used to be inside the
   old footer.php). Same conversation flows, same backend:
     chatbot_api.php actions: init, get_items, add_to_cart,
     check_email_and_send_otp, verify_guest_otp, get_saved_addresses,
     get_cart_summary, stage_for_payment_page, place_order,
     lookup_order, send_cancel_otp, cancel_order, submit_support_request
   Changes: new design; links go to the "3" pages; after "Proceed to
   payment" it opens dummy_payment3.php; debug messages are no longer
   shown to customers. Uses header3.php + footer3.php.
   PRICES: shown in the same currency as the rest of the site (the visitor's
   location, or the one picked in the header's currency switcher). The page
   works this out on the server (currency_helper.php) so even the very first
   price in the chat is already right.
   ===================================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------- SETTINGS ---------- */
$AGENT_API       = 'chatbot_api.php';
$AGENT_PAYMENT   = 'dummy_payment3.php';   // the payment page the agent hands over to
$AGENT_MASK      = false;                   // true = show prices as ### in the chat (old behaviour)
$AGENT_PAGES     = ['cup' => 'coffee_cup3.php', 'mug' => 'coffee_mug3.php', 'bag' => 'coffee_bag3.php', 'tshirt' => 'coffee_tshirt3.php'];

/* ---------- CURRENCY: same helper and same result as every other page ---------- */
$AGENT_CURRENCY = ['code' => 'USD', 'symbol' => '$', 'rate' => 1.0];   // only used if the database cannot be reached
include 'db1.php';
mysqli_report(MYSQLI_REPORT_OFF);
$agentDb = @new mysqli($host, $user, $pass, $db);
if (!$agentDb->connect_error) {
    $agentDb->set_charset('utf8mb4');
    require_once 'currency_helper.php';
    $agentCode = resolve_display_currency($agentDb);
    if (isset($CURRENCY_INFO[$agentCode])) {
        $AGENT_CURRENCY = ['code' => $agentCode, 'symbol' => $CURRENCY_INFO[$agentCode]['symbol'], 'rate' => (float)$CURRENCY_INFO[$agentCode]['rate']];
    }
    $agentDb->close();
}

$PAGE_TITLE       = 'AI Agent: express checkout - Araku Tribe';
$PAGE_DESCRIPTION = 'Order Araku Tribe coffee, cups, mugs and tees in one chat, track an order or get help.';
$ACTIVE_NAV       = 'agent';   // highlights the AI AGENT button in the header
$H3_HIDE_BOT      = true;      // no floating chat button here: the chat is already on this page
include __DIR__ . '/header3.php';
?>
<style>
/* ============ araku_agent3.php ============ */
.ag{background:radial-gradient(90% 70% at 100% 0%,rgba(201,80,26,.10),transparent 60%),var(--cream);padding:22px 0 64px}
.crumbs{display:flex;gap:8px;margin:0 0 14px;font-size:13.5px;color:var(--muted)}
.crumbs a{color:var(--muted);text-decoration:none}
.crumbs a:hover{color:var(--brown)}
.crumbs span[aria-current]{color:var(--brown);font-weight:600}
.ag-grid{display:grid;grid-template-columns:330px minmax(0,1fr);gap:24px;align-items:start}
.ag-side{position:sticky;top:96px}
.ag-side h1{font-size:clamp(1.8rem,3vw,2.4rem);line-height:1.08}
.ag-side .lede{margin-top:10px;font-size:15px}
.ag-badge{display:inline-flex;align-items:center;gap:10px;margin:0 0 12px;padding:6px 14px 6px 6px;border-radius:999px;background:var(--brown-deep);color:#fff;font-weight:700;font-size:14px}
.ag-badge .orb{width:28px;height:28px}
.ag-acts{display:grid;gap:8px;margin-top:20px}
.ag-acts button{display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;border-radius:16px;border:1px solid var(--line);background:#fff;text-align:left;font-weight:700;font-size:14.5px;color:var(--brown-deep);transition:border-color .2s,transform .2s}
.ag-acts button:hover{border-color:var(--brown-soft);transform:translateY(-1px)}
.ag-acts button span{flex:none;width:34px;height:34px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:var(--cream);color:var(--brown)}
.ag-acts button span svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.ag-acts button small{display:block;font-weight:500;font-size:12.5px;color:var(--muted)}
.ag-note{margin:18px 0 0;font-size:13px;color:var(--muted)}
.ag-note a{color:var(--brown);font-weight:600}

/* chat panel */
.chat{display:flex;flex-direction:column;height:calc(100vh - 150px);min-height:560px;max-height:860px;border-radius:26px;background:#fff;border:1px solid var(--line);box-shadow:0 30px 70px -44px rgba(59,35,23,.7);overflow:hidden}
.chat-head{display:flex;align-items:center;gap:12px;padding:14px 18px;color:#fff;background:radial-gradient(120% 140% at 100% 0%,#8A4F2B 0%,rgba(138,79,43,0) 60%),var(--brown-deep)}
.chat-head .orb{width:40px;height:40px}
.chat-head strong{display:block;font-size:16px}
.chat-head small{display:flex;align-items:center;gap:6px;font-size:12.5px;color:#E6D5C4}
.chat-head small::before{content:"";width:8px;height:8px;border-radius:50%;background:#5CC98A}
.chat-head .hb{margin-left:auto;display:flex;gap:6px}
.chat-head .hb button{border:1px solid rgba(255,247,237,.3);background:rgba(255,247,237,.08);color:#fff;border-radius:999px;padding:7px 12px;font-weight:600;font-size:13px}
.chat-head .hb button:hover{background:rgba(255,247,237,.18)}
#araku-bot-messages{flex:1;overflow-y:auto;padding:20px 18px 12px;background:linear-gradient(180deg,#FFFBF6,#FFF7ED);scroll-behavior:smooth}
#araku-bot-messages::-webkit-scrollbar{width:6px}
#araku-bot-messages::-webkit-scrollbar-thumb{background:var(--cream-2);border-radius:6px}
.araku-bot-row{display:flex;margin-bottom:12px;animation:rowIn .25s ease-out}
@keyframes rowIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.araku-bot-row.usr{justify-content:flex-end}
.araku-bot-msg{max-width:80%;padding:12px 15px;border-radius:6px 18px 18px 18px;background:#fff;color:var(--ink);font-size:15px;line-height:1.55;box-shadow:0 1px 2px rgba(59,35,23,.06),0 8px 20px -16px rgba(59,35,23,.5)}
.araku-bot-msg.user-msg{border-radius:18px 6px 18px 18px;background:var(--brown);color:#fff;font-weight:600}
.araku-bot-msg strong{color:var(--brown-deep)}
.araku-bot-msg.user-msg strong{color:#fff}
.araku-bot-msg a{color:var(--orange-cta);font-weight:700}
.araku-bot-msg img{border-radius:12px;background:#F3E9DD}
.araku-bot-typing{display:flex;gap:5px;padding:12px 15px;border-radius:6px 18px 18px 18px;background:#fff;box-shadow:0 8px 20px -16px rgba(59,35,23,.5)}
.araku-bot-typing span{width:7px;height:7px;border-radius:50%;background:var(--brown-soft);animation:dot 1.2s infinite}
.araku-bot-typing span:nth-child(2){animation-delay:.2s}.araku-bot-typing span:nth-child(3){animation-delay:.4s}
@keyframes dot{0%,60%,100%{transform:none;opacity:.5}30%{transform:translateY(-5px);opacity:1}}
.araku-bot-chips{display:flex;flex-wrap:wrap;gap:8px;max-width:92%}
.araku-bot-chip{border:1.5px solid var(--line);background:#fff;color:var(--brown-deep);border-radius:999px;padding:9px 15px;font:inherit;font-weight:700;font-size:13.5px;cursor:pointer;transition:all .15s}
.araku-bot-chip:hover:not(:disabled){border-color:var(--brown);color:var(--brown);transform:translateY(-1px)}
.araku-bot-chip.primary{background:var(--orange-cta);border-color:var(--orange-cta);color:#fff}
.araku-bot-chip.primary:hover:not(:disabled){background:#B34515;color:#fff}
.araku-bot-chip.back{background:var(--cream)}
.araku-bot-chip.home{background:var(--green-soft);border-color:transparent;color:var(--green)}
.araku-bot-chip:disabled{opacity:.45;cursor:default;transform:none}
.araku-bot-starters{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;width:100%}
.araku-bot-starter{display:flex;align-items:flex-start;gap:10px;padding:14px;border:1.5px solid transparent;border-radius:18px;background:#fff;text-align:left;font:inherit;cursor:pointer;box-shadow:0 8px 20px -16px rgba(59,35,23,.6);transition:all .15s}
.araku-bot-starter:hover:not(:disabled){border-color:var(--brown-soft);transform:translateY(-2px)}
.araku-bot-starter:disabled{opacity:.5;cursor:default;transform:none}
.araku-bot-starter .ic{flex:none;width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:var(--cream);font-size:19px}
.araku-bot-starter strong{display:block;color:var(--brown-deep);font-size:14.5px;line-height:1.3}
.araku-bot-starter small{display:block;margin-top:2px;color:var(--muted);font-size:12.5px;line-height:1.35}
@media (max-width:760px){ .araku-bot-starters{grid-template-columns:1fr 1fr} }
@media (max-width:400px){ .araku-bot-starter small{display:none} }
.araku-bot-res-scroll{display:flex;gap:10px;overflow-x:auto;padding:2px 2px 8px;scroll-snap-type:x proximity;max-width:100%}
.araku-bot-res-scroll::-webkit-scrollbar{height:6px}
.araku-bot-res-scroll::-webkit-scrollbar-thumb{background:var(--cream-2);border-radius:6px}
.araku-bot-card{flex:0 0 150px;display:flex;flex-direction:column;text-align:left;padding:8px;border:1.5px solid transparent;border-radius:16px;background:#fff;cursor:pointer;scroll-snap-align:start;box-shadow:0 8px 20px -16px rgba(59,35,23,.6);transition:all .15s;font:inherit}
.araku-bot-card:hover:not(:disabled){border-color:var(--brown-soft);transform:translateY(-2px)}
.araku-bot-card:disabled{opacity:.5;cursor:default}
.araku-bot-card img{width:100%;height:110px;object-fit:contain;padding:8%;border-radius:12px;background:#F3E9DD;mix-blend-mode:multiply;margin-bottom:8px}
.araku-bot-card-name{font-family:var(--serif);font-size:.95rem;line-height:1.25;color:var(--brown-deep);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.araku-bot-card-sub{font-size:12px;color:var(--muted);margin-top:3px}
.araku-bot-card-price{margin-top:6px;font-weight:700;font-size:14.5px;color:var(--ink)}
.araku-bot-stepper-wrap{display:flex;flex-direction:column;align-items:center;gap:10px;padding:14px 16px;border-radius:18px;background:#fff;box-shadow:0 8px 20px -16px rgba(59,35,23,.6)}
.araku-bot-stepper{display:flex;align-items:center;gap:6px;border:1.5px solid var(--line);border-radius:999px}
.araku-bot-step-btn{width:42px;height:42px;border:0;border-radius:999px;background:none;font-size:20px;color:var(--brown);cursor:pointer}
.araku-bot-step-btn:hover:not(:disabled){background:var(--cream)}
.araku-bot-step-btn:disabled{color:var(--line);cursor:default}
.araku-bot-step-val{min-width:70px;text-align:center;font-weight:700;font-size:16px}
.araku-bot-step-confirm{width:100%}
.araku-bot-textinput-wrap{display:flex;gap:8px;width:100%;max-width:520px}
.araku-bot-textinput{flex:1;min-width:0;height:46px;border:1.5px solid var(--line);border-radius:999px;padding:0 16px;background:#fff;font:inherit;font-size:15px;color:var(--ink);outline:none}
.araku-bot-textinput:focus{border-color:var(--brown-soft)}
.araku-bot-textinput:disabled{background:var(--cream)}
.araku-bot-address-form{width:100%;max-width:520px;display:grid;gap:10px;padding:16px;border-radius:18px;background:#fff;box-shadow:0 8px 20px -16px rgba(59,35,23,.6)}
.araku-bot-field{display:grid;gap:4px}
.araku-bot-field label{font-weight:600;font-size:13px;color:var(--brown-deep)}
.araku-bot-field .araku-bot-textinput{border-radius:12px;height:44px}
.araku-bot-address-error{display:none;color:#9B2626;font-weight:600;font-size:13px}
.araku-bot-address-save{justify-self:start}
.araku-bot-spin{display:inline-block;width:14px;height:14px;border:2.5px solid var(--cream-2);border-top-color:var(--brown);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle}
.chat-foot{display:flex;align-items:center;gap:10px;padding:10px 16px;border-top:1px solid var(--line);font-size:12.5px;color:var(--muted);background:#fff}
.chat-foot a{color:var(--brown);font-weight:600}
@media (max-width:1000px){
  .ag-grid{grid-template-columns:1fr}
  .ag-side{position:static}
  .ag-side .lede{display:none}
  .ag-acts{grid-template-columns:repeat(2,1fr)}
  .ag-acts button small{display:none}
  .chat{height:calc(100vh - 120px);min-height:520px}
}
@media (max-width:520px){ .araku-bot-msg{max-width:90%} .chat-head .hb button:first-child{display:none} .chat{border-radius:20px} }
@media (prefers-reduced-motion:reduce){ .araku-bot-row{animation:none} }

/* ============ BIG FLASHY ROBOT + MESSAGE (side panel) ============
   On arrival the robot flies around the page cycling VIBGYOR colours,
   then sits next to its message and glows RED. */
.ag-hero{position:relative;display:flex;align-items:flex-end;gap:12px;margin:0 0 16px}
.ag-hero-bot{position:relative;flex:none;width:132px;height:132px;--bot:#E3121B;z-index:5}
.ag-hero-bot .ring{position:absolute;inset:-8px;border-radius:50%;padding:5px;
  background:conic-gradient(from var(--spin,0deg),#8B00FF,#4B0082,#0066FF,#00B140,#FFD400,#FF7A00,#E3121B,#8B00FF);
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude;animation:spin 2.6s linear infinite}
.ag-hero-bot .disc{position:absolute;inset:0;border-radius:50%;background:radial-gradient(circle at 50% 38%,#fff 0%,#FFF1E6 60%,#FFD2B8 100%);box-shadow:0 18px 40px -14px rgba(227,18,27,.75)}
.ag-hero-bot .fig{position:absolute;inset:10px 12px 12px;animation:redGlow 2s ease-in-out infinite,bob 2.8s ease-in-out infinite}
.ag-hero-bot .sparkle{width:14px;height:14px}
.ag-hero-bot .sparkle.s1{top:-6px;left:4px;right:auto}
.ag-hero-bot .sparkle.s2{bottom:4px;right:-8px}
.ag-hero-bot .sparkle.s3{top:26%;left:auto;right:-14px}
.ag-say{position:relative;flex:1;min-width:0;padding:12px 14px;border-radius:18px 18px 18px 4px;background:#fff;font-size:14.5px;line-height:1.45;color:var(--ink);
  box-shadow:0 0 0 2px #E3121B,0 16px 34px -18px rgba(36,24,17,.6);animation:bubbleIn .5s cubic-bezier(.2,1.4,.4,1) both;transform-origin:0 100%}
.ag-say strong{display:block;font-size:16px;font-weight:800;letter-spacing:.02em;
  background:linear-gradient(90deg,#E3121B,#FF7A00,#8B00FF,#E3121B);background-size:200% auto;-webkit-background-clip:text;background-clip:text;color:transparent;animation:shine 3s linear infinite}
@keyframes bubbleIn{from{opacity:0;transform:scale(.4)}to{opacity:1;transform:none}}
.ag-hero.flying .ag-hero-bot{animation:agFly 4.4s cubic-bezier(.45,.05,.4,1) both;z-index:150}
.ag-hero.flying .ag-hero-bot .fig{animation:vibgyor 4.4s linear both,rainbowHalo .6s linear infinite}
.ag-hero.flying .ag-say{visibility:hidden;animation:none}
.ag-hero.landing .ag-hero-bot{animation:agSit .6s cubic-bezier(.3,1.6,.5,1) both}
@keyframes agFly{
  0%  {transform:translate(70vw,50vh) scale(.4) rotate(30deg);opacity:0}
  8%  {opacity:1}
  22% {transform:translate(55vw,8vh) scale(1.3) rotate(-15deg)}
  40% {transform:translate(30vw,45vh) scale(1.1) rotate(15deg)}
  58% {transform:translate(48vw,20vh) scale(1.4) rotate(-345deg)}
  76% {transform:translate(14vw,30vh) scale(1.15) rotate(-370deg)}
  92% {transform:translate(0,-30px) scale(1.05) rotate(-360deg)}
  100%{transform:none;opacity:1}}
@keyframes agSit{0%{transform:scale(1.2,.75)}45%{transform:scale(.9,1.12)}75%{transform:scale(1.05,.95)}100%{transform:none}}
/* robot face beside every bot message */
.araku-bot-row.has-av{align-items:flex-end;gap:8px}
.araku-bot-av{flex:none;position:relative;width:38px;height:38px;border-radius:50%;background:radial-gradient(circle at 50% 38%,#fff 0%,#FFE6D6 100%);box-shadow:0 0 0 2px #E3121B,0 6px 14px -6px rgba(227,18,27,.8)}
.araku-bot-av svg{position:absolute;inset:4px 5px 5px;width:auto;height:auto;--bot:#E3121B}
.araku-bot-row.first .araku-bot-msg{font-size:16px;box-shadow:0 0 0 2px #E3121B,0 14px 30px -16px rgba(227,18,27,.6)}
.araku-bot-row.first .araku-bot-av{width:52px;height:52px}
.chat-head .head-bot{position:relative;flex:none;width:44px;height:44px;border-radius:50%;background:radial-gradient(circle at 50% 38%,#fff 0%,#FFE6D6 100%);box-shadow:0 0 0 2px #FFD400,0 0 14px rgba(227,18,27,.7)}
.chat-head .head-bot svg{position:absolute;inset:4px 5px 5px;width:auto;height:auto;--bot:#E3121B;animation:bob 2.6s ease-in-out infinite}
.araku-bot-spin{animation:agSpin .7s linear infinite!important}
@keyframes agSpin{to{transform:rotate(360deg)}}
@media (max-width:1000px){ .ag-hero-bot{width:104px;height:104px} }
/* ============ BOLD COLOURFUL CHAT (matches the robot: red + VIBGYOR) ============ */
:root{--c1:#E3121B;--c2:#FF7A00;--c3:#00A63E;--c4:#0066FF;--c5:#8B00FF}
.chat{border:0;box-shadow:0 0 0 3px transparent,0 30px 70px -40px rgba(139,0,255,.55);
  background:linear-gradient(#fff,#fff) padding-box,linear-gradient(135deg,var(--c1),var(--c2),#FFD400,var(--c3),var(--c4),var(--c5)) border-box;border:3px solid transparent}
.chat ::selection{background:#FFD400;color:#1C1033}
.chat-head{background:linear-gradient(110deg,#C40F17 0%,#E3121B 30%,#8B00FF 100%)}
.chat-head small{color:#FFE6F0}
.chat-head .hb button{border-color:rgba(255,255,255,.55);background:rgba(255,255,255,.14)}
.chat-head .hb button:hover{background:#FFD400;color:#1C1033;border-color:#FFD400}
#araku-bot-messages{background:radial-gradient(100% 60% at 0% 0%,#FFF0F3 0%,rgba(255,240,243,0) 60%),radial-gradient(90% 60% at 100% 100%,#F1EBFF 0%,rgba(241,235,255,0) 60%),#FFFBF7}
.araku-bot-msg{border-left:4px solid var(--c1)}
.araku-bot-msg:not(.user-msg){background:linear-gradient(135deg,#3B2317 0%,#5A3422 55%,#7A4A2E 100%);color:#FFF4E8;box-shadow:0 12px 24px -14px rgba(59,35,23,.9)}
.araku-bot-msg:not(.user-msg) strong{color:#FFD400}
.araku-bot-msg:not(.user-msg) a{color:#FFB36B}
.araku-bot-msg:not(.user-msg) small,.araku-bot-msg:not(.user-msg) .muted{color:#E8D2BD}
.araku-bot-msg:not(.user-msg) img{background:#FFF4E8}
.araku-bot-row.has-av:nth-of-type(4n+2) .araku-bot-msg{border-left-color:var(--c5)}
.araku-bot-row.has-av:nth-of-type(4n+3) .araku-bot-msg{border-left-color:var(--c4)}
.araku-bot-row.has-av:nth-of-type(4n) .araku-bot-msg{border-left-color:var(--c2)}

.araku-bot-msg a{color:var(--c5)}
.araku-bot-msg.user-msg{border-left:0;background:linear-gradient(120deg,var(--c1),var(--c2));color:#fff;box-shadow:0 10px 22px -12px rgba(227,18,27,.8)}
.araku-bot-typing span{background:var(--c1)}
.araku-bot-typing span:nth-child(2){background:var(--c2)}.araku-bot-typing span:nth-child(3){background:var(--c5)}
/* option chips: each one its own bold colour, filled on hover / tap */
.araku-bot-chip{--k:var(--c1);border:2px solid var(--k);color:var(--k);background:#fff;font-weight:800}
.araku-bot-chip:nth-child(5n+2){--k:var(--c5)}.araku-bot-chip:nth-child(5n+3){--k:var(--c4)}
.araku-bot-chip:nth-child(5n+4){--k:var(--c3)}.araku-bot-chip:nth-child(5n+5){--k:var(--c2)}
.araku-bot-chip:hover:not(:disabled),.araku-bot-chip:focus-visible{background:var(--k);color:#fff;border-color:var(--k);box-shadow:0 8px 18px -8px var(--k)}
.araku-bot-chip.primary{border:0;color:#fff;background:linear-gradient(110deg,var(--c1),var(--c2));box-shadow:0 8px 18px -8px rgba(227,18,27,.8)}
.araku-bot-chip.primary:hover:not(:disabled){background:linear-gradient(110deg,#B80F16,var(--c1));color:#fff}
.araku-bot-chip.home{--k:var(--c3);background:#E6F8EE}
/* starter cards */
.araku-bot-starter{--k:var(--c1);border:2px solid transparent;background:linear-gradient(#fff,#fff) padding-box,linear-gradient(135deg,var(--k),transparent 70%) border-box}
.araku-bot-starter:nth-child(6n+2){--k:var(--c2)}.araku-bot-starter:nth-child(6n+3){--k:var(--c5)}
.araku-bot-starter:nth-child(6n+4){--k:var(--c3)}.araku-bot-starter:nth-child(6n+5){--k:var(--c4)}.araku-bot-starter:nth-child(6n+6){--k:#D4A000}
.araku-bot-starter .ic{background:var(--k);color:#fff;box-shadow:0 6px 14px -6px var(--k)}
.araku-bot-starter strong{color:var(--k)}
.araku-bot-starter:hover:not(:disabled){border-color:transparent;background:linear-gradient(#fff,#fff) padding-box,linear-gradient(135deg,var(--k),var(--k)) border-box;box-shadow:0 14px 26px -16px var(--k)}
/* product cards ("cups showing", "show more") */
.araku-bot-card{--k:var(--c1);border:2px solid #F2E6E6}
.araku-bot-card:nth-child(4n+2){--k:var(--c5)}.araku-bot-card:nth-child(4n+3){--k:var(--c4)}.araku-bot-card:nth-child(4n+4){--k:var(--c3)}
.araku-bot-card:hover:not(:disabled),.araku-bot-card:focus-visible{border-color:var(--k);box-shadow:0 14px 26px -16px var(--k)}
.araku-bot-card-name{color:#1C1033;font-weight:700}
.araku-bot-card-price{color:var(--k);font-weight:800}
.araku-bot-res-scroll::-webkit-scrollbar-thumb{background:linear-gradient(90deg,var(--c1),var(--c5))}
/* quantity, inputs, address */
.araku-bot-stepper{border-color:var(--c5)}
.araku-bot-step-btn{color:var(--c5);font-weight:800}
.araku-bot-step-btn:hover:not(:disabled){background:#F4EEFF}
.araku-bot-step-val{color:var(--c1);font-size:18px}
.araku-bot-textinput:focus{border-color:var(--c5);box-shadow:0 0 0 4px rgba(139,0,255,.15)}
.araku-bot-field label{color:var(--c5)}
.araku-bot-spin{border-top-color:var(--c1)}
.chat-foot a{color:var(--c1)}
/* side panel actions */
.ag-acts button{--k:var(--c1);border:2px solid transparent;background:linear-gradient(#fff,#fff) padding-box,linear-gradient(135deg,var(--k),transparent 75%) border-box}
.ag-acts button:nth-child(2){--k:var(--c4)}.ag-acts button:nth-child(3){--k:var(--c5)}.ag-acts button:nth-child(4){--k:var(--c3)}
.ag-acts button span{background:var(--k);color:#fff}
.ag-acts button:hover{border-color:transparent;box-shadow:0 12px 24px -16px var(--k)}
.ag-side h1{background:linear-gradient(90deg,#C40F17,#E3121B 40%,#8B00FF);-webkit-background-clip:text;background-clip:text;color:transparent}
@media (max-width:520px){ .ag-hero-bot{width:88px;height:88px} .ag-say{font-size:13.5px} .araku-bot-av{width:32px;height:32px} .araku-bot-row.first .araku-bot-av{width:40px;height:40px} }
</style>

<main class="ag">
  <div class="wrap">
    <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>">Home</a><span aria-hidden="true">/</span><span aria-current="page">AI Agent</span></nav>
    <div class="ag-grid">
      <aside class="ag-side">
        <div class="ag-hero" id="agHero">
          <div class="ag-hero-bot" aria-hidden="true"><span class="ring"></span><span class="disc"></span><span class="fig"><?= h3_bot_svg() ?></span><i class="sparkle s1"></i><i class="sparkle s2"></i><i class="sparkle s3"></i></div>
          <p class="ag-say"><strong>Hi! I'm Araku AI Agent 👋</strong>Tell me what you need. I'll find it, fill your cart and take you to payment.</p>
        </div>
        <h1>Express checkout, all in one chat.</h1>
        <p class="lede">Pick products, choose quantities in steps of 100, add your address and pay. Or track, cancel and get help with an order.</p>
        <div class="ag-acts" aria-label="Quick actions">
          <button type="button" data-flow="shop"><span><svg viewBox="0 0 24 24"><path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg></span><div>Shop products<small>Browse by category and add to cart</small></div></button>
          <button type="button" data-flow="status"><span><svg viewBox="0 0 24 24"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg></span><div>Track my order<small>Status, payment and delivery</small></div></button>
          <button type="button" data-flow="cancel"><span><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8.5 8.5l7 7M15.5 8.5l-7 7"/></svg></span><div>Cancel an order<small>Confirmed with a code by email</small></div></button>
          <button type="button" data-flow="support"><span><svg viewBox="0 0 24 24"><path d="M5 5h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-7l-5 4v-4H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/></svg></span><div>Get support<small>Payments, returns and more</small></div></button>
        </div>
        <p class="ag-note">Prefer to browse? <a href="<?= h3_link('shop') ?>">Shop the collection</a> or <a href="<?= h3_link('contact') ?>">contact us</a>.</p>
      </aside>

      <section class="chat" aria-label="Chat with the Araku AI Agent">
        <header class="chat-head">
          <span class="head-bot" aria-hidden="true"><?= h3_bot_svg() ?></span>
          <div><strong>Araku AI Agent</strong><small>Online · replies instantly</small></div>
          <div class="hb"><button type="button" id="agMenu">Main menu</button><button type="button" id="agRestart">Start over</button></div>
        </header>
        <div id="araku-bot-messages" aria-live="polite" aria-relevant="additions" tabindex="-1"></div>
        <div class="chat-foot">Minimum order 100 units per product · <a href="<?= h3_link('privacy') ?>">Privacy</a></div>
      </section>
    </div>
  </div>
</main>

<script>
(function () {
'use strict';

/* ── Config ──────────────────────────────────────────────────── */
const AB_API      = <?= json_encode($AGENT_API) ?>;
const AB_PAYMENT  = <?= json_encode($AGENT_PAYMENT) ?>;
const AB_MASK     = <?= $AGENT_MASK ? 'true' : 'false' ?>;
const AB_CATEGORY_PAGES = <?= json_encode($AGENT_PAGES) ?>;
const AB_SIGNUP   = <?= json_encode($H3_LINKS['signup'] ?? 'coffee_signup3.php') ?>;
const AB_OFFERS   = <?= json_encode($H3_LINKS['offers'] ?? 'offers3.php') ?>;
const AB_COMBOS   = <?= json_encode($H3_LINKS['combos'] ?? 'combos3.php') ?>;
/* the currency every price in this chat is shown in: worked out on the server, exactly like the rest of the site */
const AB_CUR      = <?= json_encode($AGENT_CURRENCY) ?>;
const msgs = document.getElementById('araku-bot-messages');
const AB_BOT_SVG = <?= json_encode(h3_bot_svg()) ?>;   // the robot shown beside every bot message

/* conversation is remembered across pages (payment page hands back "order placed") */
const AB_TRANSCRIPT_KEY    = 'arakubot_transcript';
const AB_PENDING_ORDER_KEY = 'arakubot_pending_order_message';
function abSaveTranscript() { try { localStorage.setItem(AB_TRANSCRIPT_KEY, msgs.innerHTML); localStorage.setItem(AB_TRANSCRIPT_KEY + '_cur', AB_CUR.code); } catch (e) {} }

/* ── AJAX helper (same as before) ────────────────────────────── */
async function abApi(action, params = {}, method = 'GET') {
  if (method === 'GET') {
    const q = new URLSearchParams({ action, ...params });
    const res = await fetch(`${AB_API}?${q.toString()}`, { credentials: 'same-origin' });
    return res.json();
  }
  const fd = new FormData();
  fd.append('action', action);
  Object.entries(params).forEach(([k, v]) => fd.append(k, v));
  const res = await fetch(AB_API, { method: 'POST', body: fd, credentials: 'same-origin' });
  return res.json();
}

/* ── Render helpers ──────────────────────────────────────────── */
function abEsc(s) { return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
function abScroll() { msgs.scrollTop = msgs.scrollHeight; abSaveTranscript(); }
function abShowTyping() {
  const row = document.createElement('div');
  row.className = 'araku-bot-row'; row.id = 'araku-bot-typing-row';
  row.innerHTML = '<div class="araku-bot-typing"><span></span><span></span><span></span></div>';
  msgs.appendChild(row); abScroll(); return row;
}
async function abBotMsg(html, delay = 450) {
  const t = abShowTyping();
  await new Promise(r => setTimeout(r, delay));
  t.remove();
  const row = document.createElement('div');
  row.className = 'araku-bot-row has-av' + (msgs.querySelector('.has-av') ? '' : ' first');
  row.innerHTML = `<span class="araku-bot-av" aria-hidden="true">${AB_BOT_SVG}</span><div class="araku-bot-msg bot-msg">${html}</div>`;
  msgs.appendChild(row); abScroll();
}
function abUserMsg(text) {
  const row = document.createElement('div');
  row.className = 'araku-bot-row usr';
  row.innerHTML = `<div class="araku-bot-msg user-msg">${abEsc(text)}</div>`;
  msgs.appendChild(row); abScroll();
}
/* disable every live control (used when a side button starts a new flow) */
function abFreeze() { msgs.querySelectorAll('button, input').forEach(b => b.disabled = true); }

/** Row of chip buttons. items: [{label, value, cls?}]. onPick(item). */
function abChips(items, onPick, opts = {}) {
  const row = document.createElement('div');
  row.className = 'araku-bot-row';
  const wrap = document.createElement('div');
  wrap.className = 'araku-bot-chips';
  let all = items.filter(i => i.value !== 'menu' && i.value !== '__home__');
  if (!opts.noHome) all = [...all, { label: 'Main menu', cls: 'home', value: '__home__' }];
  all.forEach(item => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'araku-bot-chip' + (item.cls ? ' ' + item.cls : '');
    btn.textContent = item.label;
    btn.addEventListener('click', () => {
      wrap.querySelectorAll('.araku-bot-chip').forEach(b => b.disabled = true);
      abUserMsg(item.label);
      if (item.value === '__home__') showMainMenu(); else onPick(item);
    });
    wrap.appendChild(btn);
  });
  row.appendChild(wrap); msgs.appendChild(row); abScroll();
}

/** Product cards. onPick(item). */
function abResultCards(items, onPick) {
  const row = document.createElement('div');
  row.className = 'araku-bot-row';
  const scroll = document.createElement('div');
  scroll.className = 'araku-bot-res-scroll';
  items.forEach(item => {
    const card = document.createElement('button');
    card.type = 'button';
    card.className = 'araku-bot-card';
    card.innerHTML = `
      <img src="${abEsc(item.image_path)}" alt="${abEsc(item.product_name)}" loading="lazy">
      <div class="araku-bot-card-name">${abEsc(item.product_name)}</div>
      <div class="araku-bot-card-sub">ID: ${abEsc(item.product_id)} · Batch of ${abEsc(item.minimum_quantity)}</div>
      <div class="araku-bot-card-price">${abFormatPriceShown(item.price)}</div>`;
    card.addEventListener('click', () => {
      scroll.querySelectorAll('.araku-bot-card').forEach(b => b.disabled = true);
      abUserMsg(item.product_name);
      onPick(item);
    });
    scroll.appendChild(card);
  });
  row.appendChild(scroll); msgs.appendChild(row); abScroll();
}

/* ── Chat state ──────────────────────────────────────────────── */
let abCurrentCategory = null, abCurrentCategoryLabel = '', abCurrentOffset = 0;
let abIsLoggedIn = false, abGuestEmail = null, abShippingAddress = null, abBillingAddress = null, abPaymentMethod = null;
/* prices come from the database in the base currency; the server's currency and rate turn them into what this visitor sees */
let abCurrencySymbol = AB_CUR.symbol, abCurrencyRate = Number(AB_CUR.rate) || 1;
function abFormatPrice(usd) { const v = (parseFloat(usd) || 0) * abCurrencyRate; return abCurrencySymbol + v.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function abFormatPriceShown(usd) { return AB_MASK ? abFormatPrice(usd).replace(/\d/g, '#') : abFormatPrice(usd); }

/* who is signed in: asked once at the very start, so every flow (even "Shop coffee Bags" straight from the menu) already knows */
let abCtxPromise = null;
function abLoadContext() {
  if (!abCtxPromise) abCtxPromise = abApi('init').then(d => { if (d && d.success !== false) abIsLoggedIn = !!d.is_logged_in; return d; }).catch(() => null);
  return abCtxPromise;
}

function abTextInput({ placeholder = '', type = 'text', buttonLabel = 'Continue' }, onSubmit) {
  const row = document.createElement('div');
  row.className = 'araku-bot-row';
  const wrap = document.createElement('div');
  wrap.className = 'araku-bot-textinput-wrap';
  wrap.innerHTML = `<input type="${type}" class="araku-bot-textinput" placeholder="${abEsc(placeholder)}" ${type === 'email' ? 'autocomplete="email"' : ''}>
    <button type="button" class="araku-bot-chip primary">${abEsc(buttonLabel)}</button>`;
  row.appendChild(wrap); msgs.appendChild(row); abScroll();
  const input = wrap.querySelector('input'), btn = wrap.querySelector('button');
  setTimeout(() => input.focus(), 50);
  let submitted = false;
  const homeRow = document.createElement('div'); homeRow.className = 'araku-bot-row';
  const homeWrap = document.createElement('div'); homeWrap.className = 'araku-bot-chips';
  const homeBtn = document.createElement('button'); homeBtn.type = 'button'; homeBtn.className = 'araku-bot-chip home'; homeBtn.textContent = 'Main menu';
  function submit() {
    if (submitted) return;
    const val = input.value.trim(); if (!val) { input.focus(); return; }
    submitted = true; input.disabled = true; btn.disabled = true; homeBtn.disabled = true;
    onSubmit(val);
  }
  btn.addEventListener('click', submit);
  input.addEventListener('keydown', e => { if (e.key === 'Enter') submit(); });
  homeBtn.addEventListener('click', () => {
    if (submitted) return; submitted = true; input.disabled = true; btn.disabled = true; homeBtn.disabled = true;
    abUserMsg('Main menu'); showMainMenu();
  });
  homeWrap.appendChild(homeBtn); homeRow.appendChild(homeWrap); msgs.appendChild(homeRow); abScroll();
}

/* ── Start / menus ───────────────────────────────────────────── */
async function startChat() {
  msgs.innerHTML = '';
  abCurrentCategory = null; abCurrentOffset = 0;
  const ctx = abLoadContext();                      // runs while the greeting types
  await abBotMsg(`Hi, I'm the <strong>Araku AI Agent</strong> ☕<br>Here are a few things I can help with. Pick one to get started:`, 300);
  await ctx;
  showMainMenu();
}
/* starter suggestions: shown at the beginning and whenever "Main menu" is chosen */
const AB_STARTERS = [
  { k: 'coffee',  ic: '☕', t: 'Shop coffee Bags',          s: 'Araku Valley coffee bags' },
  { k: 'cups',    ic: '🥤', t: 'Cups for your café',   s: 'Takeaway and branded cups' },
  { k: 'offers',  ic: '🏷️', t: "This week's offers",  s: 'Real savings on selected products' },
  { k: 'combos',  ic: '🎁', t: 'Try a combo',          s: 'Ready-made sets or build your own' },
  { k: 'status',  ic: '📦', t: 'Track an order',       s: 'Status, payment and delivery' },
  { k: 'support', ic: '💬', t: 'Get help',             s: 'Payments, returns and more' },
];
function showMainMenu() {
  const row = document.createElement('div'); row.className = 'araku-bot-row';
  const grid = document.createElement('div'); grid.className = 'araku-bot-starters';
  AB_STARTERS.forEach(st => {
    const b = document.createElement('button'); b.type = 'button'; b.className = 'araku-bot-starter';
    b.innerHTML = `<span class="ic" aria-hidden="true">${st.ic}</span><span><strong>${abEsc(st.t)}</strong><small>${abEsc(st.s)}</small></span>`;
    b.addEventListener('click', () => { grid.querySelectorAll('button').forEach(x => x.disabled = true); abUserMsg(st.t); runStarter(st.k); });
    grid.appendChild(b);
  });
  row.appendChild(grid); msgs.appendChild(row); abScroll();
  abChips([
    { label: 'Coffee mugs', cls: 'back', value: 'mug' },
    { label: 'T-shirts', cls: 'back', value: 'tshirt' },
    { label: 'All categories', cls: 'back', value: 'all' },
  ], item => item.value === 'all' ? showCategoryMenu() : selectCategory(item.value, item.value === 'mug' ? 'Coffee Mugs' : 'T-Shirts', 0), { noHome: true });
}
async function runStarter(k) {
  if (k === 'coffee') return selectCategory('bag', 'Coffee Bags', 0);
  if (k === 'cups') return selectCategory('cup', 'Coffee Cups', 0);
  if (k === 'status') return startOrderLookupFlow('status');
  if (k === 'support') return startSupportFlow();
  if (k === 'offers') {
    await abBotMsg(`Our <strong>offers this week</strong> are all on one page, with the real saving shown on every product.<br>
      <a href="${abEsc(AB_OFFERS)}" target="_blank" rel="noopener">See this week's offers</a><br><br>Or I can help you pick something right here:`, 300);
    return abChips([{ label: 'Shop coffee', cls: 'primary', value: 'bag' }, { label: 'Cups for your café', cls: 'back', value: 'cup' }, { label: 'Try a combo', cls: 'back', value: 'combos' }],
      o => o.value === 'combos' ? runStarter('combos') : selectCategory(o.value, o.value === 'bag' ? 'Coffee Bags' : 'Coffee Cups', 0));
  }
  if (k === 'combos') {
    await abBotMsg(`Combos pair Araku coffee with something to drink it from. You can:<br>
      🎁 <a href="${abEsc(AB_COMBOS)}" target="_blank" rel="noopener">Pick a ready-made set</a><br>
      🧩 <a href="${abEsc(AB_COMBOS)}#build" target="_blank" rel="noopener">Build your own combo</a> and unlock a secret reward<br><br>
      Or build one here: start with coffee, then add a mug or cup.`, 320);
    return abChips([{ label: 'Start with coffee', cls: 'primary', value: 'bag' }, { label: 'Start with a mug', cls: 'back', value: 'mug' }],
      o => selectCategory(o.value, o.value === 'bag' ? 'Coffee Bags' : 'Coffee Mugs', 0));
  }
}

/* ── Support ─────────────────────────────────────────────────── */
const AB_SUPPORT_TOPICS = {
  order_status: { label: 'Order status / tracking' },
  payment: { label: 'Payment issue', answer: `If a payment seemed to fail or you were charged unexpectedly, no amount is deducted unless an order actually confirms. Check your order confirmation email first; if something still looks wrong, our team can look into the specific transaction.` },
  returns: { label: 'Returns / refunds' },
  cancel: { label: 'Cancel an order' },
  product: { label: 'Product question' },
  other: { label: 'Something else', answer: `Got it. Let's make sure a real person can help sort this out.` },
};
async function startSupportFlow() {
  await abBotMsg(`No worries, let's sort this out. What's it about?`, 280);
  abChips(Object.entries(AB_SUPPORT_TOPICS).map(([value, t]) => ({ label: t.label, value })), item => showSupportAnswer(item.value));
}
async function showSupportAnswer(topic) {
  if (topic === 'order_status') { startOrderLookupFlow('status'); return; }
  if (topic === 'returns')      { startOrderLookupFlow('returns'); return; }
  if (topic === 'cancel')       { startOrderLookupFlow('cancel'); return; }
  if (topic === 'product') { await abBotMsg(`Sure! Which category is the product in?`, 280); showCategoryMenu(undefined, true); return; }
  if (topic === 'other') { await abBotMsg(AB_SUPPORT_TOPICS.other.answer, 320); collectSupportEmail(topic); return; }
  await abBotMsg(AB_SUPPORT_TOPICS[topic].answer, 320);
  await abBotMsg(`Did that answer your question?`, 260);
  abChips([{ label: 'Yes, thanks!', cls: 'primary', value: 'yes' }, { label: 'No, I still need help', cls: 'back', value: 'no' }], item => {
    if (item.value === 'yes') abBotMsg(`Glad I could help! 😊`, 200).then(showMainMenu); else collectSupportEmail(topic);
  });
}
async function collectSupportEmail(topic) {
  await abBotMsg(`No problem. Leave your email and our support team will reach out to help directly.`, 280);
  abTextInput({ placeholder: 'you@example.com', type: 'email', buttonLabel: 'Submit' }, async email => {
    abUserMsg(email);
    await abBotMsg(`Sending this to our team… <span class="araku-bot-spin"></span>`, 220);
    let res;
    try { res = await abApi('submit_support_request', { email, topic }, 'POST'); } catch (e) { res = { success: false, error: 'Network error. Please try again.' }; }
    if (res.success) await abBotMsg(`✅ Got it! We've sent a confirmation to <strong>${abEsc(email)}</strong>, and our support team will reach out shortly.`, 300);
    else await abBotMsg('⚠️ ' + abEsc(res.error || 'Something went wrong. You can also email us at info@arakutribe.com.'), 300);
    abChips([{ label: 'Main menu', cls: 'primary', value: 'menu_go' }], () => showMainMenu(), { noHome: true });
  });
}

/* ── Order lookup / returns / cancel ─────────────────────────── */
async function startOrderLookupFlow(mode) {
  await abBotMsg(`Sure! What's your order number? (It's in your confirmation email, e.g. 123)`, 280);
  abTextInput({ placeholder: 'e.g. 123', type: 'text', buttonLabel: 'Look up' }, orderIdRaw => {
    abUserMsg(orderIdRaw);
    const orderId = orderIdRaw.replace(/\D/g, '');
    if (abIsLoggedIn) { lookupOrder(orderId, null, mode); return; }
    abBotMsg(`And the email you used when placing that order?`, 220).then(() => {
      abTextInput({ placeholder: 'you@example.com', type: 'email', buttonLabel: 'Look up' }, email => { abUserMsg(email); lookupOrder(orderId, email, mode); });
    });
  });
}
async function lookupOrder(orderId, email, mode) {
  await abBotMsg(`Looking that up… <span class="araku-bot-spin"></span>`, 200);
  const params = { order_id: orderId }; if (email) params.email = email;
  let res;
  try { res = await abApi('lookup_order', params, 'POST'); } catch (e) { res = { success: false, error: 'Network error. Please try again.' }; }
  if (!res.success) {
    await abBotMsg('⚠️ ' + abEsc(res.error || "We couldn't find that order."));
    abChips([{ label: 'Try again', cls: 'back', value: 'retry' }, { label: 'Main menu', cls: 'primary', value: 'menu_go' }],
      item => item.value === 'retry' ? startOrderLookupFlow(mode) : showMainMenu(), { noHome: true });
    return;
  }
  const lines = (res.items || []).map(it => `${abEsc(it.product_name)} × ${abEsc(it.quantity)}`).join('<br>');
  await abBotMsg(`<strong>Order #${abEsc(res.order_id)}</strong><br>Status: <strong>${abEsc(res.order_status)}</strong><br>
     Payment: ${abEsc(res.payment_status)} (${abEsc(res.payment_method)})<br>Placed: ${abEsc(res.order_date)}<br><br>${lines}<br><br>
     <strong>Total: ${abFormatPrice(res.total)}</strong><br>📦 Shipping to: ${abEsc(res.shipping_address)}`, 350);
  if (mode === 'cancel') {
    if (res.already_cancelled) { await abBotMsg(`This order has already been cancelled.`, 260); abChips([{ label: 'Main menu', cls: 'primary', value: 'menu_go' }], showMainMenu, { noHome: true }); return; }
    if (!res.eligible_for_cancellation) { await abBotMsg('😕 ' + abEsc(res.cancellation_blocked_reason || 'This order can no longer be cancelled.'), 280); abChips([{ label: 'Main menu', cls: 'primary', value: 'menu_go' }], showMainMenu, { noHome: true }); return; }
    confirmCancelOrder(res.order_id, email); return;
  }
  if (mode === 'returns') {
    if (res.order_status !== 'Delivered') await abBotMsg(`This order hasn't been delivered yet, so there's nothing to return just yet, but you may still be able to cancel it.`, 280);
    else if (res.eligible_for_return) await abBotMsg(`✅ Good news: it's within our 7-day return window (${abEsc(res.days_since_order)} day(s) since your order). Choose "Something else" in support to reach our team, or reply to your confirmation email.`, 320);
    else await abBotMsg(`😕 This order was placed ${abEsc(res.days_since_order)} day(s) ago, which is past our 7-day return window.`, 300);
  }
  const chips = [];
  if (res.eligible_for_cancellation && !res.already_cancelled) chips.push({ label: 'Cancel this order', cls: 'back', value: 'cancel' });
  chips.push({ label: 'Done, thanks', cls: 'primary', value: 'done' });
  abChips(chips, item => item.value === 'cancel' ? confirmCancelOrder(res.order_id, email) : showMainMenu());
}
async function confirmCancelOrder(orderId, email) {
  await abBotMsg(`Are you sure you want to cancel order #${abEsc(orderId)}? This can't be undone.`, 260);
  abChips([{ label: 'Yes, cancel it', cls: 'back', value: 'yes' }, { label: 'No, keep it', cls: 'primary', value: 'no' }], async opt => {
    if (opt.value === 'no') { showMainMenu(); return; }
    await abBotMsg(`Sending a verification code to confirm this… <span class="araku-bot-spin"></span>`, 220);
    const otpParams = { order_id: orderId }; if (email) otpParams.email = email;
    let otpRes;
    try { otpRes = await abApi('send_cancel_otp', otpParams, 'POST'); } catch (e) { otpRes = { success: false, error: 'Network error. Please try again.' }; }
    if (!otpRes.success) {
      if (otpRes.debug) console.warn('send_cancel_otp:', otpRes.debug);
      await abBotMsg('⚠️ ' + abEsc(otpRes.error || 'Could not send a verification code.'));
      abChips([{ label: 'Main menu', cls: 'primary', value: 'menu_go' }], showMainMenu, { noHome: true }); return;
    }
    await abBotMsg(`We've sent a 6-digit code to <strong>${abEsc(otpRes.email)}</strong>. Enter it below to confirm the cancellation.`, 300);
    abTextInput({ placeholder: '6-digit code', type: 'text', buttonLabel: 'Confirm cancellation' }, async otp => {
      abUserMsg('••••••');
      await abBotMsg(`Cancelling your order… <span class="araku-bot-spin"></span>`, 220);
      const params = { order_id: orderId, otp }; if (email) params.email = email;
      let res;
      try { res = await abApi('cancel_order', params, 'POST'); } catch (e) { res = { success: false, error: 'Network error. Please try again.' }; }
      if (!res.success) {
        await abBotMsg('⚠️ ' + abEsc(res.error || 'Could not cancel this order.'));
        abChips([{ label: 'Try again', cls: 'back', value: 'retry' }, { label: 'Main menu', cls: 'primary', value: 'menu_go' }],
          item => item.value === 'retry' ? confirmCancelOrder(orderId, email) : showMainMenu(), { noHome: true });
        return;
      }
      const refund = res.was_paid ? `Your payment will be refunded to your original payment method within 5–7 business days.` : `No payment had been made yet, so there's nothing to refund.`;
      await abBotMsg(`✅ Order #${abEsc(res.order_id)} has been cancelled.<br>${refund}${res.email_sent ? `<br>A confirmation has been sent to <strong>${abEsc(res.email)}</strong>.` : ''}`, 320);
      abChips([{ label: 'Main menu', cls: 'primary', value: 'menu_go' }], showMainMenu, { noHome: true });
    });
  });
}

/* ── Shopping ────────────────────────────────────────────────── */
async function showCategoryMenu(introText, infoMode = false) {
  abCurrentCategory = null; abCurrentOffset = 0;
  let data;
  try { data = await abApi('init'); } catch (e) { await abBotMsg('⚠️ Could not connect to the shop right now. Please try again.'); return; }
  if (!data.success) { await abBotMsg('⚠️ Something went wrong loading the shop.'); return; }
  abIsLoggedIn = !!data.is_logged_in;                // (the currency is NOT taken from here: the page already has the visitor's currency)
  await abBotMsg(introText || `We've got <strong>${abEsc(data.total)}</strong> products across <strong>${data.categories.length}</strong> categories.<br>What would you like to shop for?`, 380);
  abChips(data.categories.map(c => ({ label: `${c.label} (${c.count})`, value: c.value })),
    item => selectCategory(item.value, data.categories.find(c => c.value === item.value).label, 0, infoMode));
}
async function selectCategory(category, label, offset = 0, infoMode = false) {
  abCurrentCategory = category; abCurrentCategoryLabel = label; abCurrentOffset = offset;
  if (offset === 0) await abBotMsg(`Great choice! Here are our ${abEsc(label)}:`, 300);
  let data;
  try { const t = abShowTyping(); data = await abApi('get_items', { category, offset }); t.remove(); }
  catch (e) { document.getElementById('araku-bot-typing-row')?.remove(); await abBotMsg('⚠️ Could not load products. Please try again.'); return; }
  if (!data.success || !data.items.length) {
    await abBotMsg('😕 No products in this category yet.');
    abChips([{ label: 'Back to categories', cls: 'back', value: 'back' }], () => showCategoryMenu(undefined, infoMode)); return;
  }
  abResultCards(data.items, item => selectItem(item, infoMode));
  setTimeout(() => {
    abChips([{ label: 'Price: low to high', value: 'sort_low' }, { label: 'Price: high to low', value: 'sort_high' }], opt => {
      const sorted = [...data.items].sort((a, b) => opt.value === 'sort_low' ? a.price - b.price : b.price - a.price);
      abResultCards(sorted, item => selectItem(item, infoMode));
    }, { noHome: true });
  }, 60);
  const nav = [];
  if (data.has_more) nav.push({ label: 'Show 5 more', value: 'more' });
  nav.push({ label: 'Back to categories', cls: 'back', value: 'back' });
  setTimeout(() => abChips(nav, item => item.value === 'more' ? selectCategory(category, label, data.next_offset, infoMode) : showCategoryMenu(undefined, infoMode)), 140);
}
function abProductPageLink(category, productId) { return `${AB_CATEGORY_PAGES[category] || 'index.php'}#product-${encodeURIComponent(productId)}`; }
async function selectItem(item, infoMode = false) {
  await abBotMsg(`<img src="${abEsc(item.image_path)}" alt="${abEsc(item.product_name)}" style="width:100%;max-height:160px;object-fit:contain;padding:6px;margin-bottom:8px">
     <strong>${abEsc(item.product_name)}</strong><br><span style="color:var(--muted);font-size:13px">ID: ${abEsc(item.product_id)}</span><br>
     ${abFormatPriceShown(item.price)} <span style="color:var(--muted)">per unit · batch of ${abEsc(item.minimum_quantity)}</span><br>
     <a href="${abProductPageLink(abCurrentCategory, item.product_id)}" target="_blank" rel="noopener">View full details on the shop page</a>`, 350);
  if (infoMode) {
    abChips([{ label: 'Back to products', cls: 'back', value: 'back' }, { label: 'Main menu', cls: 'primary', value: 'menu_go' }],
      opt => opt.value === 'back' ? selectCategory(abCurrentCategory, abCurrentCategoryLabel, abCurrentOffset, true) : showMainMenu(), { noHome: true });
    return;
  }
  abChips([{ label: 'Add to cart', cls: 'primary', value: 'cart' }, { label: 'Back', cls: 'back', value: 'back' }],
    opt => opt.value === 'cart' ? chooseQuantity(item) : selectCategory(abCurrentCategory, abCurrentCategoryLabel, abCurrentOffset));
}
async function chooseQuantity(item) {
  const step = parseInt(item.minimum_quantity, 10) || 100;
  await abBotMsg(`How many units would you like? Use − and + (each step is ${step} units).`, 280);
  let qty = step;
  const row = document.createElement('div'); row.className = 'araku-bot-row';
  const wrap = document.createElement('div'); wrap.className = 'araku-bot-stepper-wrap';
  wrap.innerHTML = `<div class="araku-bot-stepper"><button type="button" class="araku-bot-step-btn" aria-label="Fewer">−</button><span class="araku-bot-step-val">${qty}</span><button type="button" class="araku-bot-step-btn" aria-label="More">+</button></div>
    <button type="button" class="araku-bot-chip primary araku-bot-step-confirm">Add ${qty} units to cart</button>`;
  row.appendChild(wrap); msgs.appendChild(row); abScroll();
  const [minus, plus] = wrap.querySelectorAll('.araku-bot-step-btn'), val = wrap.querySelector('.araku-bot-step-val'), ok = wrap.querySelector('.araku-bot-step-confirm');
  const paint = () => { val.textContent = qty; minus.disabled = qty <= step; ok.textContent = `Add ${qty} units to cart`; };
  paint();
  minus.addEventListener('click', () => { if (qty > step) { qty -= step; paint(); } });
  plus.addEventListener('click', () => { qty += step; paint(); });
  ok.addEventListener('click', () => { minus.disabled = plus.disabled = ok.disabled = true; abUserMsg(`${qty} units`); addToCart(item, qty); });
}
async function addToCart(item, qty) {
  await abBotMsg(`Adding ${qty} units to your cart… <span class="araku-bot-spin"></span>`, 250);
  let data;
  try { data = await abApi('add_to_cart', { id: item.product_id, qty, category: abCurrentCategory }, 'POST'); } catch (e) { data = { success: false, error: 'Network error.' }; }
  if (!data.success) {
    await abBotMsg('⚠️ Something went wrong: ' + abEsc(data.error || ''));
    abChips([{ label: 'Back to categories', cls: 'back', value: 'back' }], () => showCategoryMenu()); return;
  }
  await abBotMsg(`✅ <strong>${abEsc(item.product_name)}</strong> × ${qty} units added to your cart!<br>Subtotal: ${abFormatPrice(data.subtotal)} · Items in cart: ${abEsc(data.cart_count)}`, 300);
  abChips([{ label: 'Proceed to buy', cls: 'primary', value: 'buy' }, { label: 'Continue shopping', cls: 'back', value: 'more' }],
    opt => opt.value === 'buy' ? startCheckoutFlow() : showCategoryMenu(`Sure! What else would you like to add?`));
}

/* ── Checkout ────────────────────────────────────────────────── */
async function startCheckoutFlow() {
  await abBotMsg(`Let's get this shipped! 📦`, 250);
  await abLoadContext();                              // make sure we know whether this visitor is signed in
  if (abIsLoggedIn || abGuestEmail) { stepShippingAddress(); return; }
  await abBotMsg(`What's your email so we can send your order confirmation?`, 300);
  abTextInput({ placeholder: 'you@example.com', type: 'email', buttonLabel: 'Continue' }, async email => {
    abUserMsg(email); abGuestEmail = email;
    await abBotMsg(`One moment… <span class="araku-bot-spin"></span>`, 200);
    let check;
    try { check = await abApi('check_email_and_send_otp', { email }, 'POST'); } catch (e) { check = { success: false }; }
    if (check.success && check.match_type !== 'none') {
      await abBotMsg(`Looks like <strong>${abEsc(email)}</strong> has ordered with us before. We've sent a 6-digit code to confirm it's you. Enter it to use your saved address, or skip to continue as a new guest.`, 320);
      abTextInput({ placeholder: '6-digit code', type: 'text', buttonLabel: 'Verify' }, async otp => {
        abUserMsg('••••••');
        let res;
        try { res = await abApi('verify_guest_otp', { otp }, 'POST'); } catch (e) { res = { success: false, error: 'Network error. Please try again.' }; }
        if (!res.success) {
          await abBotMsg('⚠️ ' + abEsc(res.error || 'Invalid code.'), 250);
          abChips([{ label: 'Continue as guest', cls: 'back', value: 'guest' }], () => offerSignupOrGuest()); return;
        }
        if (res.match_type === 'user') { abIsLoggedIn = true; await abBotMsg(`✅ Verified. Welcome back!`, 250); stepShippingAddress(); return; }
        await abBotMsg(`✅ Verified! Here's what you've used before:`, 250);
        const addresses = res.addresses || [];
        if (!addresses.length) { askNewAddress('shipping'); return; }
        const opts = addresses.map(a => ({ label: `📍 ${a}`, value: a }));
        opts.push({ label: 'Use a new address', cls: 'primary', value: '__new__' });
        abChips(opts, item => { if (item.value === '__new__') askNewAddress('shipping'); else { abShippingAddress = item.value; stepBillingAddress(); } });
      });
      abChips([{ label: 'Skip, continue as a new guest', cls: 'back', value: 'skip' }], () => offerSignupOrGuest());
    } else if (!check.success) {
      await abBotMsg('⚠️ ' + abEsc(check.error || 'Something went wrong. Continuing as guest.'), 250); offerSignupOrGuest();
    } else offerSignupOrGuest();
  });
}
async function offerSignupOrGuest() {
  await abBotMsg(`You're checking out as a new guest. Want to create an account? You'll get saved addresses and order history next time.`, 280);
  abChips([{ label: 'Create an account', cls: 'primary', value: 'signup' }, { label: 'Skip, continue as guest', cls: 'back', value: 'skip' }], opt => {
    if (opt.value === 'signup') window.location.href = AB_SIGNUP + (abGuestEmail ? ('?email=' + encodeURIComponent(abGuestEmail)) : '');
    else stepShippingAddress();
  });
}
async function stepShippingAddress() {
  if (abIsLoggedIn) {
    const t = abShowTyping(); let data;
    try { data = await abApi('get_saved_addresses'); } catch (e) { data = { success: false }; }
    t.remove();
    if (data.success && data.addresses && data.addresses.length) {
      await abBotMsg(`Choose a saved delivery address, or add a new one:`, 280);
      const opts = data.addresses.map(a => ({ label: `📍 ${a}`, value: a }));
      opts.push({ label: 'New address', cls: 'primary', value: '__new__' });
      abChips(opts, item => { if (item.value === '__new__') askNewAddress('shipping'); else { abShippingAddress = item.value; stepBillingAddress(); } });
      return;
    }
  }
  askNewAddress('shipping');
}
async function askNewAddress(kind) {
  await abBotMsg(`Let's get your ${kind === 'shipping' ? 'delivery' : 'billing'} address:`, 220);
  const row = document.createElement('div'); row.className = 'araku-bot-row';
  const wrap = document.createElement('div'); wrap.className = 'araku-bot-address-form';
  wrap.innerHTML = `
    <div class="araku-bot-field"><label>Street / area *</label><input type="text" class="araku-bot-textinput addr-street" placeholder="e.g. 12 MG Road, Sector 5" autocomplete="street-address"></div>
    <div class="araku-bot-field"><label>Landmark (optional)</label><input type="text" class="araku-bot-textinput addr-landmark" placeholder="e.g. Near City Mall"></div>
    <div class="araku-bot-field"><label>City *</label><input type="text" class="araku-bot-textinput addr-city" placeholder="e.g. Visakhapatnam" autocomplete="address-level2"></div>
    <div class="araku-bot-field"><label>State *</label><input type="text" class="araku-bot-textinput addr-state" placeholder="e.g. Andhra Pradesh" autocomplete="address-level1"></div>
    <div class="araku-bot-field"><label>PIN / zip code *</label><input type="text" class="araku-bot-textinput addr-zip" inputmode="numeric" placeholder="e.g. 530001" autocomplete="postal-code"></div>
    <div class="araku-bot-address-error"></div>
    <button type="button" class="araku-bot-chip primary araku-bot-address-save">Save address</button>`;
  row.appendChild(wrap); msgs.appendChild(row); abScroll();
  const q = c => wrap.querySelector(c);
  const street = q('.addr-street'), landmark = q('.addr-landmark'), city = q('.addr-city'), state = q('.addr-state'), zip = q('.addr-zip'), err = q('.araku-bot-address-error'), save = q('.araku-bot-address-save');
  const fields = [street, landmark, city, state, zip];
  setTimeout(() => street.focus(), 50);
  const LET = /^[a-zA-Z\s.'\-]+$/;
  const fail = m => { err.textContent = '⚠️ ' + m; err.style.display = 'block'; };
  let submitted = false;
  function trySubmit() {
    if (submitted) return;
    const v = { s: street.value.trim(), l: landmark.value.trim(), c: city.value.trim(), st: state.value.trim(), z: zip.value.trim() };
    if (!v.s) return fail('Street / area is required.');
    if (!v.c || !LET.test(v.c)) return fail('City must contain only letters.');
    if (!v.st || !LET.test(v.st)) return fail('State must contain only letters.');
    if (!/^\d{4,10}$/.test(v.z)) return fail('PIN / zip code must be 4 to 10 digits.');
    submitted = true; err.style.display = 'none'; fields.forEach(f => f.disabled = true); save.disabled = true;
    const full = [v.s, v.l ? `Near ${v.l}` : '', v.c, v.st, v.z].filter(Boolean).join(', ');
    abUserMsg(full);
    if (kind === 'shipping') { abShippingAddress = full; stepBillingAddress(); } else { abBillingAddress = full; proceedToPayment(); }
  }
  save.addEventListener('click', trySubmit);
  fields.forEach(i => i.addEventListener('keydown', e => { if (e.key === 'Enter') trySubmit(); }));
  abChips([], () => {});
}
async function stepBillingAddress() {
  await abBotMsg(`Is the billing address the same as delivery?`, 260);
  abChips([{ label: 'Same as delivery', cls: 'primary', value: 'same' }, { label: 'Different', cls: 'back', value: 'diff' }],
    item => { if (item.value === 'same') { abBillingAddress = abShippingAddress; proceedToPayment(); } else askNewAddress('billing'); });
}
async function proceedToPayment() { if (abIsLoggedIn) { await stepReviewBeforePayment(); return; } abPaymentMethod = null; stepConfirmOrder(); }
async function cartSummaryHtml() {
  const t = abShowTyping(); let cart;
  try { cart = await abApi('get_cart_summary'); } catch (e) { cart = { success: false }; }
  t.remove();
  if (!cart.success || !cart.items || !cart.items.length) return null;
  const lines = cart.items.map(it => `${abEsc(it.product_name)} × ${abEsc(it.quantity)} · ${abFormatPrice(it.price * it.quantity)}`).join('<br>');
  return { cart, lines };
}
async function stepReviewBeforePayment() {
  const s = await cartSummaryHtml();
  if (!s) { await abBotMsg(`Hmm, your cart looks empty right now.`); abChips([{ label: 'Browse categories', cls: 'primary', value: 'x' }], () => showCategoryMenu()); return; }
  await abBotMsg(`<strong>Order summary</strong><br>${s.lines}<br><br><strong>Total: ${abFormatPrice(s.cart.total)}</strong><br><br>
     📦 <strong>Deliver to:</strong> ${abEsc(abShippingAddress)}<br>🧾 <strong>Billing:</strong> ${abEsc(abBillingAddress)}<br><br>Ready to go to payment?`, 380);
  abChips([{ label: 'Confirm and go to payment', cls: 'primary', value: 'confirm' }, { label: 'Edit details', cls: 'back', value: 'edit' }, { label: 'Continue shopping', cls: 'back', value: 'shop' }], async opt => {
    if (opt.value === 'shop') { showCategoryMenu(`Sure! What else would you like to add?`); return; }
    if (opt.value === 'edit') { stepShippingAddress(); return; }
    await abBotMsg(`Taking you to the payment page… <span class="araku-bot-spin"></span>`, 260);
    let res;
    try { res = await abApi('stage_for_payment_page', { shipping_address: abShippingAddress, billing_address: abBillingAddress }, 'POST'); }
    catch (e) { res = { success: false, error: 'Network error. Please try again.' }; }
    if (res.success) {
      // the backend may still point at the old payment page: always use the new one
      const target = (!res.redirect || /dummy_payment\.php/i.test(res.redirect)) ? AB_PAYMENT : res.redirect;
      window.location.href = target;
    } else {
      await abBotMsg('⚠️ ' + abEsc(res.error || 'Could not open the payment page.'));
      abChips([{ label: 'Try again', cls: 'back', value: 'back' }], () => stepReviewBeforePayment());
    }
  });
}
async function stepConfirmOrder() {
  const s = await cartSummaryHtml();
  if (!s) { await abBotMsg(`Hmm, your cart looks empty right now.`); abChips([{ label: 'Browse categories', cls: 'primary', value: 'x' }], () => showCategoryMenu()); return; }
  const payLine = abPaymentMethod ? `💳 <strong>Payment:</strong> ${abEsc(abPaymentMethod === 'cod' ? 'Cash on delivery' : abPaymentMethod)}<br>` : `💳 <strong>Payment:</strong> to be arranged, our team will contact you<br>`;
  await abBotMsg(`<strong>Order summary</strong><br>${s.lines}<br><br><strong>Total: ${abFormatPrice(s.cart.total)}</strong><br><br>
     📦 <strong>Deliver to:</strong> ${abEsc(abShippingAddress)}<br>🧾 <strong>Billing:</strong> ${abEsc(abBillingAddress)}<br>${payLine}`, 380);
  abChips([{ label: 'Confirm and place order', cls: 'primary', value: 'confirm' }, { label: 'Start over', cls: 'back', value: 'restart' }], async opt => {
    if (opt.value === 'restart') { startChat(); return; }
    await abBotMsg(`Placing your order… <span class="araku-bot-spin"></span>`, 250);
    const payload = { shipping_address: abShippingAddress, billing_address: abBillingAddress };
    if (abPaymentMethod) payload.payment_method = abPaymentMethod;
    if (!abIsLoggedIn) payload.email = abGuestEmail;
    let res;
    try { res = await abApi('place_order', payload, 'POST'); }
    catch (e) { await abBotMsg('⚠️ Something went wrong placing your order. Please try again.'); abChips([{ label: 'Try again', cls: 'back', value: 'back' }], () => stepConfirmOrder()); return; }
    if (!res.success) { await abBotMsg('⚠️ ' + abEsc(res.error || 'Something went wrong placing your order.')); abChips([{ label: 'Try again', cls: 'back', value: 'back' }], () => stepConfirmOrder()); return; }
    if (res.email_debug) console.warn('place_order email:', res.email_debug);
    await abBotMsg(res.email_sent
      ? `🎉 <strong>Order #${abEsc(res.order_id)} placed!</strong><br>A confirmation email has been sent to <strong>${abEsc(res.email)}</strong>.`
      : `🎉 <strong>Order #${abEsc(res.order_id)} placed!</strong><br>(We couldn't send the confirmation email, but your order is saved.)`, 350);
    abChips([{ label: 'Shop more', cls: 'primary', value: 'more' }], () => showCategoryMenu(`What would you like to shop for next?`));
  });
}

/* ── Side buttons + header buttons start a flow directly ─────── */
const FLOWS = {
  shop:    () => { abUserMsg('Shop products'); showCategoryMenu(); },
  status:  () => { abUserMsg('Track my order'); startOrderLookupFlow('status'); },
  cancel:  () => { abUserMsg('Cancel an order'); startOrderLookupFlow('cancel'); },
  support: () => { abUserMsg('Get support'); startSupportFlow(); },
};
document.querySelectorAll('[data-flow]').forEach(b => b.addEventListener('click', () => {
  abFreeze(); FLOWS[b.dataset.flow]();
  if (matchMedia('(max-width:1000px)').matches) document.querySelector('.chat').scrollIntoView({ behavior: 'smooth' });
}));
document.getElementById('agMenu').addEventListener('click', () => { abFreeze(); abUserMsg('Main menu'); showMainMenu(); });
document.getElementById('agRestart').addEventListener('click', () => { try { localStorage.removeItem(AB_TRANSCRIPT_KEY); } catch (e) {} startChat(); });

/* ── The big robot flies around once per visit, then sits by its message ── */
(function () {
  const hero = document.getElementById('agHero'); if (!hero) return;
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  hero.classList.add('flying');
  setTimeout(() => { hero.classList.remove('flying'); hero.classList.add('landing'); setTimeout(() => hero.classList.remove('landing'), 650); }, 4450);
})();

/* ── On load: show "order placed" if the payment page just finished one, else start ── */
(async function boot() {
  let pending = null;
  try { const raw = localStorage.getItem(AB_PENDING_ORDER_KEY); if (raw) pending = JSON.parse(raw); } catch (e) {}
  if (!pending) { startChat(); return; }
  try { localStorage.removeItem(AB_PENDING_ORDER_KEY); } catch (e) {}
  // an earlier conversation is only brought back if its prices were shown in today's currency
  try { const saved = localStorage.getItem(AB_TRANSCRIPT_KEY); if (saved && localStorage.getItem(AB_TRANSCRIPT_KEY + '_cur') === AB_CUR.code) { msgs.innerHTML = saved; abFreeze(); } } catch (e) {}
  await abLoadContext();
  await abBotMsg(`🎉 <strong>Order #${abEsc(pending.order_id)} placed!</strong><br>Paid via ${abEsc(pending.payment_label)} · Total ${abFormatPrice(pending.total)}<br>
     ${pending.email_sent ? `A confirmation email has been sent to <strong>${abEsc(pending.email)}</strong>.` : `(We couldn't send the confirmation email, but your order is saved.)`}`, 300);
  abChips([{ label: 'Shop more', cls: 'primary', value: 'more' }], () => showCategoryMenu(`What would you like to shop for next?`));
})();
})();
</script>

<?php include __DIR__ . '/footer3.php'; ?>