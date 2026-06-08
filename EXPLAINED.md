# EXPLAINED — Potongan Kode Penting dan Penjelasan

Berikut potongan kode utama dari proyek dan penjelasan baris-per-baris supaya mudah dipelajari dan dijelaskan.

---

## 1) `db.php` — koneksi dan helper DB

```php
function dbConnect()
{
    static $connection;
    if ($connection === null) {
        // Buat koneksi mysqli sekali saja (singleton)
        $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($connection->connect_error) {
            die('Database connection failed: ' . $connection->connect_error);
        }
        // Pastikan charset UTF-8 untuk penyimpanan karakter yang benar
        $connection->set_charset('utf8mb4');
    }
    return $connection;
}

function dbQuery($sql)
{
    // Jalankan query dan kembalikan result object atau false
    $result = dbConnect()->query($sql);
    if ($result === false) {
        // Trigger warning agar error mudah dideteksi saat debugging
        trigger_error('Database query error: ' . dbConnect()->error . '\nSQL: ' . $sql, E_USER_WARNING);
    }
    return $result;
}
```

Penjelasan ringkas: `dbConnect()` menyimpan koneksi di static variable sehingga tidak membuat koneksi berulang. `dbQuery()` hanya wrapper kecil untuk `mysqli::query()` yang juga memicu warning bila terjadi error.

---

## 2) `createPendingTransaction()` (di `functions.php`) — membuat invoice pending

```php
// Ambil user dari session dan isi keranjang
$user = getUser();
$items = getCartItems();

// Buat kode invoice dan order code unik
$invoice = dbEscape(generateInvoiceCode());
$orderCode = dbEscape(uniqid('ORD'));

// Hitung total dan siapkan detail pembayaran
$total = getCartTotal();
$details = [ 'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')), 'amount' => $total ];

// Buat snapshot items jadi array sederhana supaya data tidak berubah nanti
$itemsSnapshot = [];
foreach ($items as $item) {
    $itemsSnapshot[] = [
        'product_id' => $item['id'],
        'name' => $item['name'],
        'quantity' => $item['quantity'],
        'price' => $item['price'],
        'subtotal' => $item['subtotal']
    ];
}

// Simpan ke DB sebagai JSON (escape string terlebih dahulu)
$itemsJson = dbEscape(json_encode($itemsSnapshot));
$detailsJson = dbEscape(json_encode($details));

// INSERT transaksi sebagai record pending (order_code + invoice_code + items_json)
$sql = "INSERT INTO transactions (order_code, invoice_code, user_id, total, payment_method, payment_details, items_json, recipient_name, phone, address, city, postal_code, payment_status, status, created_at) VALUES ('$orderCode', '$invoice', $userId, $total, '$paymentMethod', '$detailsJson', '$itemsJson', '$recipientName', '$phone', '$address', '$city', '$postalCode', 'Pending', 'Pending', NOW())";
dbQuery($sql);
$txId = dbInsertId();

// Kembalikan data minimal ke UI (id, invoice, total, items snapshot)
return ['id' => $txId, 'invoice_code' => $invoice, 'total' => $total, 'items' => $itemsSnapshot];
```

Penjelasan ringkas: fungsi ini tidak melakukan finalisasi (belum memindahkan item ke `transaction_items`), hanya mencatat pesanan sebagai pending beserta snapshot item. Snapshot mencegah perubahan produk setelah pesanan dibuat.

---

## 3) `attachPaymentProof()` (di `functions.php`) — menyimpan path bukti

```php
function attachPaymentProof($transactionId, $filePath)
{
    ensurePaymentSchema(); // pastikan kolom tersedia
    $transactionId = (int)$transactionId;
    $fileEsc = dbEscape($filePath);
    // update record transaksi: simpan path file dan ubah status
    $sql = "UPDATE transactions SET payment_proof = '$fileEsc', payment_status = 'Pending Verification' WHERE id = $transactionId";
    $res = dbQuery($sql);
    if ($res) {
        // catat audit sederhana
        dbQuery("INSERT INTO payment_audit (transaction_id, action) VALUES ($transactionId, 'proof_uploaded')");
    }
    return $res !== false;
}
```

