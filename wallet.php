<?php

require 'config.php';

auth();

$id = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| USER BALANCE
|--------------------------------------------------------------------------
*/

$u = $db->query("
    SELECT balance
    FROM users
    WHERE id = $id
    LIMIT 1
")->fetch_assoc();


/*
|--------------------------------------------------------------------------
| TRANSACTIONS
|--------------------------------------------------------------------------
*/

$tx = $db->query("
    SELECT *
    FROM transactions
    WHERE user_id = $id
    ORDER BY id DESC
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

    <title>Wallet</title>

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

            <a href="orders.php">
                Orders
            </a>

            <a
                class="active"
                href="wallet.php"
            >
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
            WALLET
        </p>


        <h1>
            Balance & Transactions
        </h1>


        <!-- ADD FUNDS BUTTON -->

        <a
            href="add_funds.php"
            class="btn primary"
        >
            + Add Funds
        </a>


        <!-- BALANCE -->

        <div class="wallet-big">

            Rs. <?= number_format(
                (float)$u['balance'],
                2
            ) ?>


            <small>
                Current Balance
            </small>

        </div>


        <!-- TRANSACTIONS CARD -->

        <div class="card">


            <div class="notice">

                For this demo, funds should be added
                by an admin. Connect a real payment
                gateway before accepting live payments.

            </div>


            <div class="table-wrap">


                <table>


                    <tr>

                        <th>
                            Amount
                        </th>

                        <th>
                            Type
                        </th>

                        <th>
                            Note
                        </th>

                        <th>
                            Date
                        </th>

                    </tr>


                    <?php while (
                        $t = $tx->fetch_assoc()
                    ): ?>


                        <tr>


                            <td>

                                Rs. <?= number_format(
                                    (float)$t['amount'],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $t['type']
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $t['note']
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $t['created_at']
                                ) ?>

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