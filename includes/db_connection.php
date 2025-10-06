<?php
// includes/db_connection.php
require_once __DIR__ . '/../vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

class HybridDataManager {
    private $pdo;
    private $sheetsService;
    private $sheetId;

    public function __construct() {
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
        if ($username === 'admin' && $password === 'guidanceservice2025') {
            return ['id' => 'admin', 'type' => 'admin'];
        }

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
            $range = "Students!A:G";
            $response = $this->sheetsService->spreadsheets_values->get($this->sheetId, $range);
            $rows = $response->getValues();

            foreach ($rows as $i => $row) {
                if ($i <= 1) continue; 
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
            return array_slice($rows, 1);
        } catch (Exception $e) {
            error_log("Failed to get teachers: " . $e->getMessage());
            return [];
        }
    }

    public function getPDO() {
        return $this->pdo;
    }
}

// Helper
function getDataManager() {
    static $manager = null;
    if (!$manager) {
        $manager = new HybridDataManager();
    }
    return $manager;
}
function getPDO() { return getDataManager()->getPDO(); }
function isDatabaseAvailable() { try { getPDO(); return true; } catch (Exception $e) { return false; } }
function logActivity($action, $details, $status = 'info', $userId = null) {
    $logMessage = sprintf("[%s] User: %s, Action: %s, Status: %s, Details: %s",
        date('Y-m-d H:i:s'), $userId ?? 'unknown', $action, $status, $details);
    error_log($logMessage);
}

