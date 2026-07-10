<?php
/**
 * includes/paket_helpers.php — Revisi R24 (Juli 2026 R4)
 *
 * Paket:
 *   - gratis     : akses fitur dasar
 *   - pro        : akses fitur premium
 *   - komunitas  : akses fitur premium + Hub Islami
 *
 * Revisi R24 (Juli 2026 R4):
 *   - Tambah masa berlaku paket (paket_expires_at).
 *   - paket_user() otomatis downgrade ke 'gratis' bila expired.
 *   - Helper baru: paket_expires_at($u), paket_expiry_label($u).
 *
 * Revisi R21..R23 (lama) tetap berlaku:
 *   - Banner kunci PRO / KOMUNITAS ber-tombol "Lihat Paket & Upgrade"
 *     mengarah ke /paket_upgrade.php.
 */

if (!function_exists('paket_row')) {
    /** Ambil baris paket user (paket + expiry) dari DB, sekali per user. */
    function paket_row(?array $u): array {
        static $cache = [];
        if (!$u) return ['paket'=>'gratis','paket_expires_at'=>null];
        $id = (int)($u['id'] ?? 0);
        if ($id <= 0) return ['paket'=>'gratis','paket_expires_at'=>null];
        if (isset($cache[$id])) return $cache[$id];
        try {
            $row = db_one(
                "SELECT COALESCE(paket,'gratis') AS paket, paket_expires_at
                   FROM users WHERE id=$1", [$id]);
        } catch (Throwable $e) { $row = null; }
        if (!$row) $row = ['paket'=>'gratis','paket_expires_at'=>null];
        $p = strtolower(trim((string)$row['paket']));
        if (!in_array($p, ['gratis','pro','komunitas'], true)) $p = 'gratis';
        // Auto-downgrade jika sudah lewat masa aktif
        if ($p !== 'gratis' && !empty($row['paket_expires_at'])) {
            $exp = strtotime((string)$row['paket_expires_at']);
            if ($exp !== false && $exp < time()) {
                try {
                    db_exec("UPDATE users SET paket='gratis' WHERE id=$1", [$id]);
                } catch (Throwable $e) {}
                $p = 'gratis';
            }
        }
        $row['paket'] = $p;
        $cache[$id] = $row;
        return $row;
    }
}

if (!function_exists('paket_user')) {
    function paket_user(?array $u): string {
        if (!$u) return 'gratis';
        if (($u['role'] ?? '') === 'admin') return 'komunitas';
        return paket_row($u)['paket'];
    }
}

if (!function_exists('paket_expires_at')) {
    /** Kembalikan timestamp expire (string) atau null jika tidak ada. */
    function paket_expires_at(?array $u): ?string {
        if (!$u) return null;
        $r = paket_row($u);
        return $r['paket_expires_at'] ?? null;
    }
}

if (!function_exists('paket_expiry_label')) {
    /** Label HTML tanggal expire + sisa hari (untuk profile.php / members.php). */
    function paket_expiry_label(?array $u): string {
        $p = paket_user($u);
        if ($p === 'gratis') return '<span class="small text-muted">Tanpa masa aktif (paket gratis)</span>';
        if (($u['role'] ?? '') === 'admin') return '<span class="small text-success">Admin — tidak dibatasi</span>';
        $exp = paket_expires_at($u);
        if (!$exp) return '<span class="small text-muted">Masa aktif belum diatur</span>';
        $ts   = strtotime($exp);
        $days = (int) ceil(($ts - time())/86400);
        $tgl  = date('d M Y', $ts);
        if ($days < 0)   return '<span class="small text-danger"><i class="bi bi-x-octagon"></i> Expired '.$tgl.' (otomatis ke Gratis)</span>';
        if ($days <= 7)  return '<span class="small text-warning"><i class="bi bi-clock-history"></i> Aktif s/d <b>'.$tgl.'</b> · sisa '.$days.' hari — segera perpanjang</span>';
        return '<span class="small text-success"><i class="bi bi-check2-circle"></i> Aktif s/d <b>'.$tgl.'</b> · sisa '.$days.' hari</span>';
    }
}

if (!function_exists('paket_is_pro')) {
    /** Revisi — Hierarki paket: Pro > Komunitas > Gratis.
     *  Hanya paket 'pro' yang dianggap PRO. */
    function paket_is_pro(?array $u): bool {
        return paket_user($u) === 'pro';
    }
}
if (!function_exists('paket_is_komunitas_or_higher')) {
    /** True jika user berhak atas fitur Komunitas (paket komunitas atau pro). */
    function paket_is_komunitas_or_higher(?array $u): bool {
        return in_array(paket_user($u), ['komunitas','pro'], true);
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
    function paket_wa_pro_url(string $fiturNama = 'Fitur PRO'): string {
        $msg = "Assalamu'alaikum, saya ingin memesan ".$fiturNama." di aplikasi KawanKeringat. Mohon informasinya. Terima kasih.";
        return 'https://wa.me/6281386369207?text='.rawurlencode($msg);
    }
}

if (!function_exists('paket_lock_banner')) {
    function paket_lock_banner(string $needed, string $fiturNama, string $deskripsi = ''): string {
        $needed = strtolower($needed) === 'komunitas' ? 'komunitas' : 'pro';
        $isKom  = $needed === 'komunitas';
        $cls    = $isKom ? 'success' : 'warning';
        $ico    = $isKom ? 'people-fill' : 'stars';
        $label  = $isKom ? '👥 Paket KOMUNITAS' : '⭐ Paket PRO';
        $desc   = $deskripsi !== '' ? $deskripsi : ($isKom
            ? 'Fitur ini hanya tersedia untuk paket KOMUNITAS. Upgrade paket Anda untuk mengaksesnya.'
            : 'Fitur ini terkunci. Upgrade ke paket PRO atau KOMUNITAS untuk mengaksesnya.');
        $href = '/paket_upgrade.php?need=' . urlencode($needed);
        return '<div class="alert alert-'.$cls.' shadow-sm">'
            .'<h5 class="mb-1"><i class="bi bi-'.$ico.'"></i> '.htmlspecialchars($fiturNama).' — '.$label.'</h5>'
            .'<div class="small">'.$desc.'</div>'
            .'<a href="'.$href.'" class="btn btn-'.$cls.' mt-2"><i class="bi bi-stars"></i> Lihat Paket &amp; Upgrade</a>'
            .'<div class="small text-muted mt-2">Aktivasi manual via WhatsApp admin.</div>'
            .'</div>';
    }
}

if (!function_exists('paket_pro_lock_banner')) {
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
    function paket_prices(): array {
        $pro = 49000; $kom = 99000;
        if (function_exists('app_setting_int')) {
            $pro = app_setting_int('paket_price_pro', $pro);
            $kom = app_setting_int('paket_price_komunitas', $kom);
        }
        return ['pro' => max(1000,$pro), 'komunitas' => max(1000,$kom)];
    }
}

if (!function_exists('paket_require_or_lock')) {
    /** Revisi — Hierarki paket: Pro > Komunitas > Gratis.
     *   - required = 'pro'       → hanya paket 'pro' yang boleh
     *   - required = 'komunitas' → paket 'komunitas' & 'pro' boleh
     */
    function paket_require_or_lock(string $needed, ?array $u, string $fitur, string $desc=''): void {
        $needed = strtolower($needed) === 'komunitas' ? 'komunitas' : 'pro';
        $paket  = paket_user($u);
        $ok = $needed === 'pro'
            ? ($paket === 'pro')
            : in_array($paket, ['komunitas','pro'], true);
        if (!$ok) {
            $pageTitle = 'Upgrade Paket — '.$fitur;
            include __DIR__.'/header.php';
            echo '<div class="container my-3">';
            echo paket_lock_banner($needed, $fitur, $desc);
            echo '</div>';
            include __DIR__.'/footer.php';
            exit;
        }
    }
}
