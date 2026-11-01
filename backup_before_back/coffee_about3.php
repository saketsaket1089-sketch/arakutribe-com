<?php
/* =====================================================================
   ARAKU TRIBE - coffee_about3.php  (About / Our story)
   Content from coffee_about.php in the new design.
   Uses header3.php + footer3.php.
   Layout: sections are one "finger gap" apart (set by .sec padding below);
   in every two-column block the text and the picture/tiles start and end
   on the same lines, whatever the screen size.
   ===================================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------------------------------------------------------------------
   DUAL MODE / SPA (AJAX) ROUTING CHECK
   --------------------------------------------------------------------- */
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || isset($_GET['ajax']) 
          || isset($_POST['ajax']);

include 'db1.php'; // Ensure database and helper functions like h3_link/h3_e are always loaded

$POSTS = [
    ['img' => 'img/blog/inner_b5.png', 'date' => '12 Jan 2026', 'title' => 'Araku coffee, produced using traditional methods',
     'text' => 'Traditional methods passed down through generations give a unique taste that reflects the rich biodiversity of the region.'],
    ['img' => 'img/blog/inner_b6.png', 'date' => '27 Jan 2026', 'title' => 'Grown in harmony with the land',
     'text' => 'The Araku tribe has embraced organic farming, protecting the environment while improving the livelihoods of their community.'],
    ['img' => 'img/blog/inner_b7.png', 'date' => '2 Feb 2026',  'title' => "From the heart of India's Araku Valley",
     'text' => 'Araku coffee has become a symbol of sustainable agriculture that inspires environmentally conscious coffee lovers.'],
];
$PAGE_TITLE       = 'About - Araku Tribe';
$PAGE_DESCRIPTION = 'Araku Tribe Coffee: grown by the indigenous farmers of the Araku Valley using sustainable, traditional methods.';

 
include __DIR__ . '/header3.php';

?>
<!-- SPA Container Start -->
<div id="spa-container" data-title="<?= h3_e($PAGE_TITLE) ?>" data-tab-style="<?= h3_e($TAB_STYLE ?? 'brown') ?>">
<style>
/* ============ coffee_about3.php ============ */

