<?php
require 'config.php';
auth();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Failed</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<div class="auth">

    <div class="auth-box">

        <h1>Payment Failed</h1>

        <p>
            Your payment could not be completed.
            Your wallet has not been charged.
        </p>

        <a
            href="add_funds.php"
            class="btn primary full"
        >
            Try Again
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