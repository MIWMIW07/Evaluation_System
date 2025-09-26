<?php
// google_sheets_integration.php - Google Sheets API Integration

require_once 'vendor/autoload.php'; // Composer autoload for Google Client

// Check if service account file exists
if (!file_exists(__DIR__ . '/credentials/service-account-key.json')) {
    // Return error response instead of crashing
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Google Sheets integration not configured. Missing service account key.'
    ]);
    exit;
}


class GoogleSheetsIntegration {
    private $client;
    private $service;
    private $pdo;
    
    // Google Sheets configuration
    private $spreadsheetId;
    private $studentsRange = 'Students!A:E'; // Columns: Last Name, First Name, Middle Name, Section, Student ID
    private $teachersRange = 'Teachers!A:C'; // Columns: Name, Department (SHS/COLLEGE/BOTH), Subject
    
    public function __construct($pdo, $credentialsPath, $spreadsheetId) {
        $this->pdo = $pdo;
        $this->spreadsheetId = $spreadsheetId;
        
        if (!file_exists($credentialsPath)) {
            throw new Exception("Credentials file not found: " . $credentialsPath);
        }
        
        // Initialize Google Client
        $this->client = new Google_Client();
        $this->client->setApplicationName('Teacher Evaluation System');
        $this->client->setScopes([\Google_Service_Sheets::SPREADSHEETS_READONLY]);
        $this->client->setAuthConfig($credentialsPath);
        $this->client->setAccessType('offline');
        
        $this->service = new \Google_Service_Sheets($this->client);
    }
    
