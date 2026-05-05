<?php
require_once __DIR__ . '/functions.php';
if (getUser()) {
    header('Location: index.php');
    exit;
}
$selectedRole = 'buyer';
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $selectedRole = $_POST['role'] ?? 'buyer';

    if ($email === '' || $password === '' || $confirm === '') {
        $errorMessage = 'Semua kolom wajib diisi.';
    } elseif ($password !== $confirm) {
        $errorMessage = 'Kata sandi dan konfirmasi tidak cocok.';
    } elseif (!registerUser($email, $password, $selectedRole)) {
        $errorMessage = 'Email sudah terdaftar. Silakan gunakan email lain atau login.';
    } else {
        header('Location: index.php');
        exit;
    }
}
include __DIR__ . '/header.php';
?>
<div class="page-section auth-page">
    <div class="section-card auth-card">
        <h1>Daftar Akun</h1>
        <p>Buat akun baru untuk menyimpan riwayat transaksi dan memudahkan checkout.</p>
        <?php if ($errorMessage): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>
        <form method="post" class="auth-form">
            <label>Email</label>
            <input type="email" name="email" placeholder="contoh@mail.com" required>
            <label>Kata Sandi</label>
            <input type="password" name="password" placeholder="Buat kata sandi" required>
            <label>Konfirmasi Kata Sandi</label>
            <input type="password" name="confirm_password" placeholder="Ulangi kata sandi" required>
            <label>Daftar sebagai</label>
            <div class="role-select">
                <label><input type="radio" name="role" value="buyer" <?= $selectedRole === 'buyer' ? 'checked' : '' ?>> Pembeli</label>
                <label><input type="radio" name="role" value="admin" <?= $selectedRole === 'admin' ? 'checked' : '' ?>> Admin</label>
            </div>
            <button type="submit" class="btn-primary">Daftar</button>
        </form>
        <p class="form-note">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
