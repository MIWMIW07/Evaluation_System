<?php
// local_reports_generator.php
error_reporting(0); // Suppress all errors from output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Start output buffering to catch any stray output
ob_start();

try {
    header('Content-Type: application/json');
    
    require_once 'includes/db_connection.php';
    
    // Check if TCPDF is available
    if (!class_exists('TCPDF')) {
        require_once __DIR__ . 'tcpdf/tcpdf.php';
    }
    
    // TCPDF Class Extension
    class EvaluationPDF extends TCPDF {
        public function Header() {
            // Add logo
            $logoPath = __DIR__ . '/logo.png';
            if (file_exists($logoPath)) {
                $this->Image($logoPath, 15, 10, 25, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
            
            $this->SetFont('helvetica', 'B', 14);
            $this->Cell(0, 10, 'PHILIPPINE TECHNOLOGICAL INSTITUTE', 0, 1, 'C');
            $this->SetFont('helvetica', '', 10);
            $this->Cell(0, 5, 'GMA-BRANCH [2nd Semester 2024-2025]', 0, 1, 'C');
            $this->Cell(0, 5, 'FACULTY EVALUATION CRITERIA', 0, 1, 'C');
            $this->Ln(5);
        }
        
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
        }
    }

    $pdo = getPDO();
    
    // Create reports directory structure
    $reportsDir = __DIR__ . '/reports/Teacher Evaluation Reports/Reports/';
    if (!file_exists($reportsDir)) {
        mkdir($reportsDir, 0777, true);
    }

    // Get unique teacher-program-section combinations for individual reports
    $stmt = $pdo->query("
        SELECT DISTINCT 
            teacher_name, 
            program, 
            section 
        FROM evaluations 
        ORDER BY teacher_name, program, section
    ");
    $combinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unique teacher-program combinations for summary reports
    $summaryStmt = $pdo->query("
        SELECT DISTINCT 
            teacher_name, 
            program
        FROM evaluations 
        ORDER BY teacher_name, program
    ");
    $summaryCombinations = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

    $teachersProcessed = [];
    $individualReports = 0;
    $summaryReports = 0;
    $totalFiles = 0;

    // Generate individual reports by section
    foreach ($combinations as $combo) {
        $teacherName = $combo['teacher_name'];
        $program = $combo['program'];
        $section = $combo['section'];

        // Create teacher folder
        $teacherDir = $reportsDir . $teacherName . '/';
        if (!file_exists($teacherDir)) {
            mkdir($teacherDir, 0777, true);
        }

        // Create program folder
        $programDir = $teacherDir . $program . '/';
        if (!file_exists($programDir)) {
            mkdir($programDir, 0777, true);
        }

        // Track teachers
        if (!in_array($teacherName, $teachersProcessed)) {
            $teachersProcessed[] = $teacherName;
        }

        // Generate individual reports
        $evalStmt = $pdo->prepare("
            SELECT * FROM evaluations 
            WHERE teacher_name = ? AND program = ? AND section = ?
        ");
        $evalStmt->execute([$teacherName, $program, $section]);
        $evaluations = $evalStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($evaluations as $eval) {
            $filename = $programDir . 'Individual_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $eval['student_name']) . '_' . $section . '.pdf';
            if (generateIndividualReport($eval, $filename)) {
                $individualReports++;
                $totalFiles++;
            }
        }
    }

    // Generate summary reports by program (entire program, all sections combined)
    foreach ($summaryCombinations as $combo) {
        $teacherName = $combo['teacher_name'];
        $program = $combo['program'];

        $teacherDir = $reportsDir . $teacherName . '/';
        $programDir = $teacherDir . $program . '/';

        // Generate summary report for entire program
        $summaryFilename = $programDir . 'Summary_' . $program . '_ALL_SECTIONS.pdf';
        if (generateSummaryReport($pdo, $teacherName, $program, $summaryFilename)) {
            $summaryReports++;
            $totalFiles++;
        }
    }

    // Clear any buffered output before sending JSON
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'message' => 'Reports generated successfully!',
        'teachers_processed' => count($teachersProcessed),
        'individual_reports' => $individualReports,
        'summary_reports' => $summaryReports,
        'total_files' => $totalFiles,
        'reports_location' => 'reports/Teacher Evaluation Reports/Reports/'
    ]);

} catch (Exception $e) {
    // Clear any buffered output
    ob_end_clean();
    
    error_log("Report Generation Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'details' => 'Check error_log.txt for more information'
    ]);
}

// Function to generate individual student evaluation report
function generateIndividualReport($evaluation, $outputPath) {
    try {
        $pdf = new EvaluationPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Teacher Evaluation System');
        $pdf->SetTitle("Evaluation - " . $evaluation['student_name']);
        
        $pdf->SetMargins(10, 30, 10);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        $pdf->AddPage();
        
        // Header Information
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6, "Name: " . strtoupper($evaluation['teacher_name']), 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, "Student: " . $evaluation['student_name'], 0, 1);
        $pdf->Cell(0, 5, "Program: " . $evaluation['program'] . " | Section: " . $evaluation['section'], 0, 1);
        $pdf->Cell(0, 5, "Date: " . date('F j, Y', strtotime($evaluation['submitted_at'])), 0, 1);
        $pdf->Ln(5);

        // Questions and answers
        $questions = [
            'KAKAYAHAN SA PAGTUTURO' => [
                'q1_1' => 'Nasuri at naipaliwanag ang aralin nang hindi binabasa ang aklat sa klase',
                'q1_2' => 'Gumagamit ng audio-visual at mga device upang suportahan ang pagtuturo',
                'q1_3' => 'Nagpapakita ng mga ideya/konsepto nang malinaw at nakakakumbinsi',
                'q1_4' => 'Hinahayaan ang mga mag-aaral na gumamit ng mga konsepto',
                'q1_5' => 'Nagbibigay ng patas na pagsusulit at ibalik ang mga resulta',
                'q1_6' => 'Naguutos nang maayos sa pagtuturo gamit ang maayos na pananalta',
            ],
            'KASANAYAN SA PAMAMAHALA' => [
                'q2_1' => 'Pinapanatiling maayos, disiplinado at ligtas ang silid-aralan',
                'q2_2' => 'Sumusunod sa sistematikong iskedyul ng mga klase',
                'q2_3' => 'Hinuhubog sa mga mag-aaral ang respeto at paggalang',
                'q2_4' => 'Pinahihintulutan ang mga mag-aaral na ipahayag ang kanilang opinyon',
            ],
            'MGA KASANAYAN SA PAGGABAY' => [
                'q3_1' => 'Pagtanggap sa mga mag-aaral bilang indibidwal',
                'q3_2' => 'Pagpapakita ng tiwala at kaayusan sa sarili',
                'q3_3' => 'Pinangangasiwaan ang problema ng klase at Mga mag-aaral',
                'q3_4' => 'Nagpapakita ng tunay na pagmamalasakit sa mga personal',
            ],
            'PERSONAL AT PANLIPUNANG KATANGIAN' => [
                'q4_1' => 'Nagpapanatili ng emosyonal na balanse; hindi masyadong kritikal',
                'q4_2' => 'Malaya sa nakasanayang galaw na nakakagambala sa proseso',
                'q4_3' => 'Maayos at presentable; Malinis at maayos ang mga damit',
                'q4_4' => 'Hindi pagpapakita ng paboritismo',
                'q4_5' => 'May magandang sense of humor at nagpapakita ng sigla',
                'q4_6' => 'May magandang diction, malinaw at maayos na timpla ng boses',
            ]
        ];

        $categoryNum = 1;
        $totalScore = 0;
        $questionCount = 0;

        foreach ($questions as $category => $categoryQuestions) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->Cell(0, 7, $category, 1, 1, 'L', true);
            
            $pdf->SetFont('helvetica', '', 9);
            $qNum = 1;
            foreach ($categoryQuestions as $key => $question) {
                $score = $evaluation[$key] ?? 0;
                $totalScore += $score;
                $questionCount++;
                
                $pdf->Cell(10, 6, "$categoryNum.$qNum", 1, 0, 'C');
                $pdf->Cell(155, 6, $question, 1, 0, 'L');
                $pdf->Cell(25, 6, $score, 1, 1, 'C');
                $qNum++;
            }
            $categoryNum++;
            $pdf->Ln(2);
        }

        // Total Score
        $averageScore = $questionCount > 0 ? $totalScore / $questionCount : 0;
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(255, 200, 150);
        $pdf->Cell(165, 8, 'AVERAGE SCORE', 1, 0, 'R', true);
        $pdf->Cell(25, 8, number_format($averageScore, 2), 1, 1, 'C', true);

        $pdf->Ln(5);

        // Comments Section
        if (!empty($evaluation['positive_comment']) || !empty($evaluation['negative_comment'])) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 6, 'STUDENT COMMENTS:', 0, 1);
            $pdf->SetFont('helvetica', '', 9);
            
            if (!empty($evaluation['positive_comment'])) {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(0, 5, 'Positive Feedback:', 0, 1);
                $pdf->SetFont('helvetica', '', 9);
                $pdf->MultiCell(0, 5, $evaluation['positive_comment'], 0, 'L');
                $pdf->Ln(2);
            }
            
            if (!empty($evaluation['negative_comment'])) {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(0, 5, 'Areas for Improvement:', 0, 1);
                $pdf->SetFont('helvetica', '', 9);
                $pdf->MultiCell(0, 5, $evaluation['negative_comment'], 0, 'L');
                $pdf->Ln(2);
            }
        }

        // Rating Legend
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'RATING SCALE:', 0, 1);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, '4.50 - 5.00 = Outstanding', 0, 1);
        $pdf->Cell(0, 5, '3.50 - 4.49 = Very Satisfactory', 0, 1);
        $pdf->Cell(0, 5, '2.50 - 3.49 = Satisfactory', 0, 1);
        $pdf->Cell(0, 5, '1.50 - 2.49 = Fair', 0, 1);
        $pdf->Cell(0, 5, '1.00 - 1.49 = Poor', 0, 1);

        $pdf->Output($outputPath, 'F');
        return true;

    } catch (Exception $e) {
        error_log("Error generating individual report: " . $e->getMessage());
        return false;
    }
}

