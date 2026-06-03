<?php
/**
 * Revisi 3 Jun 2026 — Webhook Strava.
 * Konfigurasi webhook di Strava dashboard mengarah ke URL endpoint ini.
 *  - GET  → validasi hub (Strava verification handshake)
 *  - POST → event aktivitas baru / update / delete. Auto-import ke tabel
 *           strava_activities + bisa diteruskan jadi post di feed.
 */
require __DIR__.'/config/db.php';
header('Content-Type: application/json');

$verify = getenv('STRAVA_VERIFY_TOKEN') ?: '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $mode = $_GET['hub_mode'] ?? '';
  $tok  = $_GET['hub_verify_token'] ?? '';
  $cha  = $_GET['hub_challenge'] ?? '';
  if ($mode === 'subscribe' && $verify !== '' && hash_equals($verify, $tok)) {
    echo json_encode(['hub.challenge' => $cha]); exit;
  }
  http_response_code(403); echo json_encode(['error'=>'invalid_token']); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $raw = file_get_contents('php://input');
  $evt = json_decode($raw ?: '{}', true);
  // Catat ke log sederhana — proses lebih lanjut bisa di-cron.
  try {
    db_exec("CREATE TABLE IF NOT EXISTS strava_webhook_log (
      id SERIAL PRIMARY KEY, payload JSONB, created_at TIMESTAMP NOT NULL DEFAULT now())");
    db_exec("INSERT INTO strava_webhook_log(payload) VALUES($1::jsonb)", [json_encode($evt)]);
  } catch (Throwable $e) {}
  // TODO: fetch detail aktivitas (GET /api/v3/activities/{id}) memakai access_token
  // dari tabel user_strava (lihat strava_connect.php) lalu insert ke strava_activities
  // dan buat post di tabel posts dengan caption "Aktivitas Strava: <name>".
  http_response_code(200); echo json_encode(['ok'=>true]); exit;
}

http_response_code(405); echo json_encode(['error'=>'method_not_allowed']);
