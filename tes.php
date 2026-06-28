<?php

require 'includes/ai_gemini.php';

$status = gemini_config_status();
echo "<pre>";
print_r($status);

$r = gemini_text("Halo");

echo "\n\n";
print_r($r);