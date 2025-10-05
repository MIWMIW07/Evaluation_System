<?php
// local_reports_generator.php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

ob_start();

// Custom rounding function
function customRound($score) {
    $decimal = $score - floor($score);
    if ($decimal <= 0.5) {
        return floor($score);
    } else {
        return ceil($score);
    }
}

// Function to get rating description
function getRatingDescription($score) {
    switch ($score) {
        case 5: return 'Outstanding';
        case 4: return 'Very Satisfactory';
        case 3: return 'Satisfactory';
        case 2: return 'Fair';
        case 1: return 'Poor';
        default: return 'Not Rated';
    }
}

// Function to get interpretation for teaching competence
function getTeachingInterpretation($score) {
    switch ($score) {
        case 5: return 'The teacher demonstrates Outstanding teaching competence. Lessons are well-prepared, clearly delivered, and enriched with effective instructional strategies. The teacher shows mastery of the subject matter and connects lessons with real-life applications, resulting in active and meaningful student learning.';
        case 4: return 'The teacher exhibits Very Satisfactory teaching competence. Lessons are clearly discussed, and students are effectively engaged. The teacher demonstrates good command of the subject matter and uses suitable strategies to support learning.';
        case 3: return 'The teacher shows Satisfactory performance in teaching. Instructional methods are adequate, but further improvement in delivery and student engagement is encouraged.';
        case 2: return 'The teacher\'s teaching competence is Fair. There are areas that need improvement, such as lesson organization and clarity of instruction. Coaching or additional training may help enhance performance.';
        case 1: return 'The teacher\'s teaching competence is rated Poor. Lessons may lack structure or engagement. Immediate mentoring and professional development are highly recommended.';
        default: return 'Not rated.';
    }
}

// Function to get interpretation for management skills
function getManagementInterpretation($score) {
    switch ($score) {
        case 5: return 'The teacher demonstrates Outstanding management skills. A well-disciplined, safe, and motivating classroom environment is consistently maintained. Students show respect and positive behavior, reflecting strong classroom leadership.';
        case 4: return 'The teacher shows Very Satisfactory management ability. Classroom procedures are well-implemented, and a conducive learning environment is sustained. The teacher handles students with fairness and professionalism.';
        case 3: return 'The teacher\'s management skills are Satisfactory. Classroom order and discipline are generally maintained, though consistency and student engagement can still be improved.';
        case 2: return 'The teacher\'s management skills are Fair. Some issues in maintaining classroom control or organization may affect learning efficiency. Support and mentoring are suggested.';
        case 1: return 'The teacher\'s management skills are Poor. The classroom environment may not be conducive to learning. Immediate intervention and training are needed.';
        default: return 'Not rated.';
    }
}

// Function to get interpretation for guidance skills
function getGuidanceInterpretation($score) {
    switch ($score) {
        case 5: return 'The teacher exhibits Outstanding guidance skills. Genuine concern for students\' personal growth and well-being is evident. The teacher provides fair and empathetic support, encouraging students to be confident and self-disciplined.';
        case 4: return 'The teacher demonstrates Very Satisfactory guidance skills. Students feel supported and respected, and the teacher shows fairness and understanding in handling their concerns.';
        case 3: return 'The teacher\'s guidance skills are Satisfactory. The teacher provides adequate student support but can strengthen counseling and motivational approaches.';
        case 2: return 'The teacher\'s guidance skills are Fair. Limited engagement with students\' personal and academic issues is observed. More effort in student interaction and empathy is encouraged.';
        case 1: return 'The teacher\'s guidance skills are Poor. Minimal concern or support for students\' well-being is perceived. Training on student relations and counseling is recommended.';
        default: return 'Not rated.';
    }
}

// Function to get interpretation for personal qualities
function getPersonalInterpretation($score) {
    switch ($score) {
        case 5: return 'The teacher displays Outstanding personal and social qualities. Professionalism, emotional balance, and enthusiasm are consistently evident. The teacher maintains neat grooming, clear communication, and harmonious relationships with students and colleagues.';
        case 4: return 'The teacher exhibits Very Satisfactory personal and social qualities. Professional conduct, good communication, and positive interpersonal skills are consistently observed.';
        case 3: return 'The teacher shows Satisfactory personal and social qualities. The teacher interacts well but may still enhance emotional stability or professional presentation.';
        case 2: return 'The teacher\'s personal and social qualities are Fair. Improvement is needed in maintaining professionalism, communication clarity, and social interactions.';
        case 1: return 'The teacher\'s personal and social qualities are Poor. Lack of emotional balance or professionalism may be evident. Immediate development through mentoring is advised.';
        default: return 'Not rated.';
    }
}

