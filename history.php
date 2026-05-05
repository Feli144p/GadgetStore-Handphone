<?php
require_once __DIR__ . '/functions.php';
requireLogin();
$user = getUser();
$transactions = getTransactions($user['email']);
include __DIR__ . '/header.php';
?>
<div class="page-section history-page">
    <div class="section-card">
        <div class="section-heading">
            <h1>Riwayat Transaksi</h1>
            <p>Semua pesanan yang sudah Anda lakukan tercatat di sini.</p>
        </div>
        <?php if (empty($transactions)): ?>
            <div class="empty-state">
                <p>Belum ada transaksi. Silakan lakukan pembelian untuk melihat riwayat.</p>
                <a href="index.php" class="btn-primary">Belanja Sekarang</a>
            </div>
        <?php else: ?>
            <div class="transaction-list">
                <?php foreach (array_reverse($transactions) as $transaction): ?>
                    <div class="transaction-card">
                        <div class="transaction-header">
                            <div>
                                <h2>ID Pesanan <?= htmlspecialchars($transaction['id']) ?></h2>
                                <p><?= htmlspecialchars($transaction['date']) ?></p>
                            </div>
                            <span class="tag"><?= formatIDR($transaction['total']) ?></span>
                        </div>
                        <div class="transaction-meta">
                            <div><strong>Pembayaran:</strong> <?= htmlspecialchars($transaction['payment_method'] ?? 'Belum ditentukan') ?></div>
                            <div><strong>Nama Penerima:</strong> <?= htmlspecialchars($transaction['recipient_name'] ?? '-') ?></div>
                            <div><strong>Telepon:</strong> <?= htmlspecialchars($transaction['phone'] ?? '-') ?></div>
                            <div><strong>Alamat:</strong> <?= htmlspecialchars($transaction['address'] ?? '-') ?>, <?= htmlspecialchars($transaction['city'] ?? '-') ?> <?= htmlspecialchars($transaction['postal_code'] ?? '-') ?></div>
                        </div>
                        <div class="transaction-items">
                            <?php foreach ($transaction['items'] as $item): ?>
                                <div class="transaction-item">
                                    <span><?= htmlspecialchars($item['quantity']) ?>x <?= htmlspecialchars($item['name']) ?></span>
                                    <span><?= formatIDR($item['subtotal']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
