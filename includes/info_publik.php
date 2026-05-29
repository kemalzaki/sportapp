<?php
/**
 * Helper untuk mengambil data dari API publik / RSS dengan cache file.
 * Dipakai oleh: berita.php, beasiswa.php, kesehatan.php, sejarah_nabi.php, buku.php
 */

if (!function_exists('ip_cache_dir')) {
    function ip_cache_dir(): string {
        $d = sys_get_temp_dir() . '/sportapp_publik_cache';
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }
}

if (!function_exists('ip_http_get')) {
    function ip_http_get(string $url, int $timeout = 8): ?string {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HapFamSportApp/1.0)',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
            return $body === false ? null : $body;
        }
        $ctx = stream_context_create(['http' => [
            'timeout' => $timeout,
            'header'  => "User-Agent: Mozilla/5.0 (compatible; HapFamSportApp/1.0)\r\n",
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : $body;
    }
}

if (!function_exists('ip_fetch_json')) {
    function ip_fetch_json(string $url, int $ttl = 600): array {
        $key = ip_cache_dir() . '/' . md5($url) . '.json';
        if (is_file($key) && (time() - filemtime($key)) < $ttl) {
            $raw = @file_get_contents($key);
            $j = json_decode($raw ?: 'null', true);
            if (is_array($j)) return $j;
        }
        $body = ip_http_get($url, 8);
        if (!$body) return [];
        $j = json_decode($body, true);
        if (!is_array($j)) return [];
        @file_put_contents($key, $body);
        return $j;
    }
}

if (!function_exists('ip_fetch_rss')) {
    /**
     * Ambil RSS feed dan normalisasi ke array
     * [ ['title','link','description','pubDate','thumbnail'], ... ]
     */
    function ip_fetch_rss(string $url, int $ttl = 600): array {
        $key = ip_cache_dir() . '/' . md5('rss:'.$url) . '.json';
        if (is_file($key) && (time() - filemtime($key)) < $ttl) {
            $raw = @file_get_contents($key);
            $j = json_decode($raw ?: 'null', true);
            if (is_array($j)) return $j;
        }
        $body = ip_http_get($url, 8);
        if (!$body) return [];

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_clear_errors();
        if (!$xml) return [];

        $items = [];
        $nodes = $xml->channel->item ?? $xml->item ?? $xml->entry ?? null;
        if (!$nodes) return [];

        foreach ($nodes as $it) {
            $title = trim((string)($it->title ?? ''));
            $link  = trim((string)($it->link ?? ''));
            if ($link === '' && isset($it->link['href'])) $link = (string)$it->link['href'];
            $desc  = trim(strip_tags((string)($it->description ?? $it->summary ?? '')));
            $date  = trim((string)($it->pubDate ?? $it->published ?? $it->updated ?? ''));

            $thumb = '';
            if (isset($it->enclosure) && isset($it->enclosure['url'])) {
                $thumb = (string)$it->enclosure['url'];
            }
            if ($thumb === '') {
                $media = $it->children('media', true);
                if ($media && isset($media->content) && isset($media->content->attributes()['url'])) {
                    $thumb = (string)$media->content->attributes()['url'];
                } elseif ($media && isset($media->thumbnail) && isset($media->thumbnail->attributes()['url'])) {
                    $thumb = (string)$media->thumbnail->attributes()['url'];
                }
            }
            if ($thumb === '' && $desc !== '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', (string)($it->description ?? ''), $m)) {
                $thumb = $m[1];
            }

            if ($title === '' || $link === '') continue;
            $items[] = [
                'title'       => $title,
                'link'        => $link,
                'description' => mb_substr($desc, 0, 400),
                'pubDate'     => $date,
                'thumbnail'   => $thumb,
            ];
        }
        if ($items) @file_put_contents($key, json_encode($items, JSON_UNESCAPED_UNICODE));
        return $items;
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
