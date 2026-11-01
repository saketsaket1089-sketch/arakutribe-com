<?php
/* =====================================================================
   ARAKU TRIBE - privacy_policy3.php
   Same wording as the old page, in the new reading layout.
   Set $LAST_UPDATED to the date you last changed this text (the old page
   used the current month, so it always looked freshly updated).
   Uses header3.php + footer3.php.
   ===================================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
$LAST_UPDATED = '1 October 2026';

$PAGE_TITLE = 'Privacy Policy - Araku Tribe';
include __DIR__ . '/header3.php';
?>
<style>
/* ============ privacy_policy3.php ============ */

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
      <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>">Home</a><span aria-hidden="true">/</span><span aria-current="page">Privacy Policy</span></nav>
      <h1>Privacy Policy</h1>
      <div class="meta"><span>Last updated: <?= h3_e($LAST_UPDATED) ?></span><span>We never sell your data</span></div>
    </div>
  </section>
  <div class="wrap lg">
    <nav class="toc" aria-label="On this page"><p>On this page</p><ol><li><a href="#s1">1. Information we collect</a></li><li><a href="#s2">2. How we use your information</a></li><li><a href="#s3">3. How we share information</a></li><li><a href="#s4">4. Cookies</a></li><li><a href="#s5">5. Data retention</a></li><li><a href="#s6">6. Your rights</a></li><li><a href="#s7">7. Data security</a></li><li><a href="#s8">8. Children's privacy</a></li><li><a href="#s9">9. International users</a></li><li><a href="#s10">10. Changes to this policy</a></li><li><a href="#s11">11. Contact us</a></li></ol></nav>
    <article class="doc">
      <div class="toc-mobile"><label class="sr-only" for="jump">Jump to a section</label><select id="jump"><option value="">Jump to a section…</option><option value="#s1">1. Information we collect</option><option value="#s2">2. How we use your information</option><option value="#s3">3. How we share information</option><option value="#s4">4. Cookies</option><option value="#s5">5. Data retention</option><option value="#s6">6. Your rights</option><option value="#s7">7. Data security</option><option value="#s8">8. Children's privacy</option><option value="#s9">9. International users</option><option value="#s10">10. Changes to this policy</option><option value="#s11">11. Contact us</option></select></div>
      <p class="intro">This Privacy Policy explains how Araku Tribe ("we", "us", "our") collects, uses, shares, and protects information when you visit our website, create an account, place an order, or interact with our chat assistant. By using our Service, you agree to the practices described in this Policy.</p>
        <section id="s1"><h2><span class="n">1</span>Information we collect</h2>
          <p>We collect the following categories of information:</p>
          <ul><li><strong>Account information:</strong> name, email address, phone number, and password (stored as a secure hash, never in plain text).</li><li><strong>Order and shipping information:</strong> shipping address, billing address, order contents, and payment method selected (we do not store full card numbers or CVV codes on our servers).</li><li><strong>Guest checkout information:</strong> if you check out without an account, we collect the email address and shipping details you provide for that order only.</li><li><strong>Communications:</strong> messages you send us via the Contact page, and your interactions with our chat assistant used for browsing products and placing orders.</li><li><strong>Technical information:</strong> IP address, browser type, device information, and session data, collected automatically to operate and secure the Service.</li><li><strong>Cookies and session data:</strong> used to keep you logged in, remember your cart contents, and maintain checkout state between pages.</li></ul>
        </section>
        <section id="s2"><h2><span class="n">2</span>How we use your information</h2>
          <ul><li>To create and manage your account;</li><li>To process, fulfill, and communicate about your orders, including order confirmation emails sent immediately after checkout;</li><li>To send One-Time Passwords (OTPs) for login verification and account security;</li><li>To respond to inquiries submitted through our Contact page or chat assistant;</li><li>To detect, prevent, and address fraud, abuse, or security issues;</li><li>To improve our products, website, and chat assistant based on usage patterns;</li><li>To comply with legal, tax, and accounting obligations.</li></ul>
        </section>
        <section id="s3"><h2><span class="n">3</span>How we share information</h2>
          <p>We do not sell your personal information. We may share it only in the following circumstances:</p>
          <ul><li><strong>Service providers:</strong> such as our email delivery provider (used to send OTPs and order confirmations) and payment processing services, solely to the extent necessary to provide the Service.</li><li><strong>Legal requirements:</strong> if required to do so by law, court order, or governmental request, or to protect our rights, users, or the public.</li><li><strong>Business transfers:</strong> in connection with a merger, acquisition, or sale of assets, subject to standard confidentiality obligations.</li></ul>
        </section>
        <section id="s4"><h2><span class="n">4</span>Cookies</h2>
          <p>We use session cookies to keep you signed in, maintain your shopping cart across pages, and remember checkout progress. These cookies are essential to the Service and do not track you across other websites. Disabling cookies in your browser may prevent parts of the Service, such as the cart and checkout, from working correctly. Your wishlist may also be saved in your browser when you are not signed in.</p>
        </section>
        <section id="s5"><h2><span class="n">5</span>Data retention</h2>
          <p>We retain account and order information for as long as your account is active and as needed to comply with our legal and accounting obligations. If you request deletion of your account, it is handled under our <a href="terms_deletion3.php">Account Deletion Terms</a>, which include a 30-day grace period followed by permanent anonymization of personal details. A limited, anonymized record of past orders may be retained afterward for accounting and fraud-prevention purposes, with no personally identifying information attached.</p>
        </section>
        <section id="s6"><h2><span class="n">6</span>Your rights</h2>
          <p>Depending on your location, you may have the right to:</p>
          <ul><li>Access the personal information we hold about you;</li><li>Correct inaccurate information via your <a href="<?= h3_link('profile') ?>">Profile</a> page;</li><li>Request deletion of your account, as described in our <a href="terms_deletion3.php">Account Deletion Terms</a>;</li><li>Withdraw consent for optional communications, where applicable.</li></ul>
          <p>To exercise any of these rights beyond what's available directly in your account settings, contact us at <a href="mailto:info@arakutribe.com">info@arakutribe.com</a>.</p>
        </section>
        <section id="s7"><h2><span class="n">7</span>Data security</h2>
          <p>We use industry-standard measures to protect your information, including password hashing, OTP-based login verification, and restricted access to personal data. However, no method of transmission or storage is 100% secure, and we cannot guarantee absolute security.</p>
        </section>
        <section id="s8"><h2><span class="n">8</span>Children's privacy</h2>
          <p>The Service is not directed to individuals under the age of 18. We do not knowingly collect personal information from children. If you believe a child has provided us with personal information, please contact us so we can remove it.</p>
        </section>
        <section id="s9"><h2><span class="n">9</span>International users</h2>
          <p>Our servers and service providers may be located outside your country of residence. By using the Service, you consent to the transfer and processing of your information in accordance with this Policy, wherever it is processed.</p>
        </section>
        <section id="s10"><h2><span class="n">10</span>Changes to this policy</h2>
          <p>We may update this Privacy Policy from time to time. Material changes will be reflected by updating the "Last updated" date above. We encourage you to review this page periodically.</p>
        </section>
        <section id="s11"><h2><span class="n">11</span>Contact us</h2>
          <p>For questions about this Privacy Policy or how your information is handled, contact us at <a href="mailto:info@arakutribe.com">info@arakutribe.com</a> or through our <a href="<?= h3_link('contact') ?>">Contact page</a>.</p>
        </section>
      <div class="doc-foot"><a class="btn btn-outline" href="<?= h3_link('terms') ?>">Terms &amp; conditions</a><a class="btn btn-outline" href="terms_deletion3.php">Account deletion terms</a><a class="btn btn-outline" href="<?= h3_link('home') ?>">Back to home</a></div>
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