<?php
header('Content-Type: application/manifest+json; charset=utf-8');
/* Revisi 27 Juni 2026 — perbaikan PWA installability:
   - Pisahkan icon "any" dan "maskable" (Chrome menolak "any maskable" untuk install prompt baru).
   - Tambah field `id` agar PWA terdaftar unik.
   - Tambah `display_override` modern → fallback `standalone`.
   - Tambah header cache-control agar manifest selalu fresh setelah update. */
header('Cache-Control: no-cache, max-age=0');
echo json_encode([
    "id" => "/?pwa=kawankeringat",
    "name" => "KawanKeringat",
    "short_name" => "SportApp",
    "description" => "Komunitas olahraga KawanKeringat — check-in, leaderboard, dan event dalam satu app.",
    "start_url" => "/index.php?source=pwa",
    "display" => "standalone",
    "display_override" => ["window-controls-overlay","standalone","minimal-ui","browser"],
    "orientation" => "portrait",
    "background_color" => "#0ea5e9",
    "theme_color" => "#0ea5e9",
    "scope" => "/",
    "lang" => "id",
    "categories" => ["sports","lifestyle","social"],
    "icons" => [
        ["src"=>"/assets/icon-192.png","sizes"=>"192x192","type"=>"image/png","purpose"=>"any"],
        ["src"=>"/assets/icon-512.png","sizes"=>"512x512","type"=>"image/png","purpose"=>"any"],
        ["src"=>"/assets/icon-192.png","sizes"=>"192x192","type"=>"image/png","purpose"=>"maskable"],
        ["src"=>"/assets/icon-512.png","sizes"=>"512x512","type"=>"image/png","purpose"=>"maskable"]
    ],
    "shortcuts" => [
      ["name"=>"Check-in QR","url"=>"/checkin.php","icons"=>[["src"=>"/assets/icon-192.png","sizes"=>"192x192"]]],
      ["name"=>"Upload Aktivitas","url"=>"/upload.php","icons"=>[["src"=>"/assets/icon-192.png","sizes"=>"192x192"]]]
    ]
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
