<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin');
$pdo = db(); $title = 'Manajemen User';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $a = post('action');
        if ($a === 'tambah') {
            $role = post('role'); $poli = (int)post('poli_id') ?: null;
            if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', post('username'))) throw new RuntimeException('Username 3-30 karakter (huruf, angka, _ .).');
            if (strlen((string)($_POST['password'] ?? '')) < 8) throw new RuntimeException('Password minimal 8 karakter.');
            if (!in_array($role, ['admin','dokter','petugas'], true)) throw new RuntimeException('Role tidak valid.');
            if ($role === 'dokter' && !$poli) throw new RuntimeException('Dokter wajib memilih poli.');
            $pdo->prepare('INSERT INTO users (username,password,nama_lengkap,role,poli_id) VALUES (?,?,?,?,?)')
                ->execute([post('username'), password_hash((string)$_POST['password'], PASSWORD_DEFAULT), post('nama_lengkap'), $role, $role === 'dokter' ? $poli : null]);
            flash('User ditambahkan.');
        } elseif ($a === 'toggle') {
            if ((int)post('id') === user()['id']) throw new RuntimeException('Tidak dapat menonaktifkan akun sendiri.');
            $pdo->prepare('UPDATE users SET aktif = 1 - aktif WHERE id=?')->execute([(int)post('id')]);
            flash('Status user diubah.');
        } elseif ($a === 'reset') {
            if (strlen((string)($_POST['password'] ?? '')) < 8) throw new RuntimeException('Password minimal 8 karakter.');
            $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash((string)$_POST['password'], PASSWORD_DEFAULT), (int)post('id')]);
            flash('Password diubah.');
        }
    } catch (PDOException $ex) { flash($ex->getCode() === '23000' ? 'Username sudah dipakai.' : 'Gagal menyimpan.', 'danger'); }
    catch (RuntimeException $ex) { flash($ex->getMessage(), 'danger'); }
    redirect('users.php');
}
$polis = $pdo->query('SELECT id,nama FROM poli WHERE aktif=1 ORDER BY nama')->fetchAll();
$rows = $pdo->query('SELECT u.*, p.nama AS poli FROM users u LEFT JOIN poli p ON p.id=u.poli_id ORDER BY u.id')->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<form method="post" class="row g-2 mb-4 bg-white p-3 rounded shadow-sm" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="tambah">
  <div class="col-md-2"><input name="username" class="form-control" placeholder="Username" required></div>
  <div class="col-md-3"><input name="nama_lengkap" class="form-control" placeholder="Nama lengkap" required></div>
  <div class="col-md-2"><input type="password" name="password" class="form-control" placeholder="Password (min 8)" required></div>
  <div class="col-md-2"><select name="role" class="form-select"><option value="petugas">Petugas</option><option value="dokter">Dokter</option><option value="admin">Admin</option></select></div>
  <div class="col-md-2"><select name="poli_id" class="form-select"><option value="">Poli (khusus dokter)</option><?php foreach ($polis as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['nama']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-1"><button class="btn btn-primary w-100">+</button></div></form>
<div class="card shadow-sm border-0"><div class="table-responsive"><table class="table mb-0 align-middle">
<thead class="table-light"><tr><th>Username</th><th>Nama</th><th>Role</th><th>Poli</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['username']) ?></td><td><?= e($r['nama_lengkap']) ?></td><td><?= e($r['role']) ?></td><td><?= e($r['poli'] ?: '-') ?></td>
<td><?= $r['aktif'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' ?></td>
<td class="d-flex gap-1">
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-secondary"><?= $r['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
  <form method="post" class="d-flex gap-1" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <input type="password" name="password" class="form-control form-control-sm" placeholder="Password baru" style="width:140px"><button class="btn btn-sm btn-outline-primary">Reset</button></form>
</td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
