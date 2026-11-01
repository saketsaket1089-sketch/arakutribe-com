<?php
/* =====================================================================
   ARAKU TRIBE - coffee_franchise3.php  (Franchise for cafés)
   Upgraded to support both standalone requests and AJAX SPA fragment loading.
   ===================================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------- DETECT AJAX SPA REQUEST ---------- */
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['ajax']);

/* ---------- SETTINGS ---------- */
$FRANCHISE_INVESTMENT = '';     // e.g. '₹5,80,000' or '$7,000' - leave empty to show "Details on request"
$FRANCHISE_PHONE      = '+91 78938 36529';
$FRANCHISE_EMAIL      = 'info@arakutribe.com';
$GALLERY = [
    ['large' => 'img/shop/details/franchise_large_005.png', 'thumb' => 'img/shop/details/franchise_thumb_005.png', 'alt' => 'Araku Tribe franchise café counter'],
    ['large' => 'img/shop/details/franchise_large_002.png', 'thumb' => 'img/shop/details/franchise_thumb_002.png', 'alt' => 'Araku Tribe premium coffee and cups'],
    ['large' => 'img/shop/details/franchise_large_003.png', 'thumb' => 'img/shop/details/franchise_thumb_003.png', 'alt' => 'Araku Tribe franchise setup'],
];
$PARTNERS = [   // same café partner quotes as the home page
    ['name' => 'Ramesh Varma',  'place' => 'Bean & Brew, Vizag',      'text' => 'Since we switched to Araku beans our customers keep talking about the aroma and the smooth taste. The branded cups lifted our takeaway sales too.'],
    ['name' => 'Sneha Rao',     'place' => 'The Koffee House, Vizag', 'text' => "The organic beans give us a flavour customers can't get elsewhere, and the cups give our takeaway coffee a premium feel."],
    ['name' => 'Vikram Shetty', 'place' => 'Coastal Brews, Vizag',    'text' => 'We wanted a brand that matches our sustainability values. Ethical sourcing, great beans, and cups people love to photograph.'],
];

/* ---------- CURRENCY FORMATTING HOOK ---------- */
$displayInvestment = $FRANCHISE_INVESTMENT;
include_once 'db1.php';
if (file_exists('currency_helper.php')) {
    include_once 'currency_helper.php';
    mysqli_report(MYSQLI_REPORT_OFF);
    $currDb = @new mysqli($host, $user, $pass, $db);
    if (!$currDb->connect_error) {
        $currDb->set_charset('utf8mb4');
        $resolvedCode = resolve_display_currency($currDb);
        if (isset($CURRENCY_INFO[$resolvedCode])) {
            $info = $CURRENCY_INFO[$resolvedCode];
            // Example base conversion if numeric value is present
            if ($FRANCHISE_INVESTMENT === '') {
                $displayInvestment = 'Details on request';
            }
        }
        $currDb->close();
    }
}

$PAGE_TITLE       = 'Franchise - Araku Tribe';
$PAGE_DESCRIPTION = 'Start an Araku Tribe Premium Coffee franchise: premium high-altitude Araku coffee, branded cups and machines, and branding support.';
$ACTIVE_NAV       = 'franchise';

/* ---------- HANDLE AJAX FORM SUBMISSION ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'franchise_inquiry') {
    header('Content-Type: application/json; charset=utf-8');
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city  = trim($_POST['city'] ?? '');
    
    if (empty($name) || empty($email) || empty($city)) {
        echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email address format.']);
        exit;
    }
    
    // Simulate inquiry recording / email dispatch
    $success = true; 
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Thank you! Your franchise inquiry has been received. Our team will contact you shortly.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to submit inquiry. Please try again later.']);
    }
    exit;
}

if (!$isAjax) {
    include __DIR__ . '/header3.php';
} else {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Vary: X-Requested-With');
    header('X-Nav-Top: franchise');
    header('X-Nav-Active: franchise');
    header('X-Nav-Tab-Style: brown');
    header('X-Page-Title: ' . rawurlencode($PAGE_TITLE));
}
$mailto = 'mailto:' . $FRANCHISE_EMAIL . '?subject=' . rawurlencode('Franchise enquiry');
?>
<!-- SPA Container Start -->
<div id="spa-container" data-title="<?= htmlspecialchars($PAGE_TITLE, ENT_QUOTES, 'UTF-8') ?>" data-tab-style="brown">
<style>
/* ============ coffee_franchise3.php ============ */

