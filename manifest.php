<?php
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode([
    "name"             => "Happy Family SportApp",
    "short_name"       => "SportApp",
    "id"               => "/?app=hapfam-sportapp",
    "description"      => "Komunitas olahraga HapFam — check-in, leaderboard, dan event dalam satu app.",
    "start_url"        => "/index.php?source=pwa",
    "scope"            => "/",
    "display"          => "standalone",
    "display_override" => ["standalone", "minimal-ui"],
    "orientation"      => "portrait",
    "background_color" => "#0f172a",
    "theme_color"      => "#0f172a",
    "lang"             => "id",
    "dir"              => "ltr",
    "categories"       => ["sports","lifestyle","social","health"],
    "prefer_related_applications" => false,
    "icons" => [
        ["src"=>"/assets/icon-192.png","sizes"=>"192x192","type"=>"image/png","purpose"=>"any"],
        ["src"=>"/assets/icon-512.png","sizes"=>"512x512","type"=>"image/png","purpose"=>"any"],
        ["src"=>"/assets/icon-192.png","sizes"=>"192x192","type"=>"image/png","purpose"=>"maskable"],
        ["src"=>"/assets/icon-512.png","sizes"=>"512x512","type"=>"image/png","purpose"=>"maskable"]
    ],
    "shortcuts" => [
      ["name"=>"Check-in QR",      "url"=>"/checkin.php", "icons"=>[["src"=>"/assets/icon-192.png","sizes"=>"192x192"]]],
      ["name"=>"Upload Aktivitas", "url"=>"/upload.php",  "icons"=>[["src"=>"/assets/icon-192.png","sizes"=>"192x192"]]],
      ["name"=>"Mulai Lari",       "url"=>"/run.php",     "icons"=>[["src"=>"/assets/icon-192.png","sizes"=>"192x192"]]]
    ]
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
