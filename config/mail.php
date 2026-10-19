<?php
// config/mail.php

if (!function_exists('env')) {
    require_once __DIR__ . '/env.php';
}

define('MAIL_ENABLED', filter_var(env('MAIL_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN));

// SMTP Settings
define('MAIL_HOST', env('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT', (int) env('MAIL_PORT', 587));
define('MAIL_SMTP_SECURE', env('MAIL_SMTP_SECURE', 'tls'));
define('MAIL_USERNAME', env('MAIL_USERNAME', ''));
define('MAIL_PASSWORD', env('MAIL_PASSWORD', ''));
define('MAIL_FROM_EMAIL', env('MAIL_FROM_EMAIL', ''));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'GoPlanner'));

// Rate limiting settings
define('MAIL_BATCH_SIZE', 50);       // Emails per batch
define('MAIL_BATCH_DELAY', 1000000); // Microseconds between emails (1 sec)
define('MAIL_DAILY_LIMIT', 450);     // Safe limit (leave buffer for other emails)
define('MAIL_QUEUE_ENABLED', true);  // Enable queue for large sends

// Calendar
define('CALENDAR_NAME', env('CALENDAR_NAME', 'GoPlanner'));
define('CALENDAR_TIMEZONE', env('CALENDAR_TIMEZONE', 'Asia/Manila'));
if (!defined('APP_URL')) {
    define('APP_URL', env('APP_URL', 'http://localhost/goplanner'));
}
