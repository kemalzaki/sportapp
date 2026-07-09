<?php

require 'includes/ai_router.php';

$status = ai_config_status();
echo "<pre>";
print_r($status);

$r = ai_chat("Halo");

echo "\n\n";
print_r($r);