<?php
/* =====================================================================
   ARAKU TRIBE - coffee_contact3.php  (Contact us)
   Contact details from coffee_contact.php plus a working message form
   (the old form was commented out). Each message is emailed to
   $CONTACT_TO with Reply-To set to the customer, and the customer gets a
   confirmation email. Also used by the "Help & contact" panel in the
   header (ajax=1 -> JSON).
   Spam protection: hidden trap field, security token, 5 messages per
   hour per visitor. Uses header3.php + footer3.php.
   ===================================================================== */
session_start();
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ---------- SETTINGS ---------- */
// Same mail account as coffee_login3.php. Please change this mailbox password (it was shared in a chat).
$SMTP = ['host' => 'aiinfoways.com', 'username' => 'support@hayward.university', 'password' => 'zh9nCzzB1ds_',
         'secure' => 'tls', 'port' => 587, 'from_email' => 'info@aiinfoways.com', 'from_name' => 'Araku Tribe'];
$CONTACT_TO    = 'info@arakutribe.com';          // where messages are delivered
$PHONE         = '+91 78938 36529';
$EMAIL_PUBLIC  = 'info@arakutribe.com';
$ADDRESS       = "Araku Tribe Coffee\nAraku Valley, Visakhapatnam";
$REPLY_PROMISE = 'We usually reply within 24 hours.';
$MAX_PER_HOUR  = 5;
$TOPICS = ['general' => 'General question', 'order' => 'Help with an order', 'bulk' => 'Bulk or café orders',
           'franchise' => 'Franchise', 'feedback' => 'Feedback'];

