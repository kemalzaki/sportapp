# Revisi 1 Juni 2026 (v2) — Jajanan, Login, Skeleton Global

Arsip ini **hanya** berisi file yang berubah, jadi langsung saja **timpa** ke folder project:

```
jajanan.php            → ganti file lama
login.php              → ganti file lama
includes/footer.php    → ganti file lama
```

## 1. `jajanan.php`

### #1  Popup "Pesan Sekarang" tidak bisa di-scroll, tombol bayar tertutup
- CSS modal `#pesanModal` dirombak: pakai `flex` untuk `.modal-content`
  + `.modal-body` (`flex:1 1 auto; min-height:0; overflow-y:auto`) +
  `.modal-footer` (`position:sticky; bottom:0`).
- Pada layar HP (≤576px) modal dibuat full-height (`100dvh`) dengan
  margin 0, jadi keyboard / address bar mobile tidak menutupi tombol bayar.
- `position:sticky` di footer membuat tombol **Bayar via Midtrans**
  selalu kelihatan sambil isi formnya tetap bisa di-scroll.

### #2  Detail Pesanan: foto + nomor telepon kurir
- AJAX endpoint `?ajax=detail_pesanan` kini juga mengambil
  `foto_url` dari tabel `users` (sesuai `members.php`).
- Render kartu kurir diganti: muncul **avatar bulat** (atau inisial
  fallback bila `foto_url` kosong) + nomor telepon (format `+62…`) +
  tombol Chat WhatsApp & Telepon.

## 2. `login.php` (rebuild total)

Tampilan mobile-first ala Strava / aplikasi modern:

- Hero gradien orange (brand HapFam) dengan logo + greeting.
- Card putih melayang (radius 32px) berisi form.
- Input dengan border-radius besar, focus ring lembut.
- **3 tombol utama** sesuai permintaan:
  1. `Masuk` (submit form login — primary gradient).
  2. `Daftar Akun Baru` → `/register.php` (outline).
  3. `Lanjut ke Dashboard tanpa Login` → `/index.php?guest=1` (ghost).
- Toggle "lihat password" (mata) + spinner saat submit.
- Tetap pakai CSRF + captcha + rate-limit + bcrypt upgrade lama.

> Halaman `index.php` memang tidak meng-`require_login()`, jadi
> tombol guest cukup mengarahkan ke `/index.php`. Tidak perlu
> perubahan auth / DB. Halaman yang memang butuh login (mis. profile)
> akan otomatis redirect kembali ke `/login.php` seperti biasa.

## 3. Skeleton Loading Global (`includes/footer.php`)

Ditambahkan satu blok CSS + helper JS yang ter-load di **semua halaman**
(karena semua halaman include `footer.php`):

```html
<!-- markup -->
<div class="skeleton skel-line"></div>
<div class="skeleton skel-block" style="height:120px"></div>
<div class="skeleton skel-circle" style="width:56px;height:56px"></div>
```

```js
// helper
SK.rows(targetEl, 4);   // 4 baris teks skeleton
SK.cards(targetEl, 3);  // 3 kartu skeleton
SK.block(targetEl, 200);// 1 block setinggi 200px
```

Sudah otomatis mengikuti theme dark/light. Untuk halaman lain
(`berita.php`, `feed_islami.php`, dst), tinggal panggil `SK.rows(el,n)`
sebelum `fetch(...)` pada AJAX-nya. Pada `jajanan.php` skeleton sudah
dipakai untuk modal Detail Pesanan & list produk toko (kelas
`.jjn-shimmer` lama tetap dipertahankan).

## PostgreSQL

**Tidak ada migrasi baru.** Semua kolom yang dipakai sudah ada:
- `users.foto_url` (untuk foto kurir di Detail Pesanan)
- `users.nomor_wa` (untuk nomor telepon kurir)
- `jajanan_pesanan.kurir_user_id` (sudah dari revisi sebelumnya)

Jika kolom `users.foto_url` belum ada di DB lokal Anda (pada beberapa
dump lama), tambahkan:

```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS foto_url TEXT;
```
