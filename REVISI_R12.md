# Revisi R12 — Ringkasan Perubahan

Perubahan hanya pada file PHP di bawah. **Tidak ada perubahan skema/PostgreSQL** — semua tabel & data existing tetap dipakai apa adanya. Tidak ada SQL tambahan yang harus dijalankan.

## File yang direvisi

1. **includes/header.php**
   - `nav_feature_paket_map`:
     - `gaya_hidup.php` : `['pro','komunitas']` → `['pro']` (badge di drawer jadi hanya **Pro**).
     - `artikel_olahraga.php` : `['pro','komunitas']` → `['pro']` (badge di drawer jadi hanya **Pro**).

2. **kesehatan.php** (Penyakit Umum & Obat Herbal)
   - Ditambahkan gating paket: `paket_require_or_lock('pro', $u, ...)`.
   - User paket **Gratis** akan melihat banner upgrade.
   - User paket **Pro** dan **Komunitas** tetap dapat mengakses (karena helper mengizinkan keduanya untuk kebutuhan `pro`).

3. **cedera_olahraga.php** (Cedera Olahraga & Penanganan)
   - Gating diubah dari `komunitas` → `pro`.
   - Sekarang paket **Pro** bisa akses; **Komunitas** juga tetap bisa akses.

4. **survival.php** (Survival Mode)
   - Gating diubah dari `komunitas` → `pro`.
   - Sekarang paket **Pro** bisa akses; **Komunitas** juga tetap bisa akses.

## Catatan PostgreSQL
Tidak ada tambahan SQL yang perlu dijalankan. Semua tabel yang dipakai
(`health_qa_saved`, `survival_qa_saved`, dll.) sudah dibuat idempotent
oleh masing-masing halaman (`CREATE TABLE IF NOT EXISTS ...`).

## Cara pakai
Timpa file di project lokal dengan file dari zip ini (pertahankan struktur folder).
