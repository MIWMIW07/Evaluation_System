<?php
// debug_google_api.php - Debug script to identify Google API issues
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// Start output buffering to catch any unexpected output
ob_start();

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Debug: Check if we can get to this point
file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " - Script started\n", FILE_APPEND);

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'test';
file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " - Action: $action\n", FILE_APPEND);

// Clean any output that might have been generated
ob_end_clean();

try {
    switch ($action) {
        case 'test_connection':
            echo json_encode(debugTestConnection());
            break;
            
        case 'check_environment':
            echo json_encode(debugCheckEnvironment());
            break;
            
        case 'check_files':
            echo json_encode(debugCheckFiles());
            break;
            
        case 'check_database':
            echo json_encode(debugCheckDatabase());
            break;
            
        case 'generate_reports':
            echo json_encode(debugGenerateReports());
            break;
            
        default:
            echo json_encode(['success' => true, 'message' => 'Debug script working', 'action' => $action]);
    }
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " - Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => array_slice($e->getTrace(), 0, 5) // Limit trace to avoid too much data
    ]);
}

function debugCheckEnvironment() {
    $results = [
        'success' => true,
        'php_version' => PHP_VERSION,
        'extensions' => [],
        'environment_vars' => [],
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time')
    ];
    
    // Check required PHP extensions
    $required_extensions = ['json', 'curl', 'openssl', 'pdo', 'pdo_mysql'];
    foreach ($required_extensions as $ext) {
        $results['extensions'][$ext] = extension_loaded($ext);
    }
    
    // Check environment variables (without exposing sensitive data)
    $env_vars = ['GOOGLE_CREDENTIALS_JSON', 'GOOGLE_SPREADSHEET_ID', 'MYSQL_HOST', 'MYSQL_DATABASE'];
    foreach ($env_vars as $var) {
        $value = getenv($var);
        $results['environment_vars'][$var] = $value ? 'SET (length: ' . strlen($value) . ')' : 'NOT SET';
    }
    
    return $results;
}

function debugCheckFiles() {
    $results = [
        'success' => true,
        'files' => [],
        'directories' => []
    ];
    
    $required_files = [
        'vendor/autoload.php',
        'includes/db_connection.php',
        'google_drive_reports.php',
        'google_sheets_integration.php'
    ];
    
    foreach ($required_files as $file) {
        $full_path = __DIR__ . '/' . $file;
        $results['files'][$file] = [
            'exists' => file_exists($full_path),
            'readable' => is_readable($full_path),
            'size' => file_exists($full_path) ? filesize($full_path) : 0
        ];
    }
    
    $required_dirs = ['vendor', 'includes'];
    foreach ($required_dirs as $dir) {
        $full_path = __DIR__ . '/' . $dir;
        $results['directories'][$dir] = [
            'exists' => is_dir($full_path),
            'readable' => is_readable($full_path),
            'writable' => is_writable($full_path)
        ];
    }
    
    return $results;
}

function debugCheckDatabase() {
    try {
        // Try to include the database connection
        if (!file_exists(__DIR__ . '/includes/db_connection.php')) {
            return ['success' => false, 'error' => 'Database connection file not found'];
        }
        
        require_once __DIR__ . '/includes/db_connection.php';
        
        if (!isset($pdo)) {
            return ['success' => false, 'error' => 'PDO object not available'];
        }
        
        // Test basic query
        $stmt = $pdo->query("SELECT COUNT(*) FROM students");
        $student_count = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM teachers");
        $teacher_count = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM evaluations");
        $evaluation_count = $stmt->fetchColumn();
        
        return [
            'success' => true,
            'student_count' => $student_count,
            'teacher_count' => $teacher_count,
            'evaluation_count' => $evaluation_count,
            'pdo_available' => true
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'pdo_available' => isset($pdo)
        ];
    }
}

