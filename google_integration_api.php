<?php
// google_integration_api.php - Complete implementation
session_start();

require_once 'vendor/autoload.php';
require_once 'includes/db_connection.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit();
}

// Get action from POST data
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'test_connection':
            echo json_encode(testConnection());
            break;
            
        case 'sync_data':
            echo json_encode(syncDataFromSheets());
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
            echo json_encode(createBackup());
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Test connection to Google APIs
 */
function testConnection() {
    $credentialsPath = 'credentials/service-account-key.json';
    $sheetId = $_POST['sheet_id'] ?? '';
    
    if (!file_exists($credentialsPath)) {
        return ['success' => false, 'error' => 'Google credentials file not found. Please check credentials/service-account-key.json'];
    }
    
    if (empty($sheetId)) {
        return ['success' => false, 'error' => 'Google Sheets ID is required'];
    }
    
    try {
        // Test Google Sheets API
        $client = new Google_Client();
        $client->setApplicationName('Teacher Evaluation System');
        $client->setScopes([
            \Google_Service_Sheets::SPREADSHEETS_READONLY,
            \Google_Service_Drive::DRIVE_FILE,
            \Google_Service_Drive::DRIVE
        ]);
        $client->setAuthConfig($credentialsPath);
        
        $sheetsService = new \Google_Service_Sheets($client);
        $driveService = new \Google_Service_Drive($client);
        
        // Test Sheets access
        $spreadsheet = $sheetsService->spreadsheets->get($sheetId);
        $sheetsAccessible = count($spreadsheet->getSheets());
        
        // Test Drive access
        $driveAbout = $driveService->about->get(['fields' => 'user']);
        
        logActivity('Connection Test', 'Google APIs connection test successful', 'success');
        
        return [
            'success' => true,
            'sheets_accessible' => $sheetsAccessible,
            'drive_accessible' => true,
            'user_email' => $driveAbout->getUser()->getEmailAddress()
        ];
        
    } catch (Exception $e) {
        logActivity('Connection Test', 'Failed: ' . $e->getMessage(), 'error');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Sync data from Google Sheets
 */
function syncDataFromSheets() {
    global $pdo;
    
    $sheetId = $_POST['sheet_id'] ?? '';
    $credentialsPath = 'credentials/service-account-key.json';
    
    if (!file_exists($credentialsPath)) {
        return ['success' => false, 'error' => 'Google credentials file not found'];
    }
    
    if (empty($sheetId)) {
        return ['success' => false, 'error' => 'Google Sheets ID is required'];
    }
    
    try {
        require_once 'google_sheets_integration.php';
        $googleSheets = new GoogleSheetsIntegration($pdo, $credentialsPath, $sheetId);
        $result = $googleSheets->syncAll();
        
        if ($result['students']['success'] || $result['teachers']['success']) {
            logActivity('Data Sync', 'Synchronized data from Google Sheets', 'success');
        } else {
            logActivity('Data Sync', 'Sync completed with errors', 'warning');
        }
        
        return $result;
        
    } catch (Exception $e) {
        logActivity('Data Sync', 'Failed: ' . $e->getMessage(), 'error');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Generate reports to Google Drive (enhancement of existing generate_report.php)
 */
function generateReportsToGoogleDrive() {
    global $pdo;
    
    $credentialsPath = 'credentials/service-account-key.json';
    
    if (!file_exists($credentialsPath)) {
        return ['success' => false, 'error' => 'Google credentials file not found'];
    }
    
    try {
        // First, use the existing report generation to create local reports
        ob_start();
        include 'generate_report.php';
        $output = ob_get_clean();
        
        // Now upload the generated reports to Google Drive
        $client = new Google_Client();
        $client->setApplicationName('Teacher Evaluation System');
        $client->setScopes([\Google_Service_Drive::DRIVE_FILE, \Google_Service_Drive::DRIVE]);
        $client->setAuthConfig($credentialsPath);
        
        $driveService = new \Google_Service_Drive($client);
        
        // Create main folder in Google Drive
        $mainFolderId = getOrCreateDriveFolder($driveService, 'Teacher_Evaluation_Reports', null);
        
        $uploadedFiles = 0;
        $uploadedFolders = 0;
        
        // Upload the reports directory structure to Google Drive
        if (is_dir('reports')) {
            uploadDirectoryToGoogleDrive($driveService, 'reports', $mainFolderId, $uploadedFiles, $uploadedFolders);
        }
        
        logActivity('Report Generation', "Generated and uploaded reports to Google Drive ({$uploadedFiles} files, {$uploadedFolders} folders)", 'success');
        
        return [
            'success' => true,
            'teachers_processed' => countDirectories('reports'),
            'individual_reports' => countFiles('reports', '.html'),
            'summary_reports' => countFiles('reports', 'Summary'),
            'folders_created' => $uploadedFolders,
            'files_uploaded' => $uploadedFiles,
            'message' => 'Reports generated and uploaded to Google Drive successfully'
        ];
        
    } catch (Exception $e) {
        logActivity('Report Generation', 'Failed: ' . $e->getMessage(), 'error');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get system status
 */
function getSystemStatus() {
    global $pdo;
    
    $status = [
        'success' => true,
        'database_ok' => true,
        'sheets_ok' => false,
        'drive_ok' => false,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Test database connection
    try {
        $pdo->query("SELECT 1")->fetch();
    } catch (Exception $e) {
        $status['database_ok'] = false;
    }
    
    // Test Google APIs if credentials are available
    $credentialsPath = 'credentials/service-account-key.json';
    if (file_exists($credentialsPath)) {
        try {
            $client = new Google_Client();
            $client->setAuthConfig($credentialsPath);
            $client->setScopes([\Google_Service_Sheets::SPREADSHEETS_READONLY]);
            
            $sheetsService = new \Google_Service_Sheets($client);
            $status['sheets_ok'] = true;
            
            $client->setScopes([\Google_Service_Drive::DRIVE_FILE]);
            $driveService = new \Google_Service_Drive($client);
            $status['drive_ok'] = true;
            
        } catch (Exception $e) {
            // APIs not accessible
        }
    }
    
    return $status;
}

/**
 * Get system statistics
 */
function getSystemStats() {
    global $pdo;
    
    try {
        // Count evaluations
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM evaluations");
        $evaluations = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Calculate average rating
        $stmt = $pdo->query("
            SELECT AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                       q2_1 + q2_2 + q2_3 + q2_4 + 
                       q3_1 + q3_2 + q3_3 + q3_4 + 
                       q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20) as avg_rating
            FROM evaluations
        ");
        $avgRating = $stmt->fetch(PDO::FETCH_ASSOC)['avg_rating'] ?? 0;
        
        // Count students and teachers
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
        $students = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(DISTINCT name, department) as total FROM teachers");
        $teachers = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Calculate completion rate
        $stmt = $pdo->query("
            SELECT 
                (SELECT COUNT(*) FROM evaluations) as completed_evaluations,
                (SELECT COUNT(*) FROM students) * 
                (SELECT COUNT(DISTINCT name, department) FROM teachers) as expected_evaluations
        ");
        $completionData = $stmt->fetch(PDO::FETCH_ASSOC);
        $completionRate = ($completionData['expected_evaluations'] ?? 0) > 0 
            ? round(($completionData['completed_evaluations'] / $completionData['expected_evaluations']) * 100, 1)
            : 0;
        
        // Get database size (approximate)
        try {
            $stmt = $pdo->query("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS db_size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            $dbSize = ($stmt->fetch(PDO::FETCH_ASSOC)['db_size_mb'] ?? '0') . ' MB';
        } catch (Exception $e) {
            $dbSize = 'Unknown';
        }
        
        return [
            'success' => true,
            'evaluations' => $evaluations,
            'avg_rating' => round($avgRating, 2),
            'students' => $students,
            'teachers' => $teachers,
            'completion_rate' => $completionRate,
            'db_size' => $dbSize
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get recent activity log
 */
function getActivityLog() {
    global $pdo;
    
    try {
        // Create activity log table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(100) NOT NULL,
                description TEXT,
                status ENUM('success', 'error', 'warning') DEFAULT 'success',
                user_id INT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_timestamp (timestamp)
            )
        ");
        
        $stmt = $pdo->prepare("
            SELECT action, description, status, timestamp 
            FROM activity_log 
            ORDER BY timestamp DESC 
            LIMIT 20
        ");
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format timestamps
        foreach ($activities as &$activity) {
            $activity['timestamp'] = date('M j, Y g:i A', strtotime($activity['timestamp']));
        }
        
        return ['success' => true, 'activities' => $activities];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create database backup
 */
function createBackup() {
    global $pdo;
    
    try {
        // Get database name from connection
        $dbName = $pdo->query("SELECT DATABASE() as db")->fetch()['db'];
        
        // Create backup filename
        $backupFile = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupPath = 'reports/' . $backupFile;
        
        // Ensure reports directory exists
        if (!is_dir('reports')) {
            mkdir('reports', 0755, true);
        }
        
        // Create a simple SQL dump using PHP (for Railway compatibility)
        $backup_content = "-- Teacher Evaluation System Database Backup\n";
        $backup_content .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $backup_content .= "-- Table: $table\n";
            $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
            
            // Get CREATE TABLE statement
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
            $backup_content .= $create['Create Table'] . ";\n\n";
            
            // Get table data
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $backup_content .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $escaped = array_map(function($value) use ($pdo) {
                        return $value === null ? 'NULL' : $pdo->quote($value);
                    }, $row);
                    $values[] = '(' . implode(', ', $escaped) . ')';
                }
                
                $backup_content .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        // Save backup file
        file_put_contents($backupPath, $backup_content);
        
        logActivity('Backup', 'Database backup created: ' . $backupFile, 'success');
        
        return [
            'success' => true,
            'backup_file' => $backupFile,
            'file_size' => formatBytes(filesize($backupPath))
        ];
        
    } catch (Exception $e) {
        logActivity('Backup', 'Failed: ' . $e->getMessage(), 'error');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Helper functions
 */

// Log activity to database
function logActivity($action, $description, $status = 'success') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (action, description, status, user_id) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$action, $description, $status, $_SESSION['user_id'] ?? null]);
    } catch (Exception $e) {
        // Fail silently for logging errors
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Get or create folder in Google Drive
function getOrCreateDriveFolder($driveService, $folderName, $parentId = null) {
    // Search for existing folder
    $query = "name='" . addslashes($folderName) . "' and mimeType='application/vnd.google-apps.folder' and trashed=false";
    if ($parentId) {
        $query .= " and parents in '" . $parentId . "'";
    }
    
    $results = $driveService->files->listFiles([
        'q' => $query,
        'spaces' => 'drive'
    ]);
    
    if (count($results->getFiles()) > 0) {
        return $results->getFiles()[0]->getId();
    }
    
    // Create new folder
    $fileMetadata = new \Google_Service_Drive_DriveFile([
        'name' => $folderName,
        'mimeType' => 'application/vnd.google-apps.folder'
    ]);
    
    if ($parentId) {
        $fileMetadata->setParents([$parentId]);
    }
    
    $folder = $driveService->files->create($fileMetadata);
    return $folder->getId();
}

// Upload directory to Google Drive recursively
function uploadDirectoryToGoogleDrive($driveService, $localPath, $parentId, &$fileCount, &$folderCount) {
    if (!is_dir($localPath)) {
        return;
    }
    
    $items = scandir($localPath);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $itemPath = $localPath . '/' . $item;
        
        if (is_dir($itemPath)) {
            // Create folder in Google Drive
            $folderId = getOrCreateDriveFolder($driveService, $item, $parentId);
            $folderCount++;
            
            // Recursively upload subdirectory
            uploadDirectoryToGoogleDrive($driveService, $itemPath, $folderId, $fileCount, $folderCount);
        } else {
            // Upload file
            try {
                $fileMetadata = new \Google_Service_Drive_DriveFile([
                    'name' => $item,
                    'parents' => [$parentId]
                ]);
                
                $content = file_get_contents($itemPath);
                $mimeType = getMimeType($itemPath);
                
                $driveService->files->create($fileMetadata, [
                    'data' => $content,
                    'mimeType' => $mimeType,
                    'uploadType' => 'multipart'
                ]);
                
                $fileCount++;
            } catch (Exception $e) {
                error_log("Failed to upload file $itemPath: " . $e->getMessage());
            }
        }
    }
}

// Get MIME type for file
function getMimeType($filePath) {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    $mimeTypes = [
        'html' => 'text/html',
        'csv' => 'text/csv',
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'txt' => 'text/plain'
    ];
    
    return $mimeTypes[$extension] ?? 'application/octet-stream';
}

// Count directories in a path
function countDirectories($path) {
    if (!is_dir($path)) {
        return 0;
    }
    
    $count = 0;
    $items = scandir($path);
    foreach ($items as $item) {
        if ($item !== '.' && $item !== '..' && is_dir($path . '/' . $item)) {
            $count++;
        }
    }
    return $count;
}

// Count files with specific pattern
function countFiles($path, $pattern = '') {
    if (!is_dir($path)) {
        return 0;
    }
    
    $count = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $filename = $file->getFilename();
            if (empty($pattern) || strpos($filename, $pattern) !== false) {
                $count++;
            }
        }
    }
    
    return $count;
}

// Format bytes to human readable format
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
