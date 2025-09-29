<?php
// google_drive_reports.php - Complete implementation for generating reports to Google Drive with auto-sharing

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
     * Generate all evaluation reports and save to Google Drive - UPDATED for hybrid system
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
                'errors' => [],
                'shared_with' => [],
                'folder_url' => null,
                'message' => 'Report generation completed'
            ];
            
            // Check if we have any evaluations
            $evaluationCount = $this->getEvaluationCount();
            
            if ($evaluationCount == 0) {
                return [
                    'success' => true,
                    'message' => 'No evaluations found to generate reports',
                    'details' => 'The system is ready but no evaluations have been submitted yet'
                ];
            }
            
            // Create main reports folder
            $mainFolder = $this->createReportsFolder();
            $results['folders_created']++;
            
            // Get folder URL for the response
            $folder = $this->driveService->files->get($mainFolder, ['fields' => 'webViewLink']);
            $results['folder_url'] = $folder->getWebViewLink();
            
            // 🔥 AUTO-SHARE WITH YOUR EMAIL - Replace with your actual email
            $yourEmail = 'your-actual-email@gmail.com'; // ← CHANGE THIS TO YOUR REAL EMAIL
            if ($this->shareFolderWithEmail($mainFolder, $yourEmail)) {
                $results['shared_with'][] = $yourEmail;
            }
            
            // Get unique teachers from evaluations (since we don't have separate teachers table)
            $teachersFromEvaluations = $this->getTeachersFromEvaluations();
            
            if (empty($teachersFromEvaluations)) {
                return [
                    'success' => true,
                    'message' => 'No teacher evaluation data found',
                    'folder_url' => $results['folder_url'],
                    'shared_with' => $results['shared_with']
                ];
            }
            
            foreach ($teachersFromEvaluations as $teacherName => $teacherData) {
                try {
                    // Create teacher folder
                    $teacherFolder = $this->createSubFolder($mainFolder, $this->sanitizeFileName($teacherName));
                    $results['folders_created']++;
                    
                    // Generate summary report for this teacher
                    $summaryReport = $this->generateTeacherSummaryReport($teacherName, $teacherData, $teacherFolder);
                    
                    if ($summaryReport) {
                        $results['summary_reports']++;
                        $results['files_uploaded']++;
                        $results['teachers_processed']++;
                        
                        $results['reports_created'][] = [
                            'teacher' => $teacherName,
                            'summary_report' => $summaryReport,
                            'evaluations_count' => $teacherData['total_evaluations']
                        ];
                    }
                    
                } catch (Exception $e) {
                    $results['errors'][] = "Error processing teacher {$teacherName}: " . $e->getMessage();
                }
            }
            
            // Generate overall system summary
            $systemSummary = $this->generateSystemSummaryReport($mainFolder);
            if ($systemSummary) {
                $results['summary_reports']++;
                $results['files_uploaded']++;
            }
            
            $results['message'] = "Successfully generated reports for {$results['teachers_processed']} teachers";
            
            return $results;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Share folder with specific email addresses and get shareable link
     */
    private function shareFolderWithEmail($folderId, $email) {
        try {
            // Create permission for specific user
            $userPermission = new Drive\Permission([
                'type' => 'user',
                'role' => 'writer',
                'emailAddress' => $email
            ]);
            
            $this->driveService->permissions->create($folderId, $userPermission);
            
            // Also make it accessible to anyone with the link
            $publicPermission = new Drive\Permission([
                'type' => 'anyone',
                'role' => 'writer'
            ]);
            
            $this->driveService->permissions->create($folderId, $publicPermission);
            
            return true;
        } catch (Exception $e) {
            error_log("Sharing failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get evaluation count from database
     */
    private function getEvaluationCount() {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM evaluations");
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get unique teachers from evaluations table (since we don't have separate teachers table)
     */
    private function getTeachersFromEvaluations() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    teacher_name,
                    program,
                    COUNT(*) as evaluation_count,
                    AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                         q2_1 + q2_2 + q2_3 + q2_4 + 
                         q3_1 + q3_2 + q3_3 + q3_4 + 
                         q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as avg_score
                FROM evaluations 
                GROUP BY teacher_name, program
                ORDER BY teacher_name, program
            ");
            
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $organized = [];
            
            foreach ($teachers as $teacher) {
                $name = $teacher['teacher_name'];
                if (!isset($organized[$name])) {
                    $organized[$name] = [
                        'name' => $name,
                        'total_evaluations' => 0,
                        'programs' => [],
                        'overall_avg' => 0
                    ];
                }
                
                $organized[$name]['programs'][$teacher['program']] = [
                    'evaluation_count' => $teacher['evaluation_count'],
                    'avg_score' => $teacher['avg_score']
                ];
                $organized[$name]['total_evaluations'] += $teacher['evaluation_count'];
            }
            
            return $organized;
            
        } catch (Exception $e) {
            error_log("Error getting teachers from evaluations: " . $e->getMessage());
            return [];
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
     * Generate teacher summary report
     */
    private function generateTeacherSummaryReport($teacherName, $teacherData, $parentFolderId) {
        try {
            $fileName = "Summary Report - " . $this->sanitizeFileName($teacherName);
            
            // Create spreadsheet
            $spreadsheet = new \Google\Service\Sheets\Spreadsheet([
                'properties' => ['title' => $fileName]
            ]);
            
            $createdSpreadsheet = $this->sheetsService->spreadsheets->create($spreadsheet);
            $spreadsheetId = $createdSpreadsheet->getSpreadsheetId();
            
            // Move to folder
            $this->moveFileToFolder($spreadsheetId, $parentFolderId);
            
            // Get detailed evaluation data for this teacher
            $detailedData = $this->getTeacherDetailedData($teacherName);
            
            // Populate the report
            $this->populateTeacherSummaryReport($spreadsheetId, $teacherName, $teacherData, $detailedData);
            
            return $spreadsheetId;
            
        } catch (Exception $e) {
            error_log("Error creating teacher summary: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get detailed evaluation data for a specific teacher
     */
    private function getTeacherDetailedData($teacherName) {
        try {
            // Get question averages
            $stmt = $this->pdo->prepare("
                SELECT 
                    AVG(q1_1) as q1_1_avg, AVG(q1_2) as q1_2_avg, AVG(q1_3) as q1_3_avg,
                    AVG(q1_4) as q1_4_avg, AVG(q1_5) as q1_5_avg, AVG(q1_6) as q1_6_avg,
                    AVG(q2_1) as q2_1_avg, AVG(q2_2) as q2_2_avg, AVG(q2_3) as q2_3_avg, AVG(q2_4) as q2_4_avg,
                    AVG(q3_1) as q3_1_avg, AVG(q3_2) as q3_2_avg, AVG(q3_3) as q3_3_avg, AVG(q3_4) as q3_4_avg,
                    AVG(q4_1) as q4_1_avg, AVG(q4_2) as q4_2_avg, AVG(q4_3) as q4_3_avg,
                    AVG(q4_4) as q4_4_avg, AVG(q4_5) as q4_5_avg, AVG(q4_6) as q4_6_avg,
                    COUNT(*) as total_evaluations,
                    AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                         q2_1 + q2_2 + q2_3 + q2_4 + 
                         q3_1 + q3_2 + q3_3 + q3_4 + 
                         q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as overall_avg
                FROM evaluations 
                WHERE teacher_name = ?
            ");
            $stmt->execute([$teacherName]);
            $averages = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get comments analysis
            $commentsAnalysis = $this->getCommentsAnalysis($teacherName);
            
            // Get program distribution
            $stmt = $this->pdo->prepare("
                SELECT program, COUNT(*) as count 
                FROM evaluations 
                WHERE teacher_name = ? 
                GROUP BY program
            ");
            $stmt->execute([$teacherName]);
            $programDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'averages' => $averages,
                'comments_analysis' => $commentsAnalysis,
                'program_distribution' => $programDistribution
            ];
            
        } catch (Exception $e) {
            error_log("Error getting teacher detailed data: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Analyze comments for a teacher
     */
    private function getCommentsAnalysis($teacherName) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT comments 
                FROM evaluations 
                WHERE teacher_name = ? AND comments IS NOT NULL AND comments != ''
            ");
            $stmt->execute([$teacherName]);
            $comments = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            return $this->categorizeComments($comments);
            
        } catch (Exception $e) {
            return ['total' => 0, 'positive' => 0, 'negative' => 0, 'neutral' => 0];
        }
    }
    
    /**
     * Categorize comments as positive, negative, or neutral
     */
    private function categorizeComments($comments) {
        $positiveKeywords = ['good', 'excellent', 'great', 'amazing', 'wonderful', 'fantastic', 'helpful', 'clear', 'understandable', 'patient', 'kind', 'respectful', 'magaling', 'galing', 'okay', 'mabait'];
        $negativeKeywords = ['bad', 'terrible', 'awful', 'poor', 'worst', 'horrible', 'confusing', 'unclear', 'rude', 'impatient', 'boring', 'late', 'hindi', 'wala', 'kulang', 'masama'];
        
        $analysis = [
            'total' => count($comments),
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0,
            'sample_comments' => array_slice($comments, 0, 5) // Show first 5 comments as samples
        ];
        
        foreach ($comments as $comment) {
            $commentLower = strtolower($comment);
            $isPositive = false;
            $isNegative = false;
            
            foreach ($positiveKeywords as $keyword) {
                if (strpos($commentLower, $keyword) !== false) {
                    $analysis['positive']++;
                    $isPositive = true;
                    break;
                }
            }
            
            if (!$isPositive) {
                foreach ($negativeKeywords as $keyword) {
                    if (strpos($commentLower, $keyword) !== false) {
                        $analysis['negative']++;
                        $isNegative = true;
                        break;
                    }
                }
            }
            
            if (!$isPositive && !$isNegative) {
                $analysis['neutral']++;
            }
        }
        
        return $analysis;
    }
    
    /**
     * Populate teacher summary report with data
     */
    private function populateTeacherSummaryReport($spreadsheetId, $teacherName, $teacherData, $detailedData) {
        $reportData = [
            ['TEACHER EVALUATION SUMMARY REPORT'],
            [''],
            ['Teacher Name:', $teacherName],
            ['Total Evaluations:', $teacherData['total_evaluations']],
            ['Generated on:', date('F j, Y g:i A')],
            [''],
            ['OVERALL PERFORMANCE'],
            ['Overall Average Score:', number_format($detailedData['averages']['overall_avg'] ?? 0, 2) . '%'],
            ['Performance Rating:', $this->getPerformanceRating($detailedData['averages']['overall_avg'] ?? 0)],
            [''],
            ['PROGRAM DISTRIBUTION'],
            ['Program', 'Number of Evaluations', 'Average Score']
        ];
        
        // Add program distribution
        foreach ($teacherData['programs'] as $program => $programData) {
            $reportData[] = [
                $program, 
                $programData['evaluation_count'], 
                number_format($programData['avg_score'] ?? 0, 2) . '%'
            ];
        }
        
        $reportData[] = [''];
        $reportData[] = ['DETAILED QUESTION ANALYSIS'];
        $reportData[] = ['Question Category', 'Question', 'Average Score (1-5)'];
        
        // Add question averages
        $questions = [
            'Teaching Ability' => [
                'q1_1' => 'Nasuri at naipaliwanag ang aralin nang hindi binabasa ang aklat',
                'q1_2' => 'Gumagamit ng audio-visual at mga device',
                'q1_3' => 'Nagpapakita ng mga ideya/konsepto nang malinaw',
                'q1_4' => 'Hinahayaan ang mga mag-aaral na gumamit ng mga konsepto',
                'q1_5' => 'Nagiisiglab na patas na pagsusulit at pagsuuri',
                'q1_6' => 'Nagtuitos nang maayos sa pagtuturo'
            ],
            'Management Skills' => [
                'q2_1' => 'Pinapanatiling maayos, disiplinado at ligtas ang silid-aralan',
                'q2_2' => 'Sumusunod sa sistematikong iskwdyul',
                'q2_3' => 'Hinuhubog sa mga mag-aaral ang respeto',
                'q2_4' => 'Pinahihintulutan ang mga mag-aaral na ipahayag ang kanilang mga opinyon'
            ],
            'Guidance Skills' => [
                'q3_1' => 'Pagtanggap sa mga mag-aaral bilang indibidwal',
                'q3_2' => 'Pagpapakita ng tiwala at kaayusan sa sarili',
                'q3_3' => 'Pinangnangasiwaan ang problema ng klase',
                'q3_4' => 'Nagpapakita ng tunay na pagmamalasakit'
            ],
            'Personal & Social' => [
                'q4_1' => 'Nagpapanatili ng emosyonal na balanse',
                'q4_2' => 'Malaya sa nakasanayang galaw',
                'q4_3' => 'Maayos at presentable',
                'q4_4' => 'Hindi pagpapakita ng pavoritismo',
                'q4_5' => 'May magandang sense of humor',
                'q4_6' => 'May magandang diction, malinaw at maayos na timpla ng boses'
            ]
        ];
        
        foreach ($questions as $category => $categoryQuestions) {
            $reportData[] = [$category, '', ''];
            foreach ($categoryQuestions as $questionKey => $questionText) {
                $avg = $detailedData['averages'][$questionKey . '_avg'] ?? 0;
                $reportData[] = ['', $questionText, number_format($avg, 2)];
            }
            $reportData[] = ['', '', ''];
        }
        
        // Add comments analysis
        $comments = $detailedData['comments_analysis'];
        $reportData[] = ['STUDENT COMMENTS ANALYSIS'];
        $reportData[] = ['Comment Type', 'Count', 'Percentage'];
        $reportData[] = ['Positive Comments', $comments['positive'], number_format(($comments['positive']/$comments['total'])*100, 1) . '%'];
        $reportData[] = ['Negative Comments', $comments['negative'], number_format(($comments['negative']/$comments['total'])*100, 1) . '%'];
        $reportData[] = ['Neutral Comments', $comments['neutral'], number_format(($comments['neutral']/$comments['total'])*100, 1) . '%'];
        $reportData[] = ['Total Comments', $comments['total'], '100%'];
        
        if (!empty($comments['sample_comments'])) {
            $reportData[] = [''];
            $reportData[] = ['SAMPLE COMMENTS'];
            foreach ($comments['sample_comments'] as $comment) {
                $reportData[] = [substr($comment, 0, 100) . (strlen($comment) > 100 ? '...' : '')];
            }
        }
        
        // Write data to spreadsheet
        $range = 'Sheet1!A1:C' . count($reportData);
        $body = new \Google\Service\Sheets\ValueRange([
            'values' => $reportData
        ]);
        
        $this->sheetsService->spreadsheets_values->update(
            $spreadsheetId, $range, $body, ['valueInputOption' => 'RAW']
        );
    }
    
    /**
     * Generate system-wide summary report
     */
    private function generateSystemSummaryReport($parentFolderId) {
        try {
            $fileName = "System Summary Report - " . date('Y-m-d');
            
            $spreadsheet = new \Google\Service\Sheets\Spreadsheet([
                'properties' => ['title' => $fileName]
            ]);
            
            $createdSpreadsheet = $this->sheetsService->spreadsheets->create($spreadsheet);
            $spreadsheetId = $createdSpreadsheet->getSpreadsheetId();
            
            $this->moveFileToFolder($spreadsheetId, $parentFolderId);
            
            // Get system statistics
            $systemStats = $this->getSystemStatistics();
            
            // Populate system summary
            $this->populateSystemSummaryReport($spreadsheetId, $systemStats);
            
            return $spreadsheetId;
            
        } catch (Exception $e) {
            error_log("Error creating system summary: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get system-wide statistics
     */
    private function getSystemStatistics() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_evaluations,
                    COUNT(DISTINCT teacher_name) as unique_teachers,
                    COUNT(DISTINCT program) as unique_programs,
                    AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                         q2_1 + q2_2 + q2_3 + q2_4 + 
                         q3_1 + q3_2 + q3_3 + q3_4 + 
                         q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as system_avg
                FROM evaluations
            ");
            $overall = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get program statistics
            $stmt = $this->pdo->query("
                SELECT 
                    program,
                    COUNT(*) as evaluation_count,
                    AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                         q2_1 + q2_2 + q2_3 + q2_4 + 
                         q3_1 + q3_2 + q3_3 + q3_4 + 
                         q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as avg_score
                FROM evaluations 
                GROUP BY program
                ORDER BY program
            ");
            $programStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'overall' => $overall,
                'program_stats' => $programStats
            ];
            
        } catch (Exception $e) {
            return ['overall' => [], 'program_stats' => []];
        }
    }
    
    /**
     * Populate system summary report
     */
    private function populateSystemSummaryReport($spreadsheetId, $systemStats) {
        $reportData = [
            ['TEACHER EVALUATION SYSTEM - OVERALL SUMMARY'],
            ['Generated on: ' . date('F j, Y g:i A')],
            [''],
            ['SYSTEM OVERVIEW'],
            ['Total Evaluations:', $systemStats['overall']['total_evaluations'] ?? 0],
            ['Unique Teachers:', $systemStats['overall']['unique_teachers'] ?? 0],
            ['Programs Covered:', $systemStats['overall']['unique_programs'] ?? 0],
            ['System Average Score:', number_format($systemStats['overall']['system_avg'] ?? 0, 2) . '%'],
            ['Overall Performance Rating:', $this->getPerformanceRating($systemStats['overall']['system_avg'] ?? 0)],
            [''],
            ['PROGRAM PERFORMANCE'],
            ['Program', 'Evaluations', 'Average Score', 'Performance Rating']
        ];
        
        foreach ($systemStats['program_stats'] as $program) {
            $reportData[] = [
                $program['program'],
                $program['evaluation_count'],
                number_format($program['avg_score'], 2) . '%',
                $this->getPerformanceRating($program['avg_score'])
            ];
        }
        
        $range = 'Sheet1!A1:D' . count($reportData);
        $body = new \Google\Service\Sheets\ValueRange([
            'values' => $reportData
        ]);
        
        $this->sheetsService->spreadsheets_values->update(
            $spreadsheetId, $range, $body, ['valueInputOption' => 'RAW']
        );
    }
    
    // Helper methods
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
        try {
            $file = $this->driveService->files->get($fileId, ['fields' => 'parents']);
            $previousParents = join(',', $file->parents);
            
            $this->driveService->files->update($fileId, new Drive\DriveFile(), [
                'addParents' => $folderId,
                'removeParents' => $previousParents,
                'fields' => 'id, parents'
            ]);
        } catch (Exception $e) {
            error_log("Error moving file to folder: " . $e->getMessage());
        }
    }
}

// Function to be called from your API - UPDATED to accept $pdo parameter
function generateReportsToGoogleDrive($pdo = null) {
    try {
        // If no PDO connection provided, get one
        if (!$pdo) {
            require_once 'includes/db_connection.php';
            $pdo = getPDO();
        }
        
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
