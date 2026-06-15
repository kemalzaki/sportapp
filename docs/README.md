# SportApp — HTMX + PWA + Service Worker Patch

Patch ini membuat SportApp **terasa seperti native app**: berpindah halaman
tanpa full reload, shell tetap, ada transisi halus, dan bisa dipasang
sebagai PWA di home screen.

## Isi paket

```
service-worker.js              ← REPLACE file lama
offline.html                   ← BARU, taruh di root
assets/js/htmx-boot.js         ← BARU
includes/htmx.php              ← BARU, helper is_htmx() + layout splitter
examples/                      ← contoh 3 halaman yang sudah dikonversi
docs/README.md                 ← file ini
```

## Cara pasang (30 menit)

### 1. Copy file
```
cp service-worker.js     <root>/service-worker.js
cp offline.html          <root>/offline.html
cp assets/js/htmx-boot.js <root>/assets/js/htmx-boot.js
cp includes/htmx.php     <root>/includes/htmx.php
```

### 2. Edit `includes/header.php` — tambah HTMX + CSRF meta + boot script

Cari blok `<head>`, tambahkan **sebelum** `</head>`:

```html
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
<script src="https://unpkg.com/htmx.org@1.9.12" defer></script>
<script src="/assets/js/htmx-boot.js?v=1" defer></script>
```

Lalu di `<body>`, bungkus area konten utama dengan `id="app"` + `hx-boost`:

```html
<body hx-boost="true" hx-target="#app" hx-swap="innerHTML transition:true" hx-push-url="true">
  <main id="app">
```

(`</main>` tetap di footer.php seperti sekarang)

### 3. Konversi halaman PHP ke pola "layout splitter"

Untuk **setiap** halaman publik (mis. `index.php`, `riwayat.php`, `feed_islami.php`),
ganti dua blok berikut:

**Sebelum:**
```php
$pageTitle = 'Beranda';
require __DIR__.'/includes/header.php';
// ... HTML konten ...
require __DIR__.'/includes/footer.php';
```

**Sesudah:**
```php
require_once __DIR__.'/includes/htmx.php';
htmx_layout_start('Beranda');
// ... HTML konten (SAMA) ...
htmx_layout_end();
```

Itu saja — fungsi `htmx_layout_start()` otomatis mendeteksi `HX-Request`
dan hanya mengirim fragment saat HTMX yang minta.

Lihat `examples/index.php`, `examples/riwayat.php`, `examples/feed_islami.php`
sebagai referensi.

### 4. Form (chat, like, upload kecil)

Ganti `<form method="post">` jadi `hx-post` agar tidak reload:

```html
<form hx-post="/index.php" hx-target="#chat-list" hx-swap="afterbegin">
  <input type="hidden" name="_action" value="chat_post">
  <input name="pesan" required>
  <button>Kirim</button>
</form>
```

CSRF token otomatis diinjeksi oleh `htmx-boot.js`. Server tetap pakai
`csrf_check()` seperti biasa — token dibaca dari header `X-CSRF-Token`
**atau** field `csrf_token`, jadi tambahkan di `includes/security.php`:

```php
function csrf_check(): void {
    $tok = $_POST['csrf_token']
        ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $tok)) {
        http_response_code(419); exit('CSRF');
    }
}
```

### 5. Halaman yang TIDAK boleh HTMX-fy

- `admin/*` — biarkan full reload (rumit, jarang dipakai user akhir)
- `login.php`, `logout.php`, `register.php` — disable dengan
  `hx-boost="false"` di link-nya. SW sudah otomatis skip caching path ini.
- Upload besar (`upload.php` >50MB) — biarkan native form supaya progress
  bar browser jalan.

### 6. Naikkan versi SW saat deploy

Edit `service-worker.js` baris pertama:
```js
const CACHE_VERSION = 'v5-htmx-2026-06-15';
```
Ganti string-nya setiap deploy. Client lama akan auto-update.

## Hasil yang diharapkan

✅ Klik menu bottom-nav → konten ganti instan, header tetap, URL berubah
✅ Tombol back/forward browser tetap jalan
✅ Notification badge nempel (tidak ke-reset tiap klik)
✅ Buka tanpa internet → halaman terakhir + offline.html
✅ Bisa di-install ke home screen (manifest.php sudah benar)
✅ Transisi halaman halus (View Transitions API di Chrome/Edge)

## Troubleshooting

- **Klik link malah reload penuh** → cek `hx-boost="true"` di `<body>`,
  dan pastikan link tujuan punya ekstensi `.php` (HTMX hanya intercept
  link same-origin).
- **Bottom nav hilang setelah klik** → normal, karena fragment tidak
  mengikut footer. Pastikan `</main>` dan `bottom_nav.php` di
  `footer.php` (TIDAK di `htmx_layout_end`).
- **SW lama nyangkut** → buka DevTools → Application → Service Workers
  → Unregister, atau ganti `CACHE_VERSION`.
- **CSRF 419** → cek meta tag `csrf-token` ada di `<head>` dan
  `csrf_check()` membaca header `X-CSRF-Token`.
