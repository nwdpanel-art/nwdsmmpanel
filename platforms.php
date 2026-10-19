<?php

require 'config.php';

auth();

$user_id = (int) $_SESSION['user_id'];

$user = $db->query("
    SELECT *
    FROM users
    WHERE id = $user_id
    LIMIT 1
")->fetch_assoc();

if (!$user) {
    die("User account not found.");
}


/*
|--------------------------------------------------------------------------
| PLATFORMS THAT ACTUALLY HAVE ACTIVE SERVICES
|--------------------------------------------------------------------------
*/

$platforms = [];

$result = $db->query("
    SELECT
        p.id,
        p.name,
        p.slug,
        p.icon,
        COUNT(s.id) AS service_count
    FROM platforms p
    LEFT JOIN services s
        ON s.platform_id = p.id
        AND s.status = 1
    WHERE p.status = 1
    GROUP BY p.id
    ORDER BY p.sort_order ASC, p.name ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $platforms[] = $row;
    }
}

?>

<!doctype html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>New Order - NWD PANEL</title>

<link rel="stylesheet" href="assets/style.css">

<style>

.platform-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 16px;
    margin-top: 24px;
}

.platform-tile {
    display: block;
    text-decoration: none;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 26px 18px;
    text-align: center;
    transition: border-color .15s, transform .15s;
}

.platform-tile:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
}

.platform-tile.disabled {
    opacity: .4;
    pointer-events: none;
}

.platform-icon {
    font-size: 36px;
    margin-bottom: 12px;
}

.platform-name {
    color: var(--text);
    font-size: 15px;
    font-weight: 600;
}

.platform-count {
    color: var(--muted);
    font-size: 12px;
    margin-top: 6px;
}

.balance-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 18px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
}

.balance-card span {
    font-size: 12px;
    color: var(--muted);
}

.balance-card strong {
    color: var(--text);
    font-size: 17px;
}

.alert-error {
    padding: 14px 16px;
    border-radius: 13px;
    background: var(--error-bg);
    color: var(--error-text);
    border: 1px solid var(--error-text);
    font-size: 13px;
}

@media (max-width: 600px) {
    .top {
        flex-wrap: wrap;
        gap: 12px;
    }
    .platform-grid {
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        gap: 12px;
    }
    .platform-tile {
        padding: 18px 12px;
    }
}

</style>

</head>

<body>

<div class="app">

<!-- SIDEBAR -->

<aside>

<a class="brand" href="dashboard.php">
    NWD <span>PANEL</span>
</a>

<nav>

<a href="dashboard.php">
    Dashboard
</a>

<a class="active" href="platforms.php">
    New Order
</a>

<a href="services.php">
    Services
</a>

<a href="orders.php">
    Orders
</a>

<a href="wallet.php">
    Wallet
</a>

<a href="add_funds.php">
    💳 Add Funds
</a>

<a href="support.php">
    Support
</a>

</nav>

<a class="logout" href="logout.php">
    Logout
</a>

</aside>


<!-- MAIN -->

<main class="main">

<div class="order-page" style="max-width:1050px;">

<div class="top">

<div>
<p class="eyebrow">ORDER</p>
<h1>Choose a Platform</h1>
</div>

<div class="balance-card">
<span>Balance</span>
<strong>Rs. <?= number_format((float) $user['balance'], 2) ?></strong>
</div>

</div>

<?php if (empty($platforms)): ?>

<div class="alert-error" style="margin-top:20px;">
    No platforms are configured yet. Please contact support.
</div>

<?php else: ?>

<div class="platform-grid">

<?php foreach ($platforms as $p): ?>

<?php if ((int) $p['service_count'] > 0): ?>

<a class="platform-tile" href="order.php?platform=<?= e($p['slug']) ?>">

<div class="platform-icon"><?= e($p['icon']) ?></div>
<div class="platform-name"><?= e($p['name']) ?></div>
<div class="platform-count"><?= (int) $p['service_count'] ?> services</div>

</a>

<?php else: ?>

<div class="platform-tile disabled">

<div class="platform-icon"><?= e($p['icon']) ?></div>
<div class="platform-name"><?= e($p['name']) ?></div>
<div class="platform-count">No services yet</div>

</div>

<?php endif; ?>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

</main>

</div>

<?php include __DIR__ . "/whatsapp_button.php"; ?>
</body>
</html>
