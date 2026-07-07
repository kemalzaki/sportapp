<?php
/**
 * includes/theme_user.php — Global Theme Engine (Revisi 2026)
 *
 * Emit satu set CSS Variable global (--primary, --primary-light,
 * --primary-dark, --surface, --surface-2, --text-primary, --text-secondary,
 * --border, --shadow-sm/md/lg, --radius) sesuai tema warna yang dipilih
 * user di profile.php. Semua komponen (Header, Drawer, Bottom Nav, FAB,
 * Button, Card, Badge, Progress, Link, Switch) memakai variable ini,
 * sehingga cukup ganti tema di profile.php dan seluruh UI ikut berubah.
 *
 * TIDAK mengubah logika PHP/session/DB/API — hanya menambah CSS variable
 * dan override tampilan Bootstrap.
 */
if (!function_exists('user_theme_css')) {
    function user_theme_css(): string {
        $u = function_exists('current_user') ? current_user() : null;
        $tema = 'sky';
        $dark = 0;
        if ($u) {
            try {
                $r = db_one("SELECT COALESCE(tema_warna,'sky') AS t, COALESCE(dark_mode,0) AS d FROM users WHERE id=$1",[(int)$u['id']]);
                if ($r && !empty($r['t'])) $tema = strtolower($r['t']);
                $dark = (int)($r['d'] ?? 0);
            } catch (Throwable $e) {}
        }
        // [primary, primary-light, primary-dark]
        $palette = [
            'sky'     => ['#0ea5e9','#7dd3fc','#0369a1'],
            'indigo'  => ['#6366f1','#a5b4fc','#3730a3'],
            'emerald' => ['#10b981','#6ee7b7','#047857'],
            'rose'    => ['#f43f5e','#fda4af','#9f1239'],
            'amber'   => ['#f59e0b','#fcd34d','#92400e'],
            'violet'  => ['#8b5cf6','#c4b5fd','#5b21b6'],
            'slate'   => ['#475569','#94a3b8','#0f172a'],
        ];
        $c = $palette[$tema] ?? $palette['sky'];
        [$p, $pl, $pd] = $c;
        // Turunan alpha untuk background lembut item aktif (drawer/nav)
        $softHex = $pl . '33'; // ~20% alpha

        $lightVars = "
        --primary:{$p};
        --primary-light:{$pl};
        --primary-dark:{$pd};
        --primary-soft:{$softHex};
        --surface:#ffffff;
        --surface-2:#f8fafc;
        --surface-3:#f1f5f9;
        --text-primary:#0f172a;
        --text-secondary:#64748b;
        --text-muted:#94a3b8;
        --border:#e5e7eb;
        --radius:18px;
        --radius-sm:12px;
        --radius-lg:22px;
        --shadow-sm:0 1px 2px rgba(15,23,42,.05);
        --shadow-md:0 4px 12px rgba(15,23,42,.08);
        --shadow-lg:0 14px 40px -12px rgba(15,23,42,.18);
        --gradient-primary:linear-gradient(135deg,{$pd} 0%,{$p} 55%,{$pl} 100%);
        ";
        $darkVars = "
        --surface:#0f172a;
        --surface-2:#1e293b;
        --surface-3:#111827;
        --text-primary:#f1f5f9;
        --text-secondary:#cbd5e1;
        --text-muted:#94a3b8;
        --border:rgba(255,255,255,.10);
        --shadow-sm:0 1px 2px rgba(0,0,0,.4);
        --shadow-md:0 4px 12px rgba(0,0,0,.45);
        --shadow-lg:0 14px 40px -12px rgba(0,0,0,.6);
        ";

        // Note: --bs-* mapping agar komponen Bootstrap ikut tema tanpa
        // menyentuh HTML.
        return "
:root{
  {$lightVars}
  --bs-primary:{$p};
  --bs-primary-rgb: " . implode(',', sscanf($p,'#%02x%02x%02x')) . ";
  --bs-link-color:{$p};
  --bs-link-hover-color:{$pd};
  --bs-border-radius:12px;
  --bs-border-radius-lg:{$softHex ? '18px' : '18px'};
}
[data-bs-theme=dark]{ {$darkVars} }

/* ==== Bootstrap component theming via variables ==== */
.btn-primary,.bg-primary{background-color:var(--primary)!important;border-color:var(--primary)!important;}
.btn-primary:hover,.btn-primary:focus{background-color:var(--primary-dark)!important;border-color:var(--primary-dark)!important;}
.btn-outline-primary{color:var(--primary)!important;border-color:var(--primary)!important;}
.btn-outline-primary:hover{background:var(--primary)!important;color:#fff!important;}
.text-primary{color:var(--primary)!important;}
.link-primary,a{color:var(--primary);}
a:hover{color:var(--primary-dark);}
.border-primary{border-color:var(--primary)!important;}
.progress-bar{background:var(--gradient-primary)!important;}
.form-check-input:checked{background-color:var(--primary)!important;border-color:var(--primary)!important;}
.form-switch .form-check-input:checked{background-color:var(--primary)!important;}
.badge.bg-primary{background:var(--primary)!important;}
.nav-pills .nav-link.active,.nav-pills .show>.nav-link{background:var(--primary)!important;}

/* ==== Card / surface refinements ==== */
.card{border-radius:var(--radius)!important;border:1px solid var(--border);box-shadow:var(--shadow-sm);}
.card:hover{box-shadow:var(--shadow-md);}
.rounded-3{border-radius:var(--radius)!important;}
.shadow-sm{box-shadow:var(--shadow-sm)!important;}

/* Header/hero gradient (kompat class lama) */
.hero, .gt-top{background:var(--gradient-primary)!important;color:#fff!important;}

/* Drawer active-item indicator */
.gt-drawer .list-group-item.active,
.gt-drawer .list-group-item[aria-current='true']{
  background:var(--primary-soft)!important;color:var(--primary-dark)!important;
  border-left:3px solid var(--primary)!important;
}
";
    }
}
