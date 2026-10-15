<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
$user = require_role('admin');

$pdo = db();
$errors = [];
$form = ['id' => 0, 'nama' => '', 'username' => '', 'role' => 'dosen'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id   = (int) ($_POST['id'] ?? 0);

    if ($aksi === 'hapus' && $id > 0) {
        if ($id === (int) $user['id']) {
            flash('error', 'Anda tidak dapat menghapus akun yang sedang dipakai.');
        } else {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('success', 'Akun dihapus.');
        }
        redirect('pages/admin/pengguna.php');
    }

    if ($aksi === 'simpan') {
        $form = [
            'id'       => $id,
            'nama'     => trim((string) ($_POST['nama'] ?? '')),
            'username' => trim((string) ($_POST['username'] ?? '')),
            'role'     => (string) ($_POST['role'] ?? ''),
        ];
        $password = (string) ($_POST['password'] ?? '');

        if ($form['nama'] === '' || str_len($form['nama']) > 100) {
            $errors['nama'] = 'Nama wajib diisi (maksimal 100 karakter).';
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $form['username'])) {
            $errors['username'] = 'Username 3-50 karakter (huruf, angka, titik, garis bawah, strip).';
        }
        if (!in_array($form['role'], ['admin', 'dosen'], true)) {
            $errors['role'] = 'Role tidak valid.';
        }
        if ($id > 0 && $id === (int) $user['id']) {
            $form['role'] = 'admin';   // admin tidak boleh menurunkan role dirinya sendiri
        }
        if ($id === 0 && $password === '') {
            $errors['password'] = 'Kata sandi wajib diisi untuk akun baru.';
        } elseif ($password !== '' && strlen($password) < 8) {
            $errors['password'] = 'Kata sandi minimal 8 karakter.';
        }

        if (!$errors) {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE users SET nama = ?, username = ?, role = ? WHERE id = ?')
                        ->execute([$form['nama'], $form['username'], $form['role'], $id]);
                    if ($password !== '') {
                        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                    }
                    flash('success', 'Akun diperbarui.');
                } else {
                    $pdo->prepare('INSERT INTO users (nama, username, password_hash, role) VALUES (?, ?, ?, ?)')
                        ->execute([$form['nama'], $form['username'], password_hash($password, PASSWORD_DEFAULT), $form['role']]);
                    flash('success', 'Akun ditambahkan.');
                }
                redirect('pages/admin/pengguna.php');
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $errors['username'] = 'Username sudah dipakai.';
            }
        }
    }
} elseif (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT id, nama, username, role FROM users WHERE id = ?');
    $st->execute([(int) $_GET['edit']]);
    if ($r = $st->fetch()) {
        $form = $r;
    }
}

$users = $pdo->query('SELECT id, nama, username, role, created_at FROM users ORDER BY role, nama')->fetchAll();

$pageTitle = 'Akun Pengguna';
$active = 'pengguna';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
  <h1>Akun pengguna</h1>
  <p>Kelola akun Admin dan Guru/Staf Akademik, termasuk mengganti kata sandi.</p>
</div>

<section class="card">
  <h2 class="card-title"><?= $form['id'] > 0 ? 'Ubah akun' : 'Tambah akun' ?></h2>
  <form method="post" novalidate autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
    <div class="form-grid">
      <div class="field">
        <label class="label" for="nama">Nama lengkap</label>
        <input class="input<?= isset($errors['nama']) ? ' is-invalid' : '' ?>" id="nama" name="nama" maxlength="100" value="<?= e($form['nama']) ?>" required>
        <?php if (isset($errors['nama'])): ?><div class="field-error"><?= e($errors['nama']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label class="label" for="username">Username</label>
        <input class="input<?= isset($errors['username']) ? ' is-invalid' : '' ?>" id="username" name="username" maxlength="50" value="<?= e($form['username']) ?>" required>
        <?php if (isset($errors['username'])): ?><div class="field-error"><?= e($errors['username']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label class="label" for="role">Role</label>
        <select class="select" id="role" name="role"<?= (int) $form['id'] === (int) $user['id'] ? ' disabled' : '' ?>>
          <option value="dosen"<?= $form['role'] === 'dosen' ? ' selected' : '' ?>>Guru/Staf Akademik</option>
          <option value="admin"<?= $form['role'] === 'admin' ? ' selected' : '' ?>>Admin</option>
        </select>
        <?php if (isset($errors['role'])): ?><div class="field-error"><?= e($errors['role']) ?></div><?php endif; ?>
        <?php if ((int) $form['id'] === (int) $user['id']): ?><div class="help">Role akun yang sedang dipakai tidak dapat diubah.</div><?php endif; ?>
      </div>
      <div class="field">
        <label class="label" for="password"><?= $form['id'] > 0 ? 'Kata sandi baru (kosongkan jika tidak diganti)' : 'Kata sandi' ?></label>
        <input class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" type="password" autocomplete="new-password">
        <?php if (isset($errors['password'])): ?><div class="field-error"><?= e($errors['password']) ?></div><?php endif; ?>
        <div class="help">Minimal 8 karakter.</div>
      </div>
    </div>
    <div class="btn-group">
      <button class="btn btn-primary" type="submit"><?= $form['id'] > 0 ? 'Simpan perubahan' : 'Tambah' ?></button>
      <?php if ($form['id'] > 0): ?><a class="btn btn-secondary" href="<?= e(url('pages/admin/pengguna.php')) ?>">Batal</a><?php endif; ?>
    </div>
  </form>
</section>

<section class="card mt-4">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Dibuat</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= e($u['nama']) ?><?= (int) $u['id'] === (int) $user['id'] ? ' <span class="muted">(Anda)</span>' : '' ?></td>
            <td><?= e($u['username']) ?></td>
            <td><span class="badge badge-role"><?= e(role_label($u['role'])) ?></span></td>
            <td class="nowrap"><?= e(fmt_dt($u['created_at'])) ?></td>
            <td>
              <div class="table-actions">
                <a class="btn btn-secondary btn-sm" href="<?= e('?' . http_build_query(['edit' => $u['id']])) ?>">Ubah</a>
                <?php if ((int) $u['id'] !== (int) $user['id']): ?>
                  <form method="post" data-confirm="Hapus akun <?= e($u['username']) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>