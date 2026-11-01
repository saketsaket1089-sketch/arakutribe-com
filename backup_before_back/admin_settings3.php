<?php
/* =====================================================================
   ARAKU TRIBE - admin_settings3.php   (NEW - site look & feel settings)
   Edit colours, fonts, sizes, buttons, special effects, the AI robot and
   the session timeout for the whole site. Values live in the
   `site_settings` table (create it once with site_settings.sql).
   Every setting shows its default (today's look) and a "Reset" button.

   WHO CAN OPEN IT: admins signed in through adminlogin.php AND the
   emailed code (verify_otp.php). Everyone else goes to adminlogin.php.
   It sits in the admin sidebar (admin_layout3.php) like the dashboard.
   ===================================================================== */
require_once __DIR__ . '/admin_layout3.php';
admin3_require();
$isAdmin = true;
if (empty($_SESSION['csrf3'])) $_SESSION['csrf3'] = bin2hex(random_bytes(16));

include __DIR__ . '/db1.php';
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset('utf8mb4');

/* ---------- tabs: Global (styles + header/footer text) and one tab per page ---------- */
$PAGE_TABS = [
  'global' => 'Global: styles, header & footer', 'index3' => 'Home', 'coffee_about3' => 'About', 'coffee_contact3' => 'Contact us',
  'coffee_franchise3' => 'Franchise', 'products3' => 'All products', 'coffee_bag3' => 'Coffee bags', 'coffee_cup3' => 'Coffee cups',
  'coffee_mug3' => 'Coffee mugs', 'coffee_tshirt3' => 'T-shirts', 'offers3' => 'Offers', 'hotdeals3' => 'Hot deals', 'combos3' => 'Combos',
  'cart3' => 'Cart', 'wishlist3' => 'Wishlist', 'araku_agent3' => 'AI Agent', 'coffee_login3' => 'Sign in', 'coffee_signup3' => 'Sign up',
  'forgot_password3' => 'Forgot password', 'verify_user_otp3' => 'Verify code', 'user_profile3' => 'My profile', 'user_orders3' => 'My orders',
  'edit_profile3' => 'Edit profile', 'delete_account3' => 'Delete account', 'privacy_policy3' => 'Privacy policy',
  'terms_and_conditions3' => 'Terms & conditions', 'terms_deletion3' => 'Account deletion terms',
  'process_order3' => 'Checkout', 'dummy_payment3' => 'Payment'];
