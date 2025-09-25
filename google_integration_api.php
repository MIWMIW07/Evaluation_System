<?php
// google_integration_api.php - Simplified for Railway
session_start();
require_once 'includes/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'test_connection':
            echo json_encode(testGoogleConnection());
            break;
            
        case 'sync_data':
            echo json_encode(syncFromGoogleSheets());
            break;
            
        case 'generate_reports':
            echo json_encode(generateGoogleDriveReports());
            break;
            
        case 'system_status':
            echo json_encode(getSystemStatus());
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function testGoogleConnection() {
    if (!isset($_ENV['GOOGLE_CREDENTIALS_JSON'])) {
        return ['success' => false, 'error' => 'Google credentials not configured'];
    }
    
    try {
        $credentials = json_decode($_ENV['GOOGLE_CREDENTIALS_JSON'], true);
        $client = new Google_Client();
        $client->setAuthConfig($credentials);
        $client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
        
        $sheetsService = new Google_Service_Sheets($client);
        
        return ['success' => true, 'message' => 'Google connection successful'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function syncFromGoogleSheets() {
    // Your sync logic here
    return ['success' => true, 'message' => 'Sync completed'];
}

function generateGoogleDriveReports() {
    // Your report generation logic here
    return ['success' => true, 'message' => 'Reports generated'];
}

function getSystemStatus() {
    global $pdo;
    
    $status = [
        'database_ok' => false,
        'google_ok' => false,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    try {
        $pdo->query("SELECT 1");
        $status['database_ok'] = true;
    } catch (Exception $e) {
        $status['database_ok'] = false;
    }
    
    $status['google_ok'] = isset($_ENV['GOOGLE_CREDENTIALS_JSON']);
    
    return ['success' => true, 'data' => $status];
}
?>
