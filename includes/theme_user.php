<?php
/**
 * includes/theme_user.php
 * Suntik CSS variable agar warna tema (member-selectable) ter-override
 * tanpa harus mengedit gojek-top.css / app.css.
 * Dipanggil dari includes/header.php (atau footer.php) setelah session.
 */
if (!function_exists('user_theme_css')) {
    function user_theme_css(): string {
        $u = function_exists('current_user') ? current_user() : null;
        $tema = 'sky';
        if ($u) {
            try {
                $r = db_one("SELECT COALESCE(tema_warna,'sky') AS t FROM users WHERE id=$1",[(int)$u['id']]);
                if ($r && !empty($r['t'])) $tema = strtolower($r['t']);
            } catch (Throwable $e) {}
        }
        $palette = [
            'sky'     => ['#0ea5e9','#38bdf8','#0369a1'],
            'indigo'  => ['#6366f1','#818cf8','#3730a3'],
            'emerald' => ['#10b981','#34d399','#047857'],
            'rose'    => ['#f43f5e','#fb7185','#9f1239'],
            'amber'   => ['#f59e0b','#fbbf24','#92400e'],
            'violet'  => ['#8b5cf6','#a78bfa','#5b21b6'],
            'slate'   => ['#475569','#94a3b8','#0f172a'],
        ];
        $c = $palette[$tema] ?? $palette['sky'];
        return ":root{--brand:{$c[0]};--brand-2:{$c[1]};--brand-ink:{$c[2]};}\n"
             . ".btn-primary,.bg-primary{background-color:{$c[0]} !important;border-color:{$c[0]} !important;}\n"
             . ".text-primary{color:{$c[0]} !important;}\n"
             . ".hero, .gt-top{background:linear-gradient(135deg,{$c[2]},{$c[0]}) !important;}\n";
    }
}
