<?php

require '../config.php';
admin();

require_once '../api.php';

$message = '';
$error   = '';


/*
|--------------------------------------------------------------------------
| ADD PROVIDER
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_provider'])) {

    $name            = trim($_POST['name'] ?? '');
    $api_url         = trim($_POST['api_url'] ?? '');
    $api_key         = trim($_POST['api_key'] ?? '');
    $usd_to_pkr_rate = (float) ($_POST['usd_to_pkr_rate'] ?? 0);
    $markup_percent  = (float) ($_POST['markup_percent'] ?? 50);

    if ($name === '') {
        $error = "Provider name is required.";
    } elseif ($api_url === '') {
        $error = "API URL is required.";
    } elseif ($api_key === '') {
        $error = "API key is required.";
    } elseif ($usd_to_pkr_rate <= 0) {
        $error = "USD to PKR exchange rate must be greater than 0.";
    } else {

        $stmt = $db->prepare("
            INSERT INTO providers
            (name, api_url, api_key, usd_to_pkr_rate, markup_percent, status)
            VALUES (?, ?, ?, ?, ?, 1)
        ");

        $stmt->bind_param(
            "sssdd",
            $name,
            $api_url,
            $api_key,
            $usd_to_pkr_rate,
            $markup_percent
        );

        if ($stmt->execute()) {
            $message = "Provider \"" . $name . "\" added.";
        } else {
            $error = "Could not add provider: " . $stmt->error;
        }
    }
}


/*
|--------------------------------------------------------------------------
| UPDATE PROVIDER
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_provider'])) {

    $provider_id     = (int) ($_POST['provider_id'] ?? 0);
    $name            = trim($_POST['name'] ?? '');
    $api_url         = trim($_POST['api_url'] ?? '');
    $api_key         = trim($_POST['api_key'] ?? '');
    $usd_to_pkr_rate = (float) ($_POST['usd_to_pkr_rate'] ?? 0);
    $markup_percent  = (float) ($_POST['markup_percent'] ?? 50);

    $stmt = $db->prepare("
        UPDATE providers
        SET name = ?, api_url = ?, api_key = ?, usd_to_pkr_rate = ?, markup_percent = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sssddi",
        $name,
        $api_url,
        $api_key,
        $usd_to_pkr_rate,
        $markup_percent,
        $provider_id
    );

    if ($stmt->execute()) {
        $message = "Provider updated.";
    } else {
        $error = "Could not update provider: " . $stmt->error;
    }
}


/*
|--------------------------------------------------------------------------
| TOGGLE STATUS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {

    $provider_id = (int) ($_POST['provider_id'] ?? 0);

    $db->query("
        UPDATE providers
        SET status = 1 - status
        WHERE id = " . $provider_id
    );

    $message = "Provider status updated.";
}


/*
|--------------------------------------------------------------------------
| DELETE PROVIDER
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_provider'])) {

    $provider_id = (int) ($_POST['provider_id'] ?? 0);

    $in_use = $db->query("
        SELECT COUNT(*) AS c FROM services WHERE provider_id = " . $provider_id
    )->fetch_assoc();

    if ($in_use && $in_use['c'] > 0) {
        $error = "Cannot delete — " . $in_use['c'] . " service(s) still use this provider. Reassign or delete them first.";
    } else {
        $db->query("DELETE FROM providers WHERE id = " . $provider_id);
        $message = "Provider deleted.";
    }
}


/*
|--------------------------------------------------------------------------
| TEST CONNECTION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_provider'])) {

    $provider_id = (int) ($_POST['provider_id'] ?? 0);

    try {

        $result = provider_balance($provider_id);

        if (isset($result['status']) && $result['status'] === 'success') {

            $message = "Connected successfully. Balance: $" .
                number_format((float) $result['balance'], 4);

        } elseif (isset($result['balance'])) {

            $message = "Connected successfully. Balance: $" .
                number_format((float) $result['balance'], 4);

        } else {
            $error = "API responded but did not return a balance.";
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| LIST PROVIDERS (with how many services each one powers)
|--------------------------------------------------------------------------
*/

$providers = [];

$result = $db->query("
    SELECT
        p.*,
        COUNT(s.id) AS service_count
    FROM providers p
    LEFT JOIN services s ON s.provider_id = p.id
    GROUP BY p.id
    ORDER BY p.name ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $providers[] = $row;
    }
}

?>

<!doctype html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Providers</title>
<link rel="stylesheet" href="../assets/style.css">

<style>

.provider-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 16px;
}

.provider-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.provider-head h3 {
    margin: 0;
    color: var(--text);
    font-size: 16px;
}

