# Revisi 17 Juni 2026 — Part I

Berisi 6 perbaikan sesuai permintaan. **PHP + PostgreSQL saja**, tanpa React.

## File yang berubah (timpa file lama dengan path yang sama)

| File di ZIP                                 | Letakkan di proyek                 |
|---------------------------------------------|------------------------------------|
| `islami.php`                                | `/islami.php`                      |
| `api_ai.php`                                | `/api_ai.php`                      |
| `api_run.php`                               | `/api_run.php`                     |
| `run.php`                                   | `/run.php`                         |
| `kalori_mingguan.php`                       | `/kalori_mingguan.php`             |
| `includes/ai_gemini.php`                    | `/includes/ai_gemini.php`          |
| `migrations_revisi_17juni2026_partI.sql`    | jalankan di PostgreSQL (lihat di bawah) |

## Ringkasan perubahan

1. **AI Islami — Simpan Q&A** (`islami.php`): setiap jawaban AI sekarang punya
   tombol **Simpan Q&A ini**. Daftar Q&A tersimpan ada di panel collapse
   "Tanya Jawab Tersimpan" di bawah form. Tiap item bisa dihapus.
2. **AI Islami — anti spam/quota** (`islami.php`): JS sekarang memakai flag
   `isLoading`; tombol dikunci selama request berjalan dan **pertanyaan yang
   sama** tidak akan dikirim ulang ke Gemini (memakai cache jawaban terakhir di
   memori halaman).
3. **Auto-Generate Rute — titik mulai bebas** (`run.php`): di Route Builder
   mode Auto Generate ditambahkan tombol **"Pilih di Peta"** (klik peta untuk
   menetapkan start) dan input **cari alamat/landmark** (geocoding Nominatim
   `countrycodes=id`). Selain itu input `lat,lng` dan tombol "Lokasi saya"
   yang lama tetap ada.
4. **Rute Builder — CRUD rute tersimpan** (`run.php` + `api_run.php`): setiap
   baris rute tersimpan sekarang punya tombol **Edit** (modal: rename, ubah
   preferensi elevasi/surface, toggle publik). Backend `route_update` baru
   ditambahkan di `api_run.php`. Tombol Lihat (eye) dan Hapus (trash) yang lama
   tetap ada.
5. **`run.php` — error "Geocoding gagal untuk: ..."** (`api_ai.php` &
   `api_run.php`): geocoder Nominatim sekarang mencoba **4 variasi query**
   secara berurutan: (a) full query + countrycodes=id, (b) full tanpa
   countrycodes, (c) hanya 2 segmen terakhir + id, (d) hanya nama kota +
   `, Indonesia`. Header `Accept-Language: id,en` ditambahkan. Hampir semua
   landmark yang dulu gagal (mis. "UIN Sunan Gunung Djati Bandung",
   "Perumahan Bumi Panyileukan") sekarang dapat di-resolve.
6. **`kalori_mingguan.php` — quota Gemini exceeded** (`includes/ai_gemini.php`
   & `kalori_mingguan.php`):
   - `ai_gemini.php` kini mendukung **rotasi multi-key** via env
     `GEMINI_API_KEYS=key1,key2,key3` (selain `GEMINI_API_KEY` tunggal yang
     lama). Bila satu key kena 429 / RESOURCE_EXHAUSTED / 401 / 403, sistem
     **otomatis mencoba key berikutnya**.
   - `kalori_mingguan.php` menampilkan pesan yang ramah ke user saat semua key
     habis kuota: berisi instruksi solusi (tunggu / tambah key cadangan).

## PostgreSQL yang perlu dijalankan

Hanya 1 tabel baru: `islami_qa_saved`. File `islami.php` sebenarnya membuatnya
otomatis pada akses pertama, jadi migration ini opsional — jalankan saja untuk
memastikan:

```bash
psql "$DATABASE_URL" -f migrations_revisi_17juni2026_partI.sql
```

Tidak ada perubahan skema lain. **Data lama tidak akan dihapus.**

## Konfigurasi env (opsional, untuk #6)

Tambahkan di `config/env.local.php` atau environment variables sistem:

```php
hf_env_set('GEMINI_API_KEYS', 'AIzaXXXXXXXX,AIzaYYYYYYYY,AQ.ZZZZZZZZ');
```

Tanpa konfigurasi ini, sistem tetap memakai `GEMINI_API_KEY` tunggal seperti
sebelumnya (kompatibel mundur).
