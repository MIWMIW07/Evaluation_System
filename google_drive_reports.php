<?php
// debug_logs.php - Check what's happening during report generation
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    die('Access denied');
}

// Read the last 100 lines of error log
$logFile = '/var/log/apache2/error.log'; // or wherever your error log is
if (file_exists($logFile)) {
    echo "<pre>" . shell_exec("tail -100 " . escapeshellarg($logFile)) . "</pre>";
} else {
    echo "Log file not found. Trying alternative locations...<br>";
    
    // Try common log locations
    $commonLogs = [
        '/var/log/apache2/error.log',
        '/var/log/httpd/error_log',
        '/var/www/html/error_log',
        'error_log'
    ];
    
    foreach ($commonLogs as $log) {
        if (file_exists($log)) {
            echo "<h3>Found: $log</h3>";
            echo "<pre>" . shell_exec("tail -50 " . escapeshellarg($log)) . "</pre>";
            break;
        }
    }
}
?>
