<?php
require_once __DIR__ . '/functions.php';
$brand = isset($_GET['brand']) ? trim($_GET['brand']) : null;
$search = trim($_GET['q'] ?? '');

if (!$brand) {
    header('Location: index.php');
    exit;
}

$allProducts = getProducts();
$products = [];

foreach ($allProducts as $id => $product) {
    if ($product['brand'] === $brand) {
        if ($search === '' || stripos($product['name'], $search) !== false) {
            $products[$id] = $product;
        }
    }
}

$flashMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $added = addToCart($_POST['product_id'], $_POST['quantity'] ?? 1);
    $flashMessage = $added ? 'Produk berhasil ditambahkan ke keranjang.' : 'Gagal menambahkan produk. Stok mungkin terbatas.';
}

include __DIR__ . '/header.php';
?>
<div class="page-section category-page">
    <div class="breadcrumb-nav">
        <a href="index.php">Katalog</a>
        <span>/</span>
        <span><?= htmlspecialchars($brand) ?></span>
    </div>

    <div class="category-header">
        <div>
            <h1><?= htmlspecialchars($brand) ?></h1>
            <p>Jelajahi semua smartphone dari <?= htmlspecialchars($brand) ?></p>
        </div>
        <div class="category-info">
            <span class="badge"><?= count($products) ?> produk</span>
        </div>
    </div>

    <?php if (!empty($flashMessage)): ?>
        <div class="flash-message"><?= htmlspecialchars($flashMessage) ?></div>
    <?php endif; ?>

    <?php if (empty($products)): ?>
        <div class="empty-state">
            <p>Tidak ada produk dari <?= htmlspecialchars($brand) ?> yang sesuai dengan pencarian Anda.</p>
            <a href="index.php" class="btn-primary">Kembali ke Katalog</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $id => $product): ?>
                <article class="product-card">
                    <a href="product.php?id=<?= $id ?>" class="product-thumb">
                        <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    </a>
                    <div class="product-meta">
                        <span class="tag"><?= htmlspecialchars($product['category']) ?></span>
                        <h3><a href="product.php?id=<?= $id ?>"><?= htmlspecialchars($product['name']) ?></a></h3>
                        <p class="price"><?= formatIDR($product['price']) ?></p>
                        <p class="stock">Stok tersedia: <?= $product['stock'] ?></p>
                    </div>
                    <form class="product-actions" method="post">
                        <input type="hidden" name="product_id" value="<?= $id ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" name="add_to_cart" class="btn-primary">Tambah ke Keranjang</button>
                        <a href="product.php?id=<?= $id ?>" class="btn-secondary">Lihat Detail</a>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/footer.php'; ?>
