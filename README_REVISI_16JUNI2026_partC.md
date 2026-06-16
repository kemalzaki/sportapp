# Revisi 16 Juni 2026 — Part C

Isi zip ini hanya file yang direvisi sebagian, bukan seluruh aplikasi. Data `.sql` dari zip awal tidak dihapus dan tidak perlu diubah.

## File yang direvisi

1. `flyover.php`
   - Rekaman video sekarang memakai canvas komposit, bukan hanya `map.getCanvas().captureStream()`.
   - Popup detail, HUD statistik, badge REC, ikon Start/Finish, marker KM, dan ikon runner ikut digambar ke canvas rekaman sehingga masuk ke file `.webm`.
   - Musik tetap bisa ikut terekam bila opsi musik aktif.

2. `includes/ai_gemini.php`
   - Menghapus fallback default `AQ...` karena itu bukan Gemini API key server yang stabil.
   - `GEMINI_API_KEY` sekarang divalidasi harus berupa API key AI Studio yang diawali `AIza...`.
   - Jika masih memakai token `AQ...`, aplikasi akan memberi pesan error yang jelas, bukan mengirim credential salah ke Gemini.
   - Mendukung fallback `GOOGLE_API_KEY`; OAuth server bisa dipakai lewat `GEMINI_ACCESS_TOKEN`, tetapi untuk local paling disarankan API key `AIza...`.

## Yang perlu ditambahkan di konfigurasi lokal

Tidak ada perubahan PostgreSQL yang diperlukan untuk revisi ini.

Agar Gemini AI terkoneksi di local, tambahkan salah satu cara berikut sebelum menjalankan PHP server:

### Opsi A — environment variable

```bash
export GEMINI_API_KEY=AIza...
```

### Opsi B — `config/env.local.php`

Tambahkan baris ini di bawah konfigurasi yang sudah ada:

```php
hf_env_set('GEMINI_API_KEY', 'AIza...');
```

API key bisa dibuat di: https://aistudio.google.com/apikey

> Catatan: credential lama yang diawali `AQ...` biasanya berasal dari Google Sign-In/OAuth browser dan akan ditolak/cepat kedaluwarsa untuk pemanggilan Gemini server lokal.
