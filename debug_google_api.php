<?php
// debug_google_api.php - Debug script to identify Google API issues
error_reporting(E_ALL);
ini_set('display_errors', 0); // hide direct output, log instead
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// Always start output buffering to prevent stray output
ob_start();

session_start();

// Force JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Debug log start
file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " - Debug script started\n", FILE_APPEND);

// --- Session Access Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'test';
file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " - Action: {$action}\n", FILE_APPEND);

// Clear buffer before JSON output
ob_end_clean();

try {
    switch ($action) {
        case 'test_connection':
            echo json_encode(debugTestConnection(), JSON_PRETTY_PRINT);
            break;
            
        case 'check_environment':
            echo json_encode(debugCheckEnvironment(), JSON_PRETTY_PRINT);
            break;
            
        case 'check_files':
            echo json_encode(debugCheckFiles(), JSON_PRETTY_PRINT);
            break;
            
        case 'check_database':
            echo json_encode(debugCheckDatabase(), JSON_PRETTY_PRINT);
            break;
            
        case 'generate_reports':
            echo json_encode(debugGenerateReports(), JSON_PRETTY_PRINT);
            break;
            
        default:
            echo json_encode(['success' => true, 'message' => 'Debug script working', 'action' => $action]);
    }
} catch (Exception $e) {
    file_put_contents(
        __DIR__ . '/debug.log',
        date('Y-m-d H:i:s') . " - Exception: " . $e->getMessage() . "\n",
        FILE_APPEND
    );

    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
        'trace'   => array_slice($e->getTrace(), 0, 5)
    ]);
}

/**
 * ENVIRONMENT CHECK
 */
function debugCheckEnvironment() {
    $results = [
        'success' => true,
        'php_version' => PHP_VERSION,
        'extensions' => [],
        'environment_vars' => [],
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time')
    ];
    
    $required_extensions = ['json', 'curl', 'openssl', 'pdo', 'pdo_mysql'];
    foreach ($required_extensions as $ext) {
        $results['extensions'][$ext] = extension_loaded($ext);
    }
    
    $env_vars = ['GOOGLE_CREDENTIALS_JSON', 'GOOGLE_SPREADSHEET_ID', 'MYSQL_HOST', 'MYSQL_DATABASE'];
    foreach ($env_vars as $var) {
        $val = getenv($var);
        $results['environment_vars'][$var] = $val ? 'SET (length: ' . strlen($val) . ')' : 'NOT SET';
    }
    
    return $results;
}

/**
 * FILE CHECK
 */
function debugCheckFiles() {
    $results = ['success' => true, 'files' => [], 'directories' => []];
    
    $required_files = [
        'vendor/autoload.php',
        'includes/db_connection.php',
        'google_drive_reports.php',
        'google_sheets_integration.php'
    ];
    
    foreach ($required_files as $file) {
        $path = __DIR__ . '/' . $file;
        $results['files'][$file] = [
            'exists'   => file_exists($path),
            'readable' => is_readable($path),
            'size'     => file_exists($path) ? filesize($path) : 0
        ];
    }
    
    $required_dirs = ['vendor', 'includes'];
    foreach ($required_dirs as $dir) {
        $path = __DIR__ . '/' . $dir;
        $results['directories'][$dir] = [
            'exists'   => is_dir($path),
            'readable' => is_readable($path),
            'writable' => is_writable($path)
        ];
    }
    
    return $results;
}

/**
 * DATABASE CHECK
 */
function debugCheckDatabase() {
    try {
        $db_file = __DIR__ . '/includes/db_connection.php';
        if (!file_exists($db_file)) {
            return ['success' => false, 'error' => 'Database connection file not found'];
        }
        
        require_once $db_file;
        
        if (!isset($pdo)) {
            return ['success' => false, 'error' => 'PDO object not available'];
        }
        
        $student_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
        $teacher_count = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
        $evaluation_count = $pdo->query("SELECT COUNT(*) FROM evaluations")->fetchColumn();
        
        return [
            'success' => true,
            'pdo_available' => true,
            'student_count' => $student_count,
            'teacher_count' => $teacher_count,
            'evaluation_count' => $evaluation_count
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * TEST CONNECTION TO GOOGLE APIS
 */
function debugTestConnection() {
    try {
        if (!class_exists('Google\Client')) {
            if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                require_once __DIR__ . '/vendor/autoload.php';
            }
        }
        
        if (!class_exists('Google\Client')) {
            return ['success' => false, 'error' => 'Google API Client missing. Run: composer require google/apiclient'];
        }
        
        $credentialsJson = getenv('GOOGLE_CREDENTIALS_JSON');
        if (!$credentialsJson) return ['success' => false, 'error' => 'GOOGLE_CREDENTIALS_JSON not set'];
        
        $credentials = json_decode($credentialsJson, true);
        if (!$credentials) return ['success' => false, 'error' => 'Invalid GOOGLE_CREDENTIALS_JSON'];
        
        $spreadsheetId = getenv('GOOGLE_SPREADSHEET_ID');
        if (!$spreadsheetId) return ['success' => false, 'error' => 'GOOGLE_SPREADSHEET_ID not set'];
        
        $tempFile = sys_get_temp_dir() . '/google_debug_' . uniqid() . '.json';
        file_put_contents($tempFile, $credentialsJson);
        
        try {
            $client = new Google\Client();
            $client->setAuthConfig($tempFile);
            $client->addScope([
                Google\Service\Drive::DRIVE_FILE,
                Google\Service\Sheets::SPREADSHEETS
            ]);
            
            $sheetsService = new Google\Service\Sheets($client);
            $spreadsheet = $sheetsService->spreadsheets->get($spreadsheetId);
            
            $driveService = new Google\Service\Drive($client);
            $driveService->files->listFiles(['pageSize' => 1]);
            
            return [
                'success' => true,
                'spreadsheet_title' => $spreadsheet->getProperties()->getTitle(),
                'sheets_count' => count($spreadsheet->getSheets()),
                'drive_accessible' => true,
                'client_email' => $credentials['client_email'] ?? 'Unknown'
            ];
        } finally {
            if (file_exists($tempFile)) unlink($tempFile);
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * REPORT GENERATION CHECK
 */
function debugGenerateReports() {
    try {
        $db_file = __DIR__ . '/includes/db_connection.php';
        $report_file = __DIR__ . '/google_drive_reports.php';
        
        if (!file_exists($db_file)) return ['success' => false, 'error' => 'Database connection missing'];
        if (!file_exists($report_file)) return ['success' => false, 'error' => 'Reports script missing'];
        
        require_once $db_file;
        require_once $report_file;
        
        if (!isset($pdo)) return ['success' => false, 'error' => 'PDO not available'];
        if (!class_exists('GoogleDriveReportsGenerator')) return ['success' => false, 'error' => 'Report class missing'];
        
        $evaluation_count = $pdo->query("SELECT COUNT(*) FROM evaluations")->fetchColumn();
        if ($evaluation_count == 0) return ['success' => false, 'error' => 'No evaluations found'];
        
        new GoogleDriveReportsGenerator($pdo); // Just test constructor
        
        return ['success' => true, 'evaluation_count' => $evaluation_count, 'message' => 'Ready to generate reports'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
