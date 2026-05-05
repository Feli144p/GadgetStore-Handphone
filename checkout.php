<?php
require_once __DIR__ . '/functions.php';
requireLogin();
$flashMessage = '';
$transaction = null;
$errors = [];
$cartItems = getCartItems();
$checkoutData = [
    'recipient_name' => '',
    'phone' => '',
    'address' => '',
    'city' => '',
    'postal_code' => '',
    'payment_method' => 'Transfer Bank'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_checkout'])) {
    $checkoutData = [
        'recipient_name' => trim($_POST['recipient_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'payment_method' => trim($_POST['payment_method'] ?? 'Transfer Bank')
    ];

    if ($checkoutData['recipient_name'] === '') {
        $errors[] = 'Nama penerima wajib diisi.';
    }
    if ($checkoutData['phone'] === '') {
        $errors[] = 'Nomor telepon wajib diisi.';
    }
    if ($checkoutData['address'] === '') {
        $errors[] = 'Alamat lengkap wajib diisi.';
    }
    if ($checkoutData['city'] === '') {
        $errors[] = 'Kota wajib diisi.';
    }
    if ($checkoutData['postal_code'] === '') {
        $errors[] = 'Kode pos wajib diisi.';
    }

    if (empty($cartItems)) {
        $errors[] = 'Keranjang kosong. Tambahkan produk sebelum checkout.';
    }

    if (empty($errors)) {
        $transaction = placeOrder($checkoutData);
        if ($transaction) {
            $flashMessage = 'Transaksi berhasil. Stok telah diperbarui dan riwayat pembelian tersimpan.';
        } else {
            $errors[] = 'Gagal memproses transaksi. Silakan coba lagi.';
        }
    }
}

include __DIR__ . '/header.php';
?>
<div class="page-section checkout-page">
    <div class="section-card">
        <div class="section-heading">
            <h1>Checkout</h1>
            <p>Konfirmasi pesanan sebelum memproses pembayaran.</p>
        </div>
        <?php if ($transaction): ?>
            <div class="success-panel">
                <h2>Pesanan Berhasil!</h2>
                <p>ID Pesanan: <strong><?= htmlspecialchars($transaction['id']) ?></strong></p>
                <p>Tanggal: <?= htmlspecialchars($transaction['date']) ?></p>
                <p>Total: <strong><?= formatIDR($transaction['total']) ?></strong></p>
                <a href="history.php" class="btn-primary">Lihat Riwayat Transaksi</a>
            </div>
        <?php elseif (empty($cartItems)): ?>
            <div class="empty-state">
                <p>Keranjang kosong. Tambahkan produk sebelum melakukan checkout.</p>
                <a href="index.php" class="btn-primary">Kembali ke Katalog</a>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="checkout-summary-panel">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span><?= formatIDR(getCartTotal()) ?></span>
                </div>
                <div class="summary-row">
                    <span>Biaya kirim</span>
                    <span>Rp0</span>
                </div>
                <div class="summary-row total-row">
                    <span>Total Pembayaran</span>
                    <span><?= formatIDR(getCartTotal()) ?></span>
                </div>
                <form method="post" class="checkout-form">
                    <div class="checkout-field">
                        <label>Nama Penerima</label>
                        <input type="text" name="recipient_name" value="<?= htmlspecialchars($checkoutData['recipient_name']) ?>" required>
                    </div>
                    <div class="checkout-field">
                        <label>Nomor Telepon</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($checkoutData['phone']) ?>" required>
                    </div>
                    <div class="checkout-field">
                        <label>Alamat Lengkap</label>
                        <textarea name="address" rows="3" required><?= htmlspecialchars($checkoutData['address']) ?></textarea>
                    </div>
                    <div class="checkout-row">
                        <div class="checkout-field half">
                            <label>Kota</label>
                            <input type="text" name="city" value="<?= htmlspecialchars($checkoutData['city']) ?>" required>
                        </div>
                        <div class="checkout-field half">
                            <label>Kode Pos</label>
                            <input type="text" name="postal_code" value="<?= htmlspecialchars($checkoutData['postal_code']) ?>" required>
                        </div>
                    </div>
                    <div class="checkout-field">
                        <label>Metode Pembayaran</label>
                        <select name="payment_method" required>
                            <?php foreach (['Transfer Bank', 'E-Wallet', 'Cash on Delivery'] as $method): ?>
                                <option value="<?= htmlspecialchars($method) ?>" <?= $checkoutData['payment_method'] === $method ? 'selected' : '' ?>><?= htmlspecialchars($method) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="checkout-actions">
                        <button type="submit" name="confirm_checkout" class="btn-primary">Bayar Sekarang</button>
                        <a href="cart.php" class="btn-secondary">Kembali ke Keranjang</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
