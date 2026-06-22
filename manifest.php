<?php
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode([
    "name" => "KawanKeringat",
    "short_name" => "SportApp",
    "description" => "Komunitas olahraga KawanKeringat — check-in, leaderboard, dan event dalam satu app.",
    "start_url" => "/index.php?source=pwa",
    "display" => "standalone",
    "orientation" => "portrait",
    "background_color" => "#0ea5e9",
    "theme_color" => "#0ea5e9",
    "scope" => "/",
    "lang" => "id",
    "categories" => ["sports","lifestyle","social"],
    "icons" => [
        ["src"=>"/assets/icon-192.png","sizes"=>"192x192","type"=>"image/png","purpose"=>"any maskable"],
        ["src"=>"/assets/icon-512.png","sizes"=>"512x512","type"=>"image/png","purpose"=>"any maskable"]
    ],
    "shortcuts" => [
      ["name"=>"Check-in QR","url"=>"/checkin.php","icons"=>[["src"=>"/assets/icon-192.png","sizes"=>"192x192"]]],
      ["name"=>"Upload Aktivitas","url"=>"/upload.php","icons"=>[["src"=>"/assets/icon-192.png","sizes"=>"192x192"]]]
    ]
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
