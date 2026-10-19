<?php

require 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| LIVE SERVICE PRICING PREVIEW (REAL DATA, GROUPED BY CATEGORY)
|--------------------------------------------------------------------------
*/

$category_preview = $db->query("
    SELECT category, MIN(price) AS starting_price, COUNT(*) AS service_count
    FROM services
    WHERE status = 1
    GROUP BY category
    ORDER BY starting_price ASC
    LIMIT 8
");

?>
<!doctype html>
<html>
<head>
<?php include __DIR__ . "/theme_init.php"; ?>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(defined('SITE_NAME') ? SITE_NAME : 'NWD PANEL') ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: '1' ?>">
</head>
<body style="background:var(--bg,#06070c);">

<!-- TOP NAV -->

<div class="topnav">
<a class="brand" href="index.php">NWD <span>PANEL</span></a>
<button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">☰</button>
<div class="navlinks" id="navLinks">
<a href="#how-it-works">How It Works</a>
<a href="#services">Services</a>
<a href="blog.php">Blog</a>
<a href="support.php">Support</a>
</div>
<div class="navcta">
<a class="btn ghost" href="login.php">Login</a>
<a class="btn primary" href="register.php">Create Account</a>
</div>
</div>


<!-- HERO -->

<div class="hero2">

<p class="eyebrow">SOCIAL MEDIA MANAGEMENT</p>

<h1>Grow your digital presence <span style="color:var(--violet,#7c5cff);">professionally.</span></h1>

<p class="lead">
Manage legitimate social-media marketing campaigns, orders, analytics
and support from one clean dashboard — built for creators, brands and
agencies who need results they can track.
</p>

<div class="ctas">
<a class="btn primary" href="register.php">Create Free Account</a>
<a class="btn ghost" href="#services">View Services</a>
</div>

<div class="rating-badge">
<span class="stars">★★★★★</span>
<div>
<b>4.8 / 5</b>
<small>BASED ON CUSTOMER REVIEWS</small>
</div>
</div>

</div>


<!-- PLATFORM MARQUEE -->

<div class="marquee-wrap">
<div class="marquee-track">
<span>📷 <b>Instagram</b></span>
<span>🎵 <b>TikTok</b></span>
<span>▶️ <b>YouTube</b></span>
<span>👍 <b>Facebook</b></span>
<span>🐦 <b>Twitter / X</b></span>
<span>💼 <b>LinkedIn</b></span>
<span>📌 <b>Pinterest</b></span>
<span>📷 <b>Instagram</b></span>
<span>🎵 <b>TikTok</b></span>
<span>▶️ <b>YouTube</b></span>
<span>👍 <b>Facebook</b></span>
<span>🐦 <b>Twitter / X</b></span>
<span>💼 <b>LinkedIn</b></span>
<span>📌 <b>Pinterest</b></span>
</div>
</div>


<div class="hero2">

<!-- STATS -->

<div class="stats-row">
<div><b>10,000+</b><small>Orders Delivered</small></div>
<div><b>2,500+</b><small>Active Customers</small></div>
<div><b>99.9%</b><small>Uptime</small></div>
<div><b>24/7</b><small>Support</small></div>
</div>


<!-- HOW IT WORKS -->

<div class="section-title" id="how-it-works">
<p class="eyebrow">GETTING STARTED</p>
<h2>Three steps to your first order</h2>
</div>

<div class="steps-row">

<div class="step-card">
<div class="num">1</div>
<h3>Create your account</h3>
<p>Sign up in seconds with your email or continue with Google — no credit card required.</p>
</div>

<div class="step-card">
<div class="num">2</div>
<h3>Add funds &amp; choose a service</h3>
<p>Top up your wallet, browse services by platform, and pick exactly what fits your goal.</p>
</div>

<div class="step-card">
<div class="num">3</div>
<h3>Track your order live</h3>
<p>Watch your order's status update in real time from your dashboard, start to finish.</p>
</div>

</div>


<!-- LIVE SERVICE PRICING -->

<div class="section-title" id="services">
<p class="eyebrow">STARTING PRICES</p>
<h2>Services across every platform</h2>
</div>

<?php if ($category_preview->num_rows > 0): ?>

<div class="svc-scroll">

<?php while ($cat = $category_preview->fetch_assoc()): ?>

<div class="svc-card">
<b><?= e($cat['category']) ?></b>
<small><?= (int) $cat['service_count'] ?> service<?= $cat['service_count'] > 1 ? 's' : '' ?> available</small>
<div class="price">From Rs. <?= number_format($cat['starting_price'], 2) ?></div>
</div>

<?php endwhile; ?>

</div>

<?php endif; ?>


<!-- FEATURES -->

<div class="section-title">
<p class="eyebrow">WHY CHOOSE US</p>
<h2>Built for reliability</h2>
</div>

<div class="features">

<div class="feature-card">
<div class="icon">⚡</div>
<h3>Fast Delivery</h3>
<p>Orders are sent to our provider network the moment you place them, with most starting quickly.</p>
</div>

<div class="feature-card">
<div class="icon">🔒</div>
<h3>Secure Payments</h3>
<p>Your transactions and account balance are protected with industry-standard security practices.</p>
</div>

<div class="feature-card">
<div class="icon">💬</div>
<h3>24/7 Support</h3>
<p>Our support team and WhatsApp chat are available around the clock to help with any issue.</p>
</div>

<div class="feature-card">
<div class="icon">💰</div>
<h3>Competitive Pricing</h3>
<p>Transparent, fair pricing on every service with no hidden fees.</p>
</div>

<div class="feature-card">
<div class="icon">📊</div>
<h3>Live Order Tracking</h3>
<p>Track every order's status in real time, right from your dashboard.</p>
</div>

<div class="feature-card">
<div class="icon">🛡️</div>
<h3>Quality Guaranteed</h3>
<p>We work only with trusted providers to ensure consistent, quality results.</p>
</div>

</div>


<!-- TESTIMONIAL CAROUSEL -->

<div class="section-title">
<p class="eyebrow">TESTIMONIALS</p>
<h2>What our customers say</h2>
</div>

<div class="carousel" id="testimonial-carousel">

<div class="carousel-slide active">
<div class="stars">★★★★★</div>
<p>"Replace this with a real customer review before launch."</p>
<b>Customer Name</b><br><small style="color:var(--muted);">Verified Buyer</small>
</div>

<div class="carousel-slide">
<div class="stars">★★★★★</div>
<p>"Replace this with a real customer review before launch."</p>
<b>Customer Name</b><br><small style="color:var(--muted);">Verified Buyer</small>
</div>

<div class="carousel-slide">
<div class="stars">★★★★★</div>
<p>"Replace this with a real customer review before launch."</p>
<b>Customer Name</b><br><small style="color:var(--muted);">Verified Buyer</small>
</div>

<div class="carousel-dots">
<button class="active" data-i="0"></button>
<button data-i="1"></button>
<button data-i="2"></button>
</div>

</div>


<!-- FINAL CTA -->

<div class="cta-band">
<h2>Ready to grow?</h2>
<p>Create your free account and place your first order in minutes.</p>
<a class="btn primary" href="register.php">Get Started Today</a>
</div>


<!-- FOOTER -->

<div class="site-footer">
<span>© <?= date('Y') ?> <?= e(defined('SITE_NAME') ? SITE_NAME : 'NWD PANEL') ?>. All rights reserved.</span>
<span><a href="support.php">Support</a> &nbsp;·&nbsp; <a href="blog.php">Blog</a></span>
</div>

</div>

<?php include __DIR__ . '/whatsapp_button.php'; ?>

<script>
(function () {
    var slides = document.querySelectorAll('#testimonial-carousel .carousel-slide');
    var dots   = document.querySelectorAll('#testimonial-carousel .carousel-dots button');
    var index  = 0;
    var timer;

    function show(i) {
        slides.forEach(function (s, n) { s.classList.toggle('active', n === i); });
        dots.forEach(function (d, n) { d.classList.toggle('active', n === i); });
        index = i;
    }

    function next() {
        show((index + 1) % slides.length);
    }

    function start() {
        timer = setInterval(next, 5000);
    }

    dots.forEach(function (d) {
        d.addEventListener('click', function () {
            clearInterval(timer);
            show(parseInt(d.dataset.i, 10));
            start();
        });
    });

    var carousel = document.getElementById('testimonial-carousel');
    carousel.addEventListener('mouseenter', function () { clearInterval(timer); });
    carousel.addEventListener('mouseleave', start);

    start();
})();

(function () {
    var toggle = document.getElementById('navToggle');
    var links  = document.getElementById('navLinks');

    if (!toggle || !links) return;

    toggle.addEventListener('click', function () {
        var open = links.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.textContent = open ? '✕' : '☰';
    });

    links.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () {
            links.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.textContent = '☰';
        });
    });
})();
</script>

<?php include __DIR__ . "/theme_toggle.php"; ?>
</body>
</html>