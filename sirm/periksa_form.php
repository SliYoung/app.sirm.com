<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('dokter');
$pdo = db(); $u = user(); $title = 'Form Pemeriksaan & Rekam Medis';
$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare('SELECT k.*, p.id AS pid, p.no_rm, p.nama, p.tanggal_lahir, p.golongan_darah, p.jenis_kelamin, po.nama AS poli, po.tarif
    FROM kunjungan k JOIN pasien p ON p.id=k.pasien_id JOIN poli po ON po.id=k.poli_id WHERE k.id=?');
$st->execute([$id]); $k = $st->fetch();
if (!$k || (int)$k['poli_id'] !== (int)$u['poli_id']) { flash('Data kunjungan tidak ditemukan.', 'danger'); redirect('periksa.php'); }
if (in_array($k['status'], ['menunggu','batal'], true)) { flash('Mulai pemeriksaan dari daftar antrean.', 'warning'); redirect('periksa.php'); }

$st = $pdo->prepare('SELECT * FROM rekam_medis WHERE kunjungan_id=?'); $st->execute([$id]); $rm = $st->fetch() ?: null;
$st = $pdo->prepare('SELECT * FROM pembayaran WHERE kunjungan_id=?'); $st->execute([$id]); $bayar = $st->fetch() ?: null;
$resep = null;
if ($rm) { $st = $pdo->prepare('SELECT * FROM resep WHERE rekam_medis_id=?'); $st->execute([$rm['id']]); $resep = $st->fetch() ?: null; }

$locked = ($bayar && $bayar['status'] === 'lunas') || ($resep && $resep['status'] !== 'baru');
$errors = [];
$f = ['keluhan'=>$rm['keluhan'] ?? '', 'diagnosa'=>$rm['diagnosa'] ?? '', 'kode_icd10'=>$rm['kode_icd10'] ?? '',
      'tindakan'=>$rm['tindakan'] ?? '', 'biaya_tindakan'=>$rm['biaya_tindakan'] ?? 0, 'catatan'=>$rm['catatan'] ?? ''];
$rows = [];
if ($resep) {
    $st = $pdo->prepare('SELECT obat_id, jumlah, aturan_pakai AS aturan FROM resep_detail WHERE resep_id=?');
    $st->execute([$resep['id']]); $rows = $st->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($locked) { flash('Data terkunci (resep sudah diproses farmasi atau sudah dibayar).', 'danger'); redirect('periksa.php'); }
    $f = ['keluhan'=>post('keluhan'), 'diagnosa'=>post('diagnosa'), 'kode_icd10'=>strtoupper(post('kode_icd10')),
          'tindakan'=>post('tindakan'), 'biaya_tindakan'=>max(0, (float)post('biaya_tindakan', '0')), 'catatan'=>post('catatan')];
    $rows = [];
    foreach (($_POST['obat_id'] ?? []) as $i => $oid) {
        $oid = (int)$oid; $qty = (int)($_POST['jumlah'][$i] ?? 0);
        if ($oid > 0 && $qty > 0) $rows[] = ['obat_id'=>$oid, 'jumlah'=>$qty, 'aturan'=>trim((string)($_POST['aturan'][$i] ?? ''))];
    }
    if ($f['keluhan'] === '') $errors[] = 'Keluhan utama wajib diisi.';
    if ($f['diagnosa'] === '') $errors[] = 'Diagnosa wajib diisi.';
    if ($f['kode_icd10'] !== '' && !preg_match('/^[A-Z][0-9]{2}(\.[0-9A-Z]{1,4})?$/', $f['kode_icd10'])) $errors[] = 'Format kode ICD-10 tidak valid (contoh: J00 atau K29.7).';

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            if ($rm) {
                $pdo->prepare('UPDATE rekam_medis SET keluhan=?,diagnosa=?,kode_icd10=?,tindakan=?,biaya_tindakan=?,catatan=? WHERE id=?')
                    ->execute([$f['keluhan'],$f['diagnosa'],$f['kode_icd10'] ?: null,$f['tindakan'] ?: null,$f['biaya_tindakan'],$f['catatan'] ?: null,$rm['id']]);
                $rmId = (int)$rm['id'];
            } else {
                $pdo->prepare('INSERT INTO rekam_medis (kunjungan_id,dokter_id,keluhan,diagnosa,kode_icd10,tindakan,biaya_tindakan,catatan) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$id,$u['id'],$f['keluhan'],$f['diagnosa'],$f['kode_icd10'] ?: null,$f['tindakan'] ?: null,$f['biaya_tindakan'],$f['catatan'] ?: null]);
                $rmId = (int)$pdo->lastInsertId();
            }
            $pdo->prepare('DELETE rd FROM resep_detail rd JOIN resep r ON r.id=rd.resep_id WHERE r.rekam_medis_id=?')->execute([$rmId]);
            $biayaObat = 0.0;
            if ($rows) {
                $s = $pdo->prepare('SELECT id FROM resep WHERE rekam_medis_id=?'); $s->execute([$rmId]); $resepId = $s->fetchColumn();
                if (!$resepId) { $pdo->prepare('INSERT INTO resep (rekam_medis_id) VALUES (?)')->execute([$rmId]); $resepId = $pdo->lastInsertId(); }
                $hs = $pdo->prepare('SELECT harga FROM obat WHERE id=?');
                $ins = $pdo->prepare('INSERT INTO resep_detail (resep_id,obat_id,jumlah,aturan_pakai,harga_satuan) VALUES (?,?,?,?,?)');
                foreach ($rows as $r) {
                    $hs->execute([$r['obat_id']]); $h = $hs->fetchColumn();
                    if ($h === false) throw new RuntimeException('Obat tidak valid.');
                    $ins->execute([$resepId, $r['obat_id'], $r['jumlah'], $r['aturan'] ?: null, $h]);
                    $biayaObat += (float)$h * $r['jumlah'];
                }
            } else {
                $pdo->prepare('DELETE FROM resep WHERE rekam_medis_id=?')->execute([$rmId]);
            }
            $pdo->prepare("UPDATE kunjungan SET status='selesai', dokter_id=? WHERE id=?")->execute([$u['id'], $id]);

            $total = (float)$k['tarif'] + $f['biaya_tindakan'] + $biayaObat;
            if ($bayar) {
                $pdo->prepare('UPDATE pembayaran SET biaya_layanan=?,biaya_tindakan=?,biaya_obat=?,total=? WHERE id=?')
                    ->execute([$k['tarif'], $f['biaya_tindakan'], $biayaObat, $total, $bayar['id']]);
            } else {
                $pdo->prepare('INSERT INTO pembayaran (kunjungan_id,no_invoice,biaya_layanan,biaya_tindakan,biaya_obat,total) VALUES (?,?,?,?,?,?)')
                    ->execute([$id, 'INV-' . date('Ymd') . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT), $k['tarif'], $f['biaya_tindakan'], $biayaObat, $total]);
            }
            $pdo->commit();
            flash('Rekam medis tersimpan. Pasien dilanjutkan ke farmasi/kasir.');
            redirect('periksa.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Gagal menyimpan: ' . (APP_DEBUG ? $ex->getMessage() : 'kesalahan server.');
        }
    }
}

