<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin');
$pdo = db(); $title = 'Master Obat';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (post('action') === 'tambah') {
            if (post('kode') === '' || post('nama') === '') throw new RuntimeException('Kode dan nama wajib diisi.');
            $pdo->prepare('INSERT INTO obat (kode,nama,satuan,harga,stok) VALUES (?,?,?,?,?)')
                ->execute([post('kode'), post('nama'), post('satuan', 'tablet'), max(0, (float)post('harga')), max(0, (int)post('stok'))]);
            flash('Obat ditambahkan.');
        } else {
            $pdo->prepare('UPDATE obat SET harga=?, stok=? WHERE id=?')->execute([max(0, (float)post('harga')), max(0, (int)post('stok')), (int)post('id')]);
            flash('Obat diperbarui.');
        }
    } catch (PDOException $ex) { flash($ex->getCode() === '23000' ? 'Kode obat sudah ada.' : 'Gagal menyimpan.', 'danger'); }
    catch (RuntimeException $ex) { flash($ex->getMessage(), 'danger'); }
    redirect('obat.php');
}
$rows = $pdo->query('SELECT * FROM obat ORDER BY nama')->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<form method="post" class="row g-2 mb-4 bg-white p-3 rounded shadow-sm"><?= csrf_field() ?><input type="hidden" name="action" value="tambah">
  <div class="col-md-2"><input name="kode" class="form-control" placeholder="Kode" required></div>
  <div class="col-md-3"><input name="nama" class="form-control" placeholder="Nama obat" required></div>
  <div class="col-md-2"><input name="satuan" class="form-control" placeholder="Satuan" value="tablet"></div>
  <div class="col-md-2"><input type="number" name="harga" class="form-control" placeholder="Harga" min="0" required></div>
  <div class="col-md-2"><input type="number" name="stok" class="form-control" placeholder="Stok" min="0" required></div>
  <div class="col-md-1"><button class="btn btn-primary w-100">+</button></div></form>
<div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
<thead class="table-light"><tr><th>Kode</th><th>Nama</th><th>Satuan</th><th>Harga</th><th>Stok</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $o): $fid = 'f' . (int)$o['id']; ?><tr>
<td><form id="<?= $fid ?>" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"></form><?= e($o['kode']) ?></td><td><?= e($o['nama']) ?></td><td><?= e($o['satuan']) ?></td>
<td><input form="<?= $fid ?>" type="number" name="harga" value="<?= e((int)$o['harga']) ?>" class="form-control form-control-sm" style="width:120px"></td>
<td><input form="<?= $fid ?>" type="number" name="stok" value="<?= (int)$o['stok'] ?>" class="form-control form-control-sm" style="width:90px"></td>
<td><button form="<?= $fid ?>" class="btn btn-sm btn-outline-primary">Simpan</button></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
