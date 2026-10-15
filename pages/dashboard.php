<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$user = require_login();

$pdo = db();
$counts = ['Berhasil' => 0, 'Sedang' => 0, 'Berisiko' => 0];
$st = $pdo->query('SELECT kategori, COUNT(*) AS jml FROM (' . latest_prediksi_sql() . ') t GROUP BY kategori');
foreach ($st->fetchAll() as $r) {
    $counts[$r['kategori']] = (int) $r['jml'];
}
$total  = array_sum($counts);
$recent = $pdo->query('SELECT id, id_siswa, kelas, kategori, jarak_ke_centroid, created_at
                       FROM prediksi ORDER BY id DESC LIMIT 5')->fetchAll();

$pageTitle = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/layout_header.php';
?>
<div class="page-header">
  <h1>Dashboard</h1>
  <p>Ringkasan hasil prediksi terbaru tiap siswa.</p>
</div>

<div class="stat-grid">
  <div class="card stat"><div class="stat-label">Total siswa terprediksi</div><div class="stat-value"><?= $total ?></div></div>
  <?php foreach ($counts as $kat => $jml): ?>
    <div class="card stat"><div class="stat-label"><?= kategori_badge($kat) ?></div><div class="stat-value"><?= $jml ?></div></div>
  <?php endforeach; ?>
</div>

<div class="grid-dash">
  <section class="card">
    <h2 class="card-title">Distribusi kategori</h2>
    <?php if ($total === 0): ?>
      <div class="empty"><strong>Belum ada data</strong>Grafik muncul setelah ada prediksi.</div>
    <?php else: ?>
      <div class="chart-box"><canvas id="chartKategori"></canvas></div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="result-head">
      <h2>5 prediksi terakhir</h2>
      <a class="btn btn-secondary btn-sm" href="<?= e(url('pages/prediksi.php')) ?>">Prediksi baru</a>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Waktu</th><th>NIS</th><th>Kelas</th><th>Kategori</th><th class="num">Jarak</th></tr></thead>
        <tbody>
        <?php if (!$recent): ?>
          <tr><td colspan="5"><div class="empty"><strong>Belum ada prediksi</strong>Mulai dari menu Prediksi Siswa.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td class="nowrap"><?= e(fmt_dt($r['created_at'])) ?></td>
            <td><?= e($r['id_siswa'] ?? '-') ?></td>
            <td><?= e($r['kelas'] ?? '-') ?></td>
            <td><?= kategori_badge($r['kategori']) ?></td>
            <td class="num"><?= e(fmt_num($r['jarak_ke_centroid'], 4)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php
if ($total > 0) {
    $data = json_encode(array_values($counts), JSON_THROW_ON_ERROR);
    $extraScripts = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  var css = getComputedStyle(document.documentElement);
  var warna = ['--status-berhasil-solid', '--status-sedang-solid', '--status-berisiko-solid']
    .map(function (v) { return css.getPropertyValue(v).trim(); });
  new Chart(document.getElementById('chartKategori'), {
    type: 'doughnut',
    data: { labels: ['Berhasil', 'Sedang', 'Berisiko'], datasets: [{ data: {$data}, backgroundColor: warna, borderWidth: 0 }] },
    options: { maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom' } } }
  });
})();
</script>
HTML;
}
require __DIR__ . '/../includes/layout_footer.php';