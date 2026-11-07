<?php
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url) { header('Location: ' . $url); exit; }
function post(string $k, string $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function rupiah($n): string { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function tgl($d): string { return $d ? date('d-m-Y', strtotime((string)$d)) : '-'; }
function umur($tgl): int { return (int)date_diff(date_create((string)$tgl), date_create('today'))->y; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_verify(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Token CSRF tidak valid. Muat ulang halaman.');
    }
}
function flash(?string $msg = null, string $type = 'success') {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function badge(string $status): string {
    $map = ['menunggu'=>'warning text-dark','diperiksa'=>'info text-dark','selesai'=>'success','batal'=>'secondary',
            'baru'=>'warning text-dark','disiapkan'=>'info text-dark','diserahkan'=>'success','belum'=>'danger','lunas'=>'success'];
    return '<span class="badge bg-' . ($map[$status] ?? 'secondary') . '">' . e(ucfirst($status)) . '</span>';
}

function inisial(string $nama): string {
    $nama = preg_replace('/^dr\.?\s*/i', '', trim($nama));
    $p = preg_split('/\s+/', $nama);
    return strtoupper(mb_substr($p[0] ?? '?', 0, 1) . (isset($p[1]) ? mb_substr($p[1], 0, 1) : ''));
}
function warna_avatar(string $s): string {
    $c = ['#6aa8e8', '#a78bdc', '#ee8566', '#52b788', '#e0a84c', '#d86fa0'];
    return $c[crc32($s) % count($c)];
}
function avatar(string $nama, string $cls = ''): string {
    return '<span class="av ' . $cls . '" style="background:' . warna_avatar($nama) . '" title="' . e($nama) . '">' . e(inisial($nama)) . '</span>';
}
