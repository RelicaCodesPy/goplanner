<?php
// api/mail/process_queue.php
// Run this daily via cron: php /path/to/process_queue.php
// Or visit: /goplanner%201/api/mail/process_queue.php?key=YOUR_SECRET_KEY

require_once __DIR__ . '/../../config/mail.php';

// Simple auth for cron
if (php_sapi_name() === 'cli') {
    $key = 'cli';
} else {
    $secretKey = function_exists('env') ? (string) env('GOPLANNER_CRON_SECRET', '') : '';
    $key = $_GET['key'] ?? '';
    if ($key === '' || $secretKey === '' || !hash_equals($secretKey, $key)) {
        http_response_code(403);
        die('Forbidden');
    }
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/MailHelper.php';

$db = db();

$result = MailHelper::processQueue($db);

if (php_sapi_name() === 'cli') {
    echo "Queue processed: {$result['sent']} sent, {$result['failed']} failed\n";
} else {
    header('Content-Type: application/json');
    echo json_encode($result);
}
?>
