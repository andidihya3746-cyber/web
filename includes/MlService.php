<?php
declare(strict_types=1);

/** Kesalahan yang pesannya aman ditampilkan ke pengguna. */
final class MlException extends RuntimeException {}

/**
 * Satu-satunya titik yang memanggil Python (predict.py, xlsx_to_csv.py, train.py).
 * Tidak memakai shell: perintah dikirim sebagai array sehingga aman dari masalah quoting
 * (termasuk di Windows).
 */
final class MlService
{
    public const FEATURES = ['rata_rata_nilai', 'nilai_tugas', 'nilai_uts', 'nilai_uas', 'kehadiran_persen'];
    public const KATEGORI = ['Berhasil', 'Sedang', 'Berisiko'];

    /** Prediksi satu atau banyak siswa. $rows = list of assoc array. */
    public static function predict(array $rows): array
    {
        if ($rows === []) {
            throw new MlException('Tidak ada data untuk diprediksi.');
        }
        $payload = [];
        foreach (array_values($rows) as $row) {
            $item = [];
            foreach (self::FEATURES as $f) {
                if (!isset($row[$f]) || !is_numeric($row[$f])) {
                    throw new MlException("Field {$f} harus berupa angka.");
                }
                $item[$f] = (float) $row[$f];
            }
            if (isset($row['id_siswa']) && $row['id_siswa'] !== '') {
                $item['id_siswa'] = (string) $row['id_siswa'];   // string agar "0001" tidak jadi 1
            }
            if (isset($row['kelas']) && $row['kelas'] !== '') {
                $item['kelas'] = (string) $row['kelas'];
            }
            $payload[] = $item;
        }

        $file = self::tmpFile('json');
        try {
            file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX);
            $data = self::callPredict(['--json', '@' . $file]);
        } finally {
            @unlink($file);
        }
        return self::normalize($data, count($payload));
    }

    /**
     * Prediksi massal dari file CSV unggahan. CSV dinormalkan dulu (BOM, pemisah ; atau tab)
     * lalu diproses oleh predict.py --csv.
     */
    public static function predictCsv(string $csvPath): array
    {
        $clean = self::normalizeCsv($csvPath);
        try {
            $data = self::callPredict(['--csv', $clean]);
        } finally {
            @unlink($clean);
        }
        return self::normalize($data, null);
    }

    /** Latih ulang: xlsx -> csv -> train.py. Mengembalikan isi model/metrics.json. */
    public static function retrain(string $xlsxPath): array
    {
        $csv = PREDIKSI_DIR . '/data/dataset_siswa.csv';
        $bak = self::tmpFile('bak');
        $hadCsv = is_file($csv) && copy($csv, $bak);
        try {
            [$code, , $err] = self::run(PREDIKSI_DIR . '/xlsx_to_csv.py', [$xlsxPath], 120);
            if ($code !== 0) {
                throw new MlException(self::scriptError('Konversi file Excel gagal', $err));
            }
            [$code, , $err] = self::run(PREDIKSI_DIR . '/train.py', [], TRAIN_TIMEOUT);
            if ($code !== 0) {
                throw new MlException(self::scriptError('Pelatihan model gagal', $err));
            }
        } catch (MlException $e) {
            if ($hadCsv) {
                copy($bak, $csv);   // kembalikan dataset lama jika gagal
            }
            throw $e;
        } finally {
            @unlink($bak);
        }
        $m = self::metrics();
        if ($m === null) {
            throw new MlException('Pelatihan selesai tetapi metrics.json tidak ditemukan.');
        }
        return $m;
    }

    /** Isi model/metrics.json, atau null jika belum ada. */
    public static function metrics(): ?array
    {
        $f = PREDIKSI_DIR . '/model/metrics.json';
        if (!is_file($f)) {
            return null;
        }
        $d = json_decode((string) file_get_contents($f), true);
        return is_array($d) ? $d : null;
    }

    // ------------------------------------------------------------------

    private static function callPredict(array $args): array
    {
        [$code, $out, $err] = self::run(PREDIKSI_DIR . '/predict.py', $args, PREDICT_TIMEOUT);
        $out = trim($out);
        // CSV tanpa id_siswa bisa menghasilkan NaN (bukan JSON valid) -> jadikan null
        $json = preg_replace('/(?<=[:\[,\s])-?(?:NaN|Infinity)(?=\s*[,}\]])/', 'null', $out);
        $data = ($json !== null && $json !== '') ? json_decode($json, true) : null;

        if (!is_array($data) || !isset($data['status'])) {
            error_log('[MlService] code=' . var_export($code, true)
                . ' stdout=' . substr($out, 0, 500) . ' stderr=' . substr($err, 0, 1500));
            throw new MlException(self::friendlyFailure($err));
        }
        if ($data['status'] !== 'ok') {
            throw new MlException((string) ($data['message'] ?? 'Model mengembalikan kesalahan.'));
        }
        return $data;
    }

    private static function normalize(array $data, ?int $expected): array
    {
        $hasil = $data['hasil'] ?? null;
        if (!is_array($hasil) || ($expected !== null && count($hasil) !== $expected)) {
            throw new MlException('Format keluaran model tidak dikenali.');
        }
        $rows = [];
        foreach ($hasil as $r) {
            if (!is_array($r) || !in_array($r['kategori'] ?? null, self::KATEGORI, true)
                || !isset($r['cluster'], $r['jarak_ke_centroid'])) {
                throw new MlException('Format keluaran model tidak dikenali.');
            }
            $row = [
                'id_siswa' => (isset($r['id_siswa']) && $r['id_siswa'] !== '') ? (string) $r['id_siswa'] : null,
                'kelas'    => (isset($r['kelas']) && $r['kelas'] !== '') ? (string) $r['kelas'] : null,
            ];
            foreach (self::FEATURES as $f) {
                $row[$f] = (float) ($r[$f] ?? 0);
            }
            $row['cluster']           = (int) $r['cluster'];
            $row['kategori']          = $r['kategori'];
            $row['jarak_ke_centroid'] = (float) $r['jarak_ke_centroid'];
            $rows[] = $row;
        }
        return $rows;
    }

    /** Jalankan skrip Python. Output ke file sementara (aman di Windows & Linux). */
    private static function run(string $script, array $args, int $timeout): array
    {
        $outFile = self::tmpFile('out');
        $errFile = self::tmpFile('err');
        $env = array_merge(is_array(getenv()) ? getenv() : [], [
            'PYTHONIOENCODING' => 'utf-8',
            'PYTHONUTF8'       => '1',
            'PYTHONWARNINGS'   => 'ignore',
        ]);
        $desc = [0 => ['pipe', 'r'], 1 => ['file', $outFile, 'w'], 2 => ['file', $errFile, 'w']];
        $proc = @proc_open(array_merge([PYTHON_BIN, $script], $args), $desc, $pipes, PREDIKSI_DIR, $env);
        if (!is_resource($proc)) {
            @unlink($outFile);
            @unlink($errFile);
            throw new MlException('Python tidak dapat dijalankan. Periksa PYTHON_BIN di config.php.');
        }
        fclose($pipes[0]);

        $start = microtime(true);
        $code = null;
        while (true) {
            $st = proc_get_status($proc);
            if (!$st['running']) {
                $code = (int) $st['exitcode'];
                break;
            }
            if (microtime(true) - $start > $timeout) {
                proc_terminate($proc);
                proc_close($proc);
                @unlink($outFile);
                @unlink($errFile);
                throw new MlException("Proses model melebihi batas waktu ({$timeout} detik).");
            }
            usleep(100000);
        }
        proc_close($proc);

        $out = (string) @file_get_contents($outFile);
        $err = (string) @file_get_contents($errFile);
        @unlink($outFile);
        @unlink($errFile);
        return [$code, $out, $err];
    }

    private static function friendlyFailure(string $err): string
    {
        if (stripos($err, 'No module named') !== false || stripos($err, 'ModuleNotFoundError') !== false) {
            return 'Library Python belum terpasang. Jalankan: pip install -r requirements.txt '
                 . '(di folder prediksi_siswa).';
        }
        return 'Model prediksi tidak dapat dijalankan. Pastikan Python terpasang dan PYTHON_BIN '
             . 'di config.php sudah benar.';
    }

    private static function scriptError(string $prefix, string $stderr): string
    {
        if (preg_match('/^ERROR:\s*(.+)$/m', $stderr, $m)) {
            return $prefix . ': ' . trim($m[1]);
        }
        error_log('[MlService] ' . $prefix . ' stderr=' . substr($stderr, 0, 1500));
        return $prefix . '. ' . self::friendlyFailure($stderr);
    }

    /** Hapus BOM, deteksi pemisah (, ; tab), tulis ulang sebagai CSV standar. */
    private static function normalizeCsv(string $src): string
    {
        $fh = @fopen($src, 'rb');
        if ($fh === false) {
            throw new MlException('File CSV tidak dapat dibaca.');
        }
        $first = (string) fgets($fh);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $delims = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($delims);
        $delim = (string) array_key_first($delims);
        if (reset($delims) === 0) {
            fclose($fh);
            throw new MlException('Header CSV tidak terbaca. Pastikan baris pertama berisi nama kolom.');
        }
        rewind($fh);
        $dst = self::tmpFile('csv');
        $out = fopen($dst, 'wb');
        $n = 0;
        $isHeader = true;
        while (($row = fgetcsv($fh, 0, $delim)) !== false) {
            if ($isHeader) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
                $row = array_map(static fn($c) => trim((string) $c), $row);
                // Kolom "nis" dipetakan ke "id_siswa" (nama kolom yang dikenal model)
                $lower = array_map('strtolower', $row);
                if (!in_array('id_siswa', $lower, true) && ($pos = array_search('nis', $lower, true)) !== false) {
                    $row[$pos] = 'id_siswa';
                }
                $isHeader = false;
            } else {
                if (count(array_filter($row, static fn($c) => trim((string) $c) !== '')) === 0) {
                    continue;   // lewati baris kosong
                }
                if (++$n > MAX_CSV_ROWS) {
                    fclose($fh);
                    fclose($out);
                    @unlink($dst);
                    throw new MlException('Jumlah baris melebihi batas ' . MAX_CSV_ROWS . '.');
                }
            }
            fputcsv($out, $row);
        }
        fclose($fh);
        fclose($out);
        if ($n === 0) {
            @unlink($dst);
            throw new MlException('File CSV tidak berisi data.');
        }
        return $dst;
    }

    private static function tmpFile(string $ext): string
    {
        if (!is_dir(TMP_DIR)) {
            @mkdir(TMP_DIR, 0775, true);
        }
        return TMP_DIR . '/' . bin2hex(random_bytes(8)) . '.' . $ext;
    }
}