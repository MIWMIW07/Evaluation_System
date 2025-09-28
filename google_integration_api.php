<?php
// google_integration_api_fixed.php - Fixed version with better error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Start output buffering to catch any unexpected output
ob_start();

// Start session and set headers
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Log the request
file_put_contents(__DIR__ . '/api.log', date('Y-m-d H:i:s') . " - API Called\n", FILE_APPEND);

try {
    // Check admin access
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit();
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if (empty($action)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'No action specified']);
        exit();
    }
    
    // Clean any output that might have been generated
    ob_end_clean();
    
    // Log the action
    file_put_contents(__DIR__ . '/api.log', date('Y-m-d H:i:s') . " - Action: $action\n", FILE_APPEND);

    switch ($action) {
        case 'test_connection':
            echo json_encode(testGoogleConnection());
            break;
            
        case 'sync_data':
            echo json_encode(syncGoogleSheetsData());
            break;
            
        case 'generate_reports':
            echo json_encode(generateReportsToGoogleDrive());
            break;
            
        case 'system_status':
            echo json_encode(getSystemStatus());
            break;
            
        case 'get_stats':
            echo json_encode(getSystemStats());
            break;
            
        case 'get_activity_log':
            echo json_encode(getActivityLog());
            break;
            
        case 'create_backup':
            echo json_encode(createDatabaseBackup());
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
    }
} catch (Throwable $e) {
    // Catch all errors and exceptions
    ob_end_clean();
    file_put_contents(__DIR__ . '/api.log', date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

exit();

/**
 * Test Google APIs connection
 */
function testGoogleConnection() {
    try {
        // Check if autoload exists
        if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
            return [
                'success' => false,
                'error' => 'Composer autoload not found. Please run: composer install'
            ];
        }
        
        require_once __DIR__ . '/vendor/autoload.php';
        
        // Check if Google Client class exists
        if (!class_exists('Google\Client')) {
            return [
                'success' => false,
                'error' => 'Google Client library not installed. Please run: composer require google/apiclient'
            ];
        }
        
        $credentialsJson = getenv('GOOGLE_CREDENTIALS_JSON');
        $spreadsheetId = getenv('GOOGLE_SHEETS_ID');
        
        if (!$credentialsJson) {
            return [
                'success' => false,
                'error' => 'GOOGLE_CREDENTIALS_JSON environment variable not set'
            ];
        }
        
        if (!$spreadsheetId) {
            return [
                'success' => false,
                'error' => 'GOOGLE_SPREADSHEET_ID environment variable not set'
            ];
        }
        
        // Validate JSON
        $credentials = json_decode($credentialsJson, true);
        if (!$credentials) {
            return [
                'success' => false,
                'error' => 'Invalid JSON in GOOGLE_CREDENTIALS_JSON'
            ];
        }
        
        // Create temporary credentials file
        $tempFile = sys_get_temp_dir() . '/google_credentials_' . uniqid() . '.json';
        if (!file_put_contents($tempFile, $credentialsJson)) {
            return [
                'success' => false,
                'error' => 'Failed to create temporary credentials file'
            ];
        }
        
        try {
            $client = new Google\Client();
            $client->setAuthConfig($tempFile);
            $client->addScope([
                Google\Service\Sheets::SPREADSHEETS,
                Google\Service\Drive::DRIVE_FILE
            ]);
            
            // Test Sheets API
            $sheetsService = new Google\Service\Sheets($client);
            $spreadsheet = $sheetsService->spreadsheets->get($spreadsheetId);
            
            // Test Drive API
            $driveService = new Google\Service\Drive($client);
            $driveFiles = $driveService->files->listFiles(['pageSize' => 1]);
            
            return [
                'success' => true,
                'sheets_accessible' => count($spreadsheet->getSheets()),
                'drive_accessible' => true,
                'user_email' => $credentials['client_email'],
                'spreadsheet_title' => $spreadsheet->getProperties()->getTitle(),
                'message' => 'Google APIs connection test successful'
            ];
            
        } finally {
            // Always clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
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
function syncGoogleSheetsData() {
    try {
        // Check if database connection file exists
        if (!file_exists(__DIR__ . '/includes/db_connection.php')) {
            return [
                'success' => false,
                'error' => 'Database connection file not found'
            ];
        }
        
        require_once __DIR__ . '/includes/db_connection.php';
        
        if (!isset($pdo)) {
            return [
                'success' => false,
                'error' => 'Database connection not available'
            ];
        }
        
        // Check if Google Sheets integration file exists
        if (!file_exists(__DIR__ . '/google_sheets_integration.php')) {
            return [
                'success' => false,
                'error' => 'Google Sheets integration file not found'
            ];
        }
        
        require_once __DIR__ . '/google_sheets_integration.php';
        
        $credentialsJson = getenv('GOOGLE_CREDENTIALS_JSON');
        $spreadsheetId = getenv('GOOGLE_SPREADSHEET_ID');
        
        if (!$credentialsJson || !$spreadsheetId) {
            return [
                'success' => false,
                'error' => 'Google credentials or spreadsheet ID not configured'
            ];
        }
        
        $tempFile = sys_get_temp_dir() . '/google_credentials_' . uniqid() . '.json';
        file_put_contents($tempFile, $credentialsJson);
        
        try {
            $sheetsIntegration = new GoogleSheetsIntegration($pdo, $tempFile, $spreadsheetId);
            $result = $sheetsIntegration->syncAll();
            
            // Log activity
            logActivity('Data Sync', 'Synchronized data from Google Sheets', 'success');
            
            return [
                'success' => true,
                'students' => $result['students'],
                'teachers' => $result['teachers'],
                'message' => 'Data synchronization completed successfully'
            ];
            
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        
    } catch (Exception $e) {
        logActivity('Data Sync', 'Failed: ' . $e->getMessage(), 'error');
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Generate all evaluation reports and save to Google Drive
 */
function generateReportsToGoogleDrive() {
    try {
        // Check if database connection exists
        if (!file_exists(__DIR__ . '/includes/db_connection.php')) {
            return [
                'success' => false,
                'error' => 'Database connection file not found'
            ];
        }
        
        require_once __DIR__ . '/includes/db_connection.php';
        
        if (!isset($pdo)) {
            return [
                'success' => false,
                'error' => 'Database connection not available'
            ];
        }
        
        // Check if reports generator exists
        if (!file_exists(__DIR__ . '/google_drive_reports.php')) {
            return [
                'success' => false,
                'error' => 'Google Drive reports file not found'
            ];
        }
        
        require_once __DIR__ . '/google_drive_reports.php';
        
        // Check if there are evaluations to process
        $stmt = $pdo->query("SELECT COUNT(*) FROM evaluations");
        $evaluationCount = $stmt->fetchColumn();
        
        if ($evaluationCount == 0) {
            return [
                'success' => false,
                'error' => 'No evaluations found to generate reports'
            ];
        }
        
        // Check if GoogleDriveReportsGenerator class exists
        if (!class_exists('GoogleDriveReportsGenerator')) {
            return [
                'success' => false,
                'error' => 'GoogleDriveReportsGenerator class not found'
            ];
        }
        
        // Initialize the reports generator
        $generator = new GoogleDriveReportsGenerator($pdo);
        
        // Generate all reports
        $result = $generator->generateAllReports();
        
        if ($result['success']) {
            // Log successful report generation
            logActivity(
                'Report Generation', 
                "Generated {$result['individual_reports']} individual reports and {$result['summary_reports']} summary reports for {$result['teachers_processed']} teachers", 
                'success'
            );
        } else {
            logActivity('Report Generation', 'Failed: ' . ($result['error'] ?? 'Unknown error'), 'error');
        }
        
        return $result;
        
    } catch (Exception $e) {
        logActivity('Report Generation', 'Failed: ' . $e->getMessage(), 'error');
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get system status
 */
function getSystemStatus() {
    try {
        // Test database connection
        $dbOk = false;
        try {
            if (file_exists(__DIR__ . '/includes/db_connection.php')) {
                require_once __DIR__ . '/includes/db_connection.php';
                if (isset($pdo)) {
                    $stmt = $pdo->query("SELECT 1");
                    $dbOk = (bool)$stmt->fetchColumn();
                }
            }
        } catch (Exception $e) {
            $dbOk = false;
        }
        
        // Test Google Sheets connection
        $sheetsOk = false;
        $driveOk = false;
        
        try {
            $credentialsJson = getenv('GOOGLE_CREDENTIALS_JSON');
            $spreadsheetId = getenv('GOOGLE_SPREADSHEET_ID');
            
            if ($credentialsJson && $spreadsheetId && file_exists(__DIR__ . '/vendor/autoload.php')) {
                require_once __DIR__ . '/vendor/autoload.php';
                
                $tempFile = sys_get_temp_dir() . '/google_credentials_' . uniqid() . '.json';
                file_put_contents($tempFile, $credentialsJson);
                
                try {
                    $client = new Google\Client();
                    $client->setAuthConfig($tempFile);
                    $client->addScope([
                        Google\Service\Sheets::SPREADSHEETS,
                        Google\Service\Drive::DRIVE_FILE
                    ]);
                    
                    // Test Sheets
                    $sheetsService = new Google\Service\Sheets($client);
                    $sheetsService->spreadsheets->get($spreadsheetId);
                    $sheetsOk = true;
                    
                    // Test Drive
                    $driveService = new Google\Service\Drive($client);
                    $driveService->files->listFiles(['pageSize' => 1]);
                    $driveOk = true;
                    
                } finally {
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                }
            }
        } catch (Exception $e) {
            // Google services unavailable
        }
        
        return [
            'success' => true,
            'database_ok' => $dbOk,
            'sheets_ok' => $sheetsOk,
            'drive_ok' => $driveOk,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'database_ok' => false,
            'sheets_ok' => false,
            'drive_ok' => false,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Get system statistics
 */
function getSystemStats() {
    try {
        if (!file_exists(__DIR__ . '/includes/db_connection.php')) {
            return ['success' => false, 'error' => 'Database connection not available'];
        }
        
        require_once __DIR__ . '/includes/db_connection.php';
        
        if (!isset($pdo)) {
            return ['success' => false, 'error' => 'PDO not available'];
        }
        
        // Get evaluation statistics
        $evalStmt = $pdo->query("
            SELECT 
                COUNT(*) as total_evaluations,
                AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                     q2_1 + q2_2 + q2_3 + q2_4 + 
                     q3_1 + q3_2 + q3_3 + q3_4 + 
                     q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as avg_score
            FROM evaluations
        ");
        $evalStats = $evalStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get student count
        $studentStmt = $pdo->query("SELECT COUNT(*) FROM students");
        $studentCount = $studentStmt->fetchColumn();
        
        // Get teacher count
        $teacherStmt = $pdo->query("SELECT COUNT(DISTINCT name) FROM teachers");
        $teacherCount = $teacherStmt->fetchColumn();
        
        // Calculate completion rate (students who have submitted evaluations)
        $completionStmt = $pdo->query("
            SELECT COUNT(DISTINCT student_id) as students_evaluated 
            FROM evaluations
        ");
        $studentsEvaluated = $completionStmt->fetchColumn();
        $completionRate = $studentCount > 0 ? ($studentsEvaluated / $studentCount) * 100 : 0;
        
        // Get database size (approximate)
        $dbSize = 'Unknown';
        try {
            $sizeStmt = $pdo->query("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS db_size_mb 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            $size = $sizeStmt->fetchColumn();
            if ($size) {
                $dbSize = $size . ' MB';
            }
        } catch (Exception $e) {
            // Database size query failed, ignore
        }
        
        return [
            'success' => true,
            'evaluations' => (int)$evalStats['total_evaluations'],
            'avg_rating' => $evalStats['avg_score'] ? round($evalStats['avg_score'] / 20, 1) : 0, // Convert to 5-point scale
            'students' => (int)$studentCount,
            'teachers' => (int)$teacherCount,
            'completion_rate' => round($completionRate, 1),
            'db_size' => $dbSize
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
        if (!file_exists(__DIR__ . '/includes/db_connection.php')) {
            return ['success' => false, 'error' => 'Database connection not available'];
        }
        
        require_once __DIR__ . '/includes/db_connection.php';
        
        if (!isset($pdo)) {
            return ['success' => false, 'error' => 'PDO not available'];
        }
        
        // Check if activity_log table exists, create if not
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS activity_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                    action VARCHAR(100) NOT NULL,
                    description TEXT,
                    status ENUM('success', 'error', 'warning') DEFAULT 'success',
                    user_id INT,
                    ip_address VARCHAR(45)
                )
            ");
        } catch (Exception $e) {
            // Table creation failed, ignore
        }
        
        $stmt = $pdo->query("
            SELECT timestamp, action, description, status 
            FROM activity_log 
            ORDER BY timestamp DESC 
            LIMIT 20
        ");
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format timestamps
        foreach ($activities as &$activity) {
            $activity['timestamp'] = date('M j, Y g:i A', strtotime($activity['timestamp']));
        }
        
        return [
            'success' => true,
            'activities' => $activities
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'activities' => []
        ];
    }
}

/**
 * Create database backup
 */
function createDatabaseBackup() {
    try {
        if (!file_exists(__DIR__ . '/includes/db_connection.php')) {
            return ['success' => false, 'error' => 'Database connection not available'];
        }
        
        require_once __DIR__ . '/includes/db_connection.php';
        
        if (!isset($pdo)) {
            return ['success' => false, 'error' => 'PDO not available'];
        }
        
        $backupFile = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupPath = __DIR__ . '/backups/' . $backupFile;
        
        // Create backups directory if it doesn't exist
        if (!is_dir(__DIR__ . '/backups')) {
            if (!mkdir(__DIR__ . '/backups', 0755, true)) {
                return ['success' => false, 'error' => 'Could not create backups directory'];
            }
        }
        
        // Get database configuration from environment or defaults
        $host = getenv('MYSQL_HOST') ?: 'localhost';
        $dbname = getenv('MYSQL_DATABASE') ?: 'teacher_evaluation';
        $username = getenv('MYSQL_USER') ?: 'root';
        $password = getenv('MYSQL_PASSWORD') ?: '';
        
        // Check if mysqldump is available
        $mysqldumpPath = 'mysqldump';
        if (PHP_OS_FAMILY === 'Windows') {
            // Try common Windows paths
            $possiblePaths = [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe'
            ];
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $mysqldumpPath = '"' . $path . '"';
                    break;
                }
            }
        }
        
        // Create mysqldump command
        $command = sprintf(
            '%s --host=%s --user=%s --password=%s %s > %s 2>&1',
            $mysqldumpPath,
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($dbname),
            escapeshellarg($backupPath)
        );
        
        // Execute backup command
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupPath) && filesize($backupPath) > 0) {
            $fileSize = filesize($backupPath);
            $fileSizeFormatted = formatBytes($fileSize);
            
            logActivity('Database Backup', "Created backup: $backupFile ($fileSizeFormatted)", 'success');
            
            return [
                'success' => true,
                'backup_file' => $backupFile,
                'file_size' => $fileSizeFormatted,
                'path' => $backupPath
            ];
        } else {
            $errorMsg = implode("\n", $output);
            return [
                'success' => false,
                'error' => 'Backup command failed: ' . $errorMsg
            ];
        }
        
    } catch (Exception $e) {
        logActivity('Database Backup', 'Failed: ' . $e->getMessage(), 'error');
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Log activity to database
 */
function logActivity($action, $description, $status = 'success') {
    try {
        if (file_exists(__DIR__ . '/includes/db_connection.php')) {
            require_once __DIR__ . '/includes/db_connection.php';
            
            if (isset($pdo)) {
                $stmt = $pdo->prepare("
                    INSERT INTO activity_log (action, description, status, user_id, ip_address) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $userId = $_SESSION['user_id'] ?? null;
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                
                $stmt->execute([$action, $description, $status, $userId, $ipAddress]);
            }
        }
    } catch (Exception $e) {
        // Silently fail to avoid breaking the main functionality
        file_put_contents(__DIR__ . '/api.log', date('Y-m-d H:i:s') . " - Log activity failed: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

/**
 * Format bytes to human readable format
 */
function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    $base = log($size, 1024);
    $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}
?>
