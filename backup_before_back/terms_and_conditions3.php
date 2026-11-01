<?php
/* =====================================================================
   ARAKU TRIBE - terms_and_conditions3.php
   Same wording as the old page, in the new reading layout.
   Set $LAST_UPDATED to the date you last changed this text (the old page
   used the current month, so it always looked freshly updated).
   Uses header3.php + footer3.php.
   ===================================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
$LAST_UPDATED = '1 October 2026';

$PAGE_TITLE = 'Terms &amp; Conditions - Araku Tribe';
include __DIR__ . '/header3.php';
?>
<style>
/* ============ terms_and_conditions3.php ============ */

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
      <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>">Home</a><span aria-hidden="true">/</span><span aria-current="page">Terms &amp; Conditions</span></nav>
      <h1>Terms &amp; Conditions</h1>
      <div class="meta"><span>Last updated: <?= h3_e($LAST_UPDATED) ?></span><span>16 sections</span></div>
    </div>
  </section>
  <div class="wrap lg">
    <nav class="toc" aria-label="On this page"><p>On this page</p><ol><li><a href="#s1">1. Who we are</a></li><li><a href="#s2">2. Eligibility</a></li><li><a href="#s3">3. Accounts</a></li><li><a href="#s4">4. Orders and pricing</a></li><li><a href="#s5">5. Payments</a></li><li><a href="#s6">6. Shipping and delivery</a></li><li><a href="#s7">7. Cancellations, returns and refunds</a></li><li><a href="#s8">8. Intellectual property</a></li><li><a href="#s9">9. Prohibited conduct</a></li><li><a href="#s10">10. Third-party links and services</a></li><li><a href="#s11">11. Disclaimers</a></li><li><a href="#s12">12. Limitation of liability</a></li><li><a href="#s13">13. Indemnification</a></li><li><a href="#s14">14. Changes to these terms</a></li><li><a href="#s15">15. Governing law</a></li><li><a href="#s16">16. Contact us</a></li></ol></nav>
    <article class="doc">
      <div class="toc-mobile"><label class="sr-only" for="jump">Jump to a section</label><select id="jump"><option value="">Jump to a section…</option><option value="#s1">1. Who we are</option><option value="#s2">2. Eligibility</option><option value="#s3">3. Accounts</option><option value="#s4">4. Orders and pricing</option><option value="#s5">5. Payments</option><option value="#s6">6. Shipping and delivery</option><option value="#s7">7. Cancellations, returns and refunds</option><option value="#s8">8. Intellectual property</option><option value="#s9">9. Prohibited conduct</option><option value="#s10">10. Third-party links and services</option><option value="#s11">11. Disclaimers</option><option value="#s12">12. Limitation of liability</option><option value="#s13">13. Indemnification</option><option value="#s14">14. Changes to these terms</option><option value="#s15">15. Governing law</option><option value="#s16">16. Contact us</option></select></div>
      <p class="intro">Welcome to Araku Tribe. These Terms &amp; Conditions ("Terms") govern your access to and use of our website, mobile experience, and the products and services we offer (collectively, the "Service"). By accessing or using the Service, you agree to be bound by these Terms. If you do not agree, please do not use the Service.</p>
        <section id="s1"><h2><span class="n">1</span>Who we are</h2>
          <p>Araku Tribe sells coffee, coffee cups, mugs, bags and related merchandise sourced from the Araku Valley, operated under the brand "Araku Tribe" ("we", "us", "our"). References to "you" or "the user" mean any person who accesses or uses the Service, whether as a registered account holder or a guest.</p>
        </section>
        <section id="s2"><h2><span class="n">2</span>Eligibility</h2>
          <p>You must be at least 18 years old, or the age of legal majority in your jurisdiction, to create an account or place an order. By using the Service, you represent that you meet this requirement and that any information you provide is accurate and current.</p>
        </section>
        <section id="s3"><h2><span class="n">3</span>Accounts</h2>
          <ul><li>You are responsible for maintaining the confidentiality of your login credentials and for all activity that occurs under your account.</li><li>You agree to provide accurate, current and complete information during registration and checkout, and to keep it updated.</li><li>We reserve the right to suspend or terminate accounts that violate these Terms, provide false information, or are used for fraudulent activity.</li><li>Account deletion is handled separately under our <a href="terms_deletion3.php">Account Deletion Terms</a>, which include a grace period before permanent removal.</li></ul>
        </section>
        <section id="s4"><h2><span class="n">4</span>Orders and pricing</h2>
          <ul><li>All product descriptions, images and prices are subject to change without notice.</li><li>Placing an order through the Service (including via our chat assistant) constitutes an offer to purchase; we reserve the right to accept or decline any order for any reason, including pricing errors, suspected fraud, or product unavailability.</li><li>Prices are shown in Indian Rupees (₹) by default; you can choose another currency at the top of the page. Prices do not include applicable taxes or shipping fees unless explicitly noted at checkout.</li><li>Order confirmation emails are sent to the address provided at checkout; it is your responsibility to ensure that address is correct and monitored.</li></ul>
        </section>
        <section id="s5"><h2><span class="n">5</span>Payments</h2>
          <p>We support multiple payment methods including Cash on Delivery, UPI, debit/credit cards, net banking and Amazon Pay, some of which may be processed through a simulated/demo payment flow during our current testing phase. You agree to provide accurate payment information and authorize us (or our payment processor) to charge the applicable amount for your order. We do not store full card numbers or CVV codes on our own servers.</p>
        </section>
        <section id="s6"><h2><span class="n">6</span>Shipping and delivery</h2>
          <p>Estimated delivery timeframes are provided for convenience only and are not guaranteed. Ownership and risk of loss for products pass to you upon delivery to the shipping address provided. We are not responsible for delays caused by couriers, customs, or circumstances outside our reasonable control.</p>
        </section>
        <section id="s7"><h2><span class="n">7</span>Cancellations, returns and refunds</h2>
          <p>Orders paid via Cash on Delivery may be cancelled prior to dispatch by contacting our support team. For orders paid through another method, refund eligibility depends on the product condition and the reason for return. Perishable goods (such as coffee powder once opened) may not be eligible for return for hygiene reasons. Contact <a href="mailto:info@arakutribe.com">info@arakutribe.com</a> for assistance with a specific order.</p>
        </section>
        <section id="s8"><h2><span class="n">8</span>Intellectual property</h2>
          <p>All content on the Service, including text, graphics, logos, product photography, and the "Araku Tribe" name and branding, is owned by us or our licensors and is protected by applicable intellectual property laws. You may not reproduce, distribute, or create derivative works from this content without our prior written consent.</p>
        </section>
        <section id="s9"><h2><span class="n">9</span>Prohibited conduct</h2>
          <p>You agree not to:</p>
          <ul><li>Use the Service for any unlawful purpose or in violation of any applicable law;</li><li>Attempt to gain unauthorized access to any part of the Service, other accounts, or our systems;</li><li>Interfere with or disrupt the Service, including through automated scraping, bots, or excessive requests;</li><li>Submit false, misleading, or fraudulent information, including during checkout or account creation;</li><li>Use the chat assistant to attempt to extract system information, abuse the ordering flow, or place orders you do not intend to pay for.</li></ul>
        </section>
        <section id="s10"><h2><span class="n">10</span>Third-party links and services</h2>
          <p>The Service may contain links to third-party websites or rely on third-party services (such as email delivery or payment processing) that are not owned or controlled by us. We are not responsible for the content, policies, or practices of any third-party service.</p>
        </section>
        <section id="s11"><h2><span class="n">11</span>Disclaimers</h2>
          <p>The Service is provided on an "as is" and "as available" basis without warranties of any kind, whether express or implied, including implied warranties of merchantability, fitness for a particular purpose, and non-infringement. We do not warrant that the Service will be uninterrupted, error-free, or completely secure.</p>
        </section>
        <section id="s12"><h2><span class="n">12</span>Limitation of liability</h2>
          <p>To the fullest extent permitted by law, Araku Tribe shall not be liable for any indirect, incidental, special, consequential, or punitive damages arising out of or related to your use of the Service, even if advised of the possibility of such damages. Our total liability for any claim arising from these Terms or your use of the Service shall not exceed the amount you paid for the order giving rise to the claim.</p>
        </section>
        <section id="s13"><h2><span class="n">13</span>Indemnification</h2>
          <p>You agree to indemnify and hold harmless Araku Tribe, its officers, employees, and affiliates from any claims, damages, liabilities, and expenses arising from your breach of these Terms or your misuse of the Service.</p>
        </section>
        <section id="s14"><h2><span class="n">14</span>Changes to these terms</h2>
          <p>We may update these Terms from time to time. Material changes will be reflected by updating the "Last updated" date above. Continued use of the Service after changes take effect constitutes acceptance of the revised Terms.</p>
        </section>
        <section id="s15"><h2><span class="n">15</span>Governing law</h2>
          <p>These Terms are governed by the laws of India, without regard to conflict-of-law principles. Any disputes arising from these Terms or the Service shall be subject to the exclusive jurisdiction of the courts located in Visakhapatnam, Andhra Pradesh.</p>
        </section>
        <section id="s16"><h2><span class="n">16</span>Contact us</h2>
          <p>If you have questions about these Terms, please contact us at <a href="mailto:info@arakutribe.com">info@arakutribe.com</a> or through our <a href="<?= h3_link('contact') ?>">Contact page</a>.</p>
        </section>
      <div class="doc-foot"><a class="btn btn-outline" href="<?= h3_link('privacy') ?>">Privacy policy</a><a class="btn btn-outline" href="terms_deletion3.php">Account deletion terms</a><a class="btn btn-outline" href="<?= h3_link('home') ?>">Back to home</a></div>
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