.ph{background:var(--cream);padding:20px 0 28px}
.crumbs{display:flex;gap:8px;margin:0 0 14px;font-size:13.5px;color:var(--muted)}
.crumbs a{color:var(--muted);text-decoration:none}
.crumbs a:hover{color:var(--brown)}
.crumbs span[aria-current]{color:var(--brown);font-weight:600}
/* one finger gap between sections: 28px above + 28px below = about 56px of space */
.sec{padding:28px 0}
.sec.cream{background:var(--cream)}
.sec-head{max-width:720px;margin-bottom:20px}
.sec-head h2{font-size:clamp(1.8rem,3vw,2.4rem)}
.eyebrow{display:inline-flex;align-items:center;gap:8px;margin:0 0 10px;font-weight:600;font-size:14px;color:var(--orange-cta)}
.eyebrow .ico{width:16px;height:16px}
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
.band{border-radius:30px;padding:36px 40px;color:var(--cream);display:flex;align-items:center;justify-content:space-between;gap:28px;flex-wrap:wrap;
  background:radial-gradient(120% 90% at 100% 0%,#8A4F2B 0%,rgba(138,79,43,0) 55%),radial-gradient(90% 80% at 0% 100%,#2F6B4F 0%,rgba(47,107,79,0) 60%),var(--brown-deep)}
.band h2{color:#fff;font-size:clamp(1.8rem,3vw,2.4rem)}
.band p{margin:8px 0 0;color:#E6D5C4;max-width:56ch}
.band .acts{display:flex;gap:12px;flex-wrap:wrap}
.band .btn-ghost-light{border-color:rgba(255,247,237,.5)}

/* two-column blocks: both columns are exactly as tall as the taller one, so the top and the bottom line up */
.split{display:grid;grid-template-columns:1.05fr .95fr;gap:40px;align-items:stretch}
.split>div:first-child,.ab-hero>div:first-child{display:flex;flex-direction:column}
.split .lede,.ab-hero .lede{margin:0 0 12px}
.split .grid3{grid-template-columns:1fr 1fr;grid-auto-rows:1fr}
.checks{list-style:none;padding:0;margin:6px 0 0;display:grid;gap:10px}
.checks li{display:flex;align-items:center;gap:10px;font-weight:600;font-size:15px;color:var(--brown-deep)}
.checks .ico{flex:none;width:22px;height:22px;padding:4px;border-radius:50%;background:var(--green);color:#fff;stroke-width:3}
.split .checks{margin-top:auto;padding-top:8px}          /* pinned to the bottom, level with the bottom of the tiles */

.ab-hero{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:stretch}
.ab-hero h1{font-size:clamp(2.2rem,4.4vw,3.6rem);line-height:1.05}
.ab-hero .lede{font-size:1.08rem;margin-top:16px}
.ab-hero .lede+.lede{margin-top:0}
.ab-hero .hero-acts{display:flex;gap:12px;flex-wrap:wrap;margin-top:auto;padding-top:22px}   /* buttons level with the bottom of the picture */
.ab-hero .photo{position:relative;min-height:400px}
.ab-hero .photo>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.stamp{position:absolute;left:18px;bottom:18px;width:104px;height:104px;border-radius:50%;background:#fff;box-shadow:var(--shadow);display:flex;align-items:center;justify-content:center;overflow:hidden}
.stamp img{position:static;width:80%;height:80%;object-fit:contain}
.story{display:grid;grid-template-columns:repeat(2,1fr);grid-auto-rows:1fr;gap:18px}
.story .tile p{font-size:15px}
.posts{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
.post{display:flex;flex-direction:column}
.post .media{aspect-ratio:3/2;border-radius:20px;overflow:hidden;background:var(--cream-2)}
.post img{width:100%;height:100%;object-fit:cover}
.post time{display:block;margin-top:14px;font-size:13.5px;color:var(--muted)}
.post h3{font-size:1.2rem;margin-top:4px;line-height:1.3}
.post p{margin:8px 0 0;color:var(--muted);font-size:14.5px;line-height:1.6}
@media (max-width:960px){
  .split,.ab-hero{grid-template-columns:1fr;gap:22px}
  .grid3,.quotes{grid-template-columns:1fr}
  .split .grid3{grid-template-columns:1fr 1fr}
  .story,.posts{grid-template-columns:1fr}
  .band{padding:26px}
  .ab-hero .photo{min-height:0;aspect-ratio:4/3;max-width:560px}
  .ab-hero .hero-acts{padding-top:16px}
}
@media (max-width:560px){ .split .grid3{grid-template-columns:1fr} .stamp{width:84px;height:84px} }
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
      <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= h3_link('home') ?>">Home</a><span aria-hidden="true">/</span><span aria-current="page">About</span></nav>
      <div class="ab-hero">
        <div>
          <p class="eyebrow"><svg class="ico"><use href="#i-pin"/></svg>Araku Valley, Andhra Pradesh</p>
          <h1>Experience the rich heritage of Araku coffee.</h1>
          <p class="lede">At Araku Tribe we bring you coffee grown in the lush valleys of Araku, a tribute to the indigenous farmers who have mastered coffee cultivation for generations. The unique climate, rich soil and natural shade of the Eastern Ghats give it its exceptional flavour.</p>
          <p class="lede">Every bag is hand-picked, naturally grown and packed in biodegradable wheat bags, so what reaches your cup is the valley itself.</p>
          <ul class="checks">
            <li><svg class="ico"><use href="#i-check"/></svg>Hand-picked by Araku tribal farmers</li>
            <li><svg class="ico"><use href="#i-check"/></svg>Grown naturally, with centuries-old organic methods</li>
            <li><svg class="ico"><use href="#i-check"/></svg>Fair-trade and ethically sourced</li>
          </ul>
          <div class="hero-acts">
            <a class="btn btn-primary" href="<?= h3_link('bag') ?>">Shop our coffee</a>
            <a class="btn btn-outline" href="<?= h3_link('franchise') ?>">Partner with us</a>
          </div>
        </div>
        <div class="photo"><img src="img/features/about_2.jpeg" alt="Araku Valley coffee" loading="eager">
          <span class="stamp"><img src="img/features/about_us_02.png" alt=""></span></div>
      </div>
    </div>
  </section>

  <section class="sec">
    <div class="wrap split">
      <div>
        <p class="eyebrow">Who we are</p>
        <h2>A community-driven movement, not just a coffee brand.</h2>
        <p class="lede" style="margin-top:14px">We empower local farmers through fair-trade practices and ethical sourcing. By choosing Araku coffee, you support the livelihoods of tribal communities while enjoying premium quality coffee.</p>
        <p class="lede">Our eco-friendly wheat bag packaging keeps it fresh while reducing environmental impact. From farm to cup, every step reflects our commitment to sustainability.</p>
        <p class="lede">Every purchase also supports ethical farming, education and economic stability for local communities.</p>
        <ul class="checks">
          <li><svg class="ico"><use href="#i-check"/></svg>Fair-trade practices and ethical sourcing</li>
          <li><svg class="ico"><use href="#i-check"/></svg>Eco-friendly wheat bag packaging</li>
          <li><svg class="ico"><use href="#i-check"/></svg>Sustainable, from farm to cup</li>
        </ul>
      </div>
      <div class="grid3">
        <div class="tile"><span class="ic"><svg class="ico"><use href="#i-hand"/></svg></span><h3>Hand-picked</h3><p>Every coffee cherry is picked by hand so only the best beans reach your cup.</p></div>
        <div class="tile"><span class="ic"><svg class="ico"><use href="#i-leaf"/></svg></span><h3>Grown naturally</h3><p>Centuries-old organic farming, passed down through generations.</p></div>
        <div class="tile"><span class="ic"><svg class="ico"><use href="#i-people"/></svg></span><h3>Fair to farmers</h3><p>Better wages, training and sustainable farming resources.</p></div>
        <div class="tile"><span class="ic"><svg class="ico"><use href="#i-box"/></svg></span><h3>Wheat-bag packed</h3><p>Biodegradable packaging that keeps the aroma in.</p></div>
      </div>
    </div>
  </section>

  <section class="sec cream">
    <div class="wrap">
      <div class="sec-head"><p class="eyebrow">Our story</p><h2>Rooted in tradition, made for today</h2></div>
      <div class="story">
        <div class="tile"><h3>A vision rooted in tradition and sustainability</h3><p>Our journey began with a vision: to share the rich, aromatic flavours of Araku-grown coffee with the world while ensuring the well-being of the farmers who cultivate it. Every bean we source comes from centuries-old organic farming techniques, so each cup delivers an authentic coffee experience.</p></div>
        <div class="tile"><h3>From farm to cup: our commitment to quality</h3><p>We handpick each coffee cherry and roast with care to bring out its natural richness, aroma and flavour. Whether it's a robust espresso, a smooth cappuccino or a classic filter coffee, Araku Tribe coffee is crafted to taste its best.</p></div>
        <div class="tile"><h3>Empowering farmers, enriching lives</h3><p>Our fair-trade partnerships give tribal farmers better wages, training and sustainable farming resources. Choosing Araku Tribe coffee supports ethical farming, education and economic stability for local communities.</p></div>
        <div class="tile"><h3>Sustainability at the core</h3><p>From biodegradable wheat-bag packaging to a smaller footprint in processing and distribution, every part of our business follows our commitment to the environment. Great coffee should taste good and do good, for people and the planet.</p></div>
      </div>
    </div>
  </section>

  <section class="sec">
    <div class="wrap">
      <div class="sec-head"><p class="eyebrow">From the journal</p><h2>Stories from the valley</h2></div>
      <div class="posts">
        <?php foreach ($POSTS as $p): ?>
          <article class="post"><div class="media"><img src="<?= h3_e($p['img']) ?>" alt="" loading="lazy"></div>
            <time><?= h3_e($p['date']) ?></time><h3><?= h3_e($p['title']) ?></h3><p><?= h3_e($p['text']) ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="sec">
    <div class="wrap">
      <div class="band">
        <div><h2>Join the Araku Tribe coffee experience</h2>
          <p>A legacy of heritage, excellence and sustainability. Whether you love coffee, run a café or value ethical sourcing, you're welcome here.</p></div>
        <div class="acts"><a class="btn btn-primary btn-lg" href="<?= h3_link('shop') ?>">Shop the collection</a><a class="btn btn-ghost-light" href="<?= h3_link('franchise') ?>">Become a partner</a></div>
      </div>
    </div>
  </section>
</main>


</div>
<!-- SPA Container End -->

<?php 
if (!$isAjax) {
    include __DIR__ . '/footer3.php'; 
}
?>
