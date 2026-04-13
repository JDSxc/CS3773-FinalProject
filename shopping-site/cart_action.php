<?php
declare(strict_types=1);

require_once __DIR__ . '/../account_management/config/db.php';
require_once __DIR__ . '/../account_management/includes/auth.php';

// initialize cart in session
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action    = $_POST['action'] ?? $_GET['action'] ?? '';
$productId = (int) ($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
$quantity  = max(1, (int) ($_POST['quantity'] ?? 1));

if ($action === 'add' && $productId > 0) {
    // verify product exists and is in stock
    $stmt = $pdo->prepare('SELECT product_id, quantity FROM product WHERE product_id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if ($product && (int) $product['quantity'] > 0) {
        $key = 'p_' . $productId;
        if (isset($_SESSION['cart'][$key])) {
            $newQty = $_SESSION['cart'][$key]['quantity'] + $quantity;
            // cap at available stock
            $_SESSION['cart'][$key]['quantity'] = min($newQty, (int) $product['quantity']);
        } else {
            $_SESSION['cart'][$key] = [
                'product_id' => $productId,
                'quantity'   => min($quantity, (int) $product['quantity']),
            ];
        }
    }

} elseif ($action === 'update' && $productId > 0) {
    $key = 'p_' . $productId;
    if ($quantity <= 0) {
        unset($_SESSION['cart'][$key]);
    } else {
        // check stock limit
        $stmt = $pdo->prepare('SELECT quantity FROM product WHERE product_id = ?');
        $stmt->execute([$productId]);
        $stock = (int) ($stmt->fetchColumn() ?: 0);
        $_SESSION['cart'][$key] = [
            'product_id' => $productId,
            'quantity'   => min($quantity, $stock),
        ];
    }

} elseif ($action === 'remove' && $productId > 0) {
    $key = 'p_' . $productId;
    unset($_SESSION['cart'][$key]);

} elseif ($action === 'clear') {
    $_SESSION['cart'] = [];
}

// redirect back to referring page or cart
$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? 'cart.php';
// whitelist allowed redirects to prevent open redirect
$allowed = ['cart.php', 'shop.php', 'checkout.php'];
if (!in_array($redirect, $allowed, true)) {
    $redirect = 'cart.php';
}

header('Location: ' . $redirect);
exit;
