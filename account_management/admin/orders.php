<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) { redirect('../login.php'); }
requireAdmin();

$pageTitle = 'Admin - Orders';

$sort    = $_GET['sort'] ?? 'date_desc';
$search  = trim($_GET['search'] ?? ''); // search by customer name or email

$sortOptions = [
    'date_desc'   => 'o.order_date DESC',
    'date_asc'    => 'o.order_date ASC',
    'customer'    => 'u.last_name ASC, u.first_name ASC',
    'amount_desc' => 'o.total_amount DESC',
    'amount_asc'  => 'o.total_amount ASC',
    'order_id'    => 'o.order_id DESC',
];
$orderBy = $sortOptions[$sort] ?? $sortOptions['date_desc'];

$sql = 'SELECT o.order_id, o.order_date, o.total_amount, o.tax_amount,
               u.user_id, u.first_name, u.last_name, u.email, u.username,
               dc.code AS discount_code, dc.discount_percent,
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count
        FROM orders o
        JOIN users u ON u.user_id = o.user_id
        LEFT JOIN discount_codes dc ON dc.discount_id = o.discount_id';

$params = [];
if ($search !== '') {
    $sql .= ' WHERE (u.first_name LIKE :s1 OR u.last_name LIKE :s2 OR u.email LIKE :s3 OR u.username LIKE :s4)';
    $params['s1'] = '%' . $search . '%';
    $params['s2'] = '%' . $search . '%';
    $params['s3'] = '%' . $search . '%';
    $params['s4'] = '%' . $search . '%';
}
$sql .= " ORDER BY {$orderBy}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// stats 
$totalRevenue = array_sum(array_column($orders, 'total_amount'));
$totalOrders  = count($orders);

