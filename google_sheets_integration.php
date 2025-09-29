<?php
// google_sheets_integration.php - PostgreSQL compatible version

require_once 'vendor/autoload.php';
require_once 'includes/db_connection.php';

class GoogleSheetsIntegration {
    private $client;
    private $service;
    private $pdo;
    private $spreadsheetId;
    private $studentsRange = 'Students!A:E';
    private $teachersRange = 'Teachers!A:C';
    
    public function __construct($pdo, $credentialsPath, $spreadsheetId) {
        $this->pdo = $pdo;
        $this->spreadsheetId = $spreadsheetId;
        
        if (!file_exists($credentialsPath)) {
            throw new Exception("Credentials file not found: " . $credentialsPath);
        }
        
        // Initialize Google Client
        $this->client = new Google\Client();
        $this->client->setApplicationName('Teacher Evaluation System');
        $this->client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
        $this->client->setAuthConfig($credentialsPath);
        $this->client->setAccessType('offline');
        
        $this->service = new Google\Service\Sheets($this->client);
    }
    
    /**
     * Sync students from Google Sheets - PostgreSQL compatible
     */
    public function syncStudents() {
        try {
            $response = $this->service->spreadsheets_values->get(
                $this->spreadsheetId, 
                $this->studentsRange
            );
            $values = $response->getValues();
            
            if (empty($values)) {
                throw new Exception('No student data found in Google Sheets');
            }
            
            // Skip header row
            if (!empty($values) && $this->isHeaderRow($values[0])) {
                array_shift($values);
            }
            
            $synced_count = 0;
            $errors = [];
            
            foreach ($values as $rowIndex => $row) {
                if (empty(array_filter($row))) {
                    continue;
                }
                
                if (count($row) >= 4) {
                    $last_name = trim($row[0] ?? '');
                    $first_name = trim($row[1] ?? '');
                    $middle_name = trim($row[2] ?? '');
                    $section_code = trim($row[3] ?? '');
                    $student_id = trim($row[4] ?? '');
                    
                    if (empty($last_name) || empty($first_name) || empty($section_code)) {
                        $errors[] = "Row " . ($rowIndex + 2) . ": Missing required data";
                        continue;
                    }
                    
                    if (empty($student_id)) {
                        $student_id = $this->generateStudentId($last_name, $first_name);
                    }
                    
                    $full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
                    $section_id = $this->getOrCreateSection($section_code);
                    
                    if ($section_id) {
                        // PostgreSQL UPSERT using ON CONFLICT
                        $stmt = $this->pdo->prepare("
                            INSERT INTO students (student_id, last_name, first_name, middle_name, full_name, section_id) 
                            VALUES (?, ?, ?, ?, ?, ?)
                            ON CONFLICT (student_id) DO UPDATE SET 
                                last_name = EXCLUDED.last_name,
                                first_name = EXCLUDED.first_name,
                                middle_name = EXCLUDED.middle_name,
                                full_name = EXCLUDED.full_name,
                                section_id = EXCLUDED.section_id
                        ");
                        
                        if ($stmt->execute([$student_id, $last_name, $first_name, $middle_name, $full_name, $section_id])) {
                            $synced_count++;
                            $this->createStudentUserAccount($student_id, $full_name, $last_name, $first_name);
                        } else {
                            $errors[] = "Row " . ($rowIndex + 2) . ": Failed to save student data";
                        }
                    }
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
     * Sync teachers from Google Sheets - PostgreSQL compatible
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
            
            if (!empty($values) && $this->isHeaderRow($values[0], 'teacher')) {
                array_shift($values);
            }
            
            // Clear existing teachers
            $this->pdo->exec("DELETE FROM teachers");
            
            $synced_count = 0;
            $errors = [];
            
            foreach ($values as $rowIndex => $row) {
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
                    
                    if (!in_array($department, ['SHS', 'COLLEGE', 'BOTH'])) {
                        $errors[] = "Row " . ($rowIndex + 2) . ": Invalid department '$department'";
                        continue;
                    }
                    
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
                        $stmt = $this->pdo->prepare("INSERT INTO teachers (name, department, subject) VALUES (?, ?, ?)");
                        if ($stmt->execute([$name, $department, $subject])) {
                            $synced_count++;
                        }
                    }
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
    
    private function getOrCreateSection($section_code) {
        // Check if section exists
        $stmt = $this->pdo->prepare("SELECT id FROM sections WHERE section_code = ?");
        $stmt->execute([$section_code]);
        $section_id = $stmt->fetchColumn();
        
        if ($section_id) {
            return $section_id;
        }
        
        // Create new section
        $program = $this->determineProgramFromSectionCode($section_code);
        $year_level = $this->determineYearLevelFromSectionCode($section_code);
        $section_name = $this->generateSectionName($section_code, $program, $year_level);
        
        $stmt = $this->pdo->prepare("INSERT INTO sections (section_code, section_name, program, year_level) VALUES (?, ?, ?, ?) RETURNING id");
        $stmt->execute([$section_code, $section_name, $program, $year_level]);
        return $stmt->fetchColumn();
    }
    
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
    
    private function generateSectionName($section_code, $program, $year_level) {
        return $section_code . ' - ' . $program . ' ' . $year_level;
    }
    
    private function generateStudentId($last_name, $first_name) {
        $year = date('Y');
        
        // Get count using PostgreSQL syntax
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id LIKE ?");
        $stmt->execute([$year . '%']);
        $count = $stmt->fetchColumn() + 1;
        
        return $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
    
    private function createStudentUserAccount($student_id, $full_name, $last_name, $first_name) {
        $clean_lastname = preg_replace('/[^a-zA-Z0-9]/', '', $last_name);
        $clean_firstname = preg_replace('/[^a-zA-Z0-9]/', '', $first_name);
        $base_username = strtolower($clean_lastname . substr($clean_firstname, 0, 1));
        
        $username = $base_username;
        $counter = 1;
        while ($this->usernameExists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }
        
        // Check if user already exists using PostgreSQL JOIN
        $check_stmt = $this->pdo->prepare("
            SELECT u.id FROM users u 
            JOIN students s ON u.student_table_id = s.id 
            WHERE s.student_id = ?
        ");
        $check_stmt->execute([$student_id]);
        
        if (!$check_stmt->fetchColumn()) {
            $default_password = password_hash('pass123', PASSWORD_DEFAULT);
            
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
    
    private function usernameExists($username) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetchColumn() > 0;
    }
    
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
