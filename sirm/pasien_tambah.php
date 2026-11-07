<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'petugas');
$title = 'Pendaftaran Pasien Baru';
$errors = [];
$f = ['nik'=>'','nama'=>'','jenis_kelamin'=>'L','tanggal_lahir'=>'','alamat'=>'','no_telp'=>'','golongan_darah'=>'-'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($f as $k => $_) $f[$k] = post($k, $f[$k]);
    if (!preg_match('/^\d{16}$/', $f['nik'])) $errors[] = 'NIK harus 16 digit angka.';
    if ($f['nama'] === '') $errors[] = 'Nama wajib diisi.';
    if (!in_array($f['jenis_kelamin'], ['L','P'], true)) $errors[] = 'Jenis kelamin tidak valid.';
    if (!in_array($f['golongan_darah'], ['A','B','AB','O','-'], true)) $errors[] = 'Golongan darah tidak valid.';
    $d = DateTime::createFromFormat('Y-m-d', $f['tanggal_lahir']);
    if (!$d || $d > new DateTime('today')) $errors[] = 'Tanggal lahir tidak valid.';
    if ($f['alamat'] === '') $errors[] = 'Alamat wajib diisi.';
    if ($f['no_telp'] !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $f['no_telp'])) $errors[] = 'No. telp tidak valid.';

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO pasien (no_rm,nik,nama,jenis_kelamin,tanggal_lahir,alamat,no_telp,golongan_darah) VALUES (?,?,?,?,?,?,?,?)')
                ->execute(['TMP' . bin2hex(random_bytes(6)), $f['nik'], $f['nama'], $f['jenis_kelamin'], $f['tanggal_lahir'], $f['alamat'], $f['no_telp'] ?: null, $f['golongan_darah']]);
            $id = (int)$pdo->lastInsertId();
            $noRm = 'RM-' . date('Y') . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE pasien SET no_rm=? WHERE id=?')->execute([$noRm, $id]);
            $pdo->commit();
            flash("Pasien terdaftar dengan No. RM $noRm");
            redirect('pasien_detail.php?id=' . $id);
        } catch (PDOException $ex) {
            $pdo->rollBack();
            $errors[] = $ex->getCode() === '23000' ? 'NIK sudah terdaftar.' : 'Gagal menyimpan data.';
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<div class="card shadow-sm border-0" style="max-width:800px"><div class="card-body">
<form method="post" class="row g-3">
  <?= csrf_field() ?>
  <div class="col-md-6"><label class="form-label">NIK</label><input name="nik" maxlength="16" class="form-control" value="<?= e($f['nik']) ?>" required></div>
  <div class="col-md-6"><label class="form-label">Nama Lengkap</label><input name="nama" class="form-control" value="<?= e($f['nama']) ?>" required></div>
  <div class="col-md-4"><label class="form-label">Jenis Kelamin</label>
    <select name="jenis_kelamin" class="form-select"><option value="L" <?= $f['jenis_kelamin']==='L'?'selected':'' ?>>Laki-laki</option><option value="P" <?= $f['jenis_kelamin']==='P'?'selected':'' ?>>Perempuan</option></select></div>
  <div class="col-md-4"><label class="form-label">Tanggal Lahir</label><input type="date" name="tanggal_lahir" class="form-control" value="<?= e($f['tanggal_lahir']) ?>" required></div>
  <div class="col-md-4"><label class="form-label">Golongan Darah</label>
    <select name="golongan_darah" class="form-select"><?php foreach (['-','A','B','AB','O'] as $g): ?><option <?= $f['golongan_darah']===$g?'selected':'' ?>><?= $g ?></option><?php endforeach; ?></select></div>
  <div class="col-12"><label class="form-label">Alamat</label><textarea name="alamat" rows="2" class="form-control" required><?= e($f['alamat']) ?></textarea></div>
  <div class="col-md-6"><label class="form-label">No. Telepon</label><input name="no_telp" class="form-control" value="<?= e($f['no_telp']) ?>"></div>
  <div class="col-12"><button class="btn btn-primary">Simpan</button> <a href="pasien.php" class="btn btn-light">Batal</a></div>
</form></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
