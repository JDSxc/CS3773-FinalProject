<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

requireAdmin();
$pageTitle = 'Admin Dashboard';

$userCount = 0;
$productCount = 0;
$onSaleCount = 0;
$outOfStockCount = 0;

try {
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
} catch (Throwable $e) {
    $userCount = 0;
}

try {
    $productCount = (int) $pdo->query('SELECT COUNT(*) FROM product')->fetchColumn();
    $onSaleCount = (int) $pdo->query('SELECT COUNT(*) FROM product WHERE is_on_sale = 1')->fetchColumn();
    $outOfStockCount = (int) $pdo->query('SELECT COUNT(*) FROM product WHERE quantity <= 0')->fetchColumn();
} catch (Throwable $e) {
    $productCount = 0;
    $onSaleCount = 0;
    $outOfStockCount = 0;
}

require_once __DIR__ . '/../includes/header.php';
?>
<h1>Admin Dashboard</h1>
<p class="muted">Manage store users and product inventory from one place.</p>

<div class="row">
    <div style="border:1px solid #e5e7eb;padding:18px;border-radius:12px;">
        <h2 style="margin-top:0;">Users</h2>
        <p><strong>Total Users:</strong> <?= $userCount; ?></p>
        <a class="button" href="<?= $basePath; ?>/admin/users.php">Manage Users</a>
    </div>
    <div style="border:1px solid #e5e7eb;padding:18px;border-radius:12px;">
        <h2 style="margin-top:0;">Products</h2>
        <p><strong>Total Products:</strong> <?= $productCount; ?></p>
        <p><strong>On Sale:</strong> <?= $onSaleCount; ?></p>
        <p><strong>Out of Stock:</strong> <?= $outOfStockCount; ?></p>
        <a class="button" href="<?= $basePath; ?>/admin/products.php">Manage Products</a>
    </div>
</div>

<div style="margin-top:24px;border:1px solid #e5e7eb;padding:18px;border-radius:12px;">
    <h2 style="margin-top:0;">Setup Reminder</h2>
    <p class="muted">If the products page shows a table error, import your full <code>tables.sql</code> schema in phpMyAdmin first.</p>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
