<?php
// PWA manifest (served as PHP for ease) - bisa juga simpan ke /manifest.json statis
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode([
    "name" => "HapFam SportApp",
    "short_name" => "SportApp",
    "start_url" => "/index.php",
    "display" => "standalone",
    "background_color" => "#0f172a",
    "theme_color" => "#0ea5e9",
    "scope" => "/",
    "icons" => [
        ["src"=>"/assets/icon-192.png","sizes"=>"192x192","type"=>"image/png"],
        ["src"=>"/assets/icon-512.png","sizes"=>"512x512","type"=>"image/png"]
    ]
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