$q   = trim((string)($_GET['q'] ?? ''));                       // search across ALL pages
$tab = $q !== '' ? 'search' : (isset($PAGE_TABS[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'global');

$rows = []; $tabCounts = [];
$res = @$conn->query("SELECT page, COUNT(*) n, SUM(setting_value <> default_value) changed FROM site_settings GROUP BY page");
$tableMissing = !$res;
if ($res) while ($r = $res->fetch_assoc()) $tabCounts[$r['page']] = $r;
if (!$tableMissing) {
    if ($tab === 'search') {
        $like = '%' . $q . '%';
        $st = $conn->prepare("SELECT * FROM site_settings WHERE label LIKE ? OR setting_value LIKE ? OR default_value LIKE ? OR applies_to LIKE ? ORDER BY page = 'global' DESC, page, group_order, sort_order LIMIT 300");
        $st->bind_param('ssss', $like, $like, $like, $like);
    } else {
        $st = $conn->prepare("SELECT * FROM site_settings WHERE page = ? ORDER BY group_order, sort_order");
        $st->bind_param('s', $tab);
    }
    $st->execute(); $r2 = $st->get_result();
    while ($r = $r2->fetch_assoc()) $rows[$r['setting_key']] = $r;
    $st->close();
}
$isContent = fn($r) => strpos($r['setting_key'], 'c_') === 0;        // page text / link / image rows

/* ---------- check one value against its type; returns the clean value or null ---------- */
function adm_clean($row, $v) {
    $v = trim((string)$v);
    switch ($row['input_type']) {
        case 'color':  return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtoupper($v) : null;
        case 'size':   return preg_match('/^\d{1,3}(\.\d{1,2})?(px|rem|em|%)$/', $v) ? $v : null;
        case 'number': [$min, $max] = array_map('intval', explode(',', $row['options'] ?: '0,9999'));
                       return (preg_match('/^-?\d+$/', $v) && (int)$v >= $min && (int)$v <= $max) ? (string)(int)$v : null;
        case 'select': return in_array($v, explode('|', (string)$row['options']), true) ? $v : null;
        case 'toggle': return $v === '1' ? '1' : '0';
        case 'link':   return (!preg_match('/^\s*javascript:/i', $v) && preg_match('~^(#[\w-]*|[\w./-]+\.(php|html?)([?#][^\s"\'<>`]*)?|https?://[^\s"\'<>`]+|tel:\+?[\d\s()-]{5,}|mailto:[^\s"\'<>`]+)$~i', $v)) ? $v : null;
        case 'image':  return (strpos($v, '..') === false && preg_match('~^(https?://[^\s"\'<>`]+|[\w./ -]+\.(png|jpe?g|webp|gif|svg|avif))$~i', $v)) ? $v : null;
        case 'text': case 'textarea':
            if (strpos($row['setting_key'], 'c_') !== 0) { $v = trim(strip_tags($v)); return ($v !== '' && strlen($v) <= 20) ? $v : null; }   // the robot tag
            $v = trim(str_replace(['`', '${'], ["'", '$ {'], strip_tags($v)));                  // page text: plain words only
            if ($v === '' || strlen($v) > 4000) return null;
            if ($v === html_entity_decode($row['default_value'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) return $row['default_value'];   // same words = unchanged
            return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    }
    return null;
}

/* ---------- save / reset ---------- */
$msg = ''; $errors = [];
if ($isAdmin && !$tableMissing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf3'], (string)($_POST['csrf'] ?? ''))) { $errors[] = 'Your session expired. Please try again.'; }
    else {
        $updates = [];
        if (isset($_POST['undo_key']) && isset($rows[$_POST['undo_key']]) && $rows[$_POST['undo_key']]['prev_value'] !== null) {
            $updates[$_POST['undo_key']] = $rows[$_POST['undo_key']]['prev_value'];
        } elseif (isset($_POST['reset_key']) && isset($rows[$_POST['reset_key']])) {
            $updates[$_POST['reset_key']] = $rows[$_POST['reset_key']]['default_value'];
        } elseif (isset($_POST['reset_group'])) {
            foreach ($rows as $k => $r) if ($r['setting_group'] === $_POST['reset_group']) $updates[$k] = $r['default_value'];
        } else {
            foreach ($rows as $k => $r) {
                if (!isset($_POST['shown'][$k])) continue;                 // only rows that were on the screen
                if ($r['input_type'] === 'toggle') { $updates[$k] = isset($_POST['v'][$k]) ? '1' : '0'; continue; }
                if (!isset($_POST['v'][$k])) continue;
                $clean = adm_clean($r, $_POST['v'][$k]);
                if ($clean === null) $errors[] = $r['label'] . ': "' . htmlspecialchars((string)$_POST['v'][$k]) . '" is not a valid value. It was not saved.';
                else $updates[$k] = $clean;
            }
        }
        $st = $conn->prepare("UPDATE site_settings SET prev_value = setting_value, setting_value = ? WHERE setting_key = ?");
        $n = 0;
        foreach ($updates as $k => $v) if ($rows[$k]['setting_value'] !== $v) { $st->bind_param('ss', $v, $k); $st->execute(); $rows[$k]['prev_value'] = $rows[$k]['setting_value']; $rows[$k]['setting_value'] = $v; $n++; }
        $st->close();
        $msg = isset($_POST['undo_key']) ? 'Undone: the previous value is back.' : (isset($_POST['reset_key']) ? 'Setting reset to its default.' : (isset($_POST['reset_group']) ? 'Section reset to defaults.' : ($n ? "$n setting" . ($n === 1 ? '' : 's') . ' saved. Open the page to see the change.' : 'Nothing changed.')));
    }
}
$sampleImg = '';                                   // a real product photo for the thumbnail preview
if (!$tableMissing && ($pr = $conn->query("SELECT category, image FROM products WHERE quantity > 0 ORDER BY product_id DESC LIMIT 1")) && ($pi = $pr->fetch_assoc()))
    $sampleImg = 'img/shop/' . strtolower($pi['category']) . '/' . $pi['image'];
$conn->close();

$groups = [];
foreach ($rows as $k => $r) $groups[($tab === 'search' ? ($PAGE_TABS[$r['page']] ?? $r['page']) . ' · ' : '') . $r['setting_group']][$k] = $r;
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

admin3_head('Site settings - Araku Tribe admin');
?>
<style>
.adm{padding:28px 0 70px;background:var(--cream)}
.adm .crumbs{display:flex;flex-wrap:wrap;margin:0 0 16px}
.adm-top{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:18px}
.adm-top h1{font-size:clamp(1.8rem,3vw,2.4rem)}
.adm-top p{margin:4px 0 0;color:var(--muted)}
.adm-grid{display:grid;grid-template-columns:230px 1fr;gap:24px;align-items:start}
.adm-toc{position:sticky;top:20px;background:#fff;border-radius:18px;padding:12px;box-shadow:var(--shadow)}
.adm-toc a{display:block;padding:8px 12px;border-radius:10px;font-weight:700;color:var(--brown-deep);text-decoration:none;font-size:14.5px}
.adm-toc a:hover{background:var(--cream-2)}
.adm-sec{background:#fff;border-radius:20px;padding:20px 22px;margin-bottom:18px;box-shadow:var(--shadow);scroll-margin-top:130px}
.adm-sec-head{display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:2px solid var(--cream-2);padding-bottom:10px;margin-bottom:6px}
.adm-sec-head h2{font-size:1.35rem}
.adm-row{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr) auto;gap:6px 18px;align-items:center;padding:14px 0;border-bottom:1px solid var(--line)}
.adm-row:last-child{border-bottom:0}
.adm-row label{font-weight:800;color:var(--ink)}
.adm-row .help{grid-column:1/2;margin:2px 0 0;font-size:13px;color:var(--muted);line-height:1.45}
.adm-row .def{font-size:12.5px;color:var(--muted)}
.adm-row .def code{background:var(--cream-2);padding:1px 6px;border-radius:6px;color:var(--brown-deep);font-weight:700}
.adm-in{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.adm-in input[type=text],.adm-in input[type=number],.adm-in select{height:42px;padding:0 12px;border:1.5px solid var(--line);border-radius:10px;font:600 15px var(--sans);background:#fff;min-width:0;width:170px}
.adm-in input[type=color]{width:46px;height:42px;padding:2px;border:1.5px solid var(--line);border-radius:10px;background:#fff;cursor:pointer}
.adm-in .sw{width:22px;height:22px;border-radius:6px;border:1px solid var(--line)}
.changed{display:inline-block;margin-left:8px;padding:1px 8px;border-radius:999px;background:#FFE8D6;color:#B4410F;font-size:11.5px;font-weight:800;vertical-align:2px}
.adm-reset{border:0;background:none;color:var(--brown);font-weight:700;text-decoration:underline;cursor:pointer;font-size:13.5px}
.tgl{position:relative;display:inline-block;width:54px;height:30px}
.tgl input{opacity:0;width:0;height:0}
.tgl span{position:absolute;inset:0;border-radius:999px;background:#CDBBAE;transition:background .2s;cursor:pointer}
.tgl span::after{content:"";position:absolute;left:4px;top:4px;width:22px;height:22px;border-radius:50%;background:#fff;transition:transform .2s}
.tgl input:checked+span{background:var(--green)}
.tgl input:checked+span::after{transform:translateX(24px)}
.tgl input:focus-visible+span{outline:3px solid var(--gold);outline-offset:2px}
.adm-bar{position:sticky;bottom:0;z-index:20;display:flex;gap:12px;align-items:center;justify-content:flex-end;padding:14px 0;background:linear-gradient(transparent,var(--cream) 35%)}
.adm-msg{padding:12px 16px;border-radius:12px;margin-bottom:16px;font-weight:700}
.adm-msg.ok{background:#E6F5EC;color:#1F7A4D}
.adm-msg.err{background:#FFE8E3;color:#B4410F}
.adm-msg ul{margin:6px 0 0;padding-left:18px;font-weight:600}
@media (max-width:900px){ .where{grid-column:auto} .adm-grid{grid-template-columns:1fr} .adm-toc{position:static;display:flex;flex-wrap:wrap} .adm-row{grid-template-columns:1fr} .adm-row .help{grid-column:auto} }
body{margin:0}
.adm{background:transparent;padding-top:28px}
/* page tabs + search across all pages */
.adm-all{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 12px}
.adm-all input{flex:1 1 320px;height:48px;padding:0 16px;border:2px solid #F4B63D;border-radius:14px;font:600 15px var(--sans);background:#fff}
.adm-tabs{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 18px;padding:10px;background:#fff;border-radius:18px;box-shadow:var(--shadow)}
.adm-tabs a{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;border:2px solid var(--line);text-decoration:none;font-weight:800;font-size:13.5px;color:var(--brown-deep);background:#FFFCF8}
.adm-tabs a:hover{border-color:var(--brown)}
.adm-tabs a[aria-current="page"]{background:linear-gradient(135deg,#8A4B2A,#4A2A1A);border-color:#F4B63D;color:#FFF4E2}
.adm-tabs small{font-weight:700;opacity:.7}
.adm-tabs b{min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:#E3121B;color:#fff;font-size:11.5px;line-height:20px;text-align:center}
.adm-note{margin:-6px 0 14px;font-weight:700;color:var(--brown)}
.adm-in input.wide,.adm-in textarea{width:100%;min-width:0}
.adm-in textarea{padding:10px 12px;border:1.5px solid var(--line);border-radius:10px;font:500 15px/1.5 var(--sans);resize:vertical}
.adm-c .adm-in{flex-wrap:nowrap}
.adm-c{grid-template-columns:minmax(0,1fr) minmax(0,1.3fr) auto}
.adm-thumb{flex:none;width:64px;height:64px;object-fit:cover;border-radius:10px;border:2px solid var(--line);background:var(--cream)}
.adm-thumb.bad{opacity:.25;border-color:#E3121B}
.adm-open{flex:none;font-weight:800;font-size:13px;text-decoration:none;color:var(--brown)}
.adm-acts{display:flex;flex-direction:column;gap:4px;align-items:flex-start}
.adm-row[hidden],.adm-sec[hidden]{display:none!important}
.where{grid-column:1/2;display:flex;flex-wrap:wrap;align-items:center;gap:5px;margin-top:7px;font-size:12.5px}
.where b{color:var(--brown-deep);margin-right:2px}
.where span{padding:2px 9px;border-radius:999px;background:#FFF1E2;border:1px solid #F1D3B5;color:#7A3B14;font-weight:700}
.where span.hit{background:#FFD400;border-color:#E0B800;color:#2A1910}
.adm-find{position:sticky;top:0;z-index:15;background:#fff;border-radius:18px;padding:14px 18px;margin-bottom:16px;box-shadow:var(--shadow);border:2px solid #F4B63D}
.adm-find label{display:block;font-weight:800;color:var(--brown-deep);margin-bottom:6px}
.adm-find input{width:100%;height:46px;padding:0 14px;border:2px solid var(--line);border-radius:12px;font:600 15px var(--sans)}
.adm-find input:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 4px rgba(244,182,61,.25)}
.adm-find p{margin:6px 0 0;font-size:13px;color:var(--muted);min-height:1em}
/* live previews (Buttons + Thumbnails) */
.pv{position:relative;display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin:12px 0 6px;padding:34px 18px 18px;border-radius:16px;background:var(--cream);border:2px dashed #E8C9A8}
.pv-tag{position:absolute;top:8px;left:14px;font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--brown-soft)}
.pv-b{display:inline-flex;align-items:center;justify-content:center;min-height:var(--btn-md-h,48px);padding:0 24px;border:2px solid transparent;text-decoration:none;
  font:var(--btn-fw,600) var(--btn-fs,15px) var(--sans);border-radius:var(--btn-radius,999px);box-shadow:var(--btn-shadow,none);transition:background .2s,color .2s,transform .2s;position:relative;overflow:hidden;isolation:isolate}
.pv a.pv-primary{background:var(--btn-bg,#C9501A);color:var(--btn-fg,#fff)}
.pv a.pv-primary:hover{background:var(--btn-hover-bg,#AF4413);color:var(--btn-hover-fg,#fff)}
.pv a.pv-outline{border-color:var(--btn-out,#6B3E26);color:var(--btn-out,#6B3E26);background:transparent}
.pv a.pv-outline:hover{background:var(--btn-out-hover-bg,#6B3E26);color:var(--btn-out-hover-fg,#fff);border-color:var(--btn-out-hover-bg,#6B3E26)}
.pv-sm{min-height:var(--btn-sm-h,38px);padding:0 16px}
.pv-lg{min-height:var(--btn-lg-h,54px);padding:0 30px}
.pv.pop-lift .pv-b:hover{transform:translateY(-3px)}
.pv.pop-grow .pv-b:hover{transform:scale(1.06)}
.pv.pop-bounce .pv-b:hover{animation:pvBounce .55s ease}
@keyframes pvBounce{0%,55%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}75%{transform:translateY(-3px)}}
.pv.spark .pv-primary::after{content:"";position:absolute;top:0;bottom:0;left:-60%;width:40%;z-index:-1;background:linear-gradient(105deg,transparent,rgba(255,236,170,.65),transparent);transform:skewX(-20deg);animation:pvShine 3.6s ease-in-out infinite}
@keyframes pvShine{0%,55%{left:-60%}85%,100%{left:130%}}
.pv-hot{display:inline-flex;align-items:center;gap:8px;min-height:56px;padding:0 24px 0 14px;border-radius:999px;font:900 20px var(--sans);letter-spacing:.07em;color:var(--hot-fg,#fff);
  background:linear-gradient(110deg,var(--hot-a,#E3121B) 0%,var(--hot-b,#FF4D00) 35%,var(--hot-c,#FF9500) 50%,var(--hot-b,#FF4D00) 65%,var(--hot-a,#E3121B) 100%)}
.pv .pv-fl{width:28px;height:28px;fill:var(--flame,#FFE066);stroke:#fff;stroke-width:1.2}
.pv-agent{display:inline-flex;align-items:center;gap:10px;min-height:56px;padding:4px 20px 4px 6px;border-radius:999px;color:#fff;
  background:radial-gradient(120% 140% at 100% 0%,#8B00FF 0%,rgba(139,0,255,0) 55%),var(--agent-bg,#2A1150)}
.pv-bot{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#FFE6D6;font-size:24px}
.pv-agent b{display:block;font:900 20px var(--sans);letter-spacing:.07em;background:linear-gradient(90deg,var(--agent-t1,#FFD400),#fff 30%,var(--agent-t2,#FF7A00) 50%,#fff 70%,var(--agent-t1,#FFD400));-webkit-background-clip:text;background-clip:text;color:transparent}
.pv-agent small{display:block;font-weight:700;font-size:12.5px;color:var(--agent-sub,#FFE9A8)}
.pv svg{width:var(--ico-size,22px);height:var(--ico-size,22px);fill:none;stroke:currentColor;stroke-width:var(--ico-stroke,1.7);stroke-linecap:round;stroke-linejoin:round}
.pv-ib{position:relative;width:54px;height:54px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;border:2.5px solid var(--hicon-rim,#F4B63D);color:var(--hicon-fg,#FFF4E2);
  background:linear-gradient(135deg,var(--hicon-from,#8A4B2A),var(--hicon-to,#4A2A1A))}
.pv-ib svg{width:27px;height:27px;stroke-width:2.3}
.pv-count{position:absolute;top:-6px;right:-6px;min-width:24px;height:24px;padding:0 6px;border-radius:999px;border:2.5px solid #fff;background:var(--count-bg,#E3121B);color:var(--count-fg,#fff);font:900 12px/19px var(--sans);font-style:normal;text-align:center}
.pv-dark{display:inline-flex;align-items:center;gap:12px;padding:10px 14px;border-radius:14px;background:var(--topbar-bg,#3B2317)}
.pv-help{display:inline-flex;align-items:center;gap:6px;color:var(--help-fg,#FFE6A8);font-weight:800}
.pv-help svg{width:16px;height:16px}
.pv-social{width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.25);color:var(--social-fg,#DCC9B8);font-weight:800}
.pv-social:hover{color:var(--social-hover,#fff)}
.pv-wish{width:42px;height:42px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#fff;box-shadow:var(--shadow);color:var(--wish-fg,#6B3E26)}
.pv-wish.on{color:var(--wish-on,#D9363E)}.pv-wish.on svg{fill:currentColor}
.pv-text{flex-direction:column;align-items:flex-start}
.pv-crumbs{display:flex;align-items:center;gap:6px;width:100%;font-weight:800;color:var(--brown-soft)}
.pv-crumbs a{padding:5px 13px;border-radius:999px;text-decoration:none;color:var(--crumb-link-fg,#6B3E26);background:var(--crumb-link-bg,#F6EADF)}
.pv-crumbs .cur{padding:5px 13px;border-radius:999px;color:var(--crumb-cur-fg,#fff);background:linear-gradient(90deg,var(--crumb-from,#E3121B),var(--crumb-to,#FF7A00))}
.pv-close{margin-left:auto;padding:7px 14px;border-radius:999px;border:2.5px solid var(--close-fg,#3B2317);color:var(--close-fg,#3B2317);background:#fff}
.pv-strip{display:flex;align-items:flex-end;gap:8px;width:100%;padding:8px 10px 0;border-radius:12px 12px 0 0;background:var(--topbar-bg,#3B2317)}
.pv-top{margin-bottom:8px;padding:5px 14px;border-radius:999px;border:2px solid rgba(244,182,61,.75);color:var(--topbar-text,#FFF4E2);font-weight:800;font-size:14px}
.pv-top.on{margin:0;padding:8px 18px;border:0;border-radius:12px 12px 0 0;background:#fff;color:var(--toptab-fg,#2A1910);box-shadow:inset 0 4px 0 var(--toptab-line,#FF9F1C)}
.pv-foot{display:flex;flex-direction:column;gap:4px;padding:12px 16px;border-radius:12px;background:var(--footer-bg,#3B2317);min-width:240px}
.pv-foot b{color:var(--footer-head,#fff)}
.pv-foot a{color:var(--footer-text,#DCC9B8);text-decoration:none;font-weight:600}
.pv-foot a:hover{color:var(--footer-hover,#fff)}
.pv-bubble{max-width:270px;padding:12px 14px;border-radius:18px 18px 4px 18px;background:#fff;color:var(--bubble-fg,#17110D);box-shadow:0 0 0 2px var(--bubble-title,#E3121B)}
.pv-bubble b{display:block;color:var(--bubble-title,#E3121B)}
.pv-tagbtn{padding:6px 11px;border-radius:999px;background:var(--bot-tag-bg,#E3121B);color:var(--bot-tag-fg,#fff);font:800 12px var(--sans);letter-spacing:.08em;text-transform:uppercase}
.pv-acct{padding:10px 14px;border-radius:12px;background:#fff;box-shadow:var(--shadow);color:var(--acct-fg,#17110D);font-weight:600}
.pv a.pv-add{display:inline-flex;align-items:center;justify-content:center;min-width:170px;padding:12px 18px;border-radius:var(--btn-radius,999px);text-decoration:none;font:700 15px var(--sans);
  background:var(--pbtn-add-bg,#3B2317);color:var(--pbtn-add-fg,#FFF7ED);box-shadow:var(--btn-shadow,none);transition:background .2s,color .2s}
.pv a.pv-add:hover{background:var(--pbtn-add-hbg,#C9501A);color:var(--pbtn-add-hfg,#fff)}
.pv a.pv-add.in,.pv a.pv-add.in:hover{background:var(--pbtn-in-bg,#2F6B4F);color:var(--pbtn-in-fg,#fff)}
.pv-qv{padding:9px 18px;border-radius:999px;background:var(--pbtn-q-bg,#fff);color:var(--pbtn-q-fg,#6B3E26);font-weight:600;font-size:13.5px;box-shadow:var(--shadow)}
.pv-dark2{display:inline-flex;align-items:center;gap:10px;padding:12px 14px;border-radius:14px;background:#3B2317}
.pv a.pv-ghost{padding:11px 20px;border-radius:var(--btn-radius,999px);border:2px solid var(--pbtn-gh-fg,#FFF7ED);color:var(--pbtn-gh-fg,#FFF7ED);text-decoration:none;font-weight:700}
.pv a.pv-ghost:hover{background:var(--pbtn-gh-hbg,#FFF7ED);border-color:var(--pbtn-gh-hbg,#FFF7ED);color:var(--pbtn-gh-hfg,#3B2317)}
.pv-arrow{width:40px;height:40px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(255,247,237,.35);background:rgba(30,17,10,.25);color:var(--pbtn-arrow-fg,#fff);font-size:22px;font-weight:700}
.pv-card{width:200px;background:#fff;border-radius:22px;padding:10px;box-shadow:var(--shadow);transition:transform .3s}
.pv-card:hover{transform:translateY(-5px)}
.pv-media{aspect-ratio:4/5;overflow:hidden;display:flex;align-items:center;justify-content:center;border-radius:var(--thumb-radius,16px);background:var(--pv-bg,#F3E9DD);box-shadow:var(--thumb-shadow,none)}
.pv-media img{width:100%;height:100%;object-fit:var(--thumb-fit,contain);padding:var(--thumb-pad,11%);mix-blend-mode:multiply;transition:transform .6s cubic-bezier(.2,.7,.2,1)}
.pv-card:hover .pv-media img{transform:scale(var(--thumb-zoom,1.07))}
.pv-media span{color:var(--muted);font-weight:700}
.pv-cat{margin:10px 4px 2px;font-size:12px;font-weight:800;letter-spacing:.05em;color:#C9501A}
.pv-name{margin:0 4px 4px;font-weight:800;color:#2A1150}
</style>
</head>
<body class="a3">
<?php admin3_sidebar('settings'); ?>
<main class="a3-main adm">
  <div class="wrap">
    <div class="adm-top">
      <div><h1>Site settings</h1><p>Every page's text, links, images and look. Each item shows its default; "Reset" puts it back, "Undo" goes back one save.</p></div>
      <a class="btn btn-outline" href="<?= $tab !== 'global' && $tab !== 'search' ? $e($tab) . '.php' : 'index.php' ?>" target="_blank" rel="noopener"><?= $tab !== 'global' && $tab !== 'search' ? 'Open this page ↗' : 'Open the site ↗' ?></a>
    </div>
    <?php if (!$tableMissing): ?>
    <form class="adm-all" method="GET" role="search">
      <input type="search" name="q" value="<?= $e($q) ?>" placeholder='Search ALL pages, e.g. "Add to cart", "Shop now", "about_img", "contact"'>
      <button class="btn btn-primary" type="submit">Search all pages</button>
      <?php if ($q !== ''): ?><a class="adm-reset" href="?tab=global">Clear search</a><?php endif; ?>
    </form>
    <nav class="adm-tabs" aria-label="Pages">
      <?php foreach ($PAGE_TABS as $pk => $pl): $tc = $tabCounts[$pk] ?? null; if (!$tc && $pk !== 'global') continue; ?>
        <a href="?tab=<?= $e($pk) ?>"<?= $tab === $pk ? ' aria-current="page"' : '' ?>><?= $e($pl) ?> <small><?= (int)($tc['n'] ?? 0) ?></small><?php if (!empty($tc['changed'])): ?><b title="Changed items"><?= (int)$tc['changed'] ?></b><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <?php if ($tab === 'search'): ?><p class="adm-note"><?= count($rows) ?> item<?= count($rows) === 1 ? '' : 's' ?> on all pages match "<?= $e($q) ?>"<?= count($rows) >= 300 ? ' (first 300 shown, make the search more specific)' : '' ?>.</p><?php endif; ?>
    <?php endif; ?>

    <?php if ($tableMissing): ?>
      <div class="adm-msg err">The <code>site_settings</code> table doesn't exist yet. Run <b>site_settings.sql</b> in phpMyAdmin (SQL tab), then reload this page.</div>
    <?php else: ?>
      <?php if ($msg && !$errors): ?><div class="adm-msg ok" role="status"><?= $e($msg) ?></div><?php endif; ?>
      <?php if ($errors): ?><div class="adm-msg err" role="alert">Some values were not saved:<ul><?php foreach ($errors as $er): ?><li><?= $er ?></li><?php endforeach; ?></ul></div><?php endif; ?>

      <div class="adm-grid">
        <nav class="adm-toc" aria-label="Sections">
          <?php foreach ($groups as $g => $_): ?><a href="#g-<?= $e(md5($g)) ?>"><?= $e($g) ?></a><?php endforeach; ?>
        </nav>

        <form method="POST" id="admForm">
          <div class="adm-find">
            <label for="admFind">Find settings for a button or place</label>
            <input type="search" id="admFind" form="none" placeholder='Type a button or place, e.g. "Add to cart", "Bags", "footer", "price"' autocomplete="off">
            <p id="admFindInfo" aria-live="polite"></p>
          </div>
          <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf3']) ?>">
          <?php foreach ($groups as $g => $items): ?>
          <section class="adm-sec" id="g-<?= $e(md5($g)) ?>">
            <div class="adm-sec-head"><h2><?= $e($g) ?></h2>
              <button class="adm-reset" name="reset_group" value="<?= $e($g) ?>" onclick="return confirm('Reset every setting in <?= $e($g) ?> to its default?')">Reset section</button></div>
            <?php if ($g === 'Buttons'): ?>
            <div class="pv pv-btn" id="pvButtons" aria-label="Live preview">
              <span class="pv-tag">Live preview: hover the buttons</span>
              <a class="pv-b pv-primary" href="#" onclick="return false">Add to cart</a>
              <a class="pv-b pv-outline" href="#" onclick="return false">View all</a>
              <a class="pv-b pv-primary pv-sm" href="#" onclick="return false">Small</a>
              <a class="pv-b pv-primary pv-lg" href="#" onclick="return false">Big button</a>
            </div>
            <?php elseif ($g === 'Header buttons'): ?>
            <div class="pv pv-hdr" aria-label="Live preview">
              <span class="pv-tag">Live preview</span>
              <span class="pv-hot"><svg viewBox="0 0 24 24" class="pv-fl" aria-hidden="true"><path d="M12 22c4.4 0 7-2.9 7-6.6 0-3.3-2-5.6-3.6-7.4-.4 1.6-1.3 2.7-2.4 3.1.4-3.3-.9-6.5-3.8-8.6.2 3.1-1.1 5.1-2.6 6.8C5.2 11.1 5 12.9 5 15.4 5 19.1 7.6 22 12 22Z"/></svg>HOT DEALS</span>
              <span class="pv-agent"><span class="pv-bot">🤖</span><span><b>AI AGENT</b><small>Hi! I'm here to help 👋</small></span></span>
            </div>
            <?php elseif ($g === 'Icons'): ?>
            <div class="pv pv-icons" aria-label="Live preview">
              <span class="pv-tag">Live preview</span>
              <span class="pv-ib"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></span>
              <span class="pv-ib"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z"/></svg><i class="pv-count">1</i></span>
              <span class="pv-ib"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg><i class="pv-count">2</i></span>
              <span class="pv-dark"><span class="pv-help"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H8l-4 4V5Z"/></svg>Help &amp; contact</span>
                <span class="pv-social">f</span><span class="pv-social">◎</span></span>
              <span class="pv-wish"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z"/></svg></span>
              <span class="pv-wish on"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z"/></svg></span>
            </div>
            <?php elseif ($g === 'Text colours'): ?>
            <div class="pv pv-text" aria-label="Live preview">
              <span class="pv-tag">Live preview</span>
              <div class="pv-crumbs"><a href="#" onclick="return false">Home</a> / <a href="#" onclick="return false">Shop</a> / <span class="cur">Coffee Bags</span><span class="pv-close">✕ Close</span></div>
              <div class="pv-strip"><span class="pv-top">Home</span><span class="pv-top on">Products</span><span class="pv-top">Contact us</span></div>
              <div class="pv-foot"><b>Shop</b><a href="#" onclick="return false">Coffee bags (hover me)</a></div>
              <div class="pv-bubble"><b>Hi! I'm your Araku AI Agent 👋</b>Order in one chat. Try me!</div>
              <span class="pv-tagbtn">Click me!</span>
              <div class="pv-acct">My profile · My orders · Wishlist</div>
            </div>
            <?php elseif ($g === 'Page buttons'): ?>
            <div class="pv pv-pbtn" aria-label="Live preview">
              <span class="pv-tag">Live preview: hover the buttons</span>
              <a class="pv-add" href="#" onclick="return false">Add to cart</a>
              <a class="pv-add in" href="#" onclick="return false">✓ In cart</a>
              <span class="pv-qv">Quick view</span>
              <span class="pv-dark2"><a class="pv-ghost" href="#" onclick="return false">Explore our story</a><span class="pv-arrow">‹</span><span class="pv-arrow">›</span></span>
            </div>
            <?php elseif ($g === 'Thumbnails'): ?>
            <div class="pv pv-thumb" id="pvThumbs" aria-label="Live preview">
              <span class="pv-tag">Live preview: hover the card</span>
              <div class="pv-card"><div class="pv-media"><?php if ($sampleImg): ?><img src="<?= $e($sampleImg) ?>" alt=""><?php else: ?><span>Product photo</span><?php endif; ?></div>
                <p class="pv-cat">SAMPLE RANGE</p><p class="pv-name">Sample product</p></div>
            </div>
            <?php endif; ?>
            <?php foreach ($items as $k => $r): $v = $r['setting_value']; $id = 'f_' . $k; ?>
            <div class="adm-row<?= $isContent($r) ? ' adm-c' : '' ?>"><input type="hidden" name="shown[<?= $e($k) ?>]" value="1">
              <div>
                <label for="<?= $e($id) ?>"><?= $e($r['label']) ?></label><?php if ($v !== $r['default_value']): ?><span class="changed">changed</span><?php endif; ?>
                <p class="help"><?= $e($r['help']) ?></p>
                <?php if (!empty($r['applies_to'])): ?>
                <div class="where"><b>Where you'll see it:</b><?php foreach (array_filter(array_map('trim', preg_split('/\s*[|·]\s*/u', $r['applies_to']))) as $place): ?><span><?= $e($place) ?></span><?php endforeach; ?></div>
                <?php endif; ?>
              </div>
              <div>
                <div class="adm-in">
                  <?php if ($isContent($r)): $dv = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
                    <?php if ($r['input_type'] === 'textarea'): ?>
                      <textarea id="<?= $e($id) ?>" name="v[<?= $e($k) ?>]" rows="4" maxlength="4000"><?= $e($dv) ?></textarea>
                    <?php elseif ($r['input_type'] === 'image'): ?>
                      <img class="adm-thumb" src="<?= $e($v) ?>" alt="" data-thumb="<?= $e($id) ?>" onerror="this.classList.add('bad')">
                      <input type="text" id="<?= $e($id) ?>" name="v[<?= $e($k) ?>]" value="<?= $e($v) ?>" class="wide" placeholder="img/folder/photo.jpg">
                    <?php elseif ($r['input_type'] === 'link'): ?>
                      <input type="text" id="<?= $e($id) ?>" name="v[<?= $e($k) ?>]" value="<?= $e($v) ?>" class="wide" placeholder="page.php, https://..., tel:..., mailto:...">
                      <a class="adm-open" href="<?= $e($v) ?>" target="_blank" rel="noopener">Open ↗</a>
                    <?php else: ?>
                      <input type="text" id="<?= $e($id) ?>" name="v[<?= $e($k) ?>]" value="<?= $e($dv) ?>" class="wide" maxlength="600">
                    <?php endif; ?>
                  <?php elseif ($r['input_type'] === 'color'): ?>
                    <input type="color" value="<?= $e($v) ?>" aria-label="Pick <?= $e($r['label']) ?>" data-pair="<?= $e($id) ?>">
                    <input type="text" id="<?= $e($id) ?>" name="v[<?= $e($k) ?>]" data-var="<?= $e($r['css_var']) ?>" value="<?= $e($v) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}" title="A hex colour like #6B3E26">
                  <?php elseif ($r['input_type'] === 'size'): ?>
                    <input type="text" id="<?= $e($id) ?>" name="v[<?= $e($k) ?>]" data-var="<?= $e($r['css_var']) ?>" value="<?= $e($v) ?>" maxlength="10" pattern="\d{1,3}(\.\d{1,2})?(px|rem|em|%)" title="A number with a unit, like 16px">
                  <?php elseif ($r['input_type'] === 'number'): [$mn, $mx] = explode(',', $r['options'] ?: '0,9999'); ?>
                    <input type="number" id="<?= $e($id) ?>" name="v[<?= $e($k) ?>]" value="<?= $e($v) ?>" min="<?= (int)$mn ?>" max="<?= (int)$mx ?>"><small><?= (int)$mn ?>–<?= (int)$mx ?></small>
                  <?php elseif ($r['input_type'] === 'select'): ?>
                    <select id="<?= $e($id) ?>" name="v[<?= $e($k) ?>]"><?php foreach (explode('|', $r['options']) as $o): ?><option<?= $o === $v ? ' selected' : '' ?>><?= $e($o) ?></option><?php endforeach; ?></select>
                  <?php elseif ($r['input_type'] === 'toggle'): ?>
                    <label class="tgl"><input type="checkbox" id="<?= $e($id) ?>" name="v[<?= $e($k) ?>]" value="1"<?= $v === '1' ? ' checked' : '' ?>><span></span></label><small><?= $v === '1' ? 'On' : 'Off' ?></small>
                  <?php else: ?>
                    <input type="text" id="<?= $e($id) ?>" name="v[<?= $e($k) ?>]" value="<?= $e($v) ?>" maxlength="20">
                  <?php endif; ?>
                </div>
                <p class="def">Default: <?php if ($isContent($r)): ?><code><?= $e(preg_replace('/^(.{137}).+$/us', '$1…', html_entity_decode($r['default_value'], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?></code></p><?php else: ?><?php if ($r['input_type'] === 'color'): ?><span class="sw" style="display:inline-block;vertical-align:-5px;background:<?= $e($r['default_value']) ?>"></span> <?php endif; ?><code><?= $e($r['input_type'] === 'toggle' ? ($r['default_value'] === '1' ? 'On' : 'Off') : $r['default_value']) ?></code></p><?php endif; ?>
              </div>
              <div class="adm-acts"><?php if ($v !== $r['default_value']): ?><button class="adm-reset" name="reset_key" value="<?= $e($k) ?>">Reset</button><?php endif; ?>
                <?php if ($r['prev_value'] !== null && $r['prev_value'] !== $v): ?><button class="adm-reset" name="undo_key" value="<?= $e($k) ?>" title="Go back to the value before your last save">Undo</button><?php endif; ?></div>
            </div>
            <?php endforeach; ?>
          </section>
          <?php endforeach; ?>
          <div class="adm-bar"><span id="dirty" style="font-weight:700;color:#B4410F" hidden>You have unsaved changes</span><button type="submit" class="btn btn-primary btn-lg">Save all settings</button></div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</main>

<script>
// colour picker <-> hex box stay in step; show "unsaved changes"
document.querySelectorAll('input[type=color][data-pair]').forEach(p => {
  const t = document.getElementById(p.dataset.pair);
  p.addEventListener('input', () => { t.value = p.value.toUpperCase(); t.dispatchEvent(new Event('input', { bubbles: true })); });
  t.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(t.value)) p.value = t.value; });
});
// live previews: copy the current (unsaved) values onto the preview boxes
const SHADOWS = { none: 'none', soft: '0 6px 16px -8px rgba(59,35,23,.55)', strong: '0 14px 28px -10px rgba(59,35,23,.85)', glow: '0 0 0 4px rgba(244,182,61,.35),0 8px 22px -6px rgba(201,80,26,.75)' };
const val = k => { const el = document.getElementById('f_' + k); return !el ? null : (el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value.trim()); };
const okColor = v => /^#[0-9a-f]{6}$/i.test(v || ''), okSize = v => /^\d{1,3}(\.\d{1,2})?(px|rem|em|%)$/.test(v || '');
function previewVars() {
  document.querySelectorAll('[data-var]').forEach(el => {
    const v = el.dataset.var, x = el.value.trim();
    if (!v || !(okColor(x) || okSize(x))) return;
    document.querySelectorAll('.pv').forEach(pv => pv.style.setProperty(v, x));
  });
}
function preview() {
  previewVars();
  const pb = document.getElementById('pvButtons'), pt = document.getElementById('pvThumbs');
  if (pb) {
    [['b_bg','--btn-bg'],['b_fg','--btn-fg'],['b_hover_bg','--btn-hover-bg'],['b_hover_fg','--btn-hover-fg'],['b_out','--btn-out'],['b_out_hbg','--btn-out-hover-bg'],['b_out_hfg','--btn-out-hover-fg']]
      .forEach(([k, v]) => okColor(val(k)) && pb.style.setProperty(v, val(k)));
    [['b_sm','--btn-sm-h'],['b_md','--btn-md-h'],['b_lg','--btn-lg-h'],['b_font','--btn-fs'],['b_radius','--btn-radius']]
      .forEach(([k, v]) => okSize(val(k)) && pb.style.setProperty(v, val(k)));
    if (val('b_weight')) pb.style.setProperty('--btn-fw', val('b_weight'));
    pb.style.setProperty('--btn-shadow', SHADOWS[val('b_shadow')] || 'none');
    pb.classList.remove('pop-lift', 'pop-grow', 'pop-bounce');
    if (['lift', 'grow', 'bounce'].includes(val('b_pop'))) pb.classList.add('pop-' + val('b_pop'));
    pb.classList.toggle('spark', val('fx_btnspark') === '1');
  }
  if (pt) {
    if (okSize(val('th_radius'))) pt.style.setProperty('--thumb-radius', val('th_radius'));
    const cover = val('th_fit') === 'cover';
    pt.style.setProperty('--thumb-fit', cover ? 'cover' : 'contain');
    pt.style.setProperty('--thumb-pad', cover ? '0%' : (okSize(val('th_pad')) ? val('th_pad') : '11%'));
    pt.style.setProperty('--pv-bg', val('th_one_bg') === '1' && okColor(val('th_bg')) ? val('th_bg') : '#F3E9DD');
    pt.style.setProperty('--thumb-zoom', { none: '1', zoom: '1.07', strong: '1.15' }[val('th_zoom')] || '1.07');
    pt.style.setProperty('--thumb-shadow', SHADOWS[val('th_shadow')] || 'none');
  }
}
document.addEventListener('input', preview); document.addEventListener('change', preview); preview();
// toggles: keep the On/Off label beside each switch in step
document.querySelectorAll('.tgl input').forEach(t => t.addEventListener('change', () => { const s = t.closest('.adm-in').querySelector('small'); if (s) s.textContent = t.checked ? 'On' : 'Off'; }));

// find settings by button / place name: hides other rows, highlights the matching chips
(function () {
  const box = document.getElementById('admFind'); if (!box) return;
  const info = document.getElementById('admFindInfo');
  box.addEventListener('input', () => {
    const q = box.value.trim().toLowerCase(); let n = 0;
    document.querySelectorAll('.adm-sec').forEach(sec => {
      let shown = 0;
      sec.querySelectorAll('.adm-row').forEach(row => {
        const hit = !q || row.textContent.toLowerCase().includes(q);
        row.hidden = !hit; if (hit) shown++;
        row.querySelectorAll('.where span').forEach(sp => sp.classList.toggle('hit', !!q && sp.textContent.toLowerCase().includes(q)));
      });
      sec.hidden = !shown; n += shown;
    });
    info.textContent = q ? (n ? `${n} setting${n === 1 ? '' : 's'} change "${box.value.trim()}"` : `No setting mentions "${box.value.trim()}"`) : '';
  });
})();

// image paths: the thumbnail follows what you type
document.querySelectorAll('[data-thumb]').forEach(img => {
  const box = document.getElementById(img.dataset.thumb);
  box.addEventListener('input', () => { img.classList.remove('bad'); img.src = box.value.trim(); });
});

const form = document.getElementById('admForm');
if (form) {
  let dirty = false;
  form.addEventListener('input', e => { if (e.target.id === 'admFind') return; dirty = true; document.getElementById('dirty').hidden = false; });   // searching is not a change
  form.addEventListener('submit', () => { dirty = false; });
  window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
}
</script>
</body>
</html>