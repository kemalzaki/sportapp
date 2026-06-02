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
                $h .= '<a class="list-group-item list-group-item-action" href="'.htmlspecialchars($it['url']).'"'.$tgt.'>'.$icon.htmlspecialchars($it['label']).'</a>';
                $h .= $render((int)$it['id']);
            }
            return $h;
        };
        return '<div class="'.htmlspecialchars($wrapClass).'">'.$render(0).'</div>';
    }
}
