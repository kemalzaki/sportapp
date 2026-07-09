<?php
/**
 * Konfigurasi environment lokal.
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
hf_env_set('MIDTRANS_PROD', '1');

// ===== Admin WA =====
hf_env_set('ADMIN_WA_FIRDAM', '6281386369207');

// ============================================================
// ===== AI Router (Revisi Nov 2026 — R13) =====
// ============================================================
// Urutan provider (otomatis fallback):
//   1) OpenRouter — deepseek/deepseek-chat-v3   (PRIMARY)
//   2) OpenRouter — qwen/qwen3-235b-a22b        (fallback)
//   3) Groq       — llama-3.3-70b-versatile     (SECONDARY)
//   4) Gemini     — gemini-2.5-flash            (LAST RESORT)
//
// PENTING! Provider yang API KEY-nya kosong akan DILEWATI otomatis.
// Jika Anda melihat error "Gemini: User location is not supported",
// artinya OpenRouter & Groq dilewati karena key belum diisi di bawah,
// sehingga router langsung jatuh ke Gemini (tidak didukung di Indonesia).
//
// SOLUSI: isi minimal SATU dari OPENROUTER_API_KEY atau GROQ_API_KEY,
// lalu HAPUS tanda "//" di awal barisnya.
// ------------------------------------------------------------

// --- 1) OpenRouter (PRIMARY) — daftar gratis di https://openrouter.ai/keys
// hf_env_set('OPENROUTER_API_KEY', 'sk-or-v1-XXXXXXXXXXXXXXXXXXXXXXXX'); // <-- ISI & UNCOMMENT
hf_env_set('OPENROUTER_MODEL',   'deepseek/deepseek-chat-v3');
hf_env_set('OPENROUTER_MODEL_2', 'qwen/qwen3-235b-a22b');

// --- 2) Groq (SECONDARY) — daftar gratis di https://console.groq.com/keys
// hf_env_set('GROQ_API_KEY', 'gsk_XXXXXXXXXXXXXXXXXXXXXXXX'); // <-- ISI & UNCOMMENT
hf_env_set('GROQ_MODEL',   'llama-3.3-70b-versatile');

// --- 3) Gemini (LAST RESORT) — tidak dapat dipakai dari region tertentu (ID/dll)
// hf_env_set('GEMINI_API_KEY', 'AIzaXXXXXXXXXXXXXXXXX');
hf_env_set('GEMINI_MODEL', 'gemini-2.5-flash');

// Opsional: matikan router (paksa Gemini saja) — jangan diaktifkan
// hf_env_set('AI_ROUTER_DISABLE', '1');
