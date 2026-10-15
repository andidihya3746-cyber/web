<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
$user = require_role('admin');

$formError = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    @set_time_limit(TRAIN_TIMEOUT + 120);
    $tmp = null;
    $lock = null;
    try {
        $tmp = receive_upload('file', 'xlsx');
        if (!is_dir(TMP_DIR)) {
            @mkdir(TMP_DIR, 0775, true);
        }
        $lock = fopen(TMP_DIR . '/train.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new MlException('Pelatihan lain sedang berjalan. Coba lagi beberapa saat.');
        }
        MlService::retrain($tmp);
        $done = true;
    } catch (MlException $e) {
        $formError = $e->getMessage();
        if (str_starts_with($formError, 'Konversi file Excel gagal')) {
            error_log('[Latih] ' . $formError);
            $formError = 'Konversi file Excel gagal. Pastikan file adalah Excel (.xlsx) yang valid '
                       . 'dan sheet pertama berisi data lengkap dengan baris judul kolom.';
        }
    } finally {
        if (is_resource($lock)) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        if ($tmp !== null) {
            @unlink($tmp);
        }
    }
    if ($done) {
        flash('success', 'Model berhasil dilatih ulang dan sudah dipakai untuk prediksi berikutnya.');
        redirect('pages/admin/latih_ulang.php');
    }
}

$m = MlService::metrics();

$pageTitle = 'Latih Ulang Model';
$active = 'latih';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
  <h1>Latih ulang model</h1>
  <p>Ganti model K-Means dengan data akademik terbaru.</p>
</div>

<div class="grid-2">
  <section class="card">
    <h2 class="card-title">Model saat ini</h2>
    <?php if ($m): ?>
      <dl class="kv">
        <div><dt>Jumlah data latih</dt><dd><?= e(fmt_num($m['jumlah_data'] ?? 0, 0)) ?></dd></div>
        <div><dt>Silhouette score</dt><dd><?= e(fmt_num($m['silhouette_score'] ?? 0, 4)) ?></dd></div>
      </dl>
      <h3 class="card-title mt-4">Jumlah data per kategori</h3>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Kategori</th><th class="num">Jumlah</th></tr></thead>
          <tbody>
            <?php foreach (MlService::KATEGORI as $k): ?>
              <tr><td><?= kategori_badge($k) ?></td><td class="num"><?= e(fmt_num($m['jumlah_per_kategori'][$k] ?? 0, 0)) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="help mt-3">Silhouette score berkisar -1 sampai 1; makin mendekati 1, klaster makin terpisah jelas.</p>
    <?php else: ?>
      <div class="empty"><strong>Belum ada model</strong>File model/metrics.json tidak ditemukan.</div>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 class="card-title">Latih dengan data baru</h2>
    <?php if ($formError): ?><div class="alert alert-error"><?= e($formError) ?></div><?php endif; ?>
    <div class="alert alert-info">
      Model yang sedang dipakai akan <strong>diganti</strong>. Hasil prediksi lama tetap tersimpan dan tidak dihitung ulang,
      jadi kategorinya mengikuti model saat prediksi dibuat.
    </div>
    <form method="post" enctype="multipart/form-data"
          data-confirm="Latih ulang akan mengganti model yang sedang dipakai. Lanjutkan?"
          data-loading="Melatih model, mohon tunggu...">
      <?= csrf_field() ?>
      <div class="field">
        <label class="label" for="file">File Excel (.xlsx, maksimal 5 MB)</label>
        <input class="input" id="file" name="file" type="file" accept=".xlsx" required>
        <div class="help">
          Sheet pertama dipakai. Kolom wajib: <code>rata_rata_nilai, nilai_tugas, nilai_uts, nilai_uas, kehadiran_persen</code>
          (angka 0-100; minimal 10 baris data valid). Kolom <code>status_keberhasilan</code> bersifat opsional.
        </div>
      </div>
      <button class="btn btn-danger" type="submit">Latih ulang model</button>
    </form>
  </section>
</div>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>