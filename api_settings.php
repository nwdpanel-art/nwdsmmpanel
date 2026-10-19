<?php
$API_URL = 'https://zsmmpanel.com/api/v2';
$API_KEY = '3JR1203Sx3sBv7U0AQEXTLvQCwarkHP5';
require '../config.php';
admin();

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| SAVE API SETTINGS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_api'])
) {

    $api_url = trim(
        $_POST['api_url'] ?? ''
    );

    $api_key = trim(
        $_POST['api_key'] ?? ''
    );

    $usd_to_pkr_rate = (float) (
        $_POST['usd_to_pkr_rate'] ?? 0
    );


    if ($api_url === '') {

        $error = "API URL is required.";

    } elseif ($api_key === '') {

        $error = "API key is required.";

    } elseif ($usd_to_pkr_rate <= 0) {

        $error = "USD to PKR exchange rate must be greater than 0.";

    } else {

        $check = $db->query("
            SELECT id
            FROM api_settings
            ORDER BY id DESC
            LIMIT 1
        ");

        if ($check && $check->num_rows) {

            $row = $check->fetch_assoc();

            $stmt = $db->prepare("
                UPDATE api_settings
                SET api_url = ?,
                    api_key = ?,
                    usd_to_pkr_rate = ?,
                    status = 1
                WHERE id = ?
            ");

            $stmt->bind_param(
                "ssdi",
                $api_url,
                $api_key,
                $usd_to_pkr_rate,
                $row['id']
            );

        } else {

            $stmt = $db->prepare("
                INSERT INTO api_settings
                (
                    provider_name,
                    api_url,
                    api_key,
                    usd_to_pkr_rate,
                    status
                )
                VALUES
                (
                    'ZSMMPanel',
                    ?,
                    ?,
                    ?,
                    1
                )
            ");

            $stmt->bind_param(
                "ssd",
                $api_url,
                $api_key,
                $usd_to_pkr_rate
            );
        }


        if ($stmt->execute()) {

            $message =
                "API settings saved successfully.";

        } else {

            $error =
                "Could not save API settings.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| TEST API
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['test_api'])
) {

    require_once '../api.php';

    try {

        $result = provider_balance();

        if (
            isset($result['status']) &&
            $result['status'] === 'success'
        ) {

            $message =
                "API connected successfully. " .
                "Provider balance: $" .
                number_format(
                    (float) $result['balance'],
                    4
                );

        } else {

            $error =
                "API response was not successful.";
        }

    } catch (Exception $e) {

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT SETTINGS
|--------------------------------------------------------------------------
*/

$current = $db->query("
    SELECT *
    FROM api_settings
    ORDER BY id DESC
    LIMIT 1
")->fetch_assoc();

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

<title>API Settings</title>

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
ADMIN
</p>

<h1>
Provider API
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

<div class="card-head">

<h2>
ZSMMPanel API Settings
</h2>

</div>


<form method="POST">


<div class="form-group">

<label>
API URL
</label>

<input
    class="form-control"
    type="url"
    name="api_url"
    value="<?= e(
        $current['api_url']
        ?? 'https://zsmmpanel.com/api/v2'
    ) ?>"
    required
>

</div>


<div class="form-group">

<label>
API Key
</label>

<input
    class="form-control"
    type="password"
    name="api_key"
    value="<?= e(
        $current['api_key'] ?? ''
    ) ?>"
    required
>

</div>


<div class="form-group">

<label>
USD to PKR Exchange Rate
</label>

<input
    class="form-control"
    type="number"
    step="0.01"
    min="0.01"
    name="usd_to_pkr_rate"
    value="<?= e(
        $current['usd_to_pkr_rate'] ?? '278.00'
    ) ?>"
    required
>

<small style="color:var(--muted);">
Provider ki service prices USD me hoti hain. Yeh rate import ke waqt
lag ke Rs. me convert karti hai (markup se pehle). Aaj ke rate ke
hisab se update karte rahein.
</small>

</div>


<button
    class="btn primary"
    type="submit"
    name="save_api"
>
Save API Settings
</button>

</form>


<hr style="margin:25px 0;">


<form method="POST">

<button
    class="btn"
    type="submit"
    name="test_api"
>
Test API Connection
</button>

</form>

</div>

</main>

</div>


</body>

</html>