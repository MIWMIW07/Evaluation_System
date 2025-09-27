<?php
// includes/db_connection.php
// Clean hybrid connection for minimal tables approach
require_once __DIR__ . '/../vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

class HybridDataManager {
    private $pdo;
    private $sheetsService;
    private $sheetId;

    public function __construct() {
        // PostgreSQL connection
        $dbUrl = getenv("DATABASE_URL") ?: ($_ENV["DATABASE_URL"] ?? $_SERVER["DATABASE_URL"] ?? null);
        if (!$dbUrl) {
            throw new Exception("DATABASE_URL environment variable not set");
        }

        $dbopts = parse_url($dbUrl);
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s",
            $dbopts["host"],
            $dbopts["port"],
            ltrim($dbopts["path"], "/")
        );

        $this->pdo = new PDO($dsn, $dbopts["user"], $dbopts["pass"], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Google Sheets setup
        $this->sheetId = getenv("GOOGLE_SHEETS_ID") ?: ($_ENV["GOOGLE_SHEETS_ID"] ?? $_SERVER["GOOGLE_SHEETS_ID"] ?? null);
        $googleCreds = getenv("GOOGLE_CREDENTIALS_JSON") ?: ($_ENV["GOOGLE_CREDENTIALS_JSON"] ?? $_SERVER["GOOGLE_CREDENTIALS_JSON"] ?? null);

        if ($this->sheetId && $googleCreds) {
            try {
                $client = new Client();
                $client->setApplicationName("Evaluation System");
                $client->setScopes([Sheets::SPREADSHEETS_READONLY]);
                $client->setAuthConfig(json_decode($googleCreds, true));
                $this->sheetsService = new Sheets($client);
            } catch (Exception $e) {
                error_log("Google Sheets setup failed: " . $e->getMessage());
                $this->sheetsService = null;
            }
        } else {
            $this->sheetsService = null;
        }
    }

    public function authenticateUser($username, $password) {
        // Check for hardcoded admin
        if ($username === 'admin' && $password === 'admin123') {
            return [
                'id' => 'admin',
                'type' => 'admin'
            ];
        }

        // Try Google Sheets for students
        if ($this->sheetsService) {
            $student = $this->findStudent($username, $password);
            if ($student) {
                return ['id' => $student['student_id'], 'type' => 'student'];
            }
        }

        return false;
    }

