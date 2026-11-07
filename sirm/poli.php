<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin');
$pdo = db(); $title = 'Master Poli';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (post('action') === 'tambah') {
            if (post('nama') === '') throw new RuntimeException('Nama poli wajib diisi.');
            $pdo->prepare('INSERT INTO poli (nama,tarif) VALUES (?,?)')->execute([post('nama'), max(0, (float)post('tarif'))]);
        } else {
            $pdo->prepare('UPDATE poli SET tarif=?, aktif=? WHERE id=?')->execute([max(0, (float)post('tarif')), isset($_POST['aktif']) ? 1 : 0, (int)post('id')]);
        }
        flash('Data poli tersimpan.');
    } catch (PDOException $ex) { flash($ex->getCode() === '23000' ? 'Nama poli sudah ada.' : 'Gagal menyimpan.', 'danger'); }
    catch (RuntimeException $ex) { flash($ex->getMessage(), 'danger'); }
    redirect('poli.php');
}
$rows = $pdo->query('SELECT * FROM poli ORDER BY nama')->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<form method="post" class="row g-2 mb-4 bg-white p-3 rounded shadow-sm" style="max-width:600px"><?= csrf_field() ?><input type="hidden" name="action" value="tambah">
  <div class="col-5"><input name="nama" class="form-control" placeholder="Nama poli" required></div>
  <div class="col-5"><input type="number" name="tarif" class="form-control" placeholder="Tarif" min="0" required></div>
  <div class="col-2"><button class="btn btn-primary w-100">+</button></div></form>
<div class="card shadow-sm border-0" style="max-width:600px"><table class="table mb-0 align-middle">
<thead class="table-light"><tr><th>Poli</th><th>Tarif</th><th>Aktif</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $p): $fid = 'f' . (int)$p['id']; ?><tr>
<td><form id="<?= $fid ?>" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"></form><?= e($p['nama']) ?></td>
<td><input form="<?= $fid ?>" type="number" name="tarif" value="<?= e((int)$p['tarif']) ?>" class="form-control form-control-sm" style="width:130px"></td>
<td><input form="<?= $fid ?>" type="checkbox" name="aktif" class="form-check-input" <?= $p['aktif'] ? 'checked' : '' ?>></td>
<td><button form="<?= $fid ?>" class="btn btn-sm btn-outline-primary">Simpan</button></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
