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
        // ✅ PostgreSQL connection
        $dbUrl = getenv("DATABASE_URL") ?: ($_ENV["DATABASE_URL"] ?? $_SERVER["DATABASE_URL"] ?? null);
        if (!$dbUrl) {
            throw new Exception("❌ DATABASE_URL environment variable not set");
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

        // ✅ Google Sheets
        $this->sheetId = getenv("GOOGLE_SHEETS_ID") ?: ($_ENV["GOOGLE_SHEETS_ID"] ?? $_SERVER["GOOGLE_SHEETS_ID"] ?? null);
        $googleCreds = getenv("GOOGLE_CREDENTIALS_JSON") ?: ($_ENV["GOOGLE_CREDENTIALS_JSON"] ?? $_SERVER["GOOGLE_CREDENTIALS_JSON"] ?? null);

        if (!$this->sheetId) {
            throw new Exception("❌ GOOGLE_SHEETS_ID environment variable not set");
        }
        if (!$googleCreds) {
            throw new Exception("❌ GOOGLE_CREDENTIALS_JSON environment variable not set");
        }

        $client = new Client();
        $client->setApplicationName("Evaluation System");
        $client->setScopes([Sheets::SPREADSHEETS_READONLY]);
        $client->setAuthConfig(json_decode($googleCreds, true));
        $this->sheetsService = new Sheets($client);
    }

    /** ------------------ AUTH ------------------- */
    public function authenticateUser($username, $password) {
        // 1. Try Postgres (admins)
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            return [
                'id'   => $user['id'],
                'type' => $user['type'] ?? 'admin'
            ];
        }

        // 2. Try Google Sheets (students + teachers)
        if ($username && $password) {
            $student = $this->findStudent($username, $password);
            if ($student) {
                return ['id' => $student['id'], 'type' => 'student'];
            }

            $teacher = $this->findTeacher($username, $password);
            if ($teacher) {
                return ['id' => $teacher['id'], 'type' => 'teacher'];
            }
        }

        return false;
    }

    /** ------------------ STUDENTS ------------------- */
    private function findStudent($username, $password) {
        $range = "Students!A:C";
        $response = $this->sheetsService->spreadsheets_values->get($this->sheetId, $range);
        $rows = $response->getValues();

        foreach ($rows as $i => $row) {
            if ($i === 0) continue;
            if (isset($row[0]) && $row[0] === $username && isset($row[1]) && $row[1] === $password) {
                return ['id' => $i, 'name' => $row[0]];
            }
        }
        return null;
    }

    /** ------------------ TEACHERS ------------------- */
    private function findTeacher($username, $password) {
        $range = "Teachers!A:C";
        $response = $this->sheetsService->spreadsheets_values->get($this->sheetId, $range);
        $rows = $response->getValues();

        foreach ($rows as $i => $row) {
            if ($i === 0) continue;
            if (isset($row[0]) && $row[0] === $username && isset($row[1]) && $row[1] === $password) {
                return [
                    'id' => $i,
                    'name' => $row[0],
                    'department' => $row[1] ?? null,
                    'subject' => $row[2] ?? null
                ];
            }
        }
        return null;
    }

    public function getTeachers() {
        $range = "Teachers!A:C";
        $response = $this->sheetsService->spreadsheets_values->get($this->sheetId, $range);
        $rows = $response->getValues();
        return array_slice($rows, 1);
    }
}

// ✅ Helper function
function getDataManager() {
    static $manager = null;
    if (!$manager) {
        $manager = new HybridDataManager();
    }
    return $manager;
}
