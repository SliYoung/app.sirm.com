<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'petugas');
$pdo = db(); $title = 'Farmasi - Resep Obat';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rid = (int)post('id');
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT status FROM resep WHERE id=? FOR UPDATE'); $st->execute([$rid]); $status = $st->fetchColumn();
        if ($status === 'baru') {
            $pdo->prepare("UPDATE resep SET status='disiapkan' WHERE id=?")->execute([$rid]);
            $msg = 'Resep ditandai sedang disiapkan.';
        } elseif ($status === 'disiapkan') {
            $items = $pdo->prepare('SELECT obat_id, jumlah FROM resep_detail WHERE resep_id=?'); $items->execute([$rid]);
            $chk = $pdo->prepare('SELECT nama, stok FROM obat WHERE id=? FOR UPDATE');
            $upd = $pdo->prepare('UPDATE obat SET stok = stok - ? WHERE id=?');
            foreach ($items->fetchAll() as $it) {
                $chk->execute([$it['obat_id']]); $o = $chk->fetch();
                if (!$o || $o['stok'] < $it['jumlah']) throw new RuntimeException('Stok ' . ($o['nama'] ?? 'obat') . ' tidak cukup.');
                $upd->execute([$it['jumlah'], $it['obat_id']]);
            }
            $pdo->prepare("UPDATE resep SET status='diserahkan' WHERE id=?")->execute([$rid]);
            $msg = 'Resep diserahkan, stok obat berkurang.';
        } else { throw new RuntimeException('Resep tidak dapat diproses.'); }
        $pdo->commit(); flash($msg);
    } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); flash($ex->getMessage(), 'danger'); }
    redirect('farmasi.php' . (isset($_GET['semua']) ? '?semua=1' : ''));
}

$semua = isset($_GET['semua']);
$list = $pdo->query("SELECT r.id, r.status, r.created_at, p.no_rm, p.nama, rm.id AS rmid
    FROM resep r JOIN rekam_medis rm ON rm.id=r.rekam_medis_id JOIN kunjungan k ON k.id=rm.kunjungan_id JOIN pasien p ON p.id=k.pasien_id
    " . ($semua ? '' : "WHERE r.status<>'diserahkan' ") . "ORDER BY r.id DESC LIMIT 100")->fetchAll();
$det = [];
if ($list) {
    $ids = implode(',', array_map('intval', array_column($list, 'id')));
    foreach ($pdo->query("SELECT rd.resep_id, o.nama, o.satuan, o.stok, rd.jumlah, rd.aturan_pakai FROM resep_detail rd JOIN obat o ON o.id=rd.obat_id WHERE rd.resep_id IN ($ids)") as $d) $det[$d['resep_id']][] = $d;
}
require __DIR__ . '/includes/header.php';
?>
<div class="mb-3"><a class="btn btn-sm <?= $semua?'btn-outline-primary':'btn-primary' ?>" href="farmasi.php">Perlu diproses</a>
<a class="btn btn-sm <?= $semua?'btn-primary':'btn-outline-primary' ?>" href="farmasi.php?semua=1">Semua resep</a></div>
<div class="row g-3">
<?php foreach ($list as $r): ?>
<div class="col-lg-6"><div class="card shadow-sm border-0 h-100"><div class="card-body">
  <div class="d-flex justify-content-between"><div><strong><?= e($r['nama']) ?></strong><div class="small text-muted"><?= e($r['no_rm']) ?> · <?= e($r['created_at']) ?></div></div><?= badge($r['status']) ?></div>
  <ul class="mt-2 mb-3">
    <?php foreach ($det[$r['id']] ?? [] as $d): ?><li><?= e($d['nama']) ?> × <?= (int)$d['jumlah'] ?> <?= e($d['satuan']) ?> — <em><?= e($d['aturan_pakai']) ?></em>
      <?php if ($d['stok'] < $d['jumlah'] && $r['status'] !== 'diserahkan'): ?><span class="badge bg-danger">stok kurang (<?= (int)$d['stok'] ?>)</span><?php endif; ?></li><?php endforeach; ?></ul>
  <?php if ($r['status'] !== 'diserahkan'): ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <button class="btn btn-sm btn-primary"><?= $r['status']==='baru' ? 'Mulai Siapkan' : 'Serahkan ke Pasien' ?></button></form>
  <?php endif; ?>
</div></div></div>
<?php endforeach; if (!$list): ?><div class="col-12 text-muted">Tidak ada resep.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