function getTeacherAssignments() {
    try {
        $pdo = getPDO();
        $stmt = $pdo->query("SELECT * FROM teacher_assignments WHERE is_active = true ORDER BY program, section, teacher_name");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getStudentEvaluations($studentUsername = null) {
    try {
        $pdo = getPDO();
        if ($studentUsername) {
            $stmt = $pdo->prepare("SELECT * FROM evaluations WHERE student_username = ? ORDER BY submitted_at DESC");
            $stmt->execute([$studentUsername]);
        } else {
            $stmt = $pdo->query("SELECT * FROM evaluations ORDER BY submitted_at DESC");
        }
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function hasEvaluatedTeacher($studentUsername, $teacherName = null) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT id FROM evaluations WHERE student_username = ? AND teacher_name = ?");
        $stmt->execute([$studentUsername, $teacherName]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

function saveEvaluation($evaluationData) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("
            INSERT INTO evaluations (
                student_username, student_name, teacher_name, section, program,
                q1_1, q1_2, q1_3, q1_4, q1_5, q1_6,
                q2_1, q2_2, q2_3, q2_4,
                q3_1, q3_2, q3_3, q3_4,
                q4_1, q4_2, q4_3, q4_4, q4_5, q4_6,
                positive_comments, negative_comments, created_at, submitted_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, NOW(), NOW()
            )
            ON CONFLICT (student_username, teacher_name, section) 
            DO UPDATE SET
                q1_1 = EXCLUDED.q1_1, q1_2 = EXCLUDED.q1_2, q1_3 = EXCLUDED.q1_3,
                q1_4 = EXCLUDED.q1_4, q1_5 = EXCLUDED.q1_5, q1_6 = EXCLUDED.q1_6,
                q2_1 = EXCLUDED.q2_1, q2_2 = EXCLUDED.q2_2, q2_3 = EXCLUDED.q2_3, q2_4 = EXCLUDED.q2_4,
                q3_1 = EXCLUDED.q3_1, q3_2 = EXCLUDED.q3_2, q3_3 = EXCLUDED.q3_3, q3_4 = EXCLUDED.q3_4,
                q4_1 = EXCLUDED.q4_1, q4_2 = EXCLUDED.q4_2, q4_3 = EXCLUDED.q4_3,
                q4_4 = EXCLUDED.q4_4, q4_5 = EXCLUDED.q4_5, q4_6 = EXCLUDED.q4_6,
                positive_comments = EXCLUDED.positive_comments, 
                negative_comments = EXCLUDED.negative_comments, 
                submitted_at = NOW()
        ");
        
        return $stmt->execute([
            $evaluationData['student_username'],
            $evaluationData['student_name'],
            $evaluationData['teacher_name'],
            $evaluationData['section'],
            $evaluationData['program'],
            $evaluationData['q1_1'], $evaluationData['q1_2'], $evaluationData['q1_3'],
            $evaluationData['q1_4'], $evaluationData['q1_5'], $evaluationData['q1_6'],
            $evaluationData['q2_1'], $evaluationData['q2_2'], $evaluationData['q2_3'], $evaluationData['q2_4'],
            $evaluationData['q3_1'], $evaluationData['q3_2'], $evaluationData['q3_3'], $evaluationData['q3_4'],
            $evaluationData['q4_1'], $evaluationData['q4_2'], $evaluationData['q4_3'],
            $evaluationData['q4_4'], $evaluationData['q4_5'], $evaluationData['q4_6'],
            $evaluationData['positive_comments'] ?? '',
            $evaluationData['negative_comments'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Save evaluation error: " . $e->getMessage());
        throw $e;
    }
}

function addTeacherAssignment($teacherName, $section, $program) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("
            INSERT INTO teacher_assignments (teacher_name, section, program, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
            ON CONFLICT (teacher_name, section, program) 
            DO UPDATE SET updated_at = NOW(), is_active = true
        ");
        return $stmt->execute([$teacherName, $section, $program]);
    } catch (Exception $e) {
        error_log("Add teacher assignment error: " . $e->getMessage());
        return false;
    }
}

// New functions for working with separated comments
function getTeacherPositiveComments($teacherName) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("
            SELECT positive_comments, student_name, section, submitted_at 
            FROM evaluations 
            WHERE teacher_name = ? AND positive_comments IS NOT NULL AND positive_comments != ''
            ORDER BY submitted_at DESC
        ");
        $stmt->execute([$teacherName]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get positive comments error: " . $e->getMessage());
        return [];
    }
}

function getTeacherNegativeComments($teacherName) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("
            SELECT negative_comments, student_name, section, submitted_at 
            FROM evaluations 
            WHERE teacher_name = ? AND negative_comments IS NOT NULL AND negative_comments != ''
            ORDER BY submitted_at DESC
        ");
        $stmt->execute([$teacherName]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get negative comments error: " . $e->getMessage());
        return [];
    }
}

function getEvaluationStatistics($teacherName = null) {
    try {
        $pdo = getPDO();
        
        if ($teacherName) {
            // Statistics for specific teacher
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_evaluations,
                    AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 +
                         q2_1 + q2_2 + q2_3 + q2_4 +
                         q3_1 + q3_2 + q3_3 + q3_4 +
                         q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20.0) as average_rating,
                    COUNT(CASE WHEN positive_comments IS NOT NULL AND positive_comments != '' THEN 1 END) as positive_feedback_count,
                    COUNT(CASE WHEN negative_comments IS NOT NULL AND negative_comments != '' THEN 1 END) as negative_feedback_count
                FROM evaluations 
                WHERE teacher_name = ?
            ");
            $stmt->execute([$teacherName]);
            return $stmt->fetch();
        } else {
            // Overall statistics
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total_evaluations,
                    COUNT(DISTINCT teacher_name) as total_teachers_evaluated,
                    COUNT(DISTINCT student_username) as total_students_participated,
                    AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 +
                         q2_1 + q2_2 + q2_3 + q2_4 +
                         q3_1 + q3_2 + q3_3 + q3_4 +
                         q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20.0) as overall_average_rating
                FROM evaluations
            ");
            return $stmt->fetch();
        }
    } catch (Exception $e) {
        error_log("Get evaluation statistics error: " . $e->getMessage());
        return [];
    }
}

function searchComments($searchTerm, $commentType = 'both') {
    try {
        $pdo = getPDO();
        
        switch ($commentType) {
            case 'positive':
                $stmt = $pdo->prepare("
                    SELECT teacher_name, positive_comments as comments, student_name, section, submitted_at, 'positive' as type
                    FROM evaluations 
                    WHERE positive_comments ILIKE ? 
                    ORDER BY submitted_at DESC
                ");
                break;
            case 'negative':
                $stmt = $pdo->prepare("
                    SELECT teacher_name, negative_comments as comments, student_name, section, submitted_at, 'negative' as type
                    FROM evaluations 
                    WHERE negative_comments ILIKE ? 
                    ORDER BY submitted_at DESC
                ");
                break;
            default:
                $stmt = $pdo->prepare("
                    SELECT teacher_name, positive_comments as comments, student_name, section, submitted_at, 'positive' as type
                    FROM evaluations 
                    WHERE positive_comments ILIKE ?
                    UNION ALL
                    SELECT teacher_name, negative_comments as comments, student_name, section, submitted_at, 'negative' as type
                    FROM evaluations 
                    WHERE negative_comments ILIKE ?
                    ORDER BY submitted_at DESC
                ");
                $searchTerm = "%$searchTerm%";
                $stmt->execute([$searchTerm, $searchTerm]);
                return $stmt->fetchAll();
        }
        
        $searchTerm = "%$searchTerm%";
        $stmt->execute([$searchTerm]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Search comments error: " . $e->getMessage());
        return [];
    }
}
?>

