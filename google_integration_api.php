<?php
// google_integration_api.php - Fixed version with proper error handling
session_start();

// Set JSON content type and turn off output buffering
header('Content-Type: application/json');
ob_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Error handling - catch any PHP errors and convert to JSON
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // Include required files
    if (!file_exists('includes/db_connection.php')) {
        throw new Exception('Database connection file not found');
    }
    require_once 'includes/db_connection.php';
    
    // Check if PDO connection exists
    if (!isset($pdo)) {
        throw new Exception('Database connection failed');
    }
    
    // Get the action from POST request
    $action = $_POST['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('No action specified');
    }
    
    $response = ['success' => false];
    
    switch ($action) {
        case 'system_status':
            $response = getSystemStatus($pdo);
            break;
            
        case 'test_connection':
            $response = testGoogleConnection();
            break;
            
        case 'sync_data':
            $response = syncGoogleSheetsData($pdo);
            break;
            
        case 'generate_reports':
            $response = generateGoogleDriveReports($pdo);
            break;
            
        case 'get_stats':
            $response = getSystemStatistics($pdo);
            break;
            
        case 'get_activity_log':
            $response = getActivityLog();
            break;
            
        case 'create_backup':
            $response = createDatabaseBackup($pdo);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
    // Clean any output buffer and send JSON
    ob_end_clean();
    echo json_encode($response);
    
} catch (Exception $e) {
    // Clean output buffer and send error response
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    // Catch fatal errors
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

/**
 * Get system status
 */
function getSystemStatus($pdo) {
    try {
        $database_ok = false;
        $sheets_ok = false;
        $drive_ok = false;
        
        // Test database connection
        try {
            $pdo->query('SELECT 1');
            $database_ok = true;
        } catch (Exception $e) {
            // Database connection failed
        }
        
        // Test Google Sheets connection
        try {
            $googleCredentials = getenv('GOOGLE_CREDENTIALS_JSON');
            $spreadsheetId = getenv('GOOGLE_SHEETS_ID');
            
            if ($googleCredentials && $spreadsheetId) {
                $sheets_ok = true; // Basic check - credentials exist
            }
        } catch (Exception $e) {
            // Google connection failed
        }
        
        // Test Google Drive connection (same as Sheets for now)
        $drive_ok = $sheets_ok;
        
        return [
            'success' => true,
            'database_ok' => $database_ok,
            'sheets_ok' => $sheets_ok,
            'drive_ok' => $drive_ok,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Test Google connection
 */
function testGoogleConnection() {
    try {
        // Check if credentials are available
        $googleCredentials = getenv('GOOGLE_CREDENTIALS_JSON');
        $spreadsheetId = getenv('GOOGLE_SHEETS_ID');
        
        if (!$googleCredentials) {
            throw new Exception('Google credentials not found in environment variables (GOOGLE_CREDENTIALS_JSON)');
        }
        
        if (!$spreadsheetId) {
            throw new Exception('Google Sheets ID not found in environment variables (GOOGLE_SHEETS_ID)');
        }
        
        // Test if we can create a Google Client
        if (!class_exists('Google_Client')) {
            throw new Exception('Google Client library not installed. Run: composer require google/apiclient');
        }
        
        // Try to initialize the client
        $tempPath = sys_get_temp_dir() . '/google-credentials-test.json';
        file_put_contents($tempPath, $googleCredentials);
        
        $client = new Google_Client();
        $client->setAuthConfig($tempPath);
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS_READONLY]);
        
        unlink($tempPath); // Clean up temp file
        
        return [
            'success' => true,
            'message' => 'Google connection configuration is valid',
            'spreadsheet_id' => substr($spreadsheetId, 0, 10) . '...' // Show partial ID for security
        ];
        
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
        // Check if Google Sheets integration file exists
        if (!file_exists('google_sheets_integration.php')) {
            throw new Exception('Google Sheets integration file not found');
        }
        
        require_once 'google_sheets_integration.php';
        
        // Get configuration
        $googleCredentials = getenv('GOOGLE_CREDENTIALS_JSON');
        $spreadsheetId = getenv('GOOGLE_SHEETS_ID');
        
        if (!$googleCredentials || !$spreadsheetId) {
            throw new Exception('Google Sheets not configured properly');
        }
        
        // Create temporary credentials file
        $tempPath = sys_get_temp_dir() . '/google-credentials-sync.json';
        file_put_contents($tempPath, $googleCredentials);
        
        // Initialize integration
        $integration = new GoogleSheetsIntegration($pdo, $tempPath, $spreadsheetId);
        
        // Sync all data
        $result = $integration->syncAll();
        
        // Clean up temp file
        unlink($tempPath);
        
        // Log activity
        logActivity('sync_data', 'Data synchronization completed', 'success');
        
        return [
            'success' => true,
            'students' => $result['students'],
            'teachers' => $result['teachers']
        ];
        
    } catch (Exception $e) {
        logActivity('sync_data', 'Data synchronization failed: ' . $e->getMessage(), 'error');
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Generate reports to Google Drive
 */
function generateGoogleDriveReports($pdo) {
    try {
        // Check if Google Drive reports file exists
        if (!file_exists('google_drive_reports.php')) {
            throw new Exception('Google Drive reports file not found');
        }
        
        require_once 'google_drive_reports.php';
        
        // Call the report generation function
        $result = generateReportsToGoogleDrive();
        
        if ($result['success']) {
            logActivity('generate_reports', 'Reports generated successfully', 'success');
        } else {
            logActivity('generate_reports', 'Report generation failed: ' . $result['error'], 'error');
        }
        
        return $result;
        
    } catch (Exception $e) {
        logActivity('generate_reports', 'Report generation error: ' . $e->getMessage(), 'error');
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get system statistics
 */
function getSystemStatistics($pdo) {
    try {
        $stats = [];
        
        // Get evaluation count
        $stmt = $pdo->query('SELECT COUNT(*) FROM evaluations');
        $stats['evaluations'] = $stmt->fetchColumn();
        
        // Get average rating
        $stmt = $pdo->query('
            SELECT AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                       q2_1 + q2_2 + q2_3 + q2_4 + 
                       q3_1 + q3_2 + q3_3 + q3_4 + 
                       q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20) as avg_rating
            FROM evaluations
        ');
        $avg = $stmt->fetchColumn();
        $stats['avg_rating'] = $avg ? number_format($avg, 2) : '0.00';
        
        // Get student count
        $stmt = $pdo->query('SELECT COUNT(*) FROM students');
        $stats['students'] = $stmt->fetchColumn();
        
        // Get teacher count
        $stmt = $pdo->query('SELECT COUNT(*) FROM teachers');
        $stats['teachers'] = $stmt->fetchColumn();
        
        // Calculate completion rate
        $stmt = $pdo->query('SELECT COUNT(*) FROM users WHERE user_type = "student"');
        $total_students = $stmt->fetchColumn();
        
        if ($total_students > 0) {
            $completion_rate = ($stats['evaluations'] / ($stats['teachers'] * $total_students)) * 100;
            $stats['completion_rate'] = number_format(min(100, $completion_rate), 1);
        } else {
            $stats['completion_rate'] = '0.0';
        }
        
        // Get database size (approximate)
        $stmt = $pdo->query("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'db_size'
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ");
        $db_size = $stmt->fetchColumn();
        $stats['db_size'] = $db_size ? $db_size . ' MB' : 'Unknown';
        
        return [
            'success' => true,
            ...$stats
        ];
        
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
    try {
        // For now, return a simple static log
        // In a real implementation, you'd store this in a database table
        $activities = [
            [
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => 'system_check',
                'description' => 'System status checked',
                'status' => 'success'
            ],
            [
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'action' => 'sync_data',
                'description' => 'Data synchronization completed',
                'status' => 'success'
            ]
        ];
        
        return [
            'success' => true,
            'activities' => $activities
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Create database backup
 */
function createDatabaseBackup($pdo) {
    try {
        // This is a simplified backup - you might want to use mysqldump for production
        $backup_dir = 'backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . '/' . $filename;
        
        // Simple backup by exporting table data
        $tables = ['users', 'students', 'teachers', 'sections', 'evaluations'];
        $backup_content = '';
        
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("DESCRIBE $table");
                $backup_content .= "-- Table: $table\n";
                
                $stmt = $pdo->query("SELECT * FROM $table");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $backup_content .= "INSERT INTO $table VALUES\n";
                    $values = [];
                    foreach ($rows as $row) {
                        $values[] = "('" . implode("','", array_map([$pdo, 'quote'], $row)) . "')";
                    }
                    $backup_content .= implode(",\n", $values) . ";\n\n";
                }
            } catch (Exception $e) {
                $backup_content .= "-- Error backing up table $table: " . $e->getMessage() . "\n\n";
            }
        }
        
        file_put_contents($filepath, $backup_content);
        
        logActivity('create_backup', 'Database backup created: ' . $filename, 'success');
        
        return [
            'success' => true,
            'backup_file' => $filename,
            'file_size' => formatBytes(filesize($filepath))
        ];
        
    } catch (Exception $e) {
        logActivity('create_backup', 'Backup failed: ' . $e->getMessage(), 'error');
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Log activity (simplified - in production you'd use a database table)
 */
function logActivity($action, $description, $status) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'description' => $description,
        'status' => $status
    ];
    
    // In production, insert into activity_log table
    // For now, we could write to a file or just skip it
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
?>
