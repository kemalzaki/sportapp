# Revisi 31 Mei 2026 — Patch

Arsip ini berisi **hanya** file yang berubah. Ekstrak dan timpa file dengan nama
yang sama di project asli (sportapp_core). Database & data tidak diubah.

## File yang berubah

1. `artikel_olahraga.php`
   - Gambar (thumbnail) **Senam, Tinju, Renang, Peregangan** disembunyikan
     (ditampilkan ikon fallback).
   - Artikel **Pemanasan** (judul ber-"panas"), **Plank (latihan)**,
     **Sprint (olahraga)**, dan **Latihan interval** dihapus dari daftar
     Teknik & Latihan.

2. `berita.php`
   - **Pagination 5 berita per halaman** dengan navigasi nomor halaman
     (sebelumnya / berikutnya).
   - **Fitur pencarian** (input bar) — mencari pada judul, ringkasan, dan
     sumber. Parameter URL: `?q=...`.

3. `index.php`
   - **IPTV** diperbaiki: sumber playlist diganti ke
     `https://raw.githubusercontent.com/iptv-org/iptv/master/streams/id.m3u`
     sesuai permintaan (channel Indonesia, file `streams/id.m3u`).
   - **INFO & WAWASAN → "Pilih topik"** sekarang **wajib login**.
     Bila belum login, grid topik diganti kartu CTA "Login dulu".
   - **Total Visitor** ditampilkan di Beranda (kartu gradient hijau-biru)
     dengan dua angka: total seumur waktu + jumlah hari ini.

## Catatan PostgreSQL

Tidak ada tabel yang perlu kamu buat manual — `index.php` akan membuat tabel
`site_visitors` otomatis pada request pertama (`CREATE TABLE IF NOT EXISTS`).
Throttle 1 jam per IP supaya angka tidak terinflasi oleh reload.

Bila kamu ingin meng-eksekusi schema-nya secara eksplisit:

```sql
CREATE TABLE IF NOT EXISTS site_visitors (
    id BIGSERIAL PRIMARY KEY,
    ip VARCHAR(64),
    user_agent TEXT,
    path VARCHAR(255),
    created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_site_visitors_created_at ON site_visitors(created_at);
```

Tidak ada data eksisting yang dihapus / dimodifikasi.
