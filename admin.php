<?php
// Include database connection
require_once 'db_connection.php';

// Get summary data
$summary_sql = "SELECT 
                t.name as teacher_name,
                t.subject,
                COUNT(e.id) as evaluation_count,
                AVG((e.q1_1 + e.q1_2 + e.q1_3 + e.q1_4 + e.q1_5 + e.q1_6 +
                     e.q2_1 + e.q2_2 + e.q2_3 + e.q2_4 +
                     e.q3_1 + e.q3_2 + e.q3_3 + e.q3_4 +
                     e.q4_1 + e.q4_2 + e.q4_3 + e.q4_4 + e.q4_5 + e.q4_6) / 20) as average_rating
                FROM teachers t
                LEFT JOIN evaluations e ON t.id = e.teacher_id
                GROUP BY t.id
                ORDER BY average_rating DESC";
$summary_result = $conn->query($summary_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Teacher Evaluation System</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #4CAF50;
        }
        
        h1, h2, h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .summary-table th, .summary-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        
        .summary-table th {
            background-color: #4CAF50;
            color: white;
        }
        
        .summary-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .summary-table tr:hover {
            background-color: #ddd;
        }
        
        .btn {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .btn:hover {
            background-color: #45a049;
        }
        
        .rating {
            font-weight: bold;
        }
        
        .rating-high {
            color: #4CAF50;
        }
        
        .rating-medium {
            color: #FF9800;
        }
        
        .rating-low {
            color: #F44336;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Teacher Evaluation System - Admin Dashboard</h1>
            <p>Summary of Teacher Evaluations</p>
        </header>
        
        <a href="generate_report.php" class="btn">Download Full Report (CSV)</a>
        
        <h2>Evaluation Summary</h2>
        <table class="summary-table">
            <thead>
                <tr>
                    <th>Teacher Name</th>
                    <th>Subject</th>
                    <th>Number of Evaluations</th>
                    <th>Average Rating</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($summary_result->num_rows > 0) {
                    while($row = $summary_result->fetch_assoc()) {
                        $rating_class = '';
                        if ($row['average_rating'] >= 4.0) $rating_class = 'rating-high';
                        else if ($row['average_rating'] >= 3.0) $rating_class = 'rating-medium';
                        else if ($row['average_rating'] > 0) $rating_class = 'rating-low';
                        
                        echo "<tr>
                                <td>{$row['teacher_name']}</td>
                                <td>{$row['subject']}</td>
                                <td>{$row['evaluation_count']}</td>
                                <td><span class='rating $rating_class'>" . round($row['average_rating'], 2) . "</span></td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='4'>No evaluations found</td></tr>";
                }
                ?>
            </tbody>
        </table>
        
        <h2>Quick Actions</h2>
        <a href="generate_report.php" class="btn">Generate CSV Report</a>
        <a href="index.php" class="btn">View Evaluation Form</a>
    </div>
</body>
</html>

<?php
$conn->close();
?>
