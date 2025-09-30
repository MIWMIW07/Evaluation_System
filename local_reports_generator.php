<?php
// local_reports_generator.php
ob_start(); // Fix for header issues
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Set headers for JSON response
header('Content-Type: application/json');

// Check if TCPDF exists
if (!file_exists('tcpdf/tcpdf.php')) {
    echo json_encode([
        'success' => false,
        'error' => 'TCPDF library not found. Please ensure tcpdf folder exists in your project root.'
    ]);
    exit;
}

// Include TCPDF library
require_once 'tcpdf/tcpdf.php';

class EvaluationPDF extends TCPDF {
    // Page header
    public function Header() {
        // Set font
        $this->SetFont('helvetica', 'B', 16);
        // Title
        $this->Cell(0, 15, 'TEACHER EVALUATION SYSTEM', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        // Line break
        $this->Ln(10);
    }

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

function generateIndividualReport($pdo, $teacherName, $program, $section, $outputPath) {
    try {
        // Get evaluations for this teacher, program, and section
        $stmt = $pdo->prepare("
            SELECT * FROM evaluations 
            WHERE teacher_name = ? AND program = ? AND section = ?
            ORDER BY student_name
        ");
        $stmt->execute([$teacherName, $program, $section]);
        $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($evaluations)) {
            return false;
        }

        // Create new PDF document
        $pdf = new EvaluationPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Teacher Evaluation System');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle("Individual Report - $teacherName - $program $section");
        $pdf->SetSubject('Teacher Evaluation Report');

        // Set default header data
        $pdf->SetHeaderData('', 0, "TEACHER EVALUATION SYSTEM", "Individual Evaluation Report\n$teacherName - $program $section");

        // Set margins
        $pdf->SetMargins(15, 25, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 15);

        foreach ($evaluations as $eval) {
            $pdf->AddPage();

            // Student Information
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, "STUDENT INFORMATION", 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 6, "Student Name: " . ($eval['student_name'] ?? 'N/A'), 0, 1);
            $pdf->Cell(0, 6, "Teacher: " . ($eval['teacher_name'] ?? 'N/A'), 0, 1);
            $pdf->Cell(0, 6, "Program: " . ($eval['program'] ?? 'N/A'), 0, 1);
            $pdf->Cell(0, 6, "Section: " . ($eval['section'] ?? 'N/A'), 0, 1);
            $pdf->Cell(0, 6, "Date: " . date('F j, Y', strtotime($eval['submitted_at'] ?? 'now')), 0, 1);
            $pdf->Ln(10);

            // Evaluation Scores
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, "EVALUATION SCORES", 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);

            // Category 1: Teaching for Independent Learning
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 8, "1. TEACHING FOR INDEPENDENT LEARNING", 0, 1);
            $pdf->SetFont('helvetica', '', 10);
            
            $q1_1 = $eval['q1_1'] ?? 0; $q1_2 = $eval['q1_2'] ?? 0; $q1_3 = $eval['q1_3'] ?? 0;
            $q1_4 = $eval['q1_4'] ?? 0; $q1_5 = $eval['q1_5'] ?? 0; $q1_6 = $eval['q1_6'] ?? 0;
            
            $cat1_avg = ($q1_1 + $q1_2 + $q1_3 + $q1_4 + $q1_5 + $q1_6) / 6;
            
            $pdf->Cell(0, 6, "1.1 Creates classroom environment: " . number_format($q1_1, 1), 0, 1);
            $pdf->Cell(0, 6, "1.2 Sets learning targets: " . number_format($q1_2, 1), 0, 1);
            $pdf->Cell(0, 6, "1.3 Communicates learning: " . number_format($q1_3, 1), 0, 1);
            $pdf->Cell(0, 6, "1.4 Encourages creative thinking: " . number_format($q1_4, 1), 0, 1);
            $pdf->Cell(0, 6, "1.5 Promotes collaboration: " . number_format($q1_5, 1), 0, 1);
            $pdf->Cell(0, 6, "1.6 Uses varied activities: " . number_format($q1_6, 1), 0, 1);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 6, "Category 1 Average: " . number_format($cat1_avg, 2), 0, 1);
            $pdf->Ln(5);

