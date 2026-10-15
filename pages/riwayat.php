<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_login();

const PER_PAGE = 15;
$kelas = trim((string) ($_GET['kelas'] ?? ''));
$kat   = (string) ($_GET['kategori'] ?? '');
if (!in_array($kat, MlService::KATEGORI, true)) {
    $kat = '';
}
$where = [];
$params = [];
if ($kelas !== '') {
    $where[] = 'kelas = ?';
    $params[] = $kelas;
}
if ($kat !== '') {
    $where[] = 'kategori = ?';
    $params[] = $kat;
}
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$pdo = db();

// ---------- Ekspor CSV (mengikuti filter yang aktif) ----------
if (isset($_GET['export'])) {
    $st = $pdo->prepare("SELECT * FROM prediksi {$w} ORDER BY id DESC");
    $st->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="riwayat-prediksi-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");   // BOM agar Excel membaca UTF-8
    // Pemisah ; dan desimal koma agar langsung rapi di Excel berbahasa Indonesia
    $put = static fn(array $row) => fputcsv($out, $row, ';', '"', '');
    $put(['waktu', 'nis', 'kelas', 'rata_rata_nilai', 'nilai_tugas', 'nilai_uts', 'nilai_uas',
          'kehadiran_persen', 'kategori', 'jarak_ke_centroid', 'sumber']);
    $n = static fn($v, int $d = 2) => number_format((float) $v, $d, ',', '');
    while ($r = $st->fetch()) {
        $put([$r['created_at'], csv_safe($r['id_siswa']), csv_safe($r['kelas']), $n($r['rata_rata_nilai']),
              $n($r['nilai_tugas']), $n($r['nilai_uts']), $n($r['nilai_uas']), $n($r['kehadiran_persen']),
              $r['kategori'], $n($r['jarak_ke_centroid'], 4), $r['sumber']]);
    }
    fclose($out);
    exit;
}

$st = $pdo->prepare("SELECT COUNT(*) FROM prediksi {$w}");
$st->execute($params);
$total = (int) $st->fetchColumn();
$pages = max(1, (int) ceil($total / PER_PAGE));
$page  = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

$st = $pdo->prepare("SELECT * FROM prediksi {$w} ORDER BY id DESC LIMIT ? OFFSET ?");
$i = 1;
foreach ($params as $p) {
    $st->bindValue($i++, $p, PDO::PARAM_STR);
}
$st->bindValue($i++, PER_PAGE, PDO::PARAM_INT);
$st->bindValue($i, ($page - 1) * PER_PAGE, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

$filterQuery = array_filter(['kelas' => $kelas, 'kategori' => $kat], static fn($v) => $v !== '');

$pageTitle = 'Riwayat';
$active = 'riwayat';
require __DIR__ . '/../includes/layout_header.php';
?>
<div class="page-header">
  <h1>Riwayat prediksi</h1>
  <p>Semua hasil prediksi yang pernah dibuat.</p>
</div>

<section class="card">
  <form method="get" class="toolbar">
    <select class="select" name="kelas" aria-label="Filter kelas">
      <option value="">Semua kelas</option>
      <?php foreach (kelas_tersedia() as $k): ?>
        <option value="<?= e($k) ?>"<?= $kelas === (string) $k ? ' selected' : '' ?>><?= e($k) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="select" name="kategori" aria-label="Filter kategori">
      <option value="">Semua kategori</option>
      <?php foreach (MlService::KATEGORI as $k): ?>
        <option value="<?= e($k) ?>"<?= $kat === $k ? ' selected' : '' ?>><?= e($k) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary" type="submit">Terapkan</button>
    <?php if ($filterQuery): ?><a class="btn btn-secondary" href="<?= e(url('pages/riwayat.php')) ?>">Reset</a><?php endif; ?>
    <a class="btn btn-secondary spacer" href="<?= e('?' . http_build_query($filterQuery + ['export' => 1])) ?>">Ekspor CSV</a>
  </form>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Waktu</th><th>NIS</th><th>Kelas</th><th class="num">Rata-rata</th><th class="num">Tugas</th><th class="num">UTS</th>
            <th class="num">UAS</th><th class="num">Hadir (%)</th><th>Kategori</th><th class="num">Jarak</th></tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="10"><div class="empty"><strong>Belum ada data</strong>Belum ada prediksi yang cocok dengan filter ini.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="nowrap"><?= e(fmt_dt($r['created_at'])) ?></td>
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
      </tbody>
    </table>
  </div>
  <?= render_pagination($page, $pages, $filterQuery, $total, PER_PAGE) ?>
</section>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>