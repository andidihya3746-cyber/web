<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_login();

$formError = '';
$batchNew = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    @set_time_limit(180);
    $tmp = null;
    try {
        $tmp  = receive_upload('file', 'csv');
        $rows = MlService::predictCsv($tmp);
        foreach ($rows as $i => $r) {
            $baris = 'Baris data ke-' . ($i + 1);
            if ($r['id_siswa'] !== null && !nis_valid($r['id_siswa'])) {
                throw new MlException($baris . ': NIS "' . substr($r['id_siswa'], 0, 30) . '" tidak valid (harus angka 4-20 digit).');
            }
            if ($r['kelas'] !== null) {
                $baku = kelas_kanonik($r['kelas']);
                if ($baku === null) {
                    throw new MlException($baris . ': kelas "' . substr($r['kelas'], 0, 30) . '" tidak dikenal. Gunakan ' . kelas_bantuan() . '.');
                }
                $rows[$i]['kelas'] = $baku;
            }
        }
        $batch = bin2hex(random_bytes(8));
        $pdo = db();
        $pdo->beginTransaction();
        try {
            foreach ($rows as $r) {
                save_prediksi($pdo, $r, 'massal', $batch, (int) $user['id']);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $batchNew = $batch;
    } catch (MlException $e) {
        $formError = $e->getMessage();
    } finally {
        if ($tmp !== null) {
            @unlink($tmp);
        }
    }
    if ($batchNew !== null) {
        redirect('pages/massal.php?batch=' . $batchNew);   // cegah kirim ulang saat refresh
    }
}

$hasil = [];
$ringkas = ['Berhasil' => 0, 'Sedang' => 0, 'Berisiko' => 0];
$batch = (string) ($_GET['batch'] ?? '');
if (preg_match('/^[a-f0-9]{16}$/', $batch)) {
    $st = db()->prepare('SELECT * FROM prediksi WHERE batch_id = ? ORDER BY id');
    $st->execute([$batch]);
    $hasil = $st->fetchAll();
    foreach ($hasil as $r) {
        $ringkas[$r['kategori']]++;
    }
}

$pageTitle = 'Prediksi Massal';
$active = 'massal';
require __DIR__ . '/../includes/layout_header.php';
?>
<div class="page-header">
  <h1>Prediksi massal</h1>
  <p>Unggah file CSV berisi banyak siswa sekaligus.</p>
</div>

<section class="card">
  <h2 class="card-title">Unggah file CSV</h2>
  <?php if ($formError): ?><div class="alert alert-error"><?= e($formError) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" data-loading="Menjalankan model prediksi...">
    <?= csrf_field() ?>
    <div class="field">
      <label class="label" for="file">File CSV (maksimal 5 MB)</label>
      <input class="input" id="file" name="file" type="file" accept=".csv,text/csv" required>
      <div class="help">
        Kolom wajib: <code>rata_rata_nilai, nilai_tugas, nilai_uts, nilai_uas, kehadiran_persen</code> (angka 0-100).
        Kolom opsional: <code>nis</code> (angka) dan <code>kelas</code> (contoh: <code>Macca 1</code>, <code>Mattinulu 2</code>, <code>Mallempu 3</code>).
        Nama kolom tidak peka huruf besar/kecil.
      </div>
    </div>
    <button class="btn btn-primary" type="submit">Proses prediksi</button>
  </form>
</section>

<?php if ($hasil): ?>
<section class="card mt-4">
  <div class="result-head">
    <h2>Hasil: <?= count($hasil) ?> siswa</h2>
    <div class="btn-group">
      <?php foreach ($ringkas as $k => $n): ?><span><?= kategori_badge($k) ?> <strong><?= $n ?></strong></span><?php endforeach; ?>
    </div>
  </div>
  <div data-datatable data-page-size="15">
    <div class="toolbar">
      <input class="input grow" type="search" placeholder="Cari NIS, kelas, atau nilai..." data-dt-search>
      <select class="select" data-dt-filter>
        <option value="">Semua kategori</option>
        <?php foreach (MlService::KATEGORI as $k): ?><option value="<?= e($k) ?>"><?= e($k) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>NIS</th><th>Kelas</th><th class="num">Rata-rata</th><th class="num">Tugas</th><th class="num">UTS</th>
              <th class="num">UAS</th><th class="num">Hadir (%)</th><th>Kategori</th><th class="num">Jarak</th></tr>
        </thead>
        <tbody>
          <?php foreach ($hasil as $r): ?>
            <tr data-row data-kategori="<?= e($r['kategori']) ?>">
              <td><?= e($r['id_siswa'] ?? '-') ?></td>
              <td><?= e($r['kelas'] ?? '-') ?></td>
              <td class="num"><?= e(fmt_num($r['rata_rata_nilai'])) ?></td>
              <td class="num"><?= e(fmt_num($r['nilai_tugas'])) ?></td>
              <td class="num"><?= e(fmt_num($r['nilai_uts'])) ?></td>
              <td class="num"><?= e(fmt_num($r['nilai_uas'])) ?></td>
              <td class="num"><?= e(fmt_num($r['kehadiran_persen'])) ?></td>
              <td><?= kategori_badge($r['kategori']) ?></td>
              <td class="num"><?= e(fmt_num($r['jarak_ke_centroid'], 4)) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr data-dt-empty hidden><td colspan="9"><div class="empty"><strong>Tidak ada data yang cocok</strong>Ubah kata kunci atau filter.</div></td></tr>
        </tbody>
      </table>
    </div>
    <div class="pagination"><div class="muted" data-dt-info></div><div class="pages" data-dt-pages></div></div>
  </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>