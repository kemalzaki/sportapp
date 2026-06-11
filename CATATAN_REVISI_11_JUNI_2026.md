# CATATAN REVISI — 11 Juni 2026

Zip ini berisi **berkas yang direvisi saja** (bukan seluruh project). Salin/timpa ke folder project asli Anda.

## File yang direvisi / baru

| File | Status | Keterangan |
|------|--------|------------|
| `iptv.php` | revisi | Sumber playlist diganti ke `mgi24/tvdigital · idwork.m3u` + blocklist channel. |
| `index.php` | revisi | (1) IPTV modal: sumber baru + blocklist channel. (2) Tambah 3 menu di **Info & Wawasan**: Cedera Olahraga, Kalkulator Detak Jantung, Kalkulator Kesehatan. |
| `riwayat.php` | revisi | (1) Monitoring upload harian (member belum olahraga 1× dalam 7 hari). (2) Dua kalender (publik & saya) yang bisa diklik per tanggal. (3) Like / Comment / Share di Riwayat Aktivitas Publik. |
| `islami.php` | revisi | Tambah menu **Catatan Hafalan**. |
| `catatan_hafalan.php` | **baru** | CRUD Catatan Hafalan (pola mirip Literatur Buku). |
| `cedera_olahraga.php` | **baru** | Info cedera olahraga umum + penanganan + mitigasi (termasuk pingsan). |
| `kalkulator_jantung.php` | **baru** | Kalkulator detak jantung (HRmax, zona, tanda kesehatan). |
| `kalkulator_kesehatan.php` | **baru** | Rekomendasi pace/durasi lari berdasarkan kondisi sakit (pilek, flu, dll). |
| `migrations_revisi_11_juni_2026.sql` | **baru** | Skrip schema PostgreSQL (auto-migration juga sudah ada di file PHP). |

## Yang perlu ditambahkan di PostgreSQL

Hanya **3 tabel baru** — semuanya juga dibuat otomatis saat halaman terkait pertama kali dibuka (auto-migration). Anda bisa juga jalankan `migrations_revisi_11_juni_2026.sql` manual:

1. `upload_harian_likes` — untuk fitur like aktivitas publik (riwayat.php)
2. `upload_harian_comments` — untuk fitur comment aktivitas publik (riwayat.php)
3. `catatan_hafalan` — untuk CRUD catatan hafalan (catatan_hafalan.php)

Tidak ada perubahan/penghapusan data pada tabel existing.

## Catatan IPTV

- Sumber baru: <https://raw.githubusercontent.com/mgi24/tvdigital/main/idwork.m3u>
- Blocklist (case-insensitive, normalisasi resolusi): Music Information Channel, UGTV, U Channel, semua **TVRI**, TV Mu, semua **Stara TV**, Selaparang TV, Rakyat Bengkulu TV, MQTV, PKTV, KTV, Caruban TV, BN Channel.
- Cache 6 jam disimpan di `sys_get_temp_dir()/sportapp_iptv/idwork.m3u`. Hapus file ini bila ingin refresh segera.

## Yang belum termasuk di zip ini

Semua 8 poll permintaan sudah dirangkum di rilis ini. Bila masih ada penyesuaian (mis. tata letak, copy-writing, atau tambahan kondisi sakit lainnya), beritahu dan akan disusun zip lanjutan.
