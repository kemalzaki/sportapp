# Revisi 29 Juni 2026 — SportApp Core

Berisi file yang DIREVISI saja (drop-in: timpa file dengan path yang sama).

## Daftar file
- `admin/menu.php` — auto-seed 42 menu drawer + dukungan multi-paket (CSV) + tombol Reset Drawer
- `includes/menu_render.php` — render multi-paket (mis. "pro,komunitas") menjadi 2 badge
- `includes/header.php` — GLOBAL auth guard (semua halaman wajib login), inject SweetAlert2, override `confirm()` agar popup cantik tanpa alamat URL, hapus label "Baru" di 4 menu
- `includes/bottom_nav.php` — FAB +Upload dipoles ulang (lingkaran lebih besar, gradient + ring pulse, label rapi)
- `paket_upgrade.php` — Snap.js Midtrans loader tahan banting (async, fallback URL, tunggu max 8 detik, pesan error informatif bila MIDTRANS_CLIENT_KEY belum di-set)
- `monitoring_tahajud.php` — popup SweetAlert dengan kolom **Keterangan** untuk input Tahajud/Duha (sebelumnya cuma `prompt()` rakaat). Handler `ssunnah_toggle` di `islami.php` SUDAH menerima `catatan`, jadi tidak perlu diubah.
- `opini_viral.php` — sumber Google News RSS dijadikan PRIMARY (Reddit/Nitter sering gagal → data tidak muncul). Cards sekarang konsisten terisi.

## Catatan PostgreSQL
Tidak ada migrasi manual yang wajib. Semua perubahan skema dijalankan otomatis (idempotent):
- `nav_menu.paket` di-ALTER ke `TEXT` (sebelumnya `VARCHAR(20)`) agar bisa menampung CSV multi-paket.
- Auto-seed drawer hanya jalan kalau tabel `nav_menu` belum punya satu pun baris berposisi `drawer` — jadi data Anda yang sudah ada TIDAK terhapus.

## Cara pakai
1. Backup folder project Anda.
2. Timpa file-file di atas ke struktur yang sama.
3. Buka `/admin/menu.php` sekali — auto-seed akan jalan kalau drawer masih kosong, dan/atau klik tombol **"Reset Drawer ke 42 Menu Default"** untuk paksa re-seed (akan menghapus item drawer lama).
4. Cek `MIDTRANS_CLIENT_KEY` di env Anda — jika belum di-set, halaman `paket_upgrade.php` akan menampilkan pesan jelas (sebelumnya cuma "Snap.js belum ter-load").
5. Buka `/opini_viral.php?refresh=1` untuk paksa refresh cache opini.

## Hal yang TIDAK termasuk di zip ini
- File `sportapp.sql` — tidak ada perubahan skema yang perlu di-restore (auto-ALTER berjalan saat halaman menu dibuka).
- Halaman lain yang tidak relevan dengan revisi.
