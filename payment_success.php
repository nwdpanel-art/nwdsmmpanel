<?php
require 'config.php';
auth();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Successful</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<div class="auth">

    <div class="auth-box">

        <h1>Payment Successful</h1>

        <p>
            Your payment has been successfully processed.
        </p>

        <a
            href="wallet.php"
            class="btn primary full"
        >
            Go to Wallet
        </a>

        <br><br>

        <a
            href="dashboard.php"
            class="btn full"
        >
            Dashboard
        </a>

    </div>

</div>

</body>
</html>