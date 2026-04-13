<?php
declare(strict_types=1);

require_once __DIR__ . '/../account_management/config/db.php';
require_once __DIR__ . '/../account_management/includes/auth.php';

// must be logged in to checkout
if (!isLoggedIn()) {
    header('Location: ../account_management/login.php');
    exit;
}

const TAX_RATE = 0.0825;

$pageTitle = 'Checkout';
$errors    = [];

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// load cart items from DB
$cartItems = [];
$subtotal  = 0.0;

if (!empty($_SESSION['cart'])) {
    $ids          = array_map(fn($i) => (int) $i['product_id'], array_values($_SESSION['cart']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt         = $pdo->prepare(
        "SELECT p.product_id, p.product_name, p.price, p.sale_price, p.is_on_sale, p.quantity AS stock,
                CASE WHEN p.is_on_sale = 1 AND p.sale_price IS NOT NULL THEN p.sale_price ELSE p.price END AS display_price
         FROM product p WHERE p.product_id IN ({$placeholders})"
    );
    $stmt->execute($ids);
    $productMap = [];
    foreach ($stmt->fetchAll() as $row) {
        $productMap[$row['product_id']] = $row;
    }

    foreach ($_SESSION['cart'] as $item) {
        $pid = (int) $item['product_id'];
        if (!isset($productMap[$pid])) continue;
        $p   = $productMap[$pid];
        $qty = min((int) $item['quantity'], (int) $p['stock']);
        if ($qty <= 0) continue;
        $lineTotal   = (float) $p['display_price'] * $qty;
        $subtotal   += $lineTotal;
        $cartItems[] = array_merge($p, ['qty' => $qty, 'line_total' => $lineTotal]);
    }
}

if (empty($cartItems)) {
    header('Location: cart.php');
    exit;
}

// discount from session
$discountPercent = 0.0;
$discountId      = null;
$discountCode    = '';
if (isset($_SESSION['discount'])) {
    $discountPercent = (float) $_SESSION['discount']['percent'];
    $discountId      = (int)   $_SESSION['discount']['id'];
    $discountCode    = (string) $_SESSION['discount']['code'];
}

$discountAmount = $subtotal * ($discountPercent / 100);
$afterDiscount  = $subtotal - $discountAmount;
$taxAmount      = $afterDiscount * TAX_RATE;
$grandTotal     = $afterDiscount + $taxAmount;

// place order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['place_order'] ?? '') === '1') {
    if (empty($cartItems)) {
        $errors[] = 'Your cart is empty.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // verify stock and decrement
            foreach ($cartItems as $item) {
                $stockCheck = $pdo->prepare('SELECT quantity FROM product WHERE product_id = ? FOR UPDATE');
                $stockCheck->execute([$item['product_id']]);
                $currentStock = (int) $stockCheck->fetchColumn();
                if ($currentStock < $item['qty']) {
                    throw new RuntimeException("Insufficient stock for: " . $item['product_name']);
                }
                $decrement = $pdo->prepare('UPDATE product SET quantity = quantity - ? WHERE product_id = ?');
                $decrement->execute([$item['qty'], $item['product_id']]);
            }

            // insert order
            $userId = (int) currentUser()['user_id'];
            $orderStmt = $pdo->prepare(
                'INSERT INTO orders (user_id, discount_id, total_amount, tax_amount) VALUES (?, ?, ?, ?)'
            );
            $orderStmt->execute([$userId, $discountId, round($grandTotal, 2), round($taxAmount, 2)]);
            $orderId = (int) $pdo->lastInsertId();

            // insert order items
            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)'
            );
            foreach ($cartItems as $item) {
                $itemStmt->execute([$orderId, $item['product_id'], $item['qty'], round((float) $item['display_price'], 2)]);
            }

            $pdo->commit();

            // clear cart and discount
            $_SESSION['cart']    = [];
            unset($_SESSION['discount']);

            header('Location: order_confirm.php?order_id=' . $orderId);
            exit;

        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'An error occurred placing your order. Please try again.';
        }
    }
}

