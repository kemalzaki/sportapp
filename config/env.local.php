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
 * Kredensial Midtrans (Sandbox) berdasarkan permintaan pemilik akun:
 *   Merchant ID : G537554248
 *   Client Key  : SB-Mid-client-pwmZyS8VPUrfQqMK
 *   Server Key  : SB-Mid-server-_UnhA5sDl77J0FYIgwKrmONQ
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

// ===== Midtrans (Sandbox) =====
hf_env_set('MIDTRANS_MERCHANT_ID', 'G537554248');
hf_env_set('MIDTRANS_CLIENT_KEY',  'SB-Mid-client-pwmZyS8VPUrfQqMK');
hf_env_set('MIDTRANS_SERVER_KEY',  'SB-Mid-server-_UnhA5sDl77J0FYIgwKrmONQ');
// "0" / "" = sandbox, "1" = production
hf_env_set('MIDTRANS_PROD', '0');

// ===== Admin WA (opsional, untuk tombol "Tanyakan apakah pedagang buka?") =====
hf_env_set('ADMIN_WA_FIRDAM', '6281386369207');
