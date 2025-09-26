<?php
// includes/db_connection.php - Hybrid approach (Database + Google Sheets)

// Debug flag (set to true only when needed)
$DEBUG_MODE = false;

// Safe debug log function
function debug_log($message) {
    global $DEBUG_MODE;
    if ($DEBUG_MODE) {
        error_log("[DEBUG] " . $message);
    }
}

// Database connection (for teachers, sections, evaluations)
function getDatabaseConnection() {
    try {
        $database_url = getenv('DATABASE_URL');
        
        if ($database_url) {
            $db_parts = parse_url($database_url);

            if (!$db_parts) {
                throw new Exception("Invalid DATABASE_URL format");
            }

            $host = $db_parts['host'] ?? null;
            $port = $db_parts['port'] ?? null;
            $dbname = isset($db_parts['path']) ? ltrim($db_parts['path'], '/') : null;
            $username = $db_parts['user'] ?? null;
            $password = $db_parts['pass'] ?? null;

            if (!$host || !$dbname || !$username) {
                throw new Exception("Missing required database connection parameters");
            }

            // Detect scheme
            if ($db_parts['scheme'] === 'postgres' || $db_parts['scheme'] === 'postgresql') {
                $dsn = "pgsql:host=$host;port=" . ($port ?? 5432) . ";dbname=$dbname";
            } elseif ($db_parts['scheme'] === 'mysql') {
                $dsn = "mysql:host=$host;port=" . ($port ?? 3306) . ";dbname=$dbname;charset=utf8mb4";
            } else {
                throw new Exception("Unsupported database scheme: " . ($db_parts['scheme'] ?? 'unknown'));
            }
        } else {
            // Local dev fallback (MySQL)
            $dsn = "mysql:host=localhost;dbname=evaluation_system;charset=utf8mb4";
            $username = "root";
            $password = "";
        }

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 30
        ]);
        
        debug_log("Database connection successful.");
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return false;
    }
}

$pdo = getDatabaseConnection();

// Database helper functions
function query($sql, $params = []) {
    global $pdo;
    if (!$pdo) {
        error_log("Database query attempted but no connection available: $sql");
        return false;
    }
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage() . " SQL: $sql");
        return false;
    }
}

function fetch_assoc($result) {
    return $result ? $result->fetch(PDO::FETCH_ASSOC) : false;
}

function fetch_all($result) {
    return $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];
}

function isDatabaseAvailable() {
    global $pdo;
    return $pdo !== false;
}

// Google Sheets helper
function loadGoogleSheetsHelper() {
    static $sheetsHelper = null;
    static $loadAttempted = false;
    
    if ($loadAttempted) return $sheetsHelper;
    $loadAttempted = true;
    
    try {
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            error_log("Google Sheets: vendor/autoload.php not found");
            return null;
        }
        
        if (!getenv('GOOGLE_CREDENTIALS_JSON') || !getenv('GOOGLE_SPREADSHEET_ID')) {
            error_log("Google Sheets: Missing required environment variables");
            return null;
        }
        
        require_once __DIR__ . '/google_sheets_connection.php';
        $sheetsHelper = getGoogleSheetsHelper();
        
        if (!$sheetsHelper) {
            error_log("Google Sheets: Failed to initialize helper");
            return null;
        }
        
        return $sheetsHelper;
        
    } catch (Exception $e) {
        error_log("Google Sheets connection error: " . $e->getMessage());
        return null;
    }
}

class HybridDataManager {
    private $sheetsHelper;
    
    public function __construct() {
        $this->sheetsHelper = loadGoogleSheetsHelper();
    }
    
    public function authenticateStudent($username, $password) {
        if (!$this->sheetsHelper) {
            // Fallback test account
            if ($username === 'TESTUSER' && $password === 'pass123') {
                return [
                    'student_id' => 'TEST001',
                    'full_name' => 'Test User',
                    'section' => 'Test Section',
                    'program' => 'Test Program',
                    'username' => 'TESTUSER',
                    'user_type' => 'student'
                ];
            }
            return false;
        }
        
        if ($password !== 'pass123') return false;
        
        $students = $this->sheetsHelper->readSheet('Students!A:D');
        if (!$students) return false;
        
        foreach ($students as $index => $row) {
            if ($index === 0) continue;
            if (isset($row[1]) && isset($row[2])) {
                $lastName = strtoupper(trim($row[1]));
                $firstName = strtoupper(trim($row[2]));
                $generatedUsername = $lastName . $firstName;
                
                if ($generatedUsername === strtoupper($username)) {
                    return [
                        'student_id' => $row[0] ?? '',
                        'full_name' => trim($row[2] . ' ' . $row[1]),
                        'section' => $row[3] ?? '',
                        'program' => $row[4] ?? '',
                        'username' => $generatedUsername,
                        'user_type' => 'student'
                    ];
                }
            }
        }
        
        return false;
    }
    
