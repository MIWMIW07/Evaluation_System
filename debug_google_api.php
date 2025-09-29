<?php
// debug_google_api.php - Enhanced debugging version
session_start();
ob_start();
header('Content-Type: application/json');

function jsonResponse($data) {
    ob_clean();
    echo json_encode($data);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    jsonResponse([
        'success' => false, 
        'error' => 'Unauthorized access',
        'debug_info' => ['session_user_type' => $_SESSION['user_type'] ?? 'none']
    ]);
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('No action specified');
    }
    
    // Enhanced system status with detailed checks
    if ($action === 'system_status') {
        $status = [
            'success' => true,
            'database_ok' => false,
            'sheets_ok' => false,
            'drive_ok' => false,
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => [],
            'environment' => [],
            'details' => []
        ];
        
        // Check database connection
        try {
            if (file_exists('includes/db_connection.php')) {
                require_once 'includes/db_connection.php';
                
                if (function_exists('getPDO')) {
                    $pdo = getPDO();
                    $pdo->query('SELECT 1');
                    $status['database_ok'] = true;
                    $status['checks']['db_file'] = true;
                    $status['checks']['getPDO_function'] = true;
                    $status['checks']['db_query'] = true;
                } else {
                    $status['checks']['getPDO_function'] = false;
                }
            } else {
                $status['checks']['db_file'] = false;
            }
        } catch (Exception $e) {
            $status['details']['db_error'] = $e->getMessage();
        }
        
        // Check Google configuration
        $googleCredentials = getenv('GOOGLE_CREDENTIALS_JSON');
        $spreadsheetId = getenv('GOOGLE_SHEETS_ID');
        
        $status['environment']['GOOGLE_CREDENTIALS_JSON'] = $googleCredentials ? 'Set (' . strlen($googleCredentials) . ' chars)' : 'Not set';
        $status['environment']['GOOGLE_SHEETS_ID'] = $spreadsheetId ? 'Set (' . substr($spreadsheetId, 0, 10) . '...)' : 'Not set';
        
        if ($googleCredentials && $spreadsheetId) {
            $status['sheets_ok'] = true;
            $status['drive_ok'] = true;
            $status['checks']['google_credentials'] = true;
            $status['checks']['spreadsheet_id'] = true;
        }
        
        // Check vendor directory
        $status['checks']['vendor_directory'] = file_exists('vendor/autoload.php');
        $status['checks']['google_sheets_integration'] = file_exists('google_sheets_integration.php');
        $status['checks']['google_drive_reports'] = file_exists('google_drive_reports.php');
        
        jsonResponse($status);
    }
    
    // For other actions, use the regular API
    require_once 'google_integration_api.php';
    
} catch (Exception $e) {
    http_response_code(500);
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
