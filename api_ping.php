<?php
// Revisi 3 Jun 2026 — Endpoint keep-alive untuk Render free-tier.
// Tujuannya mencegah service "waking up" lama ketika user pertama buka aplikasi.
//
// Cara pakai:
//   1) Pasang Uptime Robot / Cron-Job.org / Better Stack:
//      GET https://<domain-anda>/api_ping.php  setiap 5 menit.
//   2) Service worker & frontend juga otomatis nge-ping endpoint ini di
//      background (lihat snippet di includes/footer.php).
//
// Endpoint sengaja TIDAK menyentuh database agar response cepat & hemat resource.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo json_encode([
  'ok'   => true,
  'ts'   => time(),
  'iso'  => gmdate('c'),
  'host' => $_SERVER['HTTP_HOST'] ?? '',
]);
