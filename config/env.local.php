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

// ===== Google Gemini AI (Revisi 16 Juni 2026 — Part E) =====
// WAJIB diisi agar fitur AI berfungsi:
//   - api_run.php (AI Route generator dari foto peta / prompt teks)
//   - kalori_mingguan.php (estimasi kalori dari foto makanan)
//   - monitoring.php (AI Running Coach)
//   - islami.php (Tanya Jawab Islami)
//   - live_tracking.php (AI Safety Monitoring)
//
// Cara dapat API key (GRATIS):
//   1. Buka https://aistudio.google.com/apikey
//   2. Login dengan akun Google
//   3. Klik "Create API key" -> pilih project apa saja
//   4. Copy key-nya (formatnya diawali "AIza...")
//   5. Ganti placeholder di bawah, lalu RESTART Apache/PHP server
//
// PENTING: key HARUS diawali "AIza...". Token "AQ.Ab8RN6..." dari Google Sign-In
// TIDAK akan berfungsi karena itu OAuth browser token, bukan API key server.
hf_env_set('GEMINI_API_KEY', 'AQ.Ab8RN6IL-6ERW08AYymhdutqv5VhxMYakUyRL17hVbzN5Lu0OQ');
hf_env_set('GEMINI_MODEL',   'gemini-2.5-flash');
