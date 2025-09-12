<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "evaluation_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all evaluations with teacher names
$sql = "SELECT e.*, t.name as teacher_name 
        FROM evaluations e 
        JOIN teachers t ON e.teacher_id = t.id 
        ORDER BY e.teacher_id, e.section, e.program";
$result = $conn->query($sql);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=teacher_evaluations.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, array(
    'Teacher Name', 'Subject', 'Student ID', 'Student Name', 'Section', 'Program',
    'Q1.1', 'Q1.2', 'Q1.3', 'Q1.4', 'Q1.5', 'Q1.6',
    'Q2.1', 'Q2.2', 'Q2.3', 'Q2.4',
    'Q3.1', 'Q3.2', 'Q3.3', 'Q3.4',
    'Q4.1', 'Q4.2', 'Q4.3', 'Q4.4', 'Q4.5', 'Q4.6',
    'Comments', 'Evaluation Date'
));

// Add data rows
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
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
            $row['evaluation_date']
        ));
    }
}

fclose($output);
$conn->close();
?>