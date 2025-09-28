<?php
// generate_report.php - Generates evaluation reports by teacher/section with CSV + HTML summary
session_start();

// Restrict to admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied. Admin privileges required.');
}

require_once 'includes/db_connection.php';

// --- Helper Functions ---
function createDirectory($path) {
    return !file_exists($path) ? mkdir($path, 0755, true) : true;
}

function sanitizeFolderName($name) {
    return preg_replace('/\s+/', '_', preg_replace('/[^a-zA-Z0-9\s\-_\.]/', '', trim($name)));
}

function removeDir($dir) {
    foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
        $path = "$dir/$file";
        is_dir($path) ? removeDir($path) : unlink($path);
    }
    return rmdir($dir);
}

// Generate individual evaluation report
function generateIndividualEvaluationReport($evaluation, $teacher) {
    ob_start(); ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Faculty Evaluation Report - <?= htmlspecialchars($teacher['name']) ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .report-container { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 900px; margin: auto; }
            h1 { text-align: center; color: #2c3e50; margin-bottom: 30px; }
            .info-table { width: 100%; margin-bottom: 25px; border-collapse: collapse; }
            .info-table td { padding: 10px; border-bottom: 1px solid #eee; }
            .evaluation-section { margin-bottom: 25px; }
            .section-title { font-size: 1.2em; color: #34495e; margin-bottom: 15px; }
            .criteria-table { width: 100%; border-collapse: collapse; }
            .criteria-table th, .criteria-table td { border: 1px solid #ddd; padding: 10px; }
            .criteria-table th { background: #f8f9fa; text-align: left; }
            .comments { margin-top: 20px; padding: 15px; background: #f0f7ff; border-left: 4px solid #3498db; }
            .average-score { font-size: 1.2em; text-align: right; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="report-container">
            <h1>Faculty Evaluation Report</h1>
            <table class="info-table">
                <tr><td><strong>Teacher:</strong></td><td><?= htmlspecialchars($teacher['name']) ?></td></tr>
                <tr><td><strong>Subject:</strong></td><td><?= htmlspecialchars($teacher['subject']) ?></td></tr>
                <tr><td><strong>Student:</strong></td><td><?= htmlspecialchars($evaluation['student_name']) ?> (ID: <?= htmlspecialchars($evaluation['student_id']) ?>)</td></tr>
                <tr><td><strong>Section:</strong></td><td><?= htmlspecialchars($evaluation['section']) ?></td></tr>
                <tr><td><strong>Program:</strong></td><td><?= htmlspecialchars($evaluation['program']) ?></td></tr>
                <tr><td><strong>Date:</strong></td><td><?= htmlspecialchars($evaluation['evaluation_date']) ?></td></tr>
            </table>

            <?php
            $sections = [
                'Instructional Skills' => ['q1_1','q1_2','q1_3','q1_4','q1_5','q1_6'],
                'Classroom Management' => ['q2_1','q2_2','q2_3','q2_4'],
                'Personal and Social Qualities' => ['q3_1','q3_2','q3_3','q3_4'],
                'Commitment and Professionalism' => ['q4_1','q4_2','q4_3','q4_4','q4_5','q4_6']
            ];
            $total_score = 0; $total_questions = 0;

            foreach ($sections as $title => $criteria) {
                echo "<div class='evaluation-section'><div class='section-title'>$title</div><table class='criteria-table'>";
                echo "<tr><th>Criterion</th><th>Score</th></tr>";
                foreach ($criteria as $q) {
                    $score = $evaluation[$q];
                    $total_score += $score; $total_questions++;
                    echo "<tr><td>" . strtoupper($q) . "</td><td>$score</td></tr>";
                }
                echo "</table></div>";
            }

            $average = round($total_score/$total_questions,2);
            ?>
            <div class="comments"><strong>Comments:</strong><br><?= nl2br(htmlspecialchars($evaluation['comments'])) ?></div>
            <div class="average-score"><strong>Average Score:</strong> <?= $average ?> / 5</div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Generate section summary report
function generateSectionSummaryReport($section_data, $teacher_info) {
    ob_start(); ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Section Summary - <?= htmlspecialchars($section_data['section_info']['section']) ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .summary-container { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 1000px; margin: auto; }
            h1, h2 { text-align: center; color: #2c3e50; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 10px; }
            th { background: #f8f9fa; }
            .comments { margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class="summary-container">
            <h1>Section Summary Report</h1>
            <h2><?= htmlspecialchars($teacher_info['name']) ?> - <?= htmlspecialchars($teacher_info['subject']) ?></h2>
            <p><strong>Section:</strong> <?= htmlspecialchars($section_data['section_info']['section']) ?>
            | <strong>Program:</strong> <?= htmlspecialchars($section_data['section_info']['program']) ?></p>

            <table>
                <tr>
                    <th>Student</th><th>ID</th><th>Average Score</th><th>Evaluation Date</th>
                </tr>
                <?php
                $all_scores = []; $comments = [];
                foreach ($section_data['evaluations'] as $eval) {
                    $sum = 0; $count = 0;
                    for ($i=1;$i<=4;$i++) {
                        $prefix = "q{$i}_";
                        foreach ($eval as $k => $v) if (strpos($k,$prefix)===0) { $sum += $v; $count++; }
                    }
                    $avg = $count>0 ? round($sum/$count,2) : 0;
                    $all_scores[] = $avg;
                    if (!empty($eval['comments'])) $comments[] = $eval['student_name'].": ".$eval['comments'];
                    echo "<tr><td>".htmlspecialchars($eval['student_name'])."</td>
                          <td>".htmlspecialchars($eval['student_id'])."</td>
                          <td>$avg / 5</td>
                          <td>".htmlspecialchars($eval['evaluation_date'])."</td></tr>";
                }
                $section_avg = count($all_scores)>0 ? round(array_sum($all_scores)/count($all_scores),2) : 0;
                ?>
            </table>
            <p><strong>Section Average Score:</strong> <?= $section_avg ?> / 5</p>

            <div class="comments">
                <h3>Student Comments</h3>
                <?php if ($comments): ?>
                    <ul><?php foreach ($comments as $c) echo "<li>".htmlspecialchars($c)."</li>"; ?></ul>
                <?php else: ?>
                    <p>No comments provided.</p>
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// --- Main Execution ---
try {
    $sql = "SELECT e.*, t.name as teacher_name, t.subject as teacher_subject
            FROM evaluations e
            JOIN teachers t ON e.teacher_id = t.id
            ORDER BY t.name, e.section, e.program, e.student_name";
    $results = fetch_all(query($sql));
    if (!$results) throw new Exception("No evaluations found");

    $reports_dir = 'reports';
    createDirectory($reports_dir);

    // Clean reports dir
    foreach (glob($reports_dir . '/*') as $file) {
        is_dir($file) ? removeDir($file) : unlink($file);
    }

    // Organize by teacher > section
    $organized = [];
    foreach ($results as $row) {
        $t = $row['teacher_name'];
        $skey = $row['section'] . '_' . $row['program'];
        $organized[$t]['teacher_info'] = ['name' => $t, 'subject' => $row['teacher_subject']];
        $organized[$t]['sections'][$skey]['section_info'] = ['section' => $row['section'], 'program' => $row['program']];
        $organized[$t]['sections'][$skey]['evaluations'][] = $row;
    }

    $created_reports = $created_folders = 0;

    foreach ($organized as $teacher_name => $teacher_data) {
        $teacher_folder = $reports_dir . '/' . sanitizeFolderName($teacher_name);
        if (createDirectory($teacher_folder)) $created_folders++;

        foreach ($teacher_data['sections'] as $section_key => $section_data) {
            $section_folder = $teacher_folder . '/' . sanitizeFolderName($section_key);
            if (createDirectory($section_folder)) $created_folders++;

            foreach ($section_data['evaluations'] as $eval) {
                $file = $section_folder . '/' . sanitizeFolderName($eval['student_name']) . "_{$eval['student_id']}_evaluation.html";
                if (file_put_contents($file, generateIndividualEvaluationReport($eval, $teacher_data['teacher_info']))) {
                    $created_reports++;
                }
            }

            file_put_contents($section_folder . '/Section_Summary.html',
                generateSectionSummaryReport($section_data, $teacher_data['teacher_info']));
        }
    }

    // CSV Export
    $csv_file = "$reports_dir/complete_evaluation_data_" . date('Y-m-d_H-i-s') . ".csv";
    $csv = fopen($csv_file, 'w');
    fprintf($csv, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($csv, ['Teacher Name','Subject','Student ID','Student Name','Section','Program',
        'Q1.1','Q1.2','Q1.3','Q1.4','Q1.5','Q1.6','Q2.1','Q2.2','Q2.3','Q2.4',
        'Q3.1','Q3.2','Q3.3','Q3.4','Q4.1','Q4.2','Q4.3','Q4.4','Q4.5','Q4.6',
        'Comments','Evaluation Date','Average']);
    foreach ($results as $row) {
        $total = $row['q1_1']+$row['q1_2']+$row['q1_3']+$row['q1_4']+$row['q1_5']+$row['q1_6']+
                 $row['q2_1']+$row['q2_2']+$row['q2_3']+$row['q2_4']+
                 $row['q3_1']+$row['q3_2']+$row['q3_3']+$row['q3_4']+
                 $row['q4_1']+$row['q4_2']+$row['q4_3']+$row['q4_4']+$row['q4_5']+$row['q4_6'];
        $avg = round($total/20,2);
        fputcsv($csv, [$row['teacher_name'],$row['teacher_subject'],$row['student_id'],$row['student_name'],
            $row['section'],$row['program'],$row['q1_1'],$row['q1_2'],$row['q1_3'],$row['q1_4'],$row['q1_5'],$row['q1_6'],
            $row['q2_1'],$row['q2_2'],$row['q2_3'],$row['q2_4'],$row['q3_1'],$row['q3_2'],$row['q3_3'],$row['q3_4'],
            $row['q4_1'],$row['q4_2'],$row['q4_3'],$row['q4_4'],$row['q4_5'],$row['q4_6'],
            $row['comments'],$row['evaluation_date'],$avg]);
    }
    fclose($csv);

    // Success summary page
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Report Generation Successful</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; padding: 40px; }
        .container { max-width: 600px; margin: auto; background: #fff; padding: 30px; border-radius: 8px;
                     box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; }
        h1 { color: #2c3e50; margin-bottom: 20px; }
        .stats { margin: 20px 0; padding: 15px; background: #f0f7ff; border-radius: 6px; }
        .btn { display: inline-block; padding: 12px 25px; margin: 10px; text-decoration: none; color: white;
               border-radius: 5px; transition: background 0.3s; }
        .btn-dashboard { background: #3498db; }
        .btn-dashboard:hover { background: #2980b9; }
        .btn-download { background: #27ae60; }
        .btn-download:hover { background: #219150; }
    </style></head><body>
    <div class='container'>
        <h1>Report Generation Completed</h1>
        <div class='stats'>
            <p><strong>Total Reports Created:</strong> $created_reports</p>
            <p><strong>Total Folders Created:</strong> $created_folders</p>
        </div>
        <p>Reports have been successfully generated in the <code>$reports_dir</code> directory.</p>
        <a href='admin_dashboard.php' class='btn btn-dashboard'>Back to Dashboard</a>
        <a href='$csv_file' class='btn btn-download' download>Download Complete CSV</a>
    </div>
    </body></html>";

} catch (Exception $e) {
    echo "<h2>Error Generating Report</h2><p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
