<?php
/**
 * ============================================================
 *  includes/ai_gemini.php  —  DEPRECATED SHIM
 * ============================================================
 *  File ini TIDAK LAGI menjadi provider AI. Seluruh logika AI
 *  sekarang berada di includes/ai_router.php (Universal AI Router).
 *
 *  Shim ini hanya mempertahankan nama fungsi LAMA agar kode lama
 *  yang belum di-refactor tetap berjalan. Semua fungsi di-alias
 *  ke Universal AI Router:
 *
 *      gemini_text()          -> ai_chat()
 *      gemini_vision()        -> ai_vision()
 *      gemini_audio()         -> ai_audio()
 *      gemini_extract_json()  -> ai_extract_json()
 *      gemini_config_status() -> ai_config_status()
 *
 *  JANGAN menambahkan pemanggilan langsung ke Gemini API di sini.
 * ============================================================
 */

require_once __DIR__ . '/ai_router.php';

if (!function_exists('gemini_text')) {
    function gemini_text($prompt, array $opts = []) { return ai_chat($prompt, $opts); }
}
if (!function_exists('gemini_vision')) {
    function gemini_vision($prompt, $imagePath, array $opts = []) { return ai_vision($prompt, $imagePath, $opts); }
}
if (!function_exists('gemini_audio')) {
    function gemini_audio($prompt, $audioPath, array $opts = []) { return ai_audio($prompt, $audioPath, $opts); }
}
if (!function_exists('gemini_extract_json')) {
    function gemini_extract_json($text) { return ai_extract_json($text); }
}
if (!function_exists('gemini_config_status')) {
    function gemini_config_status() { return ai_config_status(); }
}
