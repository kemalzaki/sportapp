<?php
/**
 * includes/menu_render.php
 * Render menu dari tabel nav_menu (CMS). Mendukung parent/child 1 level.
 *
 *   nav_menu_html('drawer')  -> string HTML <ul>…</ul>
 *
 * Fallback: bila tabel kosong, kembalikan string kosong.
 */
if (!function_exists('nav_menu_items')) {
    /** Revisi Juli 2026 R8 #10-#15 — daftar URL menu yang hanya boleh
     *  tampil di drawer untuk superadmin / komunitas SuperDuperAdmin. */
    function nav_menu_super_only_urls(): array {
        return [
            '/admin/jenis.php',           // Jenis Olahraga
            '/admin/referal.php',         // Kode Referal Pendaftaran
            '/admin/lacak.php',           // Lacak HP Member
            '/admin/paket_pesanan.php',   // Pesanan Paket Member
            '/admin/paket_member.php',    // Pesanan Paket Member (varian)
            '/admin/komunitas.php',       // Komunitas Organize
            '/admin/komunitas_data.php',  // Komunitas Organize (varian)
            '/admin/sistem.php',          // Pengaturan Lainnya
        ];
    }
    function nav_menu_items(string $posisi = 'drawer'): array {
        try {
            // Revisi 27 Juni 2026 — auto-migrasi kolom paket bila admin/menu.php belum pernah dibuka.
            try { db_exec("ALTER TABLE nav_menu ADD COLUMN IF NOT EXISTS paket VARCHAR(20)"); } catch (Throwable $e) {}
            $rows = db_all("SELECT * FROM nav_menu WHERE aktif=true AND posisi=$1 ORDER BY COALESCE(parent_id,0), urutan, id", [$posisi]);
            // Revisi Juli 2026 R8 #10-#15 — sembunyikan item admin-super khusus
            // dari drawer untuk non-super. Halaman-halaman ini sendiri sudah
            // di-guard di server (require_role); ini murni kosmetik navigasi.
            try {
                require_once __DIR__ . '/scope.php';
                if (!scope_is_super()) {
                    $blocked = nav_menu_super_only_urls();
                    $rows = array_values(array_filter($rows, function($r) use ($blocked) {
                        $u = (string)($r['url'] ?? '');
                        foreach ($blocked as $b) { if (strpos($u, $b) !== false) return false; }
                        return true;
                    }));
                }
            } catch (Throwable $e) { /* fallback: biarkan */ }
            return $rows;
        } catch (Throwable $e) { return []; }
    }
    function nav_menu_html(string $posisi = 'drawer', string $wrapClass = 'list-group list-group-flush'): string {
        $rows = nav_menu_items($posisi);
        if (!$rows) return '';
        $by_parent = [];
        foreach ($rows as $r) {
            $pid = (int)($r['parent_id'] ?? 0);
            $by_parent[$pid][] = $r;
        }
        $render = function($parentId) use (&$render, $by_parent) {
            if (empty($by_parent[$parentId])) return '';
            $h = '';
            foreach ($by_parent[$parentId] as $it) {
                $icon  = $it['icon'] ? '<i class="bi '.htmlspecialchars($it['icon']).'"></i> ' : '';
                $tgt   = ($it['target']==='_blank') ? ' target="_blank" rel="noopener"' : '';
                // Revisi 27 Juni 2026 — render label paket di samping nama menu (jika di-set di admin/menu.php)
                // Revisi 29 Juni 2026 — paket dapat berisi multi nilai dipisah koma (mis. "pro,komunitas").
                // Revisi R8 Juli 2026 — sembunyikan badge "komunitas" jika paket user = pro
                //   DAN item menu memiliki paket pro & komunitas sekaligus (redundant).
                $badge = '';
                $pkRaw = strtolower((string)($it['paket'] ?? ''));
                $pks   = array_values(array_filter(array_map('trim', explode(',', $pkRaw))));
                // Deteksi paket user saat ini
                $userPaket = 'gratis';
                try {
                    if (function_exists('paket_user')) {
                        $userPaket = paket_user(current_user());
                    }
                } catch (Throwable $e) {}
                if ($userPaket === 'pro' && in_array('pro', $pks, true) && in_array('komunitas', $pks, true)) {
                    // user sudah PRO — buang label komunitas biar tidak redundan
                    $pks = array_values(array_diff($pks, ['komunitas']));
                }
                $map = [
                    'gratis'    => ['secondary', '🆓 Gratis'],
                    'pro'       => ['warning',   '⭐ PRO'],
                    'komunitas' => ['success',   '👥 Komunitas'],
                ];
                foreach ($pks as $pk) {
                    if (!isset($map[$pk])) continue;
                    $b = $map[$pk];
                    $badge .= ' <span class="badge bg-'.$b[0].' ms-1 align-middle" style="font-size:.7em">'.$b[1].'</span>';
                }
                $h .= '<a class="list-group-item list-group-item-action" href="'.htmlspecialchars($it['url']).'"'.$tgt.'>'.$icon.htmlspecialchars($it['label']).$badge.'</a>';
                $h .= $render((int)$it['id']);
            }
            return $h;
        };
        return '<div class="'.htmlspecialchars($wrapClass).'">'.$render(0).'</div>';
    }
}
