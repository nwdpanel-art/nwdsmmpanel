<?php

require 'config.php';


$status =
    $_POST['status'] ?? '';

$orderRefNum =
    $_POST['orderRefNum'] ?? '';

$description =
    $_POST['desc'] ?? '';


if (!$orderRefNum) {

    http_response_code(400);

    die("Invalid payment reference.");
}


/*
|--------------------------------------------------------------------------
| Find payment
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT *
    FROM payments
    WHERE gateway = 'easypaisa'
    AND reference = ?
    AND status = 'pending'
    FOR UPDATE
");

$stmt->bind_param(
    "s",
    $orderRefNum
);


$db->begin_transaction();


try {

    $stmt->execute();

    $payment =
        $stmt->get_result()->fetch_assoc();


    if (!$payment) {

        throw new Exception(
            "Payment not found or already processed."
        );
    }


    /*
     * Easypaisa legacy hosted response commonly
     * reports Success/Failure.
     *
     * VERIFY the merchant response/hash according
     * to the integration version issued to you
     * before enabling automatic wallet credit.
     */

    if (
        strtolower($status) !==
        'success'
    ) {

        $stmt = $db->prepare("
            UPDATE payments
            SET status = 'failed'
            WHERE id = ?
        ");

        $stmt->bind_param(
            "i",
            $payment['id']
        );

        $stmt->execute();

        $db->commit();

        header(
            "Location: wallet.php?payment=failed"
        );

        exit;
    }


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
     * Transaction
     */

    $type = 'credit';

    $note =
        'Easypaisa Deposit ' .
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
     * Mark completed
     */

    $stmt = $db->prepare("
        UPDATE payments
        SET
            status = 'completed',
            completed_at = NOW()
        WHERE id = ?
    ");

    $stmt->bind_param(
        "i",
        $payment['id']
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