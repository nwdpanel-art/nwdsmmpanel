<?php

require '../config.php';
admin();

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| APPROVE / REJECT DEPOSIT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $deposit_id = (int)($_POST['deposit_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($deposit_id <= 0) {
        $error = 'Invalid deposit.';
    } elseif (!in_array($action, ['approve', 'reject'], true)) {
        $error = 'Invalid action.';
    } else {

        $db->begin_transaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | LOCK DEPOSIT
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare("
                SELECT *
                FROM deposits
                WHERE id = ?
                FOR UPDATE
            ");

            if (!$stmt) {
                throw new Exception($db->error);
            }

            $stmt->bind_param(
                "i",
                $deposit_id
            );

            $stmt->execute();

            $deposit = $stmt
                ->get_result()
                ->fetch_assoc();

            if (!$deposit) {
                throw new Exception(
                    'Deposit not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | PREVENT DOUBLE APPROVAL
            |--------------------------------------------------------------------------
            */

            if ($deposit['status'] !== 'pending') {
                throw new Exception(
                    'This deposit has already been processed.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | APPROVE
            |--------------------------------------------------------------------------
            */

            if ($action === 'approve') {

                $user_id = (int)$deposit['user_id'];
                $amount = (float)$deposit['amount'];

                /*
                | Add amount to user balance
                */

                $stmt = $db->prepare("
                    UPDATE users
                    SET balance = balance + ?
                    WHERE id = ?
                ");

                if (!$stmt) {
                    throw new Exception($db->error);
                }

                $stmt->bind_param(
                    "di",
                    $amount,
                    $user_id
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        $stmt->error
                    );
                }

                /*
                | Mark deposit approved
                */

                $stmt = $db->prepare("
                    UPDATE deposits
                    SET
                        status = 'approved',
                        updated_at = NOW()
                    WHERE id = ?
                ");

                if (!$stmt) {
                    throw new Exception($db->error);
                }

                $stmt->bind_param(
                    "i",
                    $deposit_id
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        $stmt->error
                    );
                }

                $message =
                    'Payment approved and Rs. ' .
                    number_format($amount, 2) .
                    ' added to user balance.';
            }

            /*
            |--------------------------------------------------------------------------
            | REJECT
            |--------------------------------------------------------------------------
            */

            if ($action === 'reject') {

                $stmt = $db->prepare("
                    UPDATE deposits
                    SET
                        status = 'rejected',
                        updated_at = NOW()
                    WHERE id = ?
                ");

                if (!$stmt) {
                    throw new Exception($db->error);
                }

                $stmt->bind_param(
                    "i",
                    $deposit_id
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        $stmt->error
                    );
                }

                $message =
                    'Payment rejected successfully.';
            }

            $db->commit();

        } catch (Throwable $e) {

            $db->rollback();

            $error = $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET DEPOSITS
|--------------------------------------------------------------------------
*/

$deposits = $db->query("
    SELECT
        d.*,
        u.name,
        u.email
    FROM deposits d
    LEFT JOIN users u
        ON u.id = d.user_id
    ORDER BY d.id DESC
");

if (!$deposits) {
    $error = $db->error;
}

?>

<!doctype html>

<html>

<head>
<?php include __DIR__ . "/../theme_init.php"; ?>


<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>

<title>Deposits</title>

<link
    rel="stylesheet"
    href="../assets/style.css"
>

<style>

.deposit-card {
    overflow: hidden;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid var(--border);
}

th {
    color: var(--muted-2);
    font-size: 12px;
    text-transform: uppercase;
}

.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
}

.status.pending {
    background: var(--notice-bg);
    color: var(--gold);
}

.status.approved {
    background: var(--success-bg);
    color: var(--success-text);
}

.status.rejected {
    background: var(--error-bg);
    color: var(--error-text);
}

.method {
    font-weight: 600;
}

.actions {
    display: flex;
    gap: 8px;
}

.btn-approve,
.btn-reject {
    border: 0;
    padding: 8px 12px;
    border-radius: 7px;
    cursor: pointer;
    color: white;
}

.btn-approve {
    background: #16a34a;
}

.btn-reject {
    background: #dc2626;
}

.screenshot-link {
    color: var(--accent-light);
    text-decoration: none;
}

.alert-success {
    background: var(--success-bg);
    border: 1px solid var(--success-text);
    color: var(--success-text);
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.alert-error {
    background: var(--error-bg);
    border: 1px solid var(--error-text);
    color: var(--error-text);
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 20px;
}

</style>

</head>


<body>

<div class="app">


<!-- SIDEBAR -->

<?php include __DIR__ . "/_sidebar.php"; ?>


<!-- MAIN -->

<main class="main">


<div class="top">

<div>

<p class="eyebrow">
PAYMENTS
</p>

<h1>
Deposits
</h1>

</div>

</div>


<?php if ($message): ?>

<div class="alert-success">

<?= e($message) ?>

</div>

<?php endif; ?>


<?php if ($error): ?>

<div class="alert-error">

<?= e($error) ?>

</div>

<?php endif; ?>


<div class="card deposit-card">

<div class="card-head">

<h2>
Payment Requests
</h2>

</div>


<div class="table-wrap">

<table>

<thead>

<tr>

<th>ID</th>
<th>User</th>
<th>Method</th>
<th>Amount</th>
<th>Transaction ID</th>
<th>Screenshot</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>


<tbody>

<?php if ($deposits && $deposits->num_rows > 0): ?>

<?php while ($d = $deposits->fetch_assoc()): ?>

<tr>

<td>
#<?= (int)$d['id'] ?>
</td>


<td>

<strong>
<?= e($d['name'] ?? 'Unknown') ?>
</strong>

<br>

<small>
<?= e($d['email'] ?? '') ?>
</small>

</td>


<td>

<span class="method">

<?= e($d['method']) ?>

</span>

</td>


<td>

<strong>
Rs. <?= number_format(
    (float)$d['amount'],
    2
) ?>
</strong>

</td>


<td>

<?= e($d['transaction_id']) ?>

</td>


<td>

<?php if (!empty($d['screenshot'])): ?>

<a
    class="screenshot-link"
    href="../uploads/payments/<?= e($d['screenshot']) ?>"
    target="_blank"
>
View
</a>

<?php else: ?>

No screenshot

<?php endif; ?>

</td>


<td>

<span
    class="status <?= e($d['status']) ?>"
>

<?= e(
    ucfirst($d['status'])
) ?>

</span>

</td>


<td>

<?php if ($d['status'] === 'pending'): ?>

<div class="actions">


<form method="POST">

<input
    type="hidden"
    name="deposit_id"
    value="<?= (int)$d['id'] ?>"
>

<input
    type="hidden"
    name="action"
    value="approve"
>

<button
    type="submit"
    class="btn-approve"
    onclick="return confirm(
        'Approve this payment and add funds to the user?'
    )"
>
Approve
</button>

</form>


<form method="POST">

<input
    type="hidden"
    name="deposit_id"
    value="<?= (int)$d['id'] ?>"
>

<input
    type="hidden"
    name="action"
    value="reject"
>

<button
    type="submit"
    class="btn-reject"
    onclick="return confirm(
        'Reject this payment?'
    )"
>
Reject
</button>

</form>

</div>

<?php else: ?>

—

<?php endif; ?>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td
    colspan="8"
    style="text-align:center"
>
No payment requests found.
</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</main>

</div>


</body>

</html>