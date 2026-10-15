<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_login();

$labels = [
    'rata_rata_nilai'  => 'Rata-rata nilai',
    'nilai_tugas'      => 'Nilai tugas',
    'nilai_uts'        => 'Nilai UTS',
    'nilai_uas'        => 'Nilai UAS',
    'kehadiran_persen' => 'Kehadiran (%)',
];
$old = ['id_siswa' => '', 'kelas' => ''] + array_fill_keys(array_keys($labels), '');
$errors = [];
$formError = '';
$hasil = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $old['id_siswa'] = trim((string) ($_POST['id_siswa'] ?? ''));
    $old['kelas']    = trim((string) ($_POST['kelas'] ?? ''));
    $row = ['id_siswa' => $old['id_siswa'], 'kelas' => $old['kelas']];

    if (!nis_valid($old['id_siswa'])) {
        $errors['id_siswa'] = 'NIS wajib diisi (angka, 4-20 digit).';
    }
    $kelasBaku = kelas_kanonik($old['kelas']);
    if ($kelasBaku === null) {
        $errors['kelas'] = 'Pilih kelas dari daftar (' . kelas_bantuan() . ').';
    } else {
        $old['kelas'] = $kelasBaku;
        $row['kelas'] = $kelasBaku;
    }
    foreach ($labels as $f => $label) {
        $raw = str_replace(',', '.', trim((string) ($_POST[$f] ?? '')));
        $old[$f] = $raw;
        if ($raw === '' || !is_numeric($raw) || (float) $raw < 0 || (float) $raw > 100) {
            $errors[$f] = $label . ' harus berupa angka antara 0 dan 100.';
        } else {
            $row[$f] = (float) $raw;
        }
    }

    if (!$errors) {
        try {
            $out = MlService::predict([$row]);
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $newId = save_prediksi($pdo, $out[0], 'tunggal', null, (int) $user['id']);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            redirect('pages/prediksi.php?hasil=' . $newId);   // cegah kirim ulang saat refresh
        } catch (MlException $e) {
            $formError = $e->getMessage();
        }
    }
} elseif (isset($_GET['hasil'])) {
    $st = db()->prepare('SELECT * FROM prediksi WHERE id = ?');
    $st->execute([(int) $_GET['hasil']]);
    $hasil = $st->fetch() ?: null;
}

$pageTitle = 'Prediksi Siswa';
$active = 'prediksi';
require __DIR__ . '/../includes/layout_header.php';
?>
<div class="page-header">
  <h1>Prediksi satu siswa</h1>
  <p>Masukkan data akademik siswa, sistem akan menentukan kategori keberhasilannya.</p>
</div>

<div class="grid-2">
  <section class="card">
    <h2 class="card-title">Data siswa</h2>
    <?php if ($formError): ?><div class="alert alert-error"><?= e($formError) ?></div><?php endif; ?>
    <form method="post" novalidate data-loading="Menjalankan model prediksi...">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="field">
          <label class="label" for="id_siswa">NIS</label>
          <input class="input<?= isset($errors['id_siswa']) ? ' is-invalid' : '' ?>" id="id_siswa" name="id_siswa" maxlength="20" inputmode="numeric" placeholder="Contoh: 250344" value="<?= e($old['id_siswa']) ?>" required>
          <?php if (isset($errors['id_siswa'])): ?><div class="field-error"><?= e($errors['id_siswa']) ?></div><?php endif; ?>
        </div>
        <div class="field">
          <label class="label" for="kelas">Kelas</label>
          <select class="select" id="kelas" name="kelas" required>
            <?= kelas_options($old['kelas'], 'Pilih kelas') ?>
          </select>
          <?php if (isset($errors['kelas'])): ?><div class="field-error"><?= e($errors['kelas']) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="form-grid">
        <?php foreach ($labels as $f => $label): ?>
          <div class="field">
            <label class="label" for="<?= $f ?>"><?= e($label) ?></label>
            <input class="input<?= isset($errors[$f]) ? ' is-invalid' : '' ?>" id="<?= $f ?>" name="<?= $f ?>" type="number"
                   min="0" max="100" step="any" inputmode="decimal" data-score value="<?= e($old[$f]) ?>" required>
            <?php if (isset($errors[$f])): ?><div class="field-error"><?= e($errors[$f]) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="help">Semua nilai berskala 0-100.</div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Prediksi</button></div>
    </form>
  </section>

  <section class="card">
    <h2 class="card-title">Hasil prediksi</h2>
    <?php if ($hasil): ?>
      <div class="result-head">
        <div>
          <div class="muted">Kategori</div>
          <?= str_replace('badge-', 'badge-lg badge-', kategori_badge($hasil['kategori'])) ?>
        </div>
        <div class="muted"><?= e(fmt_dt($hasil['created_at'])) ?></div>
      </div>
      <dl class="kv">
        <div><dt>NIS</dt><dd><?= e($hasil['id_siswa'] ?? '-') ?></dd></div>
        <div><dt>Kelas</dt><dd><?= e($hasil['kelas'] ?? '-') ?></dd></div>
        <?php foreach ($labels as $f => $label): ?>
          <div><dt><?= e($label) ?></dt><dd><?= e(fmt_num($hasil[$f], 2)) ?></dd></div>
        <?php endforeach; ?>
        <div>
          <dt>Tingkat kecocokan</dt>
          <dd><?= e(fmt_num($hasil['jarak_ke_centroid'], 4)) ?> <span class="muted">(jarak ke pusat klaster; makin kecil makin cocok)</span></dd>
        </div>
      </dl>
    <?php else: ?>
      <div class="empty"><strong>Belum ada hasil</strong>Isi formulir lalu tekan Prediksi.</div>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>