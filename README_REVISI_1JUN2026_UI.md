# Revisi UI — 1 Juni 2026 (partial)

File yang direvisi pada arsip ini (sebagian dari 9 item permintaan):

| # | Item | Status | File |
|---|------|--------|------|
| 1 | Gradient warna login bukan oranye → biru–hitam | ✅ | `login.php` |
| 2 | Halaman pendaftaran didesain ulang menyamai login | ✅ | `register.php` |
| 3 | Pertama buka app langsung ke `login.php` (bukan `index.php`) | ✅ | `index.php` |
| 4 | Tombol Daftar menampilkan ikon loading saat submit | ✅ | `register.php` |
| 5 | Tombol "Cek Pesanan" di jajanan menampilkan ikon loading | ✅ | `jajanan.php` |
| 6 | Popup "Pesan Sekarang" bisa di-scroll & tombol bayar tidak tertutup | ✅ | `jajanan.php` |
| 7 | Detail Pesanan kini menampilkan foto + nomor telepon kurir (fallback ke kolom `wa` bila `nomor_wa` kosong) | ✅ | `jajanan.php` |
| 8 | Skeleton loading muncul SETELAH pindah halaman | ⏳ Belum (akan diselesaikan di revisi berikutnya — perlu sentuh banyak halaman + `includes/header.php`) | — |
| 9 | Semua halaman pakai skeleton loading saat load data | ⏳ Belum (idem #8) | — |

## Catatan teknis

### Perubahan warna gradient
Login & register sekarang memakai palet:
```
--brand:     #1e3a8a   (biru tua)
--brand-2:   #0b1d3a   (hampir hitam)
--brand-glow:#3b82f6   (biru terang untuk ring & glow)
```

### Mode guest tetap bekerja
`index.php` sekarang redirect ke `login.php` jika user belum login.
Tautan **"Lanjut ke Dashboard tanpa Login"** pada halaman login mengirim
`?guest=1`, lalu disimpan di `$_SESSION['guest_ok']` sehingga akses berikutnya
tidak terus redirect.

### Detail Pesanan — foto & nomor telepon kurir
Query AJAX `?ajax=detail_pesanan` sekarang membaca:
```sql
COALESCE(NULLIF(nomor_wa,''), NULLIF(wa,'')) AS nomor_wa
```
Jika kurir di `members.php` punya kolom `wa` (lama) atau `nomor_wa` (baru),
keduanya akan ditampilkan. Foto diambil dari `users.foto_url`.

**Tidak ada perubahan skema database** yang dibutuhkan untuk revisi ini.
Semua kolom (`nomor_wa`, `wa`, `foto_url`, `kurir_user_id`) sudah ada di
`sportapp.sql` lama. Pastikan saja data kurir di tabel `users` punya
`nomor_wa` (atau `wa`) terisi agar nomor telepon muncul.

### Yang masih perlu dikerjakan (#8 & #9)
Implementasi skeleton screen global menyentuh:
- `includes/header.php` (overlay skeleton saat klik link nav)
- Tiap halaman list (jajanan, profil, riwayat, dst.) — perlu ganti spinner
  fetch dengan skeleton card.

Akan dikirim dalam zip revisi berikutnya agar perubahan ini bisa langsung
di-test dulu di local.
