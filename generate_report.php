<?php
// Include database connection
require_once 'db_connection.php';

// Check if connection exists
if (!isset($conn)) {
    die("Database connection failed. Please check your configuration.");
}

try {
    // Get all evaluations with teacher names
    $sql = "SELECT e.*, t.name as teacher_name 
            FROM evaluations e 
            JOIN teachers t ON e.teacher_id = t.id 
            ORDER BY e.teacher_id, e.section, e.program";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=teacher_evaluations_' . date('Y-m-d') . '.csv');
    header('Cache-Control: no-cache, must-revalidate');

    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    // Add BOM for proper Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Add CSV headers
    fputcsv($output, array(
        'Teacher Name', 'Subject', 'Student ID', 'Student Name', 'Section', 'Program',
        'Q1.1 - Analyzes lessons without reading',
        'Q1.2 - Uses audio-visual devices', 
        'Q1.3 - Presents ideas clearly',
        'Q1.4 - Allows student demonstrations',
        'Q1.5 - Fair tests and evaluations',
        'Q1.6 - Orderly teaching with proper speech',
        'Q2.1 - Maintains orderly classroom',
        'Q2.2 - Follows systematic schedule',
        'Q2.3 - Develops student respect',
        'Q2.4 - Allows student opinions',
        'Q3.1 - Accepts students as individuals',
        'Q3.2 - Shows confidence and organization',
        'Q3.3 - Manages problems fairly',
        'Q3.4 - Shows genuine concern',
        'Q4.1 - Maintains emotional balance',
        'Q4.2 - Free from distracting habits',
        'Q4.3 - Neat and presentable',
        'Q4.4 - Does not show favoritism',
        'Q4.5 - Good humor and enthusiasm',
        'Q4.6 - Good diction and voice',
        'Comments', 'Evaluation Date', 'Average Rating'
    ));

    // Add data rows
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Calculate average rating
            $total_rating = $row['q1_1'] + $row['q1_2'] + $row['q1_3'] + $row['q1_4'] + $row['q1_5'] + $row['q1_6'] +
                           $row['q2_1'] + $row['q2_2'] + $row['q2_3'] + $row['q2_4'] +
                           $row['q3_1'] + $row['q3_2'] + $row['q3_3'] + $row['q3_4'] +
                           $row['q4_1'] + $row['q4_2'] + $row['q4_3'] + $row['q4_4'] + $row['q4_5'] + $row['q4_6'];
            $average_rating = round($total_rating / 20, 2);

            fputcsv($output, array(
                $row['teacher_name'],
                $row['subject'],
                $row['student_id'],
                $row['student_name'],
                $row['section'],
                $row['program'],
                $row['q1_1'], $row['q1_2'], $row['q1_3'], $row['q1_4'], $row['q1_5'], $row['q1_6'],
                $row['q2_1'], $row['q2_2'], $row['q2_3'], $row['q2_4'],
                $row['q3_1'], $row['q3_2'], $row['q3_3'], $row['q3_4'],
                $row['q4_1'], $row['q4_2'], $row['q4_3'], $row['q4_4'], $row['q4_5'], $row['q4_6'],
                $row['comments'],
                $row['evaluation_date'],
                $average_rating
            ));
        }
    } else {
        // Add empty row if no data
        fputcsv($output, array('No evaluations found', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''));
    }

    fclose($output);

} catch (Exception $e) {
    // If there's an error, show an HTML error page instead of CSV
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><body>";
    echo "<h2>Error Generating Report</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='admin.php'>← Back to Admin Dashboard</a></p>";
    echo "</body></html>";
}

$conn->close();
?>
