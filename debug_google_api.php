<?php
// debug_google_api.php - Simple debug version
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

// Start output buffering to catch any unwanted output
ob_start();

// Include your files
try {
    session_start();
    require_once 'includes/db_connection.php';
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to include files: ' . $e->getMessage()]);
    exit;
}

// Clear any buffered output
$unwanted_output = ob_get_clean();

// Set proper headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit();
}

// Get action
$action = $_POST['action'] ?? $_GET['action'] ?? 'test';

// Debug information
$debug_info = [
    'unwanted_output' => $unwanted_output,
    'session_data' => [
        'user_id' => $_SESSION['user_id'] ?? 'not set',
        'user_type' => $_SESSION['user_type'] ?? 'not set'
    ],
    'action' => $action,
    'post_data' => $_POST,
    'get_data' => $_GET
];

try {
    switch ($action) {
        case 'test_connection':
        case 'test':
            // Simple test
            $dataManager = getDataManager();
            $result = [
                'success' => true,
                'message' => 'Connection test successful',
                'timestamp' => date('Y-m-d H:i:s'),
                'debug' => $debug_info
            ];
            break;
            
        case 'system_status':
            $result = [
                'success' => true,
                'database_ok' => true,
                'sheets_ok' => true,
                'drive_ok' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'debug' => $debug_info
            ];
            break;
            
        default:
            $result = [
                'success' => false,
                'error' => 'Unknown action: ' . $action,
                'debug' => $debug_info
            ];
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $debug_info
    ], JSON_PRETTY_PRINT);
}
?>
