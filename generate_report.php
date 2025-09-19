<?php
// generate_report.php - Enhanced to generate folder structure and individual evaluation reports
session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied. Admin privileges required.');
}

// Include database connection
require_once 'includes/db_connection.php';

// Check if connection exists
if (!isset($pdo)) {
    die("Database connection failed. Please check your configuration.");
}

// Function to create directory if it doesn't exist
function createDirectory($path) {
    if (!file_exists($path)) {
        mkdir($path, 0755, true);
        return true;
    }
    return false;
}

// Function to sanitize folder names
function sanitizeFolderName($name) {
    // Remove or replace invalid characters for folder names
    $sanitized = preg_replace('/[^a-zA-Z0-9\s\-_\.]/', '', $name);
    $sanitized = preg_replace('/\s+/', '_', trim($sanitized));
    return $sanitized;
}

// Function to generate individual evaluation HTML report
function generateIndividualEvaluationReport($evaluation_data, $teacher_data) {
    $html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Evaluation Report - ' . htmlspecialchars($teacher_data['name']) . '</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.4;
            color: #000;
            background: #fff;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        .institution-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .semester-info {
            font-size: 12px;
            margin-bottom: 15px;
        }
        
        .evaluation-title {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        
        .teacher-info {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
        }
        
        .teacher-info h3 {
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 5px;
            font-size: 12px;
        }
        
        .info-label {
            font-weight: bold;
            width: 120px;
            flex-shrink: 0;
        }
        
        .evaluation-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        
        .evaluation-table th,
        .evaluation-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        
        .evaluation-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        .section-header {
            background-color: #e9ecef !important;
            font-weight: bold;
            text-align: center;
        }
        
        .question-number {
            width: 40px;
            text-align: center;
            font-weight: bold;
        }
        
        .rating-cell {
            width: 60px;
            text-align: center;
            font-weight: bold;
        }
        
        .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .comments-section {
            margin-top: 20px;
            border: 1px solid #ccc;
            padding: 15px;
        }
        
        .comments-header {
            font-weight: bold;
            margin-bottom: 10px;
            background-color: #f0f0f0;
            padding: 5px;
            text-align: center;
        }
        
        .signature-section {
            margin-top: 30px;
            text-align: center;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            width: 200px;
            margin: 20px auto 5px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="institution-name">Philippine Technological Institute of Science Arts and Trade, Inc.</div>
        <div class="semester-info">GMA-BRANCH (2nd Semester 2024-2025)</div>
        <div class="evaluation-title">Faculty Evaluation Criteria</div>
        <div style="font-size: 12px; margin-top: 10px;">
            <strong>' . strtoupper($evaluation_data['program']) . ' STUDENTS</strong>
        </div>
    </div>
    
    <div class="teacher-info">
        <div class="info-row">
            <span class="info-label">Name:</span>
            <span>' . strtoupper(htmlspecialchars($teacher_data['name'])) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Subject Handled:</span>
            <span>' . htmlspecialchars($teacher_data['subject']) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Student Name:</span>
            <span>' . htmlspecialchars($evaluation_data['student_name']) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Student ID:</span>
            <span>' . htmlspecialchars($evaluation_data['student_id']) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Section:</span>
            <span>' . htmlspecialchars($evaluation_data['section']) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Program:</span>
            <span>' . htmlspecialchars($evaluation_data['program']) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Date Evaluated:</span>
            <span>' . date('F j, Y', strtotime($evaluation_data['evaluation_date'])) . '</span>
        </div>
    </div>
    
    <table class="evaluation-table">
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th>CRITERIA</th>
                <th style="width: 80px;">RATING</th>
            </tr>
        </thead>
        <tbody>';

    // Section 1: Teaching Competence
    $html .= '<tr class="section-header">
                <td colspan="3">1. KAKAYAHAN SA PAGTUTURO</td>
              </tr>';
    
    $section1_questions = [
        '1.1' => 'Nasuri at naipaliwanag ang aralin nang hindi binabasa ang aklat sa klase',
        '1.2' => 'Gumagamit ng audio-visual at mga device upang suportahan at mapadali ang pagtuturo',
        '1.3' => 'Nagpapakita ng mga ideya/konsepto nang malinaw at nakakakumbinsi mula sa mga kaugnay na larangan at isama ang subject matter sa aktual na karanasan',
        '1.4' => 'Hinahayaan ang mga mag-aaral na gumamit ng mga konsepto upang ipakita ang pag-unawa sa mga aralin',
        '1.5' => 'Nagbibigay ng patas na pagsusulit at pagsusuri at ibalik ang mga resulta ng pagsusulit sa loob ng makatwirang panahon',
        '1.6' => 'Naguutos nang maayos sa pagtuturo gamit ang maayos na panananlita'
    ];
    
    $section1_total = 0;
    foreach($section1_questions as $key => $question) {
        $field_name = 'q1_' . substr($key, -1);
        $rating = $evaluation_data[$field_name];
        $section1_total += $rating;
        $html .= '<tr>
                    <td class="question-number">' . $key . '</td>
                    <td>' . $question . '</td>
                    <td class="rating-cell">' . $rating . '</td>
                  </tr>';
    }

    // Section 2: Management Skills
    $html .= '<tr class="section-header">
                <td colspan="3">2. KASANAYAN SA PAMAMAHALA</td>
              </tr>';
    
    $section2_questions = [
        '2.1' => 'Pinapanatiling maayos, disiplinado at ligtas ang silid-aralan upang makameron ng maayos na pag-aaral',
        '2.2' => 'Sumusunod sa sistematikong iskedyul ng mga klase at iba pang pangaraw-araw na gawain',
        '2.3' => 'Hinuhubog sa mga mag-aaral ang respeto at paggalang sa mga guro',
        '2.4' => 'Pinahihintulutan ang mga mag-aaral na ipahayag ang kanilang mga opinyon at mga pananaw'
    ];
    
    $section2_total = 0;
    foreach($section2_questions as $key => $question) {
        $field_name = 'q2_' . substr($key, -1);
        $rating = $evaluation_data[$field_name];
        $section2_total += $rating;
        $html .= '<tr>
                    <td class="question-number">' . $key . '</td>
                    <td>' . $question . '</td>
                    <td class="rating-cell">' . $rating . '</td>
                  </tr>';
    }

    // Section 3: Guidance Skills
    $html .= '<tr class="section-header">
                <td colspan="3">3. MGA KASANAYAN SA PAGGABAY</td>
              </tr>';
    
    $section3_questions = [
        '3.1' => 'Pagtanggap sa mga mag-aaral bilang indibidwal na may kalakasan at kahinaan',
        '3.2' => 'Pagpapakita ng tiwala at kaayusan sa sarili',
        '3.3' => 'Pinangangasiwan ang problema ng klase at Mga mag-aaral nang may patas at pang-unawa',
        '3.4' => 'Nagpapakita ng tunay na pagmamalasakit sa mga personal at iba pang problemang ipinakita ng mga mag-aaral sa labas ng mga aktibidad sa silid-aralan'
    ];
    
    $section3_total = 0;
    foreach($section3_questions as $key => $question) {
        $field_name = 'q3_' . substr($key, -1);
        $rating = $evaluation_data[$field_name];
        $section3_total += $rating;
        $html .= '<tr>
                    <td class="question-number">' . $key . '</td>
                    <td>' . $question . '</td>
                    <td class="rating-cell">' . $rating . '</td>
                  </tr>';
    }

    // Section 4: Personal and Social Characteristics
    $html .= '<tr class="section-header">
                <td colspan="3">4. PERSONAL AT PANLIPUNANG KATANGIAN</td>
              </tr>';
    
    $section4_questions = [
        '4.1' => 'Nagpapanatil ng emosyonal na balanse: hindi masyadong kritikal o sobrang sensitibo',
        '4.2' => 'Malaya sa nakasanayang galaw na nakakagambala sa proseso ng pagtuturo at pagkatuto',
        '4.3' => 'Maayos at presentable; Malinis at maayos ang mga damit',
        '4.4' => 'Hindi pagpapakita ng paboritismo',
        '4.5' => 'May magandang sense of humor at nagpapakita ng sigla sa pagtuturo',
        '4.6' => 'May magandang diction, malinaw at maayos na timpla ng boses'
    ];
    
    $section4_total = 0;
    foreach($section4_questions as $key => $question) {
        $field_name = 'q4_' . substr($key, -1);
        $rating = $evaluation_data[$field_name];
        $section4_total += $rating;
        $html .= '<tr>
                    <td class="question-number">' . $key . '</td>
                    <td>' . $question . '</td>
                    <td class="rating-cell">' . $rating . '</td>
                  </tr>';
    }

    // Calculate totals and average
    $grand_total = $section1_total + $section2_total + $section3_total + $section4_total;
    $average = round($grand_total / 20, 2);

    $html .= '<tr class="total-row">
                <td></td>
                <td style="text-align: right; font-weight: bold;">TOTAL</td>
                <td class="rating-cell" style="background-color: #e9ecef;">' . number_format($average, 2) . '</td>
              </tr>';

    $html .= '</tbody></table>';

    // Comments section
    $html .= '<div class="comments-section">
                <div class="comments-header">5. KOMENTO SA GURO</div>';
    
    if (!empty($evaluation_data['comments'])) {
        // Split comments into positive and negative (simplified approach)
        $comments = htmlspecialchars($evaluation_data['comments']);
        $html .= '<div style="margin-bottom: 15px;">
                    <strong>Comments:</strong><br>
                    ' . nl2br($comments) . '
                  </div>';
    } else {
        $html .= '<div style="margin-bottom: 15px; font-style: italic; color: #666;">
                    No comments provided.
                  </div>';
    }

    $html .= '</div>';

    // Signature section
    $html .= '<div class="signature-section">
                <p>Tabulated/Encoded By:</p>
                <div class="signature-line"></div>
                <p style="font-size: 12px; margin-top: 5px;">Guidance Associate</p>
                <p style="font-size: 10px; margin-top: 10px; color: #666;">
                    Generated on: ' . date('F j, Y \a\t g:i A') . '
                </p>
              </div>
    
    <div class="no-print" style="margin-top: 30px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
            🖨️ Print Report
        </button>
    </div>
</body>
</html>';

    return $html;
}

try {
    // Get all evaluations with teacher names and organize by teacher and section
    $sql = "SELECT e.*, t.name as teacher_name, t.subject as teacher_subject
            FROM evaluations e 
            JOIN teachers t ON e.teacher_id = t.id 
            ORDER BY t.name, e.section, e.program, e.student_name";
    $result = query($sql);

    if (!$result) {
        throw new Exception("Query failed");
    }

    $results = fetch_all($result);
    
    if (empty($results)) {
        throw new Exception("No evaluations found to generate reports");
    }

    // Create main reports directory
    $reports_dir = 'reports';
    createDirectory($reports_dir);
    
    // Clear existing reports (optional - comment out if you want to keep old reports)
    if (is_dir($reports_dir)) {
        $files = glob($reports_dir . '/*');
        foreach($files as $file) {
            if (is_dir($file)) {
                // Remove directory recursively
                function removeDir($dir) {
                    $files = array_diff(scandir($dir), array('.', '..'));
                    foreach ($files as $file) {
                        (is_dir("$dir/$file")) ? removeDir("$dir/$file") : unlink("$dir/$file");
                    }
                    return rmdir($dir);
                }
                removeDir($file);
            }
        }
    }
    
    // Organize data by teacher and section
    $organized_data = [];
    foreach ($results as $row) {
        $teacher_name = $row['teacher_name'];
        $section = $row['section'];
        $program = $row['program'];
        
        if (!isset($organized_data[$teacher_name])) {
            $organized_data[$teacher_name] = [
                'teacher_info' => [
                    'name' => $teacher_name,
                    'subject' => $row['teacher_subject']
                ],
                'sections' => []
            ];
        }
        
        // Create section key with program info
        $section_key = $section . '_' . $program;
        
        if (!isset($organized_data[$teacher_name]['sections'][$section_key])) {
            $organized_data[$teacher_name]['sections'][$section_key] = [
                'section_info' => [
                    'section' => $section,
                    'program' => $program
                ],
                'evaluations' => []
            ];
        }
        
        $organized_data[$teacher_name]['sections'][$section_key]['evaluations'][] = $row;
    }
    
    $created_reports = 0;
    $created_folders = 0;
    
    // Generate folder structure and individual reports
    foreach ($organized_data as $teacher_name => $teacher_data) {
        // Create teacher folder
        $teacher_folder = $reports_dir . '/' . sanitizeFolderName($teacher_name);
        if (createDirectory($teacher_folder)) {
            $created_folders++;
        }
        
        foreach ($teacher_data['sections'] as $section_key => $section_data) {
            $section_info = $section_data['section_info'];
            
            // Create section folder
            $section_folder_name = $section_info['section'] . '_' . $section_info['program'];
            $section_folder = $teacher_folder . '/' . sanitizeFolderName($section_folder_name);
            if (createDirectory($section_folder)) {
                $created_folders++;
            }
            
            // Generate individual evaluation reports for each student in this section
            foreach ($section_data['evaluations'] as $evaluation) {
                // Create filename for individual evaluation
                $student_filename = sanitizeFolderName($evaluation['student_name']) . 
                                  '_' . $evaluation['student_id'] . 
                                  '_evaluation.html';
                $evaluation_file = $section_folder . '/' . $student_filename;
                
                // Generate HTML report
                $html_content = generateIndividualEvaluationReport($evaluation, $teacher_data['teacher_info']);
                
                // Save the report
                if (file_put_contents($evaluation_file, $html_content)) {
                    $created_reports++;
                }
            }
            
            // Create section summary report (optional)
            $section_summary_file = $section_folder . '/Section_Summary.html';
            $summary_html = generateSectionSummaryReport($section_data, $teacher_data['teacher_info']);
            file_put_contents($section_summary_file, $summary_html);
        }
    }
    
    // Also generate the traditional CSV report
    $csv_file = $reports_dir . '/complete_evaluation_data_' . date('Y-m-d_H-i-s') . '.csv';
    $csv_handle = fopen($csv_file, 'w');
    
    // Add BOM for proper Excel UTF-8 support
    fprintf($csv_handle, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add CSV headers
    fputcsv($csv_handle, array(
        'Teacher Name', 'Subject', 'Student ID', 'Student Name', 'Section', 'Program',
        'Q1.1', 'Q1.2', 'Q1.3', 'Q1.4', 'Q1.5', 'Q1.6',
        'Q2.1', 'Q2.2', 'Q2.3', 'Q2.4',
        'Q3.1', 'Q3.2', 'Q3.3', 'Q3.4',
        'Q4.1', 'Q4.2', 'Q4.3', 'Q4.4', 'Q4.5', 'Q4.6',
        'Comments', 'Evaluation Date', 'Average Rating'
    ));
    
    foreach ($results as $row) {
        $total_rating = $row['q1_1'] + $row['q1_2'] + $row['q1_3'] + $row['q1_4'] + $row['q1_5'] + $row['q1_6'] +
                       $row['q2_1'] + $row['q2_2'] + $row['q2_3'] + $row['q2_4'] +
                       $row['q3_1'] + $row['q3_2'] + $row['q3_3'] + $row['q3_4'] +
                       $row['q4_1'] + $row['q4_2'] + $row['q4_3'] + $row['q4_4'] + $row['q4_5'] + $row['q4_6'];
        $average_rating = round($total_rating / 20, 2);

        fputcsv($csv_handle, array(
            $row['teacher_name'], $row['teacher_subject'],
            $row['student_id'], $row['student_name'], $row['section'], $row['program'],
            $row['q1_1'], $row['q1_2'], $row['q1_3'], $row['q1_4'], $row['q1_5'], $row['q1_6'],
            $row['q2_1'], $row['q2_2'], $row['q2_3'], $row['q2_4'],
            $row['q3_1'], $row['q3_2'], $row['q3_3'], $row['q3_4'],
            $row['q4_1'], $row['q4_2'], $row['q4_3'], $row['q4_4'], $row['q4_5'], $row['q4_6'],
            $row['comments'], $row['evaluation_date'], $average_rating
        ));
    }
    
    fclose($csv_handle);
    
    // Generate summary page
    $summary_page = generateReportSummaryPage($created_reports, $created_folders, count($organized_data), $reports_dir);
    file_put_contents($reports_dir . '/index.html', $summary_page);
    
    // Show success page instead of downloading
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reports Generated Successfully</title>
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #333;
                line-height: 1.6;
                min-height: 100vh;
                padding: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .container {
                max-width: 800px;
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
                text-align: center;
            }
            
            .success-icon {
                font-size: 4em;
                color: #28a745;
                margin-bottom: 20px;
            }
            
            h1 {
                color: #2c3e50;
                margin-bottom: 20px;
            }
            
            .stats {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin: 20px 0;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }
            
            .stat-item {
                text-align: center;
            }
            
            .stat-number {
                font-size: 2em;
                font-weight: bold;
                color: #4CAF50;
            }
            
            .stat-label {
                color: #666;
                font-size: 0.9em;
            }
            
            .btn {
                background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
                color: white;
                padding: 12px 25px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-weight: bold;
                text-decoration: none;
                display: inline-block;
                margin: 10px 5px;
                transition: all 0.3s ease;
            }
            
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
            }
            
            .btn-secondary {
                background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            }
            
            .folder-structure {
                text-align: left;
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin: 20px 0;
                font-family: 'Courier New', monospace;
                font-size: 0.9em;
                max-height: 300px;
                overflow-y: auto;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success-icon">✅</div>
            <h1>Reports Generated Successfully!</h1>
            <p>Individual evaluation reports have been organized into folders by teacher and section.</p>
            
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $created_reports; ?></div>
                    <div class="stat-label">Individual Reports</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $created_folders; ?></div>
                    <div class="stat-label">Folders Created</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($organized_data); ?></div>
                    <div class="stat-label">Teachers</div>
                </div>
            </div>
            
            <div style="margin: 30px 0;">
                <h3>Folder Structure Created:</h3>
                <div class="folder-structure">
                    📁 reports/<br>
                    <?php
                    $folder_count = 0;
                    foreach ($organized_data as $teacher_name => $teacher_data) {
                        $folder_count++;
                        if ($folder_count <= 3) { // Show only first 3 teachers to avoid clutter
                            echo "├── 📁 " . sanitizeFolderName($teacher_name) . "/<br>";
                            $section_count = 0;
                            foreach ($teacher_data['sections'] as $section_key => $section_data) {
                                $section_count++;
                                $section_prefix = ($section_count == count($teacher_data['sections'])) ? "│   └──" : "│   ├──";
                                echo "$section_prefix 📁 " . sanitizeFolderName($section_data['section_info']['section'] . '_' . $section_data['section_info']['program']) . "/<br>";
                                
                                if ($section_count == 1) { // Show files for first section only
                                    $eval_count = 0;
                                    foreach ($section_data['evaluations'] as $evaluation) {
                                        $eval_count++;
                                        if ($eval_count <= 2) {
                                            $file_prefix = ($eval_count == min(2, count($section_data['evaluations']))) ? "│   │   └──" : "│   │   ├──";
                                            echo "$file_prefix 📄 " . sanitizeFolderName($evaluation['student_name']) . "_" . $evaluation['student_id'] . "_evaluation.html<br>";
                                        } elseif ($eval_count == 3) {
                                            echo "│   │   └── ... and " . (count($section_data['evaluations']) - 2) . " more files<br>";
                                        }
                                    }
                                }
                            }
                        } elseif ($folder_count == 4) {
                            echo "└── ... and " . (count($organized_data) - 3) . " more teacher folders<br>";
                        }
                    }
                    ?>
                    ├── 📄 complete_evaluation_data_<?php echo date('Y-m-d_H-i-s'); ?>.csv
                    └── 📄 index.html (Summary Page)
                </div>
            </div>
            
            <div style="margin: 30px 0;">
                <a href="reports/index.html" class="btn" target="_blank">📂 View Report Index</a>
                <a href="reports/complete_evaluation_data_<?php echo date('Y-m-d_H-i-s'); ?>.csv" class="btn btn-secondary">📥 Download CSV</a>
                <a href="admin.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
            
            <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-top: 20px;">
                <p><strong>📌 Note:</strong> All evaluation reports have been saved in the <code>/reports</code> folder on your server. You can access individual reports by navigating to the teacher folders.</p>
            </div>
        </div>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    // If there's an error, show an HTML error page instead of CSV
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><body>";
    echo "<h2>Error Generating Report</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='admin.php'>← Back to Admin Dashboard</a></p>";
    echo "</body></html>";
}

// Function to generate section summary report
function generateSectionSummaryReport($section_data, $teacher_info) {
    $section_info = $section_data['section_info'];
    $evaluations = $section_data['evaluations'];
    
    // Calculate section averages
    $total_students = count($evaluations);
    $q_totals = array_fill(1, 20, 0);
    $overall_total = 0;
    
    foreach ($evaluations as $eval) {
        for ($i = 1; $i <= 6; $i++) {
            $q_totals[$i] += $eval["q1_$i"];
            $overall_total += $eval["q1_$i"];
        }
        for ($i = 1; $i <= 4; $i++) {
            $q_totals[6+$i] += $eval["q2_$i"];
            $overall_total += $eval["q2_$i"];
        }
        for ($i = 1; $i <= 4; $i++) {
            $q_totals[10+$i] += $eval["q3_$i"];
            $overall_total += $eval["q3_$i"];
        }
        for ($i = 1; $i <= 6; $i++) {
            $q_totals[14+$i] += $eval["q4_$i"];
            $overall_total += $eval["q4_$i"];
        }
    }
    
    $section_average = $total_students > 0 ? round($overall_total / ($total_students * 20), 2) : 0;
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Section Summary - ' . htmlspecialchars($teacher_info['name']) . ' - ' . htmlspecialchars($section_info['section']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 15px; }
        .summary-stats { background: #f8f9fa; padding: 20px; margin-bottom: 20px; }
        .students-list { margin-top: 20px; }
        .student-item { padding: 10px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Section Summary Report</h2>
        <p><strong>Teacher:</strong> ' . htmlspecialchars($teacher_info['name']) . '</p>
        <p><strong>Subject:</strong> ' . htmlspecialchars($teacher_info['subject']) . '</p>
        <p><strong>Section:</strong> ' . htmlspecialchars($section_info['section']) . ' (' . htmlspecialchars($section_info['program']) . ')</p>
    </div>
    
    <div class="summary-stats">
        <h3>Summary Statistics</h3>
        <p><strong>Total Students:</strong> ' . $total_students . '</p>
        <p><strong>Section Average Rating:</strong> ' . $section_average . ' / 5.0</p>
        <p><strong>Generated:</strong> ' . date('F j, Y \a\t g:i A') . '</p>
    </div>
    
    <div class="students-list">
        <h3>Students in this Section</h3>';
    
    foreach ($evaluations as $eval) {
        $student_avg = ($eval['q1_1'] + $eval['q1_2'] + $eval['q1_3'] + $eval['q1_4'] + $eval['q1_5'] + $eval['q1_6'] +
                      $eval['q2_1'] + $eval['q2_2'] + $eval['q2_3'] + $eval['q2_4'] +
                      $eval['q3_1'] + $eval['q3_2'] + $eval['q3_3'] + $eval['q3_4'] +
                      $eval['q4_1'] + $eval['q4_2'] + $eval['q4_3'] + $eval['q4_4'] + $eval['q4_5'] + $eval['q4_6']) / 20;
        
        $html .= '<div class="student-item">
                    <div>
                        <strong>' . htmlspecialchars($eval['student_name']) . '</strong>
                        <span style="color: #666;">(' . htmlspecialchars($eval['student_id']) . ')</span>
                    </div>
                    <div style="font-weight: bold; color: #4CAF50;">' . number_format($student_avg, 2) . '</div>
                  </div>';
    }
    
    $html .= '</div>
</body>
</html>';
    
    return $html;
}

// Function to generate report summary page
function generateReportSummaryPage($total_reports, $total_folders, $total_teachers, $reports_dir) {
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Evaluation Reports - Index</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        
        body {
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #4CAF50;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 5px solid #4CAF50;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #4CAF50;
        }
        
        .folders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .teacher-folder {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .teacher-name {
            font-size: 1.2em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .sections-list {
            margin-top: 15px;
        }
        
        .section-item {
            background: #f8f9fa;
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-name {
            font-weight: bold;
            color: #495057;
        }
        
        .student-count {
            background: #4CAF50;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }
        
        .btn {
            background: #4CAF50;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9em;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #45a049;
        }
        
        .actions {
            text-align: center;
            margin: 30px 0;
        }
        
        .btn-large {
            padding: 12px 25px;
            font-size: 1em;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 Teacher Evaluation Reports</h1>
            <p>Philippine Technological Institute of Science Arts and Trade, Inc.</p>
            <p>Generated on: ' . date('F j, Y \a\t g:i A') . '</p>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number">' . $total_teachers . '</div>
                <div>Teachers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . $total_reports . '</div>
                <div>Individual Reports</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . $total_folders . '</div>
                <div>Folders Created</div>
            </div>
        </div>
        
        <div class="actions">
            <a href="../admin.php" class="btn btn-large">← Back to Admin Dashboard</a>
            <a href="complete_evaluation_data_' . date('Y-m-d_H-i-s') . '.csv" class="btn btn-large">📥 Download CSV</a>
        </div>
        
        <h2>📂 Teacher Folders</h2>
        <div class="folders-grid">';
    
    // Get actual folder structure
    if (is_dir($reports_dir)) {
        $teacher_folders = array_diff(scandir($reports_dir), array('.', '..', 'index.html'));
        foreach ($teacher_folders as $folder) {
            if (is_dir($reports_dir . '/' . $folder) && !strpos($folder, '.')) {
                $html .= '<div class="teacher-folder">
                            <div class="teacher-name">👨‍🏫 ' . htmlspecialchars($folder) . '</div>';
                
                $section_folders = array_diff(scandir($reports_dir . '/' . $folder), array('.', '..'));
                $html .= '<div class="sections-list">';
                
                foreach ($section_folders as $section_folder) {
                    if (is_dir($reports_dir . '/' . $folder . '/' . $section_folder)) {
                        // Count files in section folder
                        $files = glob($reports_dir . '/' . $folder . '/' . $section_folder . '/*.html');
                        $file_count = count(array_filter($files, function($file) {
                            return !strpos(basename($file), 'Summary');
                        }));
                        
                        $html .= '<div class="section-item">
                                    <div>
                                        <div class="section-name">📁 ' . htmlspecialchars($section_folder) . '</div>
                                    </div>
                                    <div>
                                        <span class="student-count">' . $file_count . ' students</span>
                                        <a href="' . $folder . '/' . $section_folder . '/" class="btn" style="margin-left: 10px;">View Files</a>
                                    </div>
                                  </div>';
                    }
                }
                
                $html .= '</div></div>';
            }
        }
    }
    
    $html .= '</div>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #eee; text-align: center; color: #666;">
            <p>© 2025 Philippine Technological Institute of Science Arts and Trade, Inc.</p>
            <p>Teacher Evaluation System - Generated Reports</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}
?>
