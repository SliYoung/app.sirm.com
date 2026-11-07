<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
$pdo = db(); $u = user();
$title = 'Dashboard'; $hideTitle = true;
$isDokter = $u['role'] === 'dokter';
$pid = $isDokter ? (int)$u['poli_id'] : 0;
$polFilter = $isDokter ? ' AND k.poli_id = ' . $pid : '';

// ---- Data ringkasan ----
$totalPasien = (int)$pdo->query('SELECT COUNT(*) FROM pasien')->fetchColumn();
$pendapatan  = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM pembayaran WHERE status='lunas' AND DATE(dibayar_pada)=CURDATE()")->fetchColumn();

$sc = $pdo->query("SELECT COALESCE(SUM(status='selesai'),0) s, COALESCE(SUM(status='diperiksa'),0) d, COALESCE(SUM(status='menunggu'),0) m
    FROM kunjungan k WHERE tanggal=CURDATE() AND status<>'batal'$polFilter")->fetch();
$sel = (int)$sc['s']; $dip = (int)$sc['d']; $mnt = (int)$sc['m'];
$tot = $sel + $dip + $mnt;
$pct = fn(int $n): int => $tot ? (int)round($n / $tot * 100) : 0;

// Kartu poli (seperti "Today Task")
$polis = $pdo->query("SELECT po.id, po.nama,
        COUNT(k.id) - COALESCE(SUM(k.status='batal'),0) AS total,
        COALESCE(SUM(k.status='selesai'),0) AS selesai,
        COALESCE(SUM(k.status='menunggu'),0) AS menunggu
    FROM poli po LEFT JOIN kunjungan k ON k.poli_id=po.id AND k.tanggal=CURDATE()
    WHERE po.aktif=1" . ($isDokter ? " AND po.id=$pid" : '') . " GROUP BY po.id, po.nama ORDER BY po.nama")->fetchAll();

// Dokter teratas bulan ini (seperti "Rank Performance")
$topDokter = $pdo->query("SELECT u.nama_lengkap, po.nama AS poli, COUNT(*) AS jml
    FROM kunjungan k JOIN users u ON u.id=k.dokter_id LEFT JOIN poli po ON po.id=k.poli_id
    WHERE k.status='selesai' AND k.tanggal >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
    GROUP BY u.id, u.nama_lengkap, po.nama ORDER BY jml DESC LIMIT 3")->fetchAll();

// Kunjungan 6 hari terakhir (seperti "Tracker Detail")
$days = [];
for ($i = 5; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = ['t' => 0, 's' => 0];
$q = $pdo->query("SELECT tanggal, COUNT(*) AS total, SUM(status='selesai') AS selesai FROM kunjungan k
    WHERE tanggal >= CURDATE() - INTERVAL 5 DAY AND status<>'batal'$polFilter GROUP BY tanggal");
foreach ($q->fetchAll() as $r) if (isset($days[$r['tanggal']])) $days[$r['tanggal']] = ['t' => (int)$r['total'], 's' => (int)$r['selesai']];
$maxBar = max(1, ...array_column($days, 't'));

// Panel kanan (seperti "Chat")
$resepList = $tagihanList = $next = [];
if ($isDokter) {
    $st = $pdo->prepare("SELECT k.no_antrean, p.nama, p.no_rm FROM kunjungan k JOIN pasien p ON p.id=k.pasien_id
        WHERE k.tanggal=CURDATE() AND k.status='menunggu' AND k.poli_id=? ORDER BY k.no_antrean LIMIT 4");
    $st->execute([$pid]); $next = $st->fetchAll();
} else {
    $resepList = $pdo->query("SELECT p.nama, r.status FROM resep r JOIN rekam_medis rm ON rm.id=r.rekam_medis_id
        JOIN kunjungan k ON k.id=rm.kunjungan_id JOIN pasien p ON p.id=k.pasien_id
        WHERE r.status<>'diserahkan' ORDER BY r.id DESC LIMIT 2")->fetchAll();
    $tagihanList = $pdo->query("SELECT b.no_invoice, b.total, p.nama FROM pembayaran b JOIN kunjungan k ON k.id=b.kunjungan_id
        JOIN pasien p ON p.id=k.pasien_id WHERE b.status='belum' ORDER BY b.id DESC LIMIT 2")->fetchAll();
}
$dokterAvatar = $pdo->query("SELECT nama_lengkap FROM users WHERE role='dokter' AND aktif=1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

$hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$hrS  = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
$bln  = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tanggalStr = $hari[(int)date('w')] . ', ' . date('d') . ' ' . $bln[(int)date('n')] . ' ' . date('Y');

function ring(int $r, int $pct, string $color): string {
    $c = 2 * M_PI * $r;
    $out = '<circle cx="100" cy="100" r="' . $r . '" fill="none" stroke="rgba(120,140,170,.16)" stroke-width="14"/>';
    if ($pct > 0) {
        $out .= '<circle cx="100" cy="100" r="' . $r . '" fill="none" stroke="' . $color . '" stroke-width="14" stroke-linecap="round"'
              . ' stroke-dasharray="' . round($pct / 100 * $c, 2) . ' ' . round($c, 2) . '" transform="rotate(-90 100 100)"/>';
    }
    return $out;
}

require __DIR__ . '/includes/header.php';
?>
<!-- Header ringkasan -->
<div class="dash-head">
  <div>
    <h2>Ringkasan Hari Ini</h2>
    <div class="sub"><?= e($tanggalStr) ?> | <?= e(APP_NAME) ?></div>
    <div class="mt-2">
      <span class="chip">Total pasien: <b><?= $totalPasien ?></b></span>
      <?php if (!$isDokter): ?><span class="chip">Pendapatan hari ini: <b><?= rupiah($pendapatan) ?></b></span><?php endif; ?>
    </div>
  </div>
  <div class="d-flex align-items-center gap-3">
    <div class="stack">
      <?php foreach (array_slice($dokterAvatar, 0, 3) as $dn) echo avatar($dn); ?>
      <?php if (count($dokterAvatar) > 3): ?><span class="av more">+<?= count($dokterAvatar) - 3 ?></span><?php endif; ?>
    </div>
    <?php if ($isDokter): ?>
      <a href="periksa.php" class="btn btn-primary px-4 py-2">Mulai Periksa</a>
    <?php else: ?>
      <a href="pasien_tambah.php" class="btn btn-primary px-4 py-2">＋ Pasien Baru</a>
    <?php endif; ?>
  </div>
</div>

<!-- Baris atas -->
<div class="grid-top">
  <section class="panel white">
    <div class="panel-head"><h3>Antrean Hari Ini</h3><a class="see-all" href="<?= $isDokter ? 'periksa.php' : 'kunjungan.php' ?>">Lihat Semua</a></div>
    <div class="task-row">
      <?php foreach ($polis as $i => $po):
        $t = (int)$po['total']; $s = (int)$po['selesai']; $w = $t ? round($s / $t * 100) : 0; ?>
      <a class="task-card c<?= $i % 4 ?>" href="<?= $isDokter ? 'periksa.php' : 'kunjungan.php' ?>">
        <span class="task-chip"><?= (int)$po['menunggu'] ?> menunggu</span>
        <h4>Poli<br><?= e($po['nama']) ?></h4>
        <div class="meta"><?= $t ?> pasien terdaftar</div>
        <div class="task-foot"><div class="track"><i style="width:<?= $w ?>%"></i></div><b><?= $s ?>/<?= $t ?></b></div>
      </a>
      <?php endforeach; if (!$polis): ?><div class="empty w-100">Belum ada poli aktif.</div><?php endif; ?>
    </div>
  </section>

  <section class="panel glass">
    <div class="panel-head"><h3>Status Kunjungan</h3><span class="text-muted small">Total <?= $tot ?> kunjungan</span></div>
    <div class="donut-wrap">
      <div class="legend">
        <div class="it"><div class="lbl"><span class="dot" style="background:var(--purple)"></span>Selesai</div><div class="pct"><?= $pct($sel) ?>%</div></div>
        <div class="it"><div class="lbl"><span class="dot" style="background:var(--orange)"></span>Sedang Diperiksa</div><div class="pct"><?= $pct($dip) ?>%</div></div>
        <div class="it"><div class="lbl"><span class="dot" style="background:var(--sky)"></span>Menunggu</div><div class="pct"><?= $pct($mnt) ?>%</div></div>
      </div>
      <svg viewBox="0 0 200 200" width="210" height="210" role="img" aria-label="Diagram status kunjungan">
        <?= ring(90, $pct($sel), '#a78bdc') ?><?= ring(66, $pct($dip), '#ee8566') ?><?= ring(42, $pct($mnt), '#6aa8e8') ?>
      </svg>
    </div>
  </section>
</div>

<!-- Baris bawah -->
<div class="grid-bottom">
  <section class="panel glass">
    <div class="panel-head"><h3>Dokter Teratas</h3><a class="see-all" href="<?= $isDokter ? 'periksa.php' : 'users.php' ?>">Lihat</a></div>
    <?php foreach ($topDokter as $d): ?>
      <div class="rank-item"><?= avatar($d['nama_lengkap']) ?>
        <div class="info"><b><?= e($d['nama_lengkap']) ?></b><small>Poli <?= e($d['poli'] ?: '-') ?></small></div>
        <span class="pt"><?= (int)$d['jml'] ?> pasien</span></div>
    <?php endforeach; if (!$topDokter): ?><div class="empty">Belum ada pemeriksaan selesai bulan ini.</div><?php endif; ?>
  </section>

  <section class="panel glass">
    <div class="panel-head"><h3>Tren Kunjungan</h3><span class="text-muted small">6 hari</span></div>
    <div class="legend-inline"><span><i style="background:var(--orange)"></i>Selesai</span><span><i style="background:var(--slate)"></i>Terdaftar</span></div>
    <div class="bars">
      <?php foreach ($days as $d => $v):
        $hs = round($v['s'] / $maxBar * 100); $ht = round($v['t'] / $maxBar * 100); ?>
      <div class="bpair">
        <div class="cols">
          <div class="bcol" title="Selesai: <?= $v['s'] ?>"><?php if ($v['s']): ?><div class="fill o" style="height:<?= max($hs, 8) ?>%"></div><?php endif; ?></div>
          <div class="bcol" title="Terdaftar: <?= $v['t'] ?>"><?php if ($v['t']): ?><div class="fill g" style="height:<?= max($ht, 8) ?>%"></div><?php endif; ?></div>
        </div>
        <span class="blabel"><?= $hrS[(int)date('w', strtotime($d))] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel glass">
    <div class="panel-head"><h3><?= $isDokter ? 'Pasien Berikutnya' : 'Perlu Tindakan' ?></h3>
      <a class="see-all" href="<?= $isDokter ? 'periksa.php' : 'farmasi.php' ?>">Lihat</a></div>
    <?php if ($isDokter): foreach ($next as $n): ?>
      <div class="item"><div class="who"><?= e($n['nama']) ?> <?= avatar($n['nama'], 'sm') ?></div>
        <a class="bubble lav" href="periksa.php">Antrean #<?= (int)$n['no_antrean'] ?> · <?= e($n['no_rm']) ?></a></div>
    <?php endforeach; if (!$next): ?><div class="empty">Tidak ada pasien menunggu.</div><?php endif; ?>
    <?php else: foreach ($resepList as $r): ?>
      <div class="item"><div class="who"><?= e($r['nama']) ?> <?= avatar($r['nama'], 'sm') ?></div>
        <a class="bubble lav" href="farmasi.php">Resep <?= $r['status'] === 'baru' ? 'baru masuk' : 'sedang disiapkan' ?></a></div>
    <?php endforeach; foreach ($tagihanList as $t): ?>
      <div class="item"><div class="who"><?= e($t['nama']) ?> <?= avatar($t['nama'], 'sm') ?></div>
        <a class="bubble peach" href="kasir.php">Tagihan <?= e($t['no_invoice']) ?> · <b><?= rupiah($t['total']) ?></b></a></div>
    <?php endforeach; if (!$resepList && !$tagihanList): ?><div class="empty">Tidak ada tindakan tertunda 🎉</div><?php endif; ?>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
