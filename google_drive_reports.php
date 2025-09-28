<?php
// google_drive_reports.php - Complete implementation for generating reports to Google Drive

require_once 'vendor/autoload.php';
require_once 'includes/db_connection.php';

use Google\Client;
use Google\Service\Drive;
use Google\Service\Sheets;

class GoogleDriveReportsGenerator {
    private $client;
    private $driveService;
    private $sheetsService;
    private $pdo;
    private $reportsFolderId = null;
    
    public function __construct($pdo, $credentialsPath = null) {
        $this->pdo = $pdo;
        $this->initializeGoogleServices($credentialsPath);
    }
    
    private function initializeGoogleServices($credentialsPath = null) {
        // Get credentials from environment if not provided
        if (!$credentialsPath) {
            $credentialsJson = getenv('GOOGLE_CREDENTIALS_JSON');
            if (!$credentialsJson) {
                throw new Exception('Google credentials not found in environment variables');
            }
            
            $credentialsPath = sys_get_temp_dir() . '/google_credentials_' . uniqid() . '.json';
            file_put_contents($credentialsPath, $credentialsJson);
        }
        
        $this->client = new Client();
        $this->client->setAuthConfig($credentialsPath);
        $this->client->addScope([
            Drive::DRIVE_FILE,
            Sheets::SPREADSHEETS
        ]);
        $this->client->setAccessType('offline');
        
        $this->driveService = new Drive($this->client);
        $this->sheetsService = new Sheets($this->client);
    }
    
    /**
     * Generate all evaluation reports and save to Google Drive
     */
    public function generateAllReports() {
        try {
            $results = [
                'success' => true,
                'teachers_processed' => 0,
                'individual_reports' => 0,
                'summary_reports' => 0,
                'folders_created' => 0,
                'files_uploaded' => 0,
                'reports_created' => [],
                'errors' => []
            ];
            
            // Create main reports folder
            $mainFolder = $this->createReportsFolder();
            $results['folders_created']++;
            
            // Create "Reports" subfolder
            $reportsFolder = $this->createSubFolder($mainFolder, 'Reports');
            $results['folders_created']++;
            
            // Get all unique teachers (by name) with their departments
            $teachersByName = $this->getUniqueTeachersWithEvaluations();
            
            foreach ($teachersByName as $teacherName => $teacherData) {
                try {
                    // Create teacher-specific folder
                    $teacherFolder = $this->createSubFolder($reportsFolder, $teacherName);
                    $results['folders_created']++;
                    
                    $teacherReports = [
                        'teacher' => $teacherName,
                        'departments' => [],
                        'total_individual' => 0,
                        'total_summaries' => 0
                    ];
                    
                    // Process each department this teacher works in
                    foreach ($teacherData['departments'] as $department => $departmentData) {
                        // Create department folder (SHS or COLLEGE)
                        $departmentFolder = $this->createSubFolder($teacherFolder, $department);
                        $results['folders_created']++;
                        
                        // Generate individual reports for this department
                        $individualReports = $this->generateIndividualReportsByDepartment(
                            $departmentData, 
                            $department,
                            $departmentFolder
                        );
                        
                        $individualCount = count($individualReports);
                        $results['individual_reports'] += $individualCount;
                        $results['files_uploaded'] += $individualCount;
                        $teacherReports['total_individual'] += $individualCount;
                        
                        // Generate department summary report
                        $summaryReport = $this->generateDepartmentSummaryReport(
                            $teacherName,
                            $department,
                            $departmentData,
                            $departmentFolder
                        );
                        
                        if ($summaryReport) {
                            $results['summary_reports']++;
                            $results['files_uploaded']++;
                            $teacherReports['total_summaries']++;
                        }
                        
                        $teacherReports['departments'][] = [
                            'department' => $department,
                            'individual_count' => $individualCount,
                            'has_summary' => (bool)$summaryReport
                        ];
                    }
                    
                    $results['teachers_processed']++;
                    $results['reports_created'][] = $teacherReports;
                    
                } catch (Exception $e) {
                    $results['errors'][] = "Error processing teacher {$teacherName}: " . $e->getMessage();
                }
            }
            
            // Generate overall summary report
            $overallSummary = $this->generateOverallSummaryReport($mainFolder);
            if ($overallSummary) {
                $results['summary_reports']++;
                $results['files_uploaded']++;
            }
            
            $results['message'] = "Successfully generated reports for {$results['teachers_processed']} teachers across departments";
            
            return $results;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create main reports folder in Google Drive
     */
    private function createReportsFolder() {
        $folderName = 'Teacher Evaluation Reports - ' . date('Y-m-d H-i-s');
        
        $folderMetadata = new Drive\DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder'
        ]);
        
        $folder = $this->driveService->files->create($folderMetadata);
        $this->reportsFolderId = $folder->getId();
        
        return $folder->getId();
    }
    
