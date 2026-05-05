<?php
require_once __DIR__ . '/functions.php';
$search = trim($_GET['q'] ?? '');
$products = filterProducts($search, 'Semua');
$flashMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $added = addToCart($_POST['product_id'], $_POST['quantity'] ?? 1);
    $flashMessage = $added ? 'Produk berhasil ditambahkan ke keranjang.' : 'Gagal menambahkan produk. Stok mungkin terbatas.';
}

include __DIR__ . '/header.php';
?>
<div class="home-grid">
    <aside class="sidebar">
        <div class="sidebar-card">
            <h2>Filter Kategori</h2>
            <ul class="category-list">
                <li class="category-item">
                    <a href="index.php">Semua Produk</a>
                </li>
                <?php foreach ($categories as $cat): ?>
                    <?php if ($cat !== 'Semua'): ?>
                        <li class="category-item">
                            <a href="category.php?brand=<?= urlencode($cat) ?>"><?= htmlspecialchars($cat) ?></a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>
    <section class="catalog-section">
        <div class="catalog-header">
            <div>
                <h1>Katalog Smartphone</h1>
                <p>Temukan smartphone terbaik dengan tampilan modern dan navigasi mudah.</p>
            </div>
            <div class="catalog-note">
                <span><?= count($products) ?> produk tersedia</span>
            </div>
        </div>
        <div class="product-grid">
            <?php foreach ($products as $id => $product): ?>
                <article class="product-card">
                    <a href="product.php?id=<?= $id ?>" class="product-thumb">
                        <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    </a>
                    <div class="product-meta">
                        <span class="tag"><?= htmlspecialchars($product['brand']) ?></span>
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
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <p>Tidak ada produk yang cocok. Silakan ubah kata kunci atau kategori.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php include __DIR__ . '/footer.php'; ?>
