<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
$user = require_role('admin');

const PER_PAGE = 20;
$pdo = db();
$errors = [];
$form = ['id' => 0, 'id_siswa' => '', 'nama' => '', 'kelas' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id   = (int) ($_POST['id'] ?? 0);

    if ($aksi === 'hapus' && $id > 0) {
        $st = $pdo->prepare('SELECT id_siswa FROM siswa WHERE id = ?');
        $st->execute([$id]);
        $idSiswa = $st->fetchColumn();
        if ($idSiswa !== false) {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM prediksi WHERE siswa_id = ? OR id_siswa = ?')->execute([$id, $idSiswa]);
            $pdo->prepare('DELETE FROM siswa WHERE id = ?')->execute([$id]);
            $pdo->commit();
            flash('success', 'Siswa dengan NIS ' . $idSiswa . ' beserta riwayat prediksinya telah dihapus.');
        }
        redirect('pages/admin/siswa.php');
    }

    if ($aksi === 'simpan') {
        $form = [
            'id'       => $id,
            'id_siswa' => trim((string) ($_POST['id_siswa'] ?? '')),
            'nama'     => trim((string) ($_POST['nama'] ?? '')),
            'kelas'    => trim((string) ($_POST['kelas'] ?? '')),
        ];
        if ($id > 0) {   // saat ubah, NIS tidak boleh berganti
            $st = $pdo->prepare('SELECT id_siswa FROM siswa WHERE id = ?');
            $st->execute([$id]);
            $form['id_siswa'] = (string) $st->fetchColumn();
        } elseif (!nis_valid($form['id_siswa'])) {
            $errors['id_siswa'] = 'NIS wajib diisi (angka, 4-20 digit).';
        }
        if (str_len($form['nama']) > 100) {
            $errors['nama'] = 'Nama maksimal 100 karakter.';
        }
        if ($form['kelas'] !== '') {
            $baku = kelas_kanonik($form['kelas']);
            if ($baku === null) {
                $errors['kelas'] = 'Pilih kelas dari daftar (' . kelas_bantuan() . ').';
            } else {
                $form['kelas'] = $baku;
            }
        }
        if (!$errors) {
            $nama  = $form['nama'] !== '' ? $form['nama'] : null;
            $kelas = $form['kelas'] !== '' ? $form['kelas'] : null;
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE siswa SET nama = ?, kelas = ? WHERE id = ?')->execute([$nama, $kelas, $id]);
                    flash('success', 'Data siswa diperbarui.');
                } else {
                    $pdo->prepare('INSERT INTO siswa (id_siswa, nama, kelas, created_at) VALUES (?, ?, ?, ?)')
                        ->execute([$form['id_siswa'], $nama, $kelas, date('Y-m-d H:i:s')]);
                    flash('success', 'Siswa ditambahkan.');
                }
                redirect('pages/admin/siswa.php');
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $errors['id_siswa'] = 'NIS sudah terdaftar.';
            }
        }
    }
} elseif (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT id, id_siswa, nama, kelas FROM siswa WHERE id = ?');
    $st->execute([(int) $_GET['edit']]);
    if ($r = $st->fetch()) {
        $form = ['id' => (int) $r['id'], 'id_siswa' => $r['id_siswa'], 'nama' => (string) $r['nama'], 'kelas' => (string) $r['kelas']];
    }
}

// ---------- Daftar ----------
$q = trim((string) ($_GET['q'] ?? ''));
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE s.id_siswa LIKE ? OR s.nama LIKE ?';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $params = [$like, $like];
}
$st = $pdo->prepare("SELECT COUNT(*) FROM siswa s {$where}");
$st->execute($params);
$total = (int) $st->fetchColumn();
$pages = max(1, (int) ceil($total / PER_PAGE));
$page  = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

