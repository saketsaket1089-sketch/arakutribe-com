<?php
/* =====================================================================
   ARAKU TRIBE - admin_layout3.php   (NEW - shared by all admin pages)
   - admin3_require()      : put at the top of every admin-only page.
                             Lets in only admins who passed password AND
                             the emailed code (OTP). Others go to adminlogin.php.
   - admin3_auth_open/close: page frame for login / code / signup pages
   - admin3_sidebar($key)  : the dashboard sidebar (one copy for all pages)
   - admin3_css()          : the admin look (espresso, cream, gold)
   ===================================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();

/* signed in as an admin AND the OTP was verified */
function admin3_is_in() {
    return !empty($_SESSION['is_admin']) && !empty($_SESSION['admin_otp_ok'])
        && in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true);
}
function admin3_require() {
    if (!admin3_is_in()) { header('Location: adminlogin.php'); exit; }
}
function admin3_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* sidebar links: key => [label, icon, page]. Add new admin pages here once. */
function admin3_links() {
    return [
        'dashboard' => ['Dashboard', '🏠', 'dashboard.php'],
        'orders'    => ['Orders',    '📦', 'orders.php'],
        'products'  => ['Products',  '🛒', 'products.php'],
        'users'     => ['Customers', '🧑‍💻', 'users.php'],
        'admins'    => ['Admins',    '👥', 'admins.php'],
        'settings'  => ['Site settings', '⚙️', 'admin_settings3.php'],
    ];
}

