<?php
// detailed_debug_reports.php - Comprehensive debugging
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    die('Admin access required');
}

require_once 'vendor/autoload.php';

use Google\Client;
use Google\Service\Drive;

try {
    echo "<h3>🔍 Detailed Report Generation Debug</h3>";
    
    // Test database connection and data
    echo "<h4>📊 Database Check</h4>";
    $pdo = getPDO();
    
    // Check total evaluations
    $totalEvals = $pdo->query("SELECT COUNT(*) FROM evaluations")->fetchColumn();
    echo "<p>Total evaluations in database: <strong>{$totalEvals}</strong></p>";
    
    // Check evaluations by teacher
    $teachers = $pdo->query("
        SELECT teacher_name, program, COUNT(*) as count 
        FROM evaluations 
        GROUP BY teacher_name, program 
        ORDER BY teacher_name, program
    ")->fetchAll();
    
    echo "<h4>👨‍🏫 Teachers with Evaluations</h4>";
    if (empty($teachers)) {
        echo "<p style='color: red;'>❌ No evaluations found for any teachers!</p>";
    } else {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
        echo "<tr><th>Teacher</th><th>Program</th><th>Evaluations</th></tr>";
        foreach ($teachers as $teacher) {
            echo "<tr>";
            echo "<td>{$teacher['teacher_name']}</td>";
            echo "<td>{$teacher['program']}</td>";
            echo "<td>{$teacher['count']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check specific teacher data
    echo "<h4>🔎 Checking MR JIMENEZ Data</h4>";
    $jimenezEvals = $pdo->prepare("
        SELECT teacher_name, program, student_name, student_username, section
        FROM evaluations 
        WHERE teacher_name = 'MR JIMENEZ'
        ORDER BY program, student_name
    ");
    $jimenezEvals->execute();
    $jimenezData = $jimenezEvals->fetchAll();
    
    if (empty($jimenezData)) {
        echo "<p style='color: red;'>❌ No evaluations found for MR JIMENEZ!</p>";
    } else {
        echo "<p>Found <strong>" . count($jimenezData) . "</strong> evaluations for MR JIMENEZ</p>";
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
        echo "<tr><th>Program</th><th>Student</th><th>Username</th><th>Section</th></tr>";
        foreach ($jimenezData as $eval) {
            echo "<tr>";
            echo "<td>{$eval['program']}</td>";
            echo "<td>{$eval['student_name']}</td>";
            echo "<td>{$eval['student_username']}</td>";
            echo "<td>{$eval['section']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test Google Drive connection
    echo "<h4>🔗 Google Drive API Test</h4>";
    $credentialsJson = getenv('GOOGLE_CREDENTIALS_JSON');
    if (!$credentialsJson) {
        echo "<p style='color: red;'>❌ GOOGLE_CREDENTIALS_JSON not found</p>";
    } else {
        echo "<p>✅ Google credentials found</p>";
        
        $client = new Client();
        $client->setAuthConfig(json_decode($credentialsJson, true));
        $client->addScope(Drive::DRIVE_FILE);
        
        $driveService = new Drive($client);
        
        // Test creating a small file
        try {
            $testFile = new Drive\DriveFile([
                'name' => 'Test File - ' . date('Y-m-d H-i-s'),
                'mimeType' => 'application/vnd.google-apps.spreadsheet'
            ]);
            
            $createdFile = $driveService->files->create($testFile);
            echo "<p>✅ Google Drive API working - Test file created: " . $createdFile->getId() . "</p>";
            
            // Clean up test file
            $driveService->files->delete($createdFile->getId());
            echo "<p>✅ Test file cleaned up</p>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Google Drive API error: " . $e->getMessage() . "</p>";
        }
    }
    
    // Check recent server logs for errors
    echo "<h4>📋 Recent Error Logs</h4>";
    $logContents = shell_exec('tail -50 /var/log/apache2/error.log 2>/dev/null || tail -50 /var/www/html/error_log 2>/dev/null || echo "No log file found"');
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 300px; overflow: auto;'>" . htmlspecialchars($logContents) . "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Debug error: " . $e->getMessage() . "</p>";
}
?>
