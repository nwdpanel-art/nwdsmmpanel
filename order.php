<?php

require_once 'api.php';
require 'config.php';
require_once 'notify.php';

auth();

$user_id = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

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
| SELECTED SERVICE
|--------------------------------------------------------------------------
*/

$selected_service_id = isset($_GET['service'])
    ? (int) $_GET['service']
    : 0;

$selected_service = null;

if ($selected_service_id > 0) {

    $stmt = $db->prepare("
        SELECT *
        FROM services
        WHERE id = ?
        AND status = 1
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $selected_service_id
    );

    $stmt->execute();

    $selected_service = $stmt
        ->get_result()
        ->fetch_assoc();
}


/*
|--------------------------------------------------------------------------
| PLACE ORDER
|--------------------------------------------------------------------------
*/

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $service_id = (int) ($_POST['service_id'] ?? 0);
    $quantity   = (int) ($_POST['quantity'] ?? 0);
    $link       = trim($_POST['link'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | GET SERVICE
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        SELECT *
        FROM services
        WHERE id = ?
        AND status = 1
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $service_id
    );

    $stmt->execute();

    $service = $stmt
        ->get_result()
        ->fetch_assoc();


    if (!$service) {

        $error = "Invalid service selected.";

    } elseif ($quantity < $service['min_quantity']) {

        $error =
            "Minimum quantity is " .
            number_format($service['min_quantity']) .
            ".";

    } elseif ($quantity > $service['max_quantity']) {

        $error =
            "Maximum quantity is " .
            number_format($service['max_quantity']) .
            ".";

    } elseif ($link === '') {

        $error = "Please enter your target link.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | PRICE CALCULATION
        |--------------------------------------------------------------------------
        |
        | Price = price per 1,000
        |
        */

        $charge = (
            $quantity / 1000
        ) * (float) $service['price'];

        $charge = round(
            $charge,
            2
        );


        /*
        |--------------------------------------------------------------------------
        | CHECK BALANCE
        |--------------------------------------------------------------------------
        */

        if ((float) $user['balance'] < $charge) {

            $error =
                "Insufficient wallet balance. " .
                "Required: Rs. " .
                number_format($charge, 2) .
                " | Available: Rs. " .
                number_format($user['balance'], 2);

        } else {


            /*
            |--------------------------------------------------------------------------
            | DATABASE TRANSACTION
            |--------------------------------------------------------------------------
            */

            $db->begin_transaction();

            try {


                /*
                |--------------------------------------------------------------------------
                | LOCK USER BALANCE
                |--------------------------------------------------------------------------
                */

                $stmt = $db->prepare("
                    SELECT balance
                    FROM users
                    WHERE id = ?
                    FOR UPDATE
                ");

                $stmt->bind_param(
                    "i",
                    $user_id
                );

                $stmt->execute();

                $current_user = $stmt
                    ->get_result()
                    ->fetch_assoc();


                if (
                    !$current_user ||
                    (float) $current_user['balance'] < $charge
                ) {

                    throw new Exception(
                        "Insufficient wallet balance."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | SEND ORDER TO PROVIDER
                |--------------------------------------------------------------------------
                */

                if (
                    empty($service['provider_service_id'])
                ) {

                    throw new Exception(
                        "This service is not connected to a provider."
                    );
                }


                $provider_response = provider_add_order(
                    $service['provider_service_id'],
                    $link,
                    $quantity
                );


                if (
                    !isset($provider_response['order'])
                ) {

                    $provider_error =
                        $provider_response['error']
                        ?? 'Provider rejected the order.';

                    throw new Exception(
                        "Provider error: " .
                        $provider_error
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | PROVIDER ORDER ID
                |--------------------------------------------------------------------------
                */

                $provider_order_id =
                    (string)
                    $provider_response['order'];

                $status = 'Pending';


                /*
                |--------------------------------------------------------------------------
                | CREATE LOCAL ORDER
                |--------------------------------------------------------------------------
                */

                $stmt = $db->prepare("
                    INSERT INTO orders
                    (
                        provider_order_id,
                        user_id,
                        service_id,
                        link,
                        quantity,
                        amount,
                        status
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?
                    )
                ");

                $stmt->bind_param(
                    "siisids",
                    $provider_order_id,
                    $user_id,
                    $service_id,
                    $link,
                    $quantity,
                    $charge,
                    $status
                );


                if (!$stmt->execute()) {

                    throw new Exception(
                        "Could not create local order."
                    );
                }


                $order_id = $db->insert_id;


                /*
                |--------------------------------------------------------------------------
                | DEDUCT BALANCE
                |--------------------------------------------------------------------------
                */

                $stmt = $db->prepare("
                    UPDATE users
                    SET balance = balance - ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "di",
                    $charge,
                    $user_id
                );


                if (!$stmt->execute()) {

                    throw new Exception(
                        "Could not update wallet."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | TRANSACTION RECORD
                |--------------------------------------------------------------------------
                */

                $type = 'order';

                $note =
                    'Order payment - ' .
                    $service['name'];


                $stmt = $db->prepare("
                    INSERT INTO transactions
                    (
                        user_id,
                        amount,
                        type,
                        note
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");

                $stmt->bind_param(
                    "idss",
                    $user_id,
                    $charge,
                    $type,
                    $note
                );


                if (!$stmt->execute()) {

                    throw new Exception(
                        "Could not create transaction."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | COMMIT
                |--------------------------------------------------------------------------
                */

                $db->commit();


                /*
                |--------------------------------------------------------------------------
                | NOTIFY ADMIN (best-effort, never blocks the order)
                |--------------------------------------------------------------------------
                */

                notify_admin(
                    "🛒 New Order #" . $order_id,
                    "Service: " . $service['name'] . "\n" .
                    "Quantity: " . number_format($quantity) . "\n" .
                    "Amount: Rs. " . number_format($charge, 2) . "\n" .
                    "Link: " . $link
                );


                $success =
                    "Order placed successfully! " .
                    "Charged Rs. " .
                    number_format($charge, 2);


                /*
                |--------------------------------------------------------------------------
                | REFRESH USER
                |--------------------------------------------------------------------------
                */

                $user = $db->query("
                    SELECT *
                    FROM users
                    WHERE id = $user_id
                    LIMIT 1
                ")->fetch_assoc();


            } catch (Exception $e) {

                $db->rollback();

                $error = $e->getMessage();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET ACTIVE SERVICES
|--------------------------------------------------------------------------
*/

$serviceList = $db->query("
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
    ORDER BY category ASC, name ASC
");


/*
|--------------------------------------------------------------------------
| BUILD CATEGORY LIST
|--------------------------------------------------------------------------
*/

$categories = [];

if ($serviceList) {

    while ($s = $serviceList->fetch_assoc()) {

        $cat = trim($s['category']);

        if ($cat === '') {
            continue;
        }

        if (!isset($categories[$cat])) {
            $categories[$cat] = [];
        }

        $categories[$cat][] = $s;
    }
}


/*
|--------------------------------------------------------------------------
| SELECTED CATEGORY
|--------------------------------------------------------------------------
*/

$selected_category = '';

if ($selected_service) {
    $selected_category = $selected_service['category'];
}

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

<title>New Order - NWD PANEL</title>

<link
    rel="stylesheet"
    href="assets/style.css"
>

<style>

/* =========================================================
   ORDER PAGE
========================================================= */

.order-page {
    max-width:1050px;
}


/* =========================================================
   BALANCE
========================================================= */

.balance-card {
    display:flex;
    align-items:center;
    gap:12px;

    padding:12px 18px;

    background:var(--surface-hover);

    border:1px solid var(--border);

    border-radius:14px;
}

.balance-card span {
    font-size:12px;
    color:var(--muted);
}

.balance-card strong {
    color:var(--text);
    font-size:17px;
}


/* =========================================================
   LAYOUT
========================================================= */

.order-layout {
    display:grid;

    grid-template-columns:
        minmax(0,1.7fr)
        minmax(280px,.8fr);

    gap:20px;

    align-items:start;
}


/* =========================================================
   ORDER CARD
========================================================= */

.order-card {
    background:var(--surface);

    border:1px solid var(--border);

    border-radius:20px;

    padding:24px;

    box-shadow:
        0 15px 45px rgba(0,0,0,.18);
}


/* =========================================================
   HEADER
========================================================= */

.order-card-header {
    display:flex;

    align-items:center;

    gap:13px;

    margin-bottom:24px;
}

.order-icon {
    width:44px;
    height:44px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:13px;

    background:var(--surface-hover);

    font-size:21px;
}

.order-card-header h2 {
    margin:0;

    color:var(--text);

    font-size:20px;
}

.order-card-header p {
    margin:4px 0 0;

    color:var(--muted);

    font-size:13px;
}


/* =========================================================
   FORM GROUP
========================================================= */

.form-group {
    margin-bottom:20px;
}

.form-group label {
    display:flex;

    align-items:center;

    justify-content:space-between;

    margin-bottom:9px;

    color:var(--text);

    font-size:13px;

    font-weight:600;
}

.label-hint {
    color:var(--muted-2);

    font-size:11px;

    font-weight:400;
}


/* =========================================================
   SELECT
========================================================= */

.service-select-wrap {
    position:relative;
}

.form-control {
    width:100%;

    box-sizing:border-box;

    padding:15px 45px 15px 15px;

    border:1px solid var(--border);

    border-radius:13px;

    background:var(--surface-deep);

    color:var(--text);

    font-size:14px;

    outline:none;

    transition:.2s;

    appearance:none;

    cursor:pointer;
}

.form-control:hover {
    border-color:var(--border-strong);
}

.form-control:focus {
    border-color:var(--border-strong);

    box-shadow:
        0 0 0 3px var(--accent-bg);
}

.service-select-wrap:after {
    content:"⌄";

    position:absolute;

    right:17px;

    top:50%;

    transform:translateY(-55%);

    color:var(--muted);

    font-size:20px;

    pointer-events:none;
}

input.form-control {
    appearance:none;

    cursor:text;
}

input.form-control::placeholder {
    color:var(--muted-2);
}


/* =========================================================
   CATEGORY SELECT
========================================================= */

.category-select {
    border-color:var(--border-strong);
}

.category-select:focus {
    border-color:var(--border-strong);
}


/* =========================================================
   SERVICE SELECT
========================================================= */

.service-select {
    border-color:var(--border-strong);
}

.service-select:disabled {
    opacity:.55;

    cursor:not-allowed;

    background:var(--surface-deep);
}


/* =========================================================
   SERVICE INFO
========================================================= */

.service-info {
    margin-top:14px;

    padding:17px;

    border:1px solid var(--border);

    border-radius:15px;

    background:
        linear-gradient(
            135deg,
            var(--surface-hover),
            var(--surface)
        );
}

.service-info-top {
    display:flex;

    justify-content:space-between;

    gap:15px;

    margin-bottom:15px;
}

.service-name-box {
    min-width:0;
}

.service-category {
    display:inline-block;

    margin-bottom:6px;

    padding:4px 9px;

    border-radius:20px;

    background:var(--surface-hover);

    color:var(--muted);

    font-size:10px;

    text-transform:uppercase;

    letter-spacing:.5px;
}

.service-name {
    color:var(--text);

    font-size:16px;

    font-weight:700;

    line-height:1.3;
}

.service-description {
    margin-top:6px;

    color:var(--muted);

    font-size:12px;

    line-height:1.5;
}

.service-price {
    flex-shrink:0;

    text-align:right;
}

.service-price small {
    display:block;

    color:var(--muted);

    font-size:10px;

    margin-bottom:3px;
}

.service-price strong {
    color:var(--text);

    font-size:18px;
}


/* =========================================================
   DETAILS
========================================================= */

.service-details {
    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:8px;

    margin-top:15px;
}

.detail-box {
    padding:11px;

    background:var(--surface-hover);

    border:1px solid var(--border);

    border-radius:11px;
}

.detail-box small {
    display:block;

    color:var(--muted-2);

    font-size:9px;

    text-transform:uppercase;

    margin-bottom:5px;
}

.detail-box strong {
    color:var(--text);

    font-size:12px;
}


/* =========================================================
   CHARGE
========================================================= */

.charge-box {
    margin-top:6px;

    padding:20px;

    border-radius:16px;

    background:
        linear-gradient(
            135deg,
            var(--surface-hover),
            var(--surface)
        );

    border:1px solid var(--border);

    display:flex;

    align-items:center;

    justify-content:space-between;
}

.charge-label small {
    display:block;

    color:var(--muted);

    font-size:10px;

    letter-spacing:.8px;

    margin-bottom:5px;
}

.charge-label span {
    color:var(--muted);

    font-size:11px;
}

.charge {
    color:var(--text);

    font-size:26px;

    font-weight:800;
}


/* =========================================================
   BUTTON
========================================================= */

.place-btn {
    width:100%;

    border:0;

    cursor:pointer;

    padding:15px;

    margin-top:16px;

    border-radius:13px;

    background:#fff;

    color:#000;

    font-size:15px;

    font-weight:700;

    transition:.2s;
}

.place-btn:hover {
    transform:translateY(-1px);

    opacity:.92;
}

.place-btn:active {
    transform:translateY(0);
}


/* =========================================================
   ALERT
========================================================= */

.alert {
    padding:14px 16px;

    border-radius:13px;

    margin-bottom:18px;

    font-size:13px;

    border:1px solid transparent;
}

.alert-error {
    background:var(--error-bg);

    color:var(--error-text);

    border-color:var(--error-text);
}

.alert-success {
    background:var(--success-bg);

    color:var(--success-text);

    border-color:var(--success-text);
}


/* =========================================================
   INFO CARD
========================================================= */

.info-card {
    background:var(--surface);

    border:1px solid var(--border);

    border-radius:20px;

    padding:22px;
}

.info-card h3 {
    margin:0 0 17px;

    color:var(--text);

    font-size:16px;
}

.info-item {
    display:flex;

    gap:12px;

    padding:13px 0;

    border-bottom:1px solid var(--border);
}

.info-item:last-child {
    border-bottom:0;
}

.info-number {
    width:28px;
    height:28px;

    flex-shrink:0;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:9px;

    background:var(--surface-hover);

    color:var(--text);

    font-size:11px;

    font-weight:700;
}

.info-item strong {
    display:block;

    color:var(--text);

    font-size:12px;

    margin-bottom:3px;
}

.info-item span {
    color:var(--muted-2);

    font-size:11px;

    line-height:1.4;
}


/* =========================================================
   BALANCE
========================================================= */

.mini-balance {
    margin-top:15px;

    padding:17px;

    border-radius:15px;

    background:var(--surface-hover);

    border:1px solid var(--border);
}

.mini-balance small {
    display:block;

    color:var(--muted-2);

    font-size:10px;

    margin-bottom:6px;
}

.mini-balance strong {
    color:var(--text);

    font-size:21px;
}


/* =========================================================
   EMPTY
========================================================= */

.no-services {
    padding:15px;

    border-radius:13px;

    background:var(--surface-hover);

    border:1px solid var(--border);

    color:var(--muted);

    font-size:12px;

    margin-top:10px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:850px) {

    .order-layout {
        grid-template-columns:1fr;
    }

    .info-card {
        order:2;
    }
}


@media(max-width:600px) {

    .order-card,
    .info-card {
        padding:17px;

        border-radius:16px;
    }

    .service-info-top {
        flex-direction:column;
    }

    .service-price {
        text-align:left;
    }

    .service-details {
        grid-template-columns:1fr 1fr;
    }

    .charge-box {
        flex-direction:column;

        align-items:flex-start;

        gap:10px;
    }

    .charge {
        font-size:24px;
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

<a
    class="active"
    href="order.php"
>
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


<div class="order-page">


<!-- TOP -->

<div class="top">

<div>

<p class="eyebrow">
    ORDER
</p>

<h1>
    Create New Order
</h1>

</div>


<div class="balance-card">

<span>
    Balance
</span>

<strong>
    Rs. <?= number_format(
        (float)$user['balance'],
        2
    ) ?>
</strong>

</div>

</div>


<!-- ORDER LAYOUT -->

<div class="order-layout">


<!-- =====================================================
     ORDER FORM
===================================================== -->

<div class="order-card">


<div class="order-card-header">

<div class="order-icon">
    🛒
</div>

<div>

<h2>
    New Order
</h2>

<p>
    Select a category, then choose your service.
</p>

</div>

</div>


<!-- ALERT -->

<?php if ($error): ?>

<div class="alert alert-error">

<?= e($error) ?>

</div>

<?php endif; ?>


<?php if ($success): ?>

<div class="alert alert-success">

<?= e($success) ?>

</div>

<?php endif; ?>


<form
    method="POST"
    action=""
>


<!-- =====================================================
     STEP 1 — CATEGORY
===================================================== -->

<div class="form-group">

<label>

<span>
    1. Select Category
</span>

<span class="label-hint">
    Choose a service category
</span>

</label>


<div class="service-select-wrap">

<select
    id="categorySelect"
    class="form-control category-select"
    required
>

<option value="">
    Select Category...
</option>


<?php foreach ($categories as $category_name => $category_services): ?>

<option
    value="<?= e($category_name) ?>"
    <?= (
        $selected_category === $category_name
    ) ? 'selected' : '' ?>
>

<?= e($category_name) ?>

</option>

<?php endforeach; ?>


</select>

</div>

</div>


<!-- =====================================================
     STEP 2 — SERVICE
===================================================== -->

<div class="form-group">

<label>

<span>
    2. Select Service
</span>

<span class="label-hint">
    Services for selected category
</span>

</label>


<div class="service-select-wrap">

<select
    name="service_id"
    id="serviceSelect"
    class="form-control service-select"
    required
    <?= empty($selected_category) ? 'disabled' : '' ?>
>

<option value="">
    <?= empty($selected_category)
        ? 'First select a category...'
        : 'Select a service...' ?>
</option>


<?php

if (!empty($selected_category) &&
    isset($categories[$selected_category])
):

    foreach (
        $categories[$selected_category]
        as $s
    ):
?>

<option
    value="<?= (int) $s['id'] ?>"
    data-price="<?= e($s['price']) ?>"
    data-min="<?= (int) $s['min_quantity'] ?>"
    data-max="<?= (int) $s['max_quantity'] ?>"
    data-description="<?= e($s['description']) ?>"
    data-category="<?= e($s['category']) ?>"
    data-name="<?= e($s['name']) ?>"
    <?= (
        $selected_service_id ==
        $s['id']
    ) ? 'selected' : '' ?>
>

<?= e($s['name']) ?>
 — Rs. <?= number_format(
    (float) $s['price'],
    2
) ?> / 1K

</option>

<?php

    endforeach;

endif;

?>


</select>

</div>


<div
    id="noServices"
    class="no-services"
    style="display:none;"
>
    No active services found in this category.
</div>


<!-- SERVICE INFORMATION -->

<div
    id="serviceInfo"
    class="service-info"
    style="display:none;"
>


<div class="service-info-top">


<div class="service-name-box">


<span
    id="category"
    class="service-category"
>
    CATEGORY
</span>


<div
    id="serviceName"
    class="service-name"
>
    Select a service
</div>


<div
    id="description"
    class="service-description"
>
</div>


</div>


<div class="service-price">


<small>
    PRICE / 1K
</small>


<strong id="price">
    Rs. 0.00
</strong>


</div>


</div>


<div class="service-details">


<div class="detail-box">

<small>
    MINIMUM
</small>

<strong id="min">
    0
</strong>

</div>


<div class="detail-box">

<small>
    MAXIMUM
</small>

<strong id="max">
    0
</strong>

</div>


<div class="detail-box">

<small>
    RATE
</small>

<strong id="rate">
    Rs. 0.00 / 1K
</strong>

</div>


</div>


</div>


</div>


<!-- =====================================================
     TARGET LINK
===================================================== -->

<div class="form-group">


<label>

<span>
    Target Link
</span>

<span class="label-hint">
    URL of your target
</span>

</label>


<input
    type="url"
    name="link"
    class="form-control"
    placeholder="https://instagram.com/..."
    required
>


</div>


<!-- =====================================================
     QUANTITY
===================================================== -->

<div class="form-group">


<label>

<span>
    Quantity
</span>

<span
    id="quantityHint"
    class="label-hint"
>
    Select a service first
</span>

</label>


<input
    type="number"
    name="quantity"
    id="quantity"
    class="form-control"
    min="1"
    placeholder="Enter quantity"
    required
>


</div>


<!-- =====================================================
     CHARGE
===================================================== -->

<div class="charge-box">


<div class="charge-label">

<small>
    TOTAL CHARGE
</small>

<span>
    Calculated automatically
</span>

</div>


<div
    class="charge"
    id="charge"
>
    Rs. 0.00
</div>


</div>


<!-- =====================================================
     SUBMIT
===================================================== -->

<button
    type="submit"
    class="place-btn"
>
    🚀 Place Order
</button>


</form>


</div>


<!-- =====================================================
     SIDE INFORMATION
===================================================== -->

<div>


<div class="info-card">


<h3>
    How to Order
</h3>


<div class="info-item">

<div class="info-number">
    1
</div>

<div>

<strong>
    Select Category
</strong>

<span>
    Choose TikTok, Instagram,
    YouTube or another category.
</span>

</div>

</div>


<div class="info-item">

<div class="info-number">
    2
</div>

<div>

<strong>
    Select Service
</strong>

<span>
    Only services belonging to the
    selected category will appear.
</span>

</div>

</div>


<div class="info-item">

<div class="info-number">
    3
</div>

<div>

<strong>
    Enter Link
</strong>

<span>
    Paste the URL of your target
    account, post or video.
</span>

</div>

</div>


<div class="info-item">

<div class="info-number">
    4
</div>

<div>

<strong>
    Enter Quantity
</strong>

<span>
    Stay within the minimum and
    maximum limits shown above.
</span>

</div>

</div>


<div class="info-item">

<div class="info-number">
    5
</div>

<div>

<strong>
    Place Order
</strong>

<span>
    Your order will be submitted
    to the connected provider.
</span>

</div>

</div>


</div>


<div class="mini-balance">


<small>
    AVAILABLE BALANCE
</small>


<strong>

Rs. <?= number_format(
    (float)$user['balance'],
    2
) ?>

</strong>


</div>


</div>


</div>


</div>


</main>


</div>


<script>

/*
|--------------------------------------------------------------------------
| SERVICE DATA FROM PHP
|--------------------------------------------------------------------------
*/

const servicesByCategory =
    <?= json_encode(
        $categories,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    ) ?>;


/*
|--------------------------------------------------------------------------
| ELEMENTS
|--------------------------------------------------------------------------
*/

const categorySelect =
    document.getElementById(
        'categorySelect'
    );

const serviceSelect =
    document.getElementById(
        'serviceSelect'
    );

const quantityInput =
    document.getElementById(
        'quantity'
    );

const chargeBox =
    document.getElementById(
        'charge'
    );

const serviceInfo =
    document.getElementById(
        'serviceInfo'
    );

const noServices =
    document.getElementById(
        'noServices'
    );

const description =
    document.getElementById(
        'description'
    );

const category =
    document.getElementById(
        'category'
    );

const serviceName =
    document.getElementById(
        'serviceName'
    );

const price =
    document.getElementById(
        'price'
    );

const rate =
    document.getElementById(
        'rate'
    );

const min =
    document.getElementById(
        'min'
    );

const max =
    document.getElementById(
        'max'
    );

const quantityHint =
    document.getElementById(
        'quantityHint'
    );


/*
|--------------------------------------------------------------------------
| RESET SERVICE INFORMATION
|--------------------------------------------------------------------------
*/

function resetServiceInfo()
{

    serviceInfo.style.display =
        'none';

    chargeBox.textContent =
        'Rs. 0.00';

    quantityInput.min =
        1;

    quantityInput.max =
        '';

    quantityHint.textContent =
        'Select a service first';

}


/*
|--------------------------------------------------------------------------
| LOAD SERVICES BY CATEGORY
|--------------------------------------------------------------------------
*/

function loadServices()
{

    const selectedCategory =
        categorySelect.value;


    /*
    |--------------------------------------------------------------------------
    | RESET SERVICE DROPDOWN
    |--------------------------------------------------------------------------
    */

    serviceSelect.innerHTML = '';


    const defaultOption =
        document.createElement(
            'option'
        );

    defaultOption.value = '';

    defaultOption.textContent =
        selectedCategory
            ? 'Select a service...'
            : 'First select a category...';

    serviceSelect.appendChild(
        defaultOption
    );


    resetServiceInfo();


    /*
    |--------------------------------------------------------------------------
    | NO CATEGORY
    |--------------------------------------------------------------------------
    */

    if (!selectedCategory) {

        serviceSelect.disabled =
            true;

        noServices.style.display =
            'none';

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | GET SERVICES
    |--------------------------------------------------------------------------
    */

    const services =
        servicesByCategory[
            selectedCategory
        ] || [];


    /*
    |--------------------------------------------------------------------------
    | NO SERVICES
    |--------------------------------------------------------------------------
    */

    if (!services.length) {

        serviceSelect.disabled =
            true;

        noServices.style.display =
            'block';

        return;
    }


    noServices.style.display =
        'none';

    serviceSelect.disabled =
        false;


    /*
    |--------------------------------------------------------------------------
    | ADD SERVICES
    |--------------------------------------------------------------------------
    */

    services.forEach(function(service)
    {

        const option =
            document.createElement(
                'option'
            );


        option.value =
            service.id;


        option.textContent =
            service.name +
            ' — Rs. ' +
            parseFloat(
                service.price
            ).toFixed(2) +
            ' / 1K';


        option.dataset.price =
            service.price;

        option.dataset.min =
            service.min_quantity;

        option.dataset.max =
            service.max_quantity;

        option.dataset.description =
            service.description || '';

        option.dataset.category =
            service.category;

        option.dataset.name =
            service.name;


        serviceSelect.appendChild(
            option
        );

    });

}


/*
|--------------------------------------------------------------------------
| UPDATE SERVICE INFORMATION
|--------------------------------------------------------------------------
*/

function updateService()
{

    const option =
        serviceSelect.options[
            serviceSelect.selectedIndex
        ];


    if (
        !option ||
        !option.value
    ) {

        resetServiceInfo();

        return;
    }


    const servicePrice =
        parseFloat(
            option.dataset.price
        ) || 0;


    const serviceMin =
        parseInt(
            option.dataset.min
        ) || 1;


    const serviceMax =
        parseInt(
            option.dataset.max
        ) || 1000000;


    /*
    |--------------------------------------------------------------------------
    | SERVICE INFORMATION
    |--------------------------------------------------------------------------
    */

    serviceName.textContent =
        option.dataset.name ||
        'Selected Service';


    description.textContent =
        option.dataset.description ||
        'No description available.';


    category.textContent =
        option.dataset.category ||
        'SERVICE';


    price.textContent =
        'Rs. ' +
        servicePrice.toFixed(2);


    rate.textContent =
        'Rs. ' +
        servicePrice.toFixed(2) +
        ' / 1K';


    min.textContent =
        serviceMin.toLocaleString();


    max.textContent =
        serviceMax.toLocaleString();


    /*
    |--------------------------------------------------------------------------
    | QUANTITY LIMITS
    |--------------------------------------------------------------------------
    */

    quantityInput.min =
        serviceMin;

    quantityInput.max =
        serviceMax;


    quantityHint.textContent =
        'Min ' +
        serviceMin.toLocaleString() +
        ' • Max ' +
        serviceMax.toLocaleString();


    /*
    |--------------------------------------------------------------------------
    | SHOW INFO
    |--------------------------------------------------------------------------
    */

    serviceInfo.style.display =
        'block';


    calculateCharge();

}


/*
|--------------------------------------------------------------------------
| CALCULATE CHARGE
|--------------------------------------------------------------------------
*/

function calculateCharge()
{

    const option =
        serviceSelect.options[
            serviceSelect.selectedIndex
        ];


    if (
        !option ||
        !option.value
    ) {

        chargeBox.textContent =
            'Rs. 0.00';

        return;
    }


    const servicePrice =
        parseFloat(
            option.dataset.price
        ) || 0;


    const quantity =
        parseInt(
            quantityInput.value
        ) || 0;


    const charge =
        (
            quantity / 1000
        ) * servicePrice;


    chargeBox.textContent =
        'Rs. ' +
        charge.toFixed(2);

}


/*
|--------------------------------------------------------------------------
| CATEGORY CHANGE
|--------------------------------------------------------------------------
*/

categorySelect.addEventListener(
    'change',
    function()
    {

        loadServices();

    }
);


/*
|--------------------------------------------------------------------------
| SERVICE CHANGE
|--------------------------------------------------------------------------
*/

serviceSelect.addEventListener(
    'change',
    function()
    {

        updateService();

    }
);


/*
|--------------------------------------------------------------------------
| QUANTITY CHANGE
|--------------------------------------------------------------------------
*/

quantityInput.addEventListener(
    'input',
    function()
    {

        calculateCharge();

    }
);


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

<?php if (!empty($selected_category)): ?>

loadServices();

<?php

/*
|--------------------------------------------------------------------------
| RESTORE SELECTED SERVICE
|--------------------------------------------------------------------------
*/

if ($selected_service_id > 0):
?>

setTimeout(function()
{

    serviceSelect.value =
        "<?= (int) $selected_service_id ?>";

    updateService();

}, 0);

<?php endif; ?>

<?php else: ?>

resetServiceInfo();

<?php endif; ?>

</script>


<?php include __DIR__ . "/whatsapp_button.php"; ?>
<?php include __DIR__ . "/theme_toggle.php"; ?>
</body>

</html>