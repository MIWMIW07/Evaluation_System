<?php
// google_drive_reports.php - Fixed version with better error handling and progress tracking

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
                'errors' => [],
                'shared_with' => [],
                'folder_url' => null,
                'message' => 'Report generation completed'
            ];
            
            error_log("Starting report generation...");
            
            // Check if we have any evaluations
            $evaluationCount = $this->getEvaluationCount();
            error_log("Total evaluations found: " . $evaluationCount);
            
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
            error_log("Main folder created: " . $mainFolder);
            
            // Get folder URL for the response
            $folder = $this->driveService->files->get($mainFolder, ['fields' => 'webViewLink']);
            $results['folder_url'] = $folder->getWebViewLink();
            
            // AUTO-SHARE WITH YOUR EMAIL - Replace with your actual email
            $yourEmail = 'learsi.gabriel07@gmail.com'; // ← CHANGE THIS TO YOUR REAL EMAIL
            if ($this->shareFolderWithEmail($mainFolder, $yourEmail)) {
                $results['shared_with'][] = $yourEmail;
                error_log("Folder shared with: " . $yourEmail);
            }
            
            // Create "Reports" subfolder
            $reportsFolder = $this->createSubFolder($mainFolder, 'Reports');
            $results['folders_created']++;
            error_log("Reports subfolder created: " . $reportsFolder);
            
            // Get all evaluations organized by teacher and program
            $evaluationsByTeacher = $this->getEvaluationsByTeacherAndProgram();
            error_log("Teachers found: " . count($evaluationsByTeacher));
            
            if (empty($evaluationsByTeacher)) {
                return [
                    'success' => true,
                    'message' => 'No teacher evaluation data found',
                    'folder_url' => $results['folder_url'],
                    'shared_with' => $results['shared_with']
                ];
            }
            
            // Process each teacher
            foreach ($evaluationsByTeacher as $teacherName => $teacherData) {
                try {
                    error_log("Processing teacher: " . $teacherName);
                    
                    // Create teacher folder
                    $teacherFolder = $this->createSubFolder($reportsFolder, $this->sanitizeFileName($teacherName));
                    $results['folders_created']++;
                    error_log("Teacher folder created: " . $teacherName);
                    
                    $teacherResults = [
                        'teacher' => $teacherName,
                        'programs_processed' => 0,
                        'individual_reports' => 0,
                        'summary_reports' => 0,
                        'program_details' => []
                    ];
                    
                    // Process each program for this teacher
                    foreach ($teacherData['programs'] as $program => $programEvaluations) {
                        error_log("Processing program: " . $program . " for teacher " . $teacherName . " (" . count($programEvaluations) . " evaluations)");
                        
                        // Create program folder (SHS or COLLEGE)
                        $programFolder = $this->createSubFolder($teacherFolder, $program);
                        $results['folders_created']++;
                        error_log("Program folder created: " . $program);
                        
                        // Generate individual reports for each student in this program
                        $individualCount = $this->generateIndividualReports($programEvaluations, $teacherName, $program, $programFolder);
                        $results['individual_reports'] += $individualCount;
                        $results['files_uploaded'] += $individualCount;
                        $teacherResults['individual_reports'] += $individualCount;
                        error_log("Generated " . $individualCount . " individual reports for " . $program);
                        
                        // Generate summary report for this teacher-program combination
                        $summaryReport = $this->generateProgramSummaryReport($teacherName, $program, $programEvaluations, $programFolder);
                        if ($summaryReport) {
                            $results['summary_reports']++;
                            $results['files_uploaded']++;
                            $teacherResults['summary_reports']++;
                            error_log("Generated summary report for " . $program);
                        }
                        
                        $teacherResults['programs_processed']++;
                        $teacherResults['program_details'][] = [
                            'program' => $program,
                            'evaluations' => count($programEvaluations),
                            'individual_reports' => $individualCount,
                            'summary_report' => $summaryReport ? 'Yes' : 'No'
                        ];
                    }
                    
                    $results['teachers_processed']++;
                    $results['reports_created'][] = $teacherResults;
                    error_log("Completed processing teacher: " . $teacherName);
                    
                } catch (Exception $e) {
                    $errorMsg = "Error processing teacher {$teacherName}: " . $e->getMessage();
                    $results['errors'][] = $errorMsg;
                    error_log($errorMsg);
                }
            }
            
            // Generate overall system summary
            $systemSummary = $this->generateSystemSummaryReport($mainFolder);
            if ($systemSummary) {
                $results['summary_reports']++;
                $results['files_uploaded']++;
                error_log("Generated system summary report");
            }
            
            $results['message'] = "Successfully generated reports for {$results['teachers_processed']} teachers";
            error_log("Report generation completed: " . json_encode($results));
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Fatal error in generateAllReports: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get evaluations organized by teacher and program
     */
    private function getEvaluationsByTeacherAndProgram() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    teacher_name,
                    program,
                    student_username,
                    student_name,
                    section,
                    q1_1, q1_2, q1_3, q1_4, q1_5, q1_6,
                    q2_1, q2_2, q2_3, q2_4,
                    q3_1, q3_2, q3_3, q3_4,
                    q4_1, q4_2, q4_3, q4_4, q4_5, q4_6,
                    comments,
                    submitted_at
                FROM evaluations 
                ORDER BY teacher_name, program, student_name
            ");
            
            $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $organized = [];
            
            foreach ($evaluations as $evaluation) {
                $teacherName = $evaluation['teacher_name'];
                $program = $evaluation['program'];
                
                if (!isset($organized[$teacherName])) {
                    $organized[$teacherName] = [
                        'name' => $teacherName,
                        'programs' => []
                    ];
                }
                
                if (!isset($organized[$teacherName]['programs'][$program])) {
                    $organized[$teacherName]['programs'][$program] = [];
                }
                
                $organized[$teacherName]['programs'][$program][] = $evaluation;
            }
            
            return $organized;
            
        } catch (Exception $e) {
            error_log("Error getting evaluations by teacher: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate individual student reports for a program
     */
    private function generateIndividualReports($evaluations, $teacherName, $program, $parentFolderId) {
        $generatedCount = 0;
        
        foreach ($evaluations as $evaluation) {
            try {
                $fileName = "Individual Report - " . 
                           $this->sanitizeFileName($evaluation['student_name']) . " - " . 
                           $this->sanitizeFileName($teacherName);
                
                // Create spreadsheet
                $spreadsheet = new \Google\Service\Sheets\Spreadsheet([
                    'properties' => ['title' => $fileName]
                ]);
                
                $createdSpreadsheet = $this->sheetsService->spreadsheets->create($spreadsheet);
                $spreadsheetId = $createdSpreadsheet->getSpreadsheetId();
                
                // Move to folder
                $this->moveFileToFolder($spreadsheetId, $parentFolderId);
                
                // Populate individual report
                $this->populateIndividualReport($spreadsheetId, $evaluation, $teacherName, $program);
                
                $generatedCount++;
                error_log("Created individual report: " . $fileName);
                
            } catch (Exception $e) {
                error_log("Error creating individual report for {$evaluation['student_name']}: " . $e->getMessage());
            }
        }
        
        return $generatedCount;
    }
    
    /**
     * Populate individual student report
     */
    private function populateIndividualReport($spreadsheetId, $evaluation, $teacherName, $program) {
        try {
            // Calculate scores
            $totalScore = $evaluation['q1_1'] + $evaluation['q1_2'] + $evaluation['q1_3'] + $evaluation['q1_4'] + $evaluation['q1_5'] + $evaluation['q1_6'] +
                         $evaluation['q2_1'] + $evaluation['q2_2'] + $evaluation['q2_3'] + $evaluation['q2_4'] +
                         $evaluation['q3_1'] + $evaluation['q3_2'] + $evaluation['q3_3'] + $evaluation['q3_4'] +
                         $evaluation['q4_1'] + $evaluation['q4_2'] + $evaluation['q4_3'] + $evaluation['q4_4'] + $evaluation['q4_5'] + $evaluation['q4_6'];
            
            $maxPossible = 100; // 20 questions × 5 points each
            $percentage = ($totalScore / $maxPossible) * 100;
            $rating = $this->getPerformanceRating($percentage);
            
            $reportData = [
                ['INDIVIDUAL EVALUATION REPORT'],
                [''],
                ['Teacher Name:', $teacherName],
                ['Program:', $program],
                ['Student Name:', $evaluation['student_name']],
                ['Username:', $evaluation['student_username']],
                ['Section:', $evaluation['section']],
                ['Evaluation Date:', $evaluation['submitted_at']],
                [''],
                ['EVALUATION SCORES'],
                ['Category', 'Total Score', 'Max Possible', 'Percentage'],
                ['Teaching Ability', 
                 $evaluation['q1_1'] + $evaluation['q1_2'] + $evaluation['q1_3'] + $evaluation['q1_4'] + $evaluation['q1_5'] + $evaluation['q1_6'],
                 30, 
                 number_format((($evaluation['q1_1'] + $evaluation['q1_2'] + $evaluation['q1_3'] + $evaluation['q1_4'] + $evaluation['q1_5'] + $evaluation['q1_6']) / 30) * 100, 1) . '%'],
                ['Management Skills',
                 $evaluation['q2_1'] + $evaluation['q2_2'] + $evaluation['q2_3'] + $evaluation['q2_4'],
                 20,
                 number_format((($evaluation['q2_1'] + $evaluation['q2_2'] + $evaluation['q2_3'] + $evaluation['q2_4']) / 20) * 100, 1) . '%'],
                ['Guidance Skills',
                 $evaluation['q3_1'] + $evaluation['q3_2'] + $evaluation['q3_3'] + $evaluation['q3_4'],
                 20,
                 number_format((($evaluation['q3_1'] + $evaluation['q3_2'] + $evaluation['q3_3'] + $evaluation['q3_4']) / 20) * 100, 1) . '%'],
                ['Personal & Social',
                 $evaluation['q4_1'] + $evaluation['q4_2'] + $evaluation['q4_3'] + $evaluation['q4_4'] + $evaluation['q4_5'] + $evaluation['q4_6'],
                 30,
                 number_format((($evaluation['q4_1'] + $evaluation['q4_2'] + $evaluation['q4_3'] + $evaluation['q4_4'] + $evaluation['q4_5'] + $evaluation['q4_6']) / 30) * 100, 1) . '%'],
                [''],
                ['OVERALL RESULTS'],
                ['Total Score:', $totalScore, $maxPossible, number_format($percentage, 1) . '%'],
                ['Performance Rating:', $rating],
                [''],
                ['DETAILED BREAKDOWN'],
                ['Question', 'Score', 'Question', 'Score'],
                ['1.1 Teaching Ability', $evaluation['q1_1'], '2.1 Management', $evaluation['q2_1']],
                ['1.2 Teaching Ability', $evaluation['q1_2'], '2.2 Management', $evaluation['q2_2']],
                ['1.3 Teaching Ability', $evaluation['q1_3'], '2.3 Management', $evaluation['q2_3']],
                ['1.4 Teaching Ability', $evaluation['q1_4'], '2.4 Management', $evaluation['q2_4']],
                ['1.5 Teaching Ability', $evaluation['q1_5'], '3.1 Guidance', $evaluation['q3_1']],
                ['1.6 Teaching Ability', $evaluation['q1_6'], '3.2 Guidance', $evaluation['q3_2']],
                ['4.1 Personal', $evaluation['q4_1'], '3.3 Guidance', $evaluation['q3_3']],
                ['4.2 Personal', $evaluation['q4_2'], '3.4 Guidance', $evaluation['q3_4']],
                ['4.3 Personal', $evaluation['q4_3'], '4.4 Personal', $evaluation['q4_4']],
                ['4.5 Personal', $evaluation['q4_5'], '4.6 Personal', $evaluation['q4_6']],
                [''],
                ['STUDENT COMMENTS'],
                [$evaluation['comments'] ?: 'No comments provided']
            ];
            
            $range = 'Sheet1!A1:D' . count($reportData);
            $body = new \Google\Service\Sheets\ValueRange([
                'values' => $reportData
            ]);
            
            $this->sheetsService->spreadsheets_values->update(
                $spreadsheetId, $range, $body, ['valueInputOption' => 'RAW']
            );
            
        } catch (Exception $e) {
            error_log("Error populating individual report: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate program-specific summary report
     */
    private function generateProgramSummaryReport($teacherName, $program, $evaluations, $parentFolderId) {
        try {
            $fileName = "Summary Report FOR " . $program . " - " . $this->sanitizeFileName($teacherName);
            
            // Create spreadsheet
            $spreadsheet = new \Google\Service\Sheets\Spreadsheet([
                'properties' => ['title' => $fileName]
            ]);
            
            $createdSpreadsheet = $this->sheetsService->spreadsheets->create($spreadsheet);
            $spreadsheetId = $createdSpreadsheet->getSpreadsheetId();
            
            // Move to folder
            $this->moveFileToFolder($spreadsheetId, $parentFolderId);
            
            // Get statistics for this teacher-program combination
            $stats = $this->getProgramStatistics($teacherName, $program);
            $commentsAnalysis = $this->getCommentsAnalysis($teacherName, $program);
            
            // Populate the report
            $this->populateProgramSummaryReport($spreadsheetId, $teacherName, $program, $stats, $commentsAnalysis, count($evaluations));
            
            return $spreadsheetId;
            
        } catch (Exception $e) {
            error_log("Error creating program summary: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get statistics for a specific teacher-program combination
     */
    private function getProgramStatistics($teacherName, $program) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    AVG(q1_1) as q1_1_avg, AVG(q1_2) as q1_2_avg, AVG(q1_3) as q1_3_avg,
                    AVG(q1_4) as q1_4_avg, AVG(q1_5) as q1_5_avg, AVG(q1_6) as q1_6_avg,
                    AVG(q2_1) as q2_1_avg, AVG(q2_2) as q2_2_avg, AVG(q2_3) as q2_3_avg, AVG(q2_4) as q2_4_avg,
                    AVG(q3_1) as q3_1_avg, AVG(q3_2) as q3_2_avg, AVG(q3_3) as q3_3_avg, AVG(q3_4) as q3_4_avg,
                    AVG(q4_1) as q4_1_avg, AVG(q4_2) as q4_2_avg, AVG(q4_3) as q4_3_avg,
                    AVG(q4_4) as q4_4_avg, AVG(q4_5) as q4_5_avg, AVG(q4_6) as q4_6_avg,
                    AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                         q2_1 + q2_2 + q2_3 + q2_4 + 
                         q3_1 + q3_2 + q3_3 + q3_4 + 
                         q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as overall_avg,
                    MIN((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                         q2_1 + q2_2 + q2_3 + q2_4 + 
                         q3_1 + q3_2 + q3_3 + q3_4 + 
                         q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as min_score,
                    MAX((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                         q2_1 + q2_2 + q2_3 + q2_4 + 
                         q3_1 + q3_2 + q3_3 + q3_4 + 
                         q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 * 100) as max_score
                FROM evaluations 
                WHERE teacher_name = ? AND program = ?
            ");
            $stmt->execute([$teacherName, $program]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting program statistics: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get comments analysis for a specific teacher-program combination
     */
    private function getCommentsAnalysis($teacherName, $program) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT comments 
                FROM evaluations 
                WHERE teacher_name = ? AND program = ? AND comments IS NOT NULL AND comments != ''
            ");
            $stmt->execute([$teacherName, $program]);
            $comments = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            return $this->categorizeComments($comments);
            
        } catch (Exception $e) {
            return ['total' => 0, 'positive' => 0, 'negative' => 0, 'neutral' => 0, 'sample_comments' => []];
        }
    }
    
    /**
     * Populate program summary report
     */
    private function populateProgramSummaryReport($spreadsheetId, $teacherName, $program, $stats, $commentsAnalysis, $totalEvaluations) {
        $reportData = [
            ['PROGRAM SUMMARY REPORT - ' . strtoupper($program)],
            [''],
            ['Teacher Name:', $teacherName],
            ['Program:', $program],
            ['Total Evaluations:', $totalEvaluations],
            ['Overall Average Score:', number_format($stats['overall_avg'] ?? 0, 2) . '%'],
            ['Performance Rating:', $this->getPerformanceRating($stats['overall_avg'] ?? 0)],
            ['Highest Score:', number_format($stats['max_score'] ?? 0, 2) . '%'],
            ['Lowest Score:', number_format($stats['min_score'] ?? 0, 2) . '%'],
            ['Generated on:', date('F j, Y g:i A')],
            [''],
            ['QUESTION CATEGORY AVERAGES'],
            ['Category', 'Average Score (1-5)', 'Percentage'],
            ['Teaching Ability', number_format(($stats['q1_1_avg'] + $stats['q1_2_avg'] + $stats['q1_3_avg'] + $stats['q1_4_avg'] + $stats['q1_5_avg'] + $stats['q1_6_avg']) / 6, 2), number_format((($stats['q1_1_avg'] + $stats['q1_2_avg'] + $stats['q1_3_avg'] + $stats['q1_4_avg'] + $stats['q1_5_avg'] + $stats['q1_6_avg']) / 6 / 5) * 100, 1) . '%'],
            ['Management Skills', number_format(($stats['q2_1_avg'] + $stats['q2_2_avg'] + $stats['q2_3_avg'] + $stats['q2_4_avg']) / 4, 2), number_format((($stats['q2_1_avg'] + $stats['q2_2_avg'] + $stats['q2_3_avg'] + $stats['q2_4_avg']) / 4 / 5) * 100, 1) . '%'],
            ['Guidance Skills', number_format(($stats['q3_1_avg'] + $stats['q3_2_avg'] + $stats['q3_3_avg'] + $stats['q3_4_avg']) / 4, 2), number_format((($stats['q3_1_avg'] + $stats['q3_2_avg'] + $stats['q3_3_avg'] + $stats['q3_4_avg']) / 4 / 5) * 100, 1) . '%'],
            ['Personal & Social', number_format(($stats['q4_1_avg'] + $stats['q4_2_avg'] + $stats['q4_3_avg'] + $stats['q4_4_avg'] + $stats['q4_5_avg'] + $stats['q4_6_avg']) / 6, 2), number_format((($stats['q4_1_avg'] + $stats['q4_2_avg'] + $stats['q4_3_avg'] + $stats['q4_4_avg'] + $stats['q4_5_avg'] + $stats['q4_6_avg']) / 6 / 5) * 100, 1) . '%'],
            [''],
            ['STUDENT COMMENTS ANALYSIS'],
            ['Comment Type', 'Count', 'Percentage'],
            ['Positive Comments', $commentsAnalysis['positive'], $commentsAnalysis['total'] > 0 ? number_format(($commentsAnalysis['positive']/$commentsAnalysis['total'])*100, 1) . '%' : '0%'],
            ['Negative Comments', $commentsAnalysis['negative'], $commentsAnalysis['total'] > 0 ? number_format(($commentsAnalysis['negative']/$commentsAnalysis['total'])*100, 1) . '%' : '0%'],
            ['Neutral Comments', $commentsAnalysis['neutral'], $commentsAnalysis['total'] > 0 ? number_format(($commentsAnalysis['neutral']/$commentsAnalysis['total'])*100, 1) . '%' : '0%'],
            ['Total Comments', $commentsAnalysis['total'], '100%']
        ];
        
        if (!empty($commentsAnalysis['sample_comments'])) {
            $reportData[] = [''];
            $reportData[] = ['SAMPLE COMMENTS'];
            foreach ($commentsAnalysis['sample_comments'] as $comment) {
                $reportData[] = [substr($comment, 0, 100) . (strlen($comment) > 100 ? '...' : '')];
            }
        }
        
        $range = 'Sheet1!A1:C' . count($reportData);
        $body = new \Google\Service\Sheets\ValueRange([
            'values' => $reportData
        ]);
        
        $this->sheetsService->spreadsheets_values->update(
            $spreadsheetId, $range, $body, ['valueInputOption' => 'RAW']
        );
    }
    
    // ... (Keep all the other helper methods the same as in the previous version)
    // getSystemStatistics, populateSystemSummaryReport, generateSystemSummaryReport,
    // getPerformanceRating, categorizeComments, sanitizeFileName, moveFileToFolder,
    // shareFolderWithEmail, createReportsFolder, createSubFolder, getEvaluationCount
    
    // Include all the remaining helper methods from the previous version here
    // They should be exactly the same as in the previous code I provided
    
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
    
    private function getPerformanceRating($percentage) {
        if ($percentage >= 90) return 'Excellent';
        if ($percentage >= 80) return 'Very Good';
        if ($percentage >= 70) return 'Good';
        if ($percentage >= 60) return 'Satisfactory';
        return 'Needs Improvement';
    }
    
    private function categorizeComments($comments) {
        $positiveKeywords = ['good', 'excellent', 'great', 'amazing', 'wonderful', 'fantastic', 'helpful', 'clear', 'understandable', 'patient', 'kind', 'respectful', 'magaling', 'galing', 'okay', 'mabait'];
        $negativeKeywords = ['bad', 'terrible', 'awful', 'poor', 'worst', 'horrible', 'confusing', 'unclear', 'rude', 'impatient', 'boring', 'late', 'hindi', 'wala', 'kulang', 'masama'];
        
        $analysis = [
            'total' => count($comments),
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0,
            'sample_comments' => array_slice($comments, 0, 5)
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
    
    private function createSubFolder($parentFolderId, $folderName) {
        $folderMetadata = new Drive\DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentFolderId]
        ]);
        
        $folder = $this->driveService->files->create($folderMetadata);
        return $folder->getId();
    }
    
    private function getEvaluationCount() {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM evaluations");
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
}

// Function to be called from your API
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
