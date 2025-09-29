<?php
// includes/google_sheets_connection.php
// Helper functions for Google Sheets API integration

require_once __DIR__ . '/../vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

class GoogleSheetsHelper {
    private $client;
    private $service;
    private $spreadsheetId;
    
    public function __construct() {
        $this->initializeClient();
    }
    
    private function initializeClient() {
        try {
            // Get credentials from environment variable
            $credentialsJson = getenv('GOOGLE_CREDENTIALS_JSON');
            if (!$credentialsJson) {
                throw new Exception("Google credentials not found in environment variables");
            }
            
            // Parse JSON credentials
            $credentials = json_decode($credentialsJson, true);
            if (!$credentials) {
                throw new Exception("Invalid Google credentials JSON format");
            }
            
            // Create temporary credentials file
            $tempFile = sys_get_temp_dir() . '/google_credentials_' . uniqid() . '.json';
            file_put_contents($tempFile, $credentialsJson);
            
            // Initialize Google Client
            $this->client = new Client();
            $this->client->setAuthConfig($tempFile);
            $this->client->addScope(Sheets::SPREADSHEETS);
            $this->client->setAccessType('offline');
            
            // Initialize Sheets service
            $this->service = new Sheets($this->client);
            
            // Clean up temporary file
            unlink($tempFile);
            
            // Set your spreadsheet ID (you'll need to add this as an environment variable)
            $this->spreadsheetId = getenv('GOOGLE_SHEETS_ID') ?: 'your-spreadsheet-id-here';
            
        } catch (Exception $e) {
            error_log("Google Sheets initialization error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function readSheet($range) {
        try {
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
            return $response->getValues();
        } catch (Exception $e) {
            error_log("Error reading sheet: " . $e->getMessage());
            return false;
        }
    }
    
    public function writeSheet($range, $values) {
        try {
            $body = new Google\Service\Sheets\ValueRange([
                'values' => $values
            ]);
            
            $params = [
                'valueInputOption' => 'RAW'
            ];
            
            $response = $this->service->spreadsheets_values->update(
                $this->spreadsheetId, 
                $range, 
                $body, 
                $params
            );
            
            return $response->getUpdatedCells() > 0;
        } catch (Exception $e) {
            error_log("Error writing to sheet: " . $e->getMessage());
            return false;
        }
    }
    
    public function appendSheet($range, $values) {
        try {
            $body = new Google\Service\Sheets\ValueRange([
                'values' => $values
            ]);
            
            $params = [
                'valueInputOption' => 'RAW'
            ];
            
            $response = $this->service->spreadsheets_values->append(
                $this->spreadsheetId, 
                $range, 
                $body, 
                $params
            );
            
            return $response->getUpdates()->getUpdatedCells() > 0;
        } catch (Exception $e) {
            error_log("Error appending to sheet: " . $e->getMessage());
            return false;
        }
    }
    
    // Helper method to find student by generated username
    public function findStudentByGeneratedUsername($username) {
        $students = $this->readSheet('Students!A:E'); // Student_ID, Last_Name, First_Name, Section, Program
        
        if (!$students) return false;
        
        foreach ($students as $index => $row) {
            if ($index === 0) continue; // Skip header row
            
            if (isset($row[1]) && isset($row[2])) {
                $lastName = strtoupper(trim($row[1]));
                $firstName = strtoupper(trim($row[2]));
                $generatedUsername = $lastName . $firstName;
                
                if ($generatedUsername === strtoupper($username)) {
                    return [
                        'row' => $index + 1,
                        'student_id' => $row[0] ?? '',
                        'last_name' => $row[1] ?? '',
                        'first_name' => $row[2] ?? '',
                        'full_name' => trim($row[2] . ' ' . $row[1]),
                        'section' => $row[3] ?? '',
                        'program' => $row[4] ?? '',
                        'username' => $generatedUsername
                    ];
                }
            }
        }
        
        return false;
    }
    
    // Helper method to get all students (for admin dashboard)
    public function getAllStudents() {
        $students = $this->readSheet('Students!A:E');
        
        if (!$students) return [];
        
        $result = [];
        foreach ($students as $index => $row) {
            if ($index === 0) continue; // Skip header row
            
            if (isset($row[1]) && isset($row[2])) {
                $lastName = strtoupper(trim($row[1]));
                $firstName = strtoupper(trim($row[2]));
                $generatedUsername = $lastName . $firstName;
                
                $result[] = [
                    'student_id' => $row[0] ?? '',
                    'last_name' => $row[1] ?? '',
                    'first_name' => $row[2] ?? '',
                    'full_name' => trim($row[2] . ' ' . $row[1]),
                    'section' => $row[3] ?? '',
                    'program' => $row[4] ?? '',
                    'username' => $generatedUsername
                ];
            }
        }
        
        return $result;
    }
    
    // Helper method to get teachers list
    public function getTeachers() {
        $teachers = $this->readSheet('Teachers!A:C'); // Adjust range as needed
        
        if (!$teachers) return [];
        
        $result = [];
        foreach ($teachers as $index => $row) {
            if ($index === 0) continue; // Skip header row
            
            $result[] = [
                'id' => $index,
                'name' => $row[0] ?? '',
                'department' => $row[1] ?? '',
                'subject' => $row[2] ?? ''
            ];
        }
        
        return $result;
    }
    
    // Helper method to save evaluation
    public function saveEvaluation($evaluationData) {
        // Prepare data for Google Sheets
        $row = [
            date('Y-m-d H:i:s'), // Timestamp
            $evaluationData['student_id'],
            $evaluationData['student_name'],
            $evaluationData['teacher_name'],
            $evaluationData['section'],
            $evaluationData['program'],
            
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
        
        return $this->appendSheet('Evaluations!A:Z', [$row]);
    }
    
    // Test connection
    public function testConnection() {
        try {
            // Try to read a simple range to test connection
            $response = $this->service->spreadsheets->get($this->spreadsheetId);
            return [
                'success' => true,
                'title' => $response->getProperties()->getTitle(),
                'sheets' => count($response->getSheets())
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Global helper functions for backwards compatibility
function getGoogleSheetsHelper() {
    static $helper = null;
    if ($helper === null) {
        try {
            $helper = new GoogleSheetsHelper();
        } catch (Exception $e) {
            error_log("Failed to initialize Google Sheets helper: " . $e->getMessage());
            return false;
        }
    }
    return $helper;
}

function testGoogleSheetsConnection() {
    $helper = getGoogleSheetsHelper();
    return $helper ? $helper->testConnection() : ['success' => false, 'error' => 'Helper not initialized'];
}
?>
