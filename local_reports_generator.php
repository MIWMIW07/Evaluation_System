<?php
// local_reports_generator.php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

ob_start();

try {
    header('Content-Type: application/json');
    
    require_once 'includes/db_connection.php';
    
    if (!class_exists('TCPDF')) {
        require_once __DIR__ . '/tcpdf/tcpdf.php';
    }
    
    class EvaluationPDF extends TCPDF {
        public function Header() {
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
    
    $reportsDir = __DIR__ . '/reports/Teacher Evaluation Reports/Reports/';
    if (!file_exists($reportsDir)) {
        mkdir($reportsDir, 0777, true);
    }

    $stmt = $pdo->query("
        SELECT DISTINCT 
            teacher_name, 
            program, 
            section 
        FROM evaluations 
        ORDER BY teacher_name, program, section
    ");
    $combinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    foreach ($combinations as $combo) {
        $teacherName = $combo['teacher_name'];
        $program = $combo['program'];
        $section = $combo['section'];

        $teacherDir = $reportsDir . $teacherName . '/';
        if (!file_exists($teacherDir)) {
            mkdir($teacherDir, 0777, true);
        }

        $programDir = $teacherDir . $program . '/';
        if (!file_exists($programDir)) {
            mkdir($programDir, 0777, true);
        }

        if (!in_array($teacherName, $teachersProcessed)) {
            $teachersProcessed[] = $teacherName;
        }

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

    foreach ($summaryCombinations as $combo) {
        $teacherName = $combo['teacher_name'];
        $program = $combo['program'];

        $teacherDir = $reportsDir . $teacherName . '/';
        $programDir = $teacherDir . $program . '/';

        $summaryFilename = $programDir . 'Summary_' . $program . '_ALL_SECTIONS.pdf';
        if (generateSummaryReport($pdo, $teacherName, $program, $summaryFilename)) {
            $summaryReports++;
            $totalFiles++;
        }
    }

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
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6, "Name: " . strtoupper($evaluation['teacher_name']), 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, "Student: " . $evaluation['student_name'], 0, 1);
        $pdf->Cell(0, 5, "Program: " . $evaluation['program'] . " | Section: " . $evaluation['section'], 0, 1);
        $pdf->Cell(0, 5, "Date: " . date('F j, Y', strtotime($evaluation['submitted_at'])), 0, 1);
        $pdf->Ln(5);

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

        $averageScore = $questionCount > 0 ? $totalScore / $questionCount : 0;
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(255, 200, 150);
        $pdf->Cell(165, 8, 'AVERAGE SCORE', 1, 0, 'R', true);
        $pdf->Cell(25, 8, number_format($averageScore, 2), 1, 1, 'C', true);

        $pdf->Ln(5);

        // REPLACED RATING SCALE WITH COMMENTS TABLE
        $positiveComments = !empty(trim($evaluation['positive_comments'] ?? '')) ? $evaluation['positive_comments'] : '';
        $negativeComments = !empty(trim($evaluation['negative_comments'] ?? '')) ? $evaluation['negative_comments'] : '';

        if (!empty($positiveComments) || !empty($negativeComments)) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 6, 'STUDENT COMMENTS:', 0, 1);
            $pdf->Ln(2);

            // Table header
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(200, 230, 200); // Light green for positive
            $pdf->Cell(95, 7, 'POSITIVE FEEDBACK', 1, 0, 'C', true);
            $pdf->SetFillColor(255, 200, 200); // Light red for negative
            $pdf->Cell(95, 7, 'AREAS FOR IMPROVEMENT', 1, 1, 'C', true);

            // Calculate row height based on content
            $positiveHeight = $pdf->getStringHeight(95, $positiveComments, true, true, '', 1);
            $negativeHeight = $pdf->getStringHeight(95, $negativeComments, true, true, '', 1);
            $rowHeight = max($positiveHeight, $negativeHeight, 10); // Minimum height of 10

            // Comments content
            $pdf->SetFont('helvetica', '', 8);
            $pdf->MultiCell(95, $rowHeight, $positiveComments, 1, 'L', false, 0);
            $pdf->MultiCell(95, $rowHeight, $negativeComments, 1, 'L', false, 1);
        } else {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 6, 'STUDENT COMMENTS:', 0, 1);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 6, 'No comments provided by student.', 0, 1, 'C');
        }

        $pdf->Output($outputPath, 'F');
        return true;

    } catch (Exception $e) {
        error_log("Error generating individual report: " . $e->getMessage());
        return false;
    }
}

