"""
Konfigurasi bersama untuk train.py dan predict.py.
Semua nama kolom ditulis huruf kecil (header CSV otomatis diubah ke huruf kecil).
"""

# Kolom fitur numerik yang dipakai untuk clustering.
# Rata_Rata_Nilai sudah merupakan rata-rata 4 mapel, jadi nilai per mapel tidak dipakai (redundan).
# Kehadiran_Persen sudah merangkum izin/sakit/alpa, jadi ketiganya tidak dipakai.
FEATURES = [
    "rata_rata_nilai",
    "nilai_tugas",
    "nilai_uts",
    "nilai_uas",
    "kehadiran_persen",
]

# Kolom identitas/info yang ikut dibawa ke hasil tetapi TIDAK dilatih.
ID_COLUMNS = ["id_siswa", "kelas"]

# Kolom label asli di dataset (opsional). Tidak dipakai melatih K-Means,
# hanya untuk mengevaluasi seberapa cocok cluster dengan status sebenarnya.
LABEL_COLUMN = "status_keberhasilan"
POSITIVE_LABEL = "berhasil"

# Rentang nilai valid untuk semua fitur (skala 0-100, kehadiran dalam persen).
VALUE_MIN = 0
VALUE_MAX = 100

# Jumlah cluster sesuai proposal: Berhasil, Sedang, Berisiko.
N_CLUSTERS = 3
LABELS_HIGH_TO_LOW = ["Berhasil", "Sedang", "Berisiko"]

# Bobot tiap fitur untuk MENENTUKAN label cluster (bukan untuk clustering).
# Cluster dengan skor tertimbang tertinggi -> "Berhasil", terendah -> "Berisiko".
LABEL_WEIGHTS = {
    "rata_rata_nilai": 2.0,
    "nilai_tugas": 1.0,
    "nilai_uts": 1.0,
    "nilai_uas": 1.0,
    "kehadiran_persen": 1.0,
}

RANDOM_STATE = 42
MIN_ROWS = 10  # minimal jumlah data bersih untuk melatih model