    /**
     * Fetch students from Google Sheets and sync with database
     */
    public function syncStudents() {
        try {
            // Fetch data from Google Sheets
            $response = $this->service->spreadsheets_values->get(
                $this->spreadsheetId, 
                $this->studentsRange
            );
            $values = $response->getValues();
            
            if (empty($values)) {
                throw new Exception('No student data found in Google Sheets');
            }
            
            // Skip header row if it exists
            if (!empty($values) && $this->isHeaderRow($values[0])) {
                array_shift($values);
            }
            
            $synced_count = 0;
            $errors = [];
            
            foreach ($values as $rowIndex => $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }
                
                if (count($row) >= 4) { // Ensure minimum required columns
                    $last_name = trim($row[0] ?? '');
                    $first_name = trim($row[1] ?? '');
                    $middle_name = trim($row[2] ?? '');
                    $section_code = trim($row[3] ?? '');
                    $student_id = trim($row[4] ?? '');
                    
                    if (empty($last_name) || empty($first_name) || empty($section_code)) {
                        $errors[] = "Row " . ($rowIndex + 2) . ": Missing required data (Last Name, First Name, or Section)";
                        continue;
                    }
                    
                    // Generate student ID if not provided
                    if (empty($student_id)) {
                        $student_id = $this->generateStudentId($last_name, $first_name);
                    }
                    
                    $full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
                    
                    // Get or create section
                    $section_id = $this->getOrCreateSection($section_code);
                    
                    if ($section_id) {
                        // Insert or update student
                        $stmt = $this->pdo->prepare("
                            INSERT INTO students (student_id, last_name, first_name, middle_name, full_name, section_id) 
                            VALUES (?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                                last_name = VALUES(last_name),
                                first_name = VALUES(first_name),
                                middle_name = VALUES(middle_name),
                                full_name = VALUES(full_name),
                                section_id = VALUES(section_id)
                        ");
                        
                        if ($stmt->execute([$student_id, $last_name, $first_name, $middle_name, $full_name, $section_id])) {
                            $synced_count++;
                            
                            // Create user account for student if not exists
                            $this->createStudentUserAccount($student_id, $full_name, $last_name, $first_name);
                        } else {
                            $errors[] = "Row " . ($rowIndex + 2) . ": Failed to save student data";
                        }
                    } else {
                        $errors[] = "Row " . ($rowIndex + 2) . ": Could not create section for: $section_code";
                    }
                } else {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Insufficient columns (need at least 4)";
                }
            }
            
            return [
                'success' => true,
                'synced_count' => $synced_count,
                'errors' => $errors,
                'message' => "Successfully synced $synced_count students"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Fetch teachers from Google Sheets and sync with database
     */
    public function syncTeachers() {
        try {
            $response = $this->service->spreadsheets_values->get(
                $this->spreadsheetId, 
                $this->teachersRange
            );
            $values = $response->getValues();
            
            if (empty($values)) {
                throw new Exception('No teacher data found in Google Sheets');
            }
            
            // Skip header row if it exists
            if (!empty($values) && $this->isHeaderRow($values[0], 'teacher')) {
                array_shift($values);
            }
            
            // Clear existing teachers first to avoid duplicates
            $this->pdo->exec("DELETE FROM teachers");
            
            $synced_count = 0;
            $errors = [];
            
            foreach ($values as $rowIndex => $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }
                
                if (count($row) >= 2) {
                    $name = trim($row[0] ?? '');
                    $department = strtoupper(trim($row[1] ?? ''));
                    $subject = trim($row[2] ?? 'General');
                    
                    if (empty($name) || empty($department)) {
                        $errors[] = "Row " . ($rowIndex + 2) . ": Missing teacher name or department";
                        continue;
                    }
                    
                    // Validate department
                    if (!in_array($department, ['SHS', 'COLLEGE', 'BOTH'])) {
                        $errors[] = "Row " . ($rowIndex + 2) . ": Invalid department '$department' (use SHS, COLLEGE, or BOTH)";
                        continue;
                    }
                    
                    // Handle teachers who teach both SHS and COLLEGE
                    if ($department === 'BOTH') {
                        // Create entry for SHS
                        $stmt = $this->pdo->prepare("INSERT INTO teachers (name, department, subject) VALUES (?, 'SHS', ?)");
                        if ($stmt->execute([$name, $subject])) {
                            $synced_count++;
                        }
                        
                        // Create entry for COLLEGE
                        $stmt = $this->pdo->prepare("INSERT INTO teachers (name, department, subject) VALUES (?, 'COLLEGE', ?)");
                        if ($stmt->execute([$name, $subject])) {
                            $synced_count++;
                        }
                    } else {
                        // Single department teacher
                        $stmt = $this->pdo->prepare("INSERT INTO teachers (name, department, subject) VALUES (?, ?, ?)");
                        if ($stmt->execute([$name, $department, $subject])) {
                            $synced_count++;
                        }
                    }
                } else {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Insufficient columns (need at least 2)";
                }
            }
            
            return [
                'success' => true,
                'synced_count' => $synced_count,
                'errors' => $errors,
                'message' => "Successfully synced $synced_count teacher entries"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get or create section by section code
     */
    private function getOrCreateSection($section_code) {
        // Check if section exists
        $stmt = $this->pdo->prepare("SELECT id FROM sections WHERE section_code = ?");
        $stmt->execute([$section_code]);
        $section_id = $stmt->fetchColumn();
        
        if ($section_id) {
            return $section_id;
        }
        
        // Create new section based on naming convention
        $program = $this->determineProgramFromSectionCode($section_code);
        $year_level = $this->determineYearLevelFromSectionCode($section_code);
        $section_name = $this->generateSectionName($section_code, $program, $year_level);
        
        $stmt = $this->pdo->prepare("INSERT INTO sections (section_code, section_name, program, year_level) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$section_code, $section_name, $program, $year_level])) {
            return $this->pdo->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Determine program from section code
     */
    private function determineProgramFromSectionCode($section_code) {
        $section_upper = strtoupper($section_code);
        
        if (strpos($section_upper, 'BSCS') !== false || 
            strpos($section_upper, 'BSOA') !== false || 
            strpos($section_upper, 'EDUC') !== false ||
            strpos($section_upper, 'BS') !== false ||
            strpos($section_upper, 'BA') !== false) {
            return 'COLLEGE';
        }
        return 'SHS';
    }
    
    /**
     * Determine year level from section code
     */
    private function determineYearLevelFromSectionCode($section_code) {
        if (preg_match('/(\d+)/', $section_code, $matches)) {
            $num = intval($matches[1]);
            $isCollege = $this->determineProgramFromSectionCode($section_code) === 'COLLEGE';
            
            if ($num == 11) return 'Grade 11';
            if ($num == 12) return 'Grade 12';
            if ($num == 1) return $isCollege ? '1st Year' : 'Grade 11';
            if ($num == 2) return $isCollege ? '2nd Year' : 'Grade 12';
            if ($num == 3) return '3rd Year';
            if ($num == 4) return '4th Year';
        }
        return 'Unknown';
    }
    
    /**
     * Generate section name from code
     */
    private function generateSectionName($section_code, $program, $year_level) {
        return $section_code . ' - ' . $program . ' ' . $year_level;
    }
    
    /**
     * Generate student ID if not provided
     */
    private function generateStudentId($last_name, $first_name) {
        $year = date('Y');
        $clean_lastname = preg_replace('/[^a-zA-Z]/', '', $last_name);
        $clean_firstname = preg_replace('/[^a-zA-Z]/', '', $first_name);
        
        // Get count of existing students this year
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id LIKE ?");
        $stmt->execute([$year . '%']);
        $count = $stmt->fetchColumn() + 1;
        
        return $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Create user account for student
     */
    private function createStudentUserAccount($student_id, $full_name, $last_name, $first_name) {
        // Generate username (lastname + first letter of firstname + random number)
        $clean_lastname = preg_replace('/[^a-zA-Z0-9]/', '', $last_name);
        $clean_firstname = preg_replace('/[^a-zA-Z0-9]/', '', $first_name);
        $base_username = strtolower($clean_lastname . substr($clean_firstname, 0, 1));
        
        // Make username unique
        $username = $base_username;
        $counter = 1;
        while ($this->usernameExists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }
        
        // Check if user already exists for this student
        $check_stmt = $this->pdo->prepare("
            SELECT u.id FROM users u 
            JOIN students s ON u.student_table_id = s.id 
            WHERE s.student_id = ?
        ");
        $check_stmt->execute([$student_id]);
        
        if ($check_stmt->fetchColumn() == 0) {
            $default_password = password_hash('pass123', PASSWORD_DEFAULT);
            
            // Get the student's database ID
            $student_stmt = $this->pdo->prepare("SELECT id FROM students WHERE student_id = ?");
            $student_stmt->execute([$student_id]);
            $student_table_id = $student_stmt->fetchColumn();
            
            if ($student_table_id) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO users (username, password, user_type, full_name, student_table_id) 
                    VALUES (?, ?, 'student', ?, ?)
                ");
                $stmt->execute([$username, $default_password, $full_name, $student_table_id]);
            }
        }
    }
    
    /**
     * Check if username exists
     */
    private function usernameExists($username) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Check if row is a header row
     */
    private function isHeaderRow($row, $type = 'student') {
        if ($type === 'student') {
            return stripos($row[0] ?? '', 'last') !== false || 
                   stripos($row[0] ?? '', 'surname') !== false ||
                   stripos($row[1] ?? '', 'first') !== false;
        } else {
            return stripos($row[0] ?? '', 'name') !== false || 
                   stripos($row[1] ?? '', 'department') !== false;
        }
    }
    
    /**
     * Sync both students and teachers
     */
    public function syncAll() {
        $students_result = $this->syncStudents();
        $teachers_result = $this->syncTeachers();
        
        return [
            'students' => $students_result,
            'teachers' => $teachers_result
        ];
    }
}
?>
