<?php
// SplitMate App Header Layout

require_once __DIR__ . '/auth.php';

$title = $pageTitle ?? 'SplitMate';
$currentUser = current_user();
$currentRole = current_role();
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | SplitMate</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- Custom SaaS Theme Styles -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="app-wrapper">
    <!-- Sidebar Navigation -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="app-main">
        <!-- Topbar Header -->
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-outline-secondary btn-sm mobile-sidebar-toggle" id="sidebarToggleBtn" aria-label="Toggle Navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h5 class="mb-0 fw-bold text-dark d-none d-md-block"><?= e($pageTitle ?? 'Dashboard') ?></h5>
            </div>

            <!-- Topbar Right Controls -->
            <div class="d-flex align-items-center gap-3">
                <?php if ($currentRole === 'user'): ?>
                    <a href="<?= base_url('user/add_expense.php') ?>" class="btn btn-sm btn-primary-custom d-none d-sm-inline-flex align-items-center gap-2">
                        <i class="fa-solid fa-plus"></i> Add Expense
                    </a>
                <?php endif; ?>

                <!-- User Dropdown Menu -->
                <div class="dropdown">
                    <button class="btn btn-light btn-sm d-flex align-items-center gap-2 rounded-pill px-3 py-1 border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar-sm" style="width: 28px; height: 28px; font-size: 0.78rem;">
                            <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <span class="d-none d-sm-inline fw-semibold text-dark" style="font-size: 0.85rem;">
                            <?= e($currentUser['name'] ?? 'Account') ?>
                        </span>
                        <i class="fa-solid fa-chevron-down text-muted" style="font-size: 0.7rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" style="border-radius: 10px;">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-bold text-dark" style="font-size: 0.88rem;"><?= e($currentUser['name'] ?? '') ?></div>
                            <small class="text-muted"><?= e($currentUser['email'] ?? '') ?></small>
                        </li>
                        <li>
                            <a class="dropdown-menu-item dropdown-item py-2" href="<?= base_url($currentRole === 'admin' ? 'admin/profile.php' : 'user/profile.php') ?>">
                                <i class="fa-solid fa-user-circle me-2 text-muted"></i> My Profile
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-menu-item dropdown-item py-2 text-danger" href="<?= base_url('auth/logout.php') ?>">
                                <i class="fa-solid fa-right-from-bracket me-2 text-danger"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Flash Notifications Container -->
        <div class="container-fluid px-4 pt-3">
            <?php if (!empty($flashes)): ?>
                <?php foreach ($flashes as $flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm d-flex align-items-center gap-2" role="alert">
                        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-circle-exclamation' ?>"></i>
                        <div><?= e($flash['message']) ?></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Main Body Content -->
        <main class="app-content">

