<?php
// google_integration_api.php - Updated with real Google Drive reports functionality
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit();
}

$action = $_POST['action'] ?? '';
ob_end_clean();

try {
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
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

/**
 * Test Google APIs connection
 */
function testGoogleConnection() {
    try {
        $credentialsJson = getenv('GOOGLE_CREDENTIALS_JSON');
        $spreadsheetId = getenv('GOOGLE_SPREADSHEET_ID');
        
        if (!$credentialsJson) {
            throw new Exception('GOOGLE_CREDENTIALS_JSON environment variable not set');
        }
        
        if (!$spreadsheetId) {
            throw new Exception('GOOGLE_SPREADSHEET_ID environment variable not set');
        }
        
        // Validate JSON
        $credentials = json_decode($credentialsJson, true);
        if (!$credentials) {
            throw new Exception('Invalid JSON in GOOGLE_CREDENTIALS_JSON');
        }
        
        // Test actual connection
        require_once __DIR__ . '/vendor/autoload.php';
        
        $tempFile = sys_get_temp_dir() . '/google_credentials_' . uniqid() . '.json';
        file_put_contents($tempFile, $credentialsJson);
        
        $client = new Google_Client();
        $client->setAuthConfig($tempFile);
        $client->addScope([
            Google_Service_Sheets::SPREADSHEETS,
            Google_Service_Drive::DRIVE_FILE
        ]);
        
        // Test Sheets API
        $sheetsService = new Google_Service_Sheets($client);
        $spreadsheet = $sheetsService->spreadsheets->get($spreadsheetId);
        
        // Test Drive API
        $driveService = new Google_Service_Drive($client);
        $driveFiles = $driveService->files->listFiles(['pageSize' => 1]);
        
        unlink($tempFile);
        
        return [
            'success' => true,
            'sheets_accessible' => count($spreadsheet->getSheets()),
            'drive_accessible' => true,
            'user_email' => $credentials['client_email'],
            'spreadsheet_title' => $spreadsheet->getProperties()->getTitle(),
            'message' => 'Google APIs connection test successful'
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
function syncGoogleSheetsData() {
    try {
        require_once __DIR__ . '/includes/db_connection.php';
        require_once __DIR__ . '/google_sheets_integration.php';
        
        $credentialsJson = getenv('GOOGLE_CREDENTIALS_JSON');
        $spreadsheetId = getenv('GOOGLE_SPREADSHEET_ID');
        
        if (!$credentialsJson || !$spreadsheetId) {
            throw new Exception('Google credentials or spreadsheet ID not configured');
        }
        
        $tempFile = sys_get_temp_dir() . '/google_credentials_' . uniqid() . '.json';
        file_put_contents($tempFile, $credentialsJson);
        
        $sheetsIntegration = new GoogleSheetsIntegration($pdo, $tempFile, $spreadsheetId);
        $result = $sheetsIntegration->syncAll();
        
        unlink($tempFile);
        
        // Log activity
        logActivity('Data Sync', 'Synchronized data from Google Sheets', 'success');
        
        return [
            'success' => true,
            'students' => $result['students'],
            'teachers' => $result['teachers'],
            'message' => 'Data synchronization completed successfully'
        ];
        
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
        require_once __DIR__ . '/includes/db_connection.php';
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
        require_once __DIR__ . '/includes/db_connection.php';
        
        // Test database connection
        $dbOk = false;
        try {
            $stmt = $pdo->query("SELECT 1");
            $dbOk = (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            $dbOk = false;
        }
        
        // Test Google Sheets connection
        $sheetsOk = false;
        $driveOk = false;
        
        try {
            $credentialsJson = getenv('GOOGLE_CREDENTIALS_JSON');
            $spreadsheetId = getenv('GOOGLE_SPREADSHEET_ID');
            
            if ($credentialsJson && $spreadsheetId) {
                $tempFile = sys_get_temp_dir() . '/google_credentials_' . uniqid() . '.json';
                file_put_contents($tempFile, $credentialsJson);
                
                $client = new Google_Client();
                $client->setAuthConfig($tempFile);
                $client->addScope([
                    Google_Service_Sheets::SPREADSHEETS,
                    Google_Service_Drive::DRIVE_FILE
                ]);
                
                // Test Sheets
                $sheetsService = new Google_Service_Sheets($client);
                $sheetsService->spreadsheets->get($spreadsheetId);
                $sheetsOk = true;
                
                // Test Drive
                $driveService = new Google_Service_Drive($client);
                $driveService->files->listFiles(['pageSize' => 1]);
                $driveOk = true;
                
                unlink($tempFile);
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
        require_once __DIR__ . '/includes/db_connection.php';
        
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
        $sizeStmt = $pdo->query("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS db_size_mb 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ");
        $dbSize = $sizeStmt->fetchColumn();
        
        return [
            'success' => true,
            'evaluations' => (int)$evalStats['total_evaluations'],
            'avg_rating' => $evalStats['avg_score'] ? round($evalStats['avg_score'] / 20, 1) : 0, // Convert to 5-point scale
            'students' => (int)$studentCount,
            'teachers' => (int)$teacherCount,
            'completion_rate' => round($completionRate, 1),
            'db_size' => $dbSize ? $dbSize . ' MB' : '0 MB'
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
        require_once __DIR__ . '/includes/db_connection.php';
        
        // Check if activity_log table exists, create if not
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
        require_once __DIR__ . '/includes/db_connection.php';
        
        $backupFile = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupPath = __DIR__ . '/backups/' . $backupFile;
        
        // Create backups directory if it doesn't exist
        if (!is_dir(__DIR__ . '/backups')) {
            mkdir(__DIR__ . '/backups', 0755, true);
        }
        
        // Get database configuration
        $host = getenv('MYSQL_HOST') ?: 'localhost';
        $dbname = getenv('MYSQL_DATABASE') ?: 'teacher_evaluation';
        $username = getenv('MYSQL_USER') ?: 'root';
        $password = getenv('MYSQL_PASSWORD') ?: '';
        
        // Create mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s %s > %s',
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
        
        if ($returnCode === 0 && file_exists($backupPath)) {
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
            throw new Exception('Backup command failed or file not created');
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
        require_once __DIR__ . '/includes/db_connection.php';
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (action, description, status, user_id, ip_address) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $userId = $_SESSION['user_id'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt->execute([$action, $description, $status, $userId, $ipAddress]);
        
    } catch (Exception $e) {
        // Silently fail to avoid breaking the main functionality
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Format bytes to human readable format
 */
function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

exit();
?>
