<?php
// Idempotent migrations for v7 features
if (!function_exists('hapfam_v7_migrate')) {
function hapfam_v7_migrate(){
  static $done = false; if ($done) return; $done = true;
  try {
    // 1. Pengalaman Hiking & Camping
    db_exec("CREATE TABLE IF NOT EXISTS user_pengalaman (
      id SERIAL PRIMARY KEY,
      user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      jenis VARCHAR(20) NOT NULL,
      judul VARCHAR(160) NOT NULL,
      lokasi VARCHAR(200),
      tanggal DATE,
      deskripsi TEXT,
      foto_url TEXT,
      created_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    // 2. Perlengkapan Olahraga
    db_exec("CREATE TABLE IF NOT EXISTS user_perlengkapan (
      id SERIAL PRIMARY KEY,
      user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      jenis_olahraga_id INTEGER REFERENCES jenis_olahraga(id) ON DELETE SET NULL,
      jenis_nama VARCHAR(80),
      nama VARCHAR(120) NOT NULL,
      jumlah INTEGER NOT NULL DEFAULT 1,
      catatan VARCHAR(200),
      created_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    // 3. Kondisi Terkini (one row per user)
    db_exec("CREATE TABLE IF NOT EXISTS user_kondisi (
      user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
      status VARCHAR(10) NOT NULL DEFAULT 'sehat',
      keterangan TEXT,
      updated_at TIMESTAMP NOT NULL DEFAULT now()
    )");
  } catch (Throwable $e) { /* ignore — log on server */ }
}
}
hapfam_v7_migrate();

// Helper: ketika user mengubah kondisi -> apply ke absensi sesi mendatang
if (!function_exists('apply_kondisi_to_absensi')) {
function apply_kondisi_to_absensi(int $userId, string $status, string $ket = ''): void {
  try {
    if ($status === 'sakit') {
      $note = '[AUTO-SAKIT] '.trim($ket);
      // Untuk setiap jadwal mulai hari ini ke depan, upsert absensi -> sakit
      $rows = db_all("SELECT id FROM jadwal WHERE tanggal >= CURRENT_DATE");
      foreach ($rows as $r) {
        db_exec("INSERT INTO absensi(jadwal_id,user_id,hadir,status,keterangan)
                 VALUES($1,$2,0,'sakit',$3)
                 ON CONFLICT (jadwal_id,user_id) DO UPDATE SET status='sakit', hadir=0, keterangan=EXCLUDED.keterangan
                 WHERE absensi.status<>'hadir'", [(int)$r['id'], $userId, $note]);
      }
    } else { // sehat -> bersihkan auto-sakit di masa depan
      db_exec("DELETE FROM absensi a USING jadwal j
               WHERE a.jadwal_id=j.id AND j.tanggal >= CURRENT_DATE
               AND a.user_id=$1 AND a.status='sakit' AND COALESCE(a.keterangan,'') LIKE '[AUTO-SAKIT]%'", [$userId]);
    }
  } catch (Throwable $e) {}
}
}
