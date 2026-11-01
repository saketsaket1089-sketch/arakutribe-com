<?php
/* =====================================================================
   ARAKU TRIBE - terms_deletion3.php
   Same wording as the old page, in the new reading layout.
   Set $LAST_UPDATED to the date you last changed this text (the old page
   used the current month, so it always looked freshly updated).
   Uses header3.php + footer3.php.
   ===================================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
$LAST_UPDATED = '1 October 2026';
$GRACE_PERIOD_DAYS = 30;

$PAGE_TITLE = 'Account Deletion Terms - Araku Tribe';
include __DIR__ . '/header3.php';
?>
<style>
/* ============ terms_deletion3.php ============ */

.lg-hero{background:var(--cream);padding:28px 0 36px}
.crumbs{display:flex;gap:8px;margin:0 0 14px;font-size:13.5px;color:var(--muted)}
.crumbs a{color:var(--muted);text-decoration:none}
.crumbs a:hover{color:var(--brown)}
.crumbs span[aria-current]{color:var(--brown);font-weight:600}
.lg-hero h1{font-size:clamp(2rem,3.8vw,3rem)}
.lg-hero .meta{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.lg-hero .meta span{padding:7px 14px;border-radius:999px;background:#fff;font-size:13.5px;font-weight:600;color:var(--brown)}
.lg{display:grid;grid-template-columns:260px minmax(0,1fr);gap:44px;align-items:start;padding-top:36px;padding-bottom:88px}
.toc{position:sticky;top:100px;max-height:calc(100vh - 120px);overflow:auto;padding:18px;border:1px solid var(--line);border-radius:20px;background:#fff}
.toc p{margin:0 0 10px;font-weight:700;font-size:14px;color:var(--brown-deep)}
.toc ol{list-style:none;margin:0;padding:0;display:grid;gap:2px;counter-reset:t}
.toc a{display:block;padding:7px 10px;border-radius:10px;text-decoration:none;font-size:13.5px;color:var(--muted);line-height:1.35}
.toc a:hover{background:var(--cream);color:var(--brown)}
.toc a.on{background:var(--cream);color:var(--brown);font-weight:700}
.doc{max-width:760px;font-size:16px;line-height:1.75;color:#3F3A36}
.doc .intro{font-size:1.08rem;color:var(--brown-deep);margin:0 0 10px}
.doc section{padding-top:24px;scroll-margin-top:110px}
.doc h2{font-size:1.45rem;margin:0 0 8px;color:var(--brown)}
.doc h2 .n{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;margin-right:10px;border-radius:50%;background:var(--cream);font-family:var(--sans);font-size:14px;font-weight:700;vertical-align:3px}
.doc ul{padding-left:22px;margin:8px 0}
.doc li{margin:6px 0}
.doc a{color:var(--orange-cta);font-weight:600}
.doc strong{color:var(--brown-deep)}
.doc .note{margin:16px 0 0;padding:14px 16px;border-radius:14px;background:var(--cream);font-size:15px}
.doc-foot{display:flex;gap:10px;flex-wrap:wrap;margin-top:40px;padding-top:24px;border-top:1px solid var(--line)}
.toc-mobile{display:none}
@media (max-width:960px){
  .lg{grid-template-columns:1fr;gap:20px}
  .toc{display:none}
  .toc-mobile{display:block}
  .toc-mobile select{width:100%;height:48px;border:1.5px solid var(--line);border-radius:14px;padding:0 14px;background:#fff;font:inherit;font-size:15px}
}

</style>
<main>
  <section class="lg-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>">Home</a><span aria-hidden="true">/</span><a href="<?= h3_link('profile') ?>">My account</a><span aria-hidden="true">/</span><span aria-current="page">Account Deletion Terms</span></nav>
      <h1>Account Deletion Terms</h1>
      <div class="meta"><span>Last updated: <?= h3_e($LAST_UPDATED) ?></span><span><?= (int)$GRACE_PERIOD_DAYS ?>-day grace period</span></div>
    </div>
  </section>
  <div class="wrap lg">
    <nav class="toc" aria-label="On this page"><p>On this page</p><ol><li><a href="#s1">1. Overview</a></li><li><a href="#s2">2. Immediate deactivation</a></li><li><a href="#s3">3. The <?= (int)$GRACE_PERIOD_DAYS ?>-day grace period</a></li><li><a href="#s4">4. Our team may reach out</a></li><li><a href="#s5">5. What happens after <?= (int)$GRACE_PERIOD_DAYS ?> days</a></li><li><a href="#s6">6. What we retain, and why</a></li><li><a href="#s7">7. Orders placed as a guest</a></li><li><a href="#s8">8. Cancelling a deletion request manually</a></li><li><a href="#s9">9. Questions</a></li></ol></nav>
    <article class="doc">
      <div class="toc-mobile"><label class="sr-only" for="jump">Jump to a section</label><select id="jump"><option value="">Jump to a section…</option><option value="#s1">1. Overview</option><option value="#s2">2. Immediate deactivation</option><option value="#s3">3. The <?= (int)$GRACE_PERIOD_DAYS ?>-day grace period</option><option value="#s4">4. Our team may reach out</option><option value="#s5">5. What happens after <?= (int)$GRACE_PERIOD_DAYS ?> days</option><option value="#s6">6. What we retain, and why</option><option value="#s7">7. Orders placed as a guest</option><option value="#s8">8. Cancelling a deletion request manually</option><option value="#s9">9. Questions</option></select></div>
      <p class="intro">In short: deleting your account deactivates it straight away, you have <?= (int)$GRACE_PERIOD_DAYS ?> days to change your mind by simply signing in, and after that your personal details are permanently erased.</p>
        <section id="s1"><h2><span class="n">1</span>Overview</h2>
          <p>These terms explain exactly what happens when you request to delete your Araku Tribe account. We want this process to be transparent and reversible for as long as possible, so mistakes or a change of heart never cost you your order history or account data unnecessarily.</p>
        </section>
        <section id="s2"><h2><span class="n">2</span>Immediate deactivation</h2>
          <p>The moment you confirm account deletion, your account is <strong>deactivated</strong>, not deleted. You will be signed out immediately and will not be able to browse, shop, or place orders using that account while it is deactivated.</p>
        </section>
        <section id="s3"><h2><span class="n">3</span>The <?= (int)$GRACE_PERIOD_DAYS ?>-day grace period</h2>
          <p>For <?= (int)$GRACE_PERIOD_DAYS ?> days after deactivation, nothing is permanently removed. During this window:</p>
          <ul><li>You may sign back in at any time using your original email and password (or Google, if that's how you signed up).</li><li>Signing in automatically cancels the deletion request and fully restores your account, including your saved profile details, order history, and any pending orders.</li><li>No further action is required beyond signing in; reactivation is automatic.</li></ul>
        </section>
        <section id="s4"><h2><span class="n">4</span>Our team may reach out</h2>
          <p>During the grace period, a member of our support team may contact you at the email address on file to understand your reason for leaving, resolve any issue you experienced, or confirm your request. This is optional outreach on our part and does not affect the deletion timeline; you are never required to respond for the deletion to proceed as scheduled if you don't sign back in.</p>
        </section>
        <section id="s5"><h2><span class="n">5</span>What happens after <?= (int)$GRACE_PERIOD_DAYS ?> days</h2>
          <p>If you have not signed back in within <?= (int)$GRACE_PERIOD_DAYS ?> days of deactivation, the following happens automatically and permanently:</p>
          <ul><li>Your name, email, phone number, shipping/billing addresses, and profile picture are permanently erased from our active systems.</li><li>Your login credentials are invalidated. A new account can be created later using the same email address if you wish to return.</li><li>This step cannot be undone. Once the retention window passes, there is no way for us to recover your personal account data.</li></ul>
        </section>
        <section id="s6"><h2><span class="n">6</span>What we retain, and why</h2>
          <p>Even after permanent deletion, a limited record of your past orders (items purchased, amounts, and dates) may be retained in an <strong>anonymized</strong> form, with your name and contact details removed, for as long as required for accounting, tax, fraud prevention, and legal compliance purposes. This anonymized data cannot be used to identify you and is not linked back to any personal profile.</p>
        </section>
        <section id="s7"><h2><span class="n">7</span>Orders placed as a guest</h2>
          <p>This deletion policy applies to registered accounts. If you checked out as a guest without creating an account, there is no account to delete; any order-related emails you received can be disregarded once your order is complete, and no separate deletion request is needed.</p>
        </section>
        <section id="s8"><h2><span class="n">8</span>Cancelling a deletion request manually</h2>
          <p>If for any reason you're unable to sign in during the grace period (for example, you no longer have access to your email), contact our support team as soon as possible so we can verify your identity and assist before the grace period expires.</p>
        </section>
        <section id="s9"><h2><span class="n">9</span>Questions</h2>
          <p>If anything here is unclear, please reach out to us at <a href="mailto:info@arakutribe.com">info@arakutribe.com</a> before confirming deletion.</p>
        </section>
      <div class="doc-foot"><a class="btn btn-outline" href="delete_account3.php">Back to delete account</a><a class="btn btn-outline" href="<?= h3_link('profile') ?>">Back to my profile</a></div>
    </article>
  </div>
</main>
<script>
(function () {
  const links = [...document.querySelectorAll('.toc a')], secs = links.map(a => document.querySelector(a.getAttribute('href')));
  const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) links.forEach(a => a.classList.toggle('on', a.getAttribute('href') === '#' + e.target.id)); }), { rootMargin: '-110px 0px -65% 0px' });
  secs.forEach(s => s && io.observe(s));
  const j = document.getElementById('jump'); if (j) j.addEventListener('change', () => { if (j.value) document.querySelector(j.value).scrollIntoView({ behavior: 'smooth' }); });
})();
</script>
<?php include __DIR__ . '/footer3.php'; ?>