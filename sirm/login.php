<?php
require __DIR__ . '/includes/bootstrap.php';
if (user()) redirect('dashboard.php');

$error = '';
$_SESSION['fail'] = $_SESSION['fail'] ?? 0;
$_SESSION['lock'] = $_SESSION['lock'] ?? 0;

if (time() < $_SESSION['lock']) {
    $error = 'Terlalu banyak percobaan. Coba lagi dalam 1 menit.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (attempt_login(post('username'), (string)($_POST['password'] ?? ''))) {
        $_SESSION['fail'] = 0;
        redirect('dashboard.php');
    }
    if (++$_SESSION['fail'] >= 5) { $_SESSION['lock'] = time() + 60; $_SESSION['fail'] = 0; }
    $error = 'Username atau password salah.';
}
?><!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - <?= e(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/app.css" rel="stylesheet"></head>
<body class="login-body">
<div class="container" style="max-width:420px">
  <div class="login-card">
    <div>
      <h3 class="text-center fw-bold mb-1">SIRM</h3>
      <p class="text-center text-muted mb-4"><?= e(APP_NAME) ?> · Sistem Informasi Rekam Medis</p>
      <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" required autofocus></div>
        <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
        <button class="btn btn-primary w-100">Masuk</button>
      </form>
    </div>
  </div>
  <p class="text-center small text-muted mt-3 mb-0">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; Dibuat oleh <b><?= e(APP_AUTHOR) ?></b></p>
</div></body></html>
