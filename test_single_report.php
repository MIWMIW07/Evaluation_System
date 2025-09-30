<?php
// test_single_report.php - Generate just one report to test
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    die('Admin access required');
}

require_once 'vendor/autoload.php';

use Google\Client;
use Google\Service\Drive;
use Google\Service\Sheets;

try {
    echo "<h3>🧪 Test Single Report Generation</h3>";
    
    $pdo = getPDO();
    
    // Get one evaluation to test with
    $testEval = $pdo->query("
        SELECT * FROM evaluations 
        WHERE teacher_name = 'MR JIMENEZ' 
        LIMIT 1
    ")->fetch();
    
    if (!$testEval) {
        die("❌ No evaluations found for MR JIMENEZ to test with");
    }
    
    echo "<p>Testing with evaluation from: <strong>{$testEval['student_name']}</strong> for teacher: <strong>{$testEval['teacher_name']}</strong></p>";
    
    // Initialize Google services
    $credentialsJson = getenv('GOOGLE_CREDENTIALS_JSON');
    $client = new Client();
    $client->setAuthConfig(json_decode($credentialsJson, true));
    $client->addScope([Drive::DRIVE_FILE, Sheets::SPREADSHEETS]);
    
    $driveService = new Drive($client);
    $sheetsService = new Sheets($client);
    
    // Create test folder
    $folderName = 'TEST Report - ' . date('Y-m-d H-i-s');
    $folderMetadata = new Drive\DriveFile([
        'name' => $folderName,
        'mimeType' => 'application/vnd.google-apps.folder'
    ]);
    $folder = $driveService->files->create($folderMetadata);
    $folderId = $folder->getId();
    
    echo "<p>✅ Test folder created: " . $folderId . "</p>";
    
    // Create test spreadsheet
    $fileName = "TEST Individual Report - " . $testEval['student_name'];
    $spreadsheet = new Sheets\Spreadsheet([
        'properties' => ['title' => $fileName]
    ]);
    
    $createdSpreadsheet = $sheetsService->spreadsheets->create($spreadsheet);
    $spreadsheetId = $createdSpreadsheet->getSpreadsheetId();
    
    echo "<p>✅ Test spreadsheet created: " . $spreadsheetId . "</p>";
    
    // Move to folder
    $file = $driveService->files->get($spreadsheetId, ['fields' => 'parents']);
    $previousParents = join(',', $file->parents);
    
    $driveService->files->update($spreadsheetId, new Drive\DriveFile(), [
        'addParents' => $folderId,
        'removeParents' => $previousParents,
        'fields' => 'id, parents'
    ]);
    
    echo "<p>✅ File moved to folder</p>";
    
    // Add some test data
    $testData = [
        ['TEST REPORT - SUCCESSFUL'],
        ['Student:', $testEval['student_name']],
        ['Teacher:', $testEval['teacher_name']],
        ['Program:', $testEval['program']],
        ['Generated:', date('Y-m-d H:i:s')],
        ['Status:', '✅ WORKING CORRECTLY']
    ];
    
    $range = 'Sheet1!A1:B10';
    $body = new Sheets\ValueRange(['values' => $testData]);
    $sheetsService->spreadsheets_values->update($spreadsheetId, $range, $body, ['valueInputOption' => 'RAW']);
    
    echo "<p>✅ Test data added to spreadsheet</p>";
    
    // Get shareable link
    $fileInfo = $driveService->files->get($spreadsheetId, ['fields' => 'webViewLink']);
    $fileUrl = $fileInfo->getWebViewLink();
    
    echo "<p><strong>🎉 SUCCESS! Test report created:</strong></p>";
    echo "<p><a href='{$fileUrl}' target='_blank'>Open Test Report in Google Sheets</a></p>";
    echo "<p>If this worked, the main report generator should work too.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ TEST FAILED:</strong> " . $e->getMessage() . "</p>";
    echo "<pre>Error details: " . $e->getTraceAsString() . "</pre>";
}
?>
