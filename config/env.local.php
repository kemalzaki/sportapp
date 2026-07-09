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
// ===== Universal AI Router =====
// ============================================================
// Semua request AI (chat, OCR, vision, screenshot Strava, parsing
// data olahraga, audio) melewati includes/ai_router.php.
//
// URUTAN PROVIDER (otomatis fallback bila gagal):
//   TEXT:
//     1) OpenRouter — OPENROUTER_FREE_MODEL (openrouter/free)  PRIMARY
//     2) OpenRouter — OPENROUTER_MODEL (deepseek/deepseek-chat-v3)
//     3) Groq       — GROQ_MODEL (llama-3.3-70b-versatile)
//     4) Gemini     — GEMINI_MODEL (gemini-2.5-flash)          LAST
//   VISION:
//     1) OpenRouter — OPENROUTER_FREE_MODEL (vision gratis)    PRIMARY
//     2) Gemini     — GEMINI_MODEL                             LAST
//
// PENTING! Provider yang API KEY-nya kosong DILEWATI otomatis.
// Semua model WAJIB dari env di bawah (tidak ada hardcode di source).
//
// SOLUSI region: isi OPENROUTER_API_KEY (dan/atau GROQ_API_KEY),
// lalu HAPUS "//" di awal barisnya. Gemini hanya dipakai bila
// seluruh provider sebelumnya gagal.
// ------------------------------------------------------------

// --- 1) OpenRouter (PRIMARY) — daftar gratis di https://openrouter.ai/keys
// hf_env_set('OPENROUTER_API_KEY', 'sk-or-v1-XXXXXXXXXXXXXXXXXXXXXXXX'); // <-- ISI & UNCOMMENT
hf_env_set('OPENROUTER_FREE_MODEL', 'openrouter/free');          // PRIMARY (text + vision)
hf_env_set('OPENROUTER_MODEL',      'deepseek/deepseek-chat-v3'); // SECONDARY (text-only)

// --- 2) Groq (THIRD) — daftar gratis di https://console.groq.com/keys
// hf_env_set('GROQ_API_KEY', 'gsk_XXXXXXXXXXXXXXXXXXXXXXXX'); // <-- ISI & UNCOMMENT
hf_env_set('GROQ_MODEL',   'llama-3.3-70b-versatile');

// --- 3) Gemini (LAST RESORT) — hanya jika semua provider di atas gagal
// hf_env_set('GEMINI_API_KEY', 'AIzaXXXXXXXXXXXXXXXXX');
hf_env_set('GEMINI_MODEL', 'gemini-2.5-flash');

// Opsional: matikan router (paksa Gemini saja) — jangan diaktifkan
// hf_env_set('AI_ROUTER_DISABLE', '1');

