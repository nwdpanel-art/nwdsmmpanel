<?php

require 'config.php';


$responseCode =
    $_POST['pp_ResponseCode'] ?? '';

$reference =
    $_POST['ppmpf_1'] ?? '';

$txnRefNo =
    $_POST['pp_TxnRefNo'] ?? '';

$paymentId =
    (int) ($_POST['ppmpf_2'] ?? 0);


/*
|--------------------------------------------------------------------------
| Only successful JazzCash response
|--------------------------------------------------------------------------
|
| JazzCash documents 000 as successful.
|
*/

if ($responseCode !== '000') {

    if ($paymentId > 0) {

        $stmt = $db->prepare("
            UPDATE payments
            SET
                status = 'failed',
                gateway_reference = ?
            WHERE id = ?
            AND status = 'pending'
        ");

        $stmt->bind_param(
            "si",
            $txnRefNo,
            $paymentId
        );

        $stmt->execute();
    }

    die("Payment failed.");
}


/*
|--------------------------------------------------------------------------
| Find payment
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT *
    FROM payments
    WHERE id = ?
    AND reference = ?
    AND status = 'pending'
    FOR UPDATE
");

$stmt->bind_param(
    "is",
    $paymentId,
    $reference
);

$db->begin_transaction();

try {

    $stmt->execute();

    $payment =
        $stmt->get_result()->fetch_assoc();

    if (!$payment) {

        throw new Exception(
            "Payment already processed."
        );
    }


    /*
     * IMPORTANT:
     *
     * Production version should additionally
     * verify JazzCash response SecureHash
     * before crediting wallet.
     */


    $userId =
        (int) $payment['user_id'];

    $amount =
        (float) $payment['amount'];


    /*
     * Credit wallet
     */

    $stmt = $db->prepare("
        UPDATE users
        SET balance = balance + ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "di",
        $amount,
        $userId
    );

    $stmt->execute();


    /*
     * Transaction record
     */

    $type = 'credit';

    $note =
        'JazzCash Deposit ' .
        $payment['reference'];

    $stmt = $db->prepare("
        INSERT INTO transactions
        (
            user_id,
            amount,
            type,
            note
        )
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "idss",
        $userId,
        $amount,
        $type,
        $note
    );

    $stmt->execute();


    /*
     * Mark payment completed
     */

    $stmt = $db->prepare("
        UPDATE payments
        SET
            status = 'completed',
            gateway_reference = ?,
            completed_at = NOW()
        WHERE id = ?
    ");

    $stmt->bind_param(
        "si",
        $txnRefNo,
        $paymentId
    );

    $stmt->execute();


    $db->commit();


    header(
        "Location: wallet.php?payment=success"
    );

    exit;


} catch (Throwable $e) {

    $db->rollback();

    http_response_code(500);

    die("Payment processing error.");
}