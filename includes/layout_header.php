<?php
declare(strict_types=1);
/**
 * Header + sidebar. Variabel yang dipakai halaman sebelum include:
 *   $pageTitle (string), $active (kunci menu), $user (array dari require_login())
 */
$pageTitle = $pageTitle ?? APP_NAME;
$active    = $active ?? '';
$user      = $user ?? require_login();

function icon(string $name): string
{
    static $p = [
        'home'     => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'upload'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'refresh'  => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'menu'     => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}

// [kunci, label, ikon, file, role yang boleh]
$menu = [
    ['dashboard', 'Dashboard',         'home',    'pages/dashboard.php',        ['admin', 'dosen']],
    ['prediksi',  'Prediksi Siswa',    'user',    'pages/prediksi.php',         ['admin', 'dosen']],
    ['massal',    'Prediksi Massal',   'upload',  'pages/massal.php',           ['admin', 'dosen']],
    ['riwayat',   'Riwayat',           'clock',   'pages/riwayat.php',          ['admin', 'dosen']],
    ['laporan',   'Laporan',           'file',    'pages/laporan.php',          ['admin', 'dosen']],
    ['siswa',     'Data Siswa',        'users',   'pages/admin/siswa.php',      ['admin']],
    ['pengguna',  'Akun Pengguna',     'lock',    'pages/admin/pengguna.php',   ['admin']],
    ['latih',     'Latih Ulang Model', 'refresh', 'pages/admin/latih_ulang.php', ['admin']],
];
$flashes = flash_pull();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand"><?= e(APP_NAME) ?></div>
    <?php
    $adminTitleShown = false;
    foreach ($menu as [$key, $label, $ic, $file, $roles]):
        if (!in_array($user['role'], $roles, true) || !is_file(__DIR__ . '/../' . $file)) {
            continue;   // sembunyikan menu yang bukan haknya / halamannya belum dipasang
        }
        if ($roles === ['admin'] && !$adminTitleShown) {
            $adminTitleShown = true;
            echo '<div class="nav-title">Admin</div>';
        }
    ?>
      <a class="nav-link<?= $active === $key ? ' active' : '' ?>" href="<?= e(url($file)) ?>"><?= icon($ic) ?><span><?= e($label) ?></span></a>
    <?php endforeach; ?>
  </aside>
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <div class="main">
    <header class="topbar">
      <button class="btn btn-secondary btn-sm menu-btn" id="menuBtn" type="button" aria-label="Buka menu"><?= icon('menu') ?></button>
      <div class="topbar-user" style="margin-left:auto">
        <span class="topbar-name"><?= e($user['nama']) ?> <span class="badge badge-role"><?= e(role_label($user['role'])) ?></span></span>
        <form method="post" action="<?= e(url('auth/logout.php')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-secondary btn-sm" type="submit"><?= icon('logout') ?> Keluar</button>
        </form>
      </div>
    </header>
    <main class="content">
      <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= $f['type'] === 'error' ? 'error' : 'success' ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>