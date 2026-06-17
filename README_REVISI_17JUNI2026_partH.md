# Revisi 17 Juni 2026 — Part H

## Daftar file dalam ZIP ini
- `admin/tim.php`            — gabungan: mekanisme Pembuatan Tim berdasarkan jadwal + anggota internal & eksternal (dipindah dari `/tim.php` lama, butuh role admin)
- `includes/header.php`      — menu "Pembuatan Tim" sekarang menunjuk ke `/admin/tim.php` dan hanya tampil untuk admin
- `includes/ai_gemini.php`   — `gemini_extract_json()` lebih toleran (fence ``` yang kepotong, trailing koma, control char)
- `api_ai.php`               — `max_tokens` AI Running Coach 700→4096, Tanya Jawab Islami 900→4096, AI route teks dipaksa Indonesia + Nominatim `countrycodes=id`
- `api_run.php`              — AI route dari gambar: prompt dipaksa Indonesia, `max_tokens` 700→4096, fallback parser tidak menelan baris JSON, geocoding `countrycodes=id`
- `kalori_mingguan.php`      — `max_tokens` 300→1024 + fallback regex (`"kalori": 123` atau `123 kkal`) supaya tidak lagi "AI gagal mengurai JSON. Raw: Here is the JSON requested: ```"
- `monitoring.php`           — AI Running Coach Form dipindah ke paling atas pada tampilan mobile (<768px) via JS reposition
- `migrations_revisi_17juni2026.sql` — migrasi tabel `tim_external` (idempotent; sama seperti revisi sebelumnya)

## Catatan deploy
1. **HAPUS** file lama `tim.php` di root (sudah dipindah ke `admin/tim.php`).
2. Timpa file-file di atas pada instalasi lokal Anda.
3. Tidak ada perubahan skema baru — `tim_external` sudah otomatis dibuat saat halaman pertama kali diakses; SQL hanya berjaga-jaga.
4. Jika menjalankan PHP < 8 dan ada error `str_starts_with`, pakai PHP 8.0+ seperti revisi sebelumnya.

## Validasi cepat
- Buka `/admin/tim.php` (admin) → pilih jadwal → buat tim → undang anggota internal & eksternal.
- Buka `/islami.php` → Tanya Jawab Islami: jawaban panjang sekarang utuh.
- Buka `/monitoring.php` di mobile → AI Running Coach muncul di paling atas.
- Buka `/run.php` → AI Route by text/screenshot → koordinat tidak lagi keluar Indonesia.
- Buka `/kalori_mingguan.php` → upload foto makanan dengan "use_ai" → tidak lagi error "Here is the JSON requested:".
