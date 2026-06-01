# Revisi 3 Juni 2026 — sportapp_core

Arsip ini **HANYA berisi file yang direvisi** (tidak menggantikan semua file).
Salin file-file ini ke folder `sportapp_core/` yang sudah ada, **timpa file lama**.

## Daftar file revisi
| File | Perubahan |
|------|-----------|
| `jajanan.php` | #1 hapus tombol WA "Tanyakan apakah pedagang buka?". #2 tombol & modal "Lacak Driver" realtime via Leaflet + polling 5 dtk + tombol refresh. #4 filter "Buka Sekarang / Tutup" berbasis `jam_buka`/`jam_tutup` server. #5 hero baru dengan SVG dekoratif & kartu produk lebih menarik (overlay "Tutup", chip jam, hover-lift). Endpoint AJAX baru `?ajax=driver_loc`. |
| `index.php` | #3 IPTV kini bisa diputar di **mobile browser** (Chrome/Safari di HP). Yang diblok hanya APK Capacitor (WebView Android). Notice diperbarui + hint khusus mobile. |
| `kurir.php` | Driver dapat menekan tombol "Mulai Berbagi Lokasi" — posisi GPS dikirim otomatis tiap ~10 detik (watchPosition) ke endpoint `_action=push_loc` agar pemesan bisa melacak realtime. |
| `migrations_3jun2026.sql` | **Migrasi PostgreSQL baru** — tambah kolom `driver_lat`, `driver_lng`, `driver_loc_updated_at` pada `jajanan_pesanan`. |

## Migrasi DB (WAJIB dijalankan)
```bash
psql -U <user> -d <nama_db> -f migrations_3jun2026.sql
```
Tidak menghapus data apa pun, hanya `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`.

## Alur tracking realtime (mirip Gojek)
1. Pemesan checkout → status `pending_payment` → `baru` setelah dibayar.
2. Kurir membuka `kurir.php`, klik **"Saya Ambil Order Ini"** → status `diproses`, `kurir_user_id` terisi.
3. Kurir klik **"Mulai Berbagi Lokasi"** → browser meminta GPS → posisi dikirim tiap ±10 dtk.
4. Pemesan buka `jajanan.php` → "Cek Status Pesanan Saya" (nama) → klik **"Lacak Driver"**.
5. Modal Leaflet membuka peta, menampilkan marker UIN, tujuan, dan driver. Polling otomatis tiap 5 dtk + tombol "Refresh" manual.

## Catatan
- Tidak perlu library tambahan — Leaflet & HLS.js di-load via CDN.
- Tidak ada perubahan struktur sensitif. Data lama aman.
- Tetap PHP + PostgreSQL (tidak diubah ke React).
