<?php
$sessionName = 'USERSESSID';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
if (strpos($scriptName, '/admin/') === 0) {
    $sessionName = 'ADMINSESSID';
}
session_name($sessionName);
session_start();
require_once __DIR__ . '/db.php';

function getAppRoot()
{
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $segments = explode('/', trim($scriptDir, '/'));
    if (empty($segments[0])) {
        return '';
    }
    if (in_array(end($segments), ['admin', 'user'], true)) {
        array_pop($segments);
    }
    $segments = array_map('rawurlencode', $segments);
    return '/' . implode('/', $segments);
}

function route($path)
{
    $base = getAppRoot();
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function formatIDR($value)
{
    return 'Rp' . number_format($value, 0, ',', '.');
}

function ensureProductSchema()
{
    static $migrated = false;
    if ($migrated) {
        return;
    }

    $requiredColumns = [
        'spec_ram' => "VARCHAR(100) DEFAULT NULL",
        'spec_storage' => "VARCHAR(100) DEFAULT NULL",
        'spec_camera' => "VARCHAR(100) DEFAULT NULL",
        'spec_chipset' => "VARCHAR(100) DEFAULT NULL",
        'spec_battery' => "VARCHAR(100) DEFAULT NULL"
    ];

    foreach ($requiredColumns as $column => $definition) {
        $query = "SHOW COLUMNS FROM products LIKE '$column'";
        $result = dbQuery($query);
        $hasColumn = $result && $result instanceof mysqli_result && $result->num_rows > 0;
        if (!$hasColumn) {
            dbQuery("ALTER TABLE products ADD COLUMN $column $definition");
        }
    }

    $migrated = true;
}

function ensurePaymentSchema()
{
    static $done = false;
    if ($done) return;

    $cols = [
        "invoice_code VARCHAR(60) UNIQUE",
        "payment_status ENUM('Pending','Pending Verification','Confirmed','Rejected') NOT NULL DEFAULT 'Pending'",
        "payment_method VARCHAR(50) DEFAULT 'Transfer Bank'",
        "payment_details TEXT DEFAULT NULL",
        "payment_proof VARCHAR(255) DEFAULT NULL",
        "items_json TEXT DEFAULT NULL"
    ];

    foreach ($cols as $colDef) {
        $col = preg_split('/\s+/', $colDef)[0];
        $res = dbQuery("SHOW COLUMNS FROM transactions LIKE '$col'");
        $has = $res && $res instanceof mysqli_result && $res->num_rows > 0;
        if (!$has) {
            dbQuery("ALTER TABLE transactions ADD COLUMN $colDef");
        }
    }

    $res = dbQuery("SHOW TABLES LIKE 'payment_audit'");
    if (!($res && $res instanceof mysqli_result && $res->num_rows > 0)) {
        dbQuery("CREATE TABLE IF NOT EXISTS payment_audit (id INT AUTO_INCREMENT PRIMARY KEY, transaction_id INT NOT NULL, admin_id INT DEFAULT NULL, action VARCHAR(50) NOT NULL, note TEXT DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE)");
    }

    $done = true;
}

function generateInvoiceCode()
{
    return 'INV-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
}

function generatePaymentQR($invoiceCode, $amount = null)
{
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $scheme . '://' . $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname($_SERVER['REQUEST_URI']), '/') ;
    $invoiceUrl = $host . $basePath . '/payment_invoice.php?invoice=' . urlencode($invoiceCode);

    $amountText = '';
    if ($amount !== null && is_numeric($amount)) {
        $amountText = 'Amount: ' . formatIDR((int)$amount) . '\n';
    }

    $payloadText = "Invoice: $invoiceCode\n" . $amountText . "URL: $invoiceUrl";
    $payload = urlencode($payloadText);
    $chartUrl = "https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=$payload";

    $imageData = false;
    if (ini_get('allow_url_fopen')) {
        $imageData = @file_get_contents($chartUrl);
    }
    if ($imageData === false && function_exists('curl_init')) {
        $ch = curl_init($chartUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $imageData = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        if ($imageData === false || ($info['http_code'] ?? 0) !== 200) {
            $imageData = false;
        }
    }

    if ($imageData !== false && strlen($imageData) > 0) {
        return 'data:image/png;base64,' . base64_encode($imageData);
    }

    return $chartUrl;
}

function createPendingTransaction($checkoutData)
{
    ensurePaymentSchema();

    $user = getUser();
    if (!$user || empty($user['id'])) return false;
    $items = getCartItems();
    if (empty($items)) return false;

    $userId = (int)$user['id'];
    $invoice = dbEscape(generateInvoiceCode());
    $orderCode = dbEscape(uniqid('ORD'));
    $total = getCartTotal();
    $paymentMethod = dbEscape($checkoutData['payment_method'] ?? 'Transfer Bank');
    $recipientName = dbEscape($checkoutData['recipient_name'] ?? $user['name']);
    $phone = dbEscape($checkoutData['phone'] ?? '');
    $address = dbEscape($checkoutData['address'] ?? '');
    $city = dbEscape($checkoutData['city'] ?? '');
    $postalCode = dbEscape($checkoutData['postal_code'] ?? '');

    $paymentStatus = 'Pending';
    $orderStatus = 'Pending';
    if ($checkoutData['payment_method'] === 'Cash on Delivery') {
        $paymentStatus = 'Confirmed';
        $orderStatus = 'Paid';
    }

    $details = [
        'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        'amount' => $total
    ];
    $detailsJson = dbEscape(json_encode($details));
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
    $itemsJson = dbEscape(json_encode($itemsSnapshot));

    $sql = "INSERT INTO transactions (order_code, invoice_code, user_id, total, payment_method, payment_details, items_json, recipient_name, phone, address, city, postal_code, payment_status, status, created_at) VALUES ('$orderCode', '$invoice', $userId, $total, '$paymentMethod', '$detailsJson', '$itemsJson', '$recipientName', '$phone', '$address', '$city', '$postalCode', '$paymentStatus', '$orderStatus', NOW())";
    $res = dbQuery($sql);
    if (!$res) return false;
    $txId = dbInsertId();

    if ($checkoutData['payment_method'] === 'Cash on Delivery') {
        foreach ($itemsSnapshot as $item) {
            $pid = (int)$item['product_id'];
            $qty = (int)$item['quantity'];
            $price = (int)$item['price'];
            $subtotal = (int)$item['subtotal'];
            dbQuery("INSERT INTO transaction_items (transaction_id, product_id, quantity, price, subtotal) VALUES ($txId, $pid, $qty, $price, $subtotal)");
        }
        dbQuery("INSERT INTO payment_audit (transaction_id, action, note) VALUES ($txId, 'confirmed', 'Automated COD Confirmation')");
        clearCart();
    }

    return [
        'id' => $txId,
        'invoice_code' => $invoice,
        'total' => $total,
        'items' => $itemsSnapshot,
        'expires_at' => $details['expires_at'],
        'payment_method' => $checkoutData['payment_method'],
        'payment_status' => $paymentStatus // Kita return status pembayaran agar gampang di-filter di UI frontend
    ];
}

function attachPaymentProof($transactionId, $filePath)
{
    ensurePaymentSchema();
    $transactionId = (int)$transactionId;
    
    // Cek dulu apakah transaksinya COD. Kalau COD, blokir pengunggahan bukti
    $tx = dbFetchRow(dbQuery("SELECT payment_method FROM transactions WHERE id = $transactionId LIMIT 1"));
    if ($tx && $tx['payment_method'] === 'Cash on Delivery') {
        return false; 
    }

    $fileEsc = dbEscape($filePath);
    $sql = "UPDATE transactions SET payment_proof = '$fileEsc', payment_status = 'Pending Verification' WHERE id = $transactionId";
    $res = dbQuery($sql);
    if ($res) {
        dbQuery("INSERT INTO payment_audit (transaction_id, action) VALUES ($transactionId, 'proof_uploaded')");
    }
    return $res !== false;
}

function adminConfirmPayment($transactionId, $adminId = null)
{
    ensurePaymentSchema();
    $transactionId = (int)$transactionId;
    $tx = dbFetchRow(dbQuery("SELECT * FROM transactions WHERE id = $transactionId LIMIT 1"));
    if (!$tx) return false;
    if ($tx['payment_status'] === 'Confirmed') return true;

    $items = json_decode($tx['items_json'], true) ?: [];

    dbBeginTransaction();
    try {
        foreach ($items as $item) {
            $pid = (int)$item['product_id'];
            $qty = (int)$item['quantity'];
            $price = (int)$item['price'];
            $subtotal = (int)$item['subtotal'];
            $sql = "INSERT INTO transaction_items (transaction_id, product_id, quantity, price, subtotal) VALUES ($transactionId, $pid, $qty, $price, $subtotal)";
            dbQuery($sql);
        }
        dbQuery("UPDATE transactions SET payment_status = 'Confirmed', status = 'Paid' WHERE id = $transactionId");
        dbQuery("INSERT INTO payment_audit (transaction_id, admin_id, action) VALUES ($transactionId, " . (int)$adminId . ", 'confirmed')");
        
        $userId = (int)$tx['user_id'];
        dbQuery("DELETE FROM cart_items WHERE cart_id IN (SELECT id FROM carts WHERE user_id = $userId)");
        dbCommit();
        return true;
    } catch (Exception $e) {
        dbRollback();
        return false;
    }
}

function adminRejectPayment($transactionId, $adminId = null, $note = '')
{
    ensurePaymentSchema();
    $transactionId = (int)$transactionId;
    $noteEsc = dbEscape($note);
    $res = dbQuery("UPDATE transactions SET payment_status = 'Rejected' WHERE id = $transactionId");
    if ($res) {
        dbQuery("INSERT INTO payment_audit (transaction_id, admin_id, action, note) VALUES ($transactionId, " . (int)$adminId . ", 'rejected', '$noteEsc')");
    }
    return $res !== false;
}

function mapProductRow(array $row)
{
    if (!$row) {
        return null;
    }

    if (!isset($row['id']) && isset($row['product_id'])) {
        $row['id'] = $row['product_id'];
    }

    $row['id'] = (int)($row['id'] ?? 0);
    $row['price'] = (int)($row['price'] ?? 0);
    $row['stock'] = (int)($row['stock'] ?? 0);
    $row['specs'] = [
        'RAM' => $row['spec_ram'] ?? '',
        'Storage' => $row['spec_storage'] ?? '',
        'Camera' => $row['spec_camera'] ?? '',
        'Chipset' => $row['spec_chipset'] ?? '',
        'Battery' => $row['spec_battery'] ?? ''
    ];
    return $row;
}

function getProducts()
{
    $sql = 'SELECT * FROM products ORDER BY id';
    $result = dbQuery($sql);
    $rows = dbFetchAll($result);
    return array_map('mapProductRow', $rows);
}

function getProduct($id)
{
    $id = (int)$id;
    $sql = "SELECT * FROM products WHERE id = $id LIMIT 1";
    $result = dbQuery($sql);
    $row = dbFetchRow($result);
    return $row ? mapProductRow($row) : null;
}

function getCategories()
{
    $categories = [];
    $result = dbQuery('SELECT name FROM categories ORDER BY name');
    $rows = dbFetchAll($result);
    foreach ($rows as $row) {
        $categories[] = $row['name'];
    }

    if (empty($categories)) {
        $result = dbQuery('SELECT DISTINCT brand AS name FROM products ORDER BY brand');
        $rows = dbFetchAll($result);
        foreach ($rows as $row) {
            $categories[] = $row['name'];
        }
    }

    array_unshift($categories, 'Semua');
    return array_unique($categories);
}

function filterProducts($query = '', $brand = 'Semua')
{
    $clauses = [];
    if ($brand !== 'Semua') {
        $brandEsc = dbEscape($brand);
        $clauses[] = "(brand = '$brandEsc' OR category = '$brandEsc')";
    }
    if ($query !== '') {
        $queryEsc = dbEscape($query);
        $clauses[] = "(name LIKE '%$queryEsc%' OR brand LIKE '%$queryEsc%' OR category LIKE '%$queryEsc%')";
    }
    $sql = 'SELECT * FROM products';
    if (!empty($clauses)) {
        $sql .= ' WHERE ' . implode(' AND ', $clauses);
    }
    $sql .= ' ORDER BY id';
    $result = dbQuery($sql);
    $rows = dbFetchAll($result);
    return array_map('mapProductRow', $rows);
}

function getUser()
{
    return $_SESSION['user'] ?? null;
}

function getUserByEmail($email)
{
    $emailEsc = dbEscape($email);
    $sql = "SELECT * FROM users WHERE email = '$emailEsc' LIMIT 1";
    $result = dbQuery($sql);
    return dbFetchRow($result);
}

function loginUser($email, $role = null)
{
    $user = getUserByEmail($email);
    if ($role === null && $user) {
        $role = $user['role'] ?? 'buyer';
    }
    if ($role === null) {
        $role = 'buyer';
    }
    $_SESSION['user'] = [
        'id' => $user['id'] ?? null,
        'email' => $email,
        'name' => ucfirst(strtok($email, '@')),
        'role' => $role
    ];
}

function logoutUser()
{
    unset($_SESSION['user']);
}

function isUserAdmin($email)
{
    $user = getUserByEmail($email);
    return $user && isset($user['role']) && $user['role'] === 'admin';
}

function isAdmin()
{
    $user = getUser();
    return $user && isset($user['role']) && $user['role'] === 'admin';
}

function registerUser($email, $password, $role = 'buyer')
{
    if (getUserByEmail($email)) {
        return false;
    }
    $role = $role === 'admin' ? 'admin' : 'buyer';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $name = ucfirst(strtok($email, '@'));
    $emailEsc = dbEscape($email);
    $hashEsc = dbEscape($hash);
    $nameEsc = dbEscape($name);
    $roleEsc = dbEscape($role);

    $sql = "INSERT INTO users (email, password, name, role) VALUES ('$emailEsc', '$hashEsc', '$nameEsc', '$roleEsc')";
    $result = dbQuery($sql);
    if ($result) {
        loginUser($email, $role);
        return true;
    }
    return false;
}

function validateUser($email, $password)
{
    $user = getUserByEmail($email);
    if (!$user) {
        return false;
    }
    return password_verify($password, $user['password']);
}

function requireLogin($loginPath = 'user/login.php')
{
    if (!getUser()) {
        header('Location: ' . route($loginPath));
        exit;
    }
}

function getOrCreateCartId()
{
    $user = getUser();
    if (!$user || empty($user['id'])) {
        return null;
    }
    $userId = (int)$user['id'];
    $sql = "SELECT id FROM carts WHERE user_id = $userId LIMIT 1";
    $result = dbQuery($sql);
    $row = dbFetchRow($result);
    if ($row && !empty($row['id'])) {
        return (int)$row['id'];
    }
    $sql = "INSERT INTO carts (user_id) VALUES ($userId)";
    dbQuery($sql);
    return dbInsertId();
}

function addToCart($productId, $quantity = 1)
{
    $productId = (int)$productId;
    $quantity = max(1, (int)$quantity);
    $product = getProduct($productId);
    if (!$product || $product['stock'] <= 0) {
        return false;
    }

    $user = getUser();
    if ($user && !empty($user['id'])) {
        $cartId = getOrCreateCartId();
        $userQty = 0;
        $sql = "SELECT quantity FROM cart_items WHERE cart_id = $cartId AND product_id = $productId LIMIT 1";
        $result = dbQuery($sql);
        $row = dbFetchRow($result);
        if ($row) {
            $userQty = (int)$row['quantity'];
        }
        $newQty = min($userQty + $quantity, $product['stock']);
        if ($row) {
            $sql = "UPDATE cart_items SET quantity = $newQty WHERE cart_id = $cartId AND product_id = $productId";
        } else {
            $sql = "INSERT INTO cart_items (cart_id, product_id, quantity) VALUES ($cartId, $productId, $newQty)";
        }
        dbQuery($sql);
        return true;
    }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $currentQty = $_SESSION['cart'][$productId] ?? 0;
    $newQty = min($currentQty + $quantity, $product['stock']);
    $_SESSION['cart'][$productId] = $newQty;
    return true;
}

function updateCart($quantities)
{
    $user = getUser();
    if ($user && !empty($user['id'])) {
        $cartId = getOrCreateCartId();
        foreach ($quantities as $productId => $qty) {
            $productId = (int)$productId;
            $qty = (int)$qty;
            if ($qty <= 0) {
                $sql = "DELETE FROM cart_items WHERE cart_id = $cartId AND product_id = $productId";
                dbQuery($sql);
                continue;
            }
            $product = getProduct($productId);
            if (!$product) {
                continue;
            }
            $safeQty = min($qty, $product['stock']);
            $sql = "SELECT id FROM cart_items WHERE cart_id = $cartId AND product_id = $productId LIMIT 1";
            $result = dbQuery($sql);
            if (dbFetchRow($result)) {
                $sql = "UPDATE cart_items SET quantity = $safeQty WHERE cart_id = $cartId AND product_id = $productId";
            } else {
                $sql = "INSERT INTO cart_items (cart_id, product_id, quantity) VALUES ($cartId, $productId, $safeQty)";
            }
            dbQuery($sql);
        }
        return;
    }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    foreach ($quantities as $productId => $qty) {
        $productId = (int)$productId;
        $qty = (int)$qty;
        if ($qty <= 0) {
            unset($_SESSION['cart'][$productId]);
            continue;
        }
        $product = getProduct($productId);
        if (!$product) {
            continue;
        }
        $_SESSION['cart'][$productId] = min($qty, $product['stock']);
    }
}

function getCartItems()
{
    $items = [];
    $user = getUser();
    if ($user && !empty($user['id'])) {
        $cartId = getOrCreateCartId();
        $sql = "SELECT ci.product_id, ci.quantity, p.* FROM cart_items ci JOIN products p ON ci.product_id = p.id WHERE ci.cart_id = $cartId";
        $result = dbQuery($sql);
        $rows = dbFetchAll($result);
        foreach ($rows as $row) {
            $product = mapProductRow($row);
            $product['quantity'] = (int)$row['quantity'];
            $product['subtotal'] = $product['quantity'] * $product['price'];
            $items[$product['id']] = $product;
        }
        return $items;
    }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    foreach ($_SESSION['cart'] as $productId => $quantity) {
        $product = getProduct($productId);
        if (!$product) {
            continue;
        }
        $product['quantity'] = $quantity;
        $product['subtotal'] = $product['price'] * $quantity;
        $items[$productId] = $product;
    }
    return $items;
}

function countCartItems()
{
    $items = getCartItems();
    $count = 0;
    foreach ($items as $item) {
        $count += $item['quantity'];
    }
    return $count;
}

function getCartTotal()
{
    $total = 0;
    foreach (getCartItems() as $item) {
        $total += $item['subtotal'];
    }
    return $total;
}

function clearCart()
{
    $user = getUser();
    if ($user && !empty($user['id'])) {
        $cartId = getOrCreateCartId();
        $sql = "DELETE FROM cart_items WHERE cart_id = $cartId";
        dbQuery($sql);
        return;
    }
    unset($_SESSION['cart']);
}

function createProduct($data)
{
    ensureProductSchema();

    $name = dbEscape($data['name']);
    $brand = dbEscape($data['brand']);
    $category = dbEscape($data['category']);
    $price = (int)$data['price'];
    $stock = (int)$data['stock'];
    $image = dbEscape($data['image'] ?? '');
    $description = dbEscape($data['description'] ?? '');
    $specRam = dbEscape($data['specs']['RAM'] ?? '');
    $specStorage = dbEscape($data['specs']['Storage'] ?? '');
    $specCamera = dbEscape($data['specs']['Camera'] ?? '');
    $specChipset = dbEscape($data['specs']['Chipset'] ?? '');
    $specBattery = dbEscape($data['specs']['Battery'] ?? '');

    $sql = "INSERT INTO products (name, brand, category, price, stock, image, description, spec_ram, spec_storage, spec_camera, spec_chipset, spec_battery) VALUES ('{$name}', '{$brand}', '{$category}', $price, $stock, '{$image}', '{$description}', '{$specRam}', '{$specStorage}', '{$specCamera}', '{$specChipset}', '{$specBattery}')";
    $result = dbQuery($sql);
    return $result ? dbInsertId() : false;
}

function updateProduct($id, $data)
{
    ensureProductSchema();

    $id = (int)$id;
    $name = dbEscape($data['name']);
    $brand = dbEscape($data['brand']);
    $category = dbEscape($data['category']);
    $price = (int)$data['price'];
    $stock = (int)$data['stock'];
    $image = dbEscape($data['image'] ?? '');
    $description = dbEscape($data['description'] ?? '');
    $specRam = dbEscape($data['specs']['RAM'] ?? '');
    $specStorage = dbEscape($data['specs']['Storage'] ?? '');
    $specCamera = dbEscape($data['specs']['Camera'] ?? '');
    $specChipset = dbEscape($data['specs']['Chipset'] ?? '');
    $specBattery = dbEscape($data['specs']['Battery'] ?? '');

    $sql = "UPDATE products SET name = '{$name}', brand = '{$brand}', category = '{$category}', price = $price, stock = $stock, image = '{$image}', description = '{$description}', spec_ram = '{$specRam}', spec_storage = '{$specStorage}', spec_camera = '{$specCamera}', spec_chipset = '{$specChipset}', spec_battery = '{$specBattery}' WHERE id = $id";
    $result = dbQuery($sql);
    return $result !== false;
}

function deleteProduct($id)
{
    $id = (int)$id;
    $sql = "DELETE FROM products WHERE id = $id";
    $result = dbQuery($sql);
    return $result !== false;
}

function placeOrder($checkoutData)
{
    $user = getUser();
    if (!$user || empty($user['id'])) {
        return false;
    }

    $items = getCartItems();
    if (empty($items)) {
        return false;
    }

    dbBeginTransaction();
    try {
        $userId = (int)$user['id'];
        $orderCode = dbEscape(uniqid('ORD'));
        $total = getCartTotal();
        $paymentMethod = dbEscape($checkoutData['payment_method'] ?? 'N/A');
        $recipientName = dbEscape($checkoutData['recipient_name'] ?? $user['name']);
        $phone = dbEscape($checkoutData['phone'] ?? '');
        $address = dbEscape($checkoutData['address'] ?? '');
        $city = dbEscape($checkoutData['city'] ?? '');
        $postalCode = dbEscape($checkoutData['postal_code'] ?? '');

        $sql = "INSERT INTO transactions (order_code, user_id, total, payment_method, recipient_name, phone, address, city, postal_code, status) VALUES ('$orderCode', $userId, $total, '$paymentMethod', '$recipientName', '$phone', '$address', '$city', '$postalCode', 'Paid')";
        dbQuery($sql);
        $transactionId = dbInsertId();

        foreach ($items as $item) {
            $productId = (int)$item['id'];
            $quantity = (int)$item['quantity'];
            $price = (int)$item['price'];
            $subtotal = (int)$item['subtotal'];

            $sql = "INSERT INTO transaction_items (transaction_id, product_id, quantity, price, subtotal) VALUES ($transactionId, $productId, $quantity, $price, $subtotal)";
            dbQuery($sql);
        }

        clearCart();
        dbCommit();

        $transaction = [
            'id' => $orderCode,
            'date' => date('d M Y H:i'),
            'user' => $user['email'],
            'items' => $items,
            'total' => $total,
            'payment_method' => $checkoutData['payment_method'] ?? 'N/A',
            'recipient_name' => $checkoutData['recipient_name'] ?? $user['name'],
            'phone' => $checkoutData['phone'] ?? '',
            'address' => $checkoutData['address'] ?? '',
            'city' => $checkoutData['city'] ?? '',
            'postal_code' => $checkoutData['postal_code'] ?? ''
        ];
        return $transaction;
    } catch (Exception $e) {
        dbRollback();
        return false;
    }
}

function getTransactions($userEmail = null)
{
    $conditions = [];
    if ($userEmail !== null) {
        $user = getUserByEmail($userEmail);
        if (!$user) {
            return [];
        }
        $userId = (int)$user['id'];
        $conditions[] = "t.user_id = $userId";
    }
    $sql = 'SELECT t.*, u.email AS user_email FROM transactions t JOIN users u ON t.user_id = u.id';
    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY t.id DESC';
    $result = dbQuery($sql);
    $transactions = dbFetchAll($result);

    foreach ($transactions as &$transaction) {
        $tid = (int)$transaction['id'];
        $sql = "SELECT ti.quantity, ti.price, ti.subtotal, p.name, p.brand FROM transaction_items ti JOIN products p ON ti.product_id = p.id WHERE ti.transaction_id = $tid";
        $itemsResult = dbQuery($sql);
        $transaction['items'] = dbFetchAll($itemsResult);
    }
    return $transactions;
}