<?php

require '../config.php';
admin();

require_once '../api.php';

$message = '';
$error = '';

$imported = 0;
$updated = 0;

$MARKUP_PERCENT = 50;

/*
|--------------------------------------------------------------------------
| DIAGNOSTIC LOG
|--------------------------------------------------------------------------
|
| Records exactly what happened to each provider service during this
| import run, so price mismatches can be diagnosed instead of guessed at.
|
*/

$import_log = [];


/*
|--------------------------------------------------------------------------
| IMPORT / UPDATE SERVICES
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['import_services'])
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | GET SERVICES FROM PROVIDER
        |--------------------------------------------------------------------------
        */

        $provider_services = provider_services();

        if (!is_array($provider_services)) {

            throw new Exception(
                'Provider returned invalid service data.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | USD -> PKR EXCHANGE RATE
        |--------------------------------------------------------------------------
        |
        | ZSMMPanel's rate field is in USD per 1000. Convert to PKR before
        | applying markup, otherwise USD values get displayed as if they
        | were already Rs.
        |
        */

        $settings = provider_settings();

        $usd_to_pkr_rate = (float) (
            $settings['usd_to_pkr_rate'] ?? 0
        );

        if ($usd_to_pkr_rate <= 0) {

            throw new Exception(
                'USD to PKR exchange rate is not set. ' .
                'Please set it on the API Settings page first.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LOOP PROVIDER SERVICES
        |--------------------------------------------------------------------------
        */

        foreach ($provider_services as $service) {

            if (!is_array($service)) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | PROVIDER SERVICE ID
            |--------------------------------------------------------------------------
            */

            $provider_id = trim(
                (string)($service['service'] ?? '')
            );

            if ($provider_id === '') {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | SERVICE NAME
            |--------------------------------------------------------------------------
            */

            $name = trim(
                (string)($service['name'] ?? 'Unnamed Service')
            );

            if ($name === '') {
                $name = 'Unnamed Service';
            }


            /*
            |--------------------------------------------------------------------------
            | CATEGORY
            |--------------------------------------------------------------------------
            */

            $category = trim(
                (string)($service['category'] ?? 'General')
            );

            if ($category === '') {
                $category = 'General';
            }


            /*
            |--------------------------------------------------------------------------
            | DESCRIPTION
            |--------------------------------------------------------------------------
            */

            $description = trim(
                (string)(
                    $service['desc']
                    ?? $service['description']
                    ?? ''
                )
            );


            /*
            |--------------------------------------------------------------------------
            | PROVIDER PRICE (USD)
            |--------------------------------------------------------------------------
            |
            | Provider API rate is per 1000, in USD.
            |
            */

            $provider_price_usd = (float)(
                $service['rate'] ?? 0
            );


            /*
            |--------------------------------------------------------------------------
            | VALID PROVIDER PRICE
            |--------------------------------------------------------------------------
            */

            if ($provider_price_usd < 0) {
                $provider_price_usd = 0;
            }


            /*
            |--------------------------------------------------------------------------
            | CONVERT TO PKR
            |--------------------------------------------------------------------------
            */

            $provider_price =
                $provider_price_usd *
                $usd_to_pkr_rate;


            /*
            |--------------------------------------------------------------------------
            | CUSTOMER PRICE
            |--------------------------------------------------------------------------
            |
            | 50% markup, applied on top of the PKR-converted price.
            |
            | Example:
            |
            | Provider = $1.00 USD -> Rs. 278.00 (at rate 278)
            | Markup   = Rs. 139.00
            | Customer = Rs. 417.00
            |
            */

            $customer_price =
                $provider_price *
                (1 + ($MARKUP_PERCENT / 100));


            /*
            |--------------------------------------------------------------------------
            | ROUND CUSTOMER PRICE
            |--------------------------------------------------------------------------
            */

            $customer_price = round(
                $customer_price,
                2
            );


            /*
            |--------------------------------------------------------------------------
            | MINIMUM
            |--------------------------------------------------------------------------
            */

            $min = (int)(
                $service['min'] ?? 1
            );

            if ($min < 1) {
                $min = 1;
            }


            /*
            |--------------------------------------------------------------------------
            | MAXIMUM
            |--------------------------------------------------------------------------
            */

            $max = (int)(
                $service['max'] ?? 1000000
            );

            if ($max < $min) {
                $max = $min;
            }


            /*
            |--------------------------------------------------------------------------
            | SERVICE CODE
            |--------------------------------------------------------------------------
            */

            $service_code =
                'API-' . $provider_id;


            /*
            |--------------------------------------------------------------------------
            | CHECK EXISTING SERVICE
            |--------------------------------------------------------------------------
            */

            $check = $db->prepare("
                SELECT id, price, status
                FROM services
                WHERE provider_service_id = ?
                LIMIT 1
            ");

            if (!$check) {

                throw new Exception(
                    'Database error: ' . $db->error
                );
            }


            $check->bind_param(
                "s",
                $provider_id
            );

            $check->execute();

            $existing =
                $check
                    ->get_result()
                    ->fetch_assoc();


            /*
            |--------------------------------------------------------------------------
            | UPDATE EXISTING SERVICE
            |--------------------------------------------------------------------------
            */

            if ($existing) {

                /*
                |--------------------------------------------------------------
                | IMPORTANT:
                | price is now updated too.
                |--------------------------------------------------------------
                */

                $stmt = $db->prepare("
                    UPDATE services
                    SET
                        service_code = ?,
                        name = ?,
                        category = ?,
                        description = ?,
                        price = ?,
                        min_quantity = ?,
                        max_quantity = ?,
                        status = 1
                    WHERE provider_service_id = ?
                ");

                if (!$stmt) {

                    throw new Exception(
                        'Database error: ' . $db->error
                    );
                }


                $stmt->bind_param(
                    "ssssdiis",
                    $service_code,
                    $name,
                    $category,
                    $description,
                    $customer_price,
                    $min,
                    $max,
                    $provider_id
                );


                if (!$stmt->execute()) {

                    throw new Exception(
                        'Could not update service: ' .
                        $stmt->error
                    );
                }


                $updated++;

                $import_log[] = [
                    'action'         => 'updated',
                    'provider_id'    => $provider_id,
                    'name'           => $name,
                    'provider_price'     => $provider_price,
                    'provider_price_usd' => $provider_price_usd,
                    'old_price'      => (float) $existing['price'],
                    'new_price'      => $customer_price,
                    'rows_affected'  => $stmt->affected_rows,
                ];

            }


            /*
            |--------------------------------------------------------------------------
            | INSERT NEW SERVICE
            |--------------------------------------------------------------------------
            */

            else {

                /*
                |--------------------------------------------------------------
                | Don't import a service with invalid/zero provider price.
                |--------------------------------------------------------------
                */

                if ($provider_price <= 0) {

                    $import_log[] = [
                        'action'             => 'skipped',
                        'provider_id'        => $provider_id,
                        'name'               => $name,
                        'provider_price'     => $provider_price,
                        'provider_price_usd' => $provider_price_usd,
                        'old_price'          => null,
                        'new_price'          => null,
                        'rows_affected'      => 0,
                    ];

                    continue;
                }


                $stmt = $db->prepare("
                    INSERT INTO services
                    (
                        service_code,
                        provider_service_id,
                        name,
                        category,
                        description,
                        price,
                        min_quantity,
                        max_quantity,
                        status
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?, ?, 1
                    )
                ");

                if (!$stmt) {

                    throw new Exception(
                        'Database error: ' . $db->error
                    );
                }


                $stmt->bind_param(
                    "sssssdii",
                    $service_code,
                    $provider_id,
                    $name,
                    $category,
                    $description,
                    $customer_price,
                    $min,
                    $max
                );


                if (!$stmt->execute()) {

                    throw new Exception(
                        'Could not import service: ' .
                        $stmt->error
                    );
                }


                $imported++;

                $import_log[] = [
                    'action'         => 'inserted',
                    'provider_id'    => $provider_id,
                    'name'           => $name,
                    'provider_price'     => $provider_price,
                    'provider_price_usd' => $provider_price_usd,
                    'old_price'      => null,
                    'new_price'      => $customer_price,
                    'rows_affected'  => 1,
                ];
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SUCCESS MESSAGE
        |--------------------------------------------------------------------------
        */

        $message =
            "Import complete. " .
            $imported .
            " new services imported and " .
            $updated .
            " existing services updated. " .
            "USD to PKR rate: " .
            number_format($usd_to_pkr_rate, 2) .
            ", then " .
            $MARKUP_PERCENT .
            "% markup applied.";

        /*
        |--------------------------------------------------------------------------
        | SORT LOG: PUT UPDATED ROWS WHERE PRICE ACTUALLY CHANGED FIRST
        |--------------------------------------------------------------------------
        */

        usort($import_log, function ($a, $b) {

            $a_changed = $a['action'] === 'updated'
                && round($a['old_price'], 2) !== round($a['new_price'], 2);

            $b_changed = $b['action'] === 'updated'
                && round($b['old_price'], 2) !== round($b['new_price'], 2);

            return $b_changed <=> $a_changed;
        });


    } catch (Throwable $e) {

        $error =
            $e->getMessage();
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

<title>Import Services - NWD PANEL</title>

<link
    rel="stylesheet"
    href="../assets/style.css"
>

<style>

/* =========================================================
   IMPORT PAGE
========================================================= */

.import-box {
    max-width: 900px;
}


/* =========================================================
   ICON
========================================================= */

.import-icon {
    font-size:42px;
    margin-bottom:15px;
}


/* =========================================================
   DESCRIPTION
========================================================= */

.import-description {
    color:var(--muted);
    line-height:1.7;
    margin-bottom:25px;
}


/* =========================================================
   ALERTS
========================================================= */

.alert-success {
    padding:14px 18px;
    border-radius:10px;

    background:var(--success-bg);
    border:1px solid var(--success-text);

    color:var(--success-text);

    margin-bottom:20px;
}

.alert-error {
    padding:14px 18px;
    border-radius:10px;

    background:var(--error-bg);
    border:1px solid var(--error-text);

    color:var(--error-text);

    margin-bottom:20px;
}


/* =========================================================
   INFO GRID
========================================================= */

.info-grid {

    display:grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(180px,1fr)
        );

    gap:12px;

    margin-top:25px;
}


/* =========================================================
   INFO ITEM
========================================================= */

.info-item {

    background:var(--surface);

    border:1px solid var(--border);

    border-radius:10px;

    padding:15px;
}

.info-item small {

    display:block;

    color:var(--muted-2);

    margin-bottom:5px;
}

.info-item strong {

    color:var(--text);

}


/* =========================================================
   MARKUP BOX
========================================================= */

.markup-box {

    margin-top:20px;

    padding:18px;

    border-radius:12px;

    background:
        linear-gradient(
            135deg,
            var(--surface),
            var(--surface-hover)
        );

    border:1px solid var(--border);
}

.markup-box h3 {

    margin:0 0 8px;

    color:var(--text);

    font-size:16px;
}

.markup-box p {

    margin:0;

    color:var(--muted);

    line-height:1.6;

    font-size:13px;
}

.markup-value {

    display:inline-block;

    margin-top:10px;

    padding:7px 12px;

    border-radius:8px;

    background:var(--surface-hover);

    color:var(--text);

    font-weight:700;
}


/* =========================================================
   WARNING
========================================================= */

.import-warning {

    margin-top:15px;

    color:var(--muted);

    font-size:12px;

    line-height:1.6;
}


/* =========================================================
   BUTTON
========================================================= */

.import-button {

    min-width:230px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:600px) {

    .import-box {
        padding:18px;
    }

    .import-button {
        width:100%;
    }

}

</style>

</head>


<body>

<div class="app">


<!-- =====================================================
     SIDEBAR
===================================================== -->

<?php include __DIR__ . "/_sidebar.php"; ?>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


<div class="top">

<div>

<p class="eyebrow">
SERVICES
</p>

<h1>
Import Provider Services
</h1>

</div>

</div>


<!-- =====================================================
     SUCCESS
===================================================== -->

<?php if ($message): ?>

<div class="alert-success">

<?= e($message) ?>

</div>

<?php endif; ?>


<!-- =====================================================
     ERROR
===================================================== -->

<?php if ($error): ?>

<div class="alert-error">

<?= e($error) ?>

</div>

<?php endif; ?>


<!-- =====================================================
     DIAGNOSTIC LOG (only shown right after an import run)
===================================================== -->

<?php if (!empty($import_log)): ?>

<div class="card" style="margin-top:15px; margin-bottom:15px;">

<div class="card-head">
<h2>Import Details (this run)</h2>
</div>

<p style="color:var(--muted); font-size:12px; margin:0 0 15px;">
Provider ki price <strong>USD</strong> me hoti hai. Aaj ke rate
(<?= number_format($usd_to_pkr_rate ?? 0, 2) ?> Rs. per $1) se PKR me
convert ki gayi, phir <?= (int) $MARKUP_PERCENT ?>% markup add hua.
"Old Price" = jo pehle database me thi. Rate change karne ke liye
API Settings page par jayein.
</p>

<div class="table-wrap">

<table class="service-table">

<thead>
<tr>
<th>Provider ID</th>
<th>Name</th>
<th>Action</th>
<th>Provider Price (USD)</th>
<th>Provider Price (Rs.)</th>
<th>Old Price (DB)</th>
<th>New Price (with markup)</th>
<th>Rows Affected</th>
</tr>
</thead>

<tbody>

<?php foreach ($import_log as $row): ?>

<?php
    $price_changed =
        $row['action'] === 'updated' &&
        round($row['old_price'], 2) !== round($row['new_price'], 2);
?>

<tr <?= $price_changed ? 'style="background:var(--success-bg);"' : '' ?>>

<td><?= e($row['provider_id']) ?></td>
<td><?= e($row['name']) ?></td>

<td>
<?php if ($row['action'] === 'updated'): ?>
<span class="status-active">Updated</span>
<?php elseif ($row['action'] === 'inserted'): ?>
<span class="status-active">New</span>
<?php else: ?>
<span class="status-inactive">Skipped (zero price)</span>
<?php endif; ?>
</td>

<td>$<?= number_format($row['provider_price_usd'], 4) ?></td>
<td>Rs. <?= number_format($row['provider_price'], 2) ?></td>

<td>
<?= $row['old_price'] === null ? '—' : 'Rs. ' . number_format($row['old_price'], 2) ?>
</td>

<td>
<?= $row['new_price'] === null ? '—' : 'Rs. ' . number_format($row['new_price'], 2) ?>
</td>

<td><?= (int) $row['rows_affected'] ?></td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

<?php endif; ?>


<!-- =====================================================
     IMPORT CARD
===================================================== -->

<div class="card import-box">


<div class="import-icon">
🔄
</div>


<h2>
Sync Provider Services
</h2>


<p class="import-description">

Click the button below to retrieve the latest
services and prices from your configured provider API.

Existing services will be updated instead of
creating duplicates.

</p>


<!-- =====================================================
     MARKUP INFORMATION
===================================================== -->

<div class="markup-box">

<h3>
💰 Customer Pricing
</h3>

<p>

Provider ki original price automatically fetch hogi
aur customer ke liye us par
<strong>50% markup</strong> add kiya jayega.

</p>

<span class="markup-value">
50% Markup
</span>

</div>


<!-- =====================================================
     EXAMPLE
===================================================== -->

<div class="info-grid">


<div class="info-item">

<small>
Provider Price
</small>

<strong>
Rs. 1.00 / 1K
</strong>

</div>


<div class="info-item">

<small>
Markup
</small>

<strong>
+ Rs. 0.50
</strong>

</div>


<div class="info-item">

<small>
Customer Price
</small>

<strong>
Rs. 1.50 / 1K
</strong>

</div>


<div class="info-item">

<small>
Min / Max
</small>

<strong>
Imported Automatically
</strong>

</div>


</div>


<!-- =====================================================
     IMPORT FORM
===================================================== -->

<form
    method="POST"
    style="margin-top:25px;"
>

<button
    type="submit"
    name="import_services"
    class="btn primary import-button"
>
🔄 Import / Update Services
</button>

</form>


<p class="import-warning">

⚠️ Every time you run the import, existing services'
prices will be updated according to the latest provider
price + 50% markup.

</p>


</div>


</main>

</div>


</body>

</html>