<?php
declare(strict_types=1);

require_once __DIR__ . '/../account_management/config/db.php';
require_once __DIR__ . '/../account_management/includes/auth.php';

$pageTitle = 'Your Cart';

const TAX_RATE = 0.0825; // (8.25%)

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// load product data for items in cart
$cartItems = [];
$subtotal  = 0.0;

if (!empty($_SESSION['cart'])) {
    $ids = array_map(fn($i) => (int) $i['product_id'], array_values($_SESSION['cart']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare(
        "SELECT p.product_id, p.product_name, p.price, p.sale_price, p.is_on_sale, p.quantity AS stock,
                COALESCE(pi.image_path,'') AS image_path,
                CASE WHEN p.is_on_sale = 1 AND p.sale_price IS NOT NULL THEN p.sale_price ELSE p.price END AS display_price
         FROM product p
         LEFT JOIN product_images pi ON pi.product_id = p.product_id AND pi.is_primary = 1
         WHERE p.product_id IN ({$placeholders})"
    );
    $stmt->execute($ids);
    $productMap = [];
    foreach ($stmt->fetchAll() as $row) {
        $productMap[$row['product_id']] = $row;
    }

    foreach ($_SESSION['cart'] as $key => $item) {
        $pid = (int) $item['product_id'];
        if (!isset($productMap[$pid])) continue;
        $p   = $productMap[$pid];
        $qty = min((int) $item['quantity'], (int) $p['stock']); // re-cap to current stock

        if ($qty < (int) $item['quantity']) {
            $_SESSION['cart'][$key]['quantity'] = $qty; // fix session
        }
        if ($qty <= 0) {
            unset($_SESSION['cart'][$key]);
            continue;
        }

        $lineTotal   = (float) $p['display_price'] * $qty;
        $subtotal   += $lineTotal;
        $cartItems[] = array_merge($p, ['qty' => $qty, 'line_total' => $lineTotal]);
    }
}

// discount code from session or POST
$discountMsg     = '';
$discountPercent = 0.0;
$discountId      = null;

if (isset($_SESSION['discount']) && is_array($_SESSION['discount'])) {
    $discountPercent = (float) $_SESSION['discount']['percent'];
    $discountId      = (int)   $_SESSION['discount']['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_discount'])) {
    $code = strtoupper(trim($_POST['discount_code'] ?? ''));
    if ($code === '') {
        // clear discount
        unset($_SESSION['discount']);
        $discountPercent = 0.0;
        $discountId      = null;
        $discountMsg     = 'info:Discount removed.';
    } else {
        $ds = $pdo->prepare(
            "SELECT discount_id, discount_percent FROM discount_codes
             WHERE code = ?
               AND (start_date IS NULL OR start_date <= CURDATE())
               AND (expire_date IS NULL OR expire_date >= CURDATE())
             LIMIT 1"
        );
        $ds->execute([$code]);
        $dc = $ds->fetch();

        if ($dc) {
            $_SESSION['discount'] = ['percent' => (float) $dc['discount_percent'], 'id' => (int) $dc['discount_id'], 'code' => $code];
            $discountPercent = (float) $dc['discount_percent'];
            $discountId      = (int)   $dc['discount_id'];
            $discountMsg     = 'success:Discount code applied! You save ' . number_format($discountPercent, 2) . '%.';
        } else {
            unset($_SESSION['discount']);
            $discountPercent = 0.0;
            $discountId      = null;
            $discountMsg     = 'error:Invalid or expired discount code.';
        }
    }
}

// totals
$discountAmount = $subtotal * ($discountPercent / 100);
$afterDiscount  = $subtotal - $discountAmount;
$taxAmount      = $afterDiscount * TAX_RATE;
$grandTotal     = $afterDiscount + $taxAmount;

// cart count
$cartCount = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartCount += (int) ($item['quantity'] ?? 0);
}

