<?php

require 'config.php';

auth();

$user_id = $_SESSION['user_id'];

$payment_id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT *
    FROM payments
    WHERE id = ?
    AND user_id = ?
    AND gateway = 'jazzcash'
    AND status = 'pending'
");

$stmt->bind_param(
    "ii",
    $payment_id,
    $user_id
);

$stmt->execute();

$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    die("Payment not found.");
}


$amount = number_format(
    $payment['amount'],
    2,
    '.',
    ''
);

$txnRefNo =
    'T' .
    date('YmdHis') .
    rand(100, 999);

$txnDateTime =
    date('YmdHis');

$txnExpiryDateTime =
    date(
        'YmdHis',
        strtotime('+1 day')
    );


if (JAZZCASH_MODE === 'sandbox') {

    $gatewayUrl =
        'https://sandbox.jazzcash.com.pk/ApplicationAPI/API/4.0/Purchase/';

} else {

    /*
     * Use the LIVE endpoint supplied by
     * JazzCash for your merchant account.
     */

    $gatewayUrl =
        'https://payments.jazzcash.com.pk/ApplicationAPI/API/4.0/Purchase/';
}


/*
|--------------------------------------------------------------------------
| JazzCash request
|--------------------------------------------------------------------------
*/

$data = [

    'pp_Version' =>
        '1.1',

    'pp_TxnType' =>
        'MWALLET',

    'pp_Language' =>
        'EN',

    'pp_MerchantID' =>
        JAZZCASH_MERCHANT_ID,

    'pp_SubMerchantID' =>
        '',

    'pp_Password' =>
        JAZZCASH_PASSWORD,

    'pp_TxnRefNo' =>
        $txnRefNo,

    'pp_Amount' =>
        (string) ((int) round(
            $payment['amount'] * 100
        )),

    'pp_TxnCurrency' =>
        'PKR',

    'pp_TxnDateTime' =>
        $txnDateTime,

    'pp_TxnExpiryDateTime' =>
        $txnExpiryDateTime,

    'pp_BillReference' =>
        $payment['reference'],

    'pp_Description' =>
        'Wallet Deposit',

    'pp_ReturnURL' =>
        ((($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http').'://'.$_SERVER['HTTP_HOST'].'/')).'jazzcash_callback.php',

    'ppmpf_1' =>
        $payment['reference'],

    'ppmpf_2' =>
        (string) $payment_id,

    'ppmpf_3' =>
        '',

    'ppmpf_4' =>
        '',

    'ppmpf_5' =>
        ''
];


/*
|--------------------------------------------------------------------------
| Generate JazzCash Secure Hash
|--------------------------------------------------------------------------
*/

function jazzcashHash(
    array $data,
    string $salt
) {

    unset($data['pp_SecureHash']);

    ksort($data);

    $values = [];

    foreach ($data as $value) {

        if ($value !== '') {
            $values[] = $value;
        }
    }

    $string =
        $salt .
        '&' .
        implode('&', $values);

    return hash_hmac(
        'sha256',
        $string,
        $salt
    );
}


$data['pp_SecureHash'] =
    jazzcashHash(
        $data,
        JAZZCASH_INTEGRITY_SALT
    );

?>

<!DOCTYPE html>
<html>

<head>

    <meta charset="UTF-8">

    <title>JazzCash Payment</title>

</head>

<body>

<p>
    Redirecting to JazzCash...
</p>

<form
    id="jazzcashForm"
    method="POST"
    action="<?= e($gatewayUrl) ?>"
>

<?php foreach ($data as $key => $value): ?>

    <input
        type="hidden"
        name="<?= e($key) ?>"
        value="<?= e($value) ?>"
    >

<?php endforeach; ?>

</form>

<script>

document
    .getElementById('jazzcashForm')
    .submit();

</script>

</body>

</html>