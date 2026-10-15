<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

if (current_user()) {
    redirect('pages/dashboard.php');
}

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $st = db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
    $st->execute([$username]);
    $row = $st->fetch();

    // password_verify tetap dijalankan walau user tidak ada, agar waktu respons seragam
    $hash = $row['password_hash'] ?? '$2y$10$lFg6cdYIPRm/bP0e5VaHmepVdjgVJ5Fvja2bb3T4OYjfCJ1b00tpy';
    if (password_verify($password, $hash) && $row) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $row['id'];
        unset($_SESSION['csrf']);
        redirect('pages/dashboard.php');
    }
    $error = 'Username atau kata sandi salah.';
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk - <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="auth-body">
  <main class="auth-card card">
    <h1 class="auth-title"><?= e(APP_NAME) ?></h1>
    <p class="muted">Masuk untuk melanjutkan.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off" data-loading>
      <?= csrf_field() ?>
      <div class="field">
        <label class="label" for="username">Username</label>
        <input class="input" id="username" name="username" value="<?= e($username) ?>" required autofocus>
      </div>
      <div class="field">
        <label class="label" for="password">Kata sandi</label>
        <input class="input" id="password" name="password" type="password" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Masuk</button>
    </form>
  </main>
  <script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>