<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'petugas');
$pdo = db(); $title = 'Kasir - Pembayaran';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metode = post('metode');
    if (!in_array($metode, ['tunai','transfer','qris','bpjs'], true)) { flash('Metode pembayaran tidak valid.', 'danger'); redirect('kasir.php'); }
    $id = (int)post('id');
    $st = $pdo->prepare("UPDATE pembayaran SET status='lunas', metode=?, dibayar_pada=NOW(), kasir_id=? WHERE id=? AND status='belum'");
    $st->execute([$metode, user()['id'], $id]);
    if ($st->rowCount()) { flash('Pembayaran berhasil.'); redirect('invoice.php?id=' . $id); }
    flash('Tagihan tidak ditemukan atau sudah lunas.', 'warning'); redirect('kasir.php');
}

$lunas = isset($_GET['lunas']);
$list = $pdo->query("SELECT b.*, p.nama, p.no_rm FROM pembayaran b JOIN kunjungan k ON k.id=b.kunjungan_id JOIN pasien p ON p.id=k.pasien_id
    WHERE b.status='" . ($lunas ? 'lunas' : 'belum') . "' ORDER BY b.id DESC LIMIT 100")->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<div class="mb-3"><a class="btn btn-sm <?= $lunas?'btn-outline-primary':'btn-primary' ?>" href="kasir.php">Belum dibayar</a>
<a class="btn btn-sm <?= $lunas?'btn-primary':'btn-outline-primary' ?>" href="kasir.php?lunas=1">Sudah lunas</a></div>
<div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
<thead class="table-light"><tr><th>Invoice</th><th>Pasien</th><th class="text-end">Layanan</th><th class="text-end">Tindakan</th><th class="text-end">Obat</th><th class="text-end">Total</th><th></th></tr></thead><tbody>
<?php foreach ($list as $b): ?>
<tr><td><?= e($b['no_invoice']) ?></td><td><?= e($b['nama']) ?><div class="small text-muted"><?= e($b['no_rm']) ?></div></td>
<td class="text-end"><?= rupiah($b['biaya_layanan']) ?></td><td class="text-end"><?= rupiah($b['biaya_tindakan']) ?></td><td class="text-end"><?= rupiah($b['biaya_obat']) ?></td>
<td class="text-end fw-bold"><?= rupiah($b['total']) ?></td>
<td class="text-end"><?php if ($b['status']==='belum'): ?>
  <form method="post" class="d-flex gap-1 justify-content-end"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
    <select name="metode" class="form-select form-select-sm w-auto"><option value="tunai">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option><option value="bpjs">BPJS</option></select>
    <button class="btn btn-sm btn-success">Bayar</button></form>
<?php else: ?><a class="btn btn-sm btn-outline-primary" href="invoice.php?id=<?= (int)$b['id'] ?>">Struk</a><?php endif; ?></td></tr>
<?php endforeach; if (!$list): ?><tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
