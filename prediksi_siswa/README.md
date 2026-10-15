# Prediksi Keberhasilan Siswa (K-Means)

Mengelompokkan siswa menjadi **Berhasil / Sedang / Berisiko**.

## Instalasi
```
pip install -r requirements.txt
```

## Alur kerja
```
# 1. Ubah Excel -> CSV (hasil: data/dataset_siswa.csv)
python xlsx_to_csv.py "dataset_siswa.xlsx"

# 2. Latih model (hasil di folder model/)
python train.py

# 3. Prediksi (output JSON)
python predict.py --json "{\"nis\":\"250344\",\"kelas\":\"Macca 1\",\"rata_rata_nilai\":80,\"nilai_tugas\":85,\"nilai_uts\":78,\"nilai_uas\":80,\"kehadiran_persen\":95}"
python predict.py --json @contoh/input.json
python predict.py --csv data/siswa_baru.csv
```

## Kolom dataset
| Kolom | Peran |
|---|---|
| `rata_rata_nilai`, `nilai_tugas`, `nilai_uts`, `nilai_uas`, `kehadiran_persen` | **Fitur clustering** (skala 0-100) |
| `nis`, `kelas` | Identitas, ikut ke hasil, tidak dilatih |
| `status_keberhasilan` | Label asli, hanya untuk evaluasi (tidak dilatih) |
| Kolom lain (nilai per mapel, izin/sakit/alpa, usia, dll.) | Diabaikan |

- Kolom **NIS** (`nis`) dipetakan otomatis ke `id_siswa` (nama kolom internal), jadi tidak perlu diganti manual.
- Kelas memakai format: **Macca 1-9** (kelas X), **Mattinulu 1-8** (kelas XI), **Mallempu 1-7** (kelas XII).
- Nama kolom tidak peka huruf besar/kecil (`Nilai_UTS` = `nilai_uts`).
- Untuk mengganti fitur, ubah `FEATURES` dan `LABEL_WEIGHTS` di `config.py`.

## Output train.py (folder model/)
- `model_kmeans.joblib`: scaler + K-Means + pemetaan label (dipakai predict.py)
- `hasil_clustering.csv`: kategori tiap siswa di data latih
- `metrics.json`: Silhouette, Davies-Bouldin, centroid, dan perbandingan dengan `status_keberhasilan`

## Dari PHP
```php
$json = escapeshellarg(json_encode(['nis'=>'250344','kelas'=>'Macca 1','rata_rata_nilai'=>80,'nilai_tugas'=>85,'nilai_uts'=>78,'nilai_uas'=>80,'kehadiran_persen'=>95]));
$hasil = json_decode(shell_exec("python predict.py --json $json"), true);
// $hasil['status'] == 'ok' -> $hasil['hasil'][0]['kategori']
```
Di Windows (cmd), jika quoting bermasalah, tulis input ke file lalu pakai `--json @file.json`.

## Catatan
- Latih ulang (`train.py`) setiap dataset diperbarui.
- Label cluster ditentukan dari skor tertimbang centroid: tertinggi = Berhasil, terendah = Berisiko.