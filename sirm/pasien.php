<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'dokter', 'petugas');
$title = 'Data Pasien';
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $st = db()->prepare('SELECT * FROM pasien WHERE nama LIKE ? OR no_rm LIKE ? ORDER BY id DESC LIMIT 100');
    $st->execute(["%$q%", "%$q%"]);
} else {
    $st = db()->query('SELECT * FROM pasien ORDER BY id DESC LIMIT 50');
}
$rows = $st->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <form class="d-flex flex-grow-1" method="get">
    <input name="q" value="<?= e($q) ?>" class="form-control me-2" placeholder="Cari nama atau No. Rekam Medis...">
    <button class="btn btn-outline-primary">Cari</button>
  </form>
  <?php if (user()['role'] !== 'dokter'): ?><a href="pasien_tambah.php" class="btn btn-primary">+ Pasien Baru</a><?php endif; ?>
</div>
<div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
<thead class="table-light"><tr><th>No. RM</th><th>Nama</th><th>NIK</th><th>Umur</th><th>Gol. Darah</th><th>Telp</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><?= e($r['no_rm']) ?></td><td><?= e($r['nama']) ?></td><td><?= e($r['nik']) ?></td>
<td><?= umur($r['tanggal_lahir']) ?> th</td><td><?= e($r['golongan_darah']) ?></td><td><?= e($r['no_telp']) ?></td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="pasien_detail.php?id=<?= (int)$r['id'] ?>">Detail & Riwayat</a></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">Data tidak ditemukan.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
