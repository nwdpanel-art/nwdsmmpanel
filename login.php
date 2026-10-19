<?php

require 'config.php';

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $pass  = $_POST['password'];

    $q = $db->prepare("SELECT * FROM users WHERE email=?");
    $q->bind_param("s", $email);
    $q->execute();
    $u = $q->get_result()->fetch_assoc();

    if (
        $u &&
        $u['password'] !== null &&
        password_verify($pass, $u['password'])
    ) {

        $_SESSION['user_id'] = $u['id'];
        header("Location: dashboard.php");
        exit;

    } elseif ($u && $u['password'] === null) {

        $err = "This account uses Google Sign-In. Please continue with Google below.";

    } else {

        $err = "Invalid email or password.";
    }
}

?>
<!doctype html>
<html>
<head>
<?php include __DIR__ . "/theme_init.php"; ?>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="auth">
<div class="auth-box">

<a class="brand" href="index.php">
<?= e(defined('SITE_NAME') ? SITE_NAME : 'NWD PANEL') ?>
</a>

<h1>Welcome back</h1>
<p>Login to your dashboard.</p>

<?php if ($err): ?>
<div class="alert"><?= e($err) ?></div>
<?php endif; ?>

<form method="post">
<label>Email<input type="email" name="email" required></label>
<label>Password<input type="password" name="password" required></label>
<button class="btn primary full">Login</button>
</form>

<?php if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== ''): ?>

<div class="divider">or</div>

<a class="btn-google" href="google_login.php">
<svg width="20" height="20" viewBox="0 0 48 48" style="width:20px;height:20px;flex-shrink:0;">
<path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.9 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l5.7-5.7C34.6 6.1 29.6 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.7-.4-3.5z"/>
<path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.5 15.9 18.9 13 24 13c3.1 0 5.8 1.1 8 3l5.7-5.7C34.6 6.1 29.6 4 24 4c-7.7 0-14.3 4.4-17.7 10.7z"/>
<path fill="#4CAF50" d="M24 44c5.5 0 10.4-1.8 14.3-5l-6.6-5.6C29.6 35.1 27 36 24 36c-5.3 0-9.6-3.1-11.3-7.6l-6.6 5.1C9.6 39.6 16.2 44 24 44z"/>
<path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.2-4.2 5.6l6.6 5.6C39.9 37.2 44 31.4 44 24c0-1.3-.1-2.7-.4-3.5z"/>
</svg>
<span>Continue with Google</span>
</a>

<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'google_cancelled'): ?>
<div class="alert" style="margin-top:15px;">Google sign-in was cancelled.</div>
<?php endif; ?>

<p class="muted">No account? <a href="register.php">Create one</a></p>

</div>
</div>

<?php include __DIR__ . "/theme_toggle.php"; ?>
</body>
</html>