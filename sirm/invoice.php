<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'petugas');
$pdo = db();
$st = $pdo->prepare("SELECT b.*, p.nama, p.no_rm, po.nama AS poli, rm.tindakan, rm.id AS rmid, kasir.nama_lengkap AS kasir, d.nama_lengkap AS dokter
    FROM pembayaran b JOIN kunjungan k ON k.id=b.kunjungan_id JOIN pasien p ON p.id=k.pasien_id JOIN poli po ON po.id=k.poli_id
    LEFT JOIN rekam_medis rm ON rm.kunjungan_id=k.id LEFT JOIN users kasir ON kasir.id=b.kasir_id LEFT JOIN users d ON d.id=k.dokter_id WHERE b.id=?");
$st->execute([(int)($_GET['id'] ?? 0)]); $b = $st->fetch();
if (!$b) { flash('Invoice tidak ditemukan.', 'danger'); redirect('kasir.php'); }
$obat = [];
if ($b['rmid']) {
    $s = $pdo->prepare('SELECT o.nama, rd.jumlah, rd.harga_satuan FROM resep_detail rd JOIN resep r ON r.id=rd.resep_id JOIN obat o ON o.id=rd.obat_id WHERE r.rekam_medis_id=?');
    $s->execute([$b['rmid']]); $obat = $s->fetchAll();
}
$title = 'Invoice ' . $b['no_invoice'];
require __DIR__ . '/includes/header.php';
?>
<div class="card shadow-sm border-0 mx-auto" style="max-width:480px"><div class="card-body">
  <div class="text-center mb-3"><h5 class="mb-0"><?= e(APP_NAME) ?></h5><small class="text-muted">STRUK PEMBAYARAN</small></div>
  <div class="small mb-3">
    <div>No. Invoice: <b><?= e($b['no_invoice']) ?></b></div>
    <div>Pasien: <?= e($b['nama']) ?> (<?= e($b['no_rm']) ?>)</div>
    <div>Poli: <?= e($b['poli']) ?> · Dokter: <?= e($b['dokter'] ?: '-') ?></div>
    <div>Status: <?= badge($b['status']) ?> <?= $b['dibayar_pada'] ? '· ' . e($b['dibayar_pada']) . ' · ' . e(strtoupper((string)$b['metode'])) : '' ?></div>
  </div>
  <table class="table table-sm">
    <tr><td>Jasa layanan poli</td><td class="text-end"><?= rupiah($b['biaya_layanan']) ?></td></tr>
    <tr><td>Tindakan<?= $b['tindakan'] ? ': ' . e($b['tindakan']) : '' ?></td><td class="text-end"><?= rupiah($b['biaya_tindakan']) ?></td></tr>
    <?php foreach ($obat as $o): ?><tr><td class="ps-3 small"><?= e($o['nama']) ?> × <?= (int)$o['jumlah'] ?> @ <?= rupiah($o['harga_satuan']) ?></td><td class="text-end small"><?= rupiah($o['harga_satuan'] * $o['jumlah']) ?></td></tr><?php endforeach; ?>
    <tr><td>Obat (subtotal)</td><td class="text-end"><?= rupiah($b['biaya_obat']) ?></td></tr>
    <tr class="table-light fw-bold"><td>TOTAL</td><td class="text-end"><?= rupiah($b['total']) ?></td></tr>
  </table>
  <div class="small text-muted">Kasir: <?= e($b['kasir'] ?: '-') ?></div>
  <button class="btn btn-primary w-100 mt-3 d-print-none" onclick="window.print()">🖨 Cetak Struk</button>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
