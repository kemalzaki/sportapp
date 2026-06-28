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
    function nav_menu_items(string $posisi = 'drawer'): array {
        try {
            // Revisi 27 Juni 2026 — auto-migrasi kolom paket bila admin/menu.php belum pernah dibuka.
            try { db_exec("ALTER TABLE nav_menu ADD COLUMN IF NOT EXISTS paket VARCHAR(20)"); } catch (Throwable $e) {}
            return db_all("SELECT * FROM nav_menu WHERE aktif=true AND posisi=$1 ORDER BY COALESCE(parent_id,0), urutan, id", [$posisi]);
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
                $badge = '';
                $pk = strtolower((string)($it['paket'] ?? ''));
                if (in_array($pk, ['gratis','pro','komunitas'], true)) {
                    $map = [
                        'gratis'    => ['secondary', '🆓 Gratis'],
                        'pro'       => ['warning',   '⭐ PRO'],
                        'komunitas' => ['success',   '👥 Komunitas'],
                    ];
                    $b = $map[$pk];
                    $badge = ' <span class="badge bg-'.$b[0].' ms-1 align-middle" style="font-size:.7em">'.$b[1].'</span>';
                }
                $h .= '<a class="list-group-item list-group-item-action" href="'.htmlspecialchars($it['url']).'"'.$tgt.'>'.$icon.htmlspecialchars($it['label']).$badge.'</a>';
                $h .= $render((int)$it['id']);
            }
            return $h;
        };
        return '<div class="'.htmlspecialchars($wrapClass).'">'.$render(0).'</div>';
    }
}
