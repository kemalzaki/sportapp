<?php
/**
 * includes/paket_helpers.php — Revisi R14 (25 Juni 2026)
 * Helper untuk fitur PRO / paket member (gratis/pro/komunitas).
 *
 * Paket:
 *   - gratis     : akses fitur dasar
 *   - pro        : akses fitur premium (Hub Islami lengkap, dst.)
 *   - komunitas  : sama seperti pro + diberikan untuk admin / kontributor komunitas
 */

if (!function_exists('paket_user')) {
    function paket_user(?array $u): string {
        if (!$u) return 'gratis';
        // Admin selalu dianggap komunitas (akses penuh)
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
        $p = paket_user($u);
        return in_array($p, ['pro','komunitas'], true);
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
    /** Nomor WA pemesanan PRO (revisi R14 #1) */
    function paket_wa_pro_url(string $fiturNama = 'Fitur PRO'): string {
        $msg = "Assalamu'alaikum, saya ingin memesan ".$fiturNama." di aplikasi KawanKeringat. Mohon informasinya. Terima kasih.";
        return 'https://wa.me/6281386369207?text='.rawurlencode($msg);
    }
}

if (!function_exists('paket_pro_lock_banner')) {
    /** Banner kunci PRO + tombol pesan via WA */
    function paket_pro_lock_banner(string $fiturNama, string $deskripsi = ''): string {
        $wa = paket_wa_pro_url($fiturNama);
        $desc = $deskripsi ?: 'Fitur ini terkunci. Upgrade ke paket PRO untuk mengakses fitur premium ini.';
        return '
<div class="card shadow-sm border-warning mb-3">
  <div class="card-body text-center py-4">
    <div class="display-4 mb-2">🔒⭐</div>
    <h4 class="fw-bold text-warning-emphasis">'.htmlspecialchars($fiturNama).' <small class="badge bg-warning text-dark">PRO</small></h4>
    <p class="text-muted mb-3">'.htmlspecialchars($desc).'</p>
    <a href="'.htmlspecialchars($wa).'" target="_blank" rel="noopener" class="btn btn-success btn-lg">
      <i class="bi bi-whatsapp"></i> Pesan Fitur PRO via WhatsApp
    </a>
    <div class="small text-muted mt-2">Hubungi: <strong>0813-8636-9207</strong></div>
  </div>
</div>';
    }
}
