<?php

require 'config.php';
auth();

$id = (int) $_SESSION['user_id'];

$u = $db->query("
    SELECT *
    FROM users
    WHERE id = $id
")->fetch_assoc();

$orders = $db->query("
    SELECT
        o.*,
        s.name AS service
    FROM orders o
    JOIN services s ON s.id = o.service_id
    WHERE o.user_id = $id
    ORDER BY o.id DESC
    LIMIT 5
");

$count = $db->query("
    SELECT COUNT(*) AS c
    FROM orders
    WHERE user_id = $id
")->fetch_assoc()['c'];

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

    <title>Dashboard</title>

    <link
        rel="stylesheet"
        href="assets/style.css"
    >

</head>

<body>

<div class="app">

    <!-- SIDEBAR -->

    <aside>

        <a
            class="brand"
            href="dashboard.php"
        >
            NWD <span>PANEL</span>
        </a>

        <nav>

            <a
                class="active"
                href="dashboard.php"
            >
                Dashboard
            </a>

            <a href="order.php">
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

            <!-- NEW ADD FUNDS OPTION -->

            <a href="add_funds.php">
                💳 Add Funds
            </a>

            <a href="support.php">
                Support
            </a>

            <a href="blog.php">
                Blog
            </a>

        </nav>

        <a
            class="logout"
            href="logout.php"
        >
            Logout
        </a>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main">

        <div class="top">

            <div>

                <p class="eyebrow">
                    OVERVIEW
                </p>

                <h1>
                    Hello, <?= e($u['name']) ?> 👋
                </h1>

            </div>


            <div class="balance">

                Balance

                <strong>
                    Rs. <?= number_format($u['balance'], 2) ?>
                </strong>

            </div>

        </div>


        <!-- STATS -->

        <div class="stats">


            <!-- WALLET -->

            <div class="stat">

                <small>
                    WALLET BALANCE
                </small>

                <b>
                    Rs. <?= number_format($u['balance'], 2) ?>
                </b>

                <a
                    href="add_funds.php"
                    class="btn primary"
                    style="
                        display:inline-block;
                        margin-top:12px;
                        text-decoration:none;
                    "
                >
                    + Add Funds
                </a>

            </div>


            <!-- ORDERS -->

            <div class="stat">

                <small>
                    TOTAL ORDERS
                </small>

                <b>
                    <?= $count ?>
                </b>

            </div>


            <!-- ACCOUNT -->

            <div class="stat">

                <small>
                    ACCOUNT
                </small>

                <b>
                    Active
                </b>

            </div>

        </div>


        <!-- QUICK ACTIONS -->

        <div
            class="card"
            style="margin-bottom:20px;"
        >

            <div class="card-head">

                <h2>
                    Quick Actions
                </h2>

            </div>


            <div
                style="
                    display:flex;
                    gap:12px;
                    flex-wrap:wrap;
                "
            >

                <a
                    href="add_funds.php"
                    class="btn primary"
                    style="text-decoration:none;"
                >
                    💳 Add Funds
                </a>


                <a
                    href="order.php"
                    class="btn"
                    style="text-decoration:none;"
                >
                    🛒 New Order
                </a>


                <a
                    href="services.php"
                    class="btn"
                    style="text-decoration:none;"
                >
                    📦 View Services
                </a>

            </div>

        </div>


        <!-- RECENT ORDERS -->

        <div class="card">

            <div class="card-head">

                <h2>
                    Recent Orders
                </h2>

                <a href="orders.php">
                    View all →
                </a>

            </div>


            <div class="table-wrap">

                <table>

                    <tr>

                        <th>
                            Service
                        </th>

                        <th>
                            Quantity
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>


                    <?php while (
                        $o = $orders->fetch_assoc()
                    ): ?>

                    <tr>

                        <td>
                            <?= e($o['service']) ?>
                        </td>

                        <td>
                            <?= (int) $o['quantity'] ?>
                        </td>

                        <td>
                            Rs. <?= number_format(
                                $o['amount'],
                                2
                            ) ?>
                        </td>

                        <td>

                            <span class="pill">

                                <?= e(
                                    $o['status']
                                ) ?>

                            </span>

                        </td>

                    </tr>

                    <?php endwhile; ?>


                </table>

            </div>

        </div>

    </main>

</div>

<?php include __DIR__ . "/whatsapp_button.php"; ?>
<?php include __DIR__ . "/theme_toggle.php"; ?>
</body>

</html>