$obatList = $pdo->query('SELECT id,nama,satuan,stok,harga FROM obat ORDER BY nama')->fetchAll();
$st = $pdo->prepare("SELECT k2.tanggal, rm.diagnosa FROM kunjungan k2 JOIN rekam_medis rm ON rm.kunjungan_id=k2.id
    WHERE k2.pasien_id=? AND k2.id<>? ORDER BY k2.tanggal DESC LIMIT 5");
$st->execute([$k['pid'], $id]); $hist = $st->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<?php if ($locked): ?><div class="alert alert-warning">Data terkunci karena resep sudah diproses farmasi atau tagihan sudah lunas.</div><?php endif; ?>
<div class="row g-3">
<div class="col-lg-4">
  <div class="card shadow-sm border-0 mb-3"><div class="card-body">
    <h5><?= e($k['nama']) ?></h5><div class="text-primary fw-semibold"><?= e($k['no_rm']) ?></div>
    <div class="small text-muted mt-2">
      <?= $k['jenis_kelamin']==='L'?'Laki-laki':'Perempuan' ?>, <?= umur($k['tanggal_lahir']) ?> th · Gol. darah <?= e($k['golongan_darah']) ?><br>
      Poli <?= e($k['poli']) ?> · Antrean #<?= (int)$k['no_antrean'] ?></div>
  </div></div>
  <div class="card shadow-sm border-0"><div class="card-header bg-white fw-semibold">Riwayat Singkat</div><ul class="list-group list-group-flush">
    <?php foreach ($hist as $h): ?><li class="list-group-item small"><?= tgl($h['tanggal']) ?> — <?= e($h['diagnosa']) ?></li><?php endforeach; ?>
    <?php if (!$hist): ?><li class="list-group-item small text-muted">Belum ada riwayat.</li><?php endif; ?></ul></div>
</div>
<div class="col-lg-8"><div class="card shadow-sm border-0"><div class="card-body">
<form method="post" class="row g-3">
  <?= csrf_field() ?>
  <fieldset class="row g-3" <?= $locked ? 'disabled' : '' ?>>
  <div class="col-12"><label class="form-label">Keluhan Utama</label><textarea name="keluhan" rows="2" class="form-control" required><?= e($f['keluhan']) ?></textarea></div>
  <div class="col-md-9"><label class="form-label">Diagnosa</label><textarea name="diagnosa" rows="2" class="form-control" required><?= e($f['diagnosa']) ?></textarea></div>
  <div class="col-md-3"><label class="form-label">Kode ICD-10 <small class="text-muted">(opsional)</small></label><input name="kode_icd10" class="form-control" maxlength="10" value="<?= e($f['kode_icd10']) ?>"></div>
  <div class="col-md-8"><label class="form-label">Tindakan Medis</label><textarea name="tindakan" rows="2" class="form-control"><?= e($f['tindakan']) ?></textarea></div>
  <div class="col-md-4"><label class="form-label">Biaya Tindakan (Rp)</label><input type="number" min="0" step="1000" name="biaya_tindakan" class="form-control" value="<?= e((int)$f['biaya_tindakan']) ?>"></div>
  <div class="col-12"><label class="form-label">Catatan Dokter</label><textarea name="catatan" rows="2" class="form-control"><?= e($f['catatan']) ?></textarea></div>
  <div class="col-12"><hr><h6>Resep Obat</h6><div id="rows"></div>
    <button type="button" id="addRow" class="btn btn-sm btn-outline-primary">+ Tambah Obat</button></div>
  <div class="col-12"><button class="btn btn-success">Simpan Rekam Medis</button> <a href="periksa.php" class="btn btn-light">Kembali</a></div>
  </fieldset>
</form></div></div></div>
</div>
<script>
const obat = <?= json_encode($obatList, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const init = <?= json_encode($rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const wrap = document.getElementById('rows');
function addRow(r = {}) {
  const d = document.createElement('div'); d.className = 'row g-2 mb-2';
  const c1 = document.createElement('div'); c1.className = 'col-md-5';
  const sel = document.createElement('select'); sel.name = 'obat_id[]'; sel.className = 'form-select';
  sel.add(new Option('-- pilih obat --', ''));
  obat.forEach(o => { const op = new Option(`${o.nama} (stok ${o.stok})`, o.id); if (String(o.id) === String(r.obat_id)) op.selected = true; sel.add(op); });
  c1.append(sel);
  const c2 = document.createElement('div'); c2.className = 'col-md-2';
  const q = document.createElement('input'); q.type = 'number'; q.min = 1; q.name = 'jumlah[]'; q.className = 'form-control'; q.placeholder = 'Jumlah'; q.value = r.jumlah || '';
  c2.append(q);
  const c3 = document.createElement('div'); c3.className = 'col-md-4';
  const a = document.createElement('input'); a.name = 'aturan[]'; a.className = 'form-control'; a.placeholder = 'Aturan pakai, mis. 3x1 sesudah makan'; a.value = r.aturan || '';
  c3.append(a);
  const c4 = document.createElement('div'); c4.className = 'col-md-1';
  const x = document.createElement('button'); x.type = 'button'; x.className = 'btn btn-outline-danger w-100'; x.textContent = '×'; x.onclick = () => d.remove();
  c4.append(x);
  d.append(c1, c2, c3, c4); wrap.append(d);
}
document.getElementById('addRow').onclick = () => addRow();
(init.length ? init : []).forEach(addRow);
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
