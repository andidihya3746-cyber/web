<?php
declare(strict_types=1);

require_once __DIR__ . '/MlService.php';   // untuk MlException

/**
 * Terima satu file unggahan dengan pemeriksaan ketat, simpan ke storage/tmp dengan nama acak.
 * $ext = 'csv' atau 'xlsx'. Mengembalikan path file sementara (hapus sendiri setelah dipakai).
 */
function receive_upload(string $field, string $ext): string
{
    $f = $_FILES[$field] ?? null;
    $err = is_array($f) ? (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) {
        throw new MlException('Pilih file terlebih dahulu.');
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || (int) $f['size'] > MAX_UPLOAD_BYTES) {
        throw new MlException('Ukuran file melebihi batas ' . (MAX_UPLOAD_BYTES / 1048576) . ' MB.');
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
        throw new MlException('Unggah file gagal. Silakan coba lagi.');
    }
    if (strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION)) !== $ext) {
        throw new MlException("File harus berformat .{$ext}.");
    }
    $head = (string) file_get_contents((string) $f['tmp_name'], false, null, 0, 4096);
    if ($ext === 'xlsx' && substr($head, 0, 4) !== "PK\x03\x04") {
        throw new MlException('Isi file bukan file Excel (.xlsx) yang valid.');
    }
    if ($ext === 'csv' && strpos($head, "\0") !== false) {
        throw new MlException('Isi file bukan file CSV teks yang valid.');
    }
    if (!is_dir(TMP_DIR)) {
        @mkdir(TMP_DIR, 0775, true);
    }
    $dest = TMP_DIR . '/' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file((string) $f['tmp_name'], $dest)) {
        throw new MlException('File gagal disimpan di server.');
    }
    return $dest;
}

/** Panjang teks dalam karakter (tanpa bergantung pada ekstensi mbstring). */
function str_len(string $s): int
{
    return (int) preg_match_all('/./su', $s);
}

/** Cegah CSV injection saat ekspor (sel diawali = + - @ dianggap rumus oleh Excel). */
function csv_safe(?string $v): string
{
    $v = (string) $v;
    return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
}

/** Navigasi halaman untuk paginasi sisi server. $query = parameter GET yang dipertahankan. */
function render_pagination(int $page, int $pages, array $query, int $total, int $perPage): string
{
    $from = $total === 0 ? 0 : ($page - 1) * $perPage + 1;
    $to   = min($page * $perPage, $total);
    $link = static function (int $p, string $label, bool $active = false, bool $disabled = false) use ($query): string {
        if ($disabled) {
            return '<button type="button" class="page-btn" disabled>' . $label . '</button>';
        }
        $href = '?' . http_build_query(array_merge($query, ['page' => $p]));
        return '<a class="page-btn' . ($active ? ' active' : '') . '" href="' . e($href) . '">' . $label . '</a>';
    };
    $html = '<div class="pagination"><div class="muted">Menampilkan ' . $from . '-' . $to . ' dari ' . $total . ' data</div>';
    if ($pages > 1) {
        $start = max(1, $page - 2);
        $end   = min($pages, $start + 4);
        $start = max(1, $end - 4);
        $html .= '<div class="pages">' . $link($page - 1, '&lsaquo;', false, $page <= 1);
        for ($i = $start; $i <= $end; $i++) {
            $html .= $link($i, (string) $i, $i === $page);
        }
        $html .= $link($page + 1, '&rsaquo;', false, $page >= $pages) . '</div>';
    }
    return $html . '</div>';
}

/** NIS: angka 4-20 digit. */
function nis_valid(string $v): bool
{
    return (bool) preg_match('/^[0-9]{4,20}$/', $v);
}

/** Nama kelas di sekolah: Macca = kelas X, Mattinulu = kelas XI, Mallempu = kelas XII. */
const KELAS_GRUP = [
    'Macca'     => ['label' => 'Kelas X',   'jumlah' => 9],
    'Mattinulu' => ['label' => 'Kelas XI',  'jumlah' => 8],
    'Mallempu'  => ['label' => 'Kelas XII', 'jumlah' => 7],
];

function kelas_bantuan(): string
{
    return 'Macca 1-9, Mattinulu 1-8, atau Mallempu 1-7';
}

/** Ubah teks kelas ke bentuk baku ("macca  1" -> "Macca 1"); null bila bukan kelas yang dikenal. */
function kelas_kanonik(?string $k): ?string
{
    $k = preg_replace('/\s+/', ' ', trim((string) $k));
    if (!is_string($k) || !preg_match('/^([A-Za-z]+) (\d{1,2})$/', $k, $m)) {
        return null;
    }
    $grup = ucfirst(strtolower($m[1]));
    $no   = (int) $m[2];
    if (!isset(KELAS_GRUP[$grup]) || $no < 1 || $no > KELAS_GRUP[$grup]['jumlah']) {
        return null;
    }
    return $grup . ' ' . $no;
}

/** Opsi dropdown kelas, dikelompokkan per tingkat. $kosong = teks opsi kosong (mis. "Pilih kelas"). */
function kelas_options(string $selected = '', string $kosong = ''): string
{
    $html = $kosong !== '' ? '<option value="">' . e($kosong) . '</option>' : '';
    foreach (KELAS_GRUP as $nama => $g) {
        $html .= '<optgroup label="' . e($g['label'] . ' (' . $nama . ')') . '">';
        for ($i = 1; $i <= $g['jumlah']; $i++) {
            $k = $nama . ' ' . $i;
            $html .= '<option value="' . e($k) . '"' . ($k === $selected ? ' selected' : '') . '>' . e($k) . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html;
}

/** Kunci urut kelas: Macca (X) -> Mattinulu (XI) -> Mallempu (XII), lalu nomor. */
function kelas_urut(string $k): string
{
    static $tingkat = ['Macca' => 1, 'Mattinulu' => 2, 'Mallempu' => 3];
    if (preg_match('/^(Macca|Mattinulu|Mallempu) (\d+)$/', $k, $m)) {
        return sprintf('%d-%02d', $tingkat[$m[1]], (int) $m[2]);
    }
    return '0-' . $k;   // data lama (mis. "11") diletakkan di depan
}

/** Ekspresi ORDER BY SQL dengan urutan yang sama seperti kelas_urut(). */
function kelas_order_sql(string $col): string
{
    return "CASE WHEN {$col} LIKE 'Macca %' THEN 1 WHEN {$col} LIKE 'Mattinulu %' THEN 2 "
         . "WHEN {$col} LIKE 'Mallempu %' THEN 3 WHEN {$col} IS NULL THEN 5 ELSE 0 END, "
         . "CAST(SUBSTRING_INDEX({$col}, ' ', -1) AS UNSIGNED), {$col}";
}

/** Daftar kelas yang pernah muncul di data prediksi, terurut. */
function kelas_tersedia(): array
{
    $list = db()->query('SELECT DISTINCT kelas FROM prediksi WHERE kelas IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
    usort($list, static fn($a, $b) => strcmp(kelas_urut((string) $a), kelas_urut((string) $b)));
    return $list;
}