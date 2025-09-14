<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection with error handling
try {
    require_once 'db_connection.php';
    
    if (!isset($conn)) {
        throw new Exception("Database connection not established");
    }
} catch (Exception $e) {
    // Show error page with database connection issue
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Connection Error</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
            .error-container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
            .error-header { color: #e74c3c; text-align: center; margin-bottom: 20px; }
            .error-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .btn { display: inline-block; background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1 class="error-header">🔧 Database Connection Required</h1>
            <p>The admin dashboard cannot load because the database connection is not available.</p>
            
            <div class="error-details">
                <h3>Possible Solutions:</h3>
                <ul>
                    <li>Ensure PostgreSQL database service is running on Railway</li>
                    <li>Check that environment variables are properly set</li>
                    <li>Run database setup to create required tables</li>
                </ul>
            </div>
            
            <p><strong>Error:</strong> <?php echo htmlspecialchars($e->getMessage()); ?></p>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="database_setup.php" class="btn">🔧 Database Setup</a>
                <a href="index.php" class="btn">← Back to Main</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Initialize variables
$summary_result = null;
$total_teachers = 0;
$total_evaluations = 0;
$unique_students = 0;

try {
    // Get summary data - Fixed for PostgreSQL
    $summary_sql = "SELECT 
                    t.name as teacher_name,
                    t.subject,
                    COUNT(e.id) as evaluation_count,
                    COALESCE(AVG((e.q1_1 + e.q1_2 + e.q1_3 + e.q1_4 + e.q1_5 + e.q1_6 +
                         e.q2_1 + e.q2_2 + e.q2_3 + e.q2_4 +
                         e.q3_1 + e.q3_2 + e.q3_3 + e.q3_4 +
                         e.q4_1 + e.q4_2 + e.q4_3 + e.q4_4 + e.q4_5 + e.q4_6) / 20.0), 0) as average_rating
                    FROM teachers t
                    LEFT JOIN evaluations e ON t.id = e.teacher_id
                    GROUP BY t.id, t.name, t.subject
                    ORDER BY average_rating DESC";
    $summary_result = query($summary_sql);

    if (!$summary_result) {
        throw new Exception("Error fetching summary data");
    }

    // Get total statistics
    $total_teachers_stmt = query("SELECT COUNT(*) as total_teachers FROM teachers");
    $total_teachers_row = fetch_assoc($total_teachers_stmt);
    if ($total_teachers_row) {
        $total_teachers = $total_teachers_row['total_teachers'];
    }

    $total_evaluations_stmt = query("SELECT COUNT(*) as total_evaluations FROM evaluations");
    $total_evaluations_row = fetch_assoc($total_evaluations_stmt);
    if ($total_evaluations_row) {
        $total_evaluations = $total_evaluations_row['total_evaluations'];
    }

    $unique_students_stmt = query("SELECT COUNT(DISTINCT student_id) as unique_students FROM evaluations");
    $unique_students_row = fetch_assoc($unique_students_stmt);
    if ($unique_students_row) {
        $unique_students = $unique_students_row['unique_students'];
    }

} catch (Exception $e) {
    $db_error = $e->getMessage();
}
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-radius: 15px;
        }
        
        header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 3px solid #4CAF50;
        }
        
        h1, h2, h3 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        header h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #4CAF50;
        }
        
        .stat-card h3 {
            color: #4CAF50;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .actions-container {
            display: flex;
            gap: 15px;
            margin-bottom: 40px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #1976D2 0%, #2196F3 100%);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.4);
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .summary-table th, .summary-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .summary-table th {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9em;
        }
        
        .summary-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .summary-table tr:hover {
            background-color: #e3f2fd;
            transform: scale(1.01);
            transition: all 0.3s ease;
        }
        
        .rating {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 20px;
            color: white;
            text-align: center;
            min-width: 60px;
            display: inline-block;
        }
        
        .rating-high {
            background: linear-gradient(135deg, #4CAF50, #45a049);
        }
        
        .rating-medium {
            background: linear-gradient(135deg, #FF9800, #F57C00);
        }
        
        .rating-low {
            background: linear-gradient(135deg, #F44336, #D32F2F);
        }
        
        .rating-none {
            background: linear-gradient(135deg, #9E9E9E, #757575);
        }
        
        .table-responsive {
            overflow-x: auto;
            border-radius: 10px;
        }
        
        .back-link {
            position: fixed;
            top: 20px;
            left: 20px;
            background: #2196F3;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            background: #1976D2;
            transform: translateY(-2px);
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }

        .error-alert {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            padding: 20px;
            border-left: 5px solid #dc3545;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .actions-container {
                flex-direction: column;
                align-items: center;
            }
            
            .summary-table {
                font-size: 0.85em;
            }
            
            .summary-table th, .summary-table td {
                padding: 10px 8px;
            }
            
            .back-link {
                position: relative;
                display: block;
                width: fit-content;
                margin: 10px auto 20px;
            }
            
            header h1 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-link">← Back to Evaluation Form</a>
    
    <div class="container">
        <header>
            <h1>📊 Teacher Evaluation System</h1>
            <h2>Admin Dashboard</h2>
            <p>Comprehensive overview of teacher evaluations and performance metrics</p>
        </header>
        
        <?php if (isset($db_error)): ?>
            <div class="error-alert">
                <h3>⚠️ Database Error</h3>
                <p>There was an issue loading the dashboard data: <?php echo htmlspecialchars($db_error); ?></p>
                <p>Please check your database connection and try again.</p>
            </div>
        <?php endif; ?>
        
        <div class="stats-container">
            <div class="stat-card">
                <h3><?php echo $total_teachers; ?></h3>
                <p>Total Teachers</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_evaluations; ?></h3>
                <p>Total Evaluations</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $unique_students; ?></h3>
                <p>Participating Students</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_evaluations > 0 && $total_teachers > 0 ? round($total_evaluations / $total_teachers, 1) : '0'; ?></h3>
                <p>Avg Evaluations per Teacher</p>
            </div>
        </div>
        
        <div class="actions-container">
            <a href="generate_report.php" class="btn">📥 Download CSV Report</a>
            <a href="database_setup.php" class="btn btn-secondary">🔧 Database Setup</a>
            <a href="#" onclick="location.reload();" class="btn btn-secondary">🔄 Refresh Data</a>
        </div>
        
        <h2>📈 Teacher Evaluation Summary</h2>
        <div class="table-responsive">
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Teacher Name</th>
                        <th>Subject</th>
                        <th>Evaluations</th>
                        <th>Average Rating</th>
                        <th>Performance Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($summary_result) {
                        $results = fetch_all($summary_result);
                        if (count($results) > 0) {
                            $rank = 1;
                            foreach($results as $row) {
                                $avg_rating = floatval($row['average_rating']);
                                $rating_class = '';
                                $performance_level = '';
                                
                                if ($avg_rating >= 4.5) {
                                    $rating_class = 'rating-high';
                                    $performance_level = 'Outstanding';
                                } else if ($avg_rating >= 4.0) {
                                    $rating_class = 'rating-high';
                                    $performance_level = 'Very Satisfactory';
                                } else if ($avg_rating >= 3.5) {
                                    $rating_class = 'rating-medium';
                                    $performance_level = 'Good/Satisfactory';
                                } else if ($avg_rating >= 2.5) {
                                    $rating_class = 'rating-medium';
                                    $performance_level = 'Fair';
                                } else if ($avg_rating > 0) {
                                    $rating_class = 'rating-low';
                                    $performance_level = 'Needs Improvement';
                                } else {
                                    $rating_class = 'rating-none';
                                    $performance_level = 'Not Evaluated';
                                }
                                
                                echo "<tr>
                                        <td><strong>$rank</strong></td>
                                        <td><strong>" . htmlspecialchars($row['teacher_name']) . "</strong></td>
                                        <td>" . htmlspecialchars($row['subject']) . "</td>
                                        <td><span style='background: #e3f2fd; padding: 3px 8px; border-radius: 10px; color: #1976D2; font-weight: bold;'>{$row['evaluation_count']}</span></td>
                                        <td><span class='rating $rating_class'>" . number_format($avg_rating, 2) . "</span></td>
                                        <td><strong>$performance_level</strong></td>
                                      </tr>";
                                $rank++;
                            }
                        } else {
                            echo "<tr><td colspan='6' class='no-data'>
                                    <div>
                                        <h3>📋 No evaluations found</h3>
                                        <p>No teacher evaluations have been submitted yet.</p>
                                        <br>
                                        <a href='index.php' class='btn'>Start First Evaluation</a>
                                    </div>
                                  </td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='no-data'>
                                <div>
                                    <h3>📋 No evaluations found</h3>
                                    <p>No teacher evaluations have been submitted yet.</p>
                                    <br>
                                    <a href='index.php' class='btn'>Start First Evaluation</a>
                                </div>
                              </td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 40px; padding: 25px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; border-left: 5px solid #2196F3;">
            <h3>📊 Rating Scale Information</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
                <div style="text-align: center;">
                    <span class="rating rating-high">4.5 - 5.0</span>
                    <p style="margin-top: 8px; font-weight: bold;">Outstanding</p>
                </div>
                <div style="text-align: center;">
                    <span class="rating rating-high">4.0 - 4.4</span>
                    <p style="margin-top: 8px; font-weight: bold;">Very Satisfactory</p>
                </div>
                <div style="text-align: center;">
                    <span class="rating rating-medium">3.5 - 3.9</span>
                    <p style="margin-top: 8px; font-weight: bold;">Good/Satisfactory</p>
                </div>
                <div style="text-align: center;">
                    <span class="rating rating-medium">2.5 - 3.4</span>
                    <p style="margin-top: 8px; font-weight: bold;">Fair</p>
                </div>
                <div style="text-align: center;">
                    <span class="rating rating-low">1.0 - 2.4</span>
                    <p style="margin-top: 8px; font-weight: bold;">Needs Improvement</p>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 40px; padding-top: 25px; border-top: 2px solid #e9ecef; color: #6c757d;">
            <p><strong>© 2025 Philippine Technological Institute of Science Arts and Trade, Inc.</strong></p>
            <p>Teacher Evaluation System - Admin Dashboard</p>
            <p style="margin-top: 10px;">
                Last updated: <?php echo date('F j, Y \a\t g:i A'); ?>
            </p>
        </div>
    </div>

    <script>
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 150);
            });
            
            // Add click effect to table rows
            const tableRows = document.querySelectorAll('.summary-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('click', function() {
                    // Could add functionality to view detailed evaluation for this teacher
                    const teacherName = this.children[1].textContent;
                    console.log(`Clicked on ${teacherName}`);
                });
            });
        });
    </script>
</body>
</html>
