# Catatan Revisi 12 Juni 2026

## Ringkasan
1. **Menu "Gaya Hidup"** mekanismenya diubah → fokus ke **Pola Makan, Pola Tidur, Mood, dan Aspek Psikologi** (stres, kecemasan, motivasi, fokus, catatan refleksi).
2. **Fitur Tren** di `gaya_hidup.php` diperbaiki: `spanGaps:true` (garis tidak putus jika ada hari kosong), dua sumbu Y bermakna (kiri 0-10, kanan 0-100 untuk stres & jam tidur), label tanggal diformat (`dd Mon`), legend di bawah, empty-state ketika belum ada data, warna kategori berbeda untuk tiap metrik.
3. **Menu Gaya Hidup di mobile** dipindah ke dalam grup **Kalkulator** dan dinamai **"Kalkulator Gaya Hidup"**. Desktop dropdown ikut di-rename.
4. **Menu IPTV (admin)** di drawer mobile dipindah ke dalam grup **Pengaturan Lainnya**.
5. Tambah grup baru di drawer mobile: **"Info dan Wawasan"** berisi: Berita Terkini, IPTV, Kesehatan, Paket Bugar Kalistenik, Artikel Olahraga & Teknik, Panduan Olahraga, Paket Pemanasan, Paket Pendinginan, Cedera Olahraga & Penanganan.
6. Section **"Info & Wawasan"** di `index.php` **dihapus** (sudah dipindah ke menu navigasi mobile). Termasuk semua modal IPTV/Panduan/Pemanasan/Pendinginan yang ada di section tersebut.
7. **Layout index.php** di-reorder via JS: kartu **"Online"** dan **"Jadwal Terdekat"** naik ke paling atas, di atas **"Kabari Member (Koordinator PIC)"**. Urutan baru:
   `dashboard → online → jadwal terdekat → kabari member → story → social feed → forum → event terdekat`.

## File yang diubah
- `gaya_hidup.php` (rewrite total — data lama tetap ada, kolom baru ditambah otomatis lewat `ALTER TABLE ... IF NOT EXISTS`)
- `includes/header.php`
- `index.php`
- `migrations_revisi_12_juni_2026.sql` (baru)

## PostgreSQL yang perlu dijalankan
```bash
psql -U <user> -d <db> -f migrations_revisi_12_juni_2026.sql
```
Aman dijalankan berulang. Tidak menghapus data lama. `gaya_hidup.php` juga melakukan `ALTER TABLE ... IF NOT EXISTS` saat halaman dibuka, jadi migrasi manual ini opsional bila user PHP punya hak ALTER.

## Catatan
- Mobile menu IPTV admin sekarang ada di **Admin → Pengaturan Lainnya → IPTV**.
- Untuk Panduan/Pemanasan/Pendinginan di grup "Info dan Wawasan", link membuka YouTube langsung (sebelumnya popup modal di index). Bila ingin tetap modal, bisa dibuatkan halaman pembungkus tersendiri.