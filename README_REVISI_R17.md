# Revisi R17 — 26 Juni 2026

Berisi 6 file PHP yang direvisi. Drop-in pengganti file dengan nama sama
di folder root `sportapp_core/`.

## File yang diubah

1. **tajwid.php** — tiap hukum tajwid kini menggunakan spoiler (accordion Bootstrap), jadi halaman tidak memanjang ke bawah.
2. **artikel_sunnah.php** — pagination 5 artikel per halaman (`?p=1`, `?p=2`, dst).
3. **islami.php** — urutan card grid disusun ulang:
   - Setelah **Al-Qur'an Digital** berturut-turut: Ensiklopedia Hadist → Belajar Tajwid → Sejarah Nabi & Rasul → Tata Cara Wudhu → Tata Cara Shalat → Shalat Sunnah Rawatib → Shalat Duha & Tahajud.
4. **sejarah_nabi.php** — tab "Tabel Kaum & Azab" ditambah 15 entri baru (Bani Qabil, Babilonia/Namrud, saudara Yusuf, ujian Ayyub, Ba'l, penentang Ilyasa', Filistin/Jalut, Saba'/Bilqis, pembunuh Zakariya, pembunuh Yahya, Yahudi penentang Isa, Ashabul Ukhdud, Ashabul Fil, Diqyanus & Ashabul Kahfi, Qarun, Bani Quraizhah). Total kini 25 entri.
5. **wudhu_tatacara.php** — semua gambar ilustrasi dihapus, hanya teks bacaan & keterangan.
6. **shalat_tatacara.php** — semua gambar ilustrasi dihapus, hanya teks bacaan & keterangan.

## PostgreSQL

Tidak ada tabel baru yang perlu ditambahkan. Semua revisi memakai tabel
yang sudah ada (`islami_artikel`, `tajwid_progress`, dst.).
Tabel `tajwid_progress` akan dibuat otomatis pada saat pertama kali halaman
tajwid dibuka (auto-migration `CREATE TABLE IF NOT EXISTS` sudah ada).

## Cara apply

Cukup ekstrak zip dan timpa keenam file di folder root `sportapp_core/`.
Tidak perlu menjalankan migrasi SQL.