function debugTestConnection() {
    try {
        // Check if Google Client library is available
        if (!class_exists('Google\Client')) {
            if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
                return ['success' => false, 'error' => 'Composer autoload not found. Run: composer install'];
            }
            
            require_once __DIR__ . '/vendor/autoload.php';
            
            if (!class_exists('Google\Client')) {
                return ['success' => false, 'error' => 'Google Client library not installed. Run: composer require google/apiclient'];
            }
        }
        
        // Check credentials
        $credentialsJson = getenv('GOOGLE_CREDENTIALS_JSON');
        if (!$credentialsJson) {
            return ['success' => false, 'error' => 'GOOGLE_CREDENTIALS_JSON environment variable not set'];
        }
        
        $credentials = json_decode($credentialsJson, true);
        if (!$credentials) {
            return ['success' => false, 'error' => 'Invalid JSON in GOOGLE_CREDENTIALS_JSON'];
        }
        
        // Check spreadsheet ID
        $spreadsheetId = getenv('GOOGLE_SPREADSHEET_ID');
        if (!$spreadsheetId) {
            return ['success' => false, 'error' => 'GOOGLE_SPREADSHEET_ID environment variable not set'];
        }
        
        // Create temporary credentials file
        $tempFile = sys_get_temp_dir() . '/google_credentials_debug_' . uniqid() . '.json';
        file_put_contents($tempFile, $credentialsJson);
        
        try {
            // Initialize Google Client
            $client = new Google\Client();
            $client->setAuthConfig($tempFile);
            $client->addScope([
                Google\Service\Drive::DRIVE_FILE,
                Google\Service\Sheets::SPREADSHEETS
            ]);
            $client->setAccessType('offline');
            
            // Test Sheets service
            $sheetsService = new Google\Service\Sheets($client);
            $spreadsheet = $sheetsService->spreadsheets->get($spreadsheetId);
            
            // Test Drive service
            $driveService = new Google\Service\Drive($client);
            $files = $driveService->files->listFiles(['pageSize' => 1]);
            
            unlink($tempFile);
            
            return [
                'success' => true,
                'spreadsheet_title' => $spreadsheet->getProperties()->getTitle(),
                'sheets_count' => count($spreadsheet->getSheets()),
                'drive_accessible' => true,
                'client_email' => $credentials['client_email'] ?? 'Unknown'
            ];
            
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'class_exists' => [
                'Google\Client' => class_exists('Google\Client'),
                'Google\Service\Drive' => class_exists('Google\Service\Drive'),
                'Google\Service\Sheets' => class_exists('Google\Service\Sheets')
            ]
        ];
    }
}

function debugGenerateReports() {
    try {
        // Check if required files exist
        if (!file_exists(__DIR__ . '/includes/db_connection.php')) {
            return ['success' => false, 'error' => 'Database connection file missing'];
        }
        
        if (!file_exists(__DIR__ . '/google_drive_reports.php')) {
            return ['success' => false, 'error' => 'google_drive_reports.php file missing'];
        }
        
        // Include database connection
        require_once __DIR__ . '/includes/db_connection.php';
        
        if (!isset($pdo)) {
            return ['success' => false, 'error' => 'Database connection failed'];
        }
        
        // Check if there are evaluations
        $stmt = $pdo->query("SELECT COUNT(*) FROM evaluations");
        $evaluationCount = $stmt->fetchColumn();
        
        if ($evaluationCount == 0) {
            return ['success' => false, 'error' => 'No evaluations found in database'];
        }
        
        // Try to include the reports class
        require_once __DIR__ . '/google_drive_reports.php';
        
        if (!class_exists('GoogleDriveReportsGenerator')) {
            return ['success' => false, 'error' => 'GoogleDriveReportsGenerator class not found'];
        }
        
        // Try to create instance
        $generator = new GoogleDriveReportsGenerator($pdo);
        
        return [
            'success' => true,
            'evaluation_count' => $evaluationCount,
            'class_loaded' => true,
            'message' => 'Ready to generate reports'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

exit();
?>
