<?php
require_once __DIR__ . '/auth.php';
$user = currentUser();
$basePath = '/CS3773-FinalProject/account_management';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? e($pageTitle) : 'Admin'; ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc2/dist/css/adminlte.min.css">
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">

  <nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
            <i class="bi bi-list"></i>
          </a>
        </li>
      </ul>
      <ul class="navbar-nav ms-auto">
        <?php if ($user): ?>
          <li class="nav-item">
            <span class="nav-link">
              <?= e($user['username']); ?> (<?= e($user['user_role']); ?>)
            </span>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?= $basePath; ?>/logout.php">Logout</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </nav>

  <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
      <a href="<?= $basePath; ?>/admin/dashboard.php" class="brand-link">
        <span class="brand-text fw-light">Admin Panel</span>
      </a>
    </div>
    <div class="sidebar-wrapper">
      <nav class="mt-2">
        <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation">
          <li class="nav-item">
            <a href="<?= $basePath; ?>/admin/dashboard.php" class="nav-link">
              <i class="nav-icon bi bi-speedometer"></i>
              <p>Dashboard</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?= $basePath; ?>/admin/products.php" class="nav-link">
              <i class="nav-icon bi bi-box-seam"></i>
              <p>Products</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?= $basePath; ?>/admin/users.php" class="nav-link">
              <i class="nav-icon bi bi-people-fill"></i>
              <p>Users</p>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </aside>

  <main class="app-main">
    <div class="app-content">
      <div class="container-fluid pt-3">