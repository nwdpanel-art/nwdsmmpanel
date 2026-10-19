<?php

require 'config.php';

if (
    GOOGLE_CLIENT_ID === '' ||
    GOOGLE_REDIRECT_URI === ''
) {
    die('Google login is not configured yet. Please contact support.');
}


/*
|--------------------------------------------------------------------------
| CSRF PROTECTION (STATE PARAMETER)
|--------------------------------------------------------------------------
*/

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;


/*
|--------------------------------------------------------------------------
| BUILD GOOGLE AUTH URL AND REDIRECT
|--------------------------------------------------------------------------
*/

$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'prompt'        => 'select_account',
];

$auth_url =
    'https://accounts.google.com/o/oauth2/v2/auth?' .
    http_build_query($params);

header('Location: ' . $auth_url);
exit;