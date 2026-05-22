<?php
// Google Maps API key.
// Wajib: aktifkan "Maps JavaScript API", "Places API", "Geocoding API" di Google Cloud Console.
// Lalu set HTTP referrer restriction ke domain Anda.
$GOOGLE_MAPS_API_KEY = getenv('GOOGLE_MAPS_API_KEY') ?: 'PASTE_YOUR_GOOGLE_MAPS_API_KEY_HERE';
