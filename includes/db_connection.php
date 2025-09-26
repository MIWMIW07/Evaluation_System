<?php
// includes/db_connection.php - Hybrid approach (Database + Google Sheets)
// require_once __DIR__ . '/google_sheets_connection.php';
// Database connection (for teachers, sections, evaluations)
function getDatabaseConnection() {
    try {
        // Railway: DATABASE_URL (Postgres or MySQL depending on setup)
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

            // Detect scheme: postgres or mysql
            if ($db_parts['scheme'] === 'postgres' || $db_parts['scheme'] === 'postgresql') {
                $dsn = "pgsql:host=$host;port=" . ($port ?? 5432) . ";dbname=$dbname";
            } elseif ($db_parts['scheme'] === 'mysql') {
                $dsn = "mysql:host=$host;port=" . ($port ?? 3306) . ";dbname=$dbname;charset=utf8mb4";
            } else {
                throw new Exception("Unsupported database scheme: " . ($db_parts['scheme'] ?? 'unknown'));
            }
        } else {
            // Local development fallback (MySQL)
            $dsn = "mysql:host=localhost;dbname=evaluation_system;charset=utf8mb4";
            $username = "root";
            $password = "";
        }

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 30
        ]);
        
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        // Don't die here - return false so we can handle gracefully
        return false;
    }
}

// Try to establish database connection
$pdo = getDatabaseConnection();

// Database helper functions (with error handling)
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

// Check if database is available
function isDatabaseAvailable() {
    global $pdo;
    return $pdo !== false;
}

// Google Sheets helper for student data
require_once __DIR__ . '/google_sheets_connection.php';

class HybridDataManager {
    private $sheetsHelper;
    
    public function __construct() {
        $this->sheetsHelper = getGoogleSheetsHelper();
    }
    
    // Student authentication using Google Sheets with simple credentials
    public function authenticateStudent($username, $password) {
        if (!$this->sheetsHelper) {
            throw new Exception("Google Sheets not available for student authentication");
        }
        
        // Check if password matches the standard password
        if ($password !== 'pass123') {
            return false;
        }
        
        $students = $this->sheetsHelper->readSheet('Students!A:D'); // Adjust range as needed
        
        if (!$students) {
            throw new Exception("Could not read student data from Google Sheets");
        }
        
        foreach ($students as $index => $row) {
            if ($index === 0) continue; // Skip header row
            
            // Assuming columns: Student_ID, Last_Name, First_Name, Section, Program
            if (isset($row[1]) && isset($row[2])) {
                $lastName = strtoupper(trim($row[1]));
                $firstName = strtoupper(trim($row[2]));
                $generatedUsername = $lastName . $firstName;
                
                // Check if the generated username matches the input
                if ($generatedUsername === strtoupper($username)) {
                    return [
                        'student_id' => $row[0] ?? '',
                        'full_name' => trim($row[2] . ' ' . $row[1]), // First Last format
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
    
    // Admin authentication using database or fallback
    public function authenticateAdmin($username, $password) {
        // Try database first
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
        
        // Fallback admin credentials
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
    
    // Get teachers from database
    public function getTeachers() {
        if (!isDatabaseAvailable()) {
            return [];
        }
        
        $stmt = query("SELECT * FROM teachers ORDER BY name");
        return $stmt ? fetch_all($stmt) : [];
    }
    
    // Save evaluation to database
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
            
            // Section 1: Teaching Ability (6 questions)
            $evaluationData['q1_1'], $evaluationData['q1_2'], $evaluationData['q1_3'],
            $evaluationData['q1_4'], $evaluationData['q1_5'], $evaluationData['q1_6'],
            
            // Section 2: Management Skills (4 questions)  
            $evaluationData['q2_1'], $evaluationData['q2_2'], 
            $evaluationData['q2_3'], $evaluationData['q2_4'],
            
            // Section 3: Guidance Skills (4 questions)
            $evaluationData['q3_1'], $evaluationData['q3_2'], 
            $evaluationData['q3_3'], $evaluationData['q3_4'],
            
            // Section 4: Personal and Social Characteristics (6 questions)
            $evaluationData['q4_1'], $evaluationData['q4_2'], $evaluationData['q4_3'],
            $evaluationData['q4_4'], $evaluationData['q4_5'], $evaluationData['q4_6'],
            
            // Comments
            $evaluationData['comments'] ?? ''
        ];
        
        $stmt = query($sql, $params);
        return $stmt !== false;
    }
    
    // Check if student already evaluated a teacher
    public function hasStudentEvaluatedTeacher($studentId, $teacherId) {
        if (!isDatabaseAvailable()) {
            return false; // Allow evaluation if we can't check
        }
        
        $stmt = query("SELECT COUNT(*) as count FROM evaluations WHERE student_id = ? AND teacher_id = ?", 
                     [$studentId, $teacherId]);
        
        if ($stmt) {
            $result = fetch_assoc($stmt);
            return $result && $result['count'] > 0;
        }
        
        return false;
    }
}

// Global instance
$hybridManager = new HybridDataManager();

// Backward compatibility functions
function authenticateUser($username, $password) {
    global $hybridManager;
    
    // Try student authentication first
    $student = $hybridManager->authenticateStudent($username, $password);
    if ($student) {
        return $student;
    }
    
    // Try admin authentication
    $admin = $hybridManager->authenticateAdmin($username, $password);
    if ($admin) {
        return $admin;
    }
    
    return false;
}
?>

