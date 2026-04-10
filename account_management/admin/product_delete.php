<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

requireAdmin();

$productId = (int) ($_GET['id'] ?? 0);

if ($productId > 0) {
    $stmt = $pdo->prepare('DELETE FROM product WHERE product_id = ?');
    $stmt->execute([$productId]);
}

redirect('products.php?success=deleted');
