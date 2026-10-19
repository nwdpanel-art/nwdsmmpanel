<?php

require '../config.php';
admin();

require_once '../api.php';

$message = '';
$error = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['sync_orders'])
) {

    try {

        $orders = $db->query("
            SELECT
                id,
                provider_order_id
            FROM orders
            WHERE provider_order_id IS NOT NULL
            AND provider_order_id != ''
            AND status NOT IN ('Completed', 'Cancelled')
            ORDER BY id DESC
            LIMIT 100
        ");

        $updated = 0;

        while (
            $order = $orders->fetch_assoc()
        ) {

            $provider =
                provider_order_status(
                    $order['provider_order_id']
                );


            if (
                !isset($provider['status'])
            ) {
                continue;
            }


            $provider_status =
                strtolower(
                    trim(
                        $provider['status']
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | STATUS MAPPING
            |--------------------------------------------------------------------------
            */

            switch ($provider_status) {

                case 'pending':
                    $local_status = 'Pending';
                    break;

                case 'processing':
                    $local_status = 'Processing';
                    break;

                case 'completed':
                    $local_status = 'Completed';
                    break;

                case 'partial':
                    $local_status = 'Partial';
                    break;

                case 'canceled':
                case 'cancelled':
                    $local_status = 'Cancelled';
                    break;

                case 'in progress':
                    $local_status = 'Processing';
                    break;

                default:
                    continue 2;
            }


            $stmt = $db->prepare("
                UPDATE orders
                SET status = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "si",
                $local_status,
                $order['id']
            );

            $stmt->execute();

            $updated++;
        }


        $message =
            $updated .
            " orders synchronized.";

    } catch (Exception $e) {

        $error = $e->getMessage();
    }
}

?>

<!doctype html>

<html>

<head>
<?php include __DIR__ . "/../theme_init.php"; ?>


<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>

<title>Sync Orders</title>

<link
    rel="stylesheet"
    href="../assets/style.css"
>

</head>

<body>

<div class="app">

<?php include __DIR__ . "/_sidebar.php"; ?>


<main class="main">

<div class="top">

<div>

<p class="eyebrow">
ORDERS
</p>

<h1>
Synchronize Orders
</h1>

</div>

</div>


<?php if ($message): ?>

<div class="alert alert-success">
<?= e($message) ?>
</div>

<?php endif; ?>


<?php if ($error): ?>

<div class="alert alert-error">
<?= e($error) ?>
</div>

<?php endif; ?>


<div class="card">

<h2>
Provider Status Sync
</h2>

<p>
Update local order statuses from the provider.
</p>

<form method="POST">

<button
    type="submit"
    name="sync_orders"
    class="btn primary"
>
Sync Orders Now
</button>

</form>

</div>

</main>

</div>


</body>

</html>