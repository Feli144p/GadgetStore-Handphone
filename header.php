<?php
require_once __DIR__ . '/functions.php';
$user = getUser();
$cartCount = countCartItems();
$categories = getCategories();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GadgetStore</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a4ad4a5534.js" crossorigin="anonymous"></script>
</head>
<body>
<div class="page-shell">
    <header class="site-header">
        <div class="brand-group">
            <a href="index.php" class="logo">GadgetStore</a>
            <form class="search-box" action="index.php" method="get">
                <input type="text" name="q" placeholder="Cari smartphone..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <nav class="top-nav">
            <a href="index.php">Beranda</a>
            <a href="cart.php">Keranjang <span class="badge"><?= $cartCount ?></span></a>
            <?php if ($user): ?>
                <?php if (isAdmin()): ?>
                    <a href="admin.php">Admin</a>
                <?php endif; ?>
                <a href="history.php">Riwayat</a>
                <a href="logout.php">Keluar</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Daftar</a>
            <?php endif; ?>
        </nav>
    </header>
    <?php if (!empty($flashMessage)): ?>
        <div class="flash-message"><?= $flashMessage ?></div>
    <?php endif; ?>
    <main class="page-content">