// expanded order detail (for the detail row)
$expandOrderId = (int) ($_GET['expand'] ?? 0);
$expandedItems = [];
if ($expandOrderId > 0) {
    $iStmt = $pdo->prepare(
        'SELECT oi.quantity, oi.price_at_purchase, p.product_name
         FROM order_items oi
         JOIN product p ON p.product_id = oi.product_id
         WHERE oi.order_id = ?'
    );
    $iStmt->execute([$expandOrderId]);
    $expandedItems = $iStmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>
<h1>Order Management</h1>
<p class="muted">View, search, and sort all placed orders. Click an order to see its items.</p>

<!-- Stats -->
<div class="row" style="margin-bottom:24px;">
    <div style="border:1px solid #e5e7eb;padding:16px;border-radius:12px;text-align:center;">
        <div style="font-size:2rem;font-weight:700;"><?= $totalOrders; ?></div>
        <div class="muted">Total Orders</div>
    </div>
    <div style="border:1px solid #e5e7eb;padding:16px;border-radius:12px;text-align:center;">
        <div style="font-size:2rem;font-weight:700;">$<?= number_format($totalRevenue, 2); ?></div>
        <div class="muted">Total Revenue</div>
    </div>
    <div style="border:1px solid #e5e7eb;padding:16px;border-radius:12px;text-align:center;">
        <div style="font-size:2rem;font-weight:700;">
            $<?= $totalOrders > 0 ? number_format($totalRevenue / $totalOrders, 2) : '0.00'; ?>
        </div>
        <div class="muted">Average Order Value</div>
    </div>
</div>

<!-- Filters -->
<form method="GET" style="display:flex;gap:12px;align-items:flex-end;margin-bottom:20px;flex-wrap:wrap;">
    <div>
        <label>Search Customer</label>
        <input type="text" name="search" placeholder="Name, email, username…" value="<?= e($search); ?>">
    </div>
    <div>
        <label>Sort By</label>
        <select name="sort">
            <option value="date_desc"   <?= $sort === 'date_desc'   ? 'selected' : ''; ?>>Date: Newest First</option>
            <option value="date_asc"    <?= $sort === 'date_asc'    ? 'selected' : ''; ?>>Date: Oldest First</option>
            <option value="customer"    <?= $sort === 'customer'    ? 'selected' : ''; ?>>Customer (A–Z)</option>
            <option value="amount_desc" <?= $sort === 'amount_desc' ? 'selected' : ''; ?>>Amount: High to Low</option>
            <option value="amount_asc"  <?= $sort === 'amount_asc'  ? 'selected' : ''; ?>>Amount: Low to High</option>
            <option value="order_id"    <?= $sort === 'order_id'    ? 'selected' : ''; ?>>Order ID (newest)</option>
        </select>
    </div>
    <div>
        <?php if ($expandOrderId): ?>
            <input type="hidden" name="expand" value="<?= $expandOrderId; ?>">
        <?php endif; ?>
        <button type="submit">Apply</button>
    </div>
    <?php if ($search !== ''): ?>
    <div>
        <a class="button" href="orders.php?sort=<?= e($sort); ?>">Clear Search</a>
    </div>
    <?php endif; ?>
</form>

<!-- Orders table -->
<?php if (!$orders): ?>
    <p class="muted">No orders found<?= $search !== '' ? ' for "' . e($search) . '"' : ''; ?>.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Order #</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Email</th>
            <th>Items</th>
            <th>Discount</th>
            <th>Tax</th>
            <th>Total</th>
            <th>Details</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $o):
        $isExpanded = $expandOrderId === (int) $o['order_id'];
    ?>
        <tr style="<?= $isExpanded ? 'background:#f0f9ff;' : ''; ?>">
            <td><strong>#<?= (int) $o['order_id']; ?></strong></td>
            <td><?= date('M j, Y g:i A', strtotime((string) $o['order_date'])); ?></td>
            <td><?= e($o['first_name'] . ' ' . $o['last_name']); ?></td>
            <td class="muted"><?= e($o['email']); ?></td>
            <td><?= (int) $o['item_count']; ?> item<?= $o['item_count'] != 1 ? 's' : ''; ?></td>
            <td>
                <?php if ($o['discount_code']): ?>
                    <span style="font-size:0.85rem;background:#dcfce7;color:#166534;padding:2px 8px;border-radius:10px;">
                        <?= e($o['discount_code']); ?> (<?= number_format((float) $o['discount_percent'], 1); ?>%)
                    </span>
                <?php else: ?>
                    <span class="muted">—</span>
                <?php endif; ?>
            </td>
            <td>$<?= number_format((float) $o['tax_amount'], 2); ?></td>
            <td><strong>$<?= number_format((float) $o['total_amount'], 2); ?></strong></td>
            <td>
                <?php
                $href = 'orders.php?sort=' . urlencode($sort) . '&search=' . urlencode($search);
                $href .= $isExpanded ? '' : '&expand=' . (int) $o['order_id'];
                ?>
                <a class="button" href="<?= e($href); ?>">
                    <?= $isExpanded ? 'Hide' : 'View'; ?>
                </a>
            </td>
        </tr>

        <?php if ($isExpanded && $expandedItems): ?>
        <tr style="background:#f8fafc;">
            <td colspan="9" style="padding:16px 20px;">
                <strong>Order #<?= $expandOrderId; ?> — Line Items</strong>
                <table style="margin-top:10px;width:100%;background:white;border:1px solid #e5e7eb;border-radius:8px;">
                    <thead>
                        <tr>
                            <th style="padding:8px 14px;text-align:left;background:#f9fafb;">Product</th>
                            <th style="padding:8px 14px;text-align:center;background:#f9fafb;">Qty</th>
                            <th style="padding:8px 14px;text-align:right;background:#f9fafb;">Price Each</th>
                            <th style="padding:8px 14px;text-align:right;background:#f9fafb;">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($expandedItems as $li): ?>
                        <tr>
                            <td style="padding:8px 14px;"><?= e($li['product_name']); ?></td>
                            <td style="padding:8px 14px;text-align:center;"><?= (int) $li['quantity']; ?></td>
                            <td style="padding:8px 14px;text-align:right;">$<?= number_format((float) $li['price_at_purchase'], 2); ?></td>
                            <td style="padding:8px 14px;text-align:right;font-weight:600;">
                                $<?= number_format((float) $li['price_at_purchase'] * (int) $li['quantity'], 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </td>
        </tr>
        <?php endif; ?>

    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
