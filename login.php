<?php
require_once __DIR__ . '/functions.php';
if (getUser()) {
    header('Location: index.php');
    exit;
}
$errorMessage = '';
$selectedRole = 'buyer';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $selectedRole = $_POST['role'] ?? 'buyer';
    if ($email === '' || $password === '') {
        $errorMessage = 'Email dan kata sandi wajib diisi.';
    } elseif ($selectedRole === 'admin') {
        if (validateUser($email, $password) && isUserAdmin($email)) {
            loginUser($email, 'admin');
            header('Location: admin.php');
            exit;
        }
        $errorMessage = 'Akun admin tidak cocok atau tidak tersedia. Pastikan Anda sudah mendaftar sebagai admin.';
    } else {
        if (validateUser($email, $password)) {
            if (isUserAdmin($email)) {
                $errorMessage = 'Gunakan peran admin untuk masuk dengan akun admin.';
            } else {
                loginUser($email, 'buyer');
                header('Location: index.php');
                exit;
            }
        } else {
            $errorMessage = 'Email atau kata sandi tidak cocok.';
        }
    }
}
include __DIR__ . '/header.php';
?>
<div class="page-section auth-page">
    <div class="section-card auth-card">
        <h1>Masuk</h1>
        <p>Gunakan email Anda untuk masuk dan kelola keranjang serta riwayat pesanan.</p>
        <?php if ($errorMessage): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>
        <form method="post" class="auth-form">
            <label>Email</label>
            <input type="email" name="email" placeholder="contoh@mail.com" required>
            <label>Kata Sandi</label>
            <input type="password" name="password" placeholder="Masukkan kata sandi" required>
            <label>Masuk sebagai</label>
            <div class="role-select">
                <label><input type="radio" name="role" value="buyer" <?= $selectedRole === 'buyer' ? 'checked' : '' ?>> Pembeli</label>
                <label><input type="radio" name="role" value="admin" <?= $selectedRole === 'admin' ? 'checked' : '' ?>> Admin</label>
            </div>
            <button type="submit" class="btn-primary">Masuk</button>
        </form>
        <p class="form-note">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
