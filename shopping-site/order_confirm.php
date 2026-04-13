<?php
declare(strict_types=1);

require_once __DIR__ . '/../account_management/config/db.php';
require_once __DIR__ . '/../account_management/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ../account_management/login.php');
    exit;
}

$orderId = (int) ($_GET['order_id'] ?? 0);
$userId  = (int) currentUser()['user_id'];

// load order (make sure it belongs to current user)
$orderStmt = $pdo->prepare(
    'SELECT o.order_id, o.order_date, o.total_amount, o.tax_amount, o.discount_id,
            dc.code AS discount_code, dc.discount_percent
     FROM orders o
     LEFT JOIN discount_codes dc ON dc.discount_id = o.discount_id
     WHERE o.order_id = ? AND o.user_id = ?'
);
$orderStmt->execute([$orderId, $userId]);
$order = $orderStmt->fetch();

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

// load order items
$itemsStmt = $pdo->prepare(
    'SELECT oi.quantity, oi.price_at_purchase, p.product_name,
            COALESCE(pi.image_path, \'\') AS image_path
     FROM order_items oi
     JOIN product p ON p.product_id = oi.product_id
     LEFT JOIN product_images pi ON pi.product_id = oi.product_id AND pi.is_primary = 1
     WHERE oi.order_id = ?'
);
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

$subtotal = 0.0;
foreach ($items as $item) {
    $subtotal += (float) $item['price_at_purchase'] * (int) $item['quantity'];
}

$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Order Confirmed - Our Store</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet"/>
    <link href="css/styles.css" rel="stylesheet"/>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container px-4 px-lg-5">
        <a class="navbar-brand fw-bold" href="shop.php">Our Store</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                <li class="nav-item"><a class="nav-link" href="../account_management/account.php">My Account</a></li>
                <li class="nav-item"><a class="nav-link" href="../account_management/logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<section class="py-5">
    <div class="container px-4 px-lg-5" style="max-width:760px;">
        <div class="text-center mb-5">
            <i class="bi-check-circle-fill text-success" style="font-size:4rem;"></i>
            <h1 class="fw-bolder mt-3">Order Placed!</h1>
            <p class="lead text-muted">
                Thank you, <?= e($user['first_name']); ?>! Your order #<?= (int) $order['order_id']; ?> has been confirmed.
            </p>
            <p class="text-muted small">
                Placed on <?= date('F j, Y \a\t g:i A', strtotime((string) $order['order_date'])); ?>
            </p>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="fw-bolder mb-3">Items Ordered</h5>
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <?php if (!empty($item['image_path'])): ?>
                                        <img src="<?= e($item['image_path']); ?>" style="width:50px;height:50px;object-fit:cover;border-radius:6px;">
                                    <?php endif; ?>
                                    <span class="fw-semibold"><?= e($item['product_name']); ?></span>
                                </div>
                            </td>
                            <td class="text-center"><?= (int) $item['quantity']; ?></td>
                            <td class="text-end">$<?= number_format((float) $item['price_at_purchase'], 2); ?></td>
                            <td class="text-end fw-semibold">
                                $<?= number_format((float) $item['price_at_purchase'] * (int) $item['quantity'], 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="fw-bolder mb-3">Order Summary</h5>
                <table class="table mb-0">
                    <tr>
                        <td class="text-muted border-0">Subtotal</td>
                        <td class="text-end border-0">$<?= number_format($subtotal, 2); ?></td>
                    </tr>
                    <?php if (!empty($order['discount_code'])): ?>
                    <tr>
                        <td class="text-success border-0">Discount (<?= e($order['discount_code']); ?> — <?= number_format((float) $order['discount_percent'], 2); ?>%)</td>
                        <td class="text-end text-success border-0">
                            -$<?= number_format($subtotal - ((float) $order['total_amount'] - (float) $order['tax_amount']), 2); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-muted border-0">Tax (8.25%)</td>
                        <td class="text-end border-0">$<?= number_format((float) $order['tax_amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold border-top">Total Charged</td>
                        <td class="text-end fw-bold fs-5 border-top">$<?= number_format((float) $order['total_amount'], 2); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="text-center mt-5">
            <a href="shop.php" class="btn btn-dark btn-lg me-2">
                <i class="bi-bag-fill me-1"></i> Continue Shopping
            </a>
            <a href="../account_management/account.php" class="btn btn-outline-dark btn-lg">
                <i class="bi-person-fill me-1"></i> My Account
            </a>
        </div>
    </div>
</section>

<footer class="py-5 bg-dark">
    <div class="container"><p class="m-0 text-center text-white">Copyright &copy; Our Store <?= date('Y'); ?></p></div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
