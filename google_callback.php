<?php

require 'config.php';

if (
    GOOGLE_CLIENT_ID === '' ||
    GOOGLE_CLIENT_SECRET === '' ||
    GOOGLE_REDIRECT_URI === ''
) {
    die('Google login is not configured yet. Please contact support.');
}


/*
|--------------------------------------------------------------------------
| VERIFY STATE (CSRF PROTECTION)
|--------------------------------------------------------------------------
*/

$state = $_GET['state'] ?? '';

if (
    !isset($_SESSION['google_oauth_state']) ||
    !hash_equals($_SESSION['google_oauth_state'], $state)
) {
    unset($_SESSION['google_oauth_state']);
    die('Invalid login request. Please try again from the login page.');
}

unset($_SESSION['google_oauth_state']);


if (!isset($_GET['code'])) {
    header('Location: login.php?error=google_cancelled');
    exit;
}


/*
|--------------------------------------------------------------------------
| EXCHANGE AUTHORIZATION CODE FOR ACCESS TOKEN
|--------------------------------------------------------------------------
*/

$ch = curl_init('https://oauth2.googleapis.com/token');

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'code'          => $_GET['code'],
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]));

$token_response = curl_exec($ch);
curl_close($ch);

$token_data = json_decode($token_response, true);

if (empty($token_data['access_token'])) {
    die('Google login failed (could not get access token). Please try again.');
}


/*
|--------------------------------------------------------------------------
| FETCH USER INFO FROM GOOGLE
|--------------------------------------------------------------------------
*/

$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token_data['access_token'],
]);

$userinfo_response = curl_exec($ch);
curl_close($ch);

$google_user = json_decode($userinfo_response, true);

if (
    empty($google_user['sub']) ||
    empty($google_user['email'])
) {
    die('Google login failed (could not get profile info). Please try again.');
}

$google_id     = $google_user['sub'];
$google_email  = $google_user['email'];
$google_name   = $google_user['name'] ?? explode('@', $google_email)[0];


/*
|--------------------------------------------------------------------------
| FIND OR CREATE THE LOCAL USER ACCOUNT
|--------------------------------------------------------------------------
*/

// 1. Already linked to this Google account?

$stmt = $db->prepare("SELECT id FROM users WHERE google_id = ? LIMIT 1");
$stmt->bind_param("s", $google_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {

    $_SESSION['user_id'] = $user['id'];
    header('Location: dashboard.php');
    exit;
}


// 2. An account with this email already exists (registered normally)?
//    Link the Google ID to it so future logins can use either method.

$stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $google_email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {

    $stmt = $db->prepare("UPDATE users SET google_id = ? WHERE id = ?");
    $stmt->bind_param("si", $google_id, $user['id']);
    $stmt->execute();

    $_SESSION['user_id'] = $user['id'];
    header('Location: dashboard.php');
    exit;
}


// 3. Brand new user - create the account (no local password needed).

$stmt = $db->prepare("
    INSERT INTO users (name, email, google_id, password, balance)
    VALUES (?, ?, ?, NULL, 0.00)
");

$stmt->bind_param("sss", $google_name, $google_email, $google_id);
$stmt->execute();

$_SESSION['user_id'] = $stmt->insert_id;

header('Location: dashboard.php');
exit;