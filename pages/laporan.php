<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_login();

$kelas = trim((string) ($_GET['kelas'] ?? ''));
$sql = 'SELECT * FROM (' . latest_prediksi_sql() . ') t'
     . ($kelas !== '' ? ' WHERE t.kelas = ?' : '')
     . ' ORDER BY ' . kelas_order_sql('t.kelas') . ', t.id_siswa, t.id';
$st = db()->prepare($sql);
$st->execute($kelas !== '' ? [$kelas] : []);
$rows = $st->fetchAll();

$total = count($rows);
$ringkas = ['Berhasil' => 0, 'Sedang' => 0, 'Berisiko' => 0];
$perKelas = [];
foreach ($rows as $r) {
    $ringkas[$r['kategori']]++;
    $kk = $r['kelas'] ?? '-';
    $perKelas[$kk] ??= ['Berhasil' => 0, 'Sedang' => 0, 'Berisiko' => 0];
    $perKelas[$kk][$r['kategori']]++;
}
$persen = static fn(int $n): string => $total > 0 ? fmt_num($n / $total * 100, 1) . '%' : '-';

$pageTitle = 'Laporan';
$active = 'laporan';
require __DIR__ . '/../includes/layout_header.php';
?>
<div class="page-header no-print">
  <h1>Laporan</h1>
  <p>Rekap hasil prediksi terbaru tiap siswa. Gunakan tombol cetak untuk menyimpan sebagai PDF.</p>
</div>

<form method="get" class="toolbar no-print">
  <select class="select" name="kelas" aria-label="Filter kelas">
    <option value="">Semua kelas</option>
    <?php foreach (kelas_tersedia() as $k): ?>
      <option value="<?= e($k) ?>"<?= $kelas === (string) $k ? ' selected' : '' ?>><?= e($k) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-secondary" type="submit">Terapkan</button>
  <button class="btn btn-primary spacer" type="button" data-print>Cetak / Simpan PDF</button>
</form>

<article class="report">
  <header class="report-title">
    <h2>Laporan Prediksi Keberhasilan Siswa</h2>
    <p>Kelas: <?= $kelas !== '' ? e($kelas) : 'Semua' ?> &middot; Dicetak <?= e(date('d/m/Y H:i')) ?> oleh <?= e($user['nama']) ?></p>
  </header>

  <?php if ($total === 0): ?>
    <div class="empty"><strong>Belum ada data</strong>Belum ada prediksi untuk dilaporkan.</div>
  <?php else: ?>
    <h3 class="card-title">Ringkasan</h3>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Kategori</th><th class="num">Jumlah siswa</th><th class="num">Persentase</th></tr></thead>
        <tbody>
          <?php foreach ($ringkas as $k => $n): ?>
            <tr><td><?= kategori_badge($k) ?></td><td class="num"><?= $n ?></td><td class="num"><?= e($persen($n)) ?></td></tr>
          <?php endforeach; ?>
          <tr><td><strong>Total</strong></td><td class="num"><strong><?= $total ?></strong></td><td class="num">100,0%</td></tr>
        </tbody>
      </table>
    </div>

    <h3 class="card-title mt-4">Rekap per kelas</h3>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Kelas</th><th class="num">Berhasil</th><th class="num">Sedang</th><th class="num">Berisiko</th><th class="num">Total</th></tr></thead>
        <tbody>
          <?php foreach ($perKelas as $kk => $c): ?>
            <tr><td><?= e($kk) ?></td><td class="num"><?= $c['Berhasil'] ?></td><td class="num"><?= $c['Sedang'] ?></td>
                <td class="num"><?= $c['Berisiko'] ?></td><td class="num"><?= array_sum($c) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h3 class="card-title mt-4">Daftar siswa</h3>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>No</th><th>NIS</th><th>Kelas</th><th class="num">Rata-rata</th><th class="num">Tugas</th><th class="num">UTS</th>
              <th class="num">UAS</th><th class="num">Hadir (%)</th><th>Kategori</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= e($r['id_siswa'] ?? '-') ?></td>
              <td><?= e($r['kelas'] ?? '-') ?></td>
              <td class="num"><?= e(fmt_num($r['rata_rata_nilai'])) ?></td>
              <td class="num"><?= e(fmt_num($r['nilai_tugas'])) ?></td>
              <td class="num"><?= e(fmt_num($r['nilai_uts'])) ?></td>
              <td class="num"><?= e(fmt_num($r['nilai_uas'])) ?></td>
              <td class="num"><?= e(fmt_num($r['kehadiran_persen'])) ?></td>
              <td><?= kategori_badge($r['kategori']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</article>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>