Penjelasan: fungsi ini hanya menyimpan path publik relatif (`uploads/payments/xxx.png`) dan mengubah status sehingga admin dapat melihat antrian verifikasi.

---

## 4) `adminConfirmPayment()` (di `functions.php`) — finalize order saat admin konfirmasi

```php
// Ambil transaksi lengkap
$tx = dbFetchRow(dbQuery("SELECT * FROM transactions WHERE id = $transactionId LIMIT 1"));
// Ambil snapshot items dari kolom items_json
$items = json_decode($tx['items_json'], true) ?: [];

dbBeginTransaction();
try {
    // Insert ke table transaction_items untuk setiap item
    foreach ($items as $item) {
        $pid = (int)$item['product_id'];
        $qty = (int)$item['quantity'];
        $price = (int)$item['price'];
        $subtotal = (int)$item['subtotal'];
        dbQuery("INSERT INTO transaction_items (transaction_id, product_id, quantity, price, subtotal) VALUES ($transactionId, $pid, $qty, $price, $subtotal)");
    }
    // Tandai transaksi sebagai dibayar
    dbQuery("UPDATE transactions SET payment_status = 'Confirmed', status = 'Paid' WHERE id = $transactionId");
    // Hapus cart user (opsional)
    $userId = (int)$tx['user_id'];
    dbQuery("DELETE FROM cart_items WHERE cart_id IN (SELECT id FROM carts WHERE user_id = $userId)");
    dbCommit();
    return true;
} catch (Exception $e) {
    dbRollback();
    return false;
}
```

Penjelasan: seluruh langkah dilakukan dalam DB transaction agar konsisten — jika salah satu query gagal, semuanya di-rollback.

---

## 5) `upload_proof.php` — penanganan upload (potongan penting)

```php
// pastikan request POST dan user punya akses ke transaksi
if (!isset($_FILES['proof_file']) || $_FILES['proof_file']['error'] !== UPLOAD_ERR_OK) { /* error */ }

// validasi MIME dengan finfo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

// hanya izinkan image/jpeg, image/png, application/pdf
if (!in_array($mime, ['image/jpeg','image/png','application/pdf'])) { /* reject */ }

// gunakan ekstensi dari MIME, bukan dari nama file user
$ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/jpeg') ? 'jpg' : 'pdf');

// simpan dengan nama acak
$safe = time() . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/payments/' . $safe);

// simpan path relatif di DB
attachPaymentProof($txId, 'uploads/payments/' . $safe);
```

Penjelasan: validasi MIME dan penggunaan nama acak membantu mencegah file berbahaya dieksekusi di server dan mengurangi serangan berbasis nama file.

---

## 6) `payment_invoice.php` — menampilkan invoice & copy-invoice

```php
$tx = dbFetchRow(dbQuery("SELECT * FROM transactions WHERE invoice_code = '...'") );
echo '<h1>Invoice ' . htmlspecialchars($tx['invoice_code']) . '</h1>';
echo '<p>Total: ' . formatIDR($tx['total']) . '</p>'; // formatIDR helper

// tampilkan items dari JSON snapshot
$items = json_decode($tx['items_json'], true) ?: [];
foreach ($items as $it) {
    echo '<li>' . (int)$it['quantity'] . ' x ' . htmlspecialchars($it['name']) . ' — ' . formatIDR($it['subtotal']) . '</li>';
}

// Form upload bukti
echo '<form action="upload_proof.php" method="post" enctype="multipart/form-data">';
echo '<input type="hidden" name="transaction_id" value="' . (int)$tx['id'] . '">';
echo '<input type="file" name="proof_file">';
echo '<button>Unggah Bukti</button>';
echo '</form>';
```

Penjelasan: halaman ini mengikat invoice ke record `transactions` — `transaction_id` dikirim saat upload sehingga sistem tahu file bukti untuk transaksi mana.

---

Jika Anda mau, saya bisa menambahkan versi potongan ini yang sudah siap untuk slide (mis. 3–4 fungsi, 20–30 baris) atau menyisipkan komentar langsung ke file sumber. Mau yang mana?
