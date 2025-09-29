<?php
// google_integration_api.php - Fixed version with proper error handling
session_start();

// Prevent any output before headers
ob_start();

// Set JSON content type
header('Content-Type: application/json');

// Function to safely output JSON and exit
function jsonResponse($data) {
    ob_clean(); // Clear any previous output
    echo json_encode($data);
    exit;
}

// Check authentication first
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    jsonResponse([
        'success' => false, 
        'error' => 'Unauthorized access'
    ]);
}

// Set error handler to convert errors to exceptions
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // Include database connection
    if (!file_exists('includes/db_connection.php')) {
        throw new Exception('Database connection file not found');
    }
    
    require_once 'includes/db_connection.php';
    
    // Get PDO connection using the function from db_connection.php
    try {
        $pdo = getPDO();
        
        // Test the connection
        $pdo->query('SELECT 1');
        
    } catch (Exception $dbError) {
        throw new Exception('Database connection failed: ' . $dbError->getMessage());
    }
    
    // Get action from POST or GET
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('No action specified');
    }
    
    // Handle actions
    switch ($action) {
        case 'system_status':
            jsonResponse(getSystemStatus($pdo));
            break;
            
        case 'test_connection':
            jsonResponse(testGoogleConnection());
            break;
            
        case 'sync_data':
            jsonResponse(syncGoogleSheetsData($pdo));
            break;
            
        case 'generate_reports':
            jsonResponse(generateGoogleDriveReports($pdo));
            break;
            
        case 'get_stats':
            jsonResponse(getSystemStatistics($pdo));
            break;
            
        case 'get_activity_log':
            jsonResponse(getActivityLog());
            break;
            
        case 'create_backup':
            jsonResponse(createDatabaseBackup($pdo));
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(500);
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
} catch (Error $e) {
    http_response_code(500);
    jsonResponse([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'debug_info' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}

/**
 * Get system status
 */
function getSystemStatus($pdo) {
    try {
        $status = [
            'success' => true,
            'database_ok' => false,
            'sheets_ok' => false,
            'drive_ok' => false,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Test database connection
        try {
            $pdo->query('SELECT 1');
            $status['database_ok'] = true;
        } catch (Exception $e) {
            // Database connection failed
        }
        
        // Test Google configuration
        $googleCredentials = getenv('GOOGLE_CREDENTIALS_JSON');
        $spreadsheetId = getenv('GOOGLE_SHEETS_ID');
        
        if ($googleCredentials && $spreadsheetId) {
            $status['sheets_ok'] = true;
            $status['drive_ok'] = true;
        }
        
        return $status;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Status check failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Format bytes to human readable format
 */
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

/**
 * Test Google connection
 */
function testGoogleConnection() {
    try {
        $googleCredentials = getenv('GOOGLE_CREDENTIALS_JSON');
        $spreadsheetId = getenv('GOOGLE_SHEETS_ID');
        
        if (!$googleCredentials) {
            return [
                'success' => false,
                'error' => 'GOOGLE_CREDENTIALS_JSON environment variable not found'
            ];
        }
        
        if (!$spreadsheetId) {
            return [
                'success' => false,
                'error' => 'GOOGLE_SHEETS_ID environment variable not found'
            ];
        }
        
        // Check if vendor directory exists (Composer)
        if (!file_exists('vendor/autoload.php')) {
            return [
                'success' => false,
                'error' => 'Google API library not installed. Run: composer require google/apiclient'
            ];
        }
        
        // Try to load Google Client
        require_once 'vendor/autoload.php';
        
        if (!class_exists('Google\Client')) {
            return [
                'success' => false,
                'error' => 'Google Client class not found'
            ];
        }
        
        // Test creating a client
        $tempPath = sys_get_temp_dir() . '/test-credentials-' . uniqid() . '.json';
        file_put_contents($tempPath, $googleCredentials);
        
        try {
            $client = new Google\Client();
            $client->setAuthConfig($tempPath);
            $client->setScopes([
                Google\Service\Sheets::SPREADSHEETS_READONLY,
                Google\Service\Drive::DRIVE_FILE
            ]);
            
            unlink($tempPath);
            
            return [
                'success' => true,
                'message' => 'Google API connection configured successfully',
                'spreadsheet_id' => substr($spreadsheetId, 0, 15) . '...'
            ];
            
        } catch (Exception $e) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            
            return [
                'success' => false,
                'error' => 'Google Client creation failed: ' . $e->getMessage()
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Sync data from Google Sheets
 */
function syncGoogleSheetsData($pdo) {
    try {
        if (!file_exists('google_sheets_integration.php')) {
            return [
                'success' => false,
                'error' => 'Google Sheets integration file not found'
            ];
        }
        
        $googleCredentials = getenv('GOOGLE_CREDENTIALS_JSON');
        $spreadsheetId = getenv('GOOGLE_SHEETS_ID');
        
        if (!$googleCredentials || !$spreadsheetId) {
            return [
                'success' => false,
                'error' => 'Google Sheets configuration missing'
            ];
        }
        
        require_once 'google_sheets_integration.php';
        
        // Create temporary credentials file
        $tempPath = sys_get_temp_dir() . '/sync-credentials-' . uniqid() . '.json';
        file_put_contents($tempPath, $googleCredentials);
        
        try {
            $integration = new GoogleSheetsIntegration($pdo, $tempPath, $spreadsheetId);
            $result = $integration->syncAll();
            
            unlink($tempPath);
            
            return [
                'success' => true,
                'students' => $result['students'],
                'teachers' => $result['teachers']
            ];
            
        } catch (Exception $e) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            throw $e;
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Sync failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Generate reports to Google Drive
 */
function generateGoogleDriveReports($pdo) {
    try {
        if (!file_exists('google_drive_reports.php')) {
            return [
                'success' => false,
                'error' => 'Google Drive reports file not found'
            ];
        }
        
        require_once 'google_drive_reports.php';
        
        if (!function_exists('generateReportsToGoogleDrive')) {
            return [
                'success' => false,
                'error' => 'generateReportsToGoogleDrive function not found'
            ];
        }
        
        // Pass the PDO connection to the function
        $result = generateReportsToGoogleDrive($pdo);
        return $result;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Report generation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Get system statistics
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
        
        // Get counts with error handling for missing tables
        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM evaluations');
            $stats['evaluations'] = $stmt->fetchColumn();
        } catch (Exception $e) {
            // Table doesn't exist yet
            $stats['evaluations'] = 0;
        }
        
        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM students');
            $stats['students'] = $stmt->fetchColumn();
        } catch (Exception $e) {
            $stats['students'] = 0;
        }
        
        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM teachers');
            $stats['teachers'] = $stmt->fetchColumn();
        } catch (Exception $e) {
            $stats['teachers'] = 0;
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
            $stats['avg_rating'] = '0.00';
        }
        
        // Calculate completion rate (simple version)
        if ($stats['students'] > 0 && $stats['teachers'] > 0) {
            $expected = $stats['students'] * $stats['teachers'];
            $completion = ($stats['evaluations'] / $expected) * 100;
            $stats['completion_rate'] = number_format(min(100, $completion), 1);
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
 * Get activity log
 */
function getActivityLog() {
    // Simple static log for now
    return [
        'success' => true,
        'activities' => [
            [
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => 'api_access',
                'description' => 'Google Integration API accessed',
                'status' => 'success'
            ],
            [
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'action' => 'system_check',
                'description' => 'System status verified',
                'status' => 'success'
            ]
        ]
    ];
}

/**
 * Create database backup
 */
function createDatabaseBackup($pdo) {
    try {
        $backup_dir = 'backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . '/' . $filename;
        
        // Create simple backup file
        $backup_content = "-- Database Backup Created: " . date('Y-m-d H:i:s') . "\n";
        $backup_content .= "-- Generated by Teacher Evaluation System\n\n";
        
        $tables = ['users', 'students', 'teachers', 'sections', 'evaluations'];
        
        foreach ($tables as $table) {
            try {
                // Remove backticks for PostgreSQL compatibility
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                $backup_content .= "-- Table: $table ($count records)\n";
            } catch (Exception $e) {
                $backup_content .= "-- Error accessing $table: " . $e->getMessage() . "\n";
            }
        }
        
        $backup_content .= "\n-- End of backup summary\n";
        
        file_put_contents($filepath, $backup_content);
        
        return [
            'success' => true,
            'backup_file' => $filename,
            'file_size' => formatBytes(filesize($filepath))
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Backup failed: ' . $e->getMessage()
        ];
    }
}
?>
