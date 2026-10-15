<?php
/**
 * Entry Point Utama Aplikasi
 *
 * Berkas ini bertindak sebagai gerbang utama (landing/routing awal).
 * Mengarahkan pengguna secara otomatis berdasarkan status autentikasi/login.
 */

// Mengaktifkan mode tipe data ketat (strict typing) untuk memastikan validasi tipe data PHP
declare(strict_types=1);

// Memuat berkas autentikasi, inisialisasi sesi, konfigurasi sistem, dan fungsi-fungsi pembantu (helpers)
require_once __DIR__ . '/includes/auth.php';

/**
 * Logika Pengalihan Halaman (Routing/Redirect):
 *
 * 1. current_user() :
 *    Memeriksa status login pengguna saat ini.
 *    Mengembalikan data array user jika sesi aktif ditemukan di database, atau null jika belum login.
 *
 * 2. redirect() :
 *    Mengarahkan pengguna ke URL target via header HTTP Location:
 *    - Jika sudah login  -> diarahkan ke halaman 'pages/dashboard.php'
 *    - Jika belum login -> diarahkan ke halaman 'auth/login.php'
 */
redirect(current_user() ? 'pages/dashboard.php' : 'auth/login.php');