if (empty($_SESSION['csrf3'])) $_SESSION['csrf3'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf3'];
$isAjax = (($_POST['ajax'] ?? '') === '1');

function ct3_mail($SMTP, $to, $subject, $html, $text, $replyTo = null, $replyName = '') {
    $m = new PHPMailer(true);
    try {
        $m->isSMTP(); $m->Host = $SMTP['host']; $m->SMTPAuth = true; $m->Username = $SMTP['username']; $m->Password = $SMTP['password'];
        $m->SMTPSecure = $SMTP['secure']; $m->Port = $SMTP['port']; $m->CharSet = 'UTF-8';
        $m->setFrom($SMTP['from_email'], $SMTP['from_name']);
        $m->addAddress($to);
        if ($replyTo) $m->addReplyTo($replyTo, $replyName);
        $m->isHTML(true); $m->Subject = $subject; $m->Body = $html; $m->AltBody = $text;
        $m->send(); return true;
    } catch (Exception $e) { error_log('Contact mail failed: ' . $m->ErrorInfo); return false; }
}
function ct3_shell($title, $inner) {
    return '<!doctype html><html><body style="margin:0;background:#F7EADB"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7EADB;padding:28px 12px"><tr><td align="center">'
         . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:20px;overflow:hidden">'
         . '<tr><td style="background:#3B2317;padding:20px 26px"><span style="font-family:Georgia,serif;font-size:21px;color:#FFF7ED">Araku Tribe</span></td></tr>'
         . '<tr><td style="padding:26px;font-family:Arial,Helvetica,sans-serif;color:#242424;font-size:15px;line-height:1.6">'
         . '<h1 style="margin:0 0 12px;font-family:Georgia,serif;font-weight:400;font-size:23px;color:#6B3E26">' . $title . '</h1>' . $inner
         . '</td></tr></table></td></tr></table></body></html>';
}

$f = ['name' => '', 'email' => '', 'phone' => '', 'topic' => (string)($_GET['topic'] ?? 'general'), 'order_no' => '', 'message' => ''];
if (!isset($TOPICS[$f['topic']])) $f['topic'] = 'general';
if (!empty($_SESSION['user_id'])) { $f['name'] = (string)($_SESSION['user_name'] ?? ''); $f['email'] = (string)($_SESSION['user_email'] ?? ''); }
$errors = []; $formError = ''; $sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['name', 'email', 'phone', 'topic', 'order_no', 'message'] as $k) $f[$k] = trim((string)($_POST[$k] ?? ''));
    if (!isset($TOPICS[$f['topic']])) $f['topic'] = 'general';
    $bot = trim((string)($_POST['website'] ?? '')) !== '';                 // hidden trap field: people never see it
    $recent = array_filter($_SESSION['contact_sent'] ?? [], fn($t) => $t > time() - 3600);

    if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) $formError = 'Your session expired. Please refresh the page and try again.';
    elseif (count($recent) >= $MAX_PER_HOUR) $formError = 'You\'ve sent a few messages already. Please wait a little, or email us at ' . $EMAIL_PUBLIC . '.';
    if ($f['name'] === '' || strlen($f['name']) > 80) $errors['name'] = 'Please tell us your name.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email so we can reply.';
    if ($f['phone'] !== '' && !preg_match('/^[0-9+\s\-()]{7,20}$/', $f['phone'])) $errors['phone'] = 'Please check your phone number.';
    if (strlen($f['message']) < 10) $errors['message'] = 'Please write a little more (at least 10 characters).';
    elseif (strlen($f['message']) > 3000) $errors['message'] = 'Please keep your message under 3000 characters.';
    if ($f['topic'] === 'order' && $f['order_no'] !== '' && !preg_match('/^#?\d{1,10}$/', $f['order_no'])) $errors['order_no'] = 'Order numbers are digits only, e.g. 1042.';

    if (!$errors && !$formError) {
        if ($bot) { $sent = true; }                                        // pretend it worked; send nothing
        else {
            $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
            $topic = $TOPICS[$f['topic']];
            $rows = '';
            foreach ([['Name', $f['name']], ['Email', $f['email']], ['Phone', $f['phone']], ['Topic', $topic], ['Order number', $f['order_no']],
                      ['Signed-in account', !empty($_SESSION['user_id']) ? 'Yes (user #' . (int)$_SESSION['user_id'] . ')' : 'No']] as [$k, $v]) {
                if ($v === '') continue;
                $rows .= '<tr><td style="padding:6px 12px 6px 0;color:#77716C;white-space:nowrap">' . $k . '</td><td style="padding:6px 0">' . $e($v) . '</td></tr>';
            }
            $html = ct3_shell('New message: ' . $e($topic), '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px">' . $rows . '</table>'
                  . '<div style="margin-top:16px;padding:14px 16px;border-radius:12px;background:#FFF7ED;white-space:pre-line">' . $e($f['message']) . '</div>'
                  . '<p style="margin:16px 0 0;font-size:13px;color:#77716C">Reply to this email to answer ' . $e($f['name']) . ' directly.</p>');
            $text = "New message ($topic)\nName: {$f['name']}\nEmail: {$f['email']}\nPhone: {$f['phone']}\nOrder: {$f['order_no']}\n\n{$f['message']}";
            $okBiz = ct3_mail($SMTP, $CONTACT_TO, "[$topic] Message from {$f['name']}", $html, $text, $f['email'], $f['name']);
            if ($okBiz) {
                $first = $e(trim(explode(' ', $f['name'])[0]));
                ct3_mail($SMTP, $f['email'], 'We\'ve got your message - Araku Tribe',
                    ct3_shell("Thanks, $first. We've got your message.", '<p style="margin:0 0 12px;color:#5E5751">' . $e($REPLY_PROMISE) . ' Here\'s a copy of what you sent:</p>'
                      . '<div style="padding:14px 16px;border-radius:12px;background:#FFF7ED;white-space:pre-line;font-size:14px">' . $e($f['message']) . '</div>'
                      . '<p style="margin:16px 0 0;font-size:13.5px;color:#5E5751">Need us sooner? Call ' . $e($PHONE) . '.</p>'),
                    "Thanks for your message. {$REPLY_PROMISE}\n\nYour message:\n{$f['message']}");
                $recent[] = time(); $_SESSION['contact_sent'] = array_values($recent);
                $sent = true;
            } else {
                $formError = "We couldn't send your message right now. Please email us at $EMAIL_PUBLIC or call $PHONE.";
            }
        }
    }
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($sent ? ['status' => 'success', 'message' => "Thanks! We've got your message. $REPLY_PROMISE"]
                               : ['status' => 'error', 'message' => $formError ?: 'Please check the highlighted fields.', 'errors' => $errors]);
        exit;
    }
    if ($sent) { $_SESSION['contact_done'] = trim(explode(' ', $f['name'])[0]); header('Location: coffee_contact3.php?sent=1'); exit; }
}
$doneName = null;
if (isset($_GET['sent']) && isset($_SESSION['contact_done'])) { $doneName = $_SESSION['contact_done']; unset($_SESSION['contact_done']); }

$PAGE_TITLE       = 'Contact us - Araku Tribe';
$PAGE_DESCRIPTION = 'Contact Araku Tribe Coffee about products, orders, bulk café orders or franchise.';
include __DIR__ . '/header3.php';
function ct3_err($e, $k) { return isset($e[$k]) ? '<p class="ferr" id="' . $k . 'Err">' . h3_e($e[$k]) . '</p>' : ''; }
function ct3_inv($e, $k) { return isset($e[$k]) ? ' aria-invalid="true" aria-describedby="' . $k . 'Err"' : ''; }
$tel = 'tel:' . preg_replace('/[^0-9+]/', '', $PHONE);
?>
<style>
/* ============ coffee_contact3.php ============ */