$st = $pdo->prepare("SELECT s.*, (SELECT p.kategori FROM prediksi p WHERE p.id_siswa = s.id_siswa ORDER BY p.id DESC LIMIT 1) AS kategori_terakhir
                     FROM siswa s {$where} ORDER BY s.id_siswa LIMIT ? OFFSET ?");
$i = 1;
foreach ($params as $p) {
    $st->bindValue($i++, $p, PDO::PARAM_STR);
}
$st->bindValue($i++, PER_PAGE, PDO::PARAM_INT);
$st->bindValue($i, ($page - 1) * PER_PAGE, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

$pageTitle = 'Data Siswa';
$active = 'siswa';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
  <h1>Data siswa</h1>
  <p>Kelola daftar siswa. Siswa juga otomatis ditambahkan saat prediksi dengan NIS baru.</p>
</div>

<section class="card">
  <h2 class="card-title"><?= $form['id'] > 0 ? 'Ubah siswa' : 'Tambah siswa' ?></h2>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
    <div class="form-grid">
      <div class="field">
        <label class="label" for="id_siswa">NIS</label>
        <input class="input<?= isset($errors['id_siswa']) ? ' is-invalid' : '' ?>" id="id_siswa" name="id_siswa" maxlength="20" inputmode="numeric"
               value="<?= e($form['id_siswa']) ?>" <?= $form['id'] > 0 ? 'readonly' : 'required' ?>>
        <?php if (isset($errors['id_siswa'])): ?><div class="field-error"><?= e($errors['id_siswa']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label class="label" for="nama">Nama (opsional)</label>
        <input class="input<?= isset($errors['nama']) ? ' is-invalid' : '' ?>" id="nama" name="nama" maxlength="100" value="<?= e($form['nama']) ?>">
        <?php if (isset($errors['nama'])): ?><div class="field-error"><?= e($errors['nama']) ?></div><?php endif; ?>
      </div>
      <div class="field">
        <label class="label" for="kelas">Kelas</label>
        <select class="select" id="kelas" name="kelas">
          <?= kelas_options($form['kelas'], '-') ?>
        </select>
        <?php if (isset($errors['kelas'])): ?><div class="field-error"><?= e($errors['kelas']) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="btn-group">
      <button class="btn btn-primary" type="submit"><?= $form['id'] > 0 ? 'Simpan perubahan' : 'Tambah' ?></button>
      <?php if ($form['id'] > 0): ?><a class="btn btn-secondary" href="<?= e(url('pages/admin/siswa.php')) ?>">Batal</a><?php endif; ?>
    </div>
  </form>
</section>

<section class="card mt-4">
  <form method="get" class="toolbar">
    <input class="input grow" type="search" name="q" placeholder="Cari NIS atau nama siswa..." value="<?= e($q) ?>">
    <button class="btn btn-secondary" type="submit">Cari</button>
    <?php if ($q !== ''): ?><a class="btn btn-secondary" href="<?= e(url('pages/admin/siswa.php')) ?>">Reset</a><?php endif; ?>
  </form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Prediksi terakhir</th><th></th></tr></thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="5"><div class="empty"><strong>Belum ada siswa</strong>Tambahkan siswa lewat formulir di atas.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['id_siswa']) ?></td>
            <td><?= e($r['nama'] ?? '-') ?></td>
            <td><?= e($r['kelas'] ?? '-') ?></td>
            <td><?= $r['kategori_terakhir'] ? kategori_badge($r['kategori_terakhir']) : '<span class="muted">Belum ada</span>' ?></td>
            <td>
              <div class="table-actions">
                <a class="btn btn-secondary btn-sm" href="<?= e('?' . http_build_query(['edit' => $r['id']])) ?>">Ubah</a>
                <form method="post" data-confirm="Hapus siswa dengan NIS <?= e($r['id_siswa']) ?>? Seluruh riwayat prediksinya juga akan dihapus.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="hapus">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= render_pagination($page, $pages, $q !== '' ? ['q' => $q] : [], $total, PER_PAGE) ?>
</section>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>