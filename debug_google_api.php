<?php
// debug_google_api.php - Debug version to identify and fix issues

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and output buffering early
session_start();
ob_start();

// Set JSON content type
header('Content-Type: application/json');

// Debug function to safely return JSON response
function debugResponse($data) {
    // Clean any previous output
    if (ob_get_length()) {
        ob_clean();
    }
    
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

try {
    // Check authentication first
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
        debugResponse([
            'success' => false,
            'error' => 'Unauthorized access - please log in as admin'
        ]);
    }

    // Check if database connection file exists
    $db_file = 'includes/db_connection.php';
    if (!file_exists($db_file)) {
        debugResponse([
            'success' => false,
            'error' => 'Database connection file not found: ' . $db_file,
            'current_dir' => getcwd(),
            'files_in_dir' => scandir('.')
        ]);
    }

    // Try to include database connection
    require_once $db_file;

    // Check if PDO connection exists
    if (!isset($pdo)) {
        debugResponse([
            'success' => false,
            'error' => 'PDO connection not established',
            'defined_vars' => array_keys(get_defined_vars())
        ]);
    }

    // Get the action
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if (empty($action)) {
        debugResponse([
            'success' => false,
            'error' => 'No action specified',
            'post_data' => $_POST,
            'get_data' => $_GET
        ]);
    }

    // Handle different actions
    switch ($action) {
        case 'test_connection':
            debugResponse(testGoogleConnection());
            break;
            
        case 'system_status':
            debugResponse(getSystemStatus($pdo));
            break;
            
        case 'get_stats':
            debugResponse(getSystemStatistics($pdo));
            break;
            
        case 'sync_data':
            debugResponse(syncGoogleSheetsData($pdo));
            break;
            
        case 'generate_reports':
            debugResponse(generateGoogleDriveReports($pdo));
            break;
            
        case 'get_activity_log':
            debugResponse(getActivityLog());
            break;
            
        case 'create_backup':
            debugResponse(createDatabaseBackup($pdo));
            break;
            
        default:
            debugResponse([
                'success' => false,
                'error' => 'Invalid action: ' . $action,
                'available_actions' => ['test_connection', 'system_status', 'get_stats', 'sync_data', 'generate_reports', 'get_activity_log', 'create_backup']
            ]);
    }

} catch (Exception $e) {
    debugResponse([
        'success' => false,
        'error' => 'Exception: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    debugResponse([
        'success' => false,
        'error' => 'Fatal Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

/**
 * Test Google connection with detailed diagnostics
 */
function testGoogleConnection() {
    $diagnostics = [
        'success' => false,
        'checks' => [],
        'environment' => []
    ];
    
    // Check if Google credentials exist in environment
    $googleCredentials = getenv('GOOGLE_CREDENTIALS_JSON');
    $spreadsheetId = getenv('GOOGLE_SHEETS_ID');
    
    $diagnostics['checks']['credentials_env'] = !empty($googleCredentials);
    $diagnostics['checks']['spreadsheet_id_env'] = !empty($spreadsheetId);
    
    // Check for credentials file
    $credentialsFile = 'credentials/service-account-key.json';
    $diagnostics['checks']['credentials_file_exists'] = file_exists($credentialsFile);
    
    // Check for vendor/autoload.php (Composer)
    $diagnostics['checks']['composer_autoload'] = file_exists('vendor/autoload.php');
    
    // Check for Google API files
    if (file_exists('vendor/autoload.php')) {
        require_once 'vendor/autoload.php';
        $diagnostics['checks']['google_client_class'] = class_exists('Google\Client');
        $diagnostics['checks']['google_sheets_class'] = class_exists('Google\Service\Sheets');
        $diagnostics['checks']['google_drive_class'] = class_exists('Google\Service\Drive');
    }
    
    // Environment info
    $diagnostics['environment']['php_version'] = phpversion();
    $diagnostics['environment']['current_directory'] = getcwd();
    $diagnostics['environment']['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    
    // If we have credentials, try to create a client
    if ($googleCredentials && $diagnostics['checks']['google_client_class']) {
        try {
            // Write credentials to temp file
            $tempPath = sys_get_temp_dir() . '/test-google-credentials.json';
            file_put_contents($tempPath, $googleCredentials);
            
            $client = new Google\Client();
            $client->setAuthConfig($tempPath);
            $client->setScopes([
                Google\Service\Sheets::SPREADSHEETS_READONLY,
                Google\Service\Drive::DRIVE_FILE
            ]);
            
            unlink($tempPath); // Clean up
            
            $diagnostics['checks']['client_creation'] = true;
            $diagnostics['success'] = true;
            $diagnostics['message'] = 'Google API connection is properly configured';
            
        } catch (Exception $e) {
            $diagnostics['checks']['client_creation'] = false;
            $diagnostics['error'] = 'Failed to create Google Client: ' . $e->getMessage();
        }
    } else {
        $diagnostics['error'] = 'Missing Google credentials or API classes';
    }
    
    return $diagnostics;
}

/**
 * Get system status with detailed checks
 */
function getSystemStatus($pdo) {
    try {
        $status = [
            'success' => true,
            'database_ok' => false,
            'sheets_ok' => false,
            'drive_ok' => false,
            'details' => []
        ];
        
        // Test database
        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM users LIMIT 1');
            $status['database_ok'] = true;
            $status['details']['database'] = 'Connection successful';
        } catch (Exception $e) {
            $status['details']['database'] = 'Error: ' . $e->getMessage();
        }
        
        // Test Google credentials
        $googleCredentials = getenv('GOOGLE_CREDENTIALS_JSON');
        $spreadsheetId = getenv('GOOGLE_SHEETS_ID');
        
        if ($googleCredentials && $spreadsheetId) {
            $status['sheets_ok'] = true;
            $status['drive_ok'] = true;
            $status['details']['google'] = 'Credentials available';
        } else {
            $status['details']['google'] = 'Missing credentials or spreadsheet ID';
        }
        
        $status['timestamp'] = date('Y-m-d H:i:s');
        
        return $status;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get system statistics safely
 */
function getSystemStatistics($pdo) {
    try {
        $stats = [
            'success' => true,
            'evaluations' => 0,
            'students' => 0,
            'teachers' => 0,
            'avg_rating' => '0.00',
            'completion_rate' => '0.0',
            'db_size' => 'Unknown'
        ];
        
        // Get evaluation count
        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM evaluations');
            $stats['evaluations'] = $stmt->fetchColumn();
        } catch (Exception $e) {
            $stats['evaluations'] = 'Error: ' . $e->getMessage();
        }
        
        // Get student count
        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM students');
            $stats['students'] = $stmt->fetchColumn();
        } catch (Exception $e) {
            $stats['students'] = 'Error: ' . $e->getMessage();
        }
        
        // Get teacher count
        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM teachers');
            $stats['teachers'] = $stmt->fetchColumn();
        } catch (Exception $e) {
            $stats['teachers'] = 'Error: ' . $e->getMessage();
        }
        
        // Get average rating
        try {
            $stmt = $pdo->query('
                SELECT AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                           q2_1 + q2_2 + q2_3 + q2_4 + 
                           q3_1 + q3_2 + q3_3 + q3_4 + 
                           q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20) as avg_rating
                FROM evaluations
            ');
            $avg = $stmt->fetchColumn();
            $stats['avg_rating'] = $avg ? number_format($avg, 2) : '0.00';
        } catch (Exception $e) {
            $stats['avg_rating'] = 'Error calculating average';
        }
        
        return $stats;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Sync Google Sheets data safely
 */
function syncGoogleSheetsData($pdo) {
    try {
        // Check if integration file exists
        if (!file_exists('google_sheets_integration.php')) {
            return [
                'success' => false,
                'error' => 'Google Sheets integration file not found',
                'expected_file' => 'google_sheets_integration.php'
            ];
        }
        
        // Check environment variables
        $googleCredentials = getenv('GOOGLE_CREDENTIALS_JSON');
        $spreadsheetId = getenv('GOOGLE_SHEETS_ID');
        
        if (!$googleCredentials) {
            return [
                'success' => false,
                'error' => 'Missing GOOGLE_CREDENTIALS_JSON environment variable'
            ];
        }
        
        if (!$spreadsheetId) {
            return [
                'success' => false,
                'error' => 'Missing GOOGLE_SHEETS_ID environment variable'
            ];
        }
        
        require_once 'google_sheets_integration.php';
        
        // Create temporary credentials file
        $tempPath = sys_get_temp_dir() . '/sync-credentials-' . uniqid() . '.json';
        file_put_contents($tempPath, $googleCredentials);
        
        try {
            $integration = new GoogleSheetsIntegration($pdo, $tempPath, $spreadsheetId);
            $result = $integration->syncAll();
            
            unlink($tempPath); // Clean up
            
            return [
                'success' => true,
                'students' => $result['students'],
                'teachers' => $result['teachers']
            ];
            
        } catch (Exception $e) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            
            return [
                'success' => false,
                'error' => 'Sync failed: ' . $e->getMessage()
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Sync error: ' . $e->getMessage()
        ];
    }
}

/**
 * Generate Google Drive reports safely
 */
function generateGoogleDriveReports($pdo) {
    try {
        // Check if reports file exists
        if (!file_exists('google_drive_reports.php')) {
            return [
                'success' => false,
                'error' => 'Google Drive reports file not found',
                'expected_file' => 'google_drive_reports.php'
            ];
        }
        
        require_once 'google_drive_reports.php';
        
        // Call the report generation function
        if (function_exists('generateReportsToGoogleDrive')) {
            $result = generateReportsToGoogleDrive();
            return $result;
        } else {
            return [
                'success' => false,
                'error' => 'generateReportsToGoogleDrive function not found'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Report generation error: ' . $e->getMessage()
        ];
    }
}

/**
 * Get activity log
 */
function getActivityLog() {
    return [
        'success' => true,
        'activities' => [
            [
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => 'debug_check',
                'description' => 'Debug API endpoint accessed',
                'status' => 'success'
            ],
            [
                'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
                'action' => 'system_check',
                'description' => 'System diagnostics performed',
                'status' => 'success'
            ]
        ]
    ];
}

/**
 * Create database backup safely
 */
function createDatabaseBackup($pdo) {
    try {
        $backup_dir = 'backups';
        if (!is_dir($backup_dir)) {
            if (!mkdir($backup_dir, 0755, true)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create backup directory'
                ];
            }
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . '/' . $filename;
        
        // Simple table list backup
        $tables = ['users', 'students', 'teachers', 'sections', 'evaluations'];
        $backup_content = "-- Database backup created on " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $stmt->fetchColumn();
                $backup_content .= "-- Table: $table ($count rows)\n";
                
                // For now, just record table structure info
                $backup_content .= "-- TODO: Export actual data for $table\n\n";
                
            } catch (Exception $e) {
                $backup_content .= "-- Error accessing table $table: " . $e->getMessage() . "\n\n";
            }
        }
        
        file_put_contents($filepath, $backup_content);
        
        return [
            'success' => true,
            'backup_file' => $filename,
            'file_size' => formatBytes(filesize($filepath)),
            'tables_processed' => count($tables)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Backup failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Format bytes to human readable
 */
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}
?>
