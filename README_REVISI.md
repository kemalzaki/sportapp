# Revisi UI — 1 Juni 2026

Zip ini hanya berisi **file yang direvisi** (partial), bukan keseluruhan project.
Tinggal copy-replace ke folder `sportapp_core/` di lokal Anda (timpa file lama).

## Daftar file
- `includes/dm_floating.php`
- `includes/footer.php`

## Ringkasan perubahan

### 1) Icon chat melayang (FAB) bisa ditutup
- Ditambah tombol kecil **×** di pojok kiri-atas FAB chat (warna gelap).
- Klik **×** → FAB langsung disembunyikan; status disimpan di `localStorage`
  (`hf_dm_fab_hidden=1`) sehingga tetap tersembunyi di halaman/sesi berikutnya.
- Saat tersembunyi, muncul pil kecil **"💬 Pesan"** di sudut kanan-bawah
  yang bisa diklik untuk memunculkan kembali FAB.
- Tidak menyentuh logika DM, polling, badge unread, dsb.

### 2) Skeleton loading muncul SETELAH pindah halaman
- Handler lama yang memunculkan top-loader saat **klik link / submit / beforeunload**
  sudah **dinonaktifkan** di `footer.php`. Jadi tidak ada lagi indikator loading
  yang muncul *sebelum* navigasi (tidak menghalangi user).
- Top-loader tetap aktif untuk request **AJAX / fetch / XHR** di halaman tujuan.
- Skeleton dirender oleh `SK.auto()` pada event `DOMContentLoaded` halaman baru →
  efeknya benar-benar muncul *setelah* halaman terbuka.

### 3) Skeleton loading global untuk halaman yang load data
Tambahan helper di `footer.php` (window `SK`):

```html
<!-- Cara pakai paling mudah, tinggal tambahkan atribut di container data -->
<div id="listForum" data-skel="rows" data-skel-count="5"></div>
<div id="gridEvent" data-skel="cards" data-skel-count="3"></div>
<div id="chartArea" data-skel="block" data-skel-h="220"></div>
```

`SK.auto()` otomatis berjalan saat `DOMContentLoaded` dan mengganti isi setiap
elemen `[data-skel]` dengan skeleton sesuai tipenya
(`rows` / `cards` / `block`). Begitu JS halaman mengisi data asli (mis.
`el.innerHTML = …`), skeleton akan tergantikan otomatis.

Juga ada wrapper fetch praktis:

```js
SK.fetch('/api_xxx.php?q=1', document.getElementById('listForum'), 'rows', {count:5})
  .then(html => { document.getElementById('listForum').innerHTML = html; });
```

Atau panggil manual: `SK.rows(el, n)`, `SK.cards(el, n)`, `SK.block(el, h)`.

## PostgreSQL
**Tidak ada perubahan skema DB.** Semua revisi murni front-end (HTML/CSS/JS di
dalam file PHP yang ada). Tidak perlu menjalankan migration baru, dan tidak
ada data lama yang dihapus / dimodifikasi.

## Cara memakai di lokal
1. Backup folder `includes/` Anda (opsional tapi disarankan).
2. Ekstrak zip ini, lalu salin `includes/dm_floating.php` dan
   `includes/footer.php` ke `sportapp_core/includes/` (timpa).
3. Hard refresh browser (Ctrl+F5) supaya CSS/JS terbaru terbaca.

## Catatan menerapkan skeleton ke halaman lain
Karena ini revisi parsial, halaman-halaman yang sudah ada belum semuanya
memakai `data-skel`. Untuk mengaktifkan skeleton di halaman tertentu, cukup
tambahkan atribut `data-skel="rows|cards|block"` pada container data, contoh:

```php
<!-- riwayat.php, monitoring.php, event.php, dll -->
<div id="riwayatList" data-skel="cards" data-skel-count="4">
  <!-- isi data dari PHP / fetch JS -->
</div>
```

Tidak perlu menambah JS apa pun — `SK.auto()` mengurusnya otomatis.
