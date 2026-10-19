<?php

require 'config.php';
auth();


/*
|--------------------------------------------------------------------------
| GET ACTIVE SERVICES
|--------------------------------------------------------------------------
*/

$services = $db->query("
    SELECT
        id,
        service_code,
        name,
        category,
        description,
        price,
        min_quantity,
        max_quantity
    FROM services
    WHERE status = 1
    ORDER BY category ASC, id DESC
");

?>

<!doctype html>

<html>

<head>
<?php include __DIR__ . "/theme_init.php"; ?>


<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>

<title>Services - NWD PANEL</title>

<link
    rel="stylesheet"
    href="assets/style.css"
>

<style>

/* =========================================================
   SERVICES PAGE
========================================================= */

.services-page {
    width:100%;
}


/* =========================================================
   SERVICE GRID
========================================================= */

.service-grid {

    display:grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(260px, 1fr)
        );

    gap:18px;

    margin-top:25px;
}


/* =========================================================
   SERVICE CARD
========================================================= */

.service {

    position:relative;

    display:flex;

    flex-direction:column;

    min-height:270px;

    padding:22px;

    border-radius:18px;

    background:var(--surface);

    border:1px solid var(--border);

    transition:.2s;
}

.service:hover {

    transform:translateY(-2px);

    border-color:var(--border-strong);
}


/* =========================================================
   ICON
========================================================= */

.service .icon {

    width:42px;
    height:42px;

    display:flex;

    align-items:center;
    justify-content:center;

    margin-bottom:15px;

    border-radius:12px;

    background:var(--surface-hover);

    color:var(--text);

    font-size:19px;
}


/* =========================================================
   CATEGORY
========================================================= */

.service small {

    display:inline-block;

    margin-bottom:7px;

    color:var(--muted);

    font-size:10px;

    text-transform:uppercase;

    letter-spacing:.6px;
}


/* =========================================================
   NAME
========================================================= */

.service h2 {

    margin:0 0 8px;

    color:var(--text);

    font-size:17px;

    line-height:1.35;
}


/* =========================================================
   DESCRIPTION
========================================================= */

.service p {

    margin:0;

    color:var(--muted);

    font-size:12px;

    line-height:1.6;

    flex:1;
}


/* =========================================================
   PRICE
========================================================= */

.service-price {

    margin-top:18px;

    padding:13px;

    border-radius:11px;

    background:var(--surface-hover);

    border:1px solid var(--border);
}

.service-price small {

    display:block;

    margin-bottom:4px;

    color:var(--muted-2);

    font-size:9px;

    letter-spacing:.5px;
}

.service-price strong {

    color:var(--text);

    font-size:18px;
}


/* =========================================================
   LIMITS
========================================================= */

.service-limits {

    display:flex;

    justify-content:space-between;

    gap:10px;

    margin-top:10px;

    color:var(--muted-2);

    font-size:10px;
}

.service-limits strong {

    color:var(--muted);

    font-weight:600;
}


/* =========================================================
   ORDER BUTTON
========================================================= */

.service .btn {

    width:100%;

    box-sizing:border-box;

    margin-top:15px;

    text-align:center;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-services {

    padding:30px;

    margin-top:25px;

    text-align:center;

    border-radius:16px;

    background:var(--surface);

    border:1px solid var(--border);

    color:var(--muted);

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:600px) {

    .service-grid {

        grid-template-columns:1fr;

    }

    .service {

        min-height:auto;

    }

}

</style>

</head>


<body>


<div class="app">


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside>

<a
    class="brand"
    href="dashboard.php"
>
    NWD <span>PANEL</span>
</a>


<nav>

<a href="dashboard.php">
    Dashboard
</a>

<a href="order.php">
    New Order
</a>

<a
    class="active"
    href="services.php"
>
    Services
</a>

<a href="orders.php">
    Orders
</a>

<a href="wallet.php">
    Wallet
</a>

<a href="support.php">
    Support
</a>

<a href="blog.php">Blog</a>


</nav>


<a
    class="logout"
    href="logout.php"
>
    Logout
</a>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


<div class="services-page">


<p class="eyebrow">
    CATALOG
</p>


<h1>
    Marketing Services
</h1>


<p class="lead">
    Choose a service and place your order directly
    through our connected provider.
</p>


<!-- =====================================================
     SERVICES
===================================================== -->

<?php if ($services && $services->num_rows > 0): ?>


<div class="service-grid">


<?php while ($s = $services->fetch_assoc()): ?>


<div class="card service">


<div class="icon">
    ✦
</div>


<small>
    <?= e($s['category']) ?>
</small>


<h2>
    <?= e($s['name']) ?>
</h2>


<p>

<?= e(
    $s['description']
    ?: 'No description available.'
) ?>

</p>


<!-- PRICE -->

<div class="service-price">

<small>
    CUSTOMER PRICE / 1K
</small>

<strong>

Rs.
<?= number_format(
    (float)$s['price'],
    2
) ?>

</strong>

</div>


<!-- LIMITS -->

<div class="service-limits">

<span>
    Min:
    <strong>
        <?= number_format(
            (int)$s['min_quantity']
        ) ?>
    </strong>
</span>


<span>
    Max:
    <strong>
        <?= number_format(
            (int)$s['max_quantity']
        ) ?>
    </strong>
</span>

</div>


<!-- ORDER -->

<a
    class="btn primary"
    href="order.php?service=<?= (int)$s['id'] ?>"
>
    Order Now
</a>


</div>


<?php endwhile; ?>


</div>


<?php else: ?>


<div class="empty-services">

No active services are currently available.

</div>


<?php endif; ?>


</div>


</main>


</div>


<?php include __DIR__ . "/whatsapp_button.php"; ?>
<?php include __DIR__ . "/theme_toggle.php"; ?>
</body>

</html>