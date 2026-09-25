<?php
require_once __DIR__ . '/env.php';

return [
    'host' => env('SMTP_HOST', 'smtp.gmail.com'),
    'port' => (int) env('SMTP_PORT', 587),
    'username' => env('SMTP_USERNAME', ''),
    'password' => env('SMTP_PASSWORD', ''),
    'from_email' => env('SMTP_FROM_EMAIL', ''),
    'from_name' => env('SMTP_FROM_NAME', 'noreply.AgriTrack'),
];