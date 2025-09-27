<?php
// debug_sheets.php - Test Google Sheets connection
require_once 'includes/db_connection.php';

echo "<h2>Google Sheets Debug Test</h2>";

try {
    $manager = getDataManager();
    
    // Test if we can access the Google Sheets service
    $reflection = new ReflectionClass($manager);
    $sheetsProperty = $reflection->getProperty('sheetsService');
    $sheetsProperty->setAccessible(true);
    $sheetsService = $sheetsProperty->getValue($manager);
    
    $sheetIdProperty = $reflection->getProperty('sheetId');
    $sheetIdProperty->setAccessible(true);
    $sheetId = $sheetIdProperty->getValue($manager);
    
    echo "<p><strong>Sheet ID:</strong> " . htmlspecialchars($sheetId ?: 'NOT SET') . "</p>";
    echo "<p><strong>Sheets Service:</strong> " . ($sheetsService ? 'Connected' : 'NOT CONNECTED') . "</p>";
    
    if ($sheetsService && $sheetId) {
        echo "<h3>Reading Students Sheet:</h3>";
        
        $range = "Students!A:G";
        $response = $sheetsService->spreadsheets_values->get($sheetId, $range);
        $rows = $response->getValues();
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Row</th><th>A</th><th>B</th><th>C</th><th>D</th><th>E</th><th>F</th><th>G</th></tr>";
        
        foreach ($rows as $index => $row) {
            echo "<tr>";
            echo "<td>" . $index . "</td>";
            for ($i = 0; $i < 7; $i++) {
                $value = isset($row[$i]) ? htmlspecialchars($row[$i]) : '<em>empty</em>';
                echo "<td>" . $value . "</td>";
            }
            echo "</tr>";
            
            if ($index >= 10) { // Show only first 10 rows
                echo "<tr><td colspan='8'><em>... (showing first 10 rows only)</em></td></tr>";
                break;
            }
        }
        echo "</table>";
        
        // Test a specific login
        echo "<h3>Testing Login for First Student:</h3>";
        if (count($rows) > 1 && isset($rows[1])) {
            $testRow = $rows[1];
            $testUsername = $testRow[5] ?? '';
            $testPassword = $testRow[6] ?? '';
            
            echo "<p><strong>Test Username:</strong> " . htmlspecialchars($testUsername) . "</p>";
            echo "<p><strong>Test Password:</strong> " . htmlspecialchars($testPassword) . "</p>";
            
            // Try to authenticate
            $result = $manager->authenticateUser($testUsername, $testPassword);
            echo "<p><strong>Authentication Result:</strong> " . ($result ? 'SUCCESS' : 'FAILED') . "</p>";
            
            if ($result) {
                echo "<pre>" . print_r($result, true) . "</pre>";
            }
        }
        
    } else {
        echo "<p style='color: red;'>Cannot connect to Google Sheets. Check your environment variables:</p>";
        echo "<ul>";
        echo "<li>GOOGLE_SHEETS_ID: " . (getenv('GOOGLE_SHEETS_ID') ? 'SET' : 'NOT SET') . "</li>";
        echo "<li>GOOGLE_CREDENTIALS_JSON: " . (getenv('GOOGLE_CREDENTIALS_JSON') ? 'SET' : 'NOT SET') . "</li>";
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Trace:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<br><a href='index.php'>← Back to Login</a>";
?>
