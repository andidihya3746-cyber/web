<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('[DB] ' . $e->getMessage());
            http_response_code(500);
            exit('Tidak dapat terhubung ke database. Pastikan MySQL di XAMPP sudah berjalan '
               . 'dan pengaturan DB di config.php benar.');
        }
    }
    return $pdo;
}

/** Prediksi terbaru tiap siswa (prediksi tanpa id_siswa dihitung satu per satu). */
function latest_prediksi_sql(): string
{
    return 'SELECT p.* FROM prediksi p WHERE p.id_siswa IS NULL
            OR p.id = (SELECT MAX(p2.id) FROM prediksi p2 WHERE p2.id_siswa = p.id_siswa)';
}

/** Tambah siswa baru bila belum ada; perbarui kelas bila diberikan. Mengembalikan id tabel siswa. */
function upsert_siswa(PDO $pdo, string $idSiswa, ?string $kelas): int
{
    $st = $pdo->prepare('SELECT id FROM siswa WHERE id_siswa = ?');
    $st->execute([$idSiswa]);
    $id = $st->fetchColumn();
    if ($id !== false) {
        if ($kelas !== null) {
            $pdo->prepare('UPDATE siswa SET kelas = ? WHERE id = ?')->execute([$kelas, $id]);
        }
        return (int) $id;
    }
    $pdo->prepare('INSERT INTO siswa (id_siswa, kelas, created_at) VALUES (?, ?, ?)')
        ->execute([$idSiswa, $kelas, date('Y-m-d H:i:s')]);
    return (int) $pdo->lastInsertId();
}

/** Simpan satu hasil prediksi (baris hasil dari MlService). Mengembalikan id prediksi. */
function save_prediksi(PDO $pdo, array $r, string $sumber, ?string $batchId, int $userId): int
{
    $siswaId = ($r['id_siswa'] !== null) ? upsert_siswa($pdo, $r['id_siswa'], $r['kelas']) : null;
    $pdo->prepare(
        'INSERT INTO prediksi (siswa_id, id_siswa, kelas, rata_rata_nilai, nilai_tugas, nilai_uts, nilai_uas,
            kehadiran_persen, cluster, kategori, jarak_ke_centroid, sumber, batch_id, user_id, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $siswaId, $r['id_siswa'], $r['kelas'], $r['rata_rata_nilai'], $r['nilai_tugas'], $r['nilai_uts'],
        $r['nilai_uas'], $r['kehadiran_persen'], $r['cluster'], $r['kategori'], $r['jarak_ke_centroid'],
        $sumber, $batchId, $userId, date('Y-m-d H:i:s'),
    ]);
    return (int) $pdo->lastInsertId();
}