.provider-meta {
    color: var(--muted);
    font-size: 12px;
}

.provider-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    flex-wrap: wrap;
}

.status-pill {
    font-size: 11px;
    padding: 3px 9px;
    border-radius: 20px;
}

.status-pill.on {
    background: var(--success-bg);
    color: var(--success-text);
}

.status-pill.off {
    background: var(--error-bg);
    color: var(--error-text);
}

</style>

</head>

<body>

<div class="app">

<?php include __DIR__ . "/_sidebar.php"; ?>


<main class="main">

<div class="top">
<div>
<p class="eyebrow">ADMIN</p>
<h1>Provider APIs</h1>
</div>
</div>


<?php if ($message): ?>
<div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>


<!-- =====================================================
     EXISTING PROVIDERS
===================================================== -->

<?php foreach ($providers as $p): ?>

<div class="provider-card">

<div class="provider-head">

<div>
<h3><?= e($p['name']) ?></h3>
<div class="provider-meta">
    <?= (int) $p['service_count'] ?> service(s) imported ·
    Rate: Rs. <?= number_format((float) $p['usd_to_pkr_rate'], 2) ?> / $1 ·
    Markup: <?= number_format((float) $p['markup_percent'], 2) ?>%
</div>
</div>

<span class="status-pill <?= $p['status'] ? 'on' : 'off' ?>">
    <?= $p['status'] ? 'Active' : 'Disabled' ?>
</span>

</div>

<details>
<summary style="cursor:pointer;color:var(--muted);font-size:13px;">Edit details</summary>

<form method="POST" style="margin-top:12px;">

<input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">

<div class="form-group">
<label>Provider Name</label>
<input class="form-control" type="text" name="name" value="<?= e($p['name']) ?>" required>
</div>

<div class="form-group">
<label>API URL</label>
<input class="form-control" type="url" name="api_url" value="<?= e($p['api_url']) ?>" required>
</div>

<div class="form-group">
<label>API Key</label>
<input class="form-control" type="password" name="api_key" value="<?= e($p['api_key']) ?>" required>
</div>

<div class="form-group">
<label>USD to PKR Rate</label>
<input class="form-control" type="number" step="0.01" min="0.01" name="usd_to_pkr_rate" value="<?= e($p['usd_to_pkr_rate']) ?>" required>
</div>

<div class="form-group">
<label>Markup %</label>
<input class="form-control" type="number" step="0.01" min="0" name="markup_percent" value="<?= e($p['markup_percent']) ?>" required>
</div>

<button class="btn primary" type="submit" name="update_provider">Save Changes</button>

</form>

</details>

<div class="provider-actions">

<form method="POST">
<input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
<button class="btn" type="submit" name="test_provider">Test Connection</button>
</form>

<form method="POST">
<input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
<button class="btn" type="submit" name="toggle_status">
    <?= $p['status'] ? 'Disable' : 'Enable' ?>
</button>
</form>

<form method="POST" onsubmit="return confirm('Delete this provider?');">
<input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
<button class="btn" type="submit" name="delete_provider" style="color:var(--error-text);">Delete</button>
</form>

<a class="btn" href="import_services.php?provider=<?= (int) $p['id'] ?>">Import Services →</a>

</div>

</div>

<?php endforeach; ?>


<?php if (empty($providers)): ?>
<p style="color:var(--muted);">No providers added yet. Add your first one below.</p>
<?php endif; ?>


<!-- =====================================================
     ADD NEW PROVIDER
===================================================== -->

<div class="card" style="margin-top:10px;">

<div class="card-head">
<h2>Add New Provider</h2>
</div>

<form method="POST">

<div class="form-group">
<label>Provider Name</label>
<input class="form-control" type="text" name="name" placeholder="e.g. ZSMMPanel, JustAnotherPanel, SMMCoder" required>
</div>

<div class="form-group">
<label>API URL</label>
<input class="form-control" type="url" name="api_url" placeholder="https://provider.com/api/v2" required>
</div>

<div class="form-group">
<label>API Key</label>
<input class="form-control" type="password" name="api_key" required>
</div>

<div class="form-group">
<label>USD to PKR Exchange Rate</label>
<input class="form-control" type="number" step="0.01" min="0.01" name="usd_to_pkr_rate" value="278.00" required>
</div>

<div class="form-group">
<label>Markup %</label>
<input class="form-control" type="number" step="0.01" min="0" name="markup_percent" value="50">
</div>

<button class="btn primary" type="submit" name="add_provider">Add Provider</button>

</form>

</div>

</main>

</div>

</body>
</html>
