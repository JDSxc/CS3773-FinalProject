<?php
require_once __DIR__ . '/auth.php';
$user = currentUser();
$basePath = '/CS3773-FinalProject/account_management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) : 'Account Management'; ?></title>
    <style>
        body { font-family: Arial, sans-serif; background:#f7f7fb; margin:0; color:#1f2937; }
        nav { background:#111827; color:#fff; padding:14px 22px; display:flex; gap:16px; align-items:center; flex-wrap:wrap; }
        nav a { color:#fff; text-decoration:none; }
        .container { max-width:1000px; margin:30px auto; background:#fff; padding:24px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,.08); }
        input, select { width:100%; padding:10px; margin-top:6px; margin-bottom:14px; border:1px solid #d1d5db; border-radius:8px; box-sizing:border-box; }
        button, .button { background:#2563eb; color:#fff; border:none; padding:10px 16px; border-radius:8px; cursor:pointer; text-decoration:none; display:inline-block; }
        button.danger, .button.danger { background:#dc2626; }
        .muted { color:#6b7280; }
        .success { background:#ecfdf5; color:#065f46; padding:12px; border-radius:8px; margin-bottom:16px; }
        .error { background:#fef2f2; color:#991b1b; padding:12px; border-radius:8px; margin-bottom:16px; }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th, td { border:1px solid #e5e7eb; padding:10px; text-align:left; }
        th { background:#f3f4f6; }
        .row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        @media (max-width: 700px) { .row { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<nav>
    <?php if (!$user): ?>
        <a href="<?= $basePath; ?>/login.php">Login</a>
        <a href="<?= $basePath; ?>/register.php">Register</a>
    <?php endif; ?>

    <?php if ($user): ?>
        <a href="<?= $basePath; ?>/account.php">My Account</a>

        <?php if (($user['user_role'] ?? '') === 'admin'): ?>
            <a href="<?= $basePath; ?>/admin/users.php">Admin Users</a>
            <a href="<?= $basePath; ?>/admin/products.php">Admin Products</a>
        <?php endif; ?>

        <span>Signed in as <?= e($user['username']); ?> (<?= e($user['user_role']); ?>)</span>
        <a href="<?= $basePath; ?>/logout.php">Logout</a>
    <?php endif; ?>
</nav>
<div class="container">