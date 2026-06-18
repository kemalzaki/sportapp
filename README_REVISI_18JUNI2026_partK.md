# Revisi 18 Juni 2026 — Part K (sportapp)

Arsip parsial ini berisi halaman/file yang direvisi sesuai permintaan.
Project lain (yang tidak direvisi) tetap pakai file asli dari `sportapp_core.zip`.

## File yang berubah

| # | File | Perubahan |
|---|------|-----------|
| 1 | `api_ai.php`                  | + task `ai_health` (Cedera Olahraga) & `ai_doctor` (Penyakit/Herbal). |
| 2 | `cedera_olahraga.php`         | + Form AI Health Tanya Jawab + simpan jawaban (tabel `health_qa_saved`). |
| 3 | `kesehatan.php`               | + Form AI Doctor Tanya Jawab + simpan jawaban (sharing tabel, kategori `doctor`). |
| 4 | `includes/ai_qa_widget.php`   | **BARU** — widget Q&A AI reusable (pola sama dgn islami.php). |
| 5 | `includes/header.php`         | − menu mobile "Panduan Olahraga", "Paket Pemanasan", "Paket Pendinginan" (digabung ke Artikel Olahraga). |
| 6 | `artikel_olahraga.php`        | **DITULIS ULANG.** Tiap cabang menampilkan Definisi, Cara Main, Pembagian Tim, Sistem Skoring, Sistem Menang-Kalah + foto + video teknik YouTube. Renang pakai foto perenang laki-laki (Michael Phelps). +Artikel Biliar. +Video Pemanasan & Pendinginan di atas. |
| 7 | `kalori_mingguan.php`         | Input foto pakai `capture="environment"` → mobile langsung buka kamera belakang; tombol "Pilih dari Galeri" sebagai fallback + preview thumbnail. |
| 8 | `run.php`                     | Generate Rute shape=`loop` sekarang benar-benar melingkar (8 waypoint mengelilingi pusat lingkaran, jari-jari ≈ jarak/2π) — bukan segitiga seperti versi lama. Label dropdown diperjelas. |

## Belum dikerjakan (akan menyusul di part berikutnya)

- **Task 7** — `admin/tim.php` mengambil data eksternal dari `member_eksternal`
  (`admin/absensi.php`). Memerlukan perubahan UI & logika cukup besar
  (dropdown sumber eksternal lintas-jadwal). Ditunda.
- **Task 8** — Penyeragaman tampilan Desktop = Mobile (gaya kitabisa.com).
  Pekerjaan besar lintas-file (CSS global + navigasi + grid). Ditunda.

## PostgreSQL — tambahan (idempotent, auto-create saat halaman diakses)

Halaman membuat tabel sendiri lewat `CREATE TABLE IF NOT EXISTS`, tapi
jika ingin pre-migrate manual:

```sql
CREATE TABLE IF NOT EXISTS health_qa_saved (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    kategori VARCHAR(20) NOT NULL DEFAULT 'health',   -- 'health' | 'doctor'
    pertanyaan TEXT NOT NULL,
    jawaban TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS health_qa_user_idx
    ON health_qa_saved(user_id, kategori, created_at DESC);
```

Tidak ada tabel lain yang berubah. Data lama tetap utuh.

## Cara pasang

1. Extract `sportapp_revisi_18juni2026_partK.zip`.
2. Replace file dengan path yang sama di folder project lokal.
3. (Opsional) jalankan SQL di atas.
4. Buka halaman sekali agar trigger auto-migrate.
