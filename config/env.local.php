<?php
/**
 * Konfigurasi environment lokal (Midtrans, dsb).
 *
 * File ini di-include otomatis oleh config/db.php sehingga semua halaman
 * yang membaca via getenv() (mis. jajanan.php) akan otomatis mendapatkan
 * nilai-nilai berikut tanpa perlu menyentuh server config.
 *
 * Catatan keamanan:
 *  - Aman untuk lingkungan LOKAL / development.
 *  - Untuk production sebaiknya pindahkan nilai-nilai ini ke environment
 *    variable asli (mis. lewat Apache SetEnv, .htaccess, atau panel host)
 *    lalu hapus / kosongkan file ini.
 *
 * Kredensial Midtrans (PRODUCTION) berdasarkan permintaan pemilik akun:
 *   Merchant ID : G537554248
 *   Client Key  : Mid-client-a0Qdc090d4Z1OSXw
 *   Server Key  : Mid-server-nQ40waJaQMihHi-DnUtxndLH
 */

if (!function_exists('hf_env_set')) {
    function hf_env_set($key, $value) {
        if (getenv($key) === false || getenv($key) === '') {
            putenv($key . '=' . $value);
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// ===== Midtrans (PRODUCTION) =====
hf_env_set('MIDTRANS_MERCHANT_ID', 'G537554248');
hf_env_set('MIDTRANS_CLIENT_KEY',  'Mid-client-a0Qdc090d4Z1OSXw');
hf_env_set('MIDTRANS_SERVER_KEY',  'Mid-server-nQ40waJaQMihHi-DnUtxndLH');
// "0" / "" = sandbox, "1" = production
hf_env_set('MIDTRANS_PROD', '1');

// ===== Admin WA (opsional, untuk tombol "Tanyakan apakah pedagang buka?") =====
hf_env_set('ADMIN_WA_FIRDAM', '6281386369207');

// ===== Google Gemini AI (Revisi 16 Juni 2026 — Part F) =====
// Mulai Part F, key Gemini DEFAULT sudah di-render langsung di
// includes/ai_gemini.php (konstanta GEMINI_API_KEY_DEFAULT). Jadi
// baris di bawah hanya untuk OVERRIDE bila Anda mau pakai API key
// AI Studio sendiri (diawali "AIza..."). Biarkan kosong = pakai
// default yang sudah hardcoded.
//
// Cara dapat API key AI Studio (GRATIS, opsional):
//   1. https://aistudio.google.com/apikey  → Create API key (AIza...)
//   2. Tempel ke parameter kedua di bawah lalu restart Apache/PHP.
// hf_env_set('GEMINI_API_KEY', 'AIzaXXXXXXXXXXXXXXXXX');
hf_env_set('GEMINI_MODEL', 'gemini-2.5-flash');
