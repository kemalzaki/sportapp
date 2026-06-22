<?php
/**
 * HTMX helper — drop-in untuk SportApp.
 * Pakai di setiap halaman:
 *
 *   require_once __DIR__.'/includes/htmx.php';
 *   $pageTitle = 'Beranda';
 *   htmx_layout_start($pageTitle);
 *       // ... isi halaman (HTML) ...
 *   htmx_layout_end();
 *
 * Saat request datang dari HTMX (header HX-Request: true), hanya isi konten
 * yang dikirim — header/footer/nav tidak diikut. Saat request normal,
 * header + footer + bottom nav tetap di-include seperti biasa.
 */

if (!function_exists('is_htmx')) {
    function is_htmx(): bool {
        return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    }
    function is_htmx_boosted(): bool {
        return isset($_SERVER['HTTP_HX_BOOSTED']) && $_SERVER['HTTP_HX_BOOSTED'] === 'true';
    }
    /** Kirim instruksi HTMX ke browser. */
    function htmx_trigger(string $event, $detail = null): void {
        $val = $detail === null ? $event : json_encode([$event => $detail]);
        header('HX-Trigger: ' . $val);
    }
    function htmx_redirect(string $url): void {
        if (is_htmx()) { header('HX-Redirect: ' . $url); exit; }
        header('Location: ' . $url); exit;
    }
    function htmx_push_url(string $url): void {
        if (is_htmx()) header('HX-Push-Url: ' . $url);
    }
}

if (!function_exists('htmx_layout_start')) {
    /**
     * Mulai layout. Bila request HTMX: hanya kirim <title> OOB + buka container.
     * Bila normal: include header.php.
     */
    function htmx_layout_start(string $title = 'KawanKeringat'): void {
        global $pageTitle;
        $pageTitle = $title;

        if (is_htmx()) {
            // Update title via Out-of-Band swap
            header('HX-Push-Url: ' . ($_SERVER['REQUEST_URI'] ?? '/'));
            echo '<title hx-swap-oob="true">' . htmlspecialchars($title . ' · KawanKeringat') . '</title>';
            // Container fragment — ditangkap oleh #app di shell
            echo '<div id="app-content" data-page="' . htmlspecialchars($title) . '">';
            return;
        }

        // Mode normal: full page
        require __DIR__ . '/header.php';
        echo '<div id="app-content" data-page="' . htmlspecialchars($title) . '">';
    }

    function htmx_layout_end(): void {
        echo '</div>'; // /#app-content
        if (is_htmx()) return; // jangan kirim footer saat fragment

        // Bottom nav + footer normal
        if (file_exists(__DIR__ . '/bottom_nav.php')) require __DIR__ . '/bottom_nav.php';
        if (file_exists(__DIR__ . '/footer.php'))     require __DIR__ . '/footer.php';
    }
}
