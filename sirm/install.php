<?php
// Jalankan SEKALI untuk membuat user awal, lalu HAPUS file ini dari server.
require __DIR__ . '/includes/bootstrap.php';
$pdo = db();
if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
    exit('Instalasi sudah dilakukan (tabel users tidak kosong). Hapus file install.php.');
}
$poliUmum = (int)$pdo->query("SELECT id FROM poli WHERE nama='Umum'")->fetchColumn();
$seed = [
    ['admin',   'admin123',   'Administrator',   'admin',   null],
    ['dokter1', 'dokter123',  'dr. Budi Santoso','dokter',  $poliUmum ?: null],
    ['petugas1','petugas123', 'Siti Rahma',      'petugas', null],
];
$st = $pdo->prepare('INSERT INTO users (username,password,nama_lengkap,role,poli_id) VALUES (?,?,?,?,?)');
foreach ($seed as [$u,$p,$n,$r,$poli]) $st->execute([$u, password_hash($p, PASSWORD_DEFAULT), $n, $r, $poli]);
echo '<h3>Instalasi selesai.</h3><p>Login: admin/admin123, dokter1/dokter123, petugas1/petugas123.<br>
<b>Segera hapus install.php dan ganti semua password!</b></p><a href="login.php">Ke halaman login</a>';
