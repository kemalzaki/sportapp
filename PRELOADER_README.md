# Global Preloader System

File yang ditambah / diubah:

- `assets/css/preloader.css` (BARU) — gaya splash, overlay, button-loading, shimmer.
- `assets/js/preloader.js` (BARU) — logika global preloader.
- `includes/header.php` — load CSS preloader; CSS lama `#appPreloader` dihapus.
- `includes/footer.php` — load JS preloader; handler `#appPreloader` lama dihapus.

## Otomatis

Semua halaman yang memakai `header.php` + `footer.php` sudah otomatis dapat:

1. **Splash screen** singkat saat halaman pertama dibuka (fade-out otomatis).
2. **Overlay loading** saat:
   - klik link internal / menu navigasi
   - submit `<form>` (termasuk login, register, dll.)
   - klik elemen `[data-loader]` (tombol action penting)
   - fetch API dengan header `X-With-Loader: 1` atau opsi `{ loader:true }`
3. **Disable double-click** pada tombol submit & elemen `[data-loader]`.
4. **Anti-flicker** (min 280ms) + **fallback timeout** 15 detik.
5. **Aman BFCache** (back/forward) — overlay otomatis dibersihkan.

## API Manual

```js
HFPreloader.show('Menyimpan...');
await fetch('/api_xxx.php', { method:'POST', body:fd });
HFPreloader.hide();

// atau pakai wrap:
await HFPreloader.wrap(fetch('/api_xxx.php'), 'Memuat data...');

// reset paksa (debug):
HFPreloader.reset();
```

## Opt-out

- `<a data-no-loader="1" href="...">` — link tanpa overlay.
- `<form data-no-loader="1">` — form tanpa overlay.
- `<a target="_blank">`, `<a download>`, link hash `#`, `mailto:`, `tel:` — otomatis di-skip.

## Database (PostgreSQL)

Preloader ini **murni frontend**, **tidak butuh tabel / kolom baru**.
Tidak ada migration tambahan yang perlu dijalankan ke PostgreSQL.
