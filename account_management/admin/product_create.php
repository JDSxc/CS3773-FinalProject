<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

requireAdmin();
$pageTitle = 'Admin - Add Product';
$errors = [];

$form = [
    'product_name' => '',
    'product_description' => '',
    'image_path' => '',
    'price' => '',
    'quantity' => '0',
    'is_on_sale' => '0',
    'sale_price' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['product_name'] = trim($_POST['product_name'] ?? '');
    $form['product_description'] = trim($_POST['product_description'] ?? '');
    $form['image_path'] = trim($_POST['image_path'] ?? '');
    $form['price'] = trim($_POST['price'] ?? '');
    $form['quantity'] = trim($_POST['quantity'] ?? '0');
    $form['is_on_sale'] = isset($_POST['is_on_sale']) ? '1' : '0';
    $form['sale_price'] = trim($_POST['sale_price'] ?? '');

    if ($form['product_name'] === '') {
        $errors[] = 'Product name is required.';
    }
    if ($form['price'] === '' || !is_numeric($form['price']) || (float) $form['price'] < 0) {
        $errors[] = 'Enter a valid regular price.';
    }
    if ($form['quantity'] === '' || filter_var($form['quantity'], FILTER_VALIDATE_INT) === false || (int) $form['quantity'] < 0) {
        $errors[] = 'Quantity must be a whole number of 0 or more.';
    }
    if ($form['image_path'] !== '' && !filter_var($form['image_path'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Primary image URL must be valid or left blank.';
    }
    if ($form['is_on_sale'] === '1') {
        if ($form['sale_price'] === '' || !is_numeric($form['sale_price']) || (float) $form['sale_price'] < 0) {
            $errors[] = 'Enter a valid sale price when the product is on sale.';
        } elseif ((float) $form['sale_price'] > (float) $form['price']) {
            $errors[] = 'Sale price cannot be higher than the regular price.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO product (product_name, product_description, price, quantity, is_on_sale, sale_price) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $form['product_name'],
                $form['product_description'] !== '' ? $form['product_description'] : null,
                (float) $form['price'],
                (int) $form['quantity'],
                (int) $form['is_on_sale'],
                $form['is_on_sale'] === '1' ? (float) $form['sale_price'] : null,
            ]);

            $productId = (int) $pdo->lastInsertId();

            if ($form['image_path'] !== '') {
                $imageStmt = $pdo->prepare('INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, 1)');
                $imageStmt->execute([$productId, $form['image_path']]);
            }

            $pdo->commit();
            redirect('products.php?success=created');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Unable to create the product right now. Please check your database schema and try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<h1>Add Product</h1>
<p class="muted">Create a new store item and optionally assign a primary image and sale price.</p>

<?php foreach ($errors as $error): ?>
    <div class="error"><?= e($error); ?></div>
<?php endforeach; ?>

<form method="POST">
    <label>Product Name</label>
    <input type="text" name="product_name" value="<?= e($form['product_name']); ?>" required>

    <label>Description</label>
    <textarea name="product_description" rows="4" style="width:100%;padding:10px;margin-top:6px;margin-bottom:14px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;"><?= e($form['product_description']); ?></textarea>

    <div class="row">
        <div>
            <label>Primary Image URL</label>
            <input type="url" name="image_path" value="<?= e($form['image_path']); ?>" placeholder="https://example.com/image.jpg">
        </div>
        <div>
            <label>Regular Price</label>
            <input type="number" step="0.01" min="0" name="price" value="<?= e($form['price']); ?>" required>
        </div>
    </div>

    <div class="row">
        <div>
            <label>Quantity</label>
            <input type="number" min="0" step="1" name="quantity" value="<?= e($form['quantity']); ?>" required>
        </div>
        <div>
            <label style="display:flex;align-items:center;gap:8px;margin-top:28px;">
                <input type="checkbox" name="is_on_sale" value="1" <?= $form['is_on_sale'] === '1' ? 'checked' : ''; ?> style="width:auto;margin:0;">
                Mark as on sale
            </label>
        </div>
    </div>

    <label>Sale Price</label>
    <input type="number" step="0.01" min="0" name="sale_price" value="<?= e($form['sale_price']); ?>" placeholder="Required only if item is on sale">

    <button type="submit">Create Product</button>
    <a class="button" href="<?= $basePath; ?>/admin/products.php">Back to Products</a>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
