<?php
// includes/db_connection.php

require_once __DIR__ . '/../vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

// ================================
// GOOGLE SHEETS CONNECTION
// ================================
function getGoogleService() {
    static $service = null;

    if ($service === null) {
        $client = new Client();
        $client->setApplicationName("Evaluation System");
        $client->setScopes([Sheets::SPREADSHEETS_READONLY]);

        $credentials = getenv('GOOGLE_CREDENTIALS_JSON');
        if (!$credentials) {
            throw new Exception("Missing GOOGLE_CREDENTIALS_JSON environment variable");
        }

        $client->setAuthConfig(json_decode($credentials, true));
        $service = new Sheets($client);
    }

    return $service;
}

// Spreadsheet ID from env
$spreadsheetId = getenv('GOOGLE_SPREADSHEET_ID');

// ================================
// FETCH STUDENTS (Sheet1)
// ================================
function getStudents() {
    global $spreadsheetId;
    $service = getGoogleService();

    $range = "Sheet1!A:C"; // adjust if you have more columns
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $rows = $response->getValues();

    $students = [];
    if (!empty($rows)) {
        $headers = array_map('strtolower', $rows[0]); // first row = headers
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $students[] = [
                'name' => $row[0] ?? '',
                'department' => $row[1] ?? '',
                'subject' => $row[2] ?? ''
            ];
        }
    }
    return $students;
}

// ================================
// FETCH TEACHERS (Sheet2)
// ================================
function getTeachers() {
    global $spreadsheetId;
    $service = getGoogleService();

    $range = "Sheet2!A:C";
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $rows = $response->getValues();

    $teachers = [];
    if (!empty($rows)) {
        $headers = array_map('strtolower', $rows[0]);
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $teachers[] = [
                'name' => $row[0] ?? '',
                'department' => strtoupper(trim($row[1] ?? '')),
                'subject' => $row[2] ?? ''
            ];
        }
    }
    return $teachers;
}

// ================================
// DATABASE CONNECTION (Railway)
// ================================
$pdo = null;

if (getenv('DATABASE_URL')) {
    $dbUrl = getenv('DATABASE_URL');
    $dbParts = parse_url($dbUrl);

    $scheme = $dbParts['scheme'];
    $host = $dbParts['host'];
    $user = $dbParts['user'];
    $pass = $dbParts['pass'];
    $port = $dbParts['port'];
    $dbName = ltrim($dbParts['path'], '/');

    if ($scheme === 'postgres') {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbName";
    } elseif ($scheme === 'mysql') {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";
    } else {
        throw new Exception("Unsupported database scheme: $scheme");
    }

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (Exception $e) {
        $pdo = null;
    }
}

function isDatabaseAvailable() {
    global $pdo;
    return $pdo !== null;
}

// ================================
// AUTHENTICATION
// ================================
function authenticateUser($username, $password) {
    global $pdo;

    // Admins stored in DB
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password'])) {
            return ['type' => 'admin', 'id' => $admin['id'], 'name' => $admin['username']];
        }
    }

    // Students from Google Sheets
    foreach (getStudents() as $student) {
        if (strtolower($student['name']) === strtolower($username)) {
            // for demo: password = department (you can change logic later)
            if ($password === $student['department']) {
                return ['type' => 'student', 'id' => $student['name'], 'name' => $student['name']];
            }
        }
    }

    return null;
}
