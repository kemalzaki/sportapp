# Revisi 18 Juni 2026 (D) — Lanjutan

## 1. flyover.php — Tempo lirik dipaskan dengan lagu (tidak asal)
- Distribusi waktu tiap baris lirik kini **berbobot panjang karakter** (proxy suku kata), bukan rata-rata.
- Tambah **intro pad** (~6% durasi, max 4 detik) dan **outro pad** (~4% durasi, max 3 detik) supaya baris pertama/terakhir tidak nempel ujung lagu.
- Tiap baris diberi durasi minimum 1.2s dan maksimum 6s, lalu dinormalisasi agar total tepat = durasi lagu.
- **Lead time 0.25 detik**: subtitle muncul sedikit lebih dulu sebelum lirik dinyanyikan (lebih sinkron rasa karaoke).
- Tempo otomatis **dihitung ulang** ketika:
  - Audio `loadedmetadata` terjadi (durasi baru diketahui).
  - Tombol **Apply Trim** ditekan (durasi efektif berubah).
- Tetap menghormati format LRC `[mm:ss.xx]` bila tersedia (timestamp dipakai apa adanya).

## 2. artikel_olahraga.php — Pasang foto peralatan asli
- `<img>` peralatan kini memakai path **`$eq['img']`** (`/assets/img/peralatan/eq00.jpg` … `eq27.jpg`) yang sudah Anda taruh di folder `assets/img/peralatan/`.
- Tetap ada fallback bertingkat:
  1. File lokal `eq*.jpg`
  2. Bila 404 → placehold.co berwarna tema
  3. Bila tetap gagal → SVG inline "No Img"

## PostgreSQL
Tidak ada perubahan skema/data yang dibutuhkan untuk revisi ini.

## Cara menerapkan
Ekstrak isi ZIP ini ke root project lalu replace:
- `flyover.php`
- `artikel_olahraga.php`

Folder `assets/img/peralatan/` di project Anda sudah berisi `eq00.jpg`–`eq27.jpg`, jadi tidak perlu disentuh.