            // Category 2: Teaching for Meaningful Learning
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 8, "2. TEACHING FOR MEANINGFUL LEARNING", 0, 1);
            $pdf->SetFont('helvetica', '', 10);
            
            $q2_1 = $eval['q2_1'] ?? 0; $q2_2 = $eval['q2_2'] ?? 0; $q2_3 = $eval['q2_3'] ?? 0; $q2_4 = $eval['q2_4'] ?? 0;
            
            $cat2_avg = ($q2_1 + $q2_2 + $q2_3 + $q2_4) / 4;
            
            $pdf->Cell(0, 6, "2.1 Connects lessons to life: " . number_format($q2_1, 1), 0, 1);
            $pdf->Cell(0, 6, "2.2 Integrates technology: " . number_format($q2_2, 1), 0, 1);
            $pdf->Cell(0, 6, "2.3 Uses varied strategies: " . number_format($q2_3, 1), 0, 1);
            $pdf->Cell(0, 6, "2.4 Provides meaningful feedback: " . number_format($q2_4, 1), 0, 1);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 6, "Category 2 Average: " . number_format($cat2_avg, 2), 0, 1);
            $pdf->Ln(5);

            // Category 3: Assessment and Evaluation
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 8, "3. ASSESSMENT AND EVALUATION", 0, 1);
            $pdf->SetFont('helvetica', '', 10);
            
            $q3_1 = $eval['q3_1'] ?? 0; $q3_2 = $eval['q3_2'] ?? 0; $q3_3 = $eval['q3_3'] ?? 0; $q3_4 = $eval['q3_4'] ?? 0;
            
            $cat3_avg = ($q3_1 + $q3_2 + $q3_3 + $q3_4) / 4;
            
            $pdf->Cell(0, 6, "3.1 Uses varied assessment: " . number_format($q3_1, 1), 0, 1);
            $pdf->Cell(0, 6, "3.2 Provides timely feedback: " . number_format($q3_2, 1), 0, 1);
            $pdf->Cell(0, 6, "3.3 Assessment aligns with objectives: " . number_format($q3_3, 1), 0, 1);
            $pdf->Cell(0, 6, "3.4 Encourages self-assessment: " . number_format($q3_4, 1), 0, 1);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 6, "Category 3 Average: " . number_format($cat3_avg, 2), 0, 1);
            $pdf->Ln(5);

            // Category 4: Professional Development
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 8, "4. PROFESSIONAL DEVELOPMENT", 0, 1);
            $pdf->SetFont('helvetica', '', 10);
            
            $q4_1 = $eval['q4_1'] ?? 0; $q4_2 = $eval['q4_2'] ?? 0; $q4_3 = $eval['q4_3'] ?? 0; 
            $q4_4 = $eval['q4_4'] ?? 0; $q4_5 = $eval['q4_5'] ?? 0; $q4_6 = $eval['q4_6'] ?? 0;
            
            $cat4_avg = ($q4_1 + $q4_2 + $q4_3 + $q4_4 + $q4_5 + $q4_6) / 6;
            
            $pdf->Cell(0, 6, "4.1 Demonstrates mastery: " . number_format($q4_1, 1), 0, 1);
            $pdf->Cell(0, 6, "4.2 Shows enthusiasm: " . number_format($q4_2, 1), 0, 1);
            $pdf->Cell(0, 6, "4.3 Maintains professionalism: " . number_format($q4_3, 1), 0, 1);
            $pdf->Cell(0, 6, "4.4 Shows care for students: " . number_format($q4_4, 1), 0, 1);
            $pdf->Cell(0, 6, "4.5 Maintains positive attitude: " . number_format($q4_5, 1), 0, 1);
            $pdf->Cell(0, 6, "4.6 Adheres to ethical standards: " . number_format($q4_6, 1), 0, 1);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 6, "Category 4 Average: " . number_format($cat4_avg, 2), 0, 1);
            $pdf->Ln(10);

            // Overall Score
            $total_score = $q1_1 + $q1_2 + $q1_3 + $q1_4 + $q1_5 + $q1_6 +
                         $q2_1 + $q2_2 + $q2_3 + $q2_4 +
                         $q3_1 + $q3_2 + $q3_3 + $q3_4 +
                         $q4_1 + $q4_2 + $q4_3 + $q4_4 + $q4_5 + $q4_6;
            
            $overall_avg = $total_score / 20;
            $percentage = ($overall_avg / 5) * 100;

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, "OVERALL EVALUATION", 0, 1, 'C');
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 8, "Overall Score: " . number_format($overall_avg, 2) . " / 5.00", 0, 1, 'C');
            $pdf->Cell(0, 8, "Percentage: " . number_format($percentage, 1) . "%", 0, 1, 'C');
            
            // Rating
            $rating = '';
            if ($percentage >= 90) $rating = 'EXCELLENT';
            elseif ($percentage >= 80) $rating = 'VERY GOOD';
            elseif ($percentage >= 70) $rating = 'GOOD';
            elseif ($percentage >= 60) $rating = 'SATISFACTORY';
            else $rating = 'NEEDS IMPROVEMENT';
            
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFillColor(70, 130, 180);
            $pdf->Cell(0, 10, "RATING: $rating", 0, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
        }

        // Save PDF file
        $pdf->Output($outputPath, 'F');
        return true;

    } catch (Exception $e) {
        error_log("Error generating individual report: " . $e->getMessage());
        return false;
    }
}