// Function to generate summary report for ENTIRE PROGRAM (all sections combined)
function generateSummaryReport($pdo, $teacherName, $program, $outputPath) {
    try {
        // Get ALL evaluations for this teacher and program (all sections)
        $stmt = $pdo->prepare("
            SELECT * FROM evaluations 
            WHERE teacher_name = ? AND program = ?
        ");
        $stmt->execute([$teacherName, $program]);
        $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($evaluations)) {
            return false;
        }

        // Get all sections for this program
        $sections = array_unique(array_column($evaluations, 'section'));
        sort($sections);
        $sectionsText = implode(', ', $sections);

        // Calculate detailed statistics
        $totalStudents = count($evaluations);
        
        // Individual question averages
        $questions = [
            'q1_1' => ['sum' => 0, 'label' => 'Nasuri at naipaliwanag ang aralin nang hindi binabasa ang aklat sa klase'],
            'q1_2' => ['sum' => 0, 'label' => 'Gumagamit ng audio-visual at mga device upang suportahan ang pagtuturo'],
            'q1_3' => ['sum' => 0, 'label' => 'Nagpapakita ng mga ideya/konsepto nang malinaw at nakakakumbinsi'],
            'q1_4' => ['sum' => 0, 'label' => 'Hinahayaan ang mga mag-aaral na gumamit ng mga konsepto'],
            'q1_5' => ['sum' => 0, 'label' => 'Nagbibigay ng patas na pagsusulit at ibalik ang mga resulta'],
            'q1_6' => ['sum' => 0, 'label' => 'Naguutos nang maayos sa pagtuturo gamit ang maayos na pananalta'],
            
            'q2_1' => ['sum' => 0, 'label' => 'Pinapanatiling maayos, disiplinado at ligtas ang silid-aralan'],
            'q2_2' => ['sum' => 0, 'label' => 'Sumusunod sa sistematikong iskedyul ng mga klase'],
            'q2_3' => ['sum' => 0, 'label' => 'Hinuhubog sa mga mag-aaral ang respeto at paggalang'],
            'q2_4' => ['sum' => 0, 'label' => 'Pinahihintulutan ang mga mag-aaral na ipahayag ang kanilang opinyon'],
            
            'q3_1' => ['sum' => 0, 'label' => 'Pagtanggap sa mga mag-aaral bilang indibidwal'],
            'q3_2' => ['sum' => 0, 'label' => 'Pagpapakita ng tiwala at kaayusan sa sarili'],
            'q3_3' => ['sum' => 0, 'label' => 'Pinangangasiwaan ang problema ng klase at Mga mag-aaral'],
            'q3_4' => ['sum' => 0, 'label' => 'Nagpapakita ng tunay na pagmamalasakit sa mga personal'],
            
            'q4_1' => ['sum' => 0, 'label' => 'Nagpapanatili ng emosyonal na balanse; hindi masyadong kritikal'],
            'q4_2' => ['sum' => 0, 'label' => 'Malaya sa nakasanayang galaw na nakakagambala sa proseso'],
            'q4_3' => ['sum' => 0, 'label' => 'Maayos at presentable; Malinis at maayos ang mga damit'],
            'q4_4' => ['sum' => 0, 'label' => 'Hindi pagpapakita ng paboritismo'],
            'q4_5' => ['sum' => 0, 'label' => 'May magandang sense of humor at nagpapakita ng sigla'],
            'q4_6' => ['sum' => 0, 'label' => 'May magandang diction, malinaw at maayos na timpla ng boses'],
        ];

        // Calculate sums
        foreach ($evaluations as $eval) {
            foreach ($questions as $key => $data) {
                $questions[$key]['sum'] += ($eval[$key] ?? 0);
            }
        }

        // Calculate averages
        foreach ($questions as $key => $data) {
            $questions[$key]['avg'] = $data['sum'] / $totalStudents;
        }

        // Category averages
        $cat1_avg = 0;
        $cat2_avg = 0;
        $cat3_avg = 0;
        $cat4_avg = 0;

        foreach ($evaluations as $eval) {
            $cat1 = (($eval['q1_1'] ?? 0) + ($eval['q1_2'] ?? 0) + ($eval['q1_3'] ?? 0) + 
                    ($eval['q1_4'] ?? 0) + ($eval['q1_5'] ?? 0) + ($eval['q1_6'] ?? 0)) / 6;
            $cat2 = (($eval['q2_1'] ?? 0) + ($eval['q2_2'] ?? 0) + ($eval['q2_3'] ?? 0) + 
                    ($eval['q2_4'] ?? 0)) / 4;
            $cat3 = (($eval['q3_1'] ?? 0) + ($eval['q3_2'] ?? 0) + ($eval['q3_3'] ?? 0) + 
                    ($eval['q3_4'] ?? 0)) / 4;
            $cat4 = (($eval['q4_1'] ?? 0) + ($eval['q4_2'] ?? 0) + ($eval['q4_3'] ?? 0) + 
                    ($eval['q4_4'] ?? 0) + ($eval['q4_5'] ?? 0) + ($eval['q4_6'] ?? 0)) / 6;

            $cat1_avg += $cat1;
            $cat2_avg += $cat2;
            $cat3_avg += $cat3;
            $cat4_avg += $cat4;
        }

        $cat1_avg /= $totalStudents;
        $cat2_avg /= $totalStudents;
        $cat3_avg /= $totalStudents;
        $cat4_avg /= $totalStudents;

        $overall_avg = ($cat1_avg + $cat2_avg + $cat3_avg + $cat4_avg) / 4;

        // Create PDF
        $pdf = new EvaluationPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Teacher Evaluation System');
        $pdf->SetTitle("Summary Report - $teacherName - $program");

        $pdf->SetMargins(10, 30, 10);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 15);

        $pdf->AddPage();

        // Header Information
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6, "Name: " . strtoupper($teacherName), 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, "Program: $program (ALL SECTIONS)", 0, 1);
        $pdf->Cell(0, 5, "Sections Included: $sectionsText", 0, 1);
        $pdf->Cell(0, 5, "Total Students Evaluated: $totalStudents", 0, 1);
        $pdf->Ln(5);

        // Table Header
        $pdf->SetFillColor(200, 200, 200);
        $pdf->SetFont('helvetica', 'B', 9);
        
        // Category 1: KAKAYAHAN SA PAGTUTURO
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(10, 7, '', 1, 0, 'C', true);
        $pdf->Cell(145, 7, 'KAKAYAHAN SA PAGTUTURO', 1, 0, 'L', true);
        $pdf->Cell(25, 7, 'MARKA', 1, 1, 'C', true);

        $pdf->SetFont('helvetica', '', 8);
        $counter = 1;
        foreach (['q1_1', 'q1_2', 'q1_3', 'q1_4', 'q1_5', 'q1_6'] as $q) {
            $pdf->Cell(10, 6, '1.' . $counter, 1, 0, 'C');
            $pdf->MultiCell(145, 6, $questions[$q]['label'], 1, 'L');
            $pdf->SetXY($pdf->GetX() + 155, $pdf->GetY() - 6);
            $pdf->Cell(25, 6, number_format($questions[$q]['avg'], 2), 1, 1, 'C');
            $counter++;
        }

        // Category 2: KASANAYAN SA PAMAMAHALA
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(10, 7, '', 1, 0, 'C', true);
        $pdf->Cell(145, 7, 'KASANAYAN SA PAMAMAHALA', 1, 0, 'L', true);
        $pdf->Cell(25, 7, '', 1, 1, 'C', true);

        $pdf->SetFont('helvetica', '', 8);
        $counter = 1;
        foreach (['q2_1', 'q2_2', 'q2_3', 'q2_4'] as $q) {
            $pdf->Cell(10, 6, '2.' . $counter, 1, 0, 'C');
            $pdf->MultiCell(145, 6, $questions[$q]['label'], 1, 'L');
            $pdf->SetXY($pdf->GetX() + 155, $pdf->GetY() - 6);
            $pdf->Cell(25, 6, number_format($questions[$q]['avg'], 2), 1, 1, 'C');
            $counter++;
        }

        // Category 3: MGA KASANAYAN SA PAGGABAY
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(10, 7, '', 1, 0, 'C', true);
        $pdf->Cell(145, 7, 'MGA KASANAYAN SA PAGGABAY', 1, 0, 'L', true);
        $pdf->Cell(25, 7, '', 1, 1, 'C', true);

        $pdf->SetFont('helvetica', '', 8);
        $counter = 1;
        foreach (['q3_1', 'q3_2', 'q3_3', 'q3_4'] as $q) {
            $pdf->Cell(10, 6, '3.' . $counter, 1, 0, 'C');
            $pdf->MultiCell(145, 6, $questions[$q]['label'], 1, 'L');
            $pdf->SetXY($pdf->GetX() + 155, $pdf->GetY() - 6);
            $pdf->Cell(25, 6, number_format($questions[$q]['avg'], 2), 1, 1, 'C');
            $counter++;
        }

        // Category 4: PERSONAL AT PANLIPUNANG KATANGIAN
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(10, 7, '', 1, 0, 'C', true);
        $pdf->Cell(145, 7, 'PERSONAL AT PANLIPUNANG KATANGIAN', 1, 0, 'L', true);
        $pdf->Cell(25, 7, '', 1, 1, 'C', true);

        $pdf->SetFont('helvetica', '', 8);
        $counter = 1;
        foreach (['q4_1', 'q4_2', 'q4_3', 'q4_4', 'q4_5', 'q4_6'] as $q) {
            $pdf->Cell(10, 6, '4.' . $counter, 1, 0, 'C');
            $pdf->MultiCell(145, 6, $questions[$q]['label'], 1, 'L');
            $pdf->SetXY($pdf->GetX() + 155, $pdf->GetY() - 6);
            $pdf->Cell(25, 6, number_format($questions[$q]['avg'], 2), 1, 1, 'C');
            $counter++;
        }

        // TOTAL
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(255, 200, 150);
        $pdf->Cell(155, 8, 'TOTAL', 1, 0, 'R', true);
        $pdf->Cell(25, 8, number_format($overall_avg, 2), 1, 1, 'C', true);

        $pdf->Ln(5);

        // Rating Legend
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'RATING SCALE:', 0, 1);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, '4.50 - 5.00 = Outstanding', 0, 1);
        $pdf->Cell(0, 5, '3.50 - 4.49 = Very Satisfactory', 0, 1);
        $pdf->Cell(0, 5, '2.50 - 3.49 = Satisfactory', 0, 1);
        $pdf->Cell(0, 5, '1.50 - 2.49 = Fair', 0, 1);
        $pdf->Cell(0, 5, '1.00 - 1.49 = Poor', 0, 1);

        // Save PDF
        $pdf->Output($outputPath, 'F');
        return true;

    } catch (Exception $e) {
        error_log("Error generating summary report: " . $e->getMessage());
        return false;
    }
}
?>
