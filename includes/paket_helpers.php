<?php
/**
 * includes/paket_helpers.php — Revisi R21 (1 Juli 2026)
 *
 * Paket:
 *   - gratis     : akses fitur dasar
 *   - pro        : akses fitur premium
 *   - komunitas  : akses fitur premium + Hub Islami
 *
 * Revisi R21:
 *   - Banner kunci PRO / KOMUNITAS dihapus tombol WhatsApp-nya.
 *     Diganti SATU tombol "Lihat Paket & Upgrade" → /paket_upgrade.php
 *     yang membuka halaman pemilihan paket + pembayaran Midtrans.
 *   - Helper baru: paket_lock_banner($needed, $fiturNama, $deskripsi)
 *     untuk PRO maupun KOMUNITAS.
 *   - paket_pro_lock_banner() tetap ada (backward-compatible) tapi
 *     sekarang men-render varian baru.
 */

if (!function_exists('paket_user')) {
    function paket_user(?array $u): string {
        if (!$u) return 'gratis';
        if (($u['role'] ?? '') === 'admin') return 'komunitas';
        try {
            $p = (string) db_val("SELECT paket FROM users WHERE id=$1", [(int)$u['id']]);
        } catch (Throwable $e) { $p = ''; }
        $p = strtolower(trim($p));
        if (!in_array($p, ['gratis','pro','komunitas'], true)) $p = 'gratis';
        return $p;
    }
}

if (!function_exists('paket_is_pro')) {
    function paket_is_pro(?array $u): bool {
        return in_array(paket_user($u), ['pro','komunitas'], true);
    }
}

if (!function_exists('paket_badge')) {
    function paket_badge(string $paket): string {
        $paket = strtolower($paket);
        $map = [
            'gratis'    => ['secondary', '🆓 Gratis'],
            'pro'       => ['warning',   '⭐ PRO'],
            'komunitas' => ['success',   '👥 Komunitas'],
        ];
        $b = $map[$paket] ?? $map['gratis'];
        return '<span class="badge bg-'.$b[0].'">'.$b[1].'</span>';
    }
}

if (!function_exists('paket_wa_pro_url')) {
    /* Backward-compat: masih dipakai di beberapa halaman lama. */
    function paket_wa_pro_url(string $fiturNama = 'Fitur PRO'): string {
        $msg = "Assalamu'alaikum, saya ingin memesan ".$fiturNama." di aplikasi KawanKeringat. Mohon informasinya. Terima kasih.";
        return 'https://wa.me/6281386369207?text='.rawurlencode($msg);
    }
}

if (!function_exists('paket_lock_banner')) {
    /**
     * Banner kunci baru — SATU tombol saja yang membuka halaman pilihan paket.
     *
     * @param string $needed     'pro' atau 'komunitas' (paket minimum yg dibutuhkan)
     * @param string $fiturNama  Nama fitur (mis. "Hub Islami")
     * @param string $deskripsi  Deskripsi opsional
     */
    function paket_lock_banner(string $needed, string $fiturNama, string $deskripsi = ''): string {
        $needed = strtolower($needed) === 'komunitas' ? 'komunitas' : 'pro';
        $isKom  = $needed === 'komunitas';
        $cls    = $isKom ? 'success' : 'warning';
        $icon   = $isKom ? '🔒👥' : '🔒⭐';
        $label  = $isKom ? 'KOMUNITAS' : 'PRO';
        $desc   = $deskripsi ?: ($isKom
            ? 'Fitur ini hanya tersedia untuk paket KOMUNITAS. Upgrade paket Anda untuk mengaksesnya.'
            : 'Fitur ini terkunci. Upgrade ke paket PRO atau KOMUNITAS untuk mengaksesnya.');
        $href = '/paket_upgrade.php?need=' . urlencode($needed);
        return '
<div class="card shadow-sm border-'.$cls.' mb-3">
  <div class="card-body text-center py-4">
    <div class="display-4 mb-2">'.$icon.'</div>
    <h4 class="fw-bold text-'.$cls.'-emphasis">'.htmlspecialchars($fiturNama).' <small class="badge bg-'.$cls.' text-dark">'.$label.'</small></h4>
    <p class="text-muted mb-3">'.htmlspecialchars($desc).'</p>
    <a href="'.htmlspecialchars($href).'" class="btn btn-'.$cls.' btn-lg">
      <i class="bi bi-stars"></i> Lihat Paket &amp; Upgrade
    </a>
    <div class="small text-muted mt-2">Pembayaran aman via Midtrans · status paket otomatis aktif setelah lunas.</div>
  </div>
</div>';
    }
}

if (!function_exists('paket_pro_lock_banner')) {
    /* Wrapper backward-compatible — sekarang memanggil paket_lock_banner('pro', ...). */
    function paket_pro_lock_banner(string $fiturNama, string $deskripsi = ''): string {
        return paket_lock_banner('pro', $fiturNama, $deskripsi);
    }
}

if (!function_exists('paket_komunitas_lock_banner')) {
    function paket_komunitas_lock_banner(string $fiturNama, string $deskripsi = ''): string {
        return paket_lock_banner('komunitas', $fiturNama, $deskripsi);
    }
}

if (!function_exists('paket_prices')) {
    /** Harga paket (Rupiah / bulan). Bisa di-override via tabel app_settings:
     *   skey='paket_price_pro'        → harga PRO
     *   skey='paket_price_komunitas'  → harga KOMUNITAS
     */
    function paket_prices(): array {
        $pro = 25000; $kom = 50000;
        if (function_exists('app_setting_int')) {
            $pro = app_setting_int('paket_price_pro', $pro);
            $kom = app_setting_int('paket_price_komunitas', $kom);
        }
        return ['pro' => max(1000,$pro), 'komunitas' => max(1000,$kom)];
    }
}

if (!function_exists('paket_require_or_lock')) {
    /**
     * Revisi R22 — Gate halaman berdasarkan paket.
     *   $needed : 'pro' | 'komunitas'
     *   $u      : current_user()
     *   $fitur  : nama fitur (untuk banner)
     *   $desc   : deskripsi opsional
     * Jika user belum memenuhi paket, render header + lock banner + footer lalu exit().
     */
    function paket_require_or_lock(string $needed, ?array $u, string $fitur, string $desc=''): void {
        $needed = strtolower($needed) === 'komunitas' ? 'komunitas' : 'pro';
        $paket  = paket_user($u);
        $ok = ($needed === 'pro')
            ? in_array($paket, ['pro','komunitas'], true)
            : ($paket === 'komunitas');
        if ($ok) return;

        global $pageTitle;
        if (empty($pageTitle)) $pageTitle = $fitur;
        // header & footer mungkin sudah di-include; cek dulu via flag konstan.
        if (!defined('PAKET_GATE_RENDERED')) {
            define('PAKET_GATE_RENDERED', true);
            include __DIR__.'/header.php';
            echo paket_lock_banner($needed, $fitur, $desc);
            include __DIR__.'/footer.php';
        }
        exit;
    }
}