function generateSummaryReport($pdo, $teacherName, $program, $section, $outputPath) {
    try {
        // Get all evaluations for this teacher, program, and section
        $stmt = $pdo->prepare("
            SELECT * FROM evaluations 
            WHERE teacher_name = ? AND program = ? AND section = ?
        ");
        $stmt->execute([$teacherName, $program, $section]);
        $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($evaluations)) {
            return false;
        }

        // Calculate averages
        $totalStudents = count($evaluations);
        $category1_avg = 0; $category2_avg = 0; $category3_avg = 0; $category4_avg = 0;
        $overall_avg = 0;

        foreach ($evaluations as $eval) {
            $cat1 = (($eval['q1_1'] ?? 0) + ($eval['q1_2'] ?? 0) + ($eval['q1_3'] ?? 0) + ($eval['q1_4'] ?? 0) + ($eval['q1_5'] ?? 0) + ($eval['q1_6'] ?? 0)) / 6;
            $cat2 = (($eval['q2_1'] ?? 0) + ($eval['q2_2'] ?? 0) + ($eval['q2_3'] ?? 0) + ($eval['q2_4'] ?? 0)) / 4;
            $cat3 = (($eval['q3_1'] ?? 0) + ($eval['q3_2'] ?? 0) + ($eval['q3_3'] ?? 0) + ($eval['q3_4'] ?? 0)) / 4;
            $cat4 = (($eval['q4_1'] ?? 0) + ($eval['q4_2'] ?? 0) + ($eval['q4_3'] ?? 0) + ($eval['q4_4'] ?? 0) + ($eval['q4_5'] ?? 0) + ($eval['q4_6'] ?? 0)) / 6;
            $total = ($cat1 + $cat2 + $cat3 + $cat4) / 4;

            $category1_avg += $cat1;
            $category2_avg += $cat2;
            $category3_avg += $cat3;
            $category4_avg += $cat4;
            $overall_avg += $total;
        }

        $category1_avg /= $totalStudents;
        $category2_avg /= $totalStudents;
        $category3_avg /= $totalStudents;
        $category4_avg /= $totalStudents;
        $overall_avg /= $totalStudents;
        $overall_percentage = ($overall_avg / 5) * 100;

        // Create PDF
        $pdf = new EvaluationPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Teacher Evaluation System');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle("Summary Report - $teacherName - $program $section");

        // Set header
        $pdf->SetHeaderData('', 0, "TEACHER EVALUATION SYSTEM", "Summary Evaluation Report\n$teacherName - $program $section");

        // Set margins
        $pdf->SetMargins(15, 25, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 15);

        $pdf->AddPage();

        // Teacher Information
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, "TEACHER SUMMARY REPORT", 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, "TEACHER INFORMATION", 0, 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 6, "Teacher Name: " . $teacherName, 0, 1);
        $pdf->Cell(0, 6, "Program: " . $program, 0, 1);
        $pdf->Cell(0, 6, "Section: " . $section, 0, 1);
        $pdf->Cell(0, 6, "Total Students Evaluated: " . $totalStudents, 0, 1);
        $pdf->Cell(0, 6, "Report Date: " . date('F j, Y'), 0, 1);
        $pdf->Ln(10);

        // Summary Statistics
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, "SUMMARY STATISTICS", 0, 1);
        $pdf->SetFont('helvetica', '', 11);

        $pdf->Cell(0, 6, "1. Teaching for Independent Learning: " . number_format($category1_avg, 2) . " / 5.00", 0, 1);
        $pdf->Cell(0, 6, "2. Teaching for Meaningful Learning: " . number_format($category2_avg, 2) . " / 5.00", 0, 1);
        $pdf->Cell(0, 6, "3. Assessment and Evaluation: " . number_format($category3_avg, 2) . " / 5.00", 0, 1);
        $pdf->Cell(0, 6, "4. Professional Development: " . number_format($category4_avg, 2) . " / 5.00", 0, 1);
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(0, 8, "Overall Average Score: " . number_format($overall_avg, 2) . " / 5.00", 0, 1);
        $pdf->Cell(0, 8, "Overall Percentage: " . number_format($overall_percentage, 1) . "%", 0, 1);

        // Rating
        $rating = '';
        $color = [];
        if ($overall_percentage >= 90) {
            $rating = 'EXCELLENT';
            $color = [34, 139, 34]; // Green
        } elseif ($overall_percentage >= 80) {
            $rating = 'VERY GOOD';
            $color = [65, 105, 225]; // Blue
        } elseif ($overall_percentage >= 70) {
            $rating = 'GOOD';
            $color = [255, 165, 0]; // Orange
        } elseif ($overall_percentage >= 60) {
            $rating = 'SATISFACTORY';
            $color = [255, 140, 0]; // Dark Orange
        } else {
            $rating = 'NEEDS IMPROVEMENT';
            $color = [220, 20, 60]; // Red
        }

        $pdf->Ln(5);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 12, "OVERALL RATING: $rating", 0, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(10);

        // Student List
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, "STUDENTS EVALUATED", 0, 1);
        $pdf->SetFont('helvetica', '', 10);

        foreach ($evaluations as $eval) {
            $total_score = (($eval['q1_1'] ?? 0) + ($eval['q1_2'] ?? 0) + ($eval['q1_3'] ?? 0) + ($eval['q1_4'] ?? 0) + ($eval['q1_5'] ?? 0) + ($eval['q1_6'] ?? 0) +
                          ($eval['q2_1'] ?? 0) + ($eval['q2_2'] ?? 0) + ($eval['q2_3'] ?? 0) + ($eval['q2_4'] ?? 0) +
                          ($eval['q3_1'] ?? 0) + ($eval['q3_2'] ?? 0) + ($eval['q3_3'] ?? 0) + ($eval['q3_4'] ?? 0) +
                          ($eval['q4_1'] ?? 0) + ($eval['q4_2'] ?? 0) + ($eval['q4_3'] ?? 0) + ($eval['q4_4'] ?? 0) + ($eval['q4_5'] ?? 0) + ($eval['q4_6'] ?? 0)) / 20;
            
            $pdf->Cell(0, 6, "• " . ($eval['student_name'] ?? 'Unknown Student') . " - " . number_format($total_score, 2) . " / 5.00", 0, 1);
        }

        // Save PDF
        $pdf->Output($outputPath, 'F');
        return true;

    } catch (Exception $e) {
        error_log("Error generating summary report: " . $e->getMessage());
        return false;
    }
}

