<?php
/**
 * Revisi 3 Jun 2026 — Integrasi Strava (OAuth + import aktivitas).
 *
 * Alur:
 *   1) User klik "Sambungkan Strava" di /profile.php (atau menu) → redirect ke
 *      https://www.strava.com/oauth/authorize?...
 *   2) Strava redirect balik ke: /strava_connect.php?code=...&state=...
 *   3) Kode dipertukarkan jadi access_token & refresh_token, disimpan ke tabel
 *      `user_strava` (lihat SQL di bawah).
 *   4) Endpoint /strava_webhook.php menerima push aktivitas baru dari Strava
 *      lalu otomatis bikin post di feed.
 *
 * ENV VAR yang perlu di-set:
 *   STRAVA_CLIENT_ID      = ID aplikasi Strava
 *   STRAVA_CLIENT_SECRET  = Secret aplikasi Strava
 *   STRAVA_REDIRECT_URI   = https://<domain>/strava_connect.php
 *   STRAVA_VERIFY_TOKEN   = string acak, dipakai untuk verifikasi webhook
 *
 * SQL yang perlu ditambahkan ke PostgreSQL:
 *   CREATE TABLE IF NOT EXISTS user_strava (
 *     user_id        INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
 *     athlete_id     BIGINT,
 *     access_token   TEXT NOT NULL,
 *     refresh_token  TEXT NOT NULL,
 *     expires_at     TIMESTAMP NOT NULL,
 *     connected_at   TIMESTAMP NOT NULL DEFAULT now()
 *   );
 *   CREATE TABLE IF NOT EXISTS strava_activities (
 *     id           BIGINT PRIMARY KEY,
 *     user_id      INTEGER REFERENCES users(id) ON DELETE CASCADE,
 *     name         TEXT,
 *     type         VARCHAR(40),
 *     distance     NUMERIC(10,2),
 *     moving_time  INTEGER,
 *     start_date   TIMESTAMP,
 *     raw          JSONB,
 *     imported_at  TIMESTAMP NOT NULL DEFAULT now()
 *   );
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
send_security_headers();
require_login();
$u = current_user();

$CID = getenv('STRAVA_CLIENT_ID');
$CS  = getenv('STRAVA_CLIENT_SECRET');
$RU  = getenv('STRAVA_REDIRECT_URI') ?: ('https://'.($_SERVER['HTTP_HOST']??'').'/strava_connect.php');

if (!$CID || !$CS) {
  http_response_code(503);
  echo "<h3>Strava belum dikonfigurasi.</h3><p>Set env var <code>STRAVA_CLIENT_ID</code> dan <code>STRAVA_CLIENT_SECRET</code> dulu.</p>";
  exit;
}

// Step 2: Strava redirect balik membawa ?code=...
if (!empty($_GET['code'])) {
  $code = preg_replace('/[^A-Za-z0-9]/','', $_GET['code']);
  $ch = curl_init('https://www.strava.com/oauth/token');
  curl_setopt_array($ch, [
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>http_build_query([
      'client_id'=>$CID,'client_secret'=>$CS,'code'=>$code,'grant_type'=>'authorization_code'
    ]),
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
  ]);
  $body = curl_exec($ch); $code_http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $data = json_decode($body ?: '{}', true);
  if ($code_http >= 200 && $code_http < 300 && !empty($data['access_token'])) {
    $athlete = (int)($data['athlete']['id'] ?? 0);
    $at = $data['access_token']; $rt = $data['refresh_token'];
    $exp = date('Y-m-d H:i:s', (int)$data['expires_at']);
    try {
      db_exec("INSERT INTO user_strava(user_id,athlete_id,access_token,refresh_token,expires_at) VALUES($1,$2,$3,$4,$5)
               ON CONFLICT (user_id) DO UPDATE SET athlete_id=$2,access_token=$3,refresh_token=$4,expires_at=$5",
        [(int)$u['id'],$athlete,$at,$rt,$exp]);
    } catch (Throwable $e) {
      echo "<p>Tabel <code>user_strava</code> belum ada — jalankan SQL di header file ini.</p>"; exit;
    }
    header('Location: /profile.php?strava=ok'); exit;
  }
  echo "<h3>Gagal menyambungkan Strava.</h3><pre>".htmlspecialchars($body)."</pre>";
  exit;
}

// Step 1: arahkan ke halaman authorize Strava
$state = bin2hex(random_bytes(8));
$_SESSION['strava_state'] = $state;
$url = 'https://www.strava.com/oauth/authorize?' . http_build_query([
  'client_id'     => $CID,
  'redirect_uri'  => $RU,
  'response_type' => 'code',
  'approval_prompt'=> 'auto',
  'scope'         => 'read,activity:read_all',
  'state'         => $state,
]);
header('Location: '.$url); exit;