    private function findStudent($username, $password) {
        try {
            $range = "Students!A:G"; // A=Student_ID, B=Last_Name, C=First_Name, D=Section, E=Program, F=Username, G=Password
            $response = $this->sheetsService->spreadsheets_values->get($this->sheetId, $range);
            $rows = $response->getValues();

            foreach ($rows as $i => $row) {
                if ($i === 0) continue; // Skip header row
                
                if (count($row) >= 7) {
                    $stored_username = isset($row[5]) ? trim($row[5]) : '';
                    $stored_password = isset($row[6]) ? trim($row[6]) : '';
                    
                    if ($stored_username === $username && $stored_password === $password) {
                        return [
                            'student_id' => $row[0] ?? '',
                            'last_name' => $row[1] ?? '',
                            'first_name' => $row[2] ?? '',
                            'section' => $row[3] ?? '',
                            'program' => $row[4] ?? '',
                            'username' => $stored_username,
                            'full_name' => trim(($row[2] ?? '') . ' ' . ($row[1] ?? ''))
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Google Sheets error: " . $e->getMessage());
        }
        
        return null;
    }

    public function getTeachers() {
        if (!$this->sheetsService) return [];
        
        try {
            $range = "Teachers!A:C";
            $response = $this->sheetsService->spreadsheets_values->get($this->sheetId, $range);
            $rows = $response->getValues();
            return array_slice($rows, 1); // remove header row
        } catch (Exception $e) {
            error_log("Failed to get teachers: " . $e->getMessage());
            return [];
        }
    }

    public function getPDO() {
        return $this->pdo;
    }
}

// Helper functions
function getDataManager() {
    static $manager = null;
    if (!$manager) {
        $manager = new HybridDataManager();
    }
    return $manager;
}

function getPDO() {
    return getDataManager()->getPDO();
}

// Check if database is available
function isDatabaseAvailable() {
    try {
        getPDO();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Simple logging function (optional - for backwards compatibility)
function logActivity($action, $details, $status = 'info', $userId = null) {
    // Just log to error log since we don't have activity_logs table anymore
    $logMessage = sprintf(
        "[%s] User: %s, Action: %s, Status: %s, Details: %s",
        date('Y-m-d H:i:s'),
        $userId ?? 'unknown',
        $action,
        $status,
        $details
    );
    error_log($logMessage);
}

// Get available teacher assignments (for admin or reporting)
function getTeacherAssignments() {
    try {
        $pdo = getPDO();
        $stmt = $pdo->query("
            SELECT * FROM teacher_assignments 
            WHERE is_active = true 
            ORDER BY program, section, teacher_name
        ");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Get student evaluations (for reporting)
function getStudentEvaluations($studentUsername = null) {
    try {
        $pdo = getPDO();
        
        if ($studentUsername) {
            $stmt = $pdo->prepare("
                SELECT * FROM evaluations 
                WHERE student_username = ? 
                ORDER BY submitted_at DESC
            ");
            $stmt->execute([$studentUsername]);
        } else {
            $stmt = $pdo->query("
                SELECT * FROM evaluations 
                ORDER BY submitted_at DESC
            ");
        }
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Check if student has evaluated a specific teacher
function hasEvaluatedTeacher($studentUsername, $teacherName, $subject = null) {
    try {
        $pdo = getPDO();
        
        if ($subject) {
            $stmt = $pdo->prepare("
                SELECT id FROM evaluations 
                WHERE student_username = ? AND teacher_name = ? AND subject = ?
            ");
            $stmt->execute([$studentUsername, $teacherName, $subject]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id FROM evaluations 
                WHERE student_username = ? AND teacher_name = ?
            ");
            $stmt->execute([$studentUsername, $teacherName]);
        }
        
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

// Save evaluation to database
function saveEvaluation($evaluationData) {
    try {
        $pdo = getPDO();
        
        $stmt = $pdo->prepare("
            INSERT INTO evaluations (
                student_username, student_name, teacher_name, section, program, subject,
                q1_1, q1_2, q1_3, q1_4, q1_5, q1_6,
                q2_1, q2_2, q2_3, q2_4,
                q3_1, q3_2, q3_3, q3_4,
                q4_1, q4_2, q4_3, q4_4, q4_5, q4_6,
                comments, created_at, submitted_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, NOW(), NOW()
            )
            ON CONFLICT (student_username, teacher_name, subject, section) 
            DO UPDATE SET
                q1_1 = EXCLUDED.q1_1, q1_2 = EXCLUDED.q1_2, q1_3 = EXCLUDED.q1_3,
                q1_4 = EXCLUDED.q1_4, q1_5 = EXCLUDED.q1_5, q1_6 = EXCLUDED.q1_6,
                q2_1 = EXCLUDED.q2_1, q2_2 = EXCLUDED.q2_2, q2_3 = EXCLUDED.q2_3, q2_4 = EXCLUDED.q2_4,
                q3_1 = EXCLUDED.q3_1, q3_2 = EXCLUDED.q3_2, q3_3 = EXCLUDED.q3_3, q3_4 = EXCLUDED.q3_4,
                q4_1 = EXCLUDED.q4_1, q4_2 = EXCLUDED.q4_2, q4_3 = EXCLUDED.q4_3,
                q4_4 = EXCLUDED.q4_4, q4_5 = EXCLUDED.q4_5, q4_6 = EXCLUDED.q4_6,
                comments = EXCLUDED.comments, submitted_at = NOW()
        ");
        
        $result = $stmt->execute([
            $evaluationData['student_username'],
            $evaluationData['student_name'],
            $evaluationData['teacher_name'],
            $evaluationData['section'],
            $evaluationData['program'],
            $evaluationData['subject'],
            
            // Section 1 - Teaching Ability
            $evaluationData['q1_1'], $evaluationData['q1_2'], $evaluationData['q1_3'],
            $evaluationData['q1_4'], $evaluationData['q1_5'], $evaluationData['q1_6'],
            
            // Section 2 - Management Skills
            $evaluationData['q2_1'], $evaluationData['q2_2'], $evaluationData['q2_3'], $evaluationData['q2_4'],
            
            // Section 3 - Guidance Skills
            $evaluationData['q3_1'], $evaluationData['q3_2'], $evaluationData['q3_3'], $evaluationData['q3_4'],
            
            // Section 4 - Personal and Social Characteristics
            $evaluationData['q4_1'], $evaluationData['q4_2'], $evaluationData['q4_3'],
            $evaluationData['q4_4'], $evaluationData['q4_5'], $evaluationData['q4_6'],
            
            $evaluationData['comments'] ?? ''
        ]);
        
        return $result;
    } catch (Exception $e) {
        error_log("Save evaluation error: " . $e->getMessage());
        throw $e;
    }
}

// Add a new teacher assignment
function addTeacherAssignment($teacherName, $section, $subject, $program) {
    try {
        $pdo = getPDO();
        
        $stmt = $pdo->prepare("
            INSERT INTO teacher_assignments (teacher_name, section, subject, program, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON CONFLICT (teacher_name, section, subject, school_year, semester) 
            DO UPDATE SET updated_at = NOW(), is_active = true
        ");
        
        return $stmt->execute([$teacherName, $section, $subject, $program]);
    } catch (Exception $e) {
        error_log("Add teacher assignment error: " . $e->getMessage());
        return false;
    }
}
?>