// parse discount message
$discountClass = '';
$discountText  = '';
if ($discountMsg !== '') {
    [$discountClass, $discountText] = explode(':', $discountMsg, 2);
}
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
        .cart-img { width:70px;height:70px;object-fit:cover;border-radius:8px;border:1px solid #dee2e6; }
        .qty-input { width:70px;text-align:center; }
        .summary-table td { padding:6px 0; }
        .summary-table .total-row td { font-weight:700;font-size:1.1rem;border-top:2px solid #dee2e6;padding-top:10px; }
    </style>
</head>
<body>
<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container px-4 px-lg-5">
        <a class="navbar-brand fw-bold" href="shop.php">Our Store</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item"><a class="nav-link" href="../account_management/account.php">My Account</a></li>
                    <?php if (($_SESSION['user']['user_role'] ?? '') === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="../account_management/admin/dashboard.php">Admin</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="../account_management/logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="../account_management/login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="../account_management/register.php">Register</a></li>
                <?php endif; ?>
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
        <h1 class="fw-bolder mb-4">Shopping Cart</h1>

        <?php if (empty($cartItems)): ?>
            <div class="text-center py-5">
                <i class="bi-cart-x" style="font-size:4rem;color:#adb5bd;"></i>
                <h4 class="mt-3 text-muted">Your cart is empty.</h4>
                <a href="shop.php" class="btn btn-dark mt-3">Continue Shopping</a>
            </div>
        <?php else: ?>
        <div class="row g-5">
            <!-- Cart Items -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <table class="table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Product</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end pe-4">Line Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($cartItems as $item): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if (!empty($item['image_path'])): ?>
                                                <img src="<?= e($item['image_path']); ?>" class="cart-img" alt="<?= e($item['product_name']); ?>">
                                            <?php else: ?>
                                                <div class="cart-img bg-light d-flex align-items-center justify-content-center">
                                                    <i class="bi-image text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-semibold"><?= e($item['product_name']); ?></div>
                                                <?php if ((int) $item['is_on_sale'] === 1): ?>
                                                    <span class="badge bg-danger">Sale</span>
                                                <?php endif; ?>
                                                <div class="text-muted small"><?= (int) $item['stock']; ?> available</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" action="cart_action.php" class="d-inline">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="product_id" value="<?= (int) $item['product_id']; ?>">
                                            <input type="hidden" name="redirect" value="cart.php">
                                            <input type="number" name="quantity" value="<?= (int) $item['qty']; ?>"
                                                   min="0" max="<?= (int) $item['stock']; ?>"
                                                   class="form-control qty-input d-inline-block"
                                                   onchange="this.form.submit()">
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        <?php if ((int) $item['is_on_sale'] === 1 && $item['sale_price'] !== null): ?>
                                            <span class="text-muted text-decoration-line-through small">$<?= number_format((float) $item['price'], 2); ?></span><br>
                                            <span class="text-danger fw-semibold">$<?= number_format((float) $item['display_price'], 2); ?></span>
                                        <?php else: ?>
                                            $<?= number_format((float) $item['display_price'], 2); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3 fw-semibold">$<?= number_format($item['line_total'], 2); ?></td>
                                    <td>
                                        <form method="POST" action="cart_action.php">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="product_id" value="<?= (int) $item['product_id']; ?>">
                                            <input type="hidden" name="redirect" value="cart.php">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                                <i class="bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <a href="shop.php" class="btn btn-outline-dark"><i class="bi-arrow-left me-1"></i> Continue Shopping</a>
                    <form method="POST" action="cart_action.php">
                        <input type="hidden" name="action" value="clear">
                        <input type="hidden" name="redirect" value="cart.php">
                        <button type="submit" class="btn btn-outline-secondary" onclick="return confirm('Clear your entire cart?');">
                            <i class="bi-trash me-1"></i> Clear Cart
                        </button>
                    </form>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bolder mb-4">Order Summary</h5>

                        <!-- Discount Code -->
                        <form method="POST">
                            <label class="form-label fw-semibold">Discount Code</label>
                            <div class="input-group mb-2">
                                <input type="text" name="discount_code" class="form-control"
                                       placeholder="Enter code"
                                       value="<?= e($_SESSION['discount']['code'] ?? ''); ?>">
                                <button type="submit" name="apply_discount" class="btn btn-outline-dark">Apply</button>
                            </div>
                            <?php if ($discountText !== ''): ?>
                                <div class="alert alert-<?= $discountClass === 'success' ? 'success' : ($discountClass === 'error' ? 'danger' : 'info'); ?> py-2 small">
                                    <?= e($discountText); ?>
                                </div>
                            <?php endif; ?>
                        </form>

                        <hr>

                        <table class="table summary-table borderless mb-0">
                            <tr>
                                <td class="text-muted">Subtotal</td>
                                <td class="text-end">$<?= number_format($subtotal, 2); ?></td>
                            </tr>
                            <?php if ($discountPercent > 0): ?>
                            <tr>
                                <td class="text-success">Discount (<?= number_format($discountPercent, 2); ?>%)</td>
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

                        <div class="d-grid mt-4">
                            <?php if (isLoggedIn()): ?>
                                <a href="checkout.php" class="btn btn-dark btn-lg">
                                    <i class="bi-lock-fill me-1"></i> Proceed to Checkout
                                </a>
                            <?php else: ?>
                                <a href="../account_management/login.php" class="btn btn-dark btn-lg">
                                    <i class="bi-person-fill me-1"></i> Login to Checkout
                                </a>
                                <p class="text-center text-muted small mt-2 mb-0">
                                    Don't have an account? <a href="../account_management/register.php">Register here</a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<footer class="py-5 bg-dark">
    <div class="container"><p class="m-0 text-center text-white">Copyright &copy; Our Store <?= date('Y'); ?></p></div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
