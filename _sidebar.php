<?php
$currentAdminPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
$adminNavItems = [
    ['dashboard.php', 'Dashboard'],
    ['index.php', 'Control Center'],
    ['users.php', 'Users'],
    ['orders.php', 'Orders'],
    ['services.php', 'Services'],
    ['deposits.php', 'Deposits & Funds'],
    ['tickets.php', 'Support Tickets'],
    ['providers.php', 'API Providers'],
    ['api_settings.php', 'API Settings'],
    ['import_services.php', 'Import / Sync Services'],
    ['sync_orders.php', 'Sync Orders'],
    ['blog.php', 'Blog / Content'],
];
?>
<aside class="admin-sidebar">
  <a class="brand" href="dashboard.php" aria-label="NWD PANEL Admin Home">NWD <span>PANEL</span></a>
  <div class="admin-nav-label">ADMIN MENU</div>
  <nav aria-label="Admin navigation">
    <?php foreach ($adminNavItems as [$adminHref, $adminLabel]): ?>
      <a href="<?= e($adminHref) ?>" class="<?= $currentAdminPage === $adminHref ? 'active' : '' ?>" <?= $currentAdminPage === $adminHref ? 'aria-current="page"' : '' ?>><?= e($adminLabel) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="admin-sidebar-footer">
    <a href="../dashboard.php">View User Site</a>
    <a class="logout" href="logout.php">Log out</a>
  </div>
</aside>
