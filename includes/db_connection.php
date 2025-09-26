<?php
// includes/db_connection.php
// PostgreSQL + Google Sheets Hybrid

require_once __DIR__ . '/../vendor/autoload.php'; // Composer autoload for Google API

use Google\Client;
use Google\Service\Sheets;

class HybridDataManager {
    private $pdo;
    private $sheetsService;
    private $sheetId;

    public function __construct() {
        // =========================
        // PostgreSQL Connection
        // =========================
        $dbUrl = getenv('DATABASE_URL');
        if (!$dbUrl) {
            throw new Exception("❌ DATABASE_URL environment variable not set");
        }

        $db = parse_url($dbUrl);
        if ($db === false || !isset($db['scheme']) || $db['scheme'] !== 'postgresql') {
            throw new Exception("❌ Unsupported database scheme (only PostgreSQL is allowed)");
        }

        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s",
            $db['host'],
            $db['port'] ?? 5432,
            ltrim($db['path'], '/'),
            $db['user'],
            $db['pass']
        );

        try {
            $this->pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            throw new Exception("❌ PostgreSQL connection failed: " . $e->getMessage());
        }

        // =========================
        // Google Sheets Connection
        // =========================
        $googleCreds = getenv('GOOGLE_CREDENTIALS_JSON');
        if (!$googleCreds) {
            throw new Exception("❌ GOOGLE_CREDENTIALS_JSON environment variable not set");
        }

        $client = new Client();
        $client->setAuthConfig(json_decode($googleCreds, true));
        $client->addScope(Sheets::SPREADSHEETS);

        $this->sheetsService = new Sheets($client);

        // Your Google Sheet ID (from the URL of the sheet)
        $this->sheetId = getenv('GOOGLE_SHEET_ID');
        if (!$this->sheetId) {
            throw new Exception("❌ GOOGLE_SHEET_ID environment variable not set");
        }
    }

    // ✅ Check if PostgreSQL is available
    public function isDatabaseAvailable(): bool {
        return $this->pdo !== null;
    }

    // ✅ Authenticate users (Admins from DB, Students from Google Sheets)
    public function authenticateUser(string $username, string $password) {
        // First, try admin users from PostgreSQL
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            return [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'user_type' => $user['user_type']
            ];
        }

        // Next, try students from Google Sheets (Sheet1)
        $range = "Sheet1!A:D"; // Adjust if you have more/less columns
        $response = $this->sheetsService->spreadsheets_values->get($this->sheetId, $range);
        $rows = $response->getValues();

        if (!empty($rows)) {
            foreach ($rows as $row) {
                // Expected columns: [StudentID, Username, Password, FullName]
                if (count($row) >= 3) {
                    $sheetUsername = strtoupper(trim($row[1]));
                    $sheetPassword = trim($row[2]);
                    $sheetName = $row[3] ?? $row[1];

                    if ($sheetUsername === strtoupper($username) && $sheetPassword === $password) {
                        return [
                            'id' => $row[0],
                            'username' => $sheetUsername,
                            'full_name' => $sheetName,
                            'user_type' => 'student'
                        ];
                    }
                }
            }
        }

        return false;
    }

    // ✅ Get Teachers from Google Sheets (Sheet2)
    public function getTeachersFromSheets(): array {
        $range = "Sheet2!A:C"; // Name | Department | Subject
        $response = $this->sheetsService->spreadsheets_values->get($this->sheetId, $range);
        $rows = $response->getValues();

        $teachers = [];
        if (!empty($rows)) {
            foreach ($rows as $row) {
                if (count($row) >= 2) {
                    $teachers[] = [
                        'name' => $row[0],
                        'department' => $row[1],
                        'subject' => $row[2] ?? ''
                    ];
                }
            }
        }
        return $teachers;
    }

    // ✅ Log activity in PostgreSQL
    public function logActivity(string $action, string $description, string $status = 'success', $userId = null): void {
        $stmt = $this->pdo->prepare("INSERT INTO activity_log (action, description, status, user_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$action, $description, $status, $userId]);
    }
}

// =========================
// Global instance
// =========================
$hybrid = new HybridDataManager();

// Helper function for other scripts
function isDatabaseAvailable(): bool {
    global $hybrid;
    return $hybrid->isDatabaseAvailable();
}

function authenticateUser(string $username, string $password) {
    global $hybrid;
    return $hybrid->authenticateUser($username, $password);
}

function getTeachersFromSheets(): array {
    global $hybrid;
    return $hybrid->getTeachersFromSheets();
}

function logActivity(string $action, string $description, string $status = 'success', $userId = null): void {
    global $hybrid;
    $hybrid->logActivity($action, $description, $status, $userId);
}
?>
