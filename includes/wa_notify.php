<?php
/**
 * Revisi 3 Jun 2026 — Helper Notifikasi WhatsApp.
 *
 * Mode default: "click-to-chat" tanpa biaya (https://wa.me/<nomor>?text=...).
 * Server akan menyimpan link wa.me ke tabel `notifications` + mengirim push FCM
 * berisi link tersebut sehingga ketika user/admin tap notifikasi → langsung
 * buka WhatsApp dengan pesan sudah ter-prefill.
 *
 * Jika di masa depan ingin pakai WA Cloud API (otomatis kirim tanpa interaksi),
 * isi env var: WA_CLOUD_TOKEN + WA_PHONE_ID. Helper di bawah akan otomatis
 * memakai Cloud API jika kedua env tersebut tersedia.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notifications.php';

if (!function_exists('wa_format_no')) {
function wa_format_no(?string $raw): string {
  $n = preg_replace('/\D+/', '', (string)$raw);
  if ($n === '') return '';
  if (str_starts_with($n, '0'))  $n = '62' . substr($n, 1);
  if (str_starts_with($n, '+')) $n = substr($n, 1);
  return $n;
}}

if (!function_exists('wa_link')) {
function wa_link(?string $nomor, string $pesan): string {
  $no = wa_format_no($nomor);
  if ($no === '') return '';
  return 'https://wa.me/' . $no . '?text=' . rawurlencode($pesan);
}}

if (!function_exists('wa_send_cloud')) {
function wa_send_cloud(string $to, string $pesan): bool {
  $tok = getenv('WA_CLOUD_TOKEN'); $pid = getenv('WA_PHONE_ID');
  if (!$tok || !$pid) return false;
  $to = wa_format_no($to); if ($to === '') return false;
  $ch = curl_init("https://graph.facebook.com/v20.0/{$pid}/messages");
  curl_setopt_array($ch, [
    CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok,'Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode([
      'messaging_product'=>'whatsapp','to'=>$to,'type'=>'text',
      'text'=>['preview_url'=>true,'body'=>$pesan]
    ]),
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>6,
  ]);
  $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return $code >= 200 && $code < 300;
}}

/**
 * Kirim notifikasi WA ke 1 user.
 * - Tersimpan ke tabel notifications (in-app + FCM) berisi link wa.me
 * - Jika WA Cloud API aktif → langsung kirim ke nomor WA user
 */
if (!function_exists('wa_notify_user')) {
function wa_notify_user(int $userId, string $judul, string $pesan, string $jenis = 'wa'): void {
  try {
    $row = db_one("SELECT nama, nomor_wa FROM users WHERE id=$1", [$userId]);
    if (!$row) return;
    $link = wa_link($row['nomor_wa'] ?? '', $pesan);
    notify($userId, $jenis, $judul, $pesan, $link ?: '/');
    wa_send_cloud($row['nomor_wa'] ?? '', $pesan);
  } catch (Throwable $e) { /* silent */ }
}}

/**
 * Kirim ke seluruh peserta event/jadwal (digunakan saat admin input jadwal/absensi).
 */
if (!function_exists('wa_notify_event')) {
function wa_notify_event(int $jadwalId, string $judul, string $pesan): void {
  try {
    $rows = db_all("SELECT user_id FROM absensi WHERE jadwal_id=$1", [$jadwalId]);
    if (!$rows) $rows = db_all("SELECT id AS user_id FROM users WHERE role IN ('member','admin')");
    foreach ($rows as $r) wa_notify_user((int)$r['user_id'], $judul, $pesan, 'event');
  } catch (Throwable $e) {}
}}

/**
 * Kirim ke seluruh admin PIC untuk mengingatkan kabari member masing-masing.
 * Memerlukan kolom `pic_user_id` di tabel users (FK ke admin yang menanganinya).
 */
if (!function_exists('wa_notify_pic_admins')) {
function wa_notify_pic_admins(string $judul, string $pesan): void {
  try {
    $pics = db_all("SELECT DISTINCT pic_user_id FROM users WHERE pic_user_id IS NOT NULL");
    foreach ($pics as $p) {
      $pid = (int)$p['pic_user_id'];
      $cnt = (int)db_val("SELECT COUNT(*) FROM users WHERE pic_user_id=$1", [$pid]);
      wa_notify_user($pid, $judul, $pesan." (Anda PIC untuk {$cnt} member)", 'pic');
    }
  } catch (Throwable $e) {}
}}
