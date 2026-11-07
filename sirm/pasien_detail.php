<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'dokter', 'petugas');
$pdo = db();
$st = $pdo->prepare('SELECT * FROM pasien WHERE id=?'); $st->execute([(int)($_GET['id'] ?? 0)]);
$p = $st->fetch();
if (!$p) { flash('Pasien tidak ditemukan.', 'danger'); redirect('pasien.php'); }
$title = 'Detail Pasien';

$st = $pdo->prepare("SELECT k.id, k.tanggal, k.status, po.nama AS poli, d.nama_lengkap AS dokter,
        rm.keluhan, rm.diagnosa, rm.kode_icd10, rm.tindakan, rm.catatan
    FROM kunjungan k JOIN poli po ON po.id=k.poli_id
    LEFT JOIN users d ON d.id=k.dokter_id
    LEFT JOIN rekam_medis rm ON rm.kunjungan_id=k.id
    WHERE k.pasien_id=? AND k.status<>'batal' ORDER BY k.tanggal DESC, k.id DESC");
$st->execute([$p['id']]);
$riwayat = $st->fetchAll();

$rs = $pdo->prepare("SELECT k.id AS kid, o.nama, rd.jumlah, rd.aturan_pakai FROM resep_detail rd
    JOIN resep r ON r.id=rd.resep_id JOIN rekam_medis rm ON rm.id=r.rekam_medis_id
    JOIN kunjungan k ON k.id=rm.kunjungan_id JOIN obat o ON o.id=rd.obat_id WHERE k.pasien_id=?");
$rs->execute([$p['id']]);
$obatPer = [];
foreach ($rs->fetchAll() as $r) $obatPer[$r['kid']][] = $r;

require __DIR__ . '/includes/header.php';
?>
<div class="row g-3">
  <div class="col-lg-4"><div class="card shadow-sm border-0"><div class="card-body">
    <h5><?= e($p['nama']) ?></h5><div class="text-primary fw-semibold mb-2"><?= e($p['no_rm']) ?></div>
    <dl class="row mb-0 small">
      <dt class="col-5">NIK</dt><dd class="col-7"><?= e($p['nik']) ?></dd>
      <dt class="col-5">Jenis Kelamin</dt><dd class="col-7"><?= $p['jenis_kelamin']==='L'?'Laki-laki':'Perempuan' ?></dd>
      <dt class="col-5">Tgl Lahir</dt><dd class="col-7"><?= tgl($p['tanggal_lahir']) ?> (<?= umur($p['tanggal_lahir']) ?> th)</dd>
      <dt class="col-5">Gol. Darah</dt><dd class="col-7"><?= e($p['golongan_darah']) ?></dd>
      <dt class="col-5">Telp</dt><dd class="col-7"><?= e($p['no_telp'] ?: '-') ?></dd>
      <dt class="col-5">Alamat</dt><dd class="col-7"><?= nl2br(e($p['alamat'])) ?></dd>
    </dl>
    <?php if (user()['role'] !== 'dokter'): ?><a class="btn btn-primary w-100 mt-3" href="kunjungan.php?no_rm=<?= urlencode($p['no_rm']) ?>">Daftarkan Kunjungan</a><?php endif; ?>
  </div></div></div>
  <div class="col-lg-8">
    <h6 class="mb-3">Riwayat Kunjungan</h6>
    <?php foreach ($riwayat as $r): ?>
    <div class="card shadow-sm border-0 mb-3"><div class="card-body">
      <div class="d-flex justify-content-between"><strong><?= tgl($r['tanggal']) ?> · Poli <?= e($r['poli']) ?></strong><?= badge($r['status']) ?></div>
      <div class="text-muted small mb-2">Dokter: <?= e($r['dokter'] ?: '-') ?></div>
      <?php if ($r['diagnosa'] !== null): ?>
        <div><b>Keluhan:</b> <?= e($r['keluhan']) ?></div>
        <div><b>Diagnosa:</b> <?= e($r['diagnosa']) ?> <?= $r['kode_icd10'] ? '(' . e($r['kode_icd10']) . ')' : '' ?></div>
        <?php if ($r['tindakan']): ?><div><b>Tindakan:</b> <?= e($r['tindakan']) ?></div><?php endif; ?>
        <?php if ($r['catatan']): ?><div><b>Catatan:</b> <?= e($r['catatan']) ?></div><?php endif; ?>
        <?php if (!empty($obatPer[$r['id']])): ?><div><b>Resep:</b><ul class="mb-0">
          <?php foreach ($obatPer[$r['id']] as $o): ?><li><?= e($o['nama']) ?> × <?= (int)$o['jumlah'] ?> — <?= e($o['aturan_pakai']) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <?php else: ?><div class="text-muted">Belum ada hasil pemeriksaan.</div><?php endif; ?>
    </div></div>
    <?php endforeach; if (!$riwayat): ?><div class="text-muted">Belum ada riwayat kunjungan.</div><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
