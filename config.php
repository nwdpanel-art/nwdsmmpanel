<?php

session_start();

$db = mysqli_init();
mysqli_report(MYSQLI_REPORT_OFF);
$dbSsl = getenv('DB_SSL');
if ($dbSsl) {
    // Managed MySQL (TiDB Cloud, Aiven, ...) requires TLS
    $db->ssl_set(null, null, null, null, null);
}
@$db->real_connect(
    getenv('DB_HOST') ?: 'localhost',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    getenv('DB_NAME') ?: 'smm_panel',
    (int)(getenv('DB_PORT') ?: 3306),
    null,
    $dbSsl ? MYSQLI_CLIENT_SSL : 0
);

if ($db->connect_error) {
    die("Database connection failed.");
}

$db->set_charset("utf8mb4");


/*
|--------------------------------------------------------------------------
| USER AUTHENTICATION
|--------------------------------------------------------------------------
*/

function auth()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| ADMIN AUTHENTICATION
|--------------------------------------------------------------------------
*/

function admin()
{
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| SECURITY / ESCAPE
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| JAZZCASH
|--------------------------------------------------------------------------
*/

define(
    'JAZZCASH_MERCHANT_ID',
    getenv('JAZZCASH_MERCHANT_ID') ?: 'YOUR_MERCHANT_ID'
);

define(
    'JAZZCASH_PASSWORD',
    getenv('JAZZCASH_PASSWORD') ?: 'YOUR_PASSWORD'
);

define(
    'JAZZCASH_INTEGRITY_SALT',
    getenv('JAZZCASH_INTEGRITY_SALT') ?: 'YOUR_INTEGRITY_SALT'
);

define(
    'JAZZCASH_MODE',
    getenv('JAZZCASH_MODE') ?: 'sandbox'
);


/*
|--------------------------------------------------------------------------
| EASYPAISA
|--------------------------------------------------------------------------
*/

define(
    'EASYPAISA_STORE_ID',
    getenv('EASYPAISA_STORE_ID') ?: 'YOUR_STORE_ID'
);

define(
    'EASYPAISA_HASH_KEY',
    getenv('EASYPAISA_HASH_KEY') ?: 'YOUR_HASH_KEY'
);

define(
    'EASYPAISA_MODE',
    getenv('EASYPAISA_MODE') ?: 'sandbox'
);


/*
|--------------------------------------------------------------------------
| CRON SECRET
|--------------------------------------------------------------------------
|
| Protects cron/*.php scripts when triggered over HTTP (e.g. cPanel cron
| calling a URL instead of running the PHP CLI directly). Change this to
| your own random value and use the same value in your cron job command.
|
*/

define(
    'CRON_SECRET',
    getenv('CRON_SECRET') ?: 'a8dfc6286dfa673ade4f5139f125e6fa3e99d7fc925eff88'
);


/*
|--------------------------------------------------------------------------
| SITE NAME
|--------------------------------------------------------------------------
*/

define(
    'SITE_NAME',
    getenv('SITE_NAME') ?: 'NWD PANEL'
);


/*
|--------------------------------------------------------------------------
| SITE WHATSAPP NUMBERS (FOR THE FLOATING CHAT BUTTONS)
|--------------------------------------------------------------------------
|
| Shown to customers as "Chat with us" buttons. Country code, no + or
| spaces (e.g. 923001234567). Leave either blank to hide just that
| button (SITE_WHATSAPP_LABEL_1/2 are optional tooltip labels, e.g.
| "Sales" / "Support").
|
*/

define(
    'SITE_WHATSAPP_NUMBER_1',
    getenv('SITE_WHATSAPP_NUMBER_1') ?: '923093235260'
);

define(
    'SITE_WHATSAPP_LABEL_1',
    getenv('SITE_WHATSAPP_LABEL_1') ?: 'Chat with us on WhatsApp'
);

define(
    'SITE_WHATSAPP_NUMBER_2',
    getenv('SITE_WHATSAPP_NUMBER_2') ?: ''
);

define(
    'SITE_WHATSAPP_LABEL_2',
    getenv('SITE_WHATSAPP_LABEL_2') ?: 'Chat with us on WhatsApp'
);


/*
|--------------------------------------------------------------------------
| GOOGLE LOGIN (OAUTH 2.0)
|--------------------------------------------------------------------------
|
| Get these from Google Cloud Console > APIs & Services > Credentials.
| GOOGLE_REDIRECT_URI must exactly match what you added there, e.g.
| http://localhost/SMM_PRO_Legitimated_Panel/google_callback.php (local)
| or https://yourdomain.com/google_callback.php (live).
| Leave GOOGLE_CLIENT_ID blank to hide the "Continue with Google" button.
|
*/

define(
    'GOOGLE_CLIENT_ID',
    getenv('GOOGLE_CLIENT_ID') ?: ''
);

define(
    'GOOGLE_CLIENT_SECRET',
    getenv('GOOGLE_CLIENT_SECRET') ?: ''
);

define(
    'GOOGLE_REDIRECT_URI',
    getenv('GOOGLE_REDIRECT_URI') ?: ''
);


/*
|--------------------------------------------------------------------------
| ADMIN NOTIFICATIONS - WHATSAPP (via Meta Cloud API, official)
|--------------------------------------------------------------------------
|
| Setup instructions are in notify.php. Leave any of these blank to
| disable WhatsApp notifications.
|
*/

define(
    'WHATSAPP_NOTIFY_ENABLED',
    (getenv('WHATSAPP_NOTIFY_ENABLED') ?: 'true') === 'true'
);

define(
    'META_WHATSAPP_PHONE_NUMBER_ID',
    getenv('META_WHATSAPP_PHONE_NUMBER_ID') ?: ''
);

define(
    'META_WHATSAPP_ACCESS_TOKEN',
    getenv('META_WHATSAPP_ACCESS_TOKEN') ?: ''
);

define(
    'META_WHATSAPP_RECIPIENT',
    getenv('META_WHATSAPP_RECIPIENT') ?: ''  // e.g. 923001234567 (with country code, no + or spaces)
);

define(
    'META_WHATSAPP_TEMPLATE_NAME',
    getenv('META_WHATSAPP_TEMPLATE_NAME') ?: ''  // exact template name from WhatsApp Manager
);



/*
|--------------------------------------------------------------------------
| ADMIN NOTIFICATIONS - EMAIL (via Gmail SMTP)
|--------------------------------------------------------------------------
|
| Setup instructions are in notify.php. GMAIL_APP_PASSWORD must be a
| Gmail "App Password" (16 characters), NOT your normal Gmail password.
|
*/

define(
    'EMAIL_NOTIFY_ENABLED',
    (getenv('EMAIL_NOTIFY_ENABLED') ?: 'true') === 'true'
);

define(
    'GMAIL_ADDRESS',
    getenv('GMAIL_ADDRESS') ?: ''
);

define(
    'GMAIL_APP_PASSWORD',
    getenv('GMAIL_APP_PASSWORD') ?: ''
);

define(
    'ADMIN_NOTIFY_EMAIL',
    getenv('ADMIN_NOTIFY_EMAIL') ?: ''  // where notifications are sent (can be same as GMAIL_ADDRESS)
);