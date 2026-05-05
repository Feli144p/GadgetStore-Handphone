<?php
session_start();
require_once __DIR__ . '/data.php';

function initializeStore()
{
    if (!isset($_SESSION['products'])) {
        $_SESSION['products'] = $GLOBALS['products'];
    }
    if (!isset($_SESSION['users'])) {
        $_SESSION['users'] = $GLOBALS['users'];
    }
    if (!isset($_SESSION['transactions'])) {
        $_SESSION['transactions'] = [];
    }
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
}

function getProducts()
{
    initializeStore();
    return $_SESSION['products'];
}

function getProduct($id)
{
    $products = getProducts();
    $id = (int)$id;
    return $products[$id] ?? null;
}

function getCategories()
{
    return $GLOBALS['categories'];
}

function filterProducts($query = '', $brand = 'Semua')
{
    $items = getProducts();
    if ($brand !== 'Semua') {
        $items = array_filter($items, function ($product) use ($brand) {
            return strcasecmp($product['brand'], $brand) === 0 || strcasecmp($product['category'], $brand) === 0;
        });
    }
    if ($query !== '') {
        $items = array_filter($items, function ($product) use ($query) {
            return stripos($product['name'], $query) !== false || stripos($product['brand'], $query) !== false || stripos($product['category'], $query) !== false;
        });
    }
    return $items;
}

function formatIDR($value)
{
    return 'Rp' . number_format($value, 0, ',', '.');
}

function addToCart($productId, $quantity = 1)
{
    initializeStore();
    $productId = (int)$productId;
    $quantity = max(1, (int)$quantity);
    $product = getProduct($productId);
    if (!$product || $product['stock'] <= 0) {
        return false;
    }
    $currentQty = $_SESSION['cart'][$productId] ?? 0;
    $newQty = min($currentQty + $quantity, $product['stock']);
    $_SESSION['cart'][$productId] = $newQty;
    return true;
}

function updateCart($quantities)
{
    initializeStore();
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
    initializeStore();
    $items = [];
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
    initializeStore();
    return array_sum($_SESSION['cart']);
}

function getCartTotal()
{
    $total = 0;
    foreach (getCartItems() as $item) {
        $total += $item['subtotal'];
    }
    return $total;
}

function getUser()
{
    return $_SESSION['user'] ?? null;
}

function loginUser($email, $role = null)
{
    initializeStore();
    if ($role === null && isset($_SESSION['users'][$email])) {
        $stored = $_SESSION['users'][$email];
        if (is_array($stored)) {
            $role = $stored['role'] ?? 'buyer';
        } else {
            $role = 'buyer';
        }
    }
    if ($role === null) {
        $role = 'buyer';
    }
    $_SESSION['user'] = [
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
    initializeStore();
    if (!isset($_SESSION['users'][$email])) {
        return false;
    }
    $stored = $_SESSION['users'][$email];
    if (is_array($stored)) {
        return ($stored['role'] ?? '') === 'admin';
    }
    return strtolower($email) === 'admin@admin.com';
}

function isAdmin()
{
    $user = getUser();
    return $user && isset($user['role']) && $user['role'] === 'admin';
}

function registerUser($email, $password, $role = 'buyer')
{
    initializeStore();
    $role = $role === 'admin' ? 'admin' : 'buyer';
    if (isset($_SESSION['users'][$email])) {
        return false;
    }
    $_SESSION['users'][$email] = [
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role
    ];
    loginUser($email, $role);
    return true;
}

function validateUser($email, $password)
{
    initializeStore();
    if (!isset($_SESSION['users'][$email])) {
        return false;
    }
    $stored = $_SESSION['users'][$email];
    $hash = is_array($stored) ? ($stored['password'] ?? '') : $stored;
    return password_verify($password, $hash);
}

function requireLogin()
{
    if (!getUser()) {
        header('Location: login.php');
        exit;
    }
}

function getNextProductId()
{
    $products = getProducts();
    return empty($products) ? 1 : max(array_keys($products)) + 1;
}

function createProduct($data)
{
    initializeStore();
    $id = getNextProductId();
    $_SESSION['products'][$id] = $data;
    return $id;
}

function updateProduct($id, $data)
{
    initializeStore();
    $id = (int)$id;
    if (!isset($_SESSION['products'][$id])) {
        return false;
    }
    $_SESSION['products'][$id] = $data;
    return true;
}

function deleteProduct($id)
{
    initializeStore();
    $id = (int)$id;
    if (isset($_SESSION['products'][$id])) {
        unset($_SESSION['products'][$id]);
        return true;
    }
    return false;
}

function placeOrder($checkoutData)
{
    $user = getUser();
    if (!$user) {
        return false;
    }
    $items = getCartItems();
    if (empty($items)) {
        return false;
    }
    $transaction = [
        'id' => uniqid('ORD'),
        'date' => date('d M Y H:i'),
        'user' => $user['email'],
        'items' => $items,
        'total' => getCartTotal(),
        'payment_method' => $checkoutData['payment_method'] ?? 'N/A',
        'recipient_name' => $checkoutData['recipient_name'] ?? $user['name'],
        'phone' => $checkoutData['phone'] ?? '',
        'address' => $checkoutData['address'] ?? '',
        'city' => $checkoutData['city'] ?? '',
        'postal_code' => $checkoutData['postal_code'] ?? ''
    ];
    $_SESSION['transactions'][] = $transaction;
    foreach ($items as $id => $item) {
        $_SESSION['products'][$id]['stock'] -= $item['quantity'];
    }
    $_SESSION['cart'] = [];
    return $transaction;
}

function getTransactions($userEmail = null)
{
    initializeStore();
    if ($userEmail === null) {
        return $_SESSION['transactions'];
    }
    return array_values(array_filter($_SESSION['transactions'], function ($transaction) use ($userEmail) {
        return isset($transaction['user']) && $transaction['user'] === $userEmail;
    }));
}
