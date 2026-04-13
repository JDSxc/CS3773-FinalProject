<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

requireAdmin();
$pageTitle = 'Admin Dashboard';

// counts for dashboard stats
$userCount      = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$productCount   = (int) $pdo->query('SELECT COUNT(*) FROM product')->fetchColumn();
$onSaleCount    = (int) $pdo->query('SELECT COUNT(*) FROM product WHERE is_on_sale = 1')->fetchColumn();
$outOfStock     = (int) $pdo->query('SELECT COUNT(*) FROM product WHERE quantity <= 0')->fetchColumn();
$orderCount     = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$discountCount  = (int) $pdo->query('SELECT COUNT(*) FROM discount_codes')->fetchColumn();
$activeDiscounts = (int) $pdo->query("SELECT COUNT(*) FROM discount_codes WHERE (start_date IS NULL OR start_date <= CURDATE()) AND (expire_date IS NULL OR expire_date >= CURDATE())")->fetchColumn();
$totalRevenue   = (float) $pdo->query('SELECT COALESCE(SUM(total_amount), 0) FROM orders')->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<h1>Admin Dashboard</h1>
<p class="muted">Manage your store from one place.</p>

<div class="row" style="margin-bottom:20px;">
    <!-- Users -->
    <div style="border:1px solid #e5e7eb;padding:20px;border-radius:12px;">
        <h2 style="margin-top:0;">Users</h2>
        <p><strong><?= $userCount; ?></strong> total users</p>
        <a class="button" href="<?= $basePath; ?>/admin/users.php">Manage Users</a>
    </div>

    <!-- Products -->
    <div style="border:1px solid #e5e7eb;padding:20px;border-radius:12px;">
        <h2 style="margin-top:0;">Products</h2>
        <p><strong><?= $productCount; ?></strong> total &nbsp;|&nbsp; <strong><?= $onSaleCount; ?></strong> on sale &nbsp;|&nbsp; <strong><?= $outOfStock; ?></strong> out of stock</p>
        <a class="button" href="<?= $basePath; ?>/admin/products.php">Manage Products</a>
        <a class="button" href="<?= $basePath; ?>/admin/product_create.php" style="margin-left:8px;">+ Add Product</a>
    </div>
</div>

<div class="row">
    <!-- Orders -->
    <div style="border:1px solid #e5e7eb;padding:20px;border-radius:12px;">
        <h2 style="margin-top:0;">Orders</h2>
        <p><strong><?= $orderCount; ?></strong> total orders</p>
        <p><strong>$<?= number_format($totalRevenue, 2); ?></strong> total revenue</p>
        <a class="button" href="<?= $basePath; ?>/admin/orders.php">View All Orders</a>
    </div>

    <!-- Discounts -->
    <div style="border:1px solid #e5e7eb;padding:20px;border-radius:12px;">
        <h2 style="margin-top:0;">Discount Codes</h2>
        <p><strong><?= $discountCount; ?></strong> codes &nbsp;|&nbsp; <strong><?= $activeDiscounts; ?></strong> currently active</p>
        <a class="button" href="<?= $basePath; ?>/admin/discounts.php">Manage Discounts</a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
