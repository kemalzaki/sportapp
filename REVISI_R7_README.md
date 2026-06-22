# Revisi 22 Juni 2026 R7 — Catatan Perubahan (Partial)

Zip ini berisi **sebagian** revisi sesuai permintaan. Cukup salin/timpa file-file
di bawah ke folder `sportapp_core` Anda (struktur folder dipertahankan).

## File yang berubah / baru

### Revisi #1 — Trim Audio (CORS) + Pencarian Lagu via YouTube
- `flyover.php` *(updated)* — trim audio sekarang lewat proxy bila sumber iTunes; tombol baru "YT" di samping pencarian musik untuk cari lagu via YouTube (pola sama dgn `artikel_olahraga.php`).
- `api_audio_proxy.php` *(baru)* — proxy CORS untuk audio iTunes/mzstatic agar `fetch()` di browser tidak gagal saat decode.

### Revisi #2 — Kalori Mingguan: form catatan scrolling
- `kalori_mingguan.php` *(updated)* — kolom **Catatan** pada form tambah & edit kini `<textarea>` dengan `max-height + overflow-y:auto` (teks tidak terpotong, bisa di-scroll). Modal Detail Gizi juga `modal-dialog-scrollable`.
- **Catatan tentang Makro Nutrien yang tidak muncul:** kolom `karbohidrat_g/protein_g/lemak_g/serat_g/gula_g/sodium_mg` di tabel `kalori_makanan_log` hanya terisi bila entri dibuat dengan foto + AI Gemini sukses memparse. Untuk entri **lama** kolom-kolom ini `NULL`, sehingga modal menampilkan `—`. Edit ulang dgn foto + AI agar terisi. (Tidak ada perubahan skema yang diperlukan — kolom sudah ada.)

### Revisi #3 + #5 — Filter kata kunci pencarian video (CRUD admin)
- `admin/keywords.php` *(baru)* — CRUD kata kunci untuk kategori `olahraga` (kalistenik) & `survival`.
- `api_yt_search.php` *(updated)* — menerima parameter `cat=olahraga|survival`. Jika query user tidak mengandung salah satu kata kunci aktif, server akan menyisipkan kata kunci utama agar hasil tetap pada topik.
- `kalistenik.php` *(updated)* — ditambahkan **kolom pencarian video YouTube** dengan `cat=olahraga`.
- `survival.php` *(updated)* — query pencarian sekarang mengirim `cat=survival`.
- `includes/header.php` *(updated)* — link **"Kata Kunci Filter Video"** ditambahkan di grup *Pengaturan Lainnya* (admin drawer mobile).

### Revisi #4 — Cedera Olahraga: spoiler
- `cedera_olahraga.php` *(updated)* — tiap item cedera kini *collapse* (klik header untuk buka/tutup), pola sama dgn `artikel_olahraga.php`.

### Revisi #6 — Tempat: rute hiking & camping
- `tempat.php` *(updated)* — section baru "Tempat Hiking & Camping" yang menampilkan tempat dengan jenis `hiking`/`camping` lengkap dgn tombol **Lihat Rute** (modal Leaflet menggambar polyline dari GPX yang di-input admin di `admin/tempat.php`).

### Revisi #7 — Hapus DM & chat melayang
- `includes/footer.php` *(updated)* — `dm_floating.php` tidak lagi di-include (widget chat melayang hilang).
- `includes/header.php` *(updated)* — chip "Pesan", item drawer "Pesan", dan nav-link "Pesan" (semua → `/dm.php`) dihapus dari menu.
- File `dm.php`, `api_dm.php`, `includes/dm_floating.php` tetap ada (tidak dihapus) karena masih dirujuk dari `riwayat.php` & `index.php` (tombol "Ingatkan" / "Chat" per-user) — hanya tidak muncul di menu utama / chat melayang.

## PostgreSQL — Tabel baru / perubahan skema

Tabel berikut dibuat otomatis (`CREATE TABLE IF NOT EXISTS`) saat halaman terkait diakses, jadi **tidak wajib** Anda jalankan manual. Namun jika ingin pre-create:

```sql
-- Untuk revisi #3 & #5 (CRUD kata kunci filter pencarian)
CREATE TABLE IF NOT EXISTS search_keywords (
    id BIGSERIAL PRIMARY KEY,
    kategori VARCHAR(20) NOT NULL,         -- 'olahraga' | 'survival'
    kata TEXT NOT NULL,
    aktif BOOLEAN NOT NULL DEFAULT TRUE,
    urutan INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_search_keywords_kat ON search_keywords(kategori, aktif);

-- Seed default (akan otomatis terisi pertama kali admin/keywords.php dibuka)
INSERT INTO search_keywords(kategori,kata) VALUES
  ('olahraga','olahraga'),('olahraga','pertandingan'),('olahraga','match'),
  ('olahraga','tutorial'),('olahraga','teknik'),('olahraga','latihan'),
  ('survival','survival'),('survival','bushcraft'),('survival','wilderness'),
  ('survival','camping'),('survival','hutan');
```

Selain di atas, **tidak ada tabel/kolom baru lainnya** yang dibutuhkan untuk revisi-revisi pada zip ini. Data `kalori_makanan_log`, `tempat`, dll. dipakai apa adanya.

## Tidak termasuk pada zip ini

Semua revisi #1–#7 di atas **sudah** termasuk pada zip ini, walaupun:
- Untuk #2 (Makro Nutrien): hanya scrolling form yang diperbaiki. Data makro yang kosong adalah perilaku data (entri lama tanpa AI) — bukan bug tampilan, jadi tidak ada perubahan kode lebih lanjut.

Selamat mencoba di lokal!