    /**
     * Create subfolder in Google Drive
     */
    private function createSubFolder($parentFolderId, $folderName) {
        $folderMetadata = new Drive\DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentFolderId]
        ]);
        
        $folder = $this->driveService->files->create($folderMetadata);
        return $folder->getId();
    }
    
    /**
     * Get all unique teachers organized by name and department
     */
    private function getUniqueTeachersWithEvaluations() {
        $stmt = $this->pdo->query("
            SELECT t.id, t.name, t.department, t.subject,
                   COUNT(e.id) as evaluation_count
            FROM teachers t
            JOIN evaluations e ON t.id = e.teacher_id
            GROUP BY t.id, t.name, t.department, t.subject
            ORDER BY t.name, t.department
        ");
        
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $teachersByName = [];
        
        // Group teachers by name, then by department
        foreach ($teachers as $teacher) {
            $name = $teacher['name'];
            $department = $teacher['department'];
            
            if (!isset($teachersByName[$name])) {
                $teachersByName[$name] = [
                    'name' => $name,
                    'departments' => []
                ];
            }
            
            $teachersByName[$name]['departments'][$department] = [
                'id' => $teacher['id'],
                'name' => $teacher['name'],
                'department' => $teacher['department'],
                'subject' => $teacher['subject'],
                'evaluation_count' => $teacher['evaluation_count']
            ];
        }
        
        return $teachersByName;
    }
    
    /**
     * Generate individual evaluation reports for a specific department
     */
    private function generateIndividualReportsByDepartment($teacherData, $department, $departmentFolderId) {
        $reports = [];
        
        // Get all evaluations for this specific teacher-department combination
        $stmt = $this->pdo->prepare("
            SELECT e.*, s.full_name as student_name, s.student_id,
                   sec.section_code, sec.program
            FROM evaluations e
            JOIN students s ON e.student_id = s.id
            JOIN sections sec ON s.section_id = sec.id
            WHERE e.teacher_id = ? AND sec.program = ?
            ORDER BY s.full_name
        ");
        $stmt->execute([$teacherData['id'], $department]);
        $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($evaluations as $evaluation) {
            try {
                $spreadsheetId = $this->createIndividualReportSpreadsheet(
                    $evaluation, 
                    $teacherData, 
                    $departmentFolderId
                );
                
                if ($spreadsheetId) {
                    $reports[] = [
                        'student' => $evaluation['student_name'],
                        'department' => $department,
                        'spreadsheet_id' => $spreadsheetId
                    ];
                }
                
            } catch (Exception $e) {
                error_log("Error creating individual report: " . $e->getMessage());
            }
        }
        
        return $reports;
    }
    
    /**
     * Generate department-specific summary report
     */
    private function generateDepartmentSummaryReport($teacherName, $department, $teacherData, $departmentFolderId) {
        try {
            $fileName = "Summary Report FOR {$department} - " . $this->sanitizeFileName($teacherName);
            
            // Create spreadsheet
            $spreadsheet = new \Google\Service\Sheets\Spreadsheet([
                'properties' => ['title' => $fileName]
            ]);
            
            $createdSpreadsheet = $this->sheetsService->spreadsheets->create($spreadsheet);
            $spreadsheetId = $createdSpreadsheet->getSpreadsheetId();
            
            // Move to department folder
            $this->moveFileToFolder($spreadsheetId, $departmentFolderId);
            
            // Get teacher's evaluation statistics for this department
            $stats = $this->getDepartmentTeacherStatistics($teacherData['id'], $department);
            
            // Populate department summary report
            $this->populateDepartmentSummaryReport($spreadsheetId, $teacherName, $department, $teacherData, $stats);
            
            return $spreadsheetId;
            
        } catch (Exception $e) {
            error_log("Error creating department summary: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get teacher evaluation statistics for specific department
     */
    private function getDepartmentTeacherStatistics($teacherId, $department) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_evaluations,
                AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                     q2_1 + q2_2 + q2_3 + q2_4 + 
                     q3_1 + q3_2 + q3_3 + q3_4 + 
                     q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as avg_percentage,
                AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6) / 6) as avg_teaching,
                AVG((q2_1 + q2_2 + q2_3 + q2_4) / 4) as avg_management,
                AVG((q3_1 + q3_2 + q3_3 + q3_4) / 4) as avg_guidance,
                AVG((q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 6) as avg_personal,
                MIN((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                     q2_1 + q2_2 + q2_3 + q2_4 + 
                     q3_1 + q3_2 + q3_3 + q3_4 + 
                     q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as min_percentage,
                MAX((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                     q2_1 + q2_2 + q2_3 + q2_4 + 
                     q3_1 + q3_2 + q3_3 + q3_4 + 
                     q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as max_percentage
            FROM evaluations e
            JOIN students s ON e.student_id = s.id
            JOIN sections sec ON s.section_id = sec.id
            WHERE e.teacher_id = ? AND sec.program = ?
        ");
        $stmt->execute([$teacherId, $department]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Populate department-specific summary report with visual design
     */
    private function populateDepartmentSummaryReport($spreadsheetId, $teacherName, $department, $teacherData, $stats) {
        // Get detailed evaluation data for this teacher and department
        $evaluationData = $this->getDetailedEvaluationData($teacherData['id'], $department);
        $commentsData = $this->getCommentsAnalysis($teacherData['id'], $department);
        
        // Create the visual report structure
        $reportData = [
            // Header
            ['Philippine Technological Institute of Science Arts and Trade, Inc.'],
            [''],
            [strtoupper($department) . '-BRANCH (Evaluation Summary Report)'],
            [''],
            ['Name: ' . strtoupper($teacherName)],
            ['Subject Handled: ' . $teacherData['subject']],
            [''],
            ['FACULTY EVALUATION CRITERIA'],
            [strtoupper($department) . ' STUDENTS'],
            [''],
            
            // Section headers and data
            ['', 'CRITERIA', '', '', 'AVERAGE SCORE'],
            ['1', 'KAKAYAHAN SA PAGTUTURO', '', '', ''],
            ['1.1', 'Nasuri at naipaliwanag ang aralin nang hindi binabasa ang aklat sa klase', '', '', $evaluationData['q1_1_avg']],
            ['1.2', 'Gumagamit ng audio-visual at mga device upang suportahan at mapadali ang pagtuturo', '', '', $evaluationData['q1_2_avg']],
            ['1.3', 'Nagpapakita ng mga ideya/konsepto nang malinaw at nakakakumbinsi mula sa mga kalamay na larangan at isama ang subject matter sa aktwal na karanasan.', '', '', $evaluationData['q1_3_avg']],
            ['1.4', 'Hinahayaan ang mga mag-aaral na gumamit ng mga konsepto upang ipakita ang pag-unawa sa mga aralin.', '', '', $evaluationData['q1_4_avg']],
            ['1.5', 'Nagiisiglab na patas na pagsusulit at pagsuuri at ibalik ang mga resulta ng pagsusulit sa loob ng makatwirang panahon', '', '', $evaluationData['q1_5_avg']],
            ['1.6', 'Nagtuitos nang maayos sa pagtuturo gamit ang maayos na pananalita', '', '', $evaluationData['q1_6_avg']],
            [''],
            
            ['2', 'KASANAYAN SA PAMAMAHALA', '', '', ''],
            ['2.1', 'Pinapanatiling maayos, disiplinado at ligtas ang silid-aralan upang magkaroon ng maayos na pag-aaral.', '', '', $evaluationData['q2_1_avg']],
            ['2.2', 'Sumusunod sa sistematikong iskwdyul ng mga klase at iba pang pangaraw-araw na gawain.', '', '', $evaluationData['q2_2_avg']],
            ['2.3', 'Hinuhubog sa mga mag-aaral ang respeto at paggalang sa mga guro.', '', '', $evaluationData['q2_3_avg']],
            ['2.4', 'Pinahihintulutan ang mga mag-aaral na ipahayag ang kanilang mga opinyon at mga pananaw.', '', '', $evaluationData['q2_4_avg']],
            [''],
            
            ['3', 'MGA KASANAYAN SA PAGGABAY', '', '', ''],
            ['3.1', 'Pagtanggap sa mga mag-aaral bilang indibidwal na may kalakasan at kahinaan.', '', '', $evaluationData['q3_1_avg']],
            ['3.2', 'Pagpapakita ng tiwala at kaayusan sa sarili.', '', '', $evaluationData['q3_2_avg']],
            ['3.3', 'Pinangnangasiwaan ang problema ng klase at Mga mag-aaral nang may patas at pang-unawa', '', '', $evaluationData['q3_3_avg']],
            ['3.4', 'Nagpapakita ng tunay na pagmamalasakit sa mga personal at iba pang problemang ipinakita ng mga mag-aaral sa labas ng mga aktibidad sa silid-aralan.', '', '', $evaluationData['q3_4_avg']],
            [''],
            
            ['4', 'PERSONAL AT PANLIPUNANG KATANGIAN', '', '', ''],
            ['4.1', 'Nagpapanatili ng emosyonal na balanse; hindi masyadong kritikal o sobrang sensitibo', '', '', $evaluationData['q4_1_avg']],
            ['4.2', 'Malaya sa nakasanayang galaw na nakakagambala sa proseso ng pagtuturo at pagkatuto', '', '', $evaluationData['q4_2_avg']],
            ['4.3', 'Maayos at presentable; Malinis at maayos ang mga damit', '', '', $evaluationData['q4_3_avg']],
            ['4.4', 'Hindi pagpapakita ng paboritismo', '', '', $evaluationData['q4_4_avg']],
            ['4.5', 'May magandang sense of humor at nagpapakita ng sigla sa pagtuturo', '', '', $evaluationData['q4_5_avg']],
            ['4.6', 'May magandang diction, malinaw at maayos na timpla ng boses', '', '', $evaluationData['q4_6_avg']],
            [''],
            ['', '', '', 'TOTAL', $evaluationData['total_avg']],
            [''],
            
            // Comments Analysis Section
            ['5. KOMENTO SA GURO'],
            ['', 'Comment Category', '', '', 'Count'],
            ...($this->formatCommentsData($commentsData)),
            [''],
            
            // Summary Statistics
            ['EVALUATION SUMMARY'],
            ['Total Number of Evaluations:', $stats['total_evaluations']],
            ['Department:', $department],
            ['Overall Performance Rating:', $this->getPerformanceRating($stats['avg_percentage'])],
            ['Average Score (Percentage):', number_format($stats['avg_percentage'], 2) . '%'],
            ['Highest Individual Score:', number_format($stats['max_percentage'], 2) . '%'],
            ['Lowest Individual Score:', number_format($stats['min_percentage'], 2) . '%'],
            [''],
            
            // Signature Section
            ['Tabulated/Encoded By:'],
            [''],
            [''],
            ['_________________________'],
            ['System Administrator'],
            ['Guidance Associate'],
            [''],
            ['Generated on: ' . date('F j, Y g:i A')],
        ];
        
        // Write data to spreadsheet
        $range = 'Sheet1!A1:E' . count($reportData);
        $body = new \Google\Service\Sheets\ValueRange([
            'values' => $reportData
        ]);
        
        $this->sheetsService->spreadsheets_values->update(
            $spreadsheetId, $range, $body, ['valueInputOption' => 'RAW']
        );
        
        // Apply formatting to make it look professional
        $this->applyReportFormatting($spreadsheetId, count($reportData));
    }
    
    /**
     * Get detailed evaluation data with individual question averages
     */
    private function getDetailedEvaluationData($teacherId, $department) {
        $stmt = $this->pdo->prepare("
            SELECT 
                AVG(e.q1_1) as q1_1_avg, AVG(e.q1_2) as q1_2_avg, AVG(e.q1_3) as q1_3_avg,
                AVG(e.q1_4) as q1_4_avg, AVG(e.q1_5) as q1_5_avg, AVG(e.q1_6) as q1_6_avg,
                AVG(e.q2_1) as q2_1_avg, AVG(e.q2_2) as q2_2_avg, AVG(e.q2_3) as q2_3_avg, AVG(e.q2_4) as q2_4_avg,
                AVG(e.q3_1) as q3_1_avg, AVG(e.q3_2) as q3_2_avg, AVG(e.q3_3) as q3_3_avg, AVG(e.q3_4) as q3_4_avg,
                AVG(e.q4_1) as q4_1_avg, AVG(e.q4_2) as q4_2_avg, AVG(e.q4_3) as q4_3_avg,
                AVG(e.q4_4) as q4_4_avg, AVG(e.q4_5) as q4_5_avg, AVG(e.q4_6) as q4_6_avg,
                AVG((e.q1_1 + e.q1_2 + e.q1_3 + e.q1_4 + e.q1_5 + e.q1_6 + 
                     e.q2_1 + e.q2_2 + e.q2_3 + e.q2_4 + 
                     e.q3_1 + e.q3_2 + e.q3_3 + e.q3_4 + 
                     e.q4_1 + e.q4_2 + e.q4_3 + e.q4_4 + e.q4_5 + e.q4_6) / 4) as total_avg
            FROM evaluations e
            JOIN students s ON e.student_id = s.id
            JOIN sections sec ON s.section_id = sec.id
            WHERE e.teacher_id = ? AND sec.program = ?
        ");
        $stmt->execute([$teacherId, $department]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Format averages to 2 decimal places
        foreach ($data as $key => $value) {
            $data[$key] = number_format($value, 2);
        }
        
        return $data;
    }
    
    /**
     * Get comments analysis data
     */
    private function getCommentsAnalysis($teacherId, $department) {
        $stmt = $this->pdo->prepare("
            SELECT e.comments
            FROM evaluations e
            JOIN students s ON e.student_id = s.id
            JOIN sections sec ON s.section_id = sec.id
            WHERE e.teacher_id = ? AND sec.program = ? AND e.comments IS NOT NULL AND e.comments != ''
        ");
        $stmt->execute([$teacherId, $department]);
        $comments = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return $this->analyzeComments($comments);
    }
    
    /**
     * Analyze comments and categorize them
     */
    private function analyzeComments($comments) {
        $positiveKeywords = [
            'good', 'excellent', 'great', 'amazing', 'wonderful', 'fantastic', 'outstanding',
            'helpful', 'clear', 'understandable', 'patient', 'kind', 'respectful',
            'magaling', 'galing', 'okay', 'mabait', 'helpful', 'malinaw'
        ];
        
        $negativeKeywords = [
            'bad', 'terrible', 'awful', 'poor', 'worst', 'horrible', 'disappointing',
            'confusing', 'unclear', 'rude', 'impatient', 'boring', 'late',
            'hindi', 'wala', 'kulang', 'masama', 'pangit'
        ];
        
        $analysis = [
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0,
            'total' => count($comments),
            'sample_positive' => [],
            'sample_negative' => []
        ];
        
        foreach ($comments as $comment) {
            $comment_lower = strtolower($comment);
            $is_positive = false;
            $is_negative = false;
            
            foreach ($positiveKeywords as $keyword) {
                if (strpos($comment_lower, $keyword) !== false) {
                    $analysis['positive']++;
                    if (count($analysis['sample_positive']) < 5) {
                        $analysis['sample_positive'][] = $comment;
                    }
                    $is_positive = true;
                    break;
                }
            }
            
            if (!$is_positive) {
                foreach ($negativeKeywords as $keyword) {
                    if (strpos($comment_lower, $keyword) !== false) {
                        $analysis['negative']++;
                        if (count($analysis['sample_negative']) < 5) {
                            $analysis['sample_negative'][] = $comment;
                        }
                        $is_negative = true;
                        break;
                    }
                }
            }
            
            if (!$is_positive && !$is_negative) {
                $analysis['neutral']++;
            }
        }
        
        return $analysis;
    }
    
    /**
     * Format comments data for the report
     */
    private function formatCommentsData($commentsData) {
        $formatted = [
            ['', 'Positive Comments', '', '', $commentsData['positive']],
            ['', 'Negative Comments', '', '', $commentsData['negative']],
            ['', 'Neutral Comments', '', '', $commentsData['neutral']],
            ['', 'Total Comments', '', '', $commentsData['total']],
            ['']
        ];
        
        // Add sample positive comments
        if (!empty($commentsData['sample_positive'])) {
            $formatted[] = ['', 'Sample Positive Feedback:', '', '', ''];
            foreach (array_slice($commentsData['sample_positive'], 0, 3) as $index => $comment) {
                $formatted[] = ['', substr($comment, 0, 80) . (strlen($comment) > 80 ? '...' : ''), '', '', ''];
            }
            $formatted[] = [''];
        }
        
        // Add sample negative comments
        if (!empty($commentsData['sample_negative'])) {
            $formatted[] = ['', 'Sample Areas for Improvement:', '', '', ''];
            foreach (array_slice($commentsData['sample_negative'], 0, 3) as $index => $comment) {
                $formatted[] = ['', substr($comment, 0, 80) . (strlen($comment) > 80 ? '...' : ''), '', '', ''];
            }
            $formatted[] = [''];
        }
        
        return $formatted;
    }
    
    /**
     * Apply professional formatting to the report
     */
    private function applyReportFormatting($spreadsheetId, $totalRows) {
        try {
            $requests = [
                // Set column widths
                [
                    'updateDimensionProperties' => [
                        'range' => [
                            'sheetId' => 0,
                            'dimension' => 'COLUMNS',
                            'startIndex' => 0,
                            'endIndex' => 1
                        ],
                        'properties' => ['pixelSize' => 50],
                        'fields' => 'pixelSize'
                    ]
                ],
                [
                    'updateDimensionProperties' => [
                        'range' => [
                            'sheetId' => 0,
                            'dimension' => 'COLUMNS',
                            'startIndex' => 1,
                            'endIndex' => 4
                        ],
                        'properties' => ['pixelSize' => 400],
                        'fields' => 'pixelSize'
                    ]
                ],
                [
                    'updateDimensionProperties' => [
                        'range' => [
                            'sheetId' => 0,
                            'dimension' => 'COLUMNS',
                            'startIndex' => 4,
                            'endIndex' => 5
                        ],
                        'properties' => ['pixelSize' => 100],
                        'fields' => 'pixelSize'
                    ]
                ],
                
                // Bold headers
                [
                    'repeatCell' => [
                        'range' => [
                            'sheetId' => 0,
                            'startRowIndex' => 0,
                            'endRowIndex' => 1,
                            'startColumnIndex' => 0,
                            'endColumnIndex' => 5
                        ],
                        'cell' => [
                            'userEnteredFormat' => [
                                'textFormat' => ['bold' => true, 'fontSize' => 14],
                                'horizontalAlignment' => 'CENTER'
                            ]
                        ],
                        'fields' => 'userEnteredFormat(textFormat,horizontalAlignment)'
                    ]
                ],
                
                // Add borders to the main content area
                [
                    'updateBorders' => [
                        'range' => [
                            'sheetId' => 0,
                            'startRowIndex' => 10,
                            'endRowIndex' => $totalRows - 10,
                            'startColumnIndex' => 0,
                            'endColumnIndex' => 5
                        ],
                        'top' => ['style' => 'SOLID', 'width' => 1],
                        'bottom' => ['style' => 'SOLID', 'width' => 1],
                        'left' => ['style' => 'SOLID', 'width' => 1],
                        'right' => ['style' => 'SOLID', 'width' => 1],
                        'innerHorizontal' => ['style' => 'SOLID', 'width' => 1],
                        'innerVertical' => ['style' => 'SOLID', 'width' => 1]
                    ]
                ]
            ];
            
            $batchRequest = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                'requests' => $requests
            ]);
            
            $this->sheetsService->spreadsheets->batchUpdate($spreadsheetId, $batchRequest);
            
        } catch (Exception $e) {
            // Formatting failed, but report was still created
            error_log("Formatting error: " . $e->getMessage());
        }
    }
    
    /**
     * Create individual evaluation report spreadsheet
     */
    private function createIndividualReportSpreadsheet($evaluation, $teacher, $parentFolderId) {
        $fileName = "Individual Report - " . 
                   $this->sanitizeFileName($evaluation['student_name']) . " - " . 
                   $this->sanitizeFileName($teacher['name']);
        
        // Create new spreadsheet
        $spreadsheet = new \Google\Service\Sheets\Spreadsheet([
            'properties' => [
                'title' => $fileName
            ],
            'sheets' => [
                [
                    'properties' => [
                        'title' => 'Evaluation Report',
                        'gridProperties' => [
                            'rowCount' => 100,
                            'columnCount' => 10
                        ]
                    ]
                ]
            ]
        ]);
        
        $createdSpreadsheet = $this->sheetsService->spreadsheets->create($spreadsheet);
        $spreadsheetId = $createdSpreadsheet->getSpreadsheetId();
        
        // Move to correct folder
        $this->moveFileToFolder($spreadsheetId, $parentFolderId);
        
        // Populate with evaluation data
        $this->populateIndividualReport($spreadsheetId, $evaluation, $teacher);
        
        return $spreadsheetId;
    }
    
    /**
     * Populate individual report with evaluation data
     */
    private function populateIndividualReport($spreadsheetId, $evaluation, $teacher) {
        // Calculate scores and ratings
        $scores = $this->parseEvaluationScores($evaluation);
        $averageScore = $this->calculateAverageScore($scores);
        $rating = $this->getPerformanceRating($averageScore);
        
        // Prepare report data
        $reportData = [
            // Header information
            ['Teacher Evaluation Report'],
            [''],
            ['Teacher Name:', $teacher['name']],
            ['Department:', $teacher['department']],
            ['Subject:', $teacher['subject']],
            ['Student Name:', $evaluation['student_name']],
            ['Student ID:', $evaluation['student_id']],
            ['Section:', $evaluation['section_code']],
            ['Program:', $evaluation['program']],
            ['Date Evaluated:', $evaluation['created_at']],
            [''],
            
            // Evaluation scores
            ['EVALUATION SCORES'],
            ['Category', 'Score', 'Max Score', 'Percentage'],
            
            // Section 1: Teaching Ability
            ['Teaching Ability:', $scores['teaching_total'], $scores['teaching_max'], 
             number_format(($scores['teaching_total']/$scores['teaching_max'])*100, 1) . '%'],
            
            // Section 2: Management Skills
            ['Management Skills:', $scores['management_total'], $scores['management_max'], 
             number_format(($scores['management_total']/$scores['management_max'])*100, 1) . '%'],
            
            // Section 3: Guidance Skills
            ['Guidance Skills:', $scores['guidance_total'], $scores['guidance_max'], 
             number_format(($scores['guidance_total']/$scores['guidance_max'])*100, 1) . '%'],
            
            // Section 4: Personal & Social
            ['Personal & Social:', $scores['personal_total'], $scores['personal_max'], 
             number_format(($scores['personal_total']/$scores['personal_max'])*100, 1) . '%'],
            
            [''],
            ['OVERALL RESULTS'],
            ['Total Score:', $scores['total_score'], $scores['max_possible'], 
             number_format($averageScore, 1) . '%'],
            ['Performance Rating:', $rating],
            [''],
            
            // Comments
            ['STUDENT COMMENTS'],
            [$evaluation['comments'] ?: 'No comments provided'],
        ];
        
        // Add detailed breakdown
        $reportData = array_merge($reportData, [
            [''],
            ['DETAILED BREAKDOWN'],
            $this->getDetailedBreakdown($scores)
        ]);
        
        // Write data to spreadsheet
        $range = 'Evaluation Report!A1:Z' . count($reportData);
        $body = new \Google\Service\Sheets\ValueRange([
            'values' => $reportData
        ]);
        
        $params = ['valueInputOption' => 'RAW'];
        
        $this->sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            $range,
            $body,
            $params
        );
        
        // Apply formatting
        $this->formatIndividualReport($spreadsheetId);
    }
    
    /**
     * Generate teacher summary report
     */
    private function generateTeacherSummaryReport($teacher, $teacherFolderId) {
        try {
            $fileName = "Summary Report - " . $this->sanitizeFileName($teacher['name']);
            
            // Create spreadsheet
            $spreadsheet = new \Google\Service\Sheets\Spreadsheet([
                'properties' => ['title' => $fileName]
            ]);
            
            $createdSpreadsheet = $this->sheetsService->spreadsheets->create($spreadsheet);
            $spreadsheetId = $createdSpreadsheet->getSpreadsheetId();
            
            // Move to folder
            $this->moveFileToFolder($spreadsheetId, $teacherFolderId);
            
            // Get teacher's evaluation statistics
            $stats = $this->getTeacherStatistics($teacher['id']);
            
            // Populate summary report
            $this->populateTeacherSummaryReport($spreadsheetId, $teacher, $stats);
            
            return $spreadsheetId;
            
        } catch (Exception $e) {
            error_log("Error creating teacher summary: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get teacher evaluation statistics
     */
    private function getTeacherStatistics($teacherId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_evaluations,
                AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                     q2_1 + q2_2 + q2_3 + q2_4 + 
                     q3_1 + q3_2 + q3_3 + q3_4 + 
                     q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as avg_percentage,
                AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6) / 6) as avg_teaching,
                AVG((q2_1 + q2_2 + q2_3 + q2_4) / 4) as avg_management,
                AVG((q3_1 + q3_2 + q3_3 + q3_4) / 4) as avg_guidance,
                AVG((q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 6) as avg_personal,
                MIN((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                     q2_1 + q2_2 + q2_3 + q2_4 + 
                     q3_1 + q3_2 + q3_3 + q3_4 + 
                     q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as min_percentage,
                MAX((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                     q2_1 + q2_2 + q2_3 + q2_4 + 
                     q3_1 + q3_2 + q3_3 + q3_4 + 
                     q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as max_percentage
            FROM evaluations 
            WHERE teacher_id = ?
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate overall system summary report
     */
    private function generateOverallSummaryReport($parentFolderId) {
        try {
            $fileName = "Overall System Summary - " . date('Y-m-d');
            
            $spreadsheet = new \Google\Service\Sheets\Spreadsheet([
                'properties' => ['title' => $fileName]
            ]);
            
            $createdSpreadsheet = $this->sheetsService->spreadsheets->create($spreadsheet);
            $spreadsheetId = $createdSpreadsheet->getSpreadsheetId();
            
            $this->moveFileToFolder($spreadsheetId, $parentFolderId);
            
            // Get system-wide statistics
            $systemStats = $this->getSystemStatistics();
            
            // Populate overall summary
            $this->populateOverallSummaryReport($spreadsheetId, $systemStats);
            
            return $spreadsheetId;
            
        } catch (Exception $e) {
            error_log("Error creating overall summary: " . $e->getMessage());
            return null;
        }
    }
    
    // Helper methods
    private function parseEvaluationScores($evaluation) {
        return [
            'teaching_total' => $evaluation['q1_1'] + $evaluation['q1_2'] + $evaluation['q1_3'] + 
                              $evaluation['q1_4'] + $evaluation['q1_5'] + $evaluation['q1_6'],
            'teaching_max' => 30, // 6 questions × 5 points each
            
            'management_total' => $evaluation['q2_1'] + $evaluation['q2_2'] + 
                                 $evaluation['q2_3'] + $evaluation['q2_4'],
            'management_max' => 20, // 4 questions × 5 points each
            
            'guidance_total' => $evaluation['q3_1'] + $evaluation['q3_2'] + 
                               $evaluation['q3_3'] + $evaluation['q3_4'],
            'guidance_max' => 20, // 4 questions × 5 points each
            
            'personal_total' => $evaluation['q4_1'] + $evaluation['q4_2'] + $evaluation['q4_3'] + 
                               $evaluation['q4_4'] + $evaluation['q4_5'] + $evaluation['q4_6'],
            'personal_max' => 30, // 6 questions × 5 points each
            
            'total_score' => $evaluation['q1_1'] + $evaluation['q1_2'] + $evaluation['q1_3'] + 
                            $evaluation['q1_4'] + $evaluation['q1_5'] + $evaluation['q1_6'] +
                            $evaluation['q2_1'] + $evaluation['q2_2'] + $evaluation['q2_3'] + $evaluation['q2_4'] +
                            $evaluation['q3_1'] + $evaluation['q3_2'] + $evaluation['q3_3'] + $evaluation['q3_4'] +
                            $evaluation['q4_1'] + $evaluation['q4_2'] + $evaluation['q4_3'] + 
                            $evaluation['q4_4'] + $evaluation['q4_5'] + $evaluation['q4_6'],
            'max_possible' => 100 // 20 questions × 5 points each
        ];
    }
    
    private function calculateAverageScore($scores) {
        return ($scores['total_score'] / $scores['max_possible']) * 100;
    }
    
    private function getPerformanceRating($percentage) {
        if ($percentage >= 90) return 'Excellent';
        if ($percentage >= 80) return 'Very Good';
        if ($percentage >= 70) return 'Good';
        if ($percentage >= 60) return 'Satisfactory';
        return 'Needs Improvement';
    }
    
    private function sanitizeFileName($name) {
        return preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $name);
    }
    
    private function moveFileToFolder($fileId, $folderId) {
        $file = $this->driveService->files->get($fileId, ['fields' => 'parents']);
        $previousParents = join(',', $file->parents);
        
        $this->driveService->files->update($fileId, new Drive\DriveFile(), [
            'addParents' => $folderId,
            'removeParents' => $previousParents,
            'fields' => 'id, parents'
        ]);
    }
    
    private function formatIndividualReport($spreadsheetId) {
        // Add basic formatting like bold headers, borders, etc.
        // This would involve using the Sheets API formatting requests
        // Implementation depends on your specific formatting needs
    }
    
    private function getDetailedBreakdown($scores) {
        return [
            'Teaching Ability Questions:',
            'Question 1: ' . ($scores['q1_1'] ?? 'N/A'),
            'Question 2: ' . ($scores['q1_2'] ?? 'N/A'),
            // Add all questions breakdown
        ];
    }
    
    private function populateTeacherSummaryReport($spreadsheetId, $teacher, $stats) {
        $summaryData = [
            ['Teacher Summary Report'],
            [''],
            ['Teacher:', $teacher['name']],
            ['Department:', $teacher['department']],
            ['Subject:', $teacher['subject']],
            [''],
            ['EVALUATION STATISTICS'],
            ['Total Evaluations:', $stats['total_evaluations']],
            ['Average Score:', number_format($stats['avg_percentage'], 1) . '%'],
            ['Performance Rating:', $this->getPerformanceRating($stats['avg_percentage'])],
            [''],
            ['CATEGORY AVERAGES'],
            ['Teaching Ability:', number_format($stats['avg_teaching'], 1) . '/5.0'],
            ['Management Skills:', number_format($stats['avg_management'], 1) . '/5.0'],
            ['Guidance Skills:', number_format($stats['avg_guidance'], 1) . '/5.0'],
            ['Personal & Social:', number_format($stats['avg_personal'], 1) . '/5.0'],
            [''],
            ['SCORE RANGE'],
            ['Highest Score:', number_format($stats['max_percentage'], 1) . '%'],
            ['Lowest Score:', number_format($stats['min_percentage'], 1) . '%'],
        ];
        
        $range = 'Sheet1!A1:B' . count($summaryData);
        $body = new \Google\Service\Sheets\ValueRange(['values' => $summaryData]);
        
        $this->sheetsService->spreadsheets_values->update(
            $spreadsheetId, $range, $body, ['valueInputOption' => 'RAW']
        );
    }
    
    private function getSystemStatistics() {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(DISTINCT teacher_id) as total_teachers_evaluated,
                COUNT(*) as total_evaluations,
                AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                     q2_1 + q2_2 + q2_3 + q2_4 + 
                     q3_1 + q3_2 + q3_3 + q3_4 + 
                     q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as system_avg
            FROM evaluations
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function populateOverallSummaryReport($spreadsheetId, $stats) {
        $summaryData = [
            ['Teacher Evaluation System - Overall Summary'],
            ['Generated on: ' . date('Y-m-d H:i:s')],
            [''],
            ['SYSTEM STATISTICS'],
            ['Total Teachers Evaluated:', $stats['total_teachers_evaluated']],
            ['Total Evaluations:', $stats['total_evaluations']],
            ['System Average Score:', number_format($stats['system_avg'], 1) . '%'],
            ['Overall Performance Rating:', $this->getPerformanceRating($stats['system_avg'])],
            // Add more detailed statistics as needed
        ];
        
        $range = 'Sheet1!A1:B' . count($summaryData);
        $body = new \Google\Service\Sheets\ValueRange(['values' => $summaryData]);
        
        $this->sheetsService->spreadsheets_values->update(
            $spreadsheetId, $range, $body, ['valueInputOption' => 'RAW']
        );
    }
}

// Function to be called from your existing API
function generateReportsToGoogleDrive() {
    try {
        require_once 'includes/db_connection.php';
        
        $generator = new GoogleDriveReportsGenerator($pdo);
        $result = $generator->generateAllReports();
        
        return $result;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>
