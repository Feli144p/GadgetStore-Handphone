<?php
require_once __DIR__ . '/functions.php';
requireLogin();
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}
$errors = [];
$success = '';
$products = getProducts();
$transactions = getTransactions();
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$productToEdit = $editId ? getProduct($editId) : null;
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_product'])) {
        $name = trim($_POST['name'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = (int)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $specs = [
            'RAM' => trim($_POST['spec_ram'] ?? ''),
            'Storage' => trim($_POST['spec_storage'] ?? ''),
            'Camera' => trim($_POST['spec_camera'] ?? ''),
            'Chipset' => trim($_POST['spec_chipset'] ?? ''),
            'Battery' => trim($_POST['spec_battery'] ?? '')
        ];

        if ($name === '' || $brand === '' || $category === '' || $price <= 0 || $stock < 0 || $description === '') {
            $errors[] = 'Semua kolom wajib diisi dan harga harus lebih dari 0.';
        }

        $imagePath = $image;
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $originalName = basename($_FILES['image_file']['name']);
                $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
                $targetName = time() . '-' . $safeName;
                $targetPath = $uploadDir . '/' . $targetName;
                if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
                    $imagePath = 'uploads/' . $targetName;
                } else {
                    $errors[] = 'Gagal mengunggah gambar produk.';
                }
            } else {
                $errors[] = 'Terjadi kesalahan saat mengunggah gambar.';
            }
        }

        if ($imagePath === '') {
            if ($productToEdit && !empty($productToEdit['image'])) {
                $imagePath = $productToEdit['image'];
            } else {
                $imagePath = 'placeholder.png';
            }
        }

        if (empty($errors)) {
            $productData = [
                'name' => $name,
                'brand' => $brand,
                'category' => $category,
                'price' => $price,
                'stock' => $stock,
                'image' => $imagePath,
                'description' => $description,
                'specs' => $specs
            ];

            if (!empty($_POST['product_id'])) {
                $updateId = (int)$_POST['product_id'];
                if (updateProduct($updateId, $productData)) {
                    $success = 'Produk berhasil diperbarui.';
                    header('Location: admin.php');
                    exit;
                }
                $errors[] = 'Produk gagal diperbarui.';
            } else {
                createProduct($productData);
                $success = 'Produk berhasil ditambahkan.';
                header('Location: admin.php');
                exit;
            }
        }
    }

    if (isset($_POST['delete_product'])) {
        $deleteId = (int)$_POST['delete_product'];
        if (deleteProduct($deleteId)) {
            $success = 'Produk berhasil dihapus.';
            header('Location: admin.php');
            exit;
        }
        $errors[] = 'Produk gagal dihapus.';
    }
}

include __DIR__ . '/header.php';
?>
<div class="page-section admin-page">
    <div class="section-card">
        <div class="section-heading">
            <h1>Admin Panel</h1>
            <p>Tambahkan, edit, atau hapus produk, serta lihat transaksi pelanggan.</p>
        </div>

        <?php if ($success): ?>
            <div class="flash-message"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="admin-grid">
            <section class="admin-form-card">
                <h2><?= $productToEdit ? 'Edit Produk' : 'Tambah Produk Baru' ?></h2>
                <form method="post" enctype="multipart/form-data" class="auth-form">
                    <input type="hidden" name="product_id" value="<?= $productToEdit ? $editId : '' ?>">
                    <label>Nama Produk</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($productToEdit['name'] ?? '') ?>" required>
                    <label>Brand</label>
                    <input type="text" name="brand" value="<?= htmlspecialchars($productToEdit['brand'] ?? '') ?>" required>
                    <label>Kategori</label>
                    <input type="text" name="category" value="<?= htmlspecialchars($productToEdit['category'] ?? '') ?>" required>
                    <label>Harga</label>
                    <input type="number" name="price" min="0" value="<?= htmlspecialchars($productToEdit['price'] ?? '') ?>" required>
                    <label>Stok</label>
                    <input type="number" name="stock" min="0" value="<?= htmlspecialchars($productToEdit['stock'] ?? '') ?>" required>
                    <label>Gambar Produk</label>
                    <input type="file" name="image_file" accept="image/*">
                    <?php if ($productToEdit && !empty($productToEdit['image'])): ?>
                        <p class="form-note">Gambar saat ini: <?= htmlspecialchars($productToEdit['image']) ?></p>
                    <?php endif; ?>
                    <label>Deskripsi</label>
                    <textarea name="description" rows="4" required><?= htmlspecialchars($productToEdit['description'] ?? '') ?></textarea>
                    <label>RAM</label>
                    <input type="text" name="spec_ram" value="<?= htmlspecialchars($productToEdit['specs']['RAM'] ?? '') ?>" required>
                    <label>Storage</label>
                    <input type="text" name="spec_storage" value="<?= htmlspecialchars($productToEdit['specs']['Storage'] ?? '') ?>" required>
                    <label>Kamera</label>
                    <input type="text" name="spec_camera" value="<?= htmlspecialchars($productToEdit['specs']['Camera'] ?? '') ?>" required>
                    <label>Chipset</label>
                    <input type="text" name="spec_chipset" value="<?= htmlspecialchars($productToEdit['specs']['Chipset'] ?? '') ?>" required>
                    <label>Baterai</label>
                    <input type="text" name="spec_battery" value="<?= htmlspecialchars($productToEdit['specs']['Battery'] ?? '') ?>" required>
                    <button type="submit" name="save_product" class="btn-primary"><?= $productToEdit ? 'Simpan Perubahan' : 'Tambah Produk' ?></button>
                    <?php if ($productToEdit): ?>
                        <a href="admin.php" class="btn-secondary">Batal</a>
                    <?php endif; ?>
                </form>
            </section>

            <section class="admin-table-card">
                <h2>Daftar Produk</h2>
                <div class="product-table">
                    <div class="product-table-row header">
                        <span>#</span>
                        <span>Nama</span>
                        <span>Brand</span>
                        <span>Harga</span>
                        <span>Stok</span>
                        <span>Aksi</span>
                    </div>
                    <?php foreach ($products as $id => $product): ?>
                        <div class="product-table-row">
                            <span><?= $id ?></span>
                            <span><?= htmlspecialchars($product['name']) ?></span>
                            <span><?= htmlspecialchars($product['brand']) ?></span>
                            <span><?= formatIDR($product['price']) ?></span>
                            <span><?= htmlspecialchars($product['stock']) ?></span>
                            <span class="table-actions">
                                <a href="admin.php?id=<?= $id ?>" class="btn-secondary small">Edit</a>
                                <form method="post" style="display:inline-block;" onsubmit="return confirm('Hapus produk ini?');">
                                    <input type="hidden" name="delete_product" value="<?= $id ?>">
                                    <button type="submit" class="btn-secondary small">Hapus</button>
                                </form>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div class="section-heading">
            <h2>Riwayat Transaksi</h2>
            <p>Daftar pesanan yang telah dilakukan oleh pelanggan.</p>
        </div>
        <?php if (empty($transactions)): ?>
            <div class="empty-state">
                <p>Belum ada transaksi yang tercatat.</p>
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
                        <div class="transaction-meta admin-meta">
                            <div><strong>User:</strong> <?= htmlspecialchars($transaction['user'] ?? '-') ?></div>
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
