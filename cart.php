<?php
require_once __DIR__ . '/functions.php';
$flashMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart'])) {
        updateCart($_POST['quantity'] ?? []);
        $flashMessage = 'Keranjang berhasil diperbarui.';
    }
    if (isset($_POST['remove_item'])) {
        $removeId = (int)$_POST['remove_item'];
        unset($_SESSION['cart'][$removeId]);
        $flashMessage = 'Produk dihapus dari keranjang.';
    }
}
$cartItems = getCartItems();
include __DIR__ . '/header.php';
?>
<div class="page-section cart-page">
    <div class="section-card">
        <div class="section-heading">
            <h1>Keranjang Belanja</h1>
            <p>Periksa kembali jumlah dan harga sebelum melanjutkan ke pembayaran.</p>
        </div>
        <?php if (empty($cartItems)): ?>
            <div class="empty-state">
                <p>Keranjang Anda kosong. Jelajahi katalog untuk menambahkan produk.</p>
                <a href="index.php" class="btn-primary">Kembali ke Katalog</a>
            </div>
        <?php else: ?>
            <form method="post" class="cart-table-form">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Harga</th>
                            <th>Jumlah</th>
                            <th>Subtotal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cartItems as $itemId => $item): ?>
                            <tr>
                                <td class="cart-product">
                                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                    <div>
                                        <a href="product.php?id=<?= $itemId ?>"><?= htmlspecialchars($item['name']) ?></a>
                                        <span class="tag small"><?= htmlspecialchars($item['brand']) ?></span>
                                    </div>
                                </td>
                                <td><?= formatIDR($item['price']) ?></td>
                                <td>
                                    <input type="number" name="quantity[<?= $itemId ?>]" min="1" max="<?= $item['stock'] ?>" value="<?= $item['quantity'] ?>">
                                </td>
                                <td><?= formatIDR($item['subtotal']) ?></td>
                                <td>
                                    <button type="submit" name="remove_item" value="<?= $itemId ?>" class="btn-secondary small">Hapus</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="cart-actions">
                    <button type="submit" name="update_cart" class="btn-primary">Perbarui Keranjang</button>
                    <a href="checkout.php" class="btn-secondary">Lanjutkan ke Pembayaran</a>
                </div>
            </form>
            <aside class="checkout-summary">
                <h2>Ringkasan Pesanan</h2>
                <p>Total item: <?= count($cartItems) ?></p>
                <p class="summary-total">Total Harga: <?= formatIDR(getCartTotal()) ?></p>
                <a href="checkout.php" class="btn-primary full-width">Bayar Sekarang</a>
            </aside>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
