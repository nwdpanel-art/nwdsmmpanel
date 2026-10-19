<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'api.php';

/*
|--------------------------------------------------------------------------
| SIMPLE SECURITY TOKEN
|--------------------------------------------------------------------------
|
| Is token ko apni marzi ka long random value rakhein.
|
*/

$SYNC_TOKEN = 'SMMPRO_8x92Kp7_AUTO_SYNC_2026';


/*
|--------------------------------------------------------------------------
| ALLOW ONLY TOKEN REQUEST
|--------------------------------------------------------------------------
*/

$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {

    if (
        !isset($_GET['token']) ||
        !hash_equals(
            $SYNC_TOKEN,
            $_GET['token']
        )
    ) {
        http_response_code(403);
        exit('Access denied');
    }
}


/*
|--------------------------------------------------------------------------
| GET ACTIVE PROVIDER ORDERS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        provider_order_id,
        status
    FROM orders
    WHERE provider_order_id IS NOT NULL
    AND provider_order_id != ''
    AND status NOT IN (
        'Completed',
        'Cancelled'
    )
    ORDER BY id ASC
    LIMIT 100
";

$result = $db->query($sql);

if (!$result) {
    exit('Database error: ' . $db->error);
}


$checked = 0;
$updated = 0;
$errors = [];


/*
|--------------------------------------------------------------------------
| CHECK EACH ORDER
|--------------------------------------------------------------------------
*/

while ($order = $result->fetch_assoc()) {

    $checked++;

    try {

        $response = provider_order_status(
            $order['provider_order_id']
        );


        /*
        |--------------------------------------------------------------------------
        | PROVIDER STATUS
        |--------------------------------------------------------------------------
        */

        if (!isset($response['status'])) {
            continue;
        }


        $provider_status = strtolower(
            trim(
                (string)$response['status']
            )
        );


        /*
        |--------------------------------------------------------------------------
        | MAP PROVIDER STATUS → LOCAL STATUS
        |--------------------------------------------------------------------------
        */

        switch ($provider_status) {

            case 'pending':

                $local_status = 'Pending';

                break;


            case 'in progress':

                $local_status = 'In Progress';

                break;


            case 'processing':

                $local_status = 'In Progress';

                break;


            case 'completed':

                $local_status = 'Completed';

                break;


            case 'partial':

                $local_status = 'Partial';

                break;


            case 'canceled':

                $local_status = 'Cancelled';

                break;


            case 'cancelled':

                $local_status = 'Cancelled';

                break;


            default:

                continue 2;
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE ONLY WHEN STATUS CHANGED
        |--------------------------------------------------------------------------
        */

        if ($local_status !== $order['status']) {

            $stmt = $db->prepare("
                UPDATE orders
                SET status = ?
                WHERE id = ?
            ");

            if (!$stmt) {
                throw new Exception(
                    $db->error
                );
            }


            $stmt->bind_param(
                "si",
                $local_status,
                $order['id']
            );


            if (!$stmt->execute()) {
                throw new Exception(
                    $stmt->error
                );
            }


            $updated++;
        }

    } catch (Throwable $e) {

        $errors[] = [
            'order' => $order['id'],
            'error' => $e->getMessage()
        ];
    }
}


/*
|--------------------------------------------------------------------------
| RESULT
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: application/json'
);

echo json_encode([
    'success' => true,
    'checked' => $checked,
    'updated' => $updated,
    'errors'  => $errors
], JSON_PRETTY_PRINT);