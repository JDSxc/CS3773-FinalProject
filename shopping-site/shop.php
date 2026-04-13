<?php
declare(strict_types=1);

require_once __DIR__ . '/../account_management/config/db.php';
require_once __DIR__ . '/../account_management/includes/auth.php';

$pageTitle = 'Shop';

// search and sort parameters
$search = trim($_GET['search'] ?? '');
$sort   = $_GET['sort'] ?? 'name_asc';

$sortOptions = [
    'name_asc'        => 'p.product_name ASC',
    'name_desc'       => 'p.product_name DESC',
    'price_asc'       => 'display_price ASC',
    'price_desc'      => 'display_price DESC',
    'availability'    => 'p.quantity DESC, p.product_name ASC',
    'sale_first'      => 'p.is_on_sale DESC, display_price ASC',
];
$orderBy = $sortOptions[$sort] ?? $sortOptions['name_asc'];

$sql = 'SELECT p.product_id,
               p.product_name,
               p.product_description,
               p.price,
               p.quantity,
               p.is_on_sale,
               p.sale_price,
               COALESCE(pi.image_path, \'\') AS image_path,
               CASE
                   WHEN p.is_on_sale = 1 AND p.sale_price IS NOT NULL
                   THEN p.sale_price
                   ELSE p.price
               END AS display_price
        FROM product p
        LEFT JOIN product_images pi
            ON pi.product_id = p.product_id AND pi.is_primary = 1';

$params = [];
if ($search !== '') {
    $sql .= ' WHERE (p.product_name LIKE :s1 OR p.product_description LIKE :s2)';
    $params['s1'] = '%' . $search . '%';
    $params['s2'] = '%' . $search . '%';
}
$sql .= " ORDER BY {$orderBy}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// cart count from session
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int) ($item['quantity'] ?? 0);
    }
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
                <li class="nav-item"><a class="nav-link active" href="shop.php">Shop</a></li>
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
                <i class="bi-cart-fill me-1"></i>
                Cart
                <span class="badge bg-dark text-white ms-1 rounded-pill"><?= $cartCount; ?></span>
            </a>
        </div>
    </div>
</nav>

<!-- Hero -->
<header class="bg-dark py-5">
    <div class="container px-4 px-lg-5 my-5">
        <div class="text-center text-white">
            <h1 class="display-4 fw-bolder">Shop</h1>
            <p class="lead fw-normal text-white-50 mb-0">Browse our full catalog</p>
        </div>
    </div>
</header>

<!-- Filters -->
<section class="py-4 bg-light border-bottom">
    <div class="container px-4 px-lg-5">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Search by Name or Description</label>
                <input type="text" name="search" class="form-control" placeholder="e.g. shirt, jacket…" value="<?= e($search); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Sort By</label>
                <select name="sort" class="form-select">
                    <option value="name_asc"     <?= $sort === 'name_asc'     ? 'selected' : ''; ?>>Name A–Z</option>
                    <option value="name_desc"    <?= $sort === 'name_desc'    ? 'selected' : ''; ?>>Name Z–A</option>
                    <option value="price_asc"    <?= $sort === 'price_asc'    ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_desc"   <?= $sort === 'price_desc'   ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="availability" <?= $sort === 'availability' ? 'selected' : ''; ?>>Availability</option>
                    <option value="sale_first"   <?= $sort === 'sale_first'   ? 'selected' : ''; ?>>Sale Items First</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100">Apply</button>
            </div>
        </form>
    </div>
</section>

<!-- Products -->
<section class="py-5">
    <div class="container px-4 px-lg-5 mt-3">
        <?php if (!$products): ?>
            <div class="text-center py-5">
                <h4 class="text-muted">No products found<?= $search !== '' ? ' for "' . e($search) . '"' : ''; ?>.</h4>
                <?php if ($search !== ''): ?>
                    <a href="shop.php" class="btn btn-outline-dark mt-3">Clear Search</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <div class="row gx-4 gx-lg-5 row-cols-2 row-cols-md-3 row-cols-xl-4">
            <?php foreach ($products as $p): ?>
            <div class="col mb-5">
                <div class="card h-100">
                    <?php if ((int) $p['is_on_sale'] === 1): ?>
                        <div class="badge bg-danger text-white position-absolute" style="top:.5rem;right:.5rem">Sale</div>
                    <?php endif; ?>
                    <?php if ((int) $p['quantity'] === 0): ?>
                        <div class="badge bg-secondary text-white position-absolute" style="top:.5rem;left:.5rem">Out of Stock</div>
                    <?php endif; ?>

                    <?php if (!empty($p['image_path'])): ?>
                        <img class="card-img-top" src="<?= e($p['image_path']); ?>" alt="<?= e($p['product_name']); ?>" style="height:220px;object-fit:cover;">
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height:220px;">
                            <i class="bi bi-image text-muted" style="font-size:3rem;"></i>
                        </div>
                    <?php endif; ?>

                    <div class="card-body p-4">
                        <div class="text-center">
                            <h5 class="fw-bolder"><?= e($p['product_name']); ?></h5>
                            <?php if ($p['product_description']): ?>
                                <p class="text-muted small"><?= e(mb_strimwidth((string) $p['product_description'], 0, 80, '…')); ?></p>
                            <?php endif; ?>
                            <div>
                                <?php if ((int) $p['is_on_sale'] === 1 && $p['sale_price'] !== null): ?>
                                    <span class="text-muted text-decoration-line-through">$<?= number_format((float) $p['price'], 2); ?></span>
                                    <span class="fw-bold text-danger ms-1">$<?= number_format((float) $p['sale_price'], 2); ?></span>
                                <?php else: ?>
                                    <span class="fw-bold">$<?= number_format((float) $p['price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted small mt-1">
                                <?php if ((int) $p['quantity'] > 0): ?>
                                    <?= (int) $p['quantity']; ?> in stock
                                <?php else: ?>
                                    <span class="text-danger">Out of stock</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer p-4 pt-0 border-top-0 bg-transparent">
                        <?php if ((int) $p['quantity'] > 0): ?>
                            <form method="POST" action="cart_action.php">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?= (int) $p['product_id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-dark mt-auto w-100">
                                    <i class="bi-cart-plus me-1"></i> Add to Cart
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-secondary mt-auto w-100" disabled>Out of Stock</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Footer -->
<footer class="py-5 bg-dark">
    <div class="container"><p class="m-0 text-center text-white">Copyright &copy; Our Store <?= date('Y'); ?></p></div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
