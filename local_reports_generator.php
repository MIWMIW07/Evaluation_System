<?php
// This replaces the generateSummaryReport function in local_reports_generator.php

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
        $pdf->SetAuthor(PDF_AUTHOR);
        $pdf->SetTitle("Summary Report - $teacherName - $program $section");

        $pdf->SetHeaderData('', 0, "PHILIPPINE TECHNOLOGICAL INSTITUTE", 
                           "GMA-BRANCH [2nd Semester 2024-2025]\nFACULTY EVALUATION CRITERIA");

        $pdf->SetMargins(10, 30, 10);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 15);

        $pdf->AddPage();

        // Header Information
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6, "Name: " . strtoupper($teacherName), 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, "Program: $program | Section: $section", 0, 1);
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
