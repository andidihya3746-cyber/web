<?php
// ============================================================
// KONFIGURASI APLIKASI - sesuaikan nilai di bawah ini
// ============================================================
date_default_timezone_set('Asia/Makassar');

// Database (bawaan XAMPP: user root tanpa password)
const DB_HOST = '127.0.0.1';
const DB_NAME = 'prediksi_siswa_db';
const DB_USER = 'root';
const DB_PASS = '';

// Perintah Python. Pakai 'python' jika sudah ada di PATH.
// Jika tidak jalan di XAMPP, isi path lengkap, contoh Windows:
//   'C:/Users/NamaAnda/AppData/Local/Programs/Python/Python312/python.exe'
const PYTHON_BIN = 'C:/Users/andid/AppData/Local/Python/bin/python.exe';

// Folder model dari Anda (isi: predict.py, train.py, xlsx_to_csv.py, model/, data/)
const PREDIKSI_DIR = __DIR__ . '/prediksi_siswa';
const TMP_DIR      = __DIR__ . '/storage/tmp';

const PREDICT_TIMEOUT  = 60;    // detik, untuk predict.py
const TRAIN_TIMEOUT    = 300;   // detik, untuk train.py
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;  // 5 MB
const MAX_CSV_ROWS     = 5000;

const APP_NAME = 'Prediksi Keberhasilan Siswa';
const DEBUG    = false;   // true hanya saat pengembangan

// Alamat dasar aplikasi, dideteksi otomatis dari lokasi folder di htdocs.
// Jika link salah, isi manual, misalnya: define('BASE_URL', '/web');
if (!defined('BASE_URL')) {
    $docRoot = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $appRoot = str_replace('\\', '/', (string) realpath(__DIR__));
    define('BASE_URL', ($docRoot !== '' && strncasecmp($appRoot, $docRoot, strlen($docRoot)) === 0)
        ? substr($appRoot, strlen($docRoot)) : '');
}