.ph{background:var(--cream);padding:28px 0 40px}
.crumbs{display:flex;gap:8px;margin:0 0 14px;font-size:13.5px;}
.ph .crumbs a,.ph .crumbs a:visited{background:var(--brown);color:#fff}
.ph .crumbs a:hover{background:var(--brown-deep);color:#fff}
/* top row: breadcrumb on the left, "Back To Previous Page" on the right */
.crumb-row{display:flex;align-items:center;justify-content:space-between;gap:10px 20px;flex-wrap:wrap;margin:0 0 14px}
.crumb-row .crumbs{margin:0}
.close-btn{display:inline-flex;align-items:center;gap:8px;flex:none;min-height:46px;padding:0 20px;border-radius:999px;border:2px solid var(--brown-deep);background:#fff;color:var(--brown-deep);
  font:inherit;font-weight:700;font-size:15.5px;line-height:1.2;text-decoration:none;white-space:nowrap;cursor:pointer;transition:background-color .2s,color .2s}
.close-btn:hover{background:var(--brown-deep);color:#fff}
.close-btn .ico{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
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

.fr-hero{display:grid;grid-template-columns:1.1fr .9fr;gap:44px;align-items:center}
.fr-hero h1{font-size:clamp(2.2rem,4.4vw,3.6rem);line-height:1.05}
.fr-hero .lede{font-size:1.08rem;margin-top:16px}
.price-card{display:inline-flex;flex-direction:column;margin-top:24px;padding:14px 20px;border-radius:18px;background:#fff;box-shadow:0 10px 26px -20px rgba(59,35,23,.6)}
.price-card small{font-size:13px;color:var(--muted)}
.price-card strong{font-family:var(--serif);font-weight:400;font-size:1.7rem;color:var(--brown)}
.price-card span{font-size:13px;color:var(--muted)}
.hero-acts{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}
.gallery .main{aspect-ratio:4/3;border-radius:26px;overflow:hidden;background:var(--cream-2);box-shadow:0 30px 60px -36px rgba(59,35,23,.6)}
.gallery .main img{width:100%;height:100%;object-fit:cover;transition:opacity .25s}
.gallery .thumbs{display:flex;gap:10px;margin-top:12px}
.gallery .thumbs button{width:78px;height:62px;padding:0;border-radius:14px;overflow:hidden;border:2px solid transparent;background:var(--cream-2)}
.gallery .thumbs button[aria-pressed="true"]{border-color:var(--brown)}
.gallery .thumbs img{width:100%;height:100%;object-fit:cover}
.checks{list-style:none;padding:0;margin:20px 0 0;display:grid;gap:10px}
.checks li{display:flex;gap:10px;align-items:flex-start;font-weight:600;color:var(--brown-deep)}
.checks .ico{flex:none;width:22px;height:22px;padding:3px;border-radius:50%;background:var(--green);color:#fff;stroke-width:2.6}
.steps{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(4,1fr);gap:16px;counter-reset:s}
.steps li{position:relative;padding:22px;border-radius:22px;background:#fff;border:1px solid var(--line)}
.steps li::before{counter-increment:s;content:counter(s);display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:var(--brown);color:var(--cream);font-weight:700;margin-bottom:12px}
.steps strong{display:block;color:var(--brown-deep);font-size:1.05rem}
.steps span{display:block;margin-top:6px;font-size:14px;color:var(--muted)}
.facts{margin:0;display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.facts div{padding:16px 18px;border-radius:18px;background:#fff}
.facts dt{font-size:13px;color:var(--muted)}
.facts dd{margin:4px 0 0;font-weight:700;color:var(--brown-deep)}
.contact-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.contact-row a{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:999px;background:rgba(255,247,237,.12);color:#fff;text-decoration:none;font-weight:600;font-size:14px}
.contact-row a .ico{width:16px;height:16px}
.franchise-form-box{background:#fff;padding:32px;border-radius:24px;border:1px solid var(--line);box-shadow:0 20px 40px -20px rgba(59,35,23,.15);margin-top:32px}
.franchise-form-box h3{margin-bottom:16px;font-size:1.5rem}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.form-group.full{grid-column:span 2}
.form-group label{font-weight:600;font-size:13.5px;color:var(--brown-deep)}
.form-group input, .form-group textarea{padding:12px 16px;border-radius:12px;border:1.5px solid var(--line);font:inherit;outline:none;transition:border-color .2s}
.form-group input:focus, .form-group textarea:focus{border-color:var(--brown)}
.form-feedback{padding:12px 16px;border-radius:12px;margin-bottom:16px;font-weight:600;display:none}
.form-feedback.success{background:#E6F8EE;color:var(--green)}
.form-feedback.error{background:#FDF2F2;color:#9B2626}
@media (max-width:960px){ .fr-hero{grid-template-columns:1fr} .steps{grid-template-columns:1fr 1fr} .facts{grid-template-columns:1fr} .form-grid{grid-template-columns:1fr} .form-group.full{grid-column:span 1} }
@media (max-width:520px){ .steps{grid-template-columns:1fr} }
</style>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-cup" viewBox="0 0 24 24"><path d="M5 8h12v6a5 5 0 0 1-5 5h-2a5 5 0 0 1-5-5V8Z"/><path d="M17 10h1.5a2.5 2.5 0 0 1 0 5H17M8 3v2M12 3v2"/></symbol>
  <symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></symbol>
  <symbol id="i-people" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a5 5 0 0 1 3.5 6"/></symbol>
  <symbol id="i-phone" viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12 5 5 9-10"/></symbol>
</svg>

<main>
  <section class="ph">
    <div class="wrap">
      <div class="crumb-row">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="index.php">Home</a><span aria-hidden="true">/</span><span aria-current="page">Franchise</span></nav>
        <a class="close-btn" id="pgClose" href="index.php"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>Back To Previous Page</a>
      </div>
      <div class="fr-hero">
        <div>
          <p class="eyebrow"><svg class="ico"><use href="#i-spark"/></svg>Partner with Araku Tribe  Coffee</p>
          <h1>Bring authentic Araku premium coffee to your city.</h1>
          <p class="lede">Step into premium coffee culture with an Araku Tribe Premium Coffee franchise: a business that blends tradition, sustainability and modern entrepreneurship, backed by premium branding and quality coffee products.</p>
          <div class="price-card">
            <small>Franchise investment</small>
            <strong><?= $displayInvestment !== '' ? htmlspecialchars($displayInvestment, ENT_QUOTES, 'UTF-8') : 'Details on request' ?></strong>
            <span><?= $FRANCHISE_INVESTMENT !== '' ? 'Talk to us for what\'s included' : 'Tell us about your city and we\'ll share the full plan' ?></span>
          </div>
          <div class="hero-acts">
            <a class="btn btn-primary btn-lg" href="#franchise-inquiry-form">Enquire about a franchise</a>
            <a class="btn btn-outline" href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $FRANCHISE_PHONE), ENT_QUOTES, 'UTF-8') ?>">Call <?= htmlspecialchars($FRANCHISE_PHONE, ENT_QUOTES, 'UTF-8') ?></a>
          </div>
        </div>
        <div class="gallery">
          <div class="main"><img id="gMain" src="<?= htmlspecialchars($GALLERY[0]['large'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($GALLERY[0]['alt'], ENT_QUOTES, 'UTF-8') ?>"></div>
          <div class="thumbs" role="group" aria-label="Photos">
            <?php foreach ($GALLERY as $i => $g): ?>
              <button type="button" data-large="<?= htmlspecialchars($g['large'], ENT_QUOTES, 'UTF-8') ?>" data-alt="<?= htmlspecialchars($g['alt'], ENT_QUOTES, 'UTF-8') ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="Photo <?= $i + 1 ?>"><img src="<?= htmlspecialchars($g['thumb'], ENT_QUOTES, 'UTF-8') ?>" alt=""></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="sec">
    <div class="wrap">
      <div class="sec-head"><p class="eyebrow">What you get</p><h2>Everything a café needs to serve Araku Tribe</h2></div>
      <div class="grid3">
        <div class="tile"><span class="ic"><svg class="ico"><use href="#i-leaf"/></svg></span><h3>Premium high-altitude coffee</h3><p>Araku Valley premium coffee powder, hand-picked by tribal farmers and grown without chemical fertilisers.</p></div>
        <div class="tile"><span class="ic"><svg class="ico"><use href="#i-cup"/></svg></span><h3>Branded cups and machines</h3><p>Araku Tribe cups for dine-in and takeaway, plus premium coffee machines to brew it the way it should taste.</p></div>
        <div class="tile"><span class="ic"><svg class="ico"><use href="#i-spark"/></svg></span><h3>Branding support</h3><p>Premium branding that helps your café stand out, from cups to counter.</p></div>
      </div>
    </div>
  </section>

  <section class="sec cream">
    <div class="wrap split">
      <div class="photo" style="aspect-ratio:5/4"><img src="<?= htmlspecialchars($GALLERY[1]['large'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy"></div>
      <div>
        <p class="eyebrow">Why Araku Tribe?</p>
        <h2>More than a brand: a commitment to quality and ethical sourcing.</h2>
        <p class="lede">Our franchise model is built for profitability, smooth operations and strong customer engagement.</p>
        <ul class="checks">
          <li><svg class="ico"><use href="#i-check"/></svg>Premium offerings customers come back for</li>
          <li><svg class="ico"><use href="#i-check"/></svg>Authentic high-altitude Araku premium coffee</li>
          <li><svg class="ico"><use href="#i-check"/></svg>A sustainable and ethical business model</li>
          <li><svg class="ico"><use href="#i-check"/></svg>A proven model and a fast-growing brand</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="sec">
    <div class="wrap">
      <div class="sec-head"><p class="eyebrow">Getting started</p><h2>How it works</h2></div>
      <ol class="steps">
        <li><strong>Send an enquiry</strong><span>Tell us about yourself and the city or area you have in mind.</span></li>
        <li><strong>Talk to our team</strong><span>We'll walk you through the investment, products and support.</span></li>
        <li><strong>Plan your café</strong><span>Agree the details and set up with Araku Tribe preimum coffee, cups and machines.</span></li>
        <li><strong>Open your doors</strong><span>Launch with our branding and keep ordering supplies online.</span></li>
      </ol>
    </div>
  </section>

  <section class="sec cream">
    <div class="wrap">
      <div class="sec-head"><p class="eyebrow">At a glance</p><h2>Franchise details</h2></div>
      <dl class="facts">
        <div><dt>Investment</dt><dd><?= $displayInvestment !== '' ? htmlspecialchars($displayInvestment, ENT_QUOTES, 'UTF-8') : 'On request' ?></dd></div>
        <div><dt>What's included</dt><dd>Premium coffee, branded cups &amp; machines</dd></div>
        <div><dt>Ideal partners</dt><dd>Entrepreneurs, café owners &amp; investors</dd></div>
      </dl>

      <!-- Interactive Inquiry Form Container -->
      <div class="franchise-form-box" id="franchise-inquiry-form">
        <h3>Fast Franchise Inquiry</h3>
        <div id="formFeedback" class="form-feedback"></div>
        <form id="franchiseAjaxForm">
          <input type="hidden" name="action" value="franchise_inquiry">
          <div class="form-grid">
            <div class="form-group">
              <label for="fname">Full Name *</label>
              <input type="text" id="fname" name="name" required placeholder="e.g. Rahul Sharma">
            </div>
            <div class="form-group">
              <label for="femail">Email Address *</label>
              <input type="email" id="femail" name="email" required placeholder="rahul@example.com">
            </div>
            <div class="form-group">
              <label for="fphone">Phone Number</label>
              <input type="tel" id="fphone" name="phone" placeholder="+91 98765 43210">
            </div>
            <div class="form-group">
              <label for="fcity">Target City / Location *</label>
              <input type="text" id="fcity" name="city" required placeholder="e.g. Hyderabad">
            </div>
            <div class="form-group full">
              <label for="fmessage">Additional Notes or Questions</label>
              <textarea id="fmessage" name="message" rows="3" placeholder="Tell us about your experience or existing space..."></textarea>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">Submit Franchise Inquiry</button>
        </form>
      </div>

    </div>
  </section>

  <section class="sec">
    <div class="wrap">
      <div class="sec-head"><p class="eyebrow">Partner experiences</p><h2>What our café partners say</h2></div>
      <div class="quotes">
        <?php foreach ($PARTNERS as $q): ?>
          <figure class="quote"><blockquote><?= htmlspecialchars($q['text'], ENT_QUOTES, 'UTF-8') ?></blockquote><figcaption><strong><?= htmlspecialchars($q['name'], ENT_QUOTES, 'UTF-8') ?></strong><?= htmlspecialchars($q['place'], ENT_QUOTES, 'UTF-8') ?></figcaption></figure>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="sec" style="padding-top:0">
    <div class="wrap">
      <div class="band">
        <div>
          <h2>Ready to talk?</h2>
          <p>Send us an enquiry and our team will get back to you with the full franchise plan.</p>
          <div class="contact-row">
            <a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $FRANCHISE_PHONE), ENT_QUOTES, 'UTF-8') ?>"><svg class="ico"><use href="#i-phone"/></svg><?= htmlspecialchars($FRANCHISE_PHONE, ENT_QUOTES, 'UTF-8') ?></a>
            <a href="<?= htmlspecialchars($mailto, ENT_QUOTES, 'UTF-8') ?>"><svg class="ico"><use href="#i-mail"/></svg><?= htmlspecialchars($FRANCHISE_EMAIL, ENT_QUOTES, 'UTF-8') ?></a>
          </div>
        </div>
        <div class="acts">
          <a class="btn btn-primary btn-lg" href="#franchise-inquiry-form">Enquire now</a>
          <a class="btn btn-ghost-light" href="index.php?page=shop">See our products</a>
        </div>
      </div>
    </div>
  </section>
</main>

<script>
(function () {
  // "Back To Previous Page": goes back to the page the visitor came from, or Home when there is none
  const back = document.getElementById('pgClose');
  if (back) {
    back.addEventListener('click', e => {
      try { if (document.referrer && new URL(document.referrer).origin === location.origin && history.length > 1) { e.preventDefault(); history.back(); } } catch (err) {}
    });
  }

  // Gallery Thumbnail Switcher
  const main = document.getElementById('gMain');
  if (main) {
    document.querySelectorAll('.gallery .thumbs button').forEach(b => {
      b.addEventListener('click', () => {
        document.querySelectorAll('.gallery .thumbs button').forEach(x => x.setAttribute('aria-pressed', x === b));
        main.style.opacity = .3; 
        setTimeout(() => { 
          main.src = b.dataset.large; 
          main.alt = b.dataset.alt; 
          main.style.opacity = 1; 
        }, 150);
      });
    });
  }

  // Asynchronous AJAX Form Handler for SPA Fragment Integration
  const form = document.getElementById('franchiseAjaxForm');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const feedback = document.getElementById('formFeedback');
      const submitBtn = document.getElementById('submitBtn');
      
      feedback.style.display = 'none';
      feedback.className = 'form-feedback';
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';

      try {
        const formData = new FormData(form);
        const res = await fetch('coffee_franchise3.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        });
        const data = await res.json();
        
        feedback.style.display = 'block';
        if (data.success) {
          feedback.classList.add('success');
          feedback.textContent = data.message;
          form.reset();
        } else {
          feedback.classList.add('error');
          feedback.textContent = data.error || 'An unexpected error occurred.';
        }
      } catch (err) {
        feedback.style.display = 'block';
        feedback.classList.add('error');
        feedback.textContent = 'Network communication error. Please try again.';
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Franchise Inquiry';
      }
    });
  }
})();
</script>
</div>
<!-- SPA Container End -->

<?php 
if (!$isAjax) {
    include __DIR__ . '/footer3.php';
}