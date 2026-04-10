<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

requireAdmin();
$pageTitle = 'Admin - Manage Products';

$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'listed_desc';
$tableError = '';
$products = [];

$sortOptions = [
    'listed_desc' => 'p.listed DESC, p.product_id DESC',
    'listed_asc' => 'p.listed ASC, p.product_id ASC',
    'name_asc' => 'p.product_name ASC',
    'name_desc' => 'p.product_name DESC',
    'price_asc' => 'display_price ASC, p.product_name ASC',
    'price_desc' => 'display_price DESC, p.product_name ASC',
    'quantity_asc' => 'p.quantity ASC, p.product_name ASC',
    'quantity_desc' => 'p.quantity DESC, p.product_name ASC',
    'sale_desc' => 'p.is_on_sale DESC, p.product_name ASC',
];

$orderBy = $sortOptions[$sort] ?? $sortOptions['listed_desc'];

try {
    $sql = 'SELECT p.product_id,
                   p.product_name,
                   p.product_description,
                   p.price,
                   p.quantity,
                   p.is_on_sale,
                   p.sale_price,
                   p.listed,
                   COALESCE(pi.image_path, "") AS image_path,
                   CASE 
                       WHEN p.is_on_sale = 1 AND p.sale_price IS NOT NULL 
                       THEN p.sale_price 
                       ELSE p.price 
                   END AS display_price
            FROM product p
            LEFT JOIN product_images pi 
                ON pi.product_id = p.product_id 
                AND pi.is_primary = 1';

    $params = [];

    if ($search !== '') {
        $sql .= ' WHERE p.product_name LIKE :search_name OR p.product_description LIKE :search_description';
        $params['search_name'] = '%' . $search . '%';
        $params['search_description'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY {$orderBy}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (Throwable $e) {
    $tableError = 'Actual error: ' . $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
?>
<h1>Admin Product Management</h1>
<p class="muted">Create, review, update, and delete products. This page also shows sale pricing and the primary product image.</p>

<?php if (isset($_GET['success'])): ?>
    <div class="success">
        <?php
            $messages = [
                'created' => 'Product created successfully.',
                'updated' => 'Product updated successfully.',
                'deleted' => 'Product deleted successfully.',
            ];
            echo e($messages[$_GET['success']] ?? 'Action completed successfully.');
        ?>
    </div>
<?php endif; ?>

<?php if ($tableError): ?>
    <div class="error"><?= e($tableError); ?></div>
<?php else: ?>
    <div class="row" style="align-items:end;">
        <form method="GET" style="margin:0;">
            <label>Search Product Name or Description</label>
            <input type="text" name="search" value="<?= e($search); ?>" placeholder="shirts, jacket, summer...">
            <label>Sort</label>
            <select name="sort">
                <option value="listed_desc" <?= $sort === 'listed_desc' ? 'selected' : ''; ?>>Newest first</option>
                <option value="listed_asc" <?= $sort === 'listed_asc' ? 'selected' : ''; ?>>Oldest first</option>
                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : ''; ?>>Price low-high</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : ''; ?>>Price high-low</option>
                <option value="quantity_asc" <?= $sort === 'quantity_asc' ? 'selected' : ''; ?>>Quantity low-high</option>
                <option value="quantity_desc" <?= $sort === 'quantity_desc' ? 'selected' : ''; ?>>Quantity high-low</option>
                <option value="sale_desc" <?= $sort === 'sale_desc' ? 'selected' : ''; ?>>On-sale first</option>
            </select>
            <button type="submit">Apply</button>
        </form>
        <div style="display:flex;align-items:end;justify-content:flex-end;">
            <a class="button" href="<?= $basePath; ?>/admin/product_create.php">Add New Product</a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Name</th>
                <th>Description</th>
                <th>Regular Price</th>
                <th>Sale</th>
                <th>Current Price</th>
                <th>Quantity</th>
                <th>Listed</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$products): ?>
                <tr>
                    <td colspan="10">No products found.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= (int) $product['product_id']; ?></td>
                    <td>
                        <?php if (!empty($product['image_path'])): ?>
                            <img src="<?= e($product['image_path']); ?>" alt="<?= e($product['product_name']); ?>" style="width:70px;height:70px;object-fit:cover;border-radius:10px;border:1px solid #e5e7eb;">
                        <?php else: ?>
                            <span class="muted">No image</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($product['product_name']); ?></td>
                    <td><?= e(mb_strimwidth((string) ($product['product_description'] ?? ''), 0, 90, '...')); ?></td>
                    <td>$<?= number_format((float) $product['price'], 2); ?></td>
                    <td>
                        <?= (int) $product['is_on_sale'] === 1 ? 'Yes' : 'No'; ?>
                        <?= $product['sale_price'] !== null ? ' ($' . number_format((float) $product['sale_price'], 2) . ')' : ''; ?>
                    </td>
                    <td>$<?= number_format((float) $product['display_price'], 2); ?></td>
                    <td><?= (int) $product['quantity']; ?></td>
                    <td><?= e((string) $product['listed']); ?></td>
                    <td>
                        <a class="button" href="<?= $basePath; ?>/admin/product_edit.php?id=<?= (int) $product['product_id']; ?>">Edit</a>
                        <a class="button danger" href="<?= $basePath; ?>/admin/product_delete.php?id=<?= (int) $product['product_id']; ?>" onclick="return confirm('Delete this product?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>