function generateSummaryReport($pdo, $teacherName, $program, $outputPath) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM evaluations 
            WHERE teacher_name = ? AND program = ?
        ");
        $stmt->execute([$teacherName, $program]);
        $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($evaluations)) {
            return false;
        }

        $sections = array_unique(array_column($evaluations, 'section'));
        sort($sections);
        $sectionsText = implode(', ', $sections);

        $totalStudents = count($evaluations);
        
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

        foreach ($evaluations as $eval) {
            foreach ($questions as $key => $data) {
                $questions[$key]['sum'] += ($eval[$key] ?? 0);
            }
        }

        foreach ($questions as $key => $data) {
            $questions[$key]['avg'] = $data['sum'] / $totalStudents;
        }

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

        $pdf = new EvaluationPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Teacher Evaluation System');
        $pdf->SetTitle("Summary Report - $teacherName - $program");

        $pdf->SetMargins(10, 30, 10);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 15);

        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6, "Name: " . strtoupper($teacherName), 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, "Program: $program (ALL SECTIONS)", 0, 1);
        $pdf->Cell(0, 5, "Sections Included: $sectionsText", 0, 1);
        $pdf->Cell(0, 5, "Total Students Evaluated: $totalStudents", 0, 1);
        $pdf->Ln(5);

        $pdf->SetFillColor(200, 200, 200);
        $pdf->SetFont('helvetica', 'B', 9);
        
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

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(255, 200, 150);
        $pdf->Cell(155, 8, 'TOTAL', 1, 0, 'R', true);
        $pdf->Cell(25, 8, number_format($overall_avg, 2), 1, 1, 'C', true);

        $pdf->Ln(5);

        // REPLACED RATING SCALE WITH COMMENTS SUMMARY TABLE
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'STUDENT COMMENTS SUMMARY:', 0, 1);
        $pdf->Ln(2);

        // Collect all comments
        $allPositiveComments = [];
        $allNegativeComments = [];

        foreach ($evaluations as $eval) {
            $positiveComment = !empty(trim($eval['positive_comments'] ?? '')) ? $eval['positive_comments'] : null;
            $negativeComment = !empty(trim($eval['negative_comments'] ?? '')) ? $eval['negative_comments'] : null;

            if ($positiveComment) {
                $allPositiveComments[] = [
                    'comment' => $positiveComment,
                    'student' => $eval['student_name'],
                    'section' => $eval['section']
                ];
            }

            if ($negativeComment) {
                $allNegativeComments[] = [
                    'comment' => $negativeComment,
                    'student' => $eval['student_name'],
                    'section' => $eval['section']
                ];
            }
        }

        // Calculate statistics
        $totalWithPositive = count($allPositiveComments);
        $totalWithNegative = count($allNegativeComments);
        $totalWithAnyComment = count(array_filter($evaluations, function($eval) {
            return !empty(trim($eval['positive_comments'] ?? '')) || !empty(trim($eval['negative_comments'] ?? ''));
        }));

        // Comments Statistics
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 6, 'Comments Statistics:', 0, 1);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 5, "Evaluations with Positive Comments: $totalWithPositive ($totalStudents total)", 0, 1);
        $pdf->Cell(0, 5, "Evaluations with Negative Comments: $totalWithNegative ($totalStudents total)", 0, 1);
        $pdf->Cell(0, 5, "Evaluations with Any Comments: $totalWithAnyComment ($totalStudents total)", 0, 1);
        $pdf->Ln(3);

        // Display comments in a table if there are any
        if (!empty($allPositiveComments) || !empty($allNegativeComments)) {
            // Table header
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(200, 230, 200); // Light green for positive
            $pdf->Cell(95, 6, 'POSITIVE FEEDBACK COMMENTS', 1, 0, 'C', true);
            $pdf->SetFillColor(255, 200, 200); // Light red for negative
            $pdf->Cell(95, 6, 'AREAS FOR IMPROVEMENT COMMENTS', 1, 1, 'C', true);

            $pdf->SetFont('helvetica', '', 7);
            
            // Determine max rows to display
            $maxRows = max(count($allPositiveComments), count($allNegativeComments));
            $maxRows = min($maxRows, 20); // Limit to 20 rows to prevent overflow

            for ($i = 0; $i < $maxRows; $i++) {
                $positiveData = $allPositiveComments[$i] ?? null;
                $negativeData = $allNegativeComments[$i] ?? null;

                $positiveText = $positiveData ? 
                    "[" . $positiveData['student'] . " - " . $positiveData['section'] . "]\n" . 
                    substr($positiveData['comment'], 0, 80) . (strlen($positiveData['comment']) > 80 ? '...' : '') : 
                    '';

                $negativeText = $negativeData ? 
                    "[" . $negativeData['student'] . " - " . $negativeData['section'] . "]\n" . 
                    substr($negativeData['comment'], 0, 80) . (strlen($negativeData['comment']) > 80 ? '...' : '') : 
                    '';

                // Calculate row height
                $positiveHeight = $pdf->getStringHeight(95, $positiveText, true, true, '', 1);
                $negativeHeight = $pdf->getStringHeight(95, $negativeText, true, true, '', 1);
                $rowHeight = max($positiveHeight, $negativeHeight, 8);

                $pdf->MultiCell(95, $rowHeight, $positiveText, 1, 'L', false, 0);
                $pdf->MultiCell(95, $rowHeight, $negativeText, 1, 'L', false, 1);
            }

            // Note if there are more comments
            if (count($allPositiveComments) > 20 || count($allNegativeComments) > 20) {
                $pdf->SetFont('helvetica', 'I', 7);
                $pdf->Cell(0, 5, '* Displaying first 20 comments only. See individual reports for complete comments.', 0, 1, 'C');
            }
        } else {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 6, 'No comments provided by students.', 0, 1, 'C');
        }

        $pdf->Output($outputPath, 'F');
        return true;

    } catch (Exception $e) {
        error_log("Error generating summary report: " . $e->getMessage());
        return false;
    }
}
?>
