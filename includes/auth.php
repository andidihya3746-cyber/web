<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

ini_set('display_errors', DEBUG ? '1' : '0');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e): void {
    error_log('[APP] ' . $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!doctype html><meta charset="utf-8"><title>Terjadi kesalahan</title>'
       . '<body style="font-family:system-ui;padding:32px"><h2>Terjadi kesalahan</h2>'
       . '<p>Maaf, permintaan tidak dapat diproses. Silakan coba lagi.</p>'
       . (DEBUG ? '<pre>' . htmlspecialchars((string) $e) . '</pre>' : '') . '</body>';
});

if (session_status() === PHP_SESSION_NONE) {
    session_name('ps_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL === '' ? '/' : BASE_URL,
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// ---------- Helper umum ----------
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function fmt_dt(?string $dt): string
{
    return $dt ? date('d/m/Y H:i', strtotime($dt)) : '-';
}

function fmt_num(float|int|string|null $n, int $dec = 2): string
{
    return number_format((float) $n, $dec, ',', '.');
}

function kategori_badge(string $k): string
{
    $map = ['Berhasil' => 'berhasil', 'Sedang' => 'sedang', 'Berisiko' => 'berisiko'];
    return isset($map[$k]) ? '<span class="badge badge-' . $map[$k] . '">' . e($k) . '</span>' : e($k);
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = (string) ($_POST['csrf'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

// ---------- Flash message ----------
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_pull(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------- Login & role ----------
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $st = db()->prepare('SELECT id, nama, username, role FROM users WHERE id = ?');
            $st->execute([$_SESSION['uid']]);
            $user = $st->fetch() ?: null;
            if ($user === null) {
                $_SESSION = [];   // akun sudah dihapus
            }
        }
    }
    return $user;
}

function require_login(): array
{
    $u = current_user();
    if ($u === null) {
        redirect('auth/login.php');
    }
    return $u;
}

function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        exit('Anda tidak memiliki akses ke halaman ini.');
    }
    return $u;
}

function role_label(string $role): string
{
    return $role === 'admin' ? 'Admin' : 'Guru/Staf Akademik';
}