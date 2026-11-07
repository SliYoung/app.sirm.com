<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('dokter');
$pdo = db(); $u = user(); $title = 'Antrean Pemeriksaan';

if (!$u['poli_id']) { require __DIR__ . '/includes/header.php'; echo '<div class="alert alert-warning">Akun dokter belum dikaitkan dengan poli. Hubungi admin.</div>'; require __DIR__ . '/includes/footer.php'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    $st = $pdo->prepare("UPDATE kunjungan SET status='diperiksa', dokter_id=? WHERE id=? AND poli_id=? AND tanggal=CURDATE() AND status='menunggu'");
    $st->execute([$u['id'], $id, $u['poli_id']]);
    if ($st->rowCount()) redirect('periksa_form.php?id=' . $id);
    flash('Antrean tidak dapat dipanggil.', 'warning'); redirect('periksa.php');
}

$st = $pdo->prepare("SELECT k.id,k.no_antrean,k.status,p.no_rm,p.nama,p.tanggal_lahir
    FROM kunjungan k JOIN pasien p ON p.id=k.pasien_id
    WHERE k.poli_id=? AND k.tanggal=CURDATE() AND k.status<>'batal'
    ORDER BY FIELD(k.status,'diperiksa','menunggu','selesai'), k.no_antrean");
$st->execute([$u['poli_id']]);
$rows = $st->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
<thead class="table-light"><tr><th>No.</th><th>No. RM</th><th>Pasien</th><th>Umur</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td class="fw-bold"><?= (int)$r['no_antrean'] ?></td><td><?= e($r['no_rm']) ?></td><td><?= e($r['nama']) ?></td><td><?= umur($r['tanggal_lahir']) ?> th</td><td><?= badge($r['status']) ?></td>
<td class="text-end">
<?php if ($r['status']==='menunggu'): ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-primary">Mulai Periksa</button></form>
<?php else: ?>
  <a class="btn btn-sm btn-outline-primary" href="periksa_form.php?id=<?= (int)$r['id'] ?>"><?= $r['status']==='diperiksa'?'Lanjutkan':'Lihat / Ubah' ?></a>
<?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada antrean untuk poli Anda.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
