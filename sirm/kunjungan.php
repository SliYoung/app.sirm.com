<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'petugas');
$pdo = db(); $title = 'Pendaftaran & Antrean';
$noRm = trim($_GET['no_rm'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'daftar') {
        $noRm = post('no_rm'); $poliId = (int)post('poli_id');
        $st = $pdo->prepare('SELECT id FROM pasien WHERE no_rm=?'); $st->execute([$noRm]); $pid = (int)$st->fetchColumn();
        $st = $pdo->prepare('SELECT id FROM poli WHERE id=? AND aktif=1'); $st->execute([$poliId]);
        if (!$pid) flash('No. RM tidak ditemukan.', 'danger');
        elseif (!$st->fetchColumn()) flash('Poli tidak valid.', 'danger');
        else {
            $st = $pdo->prepare("SELECT COUNT(*) FROM kunjungan WHERE pasien_id=? AND poli_id=? AND tanggal=CURDATE() AND status IN ('menunggu','diperiksa')");
            $st->execute([$pid, $poliId]);
            if ($st->fetchColumn() > 0) { flash('Pasien sudah terdaftar di poli ini dan masih aktif.', 'warning'); redirect('kunjungan.php'); }
            try {
                $pdo->beginTransaction();
                $st = $pdo->prepare('SELECT COALESCE(MAX(no_antrean),0)+1 FROM kunjungan WHERE poli_id=? AND tanggal=CURDATE() FOR UPDATE');
                $st->execute([$poliId]); $no = (int)$st->fetchColumn();
                $pdo->prepare('INSERT INTO kunjungan (pasien_id,poli_id,tanggal,no_antrean,created_by) VALUES (?,?,CURDATE(),?,?)')
                    ->execute([$pid, $poliId, $no, user()['id']]);
                $pdo->commit();
                flash("Pendaftaran berhasil. Nomor antrean: $no");
                redirect('kunjungan.php');
            } catch (PDOException $ex) { $pdo->rollBack(); flash('Gagal mendaftar, coba lagi.', 'danger'); redirect('kunjungan.php'); }
        }
    } elseif ($action === 'batal') {
        $pdo->prepare("UPDATE kunjungan SET status='batal' WHERE id=? AND status='menunggu'")->execute([(int)post('id')]);
        flash('Antrean dibatalkan.', 'warning'); redirect('kunjungan.php');
    }
}

$polis = $pdo->query('SELECT id,nama,tarif FROM poli WHERE aktif=1 ORDER BY nama')->fetchAll();
$antrean = $pdo->query("SELECT k.id,k.no_antrean,k.status,p.no_rm,p.nama,po.nama AS poli
    FROM kunjungan k JOIN pasien p ON p.id=k.pasien_id JOIN poli po ON po.id=k.poli_id
    WHERE k.tanggal=CURDATE() ORDER BY po.nama,k.no_antrean")->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3">
<div class="col-lg-4"><div class="card shadow-sm border-0"><div class="card-body">
  <h6>Daftarkan Kunjungan</h6>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="daftar">
    <div class="mb-3"><label class="form-label">No. Rekam Medis</label><input name="no_rm" class="form-control" value="<?= e($noRm) ?>" placeholder="RM-2026-000001" required></div>
    <div class="mb-3"><label class="form-label">Poli Tujuan</label><select name="poli_id" class="form-select" required>
      <?php foreach ($polis as $po): ?><option value="<?= (int)$po['id'] ?>"><?= e($po['nama']) ?> (<?= rupiah($po['tarif']) ?>)</option><?php endforeach; ?></select></div>
    <button class="btn btn-primary w-100">Daftar & Ambil Antrean</button>
  </form>
  <a href="pasien_tambah.php" class="btn btn-link px-0 mt-2">Pasien belum terdaftar? Daftar baru</a>
</div></div></div>
<div class="col-lg-8"><div class="card shadow-sm border-0"><div class="card-header bg-white fw-semibold">Antrean Hari Ini</div>
<div class="table-responsive"><table class="table table-hover mb-0 align-middle">
<thead class="table-light"><tr><th>Poli</th><th>No.</th><th>No. RM</th><th>Pasien</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($antrean as $a): ?>
<tr><td><?= e($a['poli']) ?></td><td><?= e($a['poli'][0] . str_pad((string)$a['no_antrean'],3,'0',STR_PAD_LEFT)) ?></td><td><?= e($a['no_rm']) ?></td><td><?= e($a['nama']) ?></td><td><?= badge($a['status']) ?></td>
<td class="text-end"><?php if ($a['status']==='menunggu'): ?><form method="post" onsubmit="return confirm('Batalkan antrean ini?')"><?= csrf_field() ?><input type="hidden" name="action" value="batal"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-outline-danger">Batal</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$antrean): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada antrean.</td></tr><?php endif; ?>
</tbody></table></div></div></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