// Function to get overall interpretation
function getOverallInterpretation($score) {
    switch ($score) {
        case 5: return 'The teacher\'s Overall Performance is Outstanding. This reflects exceptional competence across all areas — teaching, management, guidance, and personal qualities. The teacher consistently exceeds expectations and serves as an excellent role model.';
        case 4: return 'The teacher\'s Overall Performance is Very Satisfactory. The teacher meets and often exceeds expectations, demonstrating effective teaching, sound classroom management, and good rapport with students.';
        case 3: return 'The teacher\'s Overall Performance is Satisfactory. The teacher meets the minimum standards and performs adequately but would benefit from ongoing professional development.';
        case 2: return 'The teacher\'s Overall Performance is Fair. Certain areas require improvement. Focused support and guidance are recommended.';
        case 1: return 'The teacher\'s Overall Performance is Poor. Immediate intervention and professional coaching are necessary to improve competency and effectiveness.';
        default: return 'Not rated.';
    }
}

try {
    header('Content-Type: application/json');
    
    require_once 'includes/db_connection.php';
    
    if (!class_exists('TCPDF')) {
        require_once __DIR__ . '/tcpdf/tcpdf.php';
    }
    
    class EvaluationPDF extends TCPDF {
        public function Header() {
            // Add logo to the header
            $logoPath = __DIR__ . '/images/logo-original.png';
            if (file_exists($logoPath)) {
                $this->Image($logoPath, 10, 5, 20, 20, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
                $this->SetX(35); // Move right after logo
            } else {
                $this->SetX(10);
            }
            
            $this->SetFont('helvetica', 'B', 14);
            $this->Cell(0, 10, 'PHILIPPINE TECHNOLOGICAL INSTITUTE OF SCIENCE ARTS AND TRADE, INC.', 0, 1, 'C');
            $this->SetFont('helvetica', '', 10);
            $this->Cell(0, 5, 'GMA-BRANCH (1ST Semester 2025-2026)', 0, 1, 'C');
            $this->Ln(5);
        }
        
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
        }
        
        // Add cover page method matching the screenshot
        public function AddEvaluationCoverPage($teacherName, $program, $teachingScore, $managementScore, $guidanceScore, $personalScore, $overallScore) {
            $this->AddPage();
            
            // Title
            $this->SetFont('helvetica', 'B', 16);
            $this->Cell(0, 15, 'Teacher Evaluation by the students result', 0, 1, 'C');
            $this->Ln(10);
            
            // Evaluation Table
            $this->SetFont('helvetica', 'B', 11);
            
            // Table Header
            $this->SetFillColor(200, 200, 200);
            $this->Cell(50, 10, 'Indicators', 1, 0, 'C', true);
            $this->Cell(25, 10, 'Rating', 1, 0, 'C', true);
            $this->Cell(45, 10, 'Description', 1, 0, 'C', true);
            $this->Cell(70, 10, 'Interpretation', 1, 1, 'C', true);
            
            $this->SetFont('helvetica', '', 9);
            
            // Teaching Competencies Row
            $this->Cell(50, 8, '1. Teaching Competencies', 1, 0, 'L');
            $this->Cell(25, 8, $teachingScore, 1, 0, 'C');
            $this->Cell(45, 8, getRatingDescription($teachingScore), 1, 0, 'C');
            $this->MultiCell(70, 4, getTeachingInterpretation($teachingScore), 1, 'L');
            
            // Management Skills Row
            $this->Cell(50, 8, '2. Management Skills', 1, 0, 'L');
            $this->Cell(25, 8, $managementScore, 1, 0, 'C');
            $this->Cell(45, 8, getRatingDescription($managementScore), 1, 0, 'C');
            $this->MultiCell(70, 4, getManagementInterpretation($managementScore), 1, 'L');
            
            // Guidance Skills Row
            $this->Cell(50, 8, '3. Guidance Skills', 1, 0, 'L');
            $this->Cell(25, 8, $guidanceScore, 1, 0, 'C');
            $this->Cell(45, 8, getRatingDescription($guidanceScore), 1, 0, 'C');
            $this->MultiCell(70, 4, getGuidanceInterpretation($guidanceScore), 1, 'L');
            
            // Personal and Social Qualities/Skills Row
            $this->Cell(50, 8, '4. Personal and Social Qualities/Skills', 1, 0, 'L');
            $this->Cell(25, 8, $personalScore, 1, 0, 'C');
            $this->Cell(45, 8, getRatingDescription($personalScore), 1, 0, 'C');
            $this->MultiCell(70, 4, getPersonalInterpretation($personalScore), 1, 'L');
            
            // Overall Performance Row (bold)
            $this->SetFont('helvetica', 'B', 9);
            $this->SetFillColor(220, 220, 220);
            $this->Cell(50, 8, 'Overall Performance', 1, 0, 'L', true);
            $this->Cell(25, 8, $overallScore, 1, 0, 'C', true);
            $this->Cell(45, 8, getRatingDescription($overallScore), 1, 0, 'C', true);
            $this->MultiCell(70, 4, getOverallInterpretation($overallScore), 1, 'L', true);
            
            $this->Ln(8);
            
            // Rating Scale
            $this->SetFont('helvetica', '', 9);
            $this->Cell(0, 6, 'Rating used: 5 - Outstanding 4 - Very Satisfactory 3 - Satisfactory 2 - Fair 1 - Poor', 0, 1, 'L');
            
            $this->Ln(15);
            
            // Signature Sections - UPDATED TO MATCH YOUR IMAGE
            $currentY = $this->GetY();
            
            // Tabulated by section (Left side)
            $this->SetFont('helvetica', 'B', 11);
            $this->Cell(80, 8, 'Tabulated by :', 0, 1, 'L');
            $this->Ln(5);
            
            // Add Joanne P. Castro signature
            $signature1Path = __DIR__ . '/images/Picture2.png';
            if (file_exists($signature1Path)) {
                $this->Image($signature1Path, 20, $this->GetY(), 40, 15, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
            $this->SetY($this->GetY() + 18);
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(80, 6, 'Joanne P. Castro', 0, 1, 'L');
            $this->SetFont('helvetica', '', 9);
            $this->Cell(80, 6, 'Guidance Associate', 0, 1, 'L');
            
            // Reset Y position for second signature
            $this->SetY($currentY);
            
            // Noted by section (Right side)
            $this->SetX(110);
            $this->SetFont('helvetica', 'B', 11);
            $this->Cell(80, 8, 'Noted by :', 0, 1, 'L');
            $this->SetX(110);
            $this->Ln(5);
            
            // Add Myra V. Jumantoc signature
            $signature2Path = __DIR__ . '/images/Picture1.png';
            if (file_exists($signature2Path)) {
                $this->Image($signature2Path, 120, $this->GetY(), 40, 15, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
            $this->SetY($this->GetY() + 18);
            $this->SetX(110);
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(80, 6, 'Myra V. Jumantoc', 0, 1, 'L');
            $this->SetX(110);
            $this->SetFont('helvetica', '', 9);
            $this->Cell(80, 6, 'HR Head', 0, 1, 'L');
        }
    }

    $pdo = getPDO();
    
    $reportsDir = __DIR__ . '/reports/Teacher Evaluation Reports/Reports/';
    if (!file_exists($reportsDir)) {
        mkdir($reportsDir, 0777, true);
    }

    // Create images directory if it doesn't exist
    $imagesDir = __DIR__ . '/images/';
    if (!file_exists($imagesDir)) {
        mkdir($imagesDir, 0777, true);
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
        
        $pdf->SetMargins(10, 40, 10);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Calculate scores for the cover page
        $teachingScore = customRound(($evaluation['q1_1'] + $evaluation['q1_2'] + $evaluation['q1_3'] + 
                                    $evaluation['q1_4'] + $evaluation['q1_5'] + $evaluation['q1_6']) / 6);
        
        $managementScore = customRound(($evaluation['q2_1'] + $evaluation['q2_2'] + 
                                      $evaluation['q2_3'] + $evaluation['q2_4']) / 4);
        
        $guidanceScore = customRound(($evaluation['q3_1'] + $evaluation['q3_2'] + 
                                    $evaluation['q3_3'] + $evaluation['q3_4']) / 4);
        
        $personalScore = customRound(($evaluation['q4_1'] + $evaluation['q4_2'] + $evaluation['q4_3'] + 
                                    $evaluation['q4_4'] + $evaluation['q4_5'] + $evaluation['q4_6']) / 6);
        
        $overallScore = customRound(($teachingScore + $managementScore + $guidanceScore + $personalScore) / 4);
        
        // Add cover page with evaluation results
        $pdf->AddEvaluationCoverPage($evaluation['teacher_name'], $evaluation['program'], 
                                   $teachingScore, $managementScore, $guidanceScore, $personalScore, $overallScore);
        
        // Start detailed evaluation content on page 2
        $pdf->AddPage();
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6, "Name: " . strtoupper($evaluation['teacher_name']), 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, "Student: " . $evaluation['student_name'], 0, 1);
        $pdf->Cell(0, 5, "Program: " . $evaluation['program'] . " | Section: " . $evaluation['section'], 0, 1);
        $pdf->Cell(0, 5, "Date: " . date('F j, Y', strtotime($evaluation['submitted_at'])), 0, 1);
        $pdf->Ln(5);

        // English questions
        $questions = [
            'TEACHING COMPETENCE' => [
                'q1_1' => 'Analyzes and explains lessons without reading from the book in class',
                'q1_2' => 'Uses audio-visual and devices to support teaching',
                'q1_3' => 'Presents ideas/concepts clearly and convincingly',
                'q1_4' => 'Allows students to use concepts',
                'q1_5' => 'Gives fair tests and returns results',
                'q1_6' => 'Teaches effectively using proper language',
            ],
            'MANAGEMENT SKILLS' => [
                'q2_1' => 'Maintains orderly, disciplined and safe classroom',
                'q2_2' => 'Follows systematic class schedule',
                'q2_3' => 'Instills respect and courtesy in students',
                'q2_4' => 'Allows students to express their opinions',
            ],
            'GUIDANCE SKILLS' => [
                'q3_1' => 'Accepts students as individuals',
                'q3_2' => 'Shows confidence and self-composure',
                'q3_3' => 'Manages class and student problems',
                'q3_4' => 'Shows genuine concern for personal matters',
            ],
            'PERSONAL AND SOCIAL ATTRIBUTES' => [
                'q4_1' => 'Maintains emotional balance; not overly critical',
                'q4_2' => 'Free from habitual movements that disrupt the process',
                'q4_3' => 'Neat and presentable; Clean and orderly clothes',
                'q4_4' => 'Does not show favoritism',
                'q4_5' => 'Has good sense of humor and shows vitality',
                'q4_6' => 'Has good diction, clear and proper voice modulation',
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
        $roundedAverage = customRound($averageScore);
        
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(255, 200, 150);
        $pdf->Cell(165, 8, 'AVERAGE SCORE', 1, 0, 'R', true);
        $pdf->Cell(25, 8, $roundedAverage, 1, 1, 'C', true);

        $pdf->Ln(5);

        // Comments section
        $positiveComments = !empty(trim($evaluation['positive_comments'] ?? '')) ? $evaluation['positive_comments'] : '';
        $negativeComments = !empty(trim($evaluation['negative_comments'] ?? '')) ? $evaluation['negative_comments'] : '';

        if (!empty($positiveComments) || !empty($negativeComments)) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 6, 'STUDENT COMMENTS:', 0, 1);
            $pdf->Ln(2);

            if (!empty($positiveComments)) {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetFillColor(200, 230, 200);
                $pdf->Cell(0, 7, 'POSITIVE FEEDBACK', 1, 1, 'L', true);
                $pdf->SetFont('helvetica', '', 8);
                $pdf->MultiCell(0, 5, $positiveComments, 1, 'L');
                $pdf->Ln(3);
            }

            if (!empty($negativeComments)) {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetFillColor(255, 200, 200);
                $pdf->Cell(0, 7, 'AREAS FOR IMPROVEMENT', 1, 1, 'L', true);
                $pdf->SetFont('helvetica', '', 8);
                $pdf->MultiCell(0, 5, $negativeComments, 1, 'L');
            }
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
        
        // Calculate average scores for each question
        $questions = [
            'q1_1' => ['sum' => 0, 'label' => 'Analyzes and explains lessons without reading from the book in class'],
            'q1_2' => ['sum' => 0, 'label' => 'Uses audio-visual and devices to support teaching'],
            'q1_3' => ['sum' => 0, 'label' => 'Presents ideas/concepts clearly and convincingly'],
            'q1_4' => ['sum' => 0, 'label' => 'Allows students to use concepts'],
            'q1_5' => ['sum' => 0, 'label' => 'Gives fair tests and returns results'],
            'q1_6' => ['sum' => 0, 'label' => 'Teaches effectively using proper language'],
            
            'q2_1' => ['sum' => 0, 'label' => 'Maintains orderly, disciplined and safe classroom'],
            'q2_2' => ['sum' => 0, 'label' => 'Follows systematic class schedule'],
            'q2_3' => ['sum' => 0, 'label' => 'Instills respect and courtesy in students'],
            'q2_4' => ['sum' => 0, 'label' => 'Allows students to express their opinions'],
            
            'q3_1' => ['sum' => 0, 'label' => 'Accepts students as individuals'],
            'q3_2' => ['sum' => 0, 'label' => 'Shows confidence and self-composure'],
            'q3_3' => ['sum' => 0, 'label' => 'Manages class and student problems'],
            'q3_4' => ['sum' => 0, 'label' => 'Shows genuine concern for personal matters'],
            
            'q4_1' => ['sum' => 0, 'label' => 'Maintains emotional balance; not overly critical'],
            'q4_2' => ['sum' => 0, 'label' => 'Free from habitual movements that disrupt the process'],
            'q4_3' => ['sum' => 0, 'label' => 'Neat and presentable; Clean and orderly clothes'],
            'q4_4' => ['sum' => 0, 'label' => 'Does not show favoritism'],
            'q4_5' => ['sum' => 0, 'label' => 'Has good sense of humor and shows vitality'],
            'q4_6' => ['sum' => 0, 'label' => 'Has good diction, clear and proper voice modulation'],
        ];

        foreach ($evaluations as $eval) {
            foreach ($questions as $key => $data) {
                $questions[$key]['sum'] += ($eval[$key] ?? 0);
            }
        }

        foreach ($questions as $key => $data) {
            $questions[$key]['avg'] = $data['sum'] / $totalStudents;
        }

        // Calculate category averages for cover page
        $teachingScore = customRound(($questions['q1_1']['avg'] + $questions['q1_2']['avg'] + $questions['q1_3']['avg'] + 
                                    $questions['q1_4']['avg'] + $questions['q1_5']['avg'] + $questions['q1_6']['avg']) / 6);
        
        $managementScore = customRound(($questions['q2_1']['avg'] + $questions['q2_2']['avg'] + 
                                      $questions['q2_3']['avg'] + $questions['q2_4']['avg']) / 4);
        
        $guidanceScore = customRound(($questions['q3_1']['avg'] + $questions['q3_2']['avg'] + 
                                    $questions['q3_3']['avg'] + $questions['q3_4']['avg']) / 4);
        
        $personalScore = customRound(($questions['q4_1']['avg'] + $questions['q4_2']['avg'] + $questions['q4_3']['avg'] + 
                                    $questions['q4_4']['avg'] + $questions['q4_5']['avg'] + $questions['q4_6']['avg']) / 6);
        
        $overallScore = customRound(($teachingScore + $managementScore + $guidanceScore + $personalScore) / 4);

        $pdf = new EvaluationPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Teacher Evaluation System');
        $pdf->SetTitle("Summary Report - $teacherName - $program");

        $pdf->SetMargins(10, 40, 10);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 15);

        // Add cover page with evaluation results
        $pdf->AddEvaluationCoverPage($teacherName, $program, $teachingScore, $managementScore, $guidanceScore, $personalScore, $overallScore);

        // Start detailed content on page 2
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6, "Name: " . strtoupper($teacherName), 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, "Program: $program (ALL SECTIONS)", 0, 1);
        $pdf->Cell(0, 5, "Sections Included: $sectionsText", 0, 1);
        $pdf->Cell(0, 5, "Total Students Evaluated: $totalStudents", 0, 1);
        $pdf->Ln(5);

        // Rest of the detailed summary report content...
        // [Keep the existing detailed table content from your previous summary report function]

        $pdf->Output($outputPath, 'F');
        return true;

    } catch (Exception $e) {
        error_log("Error generating summary report: " . $e->getMessage());
        return false;
    }
}
?>