.ph{background:var(--cream);padding:28px 0 40px}
.crumbs{display:flex;gap:8px;margin:0 0 14px;font-size:13.5px;color:var(--muted)}
.crumbs a{color:var(--muted);text-decoration:none}
.crumbs a:hover{color:var(--brown)}
.crumbs span[aria-current]{color:var(--brown);font-weight:600}
.sec{padding:72px 0}
.sec.cream{background:var(--cream)}
.sec-head{max-width:720px;margin-bottom:32px}
.sec-head h2{font-size:clamp(1.8rem,3vw,2.4rem)}
.eyebrow{display:inline-flex;align-items:center;gap:8px;margin:0 0 10px;font-weight:600;font-size:14px;color:var(--orange-cta)}
.eyebrow .ico{width:16px;height:16px}
.split{display:grid;grid-template-columns:1.05fr .95fr;gap:48px;align-items:center}
.photo{border-radius:26px;overflow:hidden;background:var(--cream-2);box-shadow:0 30px 60px -36px rgba(59,35,23,.6)}
.photo img{width:100%;height:100%;object-fit:cover;display:block}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.tile{padding:22px;border-radius:22px;background:#fff;border:1px solid var(--line)}
.sec.cream .tile{border-color:transparent}
.tile .ic{width:44px;height:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:var(--cream);color:var(--brown);margin-bottom:14px}
.sec.cream .tile .ic{background:var(--cream-2)}
.tile .ic .ico{width:22px;height:22px}
.tile h3{font-size:1.2rem}
.tile p{margin:8px 0 0;color:var(--muted);font-size:14.5px;line-height:1.6}
.quotes{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.quote{margin:0;padding:24px;border-radius:22px;background:#fff;border:1px solid var(--line);display:flex;flex-direction:column}
.quote blockquote{margin:0 0 18px;font-family:var(--serif);font-size:1.08rem;line-height:1.55;color:var(--brown-deep)}
.quote figcaption{margin-top:auto;font-size:14px;color:var(--muted)}
.quote figcaption strong{display:block;color:var(--ink)}
.band{border-radius:30px;padding:44px;color:var(--cream);display:flex;align-items:center;justify-content:space-between;gap:28px;flex-wrap:wrap;
  background:radial-gradient(120% 90% at 100% 0%,#8A4F2B 0%,rgba(138,79,43,0) 55%),radial-gradient(90% 80% at 0% 100%,#2F6B4F 0%,rgba(47,107,79,0) 60%),var(--brown-deep)}
.band h2{color:#fff;font-size:clamp(1.8rem,3vw,2.4rem)}
.band p{margin:8px 0 0;color:#E6D5C4;max-width:56ch}
.band .acts{display:flex;gap:12px;flex-wrap:wrap}
.band .btn-ghost-light{border-color:rgba(255,247,237,.5)}
@media (max-width:960px){ .split{grid-template-columns:1fr;gap:28px} .grid3,.quotes{grid-template-columns:1fr} .band{padding:28px} }

.ct{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:28px;align-items:start;padding-top:36px;padding-bottom:88px}
.ct-hero h1{font-size:clamp(2.2rem,4.2vw,3.4rem);line-height:1.05}
.ct-hero .lede{max-width:56ch;margin-top:12px}
.ways{display:grid;gap:12px}
.way{display:flex;gap:14px;align-items:center;padding:16px 18px;border-radius:20px;border:1px solid var(--line);background:#fff;text-decoration:none;color:var(--ink);transition:border-color .2s,transform .2s}
a.way:hover{border-color:var(--brown-soft);transform:translateY(-2px)}
.way .ic{flex:none;width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:var(--cream);color:var(--brown)}
.way .ic .ico{width:22px;height:22px}
.way strong{display:block;color:var(--brown-deep)}
.way span{display:block;font-size:14px;color:var(--muted);white-space:pre-line}
.way em{margin-left:auto;font-style:normal;font-weight:700;font-size:13.5px;color:var(--orange-cta)}
.way.agent{background:linear-gradient(135deg,#3B2317,#6B3E26);border-color:transparent;color:#fff}
.way.agent strong{color:#fff}.way.agent span{color:#E6D5C4}.way.agent em{color:var(--gold)}
.way.agent .ic{background:none;padding:0}
.quick{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
.quick a{padding:8px 14px;border-radius:999px;background:var(--cream);text-decoration:none;font-weight:600;font-size:13.5px;color:var(--brown)}
.quick a:hover{background:var(--cream-2)}
.form-card{background:#fff;border:1px solid var(--line);border-radius:26px;padding:28px;box-shadow:0 30px 60px -44px rgba(59,35,23,.6)}
.form-card h2{font-size:1.6rem}
.form-card .sub{margin:6px 0 20px;color:var(--muted)}
.topics{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px;padding:0;border:0}
.topics legend{width:100%;margin:0 0 8px;font-weight:600;font-size:14px;color:var(--brown-deep)}
.topics label{cursor:pointer}
.topics input{position:absolute;opacity:0;pointer-events:none}
.topics span{display:inline-block;padding:9px 15px;border-radius:999px;border:1.5px solid var(--line);font-weight:600;font-size:14px;color:var(--ink);transition:all .15s}
.topics input:checked + span{background:var(--brown);border-color:var(--brown);color:#fff}
.topics input:focus-visible + span{outline:3px solid var(--gold);outline-offset:2px}
.two{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.field{margin-bottom:14px}
.field label{display:block;margin:0 0 6px;font-weight:600;font-size:14px;color:var(--brown-deep)}
.field label small{font-weight:500;color:var(--muted)}
.field input,.field textarea{width:100%;border:1.5px solid var(--line);border-radius:14px;padding:12px 14px;background:var(--cream);font:inherit;font-size:15.5px;color:var(--ink)}
.field input{height:50px}
.field textarea{min-height:150px;resize:vertical}
.field input:focus,.field textarea:focus{outline:none;border-color:var(--brown-soft);background:#fff}
.field [aria-invalid="true"]{border-color:#D98A8A;background:#FFF6F6}
.ferr{margin:6px 0 0;font-size:13px;font-weight:600;color:#9B2626}
.ct-count{float:right;font-weight:500;font-size:12.5px;color:var(--muted)}
.trap{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.flash{margin:0 0 16px;padding:12px 14px;border-radius:14px;font-weight:600;font-size:14.5px}
.flash.bad{background:#FBE4E4;color:#9B2626}
.form-card .btn-primary{width:100%;padding:15px;font-size:16px}
.fine{margin:10px 0 0;font-size:12.5px;color:var(--muted);text-align:center}
.done{text-align:center;padding:24px 8px}
.done .big{width:76px;height:76px;margin:0 auto 16px;border-radius:24px;display:flex;align-items:center;justify-content:center;background:var(--green-soft);color:var(--green)}
.done .big svg{width:34px;height:34px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.done p{color:var(--muted);margin:8px 0 20px}
@media (max-width:960px){ .ct{grid-template-columns:1fr} .form-card{padding:20px} }
@media (max-width:520px){ .two{grid-template-columns:1fr} }
</style>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-phone" viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></symbol>
</svg>

<main>
  <section class="ph ct-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>">Home</a><span aria-hidden="true">/</span><span aria-current="page">Contact us</span></nav>
      <h1>We'd love to hear from you.</h1>
      <p class="lede">Questions about our products, an order, bulk orders for your café or a franchise? Reach us any way you like. <?= h3_e($REPLY_PROMISE) ?></p>
    </div>
  </section>

  <div class="wrap ct">
    <div>
      <div class="ways">
        <a class="way" href="<?= h3_e($tel) ?>"><span class="ic"><svg class="ico"><use href="#i-phone"/></svg></span><span><strong>Call us</strong><?= h3_e($PHONE) ?></span><em>Call</em></a>
        <a class="way" href="mailto:<?= h3_e($EMAIL_PUBLIC) ?>"><span class="ic"><svg class="ico"><use href="#i-mail"/></svg></span><span><strong>Email us</strong><?= h3_e($EMAIL_PUBLIC) ?></span><em>Email</em></a>
        <a class="way agent" href="<?= h3_link('agent') ?>"><span class="ic"><span class="orb"><svg class="ico"><use href="#i-spark"/></svg></span></span><span><strong>Ask the AI Agent</strong><span>Track, cancel or place an order in one chat, any time</span></span><em>Open</em></a>
        <div class="way"><span class="ic"><svg class="ico"><use href="#i-pin"/></svg></span><span><strong>Visit us</strong><span><?= h3_e($ADDRESS) ?></span></span></div>
      </div>
      <div class="quick" aria-label="Quick links">
        <a href="<?= h3_link('orders') ?>">Track my order</a><a href="<?= h3_link('franchise') ?>">Franchise details</a><a href="<?= h3_link('combos') ?>">Bulk &amp; combos</a>
      </div>
    </div>

    <section class="form-card" aria-labelledby="formTitle">
    <?php if ($doneName !== null): ?>
      <div class="done" role="status">
        <div class="big"><svg viewBox="0 0 24 24"><path d="m5 12 5 5 9-10"/></svg></div>
        <h2 id="formTitle">Thanks<?= $doneName !== '' ? ', ' . h3_e($doneName) : '' ?>! We've got your message.</h2>
        <p><?= h3_e($REPLY_PROMISE) ?> We've also emailed you a copy.</p>
        <a class="btn btn-outline" href="coffee_contact3.php">Send another message</a>
      </div>
    <?php else: ?>
      <h2 id="formTitle">Send us a message</h2>
      <p class="sub">Fill this in and we'll reply by email.</p>
      <?php if ($formError): ?><p class="flash bad" role="alert"><?= h3_e($formError) ?></p><?php elseif ($errors): ?><p class="flash bad" role="alert">Please check the highlighted fields.</p><?php endif; ?>
      <form method="POST" action="coffee_contact3.php" id="ctForm" novalidate>
        <input type="hidden" name="csrf" value="<?= h3_e($CSRF) ?>">
        <div class="trap" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <fieldset class="topics"><legend>What's it about?</legend>
          <?php foreach ($TOPICS as $k => $label): ?><label><input type="radio" name="topic" value="<?= h3_e($k) ?>" <?= $f['topic'] === $k ? 'checked' : '' ?>><span><?= h3_e($label) ?></span></label><?php endforeach; ?>
        </fieldset>
        <div class="two">
          <div class="field"><label for="name">Your name</label><input id="name" name="name" value="<?= h3_e($f['name']) ?>" autocomplete="name" maxlength="80" required<?= ct3_inv($errors, 'name') ?>><?= ct3_err($errors, 'name') ?></div>
          <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= h3_e($f['email']) ?>" autocomplete="email" required<?= ct3_inv($errors, 'email') ?>><?= ct3_err($errors, 'email') ?></div>
        </div>
        <div class="two">
          <div class="field"><label for="phone">Phone <small>(optional)</small></label><input type="tel" id="phone" name="phone" value="<?= h3_e($f['phone']) ?>" autocomplete="tel" maxlength="20"<?= ct3_inv($errors, 'phone') ?>><?= ct3_err($errors, 'phone') ?></div>
          <div class="field" id="orderBox" <?= $f['topic'] === 'order' ? '' : 'hidden' ?>><label for="order_no">Order number <small>(if you have it)</small></label><input id="order_no" name="order_no" value="<?= h3_e($f['order_no']) ?>" inputmode="numeric" placeholder="e.g. 1042"<?= ct3_inv($errors, 'order_no') ?>><?= ct3_err($errors, 'order_no') ?></div>
        </div>
        <div class="field"><label for="message">Message <span class="ct-count" id="count">0 / 3000</span></label>
          <textarea id="message" name="message" maxlength="3000" required placeholder="How can we help?"<?= ct3_inv($errors, 'message') ?>><?= h3_e($f['message']) ?></textarea><?= ct3_err($errors, 'message') ?></div>
        <button type="submit" class="btn btn-primary" id="sendBtn">Send message</button>
        <p class="fine">We only use your details to reply to you. See our <a href="<?= h3_link('privacy') ?>">privacy policy</a>.</p>
      </form>
    <?php endif; ?>
    </section>
  </div>
</main>

<script>
(function () {
  const form = document.getElementById('ctForm'); if (!form) return;
  const box = document.getElementById('orderBox'), msg = document.getElementById('message'), count = document.getElementById('count');
  form.querySelectorAll('input[name="topic"]').forEach(r => r.addEventListener('change', () => { box.hidden = form.querySelector('input[name="topic"]:checked').value !== 'order'; }));
  const paint = () => { count.textContent = msg.value.length + ' / 3000'; }; msg.addEventListener('input', paint); paint();
  form.addEventListener('submit', e => {
    const need = ['name', 'email', 'message'].map(id => document.getElementById(id)).find(i => !i.value.trim());
    if (need) { e.preventDefault(); need.focus(); if (window.A3) A3.toast('Please fill in your name, email and message.'); return; }
    const b = document.getElementById('sendBtn'); b.disabled = true; b.textContent = 'Sending…';
  });
  const bad = document.querySelector('[aria-invalid="true"]'); if (bad) bad.focus();
})();
</script>

<?php include __DIR__ . '/footer3.php'; ?>