    public function authenticateAdmin($username, $password) {
        if (isDatabaseAvailable()) {
            $stmt = query("SELECT * FROM users WHERE username = ? AND user_type = 'admin'", [$username]);
            if ($stmt) {
                $admin = fetch_assoc($stmt);
                if ($admin && password_verify($password, $admin['password'])) {
                    return [
                        'user_id' => $admin['id'],
                        'username' => $admin['username'],
                        'full_name' => $admin['full_name'],
                        'user_type' => 'admin'
                    ];
                }
            }
        }
        
        // Fallback admin
        if ($username === 'admin' && $password === 'admin123') {
            return [
                'user_id' => 'admin',
                'username' => 'admin',
                'full_name' => 'System Administrator',
                'user_type' => 'admin'
            ];
        }
        
        return false;
    }
    
    public function getTeachers() {
        if (isDatabaseAvailable()) {
            $stmt = query("SELECT * FROM teachers ORDER BY name");
            $teachers = $stmt ? fetch_all($stmt) : [];
            if (!empty($teachers)) return $teachers;
        }
        
        if ($this->sheetsHelper) {
            return $this->sheetsHelper->getTeachers();
        }
        
        return [
            ['id' => 1, 'name' => 'Sample Teacher 1', 'department' => 'Mathematics', 'subject' => 'Algebra'],
            ['id' => 2, 'name' => 'Sample Teacher 2', 'department' => 'Science', 'subject' => 'Physics'],
        ];
    }
    
    public function saveEvaluation($evaluationData) {
        if (!isDatabaseAvailable()) {
            error_log("Cannot save evaluation - database not available");
            return false;
        }
        
        $sql = "INSERT INTO evaluations (
            student_id, student_name, teacher_id, section, program, subject,
            q1_1, q1_2, q1_3, q1_4, q1_5, q1_6,
            q2_1, q2_2, q2_3, q2_4,
            q3_1, q3_2, q3_3, q3_4,
            q4_1, q4_2, q4_3, q4_4, q4_5, q4_6,
            comments, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, NOW()
        )";
        
        $params = [
            $evaluationData['student_id'],
            $evaluationData['student_name'],
            $evaluationData['teacher_id'],
            $evaluationData['section'],
            $evaluationData['program'],
            $evaluationData['subject'] ?? '',
            $evaluationData['q1_1'], $evaluationData['q1_2'], $evaluationData['q1_3'],
            $evaluationData['q1_4'], $evaluationData['q1_5'], $evaluationData['q1_6'],
            $evaluationData['q2_1'], $evaluationData['q2_2'], $evaluationData['q2_3'], $evaluationData['q2_4'],
            $evaluationData['q3_1'], $evaluationData['q3_2'], $evaluationData['q3_3'], $evaluationData['q3_4'],
            $evaluationData['q4_1'], $evaluationData['q4_2'], $evaluationData['q4_3'],
            $evaluationData['q4_4'], $evaluationData['q4_5'], $evaluationData['q4_6'],
            $evaluationData['comments'] ?? ''
        ];
        
        $stmt = query($sql, $params);
        return $stmt !== false;
    }
    
    public function hasStudentEvaluatedTeacher($studentId, $teacherId) {
        if (!isDatabaseAvailable()) return false;
        $stmt = query("SELECT COUNT(*) as count FROM evaluations WHERE student_id = ? AND teacher_id = ?", 
                     [$studentId, $teacherId]);
        $result = $stmt ? fetch_assoc($stmt) : null;
        return $result && $result['count'] > 0;
    }
}

$hybridManager = new HybridDataManager();

// Backward compatibility
function authenticateUser($username, $password) {
    global $hybridManager;
    $student = $hybridManager->authenticateStudent($username, $password);
    if ($student) return $student;
    $admin = $hybridManager->authenticateAdmin($username, $password);
    if ($admin) return $admin;
    return false;
}