$user      = currentUser();
$cartCount = array_sum(array_column(array_values($_SESSION['cart']), 'quantity'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title><?= e($pageTitle); ?> - Our Store</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet"/>
    <link href="css/styles.css" rel="stylesheet"/>
    <style>
        .summary-table td { padding: 6px 0; }
        .summary-table .total-row td { font-weight:700; font-size:1.1rem; border-top:2px solid #dee2e6; padding-top:10px; }
    </style>
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
            <a class="btn btn-outline-dark" href="cart.php">
                <i class="bi-cart-fill me-1"></i> Cart
                <span class="badge bg-dark text-white ms-1 rounded-pill"><?= $cartCount; ?></span>
            </a>
        </div>
    </div>
</nav>

<section class="py-5">
    <div class="container px-4 px-lg-5">
        <h1 class="fw-bolder mb-4">Checkout</h1>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?= e($err); ?></div>
        <?php endforeach; ?>

        <div class="row g-5">
            <!-- Order Details -->
            <div class="col-lg-8">
                <!-- Shipping Info (display only, can be extended) -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="fw-bolder mb-3"><i class="bi-person-fill me-2"></i>Account</h5>
                        <p class="mb-1"><strong><?= e($user['first_name'] . ' ' . $user['last_name']); ?></strong></p>
                        <p class="text-muted mb-0"><?= e($user['email']); ?></p>
                        <a href="../account_management/account.php" class="small">Update account info</a>
                    </div>
                </div>

                <!-- Items -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bolder mb-3"><i class="bi-bag-fill me-2"></i>Order Items</h5>
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Unit</th>
                                    <th class="text-end">Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($cartItems as $item): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($item['product_name']); ?></td>
                                    <td class="text-center"><?= (int) $item['qty']; ?></td>
                                    <td class="text-end">$<?= number_format((float) $item['display_price'], 2); ?></td>
                                    <td class="text-end fw-semibold">$<?= number_format($item['line_total'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="cart.php" class="btn btn-outline-secondary"><i class="bi-arrow-left me-1"></i> Edit Cart</a>
                </div>
            </div>

            <!-- Summary & Place Order -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bolder mb-4">Order Summary</h5>

                        <?php if ($discountCode !== ''): ?>
                            <div class="alert alert-success py-2 small">
                                <i class="bi-tag-fill me-1"></i> Code <strong><?= e($discountCode); ?></strong> applied (<?= number_format($discountPercent, 2); ?>% off)
                            </div>
                        <?php endif; ?>

                        <table class="table summary-table borderless mb-0">
                            <tr>
                                <td class="text-muted">Subtotal</td>
                                <td class="text-end">$<?= number_format($subtotal, 2); ?></td>
                            </tr>
                            <?php if ($discountAmount > 0): ?>
                            <tr>
                                <td class="text-success">Discount</td>
                                <td class="text-end text-success">-$<?= number_format($discountAmount, 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td class="text-muted">Tax (8.25%)</td>
                                <td class="text-end">$<?= number_format($taxAmount, 2); ?></td>
                            </tr>
                            <tr class="total-row">
                                <td>Total</td>
                                <td class="text-end">$<?= number_format($grandTotal, 2); ?></td>
                            </tr>
                        </table>

                        <form method="POST" class="d-grid mt-4">
                            <input type="hidden" name="place_order" value="1">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi-check-circle-fill me-1"></i> Place Order
                            </button>
                        </form>
                        <p class="text-muted small text-center mt-2 mb-0">
                            <i class="bi-lock-fill me-1"></i> Your order will be confirmed immediately.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="py-5 bg-dark">
    <div class="container"><p class="m-0 text-center text-white">Copyright &copy; Our Store <?= date('Y'); ?></p></div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
