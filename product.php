<?php
require_once __DIR__ . '/functions.php';
$productId = $_GET['id'] ?? null;
$product = getProduct($productId);
if (!$product) {
    header('Location: index.php');
    exit;
}
$flashMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $added = addToCart($productId, $_POST['quantity'] ?? 1);
    $flashMessage = $added ? 'Produk berhasil ditambahkan ke keranjang.' : 'Gagal menambahkan ke keranjang. Stok mungkin sudah habis.';
}
include __DIR__ . '/header.php';
?>
<div class="page-section single-product">
    <div class="product-detail-card">
        <div class="product-image-panel">
            <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
        </div>
        <div class="product-detail-info">
            <span class="tag"><?= htmlspecialchars($product['brand']) ?></span>
            <h1><?= htmlspecialchars($product['name']) ?></h1>
            <p class="price large"><?= formatIDR($product['price']) ?></p>
            <p class="stock">Stok tersedia: <?= $product['stock'] ?></p>
            <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>
            <div class="spec-grid">
                <?php foreach ($product['specs'] as $label => $value): ?>
                    <div>
                        <span class="spec-label"><?= htmlspecialchars($label) ?></span>
                        <span><?= htmlspecialchars($value) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <form class="detail-actions" method="post">
                <label for="quantity">Jumlah</label>
                <div class="quantity-control">
                    <input type="number" id="quantity" name="quantity" min="1" max="<?= $product['stock'] ?>" value="1">
                    <button type="submit" name="add_to_cart" class="btn-primary">Tambah ke Keranjang</button>
                </div>
            </form>
            <a href="index.php" class="btn-secondary mt-12">Kembali ke Katalog</a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
