<?php

require 'config.php';

auth();

$id = $_SESSION['user_id'];

$orders = $db->query("
    SELECT
        o.*,
        s.name service
    FROM orders o
    JOIN services s
        ON s.id = o.service_id
    WHERE o.user_id = $id
    ORDER BY o.id DESC
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

    <title>Orders</title>

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

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="order.php">
                New Order
            </a>

            <a href="services.php">
                Services
            </a>

            <a
                class="active"
                href="orders.php"
            >
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

<a href="blog.php">Blog</a>


        </nav>


        <a
            class="logout"
            href="logout.php"
        >
            Logout
        </a>

    </aside>


    <!-- MAIN -->

    <main class="main">

        <p class="eyebrow">
            HISTORY
        </p>


        <h1>
            My Orders
        </h1>


        <div class="card table-wrap">


            <table>

                <tr>

                    <th>
                        #
                    </th>

                    <th>
                        Service
                    </th>

                    <th>
                        Link
                    </th>

                    <th>
                        Qty
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
                        <?= (int)$o['id'] ?>
                    </td>


                    <td>
                        <?= e($o['service']) ?>
                    </td>


                    <td class="truncate">
                        <?= e($o['link']) ?>
                    </td>


                    <td>
                        <?= (int)$o['quantity'] ?>
                    </td>


                    <td>
                        Rs. <?= number_format(
                            (float)$o['amount'],
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


    </main>


</div>


<?php include __DIR__ . "/whatsapp_button.php"; ?>
<?php include __DIR__ . "/theme_toggle.php"; ?>
</body>

</html>