function admin3_css() { return <<<'CSS'
:root{--brown:#6B3E26;--brown-deep:#3B2317;--brown-soft:#8A5A3F;--espresso:#2B1810;--gold:#F4B63D;--orange-cta:#C9501A;
  --cream:#FFF7ED;--cream-2:#F7EADB;--ink:#17110D;--muted:#5B4B41;--line:#EDE2D6;--green:#2F6B4F;--red:#D9363E;
  --sans:'Figtree',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;--serif:'Young Serif',Georgia,serif;
  --shadow:0 1px 2px rgba(59,35,23,.06),0 8px 24px -12px rgba(59,35,23,.18)}
*{box-sizing:border-box}
body.a3{margin:0;font-family:var(--sans);font-weight:500;color:var(--ink);background:#F6F1EA}
.a3 h1,.a3 h2,.a3 h3{font-family:var(--serif);color:var(--brown);margin:0}
.a3 a{color:var(--brown)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:46px;padding:0 22px;border-radius:999px;border:2px solid transparent;
  font:700 15px var(--sans);text-decoration:none;cursor:pointer;transition:transform .15s,background .2s}
.btn-primary{background:linear-gradient(135deg,#D9601F,var(--orange-cta));color:#fff;box-shadow:0 8px 18px -8px rgba(201,80,26,.8)}
.btn-primary:hover{transform:translateY(-1px);background:linear-gradient(135deg,var(--orange-cta),#A9410F)}
.btn-outline{border-color:var(--brown);color:var(--brown);background:#fff}
.btn-outline:hover{background:var(--brown);color:#fff}
.btn-lg{min-height:54px;padding:0 30px;font-size:16px}
.btn[disabled]{opacity:.55;cursor:not-allowed;transform:none}
/* ---------- login / code / signup pages ---------- */
.a3-auth{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px 16px;
  background:radial-gradient(120% 80% at 0% 0%,#6B4226 0%,rgba(107,66,38,0) 55%),linear-gradient(160deg,#2B1810 0%,#1F120C 100%)}
.a3 .a3-brand{display:flex;align-items:center;gap:10px;margin-bottom:22px;color:#FFF4E2;font:900 22px var(--sans);letter-spacing:.04em;text-decoration:none}
.a3-brand small{display:block;font-size:12px;font-weight:700;letter-spacing:.12em;color:var(--gold);text-transform:uppercase}
.a3-card{width:100%;max-width:420px;background:#fff;border-radius:22px;padding:30px 28px;border-top:6px solid var(--gold);box-shadow:0 30px 70px -30px rgba(0,0,0,.7)}
.a3-card h1{font-size:1.8rem;text-align:center}
.a3-card .sub{margin:6px 0 20px;text-align:center;color:var(--muted);font-size:14.5px}
.a3-field{margin-bottom:14px}
.a3-field label{display:block;font-weight:800;font-size:14px;margin-bottom:6px;color:var(--brown-deep)}
.a3-field input{width:100%;height:48px;padding:0 14px;border:2px solid var(--line);border-radius:12px;font:600 15px var(--sans);background:#FFFCF8}
.a3-field input:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 4px rgba(244,182,61,.25)}
.a3-card .btn{width:100%;margin-top:6px}
.a3-msg{padding:11px 14px;border-radius:12px;margin-bottom:14px;font-weight:700;font-size:14px}
.a3-msg.err{background:#FFE8E3;color:#B4410F;border:1px solid #F6C7B8}
.a3-msg.ok{background:#E6F5EC;color:#1F7A4D;border:1px solid #BFE3CC}
.a3-links{margin-top:16px;text-align:center;font-size:14px}
.a3-links a{font-weight:800;text-decoration:none}
.a3-links a:hover{text-decoration:underline}
.a3-cap{display:flex;gap:8px;align-items:center}
.a3-cap img{height:48px;border:2px solid var(--line);border-radius:12px;flex:1;min-width:0;cursor:pointer}
.a3-cap button{flex:none;width:48px;height:48px;border:2px solid var(--line);border-radius:12px;background:#fff;cursor:pointer;font-size:18px}
.a3-foot{margin-top:18px;color:#CBB7A6;font-size:12.5px}
/* ---------- dashboard layout: sidebar + content ---------- */
.a3-side{position:fixed;inset:0 auto 0 0;width:256px;overflow-y:auto;z-index:20;color:#EADDC9;
  background:linear-gradient(180deg,#2B1810 0%,#1F120C 100%);box-shadow:8px 0 30px -12px rgba(0,0,0,.5)}
.a3-side .top{padding:22px 20px;border-bottom:1px solid rgba(255,255,255,.1)}
.a3-side .top b{display:block;color:#fff;font-size:21px;font-weight:900}
.a3-side .top small{color:#D9C3A1;font-size:12.5px}
.a3-side nav{padding:12px 10px;display:flex;flex-direction:column;gap:4px}
.a3-side nav a{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:12px;color:#EADDC9;text-decoration:none;font-weight:700;font-size:15px}
.a3-side nav a:hover{background:rgba(255,255,255,.08);color:#fff}
.a3-side nav a[aria-current="page"]{background:linear-gradient(135deg,#8A4B2A,#6B3E26);color:#fff;box-shadow:inset 3px 0 0 var(--gold)}
.a3-side nav .sep{height:1px;margin:10px 6px;background:rgba(255,255,255,.1)}
.a3-side nav a.out{color:#FFB1A3}
.a3-side nav a.out:hover{background:rgba(217,54,62,.25)}
.a3-main{margin-left:256px;padding:28px 32px 60px;min-height:100vh}
@media (max-width:900px){
  .a3-side{position:static;width:auto}
  .a3-side nav{flex-direction:row;flex-wrap:wrap}
  .a3-main{margin-left:0;padding:20px 16px 50px}
}
CSS; }

function admin3_head($title) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>' . admin3_e($title) . '</title>'
       . '<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800;900&family=Young+Serif&display=swap" rel="stylesheet">'
       . '<style>' . admin3_css() . '</style>';
}
/* login-style pages: centred card on an espresso background */
function admin3_auth_open($title) {
    admin3_head($title);
    echo '</head><body class="a3"><div class="a3-auth"><a class="a3-brand" href="index.php">☕<span>ARAKU TRIBE<small>Admin</small></span></a><div class="a3-card">';
}
function admin3_auth_close() {
    echo '</div><p class="a3-foot">© ' . date('Y') . ' Araku Tribe · Admin area</p></div></body></html>';
}
/* the sidebar, with $active highlighted */
function admin3_sidebar($active) {
    $role = $_SESSION['role'] ?? '';
    echo '<aside class="a3-side"><div class="top"><b>☕ ArakuTribe</b><small>Role: ' . admin3_e(ucfirst($role)) . '</small></div><nav aria-label="Admin">';
    foreach (admin3_links() as $k => [$label, $icon, $href])
        echo '<a href="' . admin3_e($href) . '"' . ($k === $active ? ' aria-current="page"' : '') . '><span aria-hidden="true">' . $icon . '</span>' . admin3_e($label) . '</a>';
    echo '<span class="sep"></span><a href="index.php" target="_blank" rel="noopener"><span aria-hidden="true">🌐</span>View the site</a>'
       . '<a class="out" href="logout_admin.php"><span aria-hidden="true">🚪</span>Log out</a></nav></aside>';
}