<?php
$u = user(); $curPage = basename($_SERVER['PHP_SELF']);
$navMenu = [
  ['dashboard.php','Dashboard',['admin','dokter','petugas']],
  ['pasien.php','Pasien',['admin','dokter','petugas']],
  ['kunjungan.php','Pendaftaran',['admin','petugas']],
  ['periksa.php','Pemeriksaan',['dokter']],
  ['farmasi.php','Farmasi',['admin','petugas']],
  ['kasir.php','Kasir',['admin','petugas']],
  ['obat.php','Obat',['admin']],
  ['poli.php','Poli',['admin']],
  ['users.php','User',['admin']],
];
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'SIRM') ?> - <?= e(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/app.css" rel="stylesheet">
</head>
<body>
<div class="app-frame">
<nav class="navbar navbar-expand-xl topbar d-print-none">
  <div class="container-fluid px-0">
    <a class="brand" href="dashboard.php">SIRM</a>
    <button class="navbar-toggler border-0" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div id="nav" class="collapse navbar-collapse">
      <ul class="navbar-nav mx-auto gap-1 my-2 my-xl-0">
        <?php foreach ($navMenu as [$navFile, $navLabel, $navRoles]): if (!in_array($u['role'], $navRoles, true)) continue; ?>
          <li class="nav-item"><a class="nav-link <?= $curPage === $navFile ? 'active' : '' ?>" href="<?= $navFile ?>"><?= e($navLabel) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="user-chip">
        <?= avatar($u['nama']) ?>
        <div><b class="d-block small"><?= e($u['nama']) ?></b><small><?= e(ucfirst($u['role'])) ?></small></div>
        <a class="btn btn-sm btn-outline-primary ms-2" href="logout.php">Keluar</a>
      </div>
    </div>
  </div>
</nav>
<main class="page-main">
<?php $flashMsg = flash(); if ($flashMsg): ?>
  <div class="alert alert-<?= e($flashMsg[1]) ?> alert-dismissible fade show"><?= e($flashMsg[0]) ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (empty($hideTitle)): ?><h4 class="page-title mb-3 d-print-none"><?= e($title ?? '') ?></h4><?php endif; ?>
