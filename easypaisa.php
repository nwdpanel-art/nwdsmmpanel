<?php

require 'config.php';

auth();

$user_id = $_SESSION['user_id'];

$payment_id =
    (int) ($_GET['id'] ?? 0);


$stmt = $db->prepare("
    SELECT *
    FROM payments
    WHERE id = ?
    AND user_id = ?
    AND gateway = 'easypaisa'
    AND status = 'pending'
");

$stmt->bind_param(
    "ii",
    $payment_id,
    $user_id
);

$stmt->execute();

$payment =
    $stmt->get_result()->fetch_assoc();

if (!$payment) {
    die("Payment not found.");
}


$amount =
    number_format(
        $payment['amount'],
        1,
        '.',
        ''
    );


$orderRefNum =
    substr(
        preg_replace(
            '/[^A-Za-z0-9]/',
            '',
            $payment['reference']
        ),
        0,
        20
    );


$postBackURL =
    ((($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http').'://'.$_SERVER['HTTP_HOST'].'/')).'easypaisa_callback.php';


if (EASYPAISA_MODE === 'sandbox') {

    $gatewayUrl =
        'https://easypaystg.easypaisa.com.pk/easypay/Index.jsf';

} else {

    $gatewayUrl =
        'https://easypay.easypaisa.com.pk/easypay/Index.jsf';
}


/*
|--------------------------------------------------------------------------
| Easypaisa merchant hash
|--------------------------------------------------------------------------
|
| The exact field sequence/algorithm must match the
| version enabled for your merchant account.
|
| This implementation follows the documented
| legacy hosted-post pattern.
|
*/

$fields = [

    'amount' =>
        $amount,

    'autoRedirect' =>
        '1',

    'emailAddr' =>
        '',

    'mobileNum' =>
        '',

    'orderRefNum' =>
        $orderRefNum,

    'paymentMethod' =>
        '0',

    'postBackURL' =>
        $postBackURL,

    'storeId' =>
        EASYPAISA_STORE_ID
];


ksort($fields);


$plainText =
    http_build_query(
        $fields,
        '',
        '&',
        PHP_QUERY_RFC3986
    );


/*
|--------------------------------------------------------------------------
| AES encryption
|--------------------------------------------------------------------------
|
| Easypaisa merchant portal supplies the
| hash/encryption key for the applicable
| integration version.
|
*/

$key =
    EASYPAISA_HASH_KEY;


if (strlen($key) !== 16) {

    die(
        "Configure the Easypaisa Hash Key supplied by your merchant portal."
    );
}


$encrypted =
    openssl_encrypt(
        $plainText,
        'AES-128-ECB',
        $key,
        OPENSSL_RAW_DATA
    );


$merchantHashedReq =
    base64_encode($encrypted);


?>

<!DOCTYPE html>
<html>

<head>

    <meta charset="UTF-8">

    <title>Easypaisa Payment</title>

</head>

<body>

<p>
    Redirecting to Easypaisa...
</p>


<form
    id="easypaisaForm"
    method="POST"
    action="<?= e($gatewayUrl) ?>"
>

<input
    type="hidden"
    name="amount"
    value="<?= e($amount) ?>"
>

<input
    type="hidden"
    name="storeId"
    value="<?= e(EASYPAISA_STORE_ID) ?>"
>

<input
    type="hidden"
    name="postBackURL"
    value="<?= e($postBackURL) ?>"
>

<input
    type="hidden"
    name="orderRefNum"
    value="<?= e($orderRefNum) ?>"
>

<input
    type="hidden"
    name="merchantHashedReq"
    value="<?= e($merchantHashedReq) ?>"
>

<input
    type="hidden"
    name="autoRedirect"
    value="1"
>

<input
    type="hidden"
    name="paymentMethod"
    value="0"
>

</form>


<script>

document
    .getElementById('easypaisaForm')
    .submit();

</script>

</body>

</html>