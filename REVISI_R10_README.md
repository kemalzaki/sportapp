# Revisi 22 Juni 2026 R10 — Catatan Perubahan (Partial)

Zip ini berisi **sebagian** file revisi (hanya yang berubah). Salin/timpa
ke folder `sportapp_core` Anda. Struktur folder dipertahankan.

## Daftar file di zip ini

```
migrations_r10.sql          (BARU — jalankan di HeidiSQL/psql)
includes/ai_gemini.php      (updated — handle "high demand" / 503)
tempat.php                  (updated — section hiking & camping dihapus)
tempat_list.php             (updated — section hiking & camping ditambahkan)
REVISI_R10_README.md        (file ini)
```

> Tidak ada file `.sql` data yang ikut di-zip (sportapp.sql aslinya tetap di
> folder Anda — tidak dihapus / dibongkar). Hanya satu file migrasi schema
> (`migrations_r10.sql`) yang perlu Anda jalankan SEKALI.

---

## Revisi #1 — Error "no unique or exclusion constraint matching the ON CONFLICT specification"

Error ini muncul karena beberapa tabel di PostgreSQL Anda **belum punya
UNIQUE / PRIMARY KEY** pada kolom yang dipakai oleh klausa `ON CONFLICT`
di kode PHP. Ini terjadi misalnya saat halaman:

- `index.php` (quick check-in absensi / sapa antar member)
- `admin/jadwal.php` (insert jadwal lalu trigger `apply_kondisi_to_absensi`
  menyentuh tabel `absensi`)
- `admin/absensi.php` (lewat `wa_notify_event` / fungsi terkait)
- `kalori_mingguan.php` (target / setting defisit kalori)

### Solusi — jalankan SEKALI di HeidiSQL

1. Buka tab **Query** di HeidiSQL.
2. **Buka file** `migrations_r10.sql` (File → Load SQL file).
3. Tekan **F9** untuk menjalankan seluruh isi.
4. Tidak ada output `ERROR` → migrasi berhasil. Aman dijalankan ulang
   berkali-kali (idempotent).

`migrations_r10.sql` adalah superset dari `migrations_r9.sql` — Anda
**tidak perlu** lagi menjalankan r9 jika sudah menjalankan r10.

Tabel yang ditambahi UNIQUE / PRIMARY KEY oleh r10:

| Tabel                     | UNIQUE / PRIMARY KEY                |
|---------------------------|-------------------------------------|
| `kalori_target`           | (user_id)                           |
| `kalori_defisit_setting`  | (user_id)                           |
| `absensi`                 | (jadwal_id, user_id)                |
| `app_settings`            | (skey)                              |
| `fcm_tokens`              | (user_id, token)                    |
| `user_device_loc`         | (user_id)                           |
| `user_notif_state`        | (user_id)                           |
| `post_views`              | (post_id, user_id)                  |
| `post_bookmarks`          | (user_id, post_id)                  |
| `doa_aamiin`              | (doa_id, user_id)                   |
| `gaya_hidup_log`          | (user_id, tanggal)                  |
| `user_olahraga_favorit`   | (user_id, nama)                     |
| `user_kondisi`            | (user_id)                           |
| `user_quran_catatan`      | (user_id, surah, ayat)              |
| `user_quran_bookmark`     | (user_id)                           |
| `upload_harian_likes`     | (upload_id, user_id)                |
| `strava_tokens`           | (user_id)                           |
| `iptv_channels`           | (url)                               |
| `tim_member`              | (tim_id, user_id)                   |
| `user_islami_pref`        | (user_id)                           |
| `islami_streak`           | (user_id, tanggal)                  |
| `islami_badges`           | (user_id, badge_key)                |
| `challenge_master`        | (kunci)                             |
| `post_hashtags`           | (post_id, tag)                      |
| `post_mentions`           | (post_id, user_id)                  |
| `sapa_log`                | (sender_user_id, target_user_id)    |

Data TIDAK dihapus, kecuali baris duplikat **persis** yang menghalangi
pembuatan UNIQUE constraint (baris ber-`ctid` lebih kecil dibuang, baris
terbaru dipertahankan). Bila tidak ada duplikat, tidak ada data yang
hilang.

Jika ada nama tabel yang **belum ada** di database Anda, migrasi akan
otomatis melewatinya (skip diam-diam) — aman.

---

## Revisi #2 — Error "This model is currently experiencing high demand"

Pesan ini berasal dari Google Gemini API (HTTP 503 / `UNAVAILABLE` /
`overloaded`). Sebelumnya kode tidak menangani 503 sebagai transient,
jadi key tidak dirotasi dan pesan mentah muncul ke user.

### Yang diubah — `includes/ai_gemini.php`

- HTTP 503 / pesan `"overloaded"`, `"high demand"`, `"UNAVAILABLE"`,
  `"try again"` kini terdeteksi sebagai **transient**.
- Jika Anda punya `GEMINI_API_KEY_1`, `GEMINI_API_KEY_2`, …
  (atau `GEMINI_API_KEYS=key1,key2,...`) maka key cadangan **otomatis
  dirotasi** saat key utama overloaded.
- Bila semua key overloaded, user melihat pesan ramah singkat:
  *“Model AI sedang sibuk (high demand). Coba lagi sebentar, atau
  tambahkan GEMINI_API_KEY cadangan agar otomatis dirotasi.”*

Tidak ada perubahan PostgreSQL untuk revisi ini.

---

## Revisi #3 — Pindah section "Tempat Hiking & Camping"

Sebelumnya section "Tempat Hiking & Camping" dengan tombol "Lihat Rute"
ada di `tempat.php` (halaman **Booking Lapangan**) — lokasinya salah
karena tempat hiking/camping bukan untuk booking.

### Yang diubah

- `tempat.php` — section + query `$trails` dihapus dari sini. Halaman
  kembali fokus murni ke booking lapangan.
- `tempat_list.php` — section "Tempat Hiking & Camping" + modal Leaflet
  (peta + polyline dari GPX admin) **ditambahkan di bawah** Daftar
  Tempat Olahraga. Fungsionalitas identik dengan versi lama (data dari
  tabel `tempat` yang `jenis_olahraga = hiking / camping`).

Tidak ada perubahan PostgreSQL untuk revisi ini.

---

## Yang tidak termasuk pada zip ini

Hanya 4 file di atas. Halaman lain tidak berubah pada revisi R10.
