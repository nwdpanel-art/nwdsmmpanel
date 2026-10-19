<?php

require 'config.php';
require_once 'notify.php';
auth();

$user_id = (int)$_SESSION['user_id'];

$message = '';
$error = '';


/*
|--------------------------------------------------------------------------
| SHOW SUCCESS MESSAGE AFTER REDIRECT (PREVENTS RE-SUBMIT ON REFRESH)
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['deposit_success'])) {

    $message = $_SESSION['deposit_success'];
    unset($_SESSION['deposit_success']);
}

/*
|--------------------------------------------------------------------------
| SUBMIT PAYMENT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $method = trim($_POST['method'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $transaction_id = trim($_POST['transaction_id'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | ALLOWED PAYMENT METHODS
    |--------------------------------------------------------------------------
    */

    $allowed_methods = [
        'JazzCash',
        'Easypaisa',
        'Bank Transfer'
    ];

    if (!in_array($method, $allowed_methods, true)) {

        $error = 'Please select a valid payment method.';

    } elseif ($amount <= 0) {

        $error = 'Please enter a valid amount.';

    } elseif ($transaction_id === '') {

        $error = 'Please enter your transaction ID.';

    } elseif (!ctype_digit($transaction_id)) {

        $error = 'Transaction ID must contain numbers only.';

    } elseif (
        strlen($transaction_id) < 11 ||
        strlen($transaction_id) > 20
    ) {

        $error = 'Transaction ID must be between 11 and 20 digits.';

    } else {

        $screenshot = null;

        /*
        |--------------------------------------------------------------------------
        | SCREENSHOT UPLOAD
        |--------------------------------------------------------------------------
        */

        if (
            isset($_FILES['screenshot']) &&
            $_FILES['screenshot']['error'] === UPLOAD_ERR_OK
        ) {

            $allowed = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            if (!in_array(
                $_FILES['screenshot']['type'],
                $allowed,
                true
            )) {

                $error =
                    'Only JPG, PNG or WEBP screenshots are allowed.';

            } elseif (
                $_FILES['screenshot']['size'] >
                5 * 1024 * 1024
            ) {

                $error =
                    'Screenshot must be smaller than 5MB.';

            } else {

                $upload_dir =
                    __DIR__ . '/uploads/payments/';

                if (!is_dir($upload_dir)) {

                    mkdir(
                        $upload_dir,
                        0755,
                        true
                    );
                }

                $extension =
                    strtolower(
                        pathinfo(
                            $_FILES['screenshot']['name'],
                            PATHINFO_EXTENSION
                        )
                    );

                $filename =
                    'payment_' .
                    $user_id . '_' .
                    time() . '_' .
                    bin2hex(random_bytes(4)) .
                    '.' .
                    $extension;

                $destination =
                    $upload_dir . $filename;

                if (
                    move_uploaded_file(
                        $_FILES['screenshot']['tmp_name'],
                        $destination
                    )
                ) {

                    $screenshot = $filename;

                } else {

                    $error =
                        'Unable to upload screenshot.';
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE DEPOSIT
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $stmt = $db->prepare("
                INSERT INTO deposits
                (
                    user_id,
                    method,
                    amount,
                    transaction_id,
                    screenshot,
                    status
                )
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");

            if (!$stmt) {

                $error = $db->error;

            } else {

                $stmt->bind_param(
                    "isdss",
                    $user_id,
                    $method,
                    $amount,
                    $transaction_id,
                    $screenshot
                );

                if ($stmt->execute()) {

                    /*
                    |--------------------------------------------------------------------------
                    | NOTIFY ADMIN (best-effort, never blocks the submission)
                    |--------------------------------------------------------------------------
                    */

                    notify_admin(
                        "💰 New Deposit Request",
                        "User ID: " . $user_id . "\n" .
                        "Method: " . $method . "\n" .
                        "Amount: Rs. " . number_format($amount, 2) . "\n" .
                        "Transaction ID: " . $transaction_id
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | REDIRECT (PREVENTS DUPLICATE SUBMISSION ON PAGE REFRESH)
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['deposit_success'] =
                        'Payment submitted successfully. ' .
                        'Admin verification is pending.';

                    header('Location: add_funds.php');
                    exit;

                } else {

                    $error = $stmt->error;
                }
            }
        }
    }
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

<title>Add Funds - NWD PANEL</title>

<link
    rel="stylesheet"
    href="assets/style.css"
>

<style>

/* =========================================================
   PAGE
========================================================= */

.funds-page {
    max-width: 1100px;
}


/* =========================================================
   PAYMENT GRID
========================================================= */

.payment-grid {

    display:grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(250px,1fr)
        );

    gap:18px;

    margin-bottom:22px;
}


/* =========================================================
   PAYMENT CARD
========================================================= */

.payment-method {

    position:relative;

    padding:22px;

    background:
        linear-gradient(
            135deg,
            var(--surface-deep),
            var(--surface)
        );

    border:1px solid var(--border);

    border-radius:18px;

    transition:.2s;
}


.payment-method:hover {

    border-color:var(--border-strong);

    transform:translateY(-2px);

}


.payment-method h3 {

    margin:0 0 14px;

    color:var(--text);

    font-size:17px;

}


.payment-method p {

    margin:7px 0;

    color:var(--muted);

    font-size:12px;

    line-height:1.5;

}


.payment-number {

    display:inline-block;

    margin:4px 0 10px;

    padding:9px 13px;

    background:var(--surface-hover);

    border:1px solid var(--border);

    border-radius:10px;

    color:var(--text);

    font-size:16px;

    font-weight:700;

    letter-spacing:.4px;

}


/* =========================================================
   ACCOUNT NAME
========================================================= */

.account-name {

    color:var(--muted) !important;

    font-size:12px !important;

}


.account-name strong {

    color:var(--text);

}


/* =========================================================
   BANK DETAILS
========================================================= */

.bank-details {

    margin-top:12px;

    padding:13px;

    background:var(--surface-hover);

    border:1px solid var(--border);

    border-radius:11px;

}


.bank-details p {

    margin:8px 0;

    color:var(--muted);

    font-size:12px;

    line-height:1.5;

}


.bank-details p:first-child {

    margin-top:0;

}


.bank-details p:last-child {

    margin-bottom:0;

}


.bank-details strong {

    color:var(--text);

}


/* =========================================================
   IBAN
========================================================= */

.iban {

    word-break:break-all;

    color:var(--text) !important;

    font-weight:600;

}


/* =========================================================
   INSTRUCTIONS
========================================================= */

.instructions-box {

    margin-bottom:22px;

    padding:22px;

    background:
        linear-gradient(
            135deg,
            var(--surface-hover),
            var(--surface)
        );

    border:1px solid var(--border);

    border-radius:18px;

    box-shadow:
        0 12px 35px rgba(0,0,0,.15);

}


.instructions-header {

    display:flex;

    align-items:center;

    gap:12px;

    margin-bottom:18px;

}


.instructions-icon {

    width:42px;

    height:42px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:var(--surface-hover);

    border:1px solid var(--border);

    border-radius:12px;

    font-size:19px;

}


.instructions-header h2 {

    margin:0;

    color:var(--text);

    font-size:18px;

}


.instructions-header p {

    margin:4px 0 0;

    color:var(--muted-2);

    font-size:11px;

}


/* =========================================================
   INSTRUCTION LIST
========================================================= */

.instruction-list {

    display:grid;

    gap:10px;

}


.instruction-item {

    display:flex;

    gap:12px;

    align-items:flex-start;

    padding:12px;

    background:var(--surface-deep);

    border:1px solid var(--border);

    border-radius:12px;

}


.instruction-number {

    width:27px;

    height:27px;

    flex-shrink:0;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:8px;

    background:var(--surface-hover);

    color:var(--text);

    font-size:11px;

    font-weight:700;

}


.instruction-item strong {

    display:block;

    color:var(--text);

    font-size:12px;

    margin-bottom:3px;

}


.instruction-item span {

    color:var(--muted);

    font-size:11px;

    line-height:1.5;

}


/* =========================================================
   WARNING
========================================================= */

.payment-warning {

    margin-top:15px;

    padding:12px 14px;

    border-radius:11px;

    background:var(--notice-bg);

    border:1px solid var(--gold);

    color:var(--gold);

    font-size:11px;

    line-height:1.5;

}


.payment-warning strong {

    color:var(--text);

}


/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom:18px;

}


.form-group label {

    display:block;

    margin-bottom:8px;

    color:var(--text);

    font-size:13px;

    font-weight:600;

}


.form-group input,
.form-group select {

    width:100%;

    box-sizing:border-box;

    padding:13px 14px;

    border-radius:11px;

    border:1px solid var(--border);

    background:var(--surface-deep);

    color:var(--text);

    outline:none;

    font-size:13px;

    transition:.2s;

}


.form-group input:focus,
.form-group select:focus {

    border-color:var(--muted-2);

    box-shadow:
        0 0 0 3px var(--accent-bg);

}


.form-group input::placeholder {

    color:var(--muted-2);

}


/* =========================================================
   FILE
========================================================= */

.form-group input[type="file"] {

    padding:10px;

    cursor:pointer;

}


/* =========================================================
   ALERTS
========================================================= */

.alert-success {

    background:var(--success-bg);

    color:var(--success-text);

    padding:14px 16px;

    border-radius:12px;

    margin-bottom:20px;

    border:1px solid var(--success-text);

    font-size:13px;

}


.alert-error {

    background:var(--error-bg);

    color:var(--error-text);

    padding:14px 16px;

    border-radius:12px;

    margin-bottom:20px;

    border:1px solid var(--error-text);

    font-size:13px;

}


/* =========================================================
   SUBMIT BUTTON
========================================================= */

.submit-payment-btn {

    width:100%;

    padding:14px;

    border:0;

    border-radius:12px;

    background:#fff;

    color:#000;

    font-size:14px;

    font-weight:700;

    cursor:pointer;

    transition:.2s;

}


.submit-payment-btn:hover {

    opacity:.9;

    transform:translateY(-1px);

}


.submit-payment-btn:active {

    transform:translateY(0);

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:600px) {

    .payment-method,
    .instructions-box,
    .card {

        padding:17px;

    }

    .payment-number {

        font-size:14px;

    }

    .bank-details {

        padding:11px;

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


<div class="funds-page">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="top">

<div>

<p class="eyebrow">
    WALLET
</p>

<h1>
    Add Funds
</h1>

</div>

</div>


<!-- =====================================================
     ALERTS
===================================================== -->

<?php if ($message): ?>

<div class="alert-success">

<?= e($message) ?>

</div>

<?php endif; ?>


<?php if ($error): ?>

<div class="alert-error">

<?= e($error) ?>

</div>

<?php endif; ?>


<!-- =====================================================
     DEPOSIT SUBMITTED POPUP
===================================================== -->

<?php if ($message): ?>

<div
    id="deposit-popup-overlay"
    style="
        display:flex;
        position:fixed;
        top:0; left:0; right:0; bottom:0;
        background:rgba(0,0,0,0.7);
        align-items:center;
        justify-content:center;
        z-index:9999;
    "
>

<div
    style="
        background:var(--surface);
        border:1px solid var(--success-text);
        border-radius:12px;
        max-width:380px;
        width:90%;
        padding:30px 25px;
        text-align:center;
        box-shadow:0 10px 40px rgba(0,0,0,0.5);
    "
>

<div style="font-size:48px; margin-bottom:10px;">
✅
</div>

<h3 style="color:var(--text); margin:0 0 12px;">
Payment Submitted
</h3>

<p style="color:var(--muted); font-size:14px; line-height:1.6; margin:0 0 20px;">
Your payment request has been received. It will be verified and
<strong style="color:var(--success-text);">
added to your account balance within 5 to 30 minutes.
</strong>
</p>

<button
    onclick="document.getElementById('deposit-popup-overlay').style.display='none';"
    style="
        background:var(--success-text);
        color:#fff;
        border:none;
        border-radius:8px;
        padding:10px 30px;
        font-size:14px;
        font-weight:600;
        cursor:pointer;
    "
>
OK, Got it
</button>

</div>

</div>

<?php endif; ?>


<!-- =====================================================
     PAYMENT METHODS
===================================================== -->

<div class="payment-grid">


<!-- JAZZCASH -->

<div class="payment-method">

<h3>
    📱 JazzCash
</h3>

<p>
    Send your payment to:
</p>

<div class="payment-number">
    03093235260
</div>

<p class="account-name">

<strong>
    Account Name:
</strong>

KAMAL UDDIN

</p>

</div>


<!-- EASYPAISA -->

<div class="payment-method">

<h3>
    📱 Easypaisa
</h3>

<p>
    Send your payment to:
</p>

<div class="payment-number">
    03093235260
</div>

<p class="account-name">

<strong>
    Account Name:
</strong>

KAMAL UDDIN
</p>

</div>

<!-- Nayapay --> 

<div class="payment-method">

<h3>
    📱 Sadapay
</h3>

<p>
    Send your payment to:
</p>

<div class="payment-number">
    03093235260
</div>

<p class="account-name">

<strong>
    Account Name:
</strong>

KAMAL UDDIN
</p>

</div>


<!-- BANK TRANSFER -->

<div class="payment-method">

<h3>
    🏦 Bank Transfer
</h3>

<p>
    Send your payment to:
</p>


<div class="bank-details">


<p>

<strong>
    Bank Name:
</strong>

Faysal Bank 

</p>


<p>

<strong>
    Account Holder Name:
</strong>

KAMAL UDDIN

</p>


<p class="iban">

<strong>
    Account No# :
</strong>

3367567000005180
</p>


</div>


<div class="payment-warning">

⚠️ Payment karne ke baad
<strong>
Transaction ID
</strong>
zaroor enter karein.

</div>


</div>


</div>


<!-- =====================================================
     PAYMENT INSTRUCTIONS
===================================================== -->

<div class="instructions-box">


<div class="instructions-header">


<div class="instructions-icon">
    💳
</div>


<div>

<h2>
    Payment Instructions
</h2>

<p>
    Follow these steps to add balance to your wallet.
</p>

</div>


</div>


<div class="instruction-list">


<div class="instruction-item">

<div class="instruction-number">
    1
</div>

<div>

<strong>
    Select Payment Method
</strong>

<span>
    Choose JazzCash, Easypaisa or Bank Transfer
    from the payment method field below.
</span>

</div>

</div>


<div class="instruction-item">

<div class="instruction-number">
    2
</div>

<div>

<strong>
    Send Payment
</strong>

<span>
    Send the amount you want to add to the
    corresponding payment account shown above.
</span>

</div>

</div>


<div class="instruction-item">

<div class="instruction-number">
    3
</div>

<div>

<strong>
    Save Transaction ID
</strong>

<span>
    After completing the payment, copy the
    transaction ID from your payment receipt.
</span>

</div>

</div>


<div class="instruction-item">

<div class="instruction-number">
    4
</div>

<div>

<strong>
    Upload Screenshot
</strong>

<span>
    Upload a clear screenshot of your successful
    payment as proof.
</span>

</div>

</div>


<div class="instruction-item">

<div class="instruction-number">
    5
</div>

<div>

<strong>
    Submit Payment
</strong>

<span>
    Enter all details correctly and click
    "Submit Payment". Admin will verify your payment.
</span>

</div>

</div>


</div>


<div class="payment-warning">

⚠️ <strong>Important:</strong>
Please make sure the payment amount, transaction ID
and screenshot are correct. Your wallet balance will
be updated after admin verification.

</div>


</div>


<!-- =====================================================
     SUBMIT PAYMENT
===================================================== -->

<div class="card">


<h2>
    Submit Payment
</h2>


<form
    method="POST"
    enctype="multipart/form-data"
>


<!-- PAYMENT METHOD -->

<div class="form-group">

<label>
    Payment Method
</label>


<select
    name="method"
    required
>


<option value="">
    Select Payment Method
</option>


<option value="JazzCash">
    📱 JazzCash
</option>


<option value="Easypaisa">
    📱 Easypaisa
</option>


<option value="Easypaisa">
    📱 Sadapay
</option>


<option value="Bank Transfer">
    🏦 Bank Transfer
</option>


</select>

</div>


<!-- AMOUNT -->

<div class="form-group">

<label>
    Amount (PKR)
</label>


<input
    type="number"
    name="amount"
    min="1"
    step="0.01"
    placeholder="1000"
    required
>

</div>


<!-- TRANSACTION ID -->

<div class="form-group">

<label>
    Transaction ID
</label>


<input
    type="text"
    name="transaction_id"
    id="transaction_id"
    placeholder="Enter transaction ID (11-20 digits)"
    inputmode="numeric"
    pattern="[0-9]{11,20}"
    minlength="11"
    maxlength="20"
    title="Transaction ID must be 11 to 20 digits, numbers only."
    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
    required
>

<small
    id="transaction_id_error"
    style="color:var(--error-text); display:none;"
>
Transaction ID must be 11 to 20 digits, numbers only.
</small>

<script>
document.getElementById('transaction_id').addEventListener('invalid', function () {
    document.getElementById('transaction_id_error').style.display = 'block';
});
document.getElementById('transaction_id').addEventListener('input', function () {
    document.getElementById('transaction_id_error').style.display = 'none';
});
</script>

</div>


<!-- SCREENSHOT -->

<div class="form-group">

<label>
    Payment Screenshot
</label>


<input
    type="file"
    name="screenshot"
    accept="image/jpeg,image/png,image/webp"
>


</div>


<!-- SUBMIT -->

<button
    type="submit"
    class="submit-payment-btn"
>
    💳 Submit Payment
</button>


</form>


</div>


</div>


</main>


</div>


<?php include __DIR__ . "/whatsapp_button.php"; ?>
<?php include __DIR__ . "/theme_toggle.php"; ?>
</body>

</html>