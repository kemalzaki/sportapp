<?php
/**
 * includes/theme_user.php  —  Revisi Nov 2026 (Global Theme Engine)
 *
 * Menyediakan CSS Variable global (--primary, --primary-light, --primary-dark,
 * --surface, --text-primary, --text-secondary, dsb.) yang dipakai oleh SELURUH
 * komponen UI (Header, Drawer, Bottom Nav, FAB, Card, Button, Badge, Link,
 * Progress Bar, Icon aktif) — konsisten seperti Strava / Nike Run Club /
 * Samsung Health / Google Fit.
 *
 * Tema diambil dari kolom users.tema_warna (yang di-set di profile.php).
 * Tidak mengubah struktur database — hanya menambah palette baru bila belum ada.
 */
if (!function_exists('user_theme_css')) {
    function user_theme_css(): string {
        $u = function_exists('current_user') ? current_user() : null;
        $tema = 'sky';
        if ($u) {
            try {
                $r = db_one("SELECT COALESCE(tema_warna,'sky') AS t FROM users WHERE id=$1", [(int)$u['id']]);
                if ($r && !empty($r['t'])) $tema = strtolower($r['t']);
            } catch (Throwable $e) {}
        }
        // [primary, primary-light, primary-dark]
        $palette = [
            'sky'     => ['#0ea5e9', '#7dd3fc', '#0369a1'],
            'indigo'  => ['#6366f1', '#a5b4fc', '#3730a3'],
            'emerald' => ['#10b981', '#6ee7b7', '#047857'],
            'rose'    => ['#f43f5e', '#fda4af', '#9f1239'],
            'amber'   => ['#f59e0b', '#fcd34d', '#92400e'],
            'violet'  => ['#8b5cf6', '#c4b5fd', '#5b21b6'],
            'slate'   => ['#475569', '#94a3b8', '#0f172a'],
            'orange'  => ['#fc4c02', '#ff8a5b', '#c23a00'], // Strava-ish
            'teal'    => ['#14b8a6', '#5eead4', '#0f766e'],
        ];
        $c = $palette[$tema] ?? $palette['sky'];
        [$p, $pl, $pd] = $c;

        // hex -> rgb helper
        $hex2rgb = function ($h) {
            $h = ltrim($h, '#');
            if (strlen($h) === 3) { $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2]; }
            return sprintf('%d, %d, %d',
                hexdec(substr($h,0,2)), hexdec(substr($h,2,2)), hexdec(substr($h,4,2)));
        };
        $pRgb  = $hex2rgb($p);
        $pdRgb = $hex2rgb($pd);

        return <<<CSS
:root{
  /* === Global Theme Engine === */
  --primary: {$p};
  --primary-light: {$pl};
  --primary-dark: {$pd};
  --primary-rgb: {$pRgb};
  --primary-dark-rgb: {$pdRgb};
  --primary-soft: rgba({$pRgb}, .10);
  --primary-soft-2: rgba({$pRgb}, .18);
  --primary-gradient: linear-gradient(135deg, {$pd}, {$p});
  --primary-gradient-soft: linear-gradient(135deg, rgba({$pRgb},.12), rgba({$pdRgb},.06));

  --surface: #ffffff;
  --surface-alt: #f7f8fa;
  --surface-elevated: #ffffff;
  --border-soft: #eef0f3;
  --border-strong: #e2e6eb;

  --text-primary: #0f172a;
  --text-secondary: #64748b;
  --text-muted: #94a3b8;

  --radius-card: 20px;
  --radius-lg: 18px;
  --radius-md: 14px;
  --radius-pill: 999px;
  --shadow-card: 0 4px 20px rgba(15, 23, 42, .06);
  --shadow-elevated: 0 12px 32px rgba(15, 23, 42, .10);
  --shadow-fab: 0 10px 24px rgba({$pRgb}, .35), 0 4px 10px rgba(15,23,42,.12);

  /* Legacy alias */
  --brand: {$p};
  --brand-2: {$pl};
  --brand-ink: {$pd};

  /* Bootstrap overrides */
  --bs-primary: {$p};
  --bs-primary-rgb: {$pRgb};
  --bs-link-color: {$p};
  --bs-link-hover-color: {$pd};
}
CSS;
    }
}
