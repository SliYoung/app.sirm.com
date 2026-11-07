<?php
function user(): ?array { return $_SESSION['user'] ?? null; }

function require_login(): void { if (!user()) redirect('login.php'); }

function require_role(string ...$roles): void {
    require_login();
    if (!in_array(user()['role'], $roles, true)) {
        http_response_code(403);
        exit('<h3 style="font-family:sans-serif;padding:2rem">403 - Anda tidak memiliki akses ke halaman ini.</h3>');
    }
}

function attempt_login(string $username, string $password): bool {
    $st = db()->prepare('SELECT * FROM users WHERE username = ? AND aktif = 1 LIMIT 1');
    $st->execute([$username]);
    $row = $st->fetch();
    if ($row && password_verify($password, $row['password'])) {
        session_regenerate_id(true); // cegah session fixation
        if (password_needs_rehash($row['password'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE users SET password=? WHERE id=?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }
        $_SESSION['user'] = ['id'=>(int)$row['id'], 'username'=>$row['username'], 'nama'=>$row['nama_lengkap'],
                             'role'=>$row['role'], 'poli_id'=>$row['poli_id'] ? (int)$row['poli_id'] : null];
        return true;
    }
    return false;
}
