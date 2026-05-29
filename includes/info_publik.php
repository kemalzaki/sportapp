<?php
/**
 * Helper sederhana untuk mengambil data dari API publik dengan cache file.
 * Dipakai oleh: berita.php, beasiswa.php, kesehatan.php, sejarah_nabi.php
 */

if (!function_exists('ip_cache_dir')) {
    function ip_cache_dir(): string {
        $d = sys_get_temp_dir() . '/sportapp_publik_cache';
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }
}

if (!function_exists('ip_fetch_json')) {
    /**
     * GET JSON dengan cache file (TTL detik). Return array kosong jika gagal.
     */
    function ip_fetch_json(string $url, int $ttl = 600): array {
        $key  = ip_cache_dir() . '/' . md5($url) . '.json';
        if (is_file($key) && (time() - filemtime($key)) < $ttl) {
            $raw = @file_get_contents($key);
            $j = json_decode($raw ?: 'null', true);
            if (is_array($j)) return $j;
        }
        $body = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT      => 'HapFamSportApp/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: HapFamSportApp/1.0\r\n"]]);
            $body = @file_get_contents($url, false, $ctx);
        }
        if (!$body) return [];
        $j = json_decode($body, true);
        if (!is_array($j)) return [];
        @file_put_contents($key, $body);
        return $j;
    }
}

if (!function_exists('ip_card_open')) {
    function ip_card_open(string $title, string $icon = 'bi-newspaper', string $back = '/index.php'): void {
        echo '<div class="d-flex align-items-center justify-content-between mb-3">';
        echo '<h1 class="h4 mb-0"><i class="bi '.htmlspecialchars($icon).' text-primary"></i> '.htmlspecialchars($title).'</h1>';
        echo '<a href="'.htmlspecialchars($back).'" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Beranda</a>';
        echo '</div>';
    }
}