function createZip($source, $destination) {
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZipArchive::CREATE)) {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);

            // Skip directories and . / .. 
            if (in_array(substr($file, strrpos($file, '/') + 1), ['.', '..'])) {
                continue;
            }

            $file = realpath($file);

            if (is_dir($file) === true) {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            } else if (is_file($file) === true) {
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    } else if (is_file($source) === true) {
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    return $zip->close();
}

// Main execution
try {
    $pdo = getPDO();
    
    // Get all unique teacher-program-section combinations
    $stmt = $pdo->query("
        SELECT DISTINCT teacher_name, program, section 
        FROM evaluations 
        ORDER BY teacher_name, program, section
    ");
    $combinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($combinations)) {
        echo json_encode([
            'success' => false, 
            'error' => 'No evaluation data found to generate reports'
        ]);
        exit;
    }

    // For Railway deployment, use a writable directory in the project
    $basePath = __DIR__ . '/reports/';
    
    // Create base directory
    if (!is_dir($basePath)) {
        if (!mkdir($basePath, 0777, true)) {
            throw new Exception("Failed to create reports directory: " . $basePath);
        }
    }

    $baseReportsPath = $basePath . 'Teacher Evaluation Reports/Reports/';
    
    // Create reports directory
    if (!is_dir($baseReportsPath)) {
        if (!mkdir($baseReportsPath, 0777, true)) {
            throw new Exception("Failed to create reports subdirectory: " . $baseReportsPath);
        }
    }

    $teachersProcessed = 0;
    $individualReports = 0;
    $summaryReports = 0;

    foreach ($combinations as $combo) {
        $teacherName = $combo['teacher_name'];
        $program = $combo['program'];
        $section = $combo['section'];

        // Create teacher directory
        $teacherDir = $baseReportsPath . $teacherName . '/';
        if (!is_dir($teacherDir)) {
            if (!mkdir($teacherDir, 0777, true)) {
                error_log("Failed to create teacher directory: " . $teacherDir);
                continue;
            }
            $teachersProcessed++;
        }

        // Create program directory
        $programDir = $teacherDir . $program . '/';
        if (!is_dir($programDir)) {
            if (!mkdir($programDir, 0777, true)) {
                error_log("Failed to create program directory: " . $programDir);
                continue;
            }
        }

        // Generate individual reports
        $individualFilename = "Individual Report - " . $teacherName . " - " . $program . " " . $section . ".pdf";
        $individualSuccess = generateIndividualReport(
            $pdo, 
            $teacherName, 
            $program, 
            $section, 
            $programDir . $individualFilename
        );

        if ($individualSuccess) {
            $individualReports++;
        }

        // Generate summary report
        $summaryFilename = "Summary Report FOR " . $program . " - " . $teacherName . ".pdf";
        $summarySuccess = generateSummaryReport(
            $pdo,
            $teacherName,
            $program,
            $section,
            $programDir . $summaryFilename
        );

        if ($summarySuccess) {
            $summaryReports++;
        }
    }

    $totalFiles = $individualReports + $summaryReports;

    // Create ZIP file of all reports
    $zipFile = $basePath . 'All_Reports_' . date('Y-m-d_H-i-s') . '.zip';
    $zipCreated = createZip($baseReportsPath, $zipFile);

    // Prepare response
    $response = [
        'success' => true,
        'message' => 'PDF reports generated successfully!',
        'teachers_processed' => $teachersProcessed,
        'individual_reports' => $individualReports,
        'summary_reports' => $summaryReports,
        'total_files' => $totalFiles,
        'base_path' => $baseReportsPath
    ];

    if ($zipCreated && file_exists($zipFile)) {
        $response['zip_file'] = 'reports/' . basename($zipFile);
        $response['zip_message'] = 'All reports have been bundled into a ZIP file for easy download.';
    }

    echo json_encode($response);

} catch (Exception $e) {
    // Ensure we only output JSON, even for errors
    ob_clean();
    error_log("Error in local reports generator: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate reports: ' . $e->getMessage()
    ]);
}
exit;
?>
