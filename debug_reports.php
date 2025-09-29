<?php
// debug_reports.php - Check where reports are going
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    die('Admin access required');
}

require_once 'vendor/autoload.php';

use Google\Client;
use Google\Service\Drive;

try {
    $credentialsJson = getenv('GOOGLE_CREDENTIALS_JSON');
    if (!$credentialsJson) {
        throw new Exception('GOOGLE_CREDENTIALS_JSON not found');
    }
    
    $credentials = json_decode($credentialsJson, true);
    $serviceAccountEmail = $credentials['client_email'] ?? 'Unknown';
    
    echo "<h3>🔧 Debug Information</h3>";
    echo "<p><strong>Service Account Email:</strong> {$serviceAccountEmail}</p>";
    
    $client = new Client();
    $client->setAuthConfig($credentials);
    $client->addScope(Drive::DRIVE_READONLY);
    
    $driveService = new Drive($client);
    
    // Test connection and list some files
    $results = $driveService->files->listFiles([
        'pageSize' => 10,
        'fields' => 'files(id, name, mimeType, createdTime)',
        'orderBy' => 'createdTime desc'
    ]);
    
    echo "<h4>Recent Files in This Drive:</h4>";
    $files = $results->getFiles();
    
    if (empty($files)) {
        echo "<p>No files found in this Drive account.</p>";
    } else {
        echo "<ul>";
        foreach ($files as $file) {
            echo "<li>{$file->getName()} ({$file->getMimeType()}) - " . 
                 date('Y-m-d H:i:s', strtotime($file->getCreatedTime())) . "</li>";
        }
        echo "</ul>";
    }
    
    // Check for report folders specifically
    $query = "name contains 'Teacher Evaluation Reports' and mimeType = 'application/vnd.google-apps.folder'";
    $folders = $driveService->files->listFiles(['q' => $query]);
    
    echo "<h4>Report Folders Found:</h4>";
    $reportFolders = $folders->getFiles();
    if (empty($reportFolders)) {
        echo "<p>No report folders found.</p>";
    } else {
        foreach ($reportFolders as $folder) {
            echo "<p>📁 <strong>{$folder->getName()}</strong> - Created: " . 
                 date('Y-m-d H:i:s', strtotime($folder->getCreatedTime())) . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</div>";
}
?>
