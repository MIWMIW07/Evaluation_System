<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection with error handling
try {
    require_once 'includes/db_connection.php';
    
    if (!isset($pdo)) {
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
    // ==================================================================
    // FIXED SQL QUERY - Subject has been totally removed from this query
    // ==================================================================
    $summary_sql = "SELECT 
                        t.name as teacher_name,
                        t.department,
                        COUNT(e.id) as evaluation_count,
                        COALESCE(AVG((e.q1_1 + e.q1_2 + e.q1_3 + e.q1_4 + e.q1_5 + e.q1_6 +
                                e.q2_1 + e.q2_2 + e.q2_3 + e.q2_4 +
                                e.q3_1 + e.q3_2 + e.q3_3 + e.q3_4 +
                                e.q4_1 + e.q4_2 + e.q4_3 + e.q4_4 + e.q4_5 + e.q4_6) / 20.0), 0) as average_rating
                    FROM teachers t
                    LEFT JOIN evaluations e ON t.id = e.teacher_id
                    GROUP BY t.id, t.name, t.department
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
            background: linear-gradient(135deg, #800000 0%, #5a0000 100%);
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
            border-bottom: 3px solid #D4AF37;
        }
        
        h1, h2, h3 {
            color: #5a0000;
            margin-bottom: 20px;
        }
        
        header h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #D4AF37, #FFDF7F);
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
            background: linear-gradient(135deg, #FFF8DC 0%, #FAF0C9 100%);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #D4AF37;
        }
        
        .stat-card h3 {
            color: #800000;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            color: #5a0000;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .actions-container {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #800000 0%, #5a0000 100%);
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(128, 0, 0, 0.3);
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #5a0000 0%, #800000 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(128, 0, 0, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #D4AF37 0%, #B8860B 100%);
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
            color: #5a0000;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #B8860B 0%, #D4AF37 100%);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4);
        }
        
        .btn-enhanced {
            background: linear-gradient(135deg, #800000 0%, #5a0000 100%);
            box-shadow: 0 6px 20px rgba(128, 0, 0, 0.4);
            padding: 15px 30px;
            font-size: 1.1em;
        }
        
        .btn-enhanced:hover {
            background: linear-gradient(135deg, #5a0000 0%, #800000 100%);
            box-shadow: 0 10px 30px rgba(128, 0, 0, 0.5);
        }
        
        .btn-logout {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-logout:hover {
            background: linear-gradient(135deg, #c82333 0%, #dc3545 100%);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
        }
        
        .report-generation-section {
            background: linear-gradient(135deg, #FFF8DC 0%, #FAF0C9 100%);
            padding: 25px;
            border-radius: 15px;
            margin: 30px 0;
            border-left: 5px solid #D4AF37;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .feature-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .feature-card h4 {
            color: #800000;
            margin-bottom: 10px;
        }
        
        .feature-card p {
            font-size: 0.9em;
            color: #666;
        }
        
        .note-box {
            background: #FFF8DC;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #D4AF37;
        }
        
        .note-box p {
            color: #800000;
            font-size: 0.9em;
            margin: 0;
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
            background: linear-gradient(135deg, #800000 0%, #5a0000 100%);
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
            background-color: #FFF8DC;
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
            background: linear-gradient(135deg, #D4AF37, #FFDF7F);
        }
        
        .rating-medium {
            background: linear-gradient(135deg, #B8860B, #D4AF37);
        }
        
        .rating-low {
            background: linear-gradient(135deg, #800000, #5a0000);
        }
        
        .rating-none {
            background: linear-gradient(135deg, #9E9E9E, #757575);
        }
        
        .table-responsive {
            overflow-x: auto;
            border-radius: 10px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }

        .error-alert {
            background: linear-gradient(135deg, #FFF8DC 0%, #FAF0C9 100%);
            color: #800000;
            padding: 20px;
            border-left: 5px solid #800000;
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
            
            .feature-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-table {
                font-size: 0.85em;
            }
            
            .summary-table th, .summary-table td {
                padding: 10px 8px;
            }
            
            header h1 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    
    <div class="container">
        <header>
            <h1>📊 Teacher Evaluation System</h1>
            <h2>Admin Dashboard</h2>
            <p>Comprehensive overview of teacher evaluations and performance metrics</p>
        </header>
        
        <?php if (isset($db_error)): ?>
            <div class="error-alert">⚠️ Database Error</h3>
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
            <a href="database_setup.php" class="btn btn-secondary">🔧 Database Setup</a>
            <a href="#" onclick="location.reload();" class="btn btn-secondary">🔄 Refresh Data</a>
        </div>

        <div class="report-generation-section">
            <h2 style="color: #5a0000; margin-bottom: 20px; display: flex; align-items: center;">
                📊 Advanced Report Generation
            </h2>
            <p style="color: #666; margin-bottom: 20px;">Generate comprehensive evaluation reports organized by teacher and section. Each report includes individual student evaluations formatted for printing.</p>
            
            <div class="feature-grid">
                <div class="feature-card">
                    <h4>📁 Organized Folders</h4>
                    <p>Creates folders for each teacher with subfolders for each section they teach.</p>
                </div>
                
                <div class="feature-card">
                    <h4>📄 Individual Reports</h4>
                    <p>Generates printable HTML reports for each student's evaluation.</p>
                </div>
                
                <div class="feature-card">
                    <h4>📈 Section Summaries</h4>
                    <p>Creates summary reports showing section-wide evaluation statistics.</p>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="generate_report.php" class="btn btn-enhanced">
                    🚀 Generate Organized Reports
                </a>
            </div>
            
            <div class="note-box">
                <p>
                    <strong>💡 Note:</strong> This will create a complete folder structure with individual evaluation reports that can be easily printed or shared. The process may take a few moments for large datasets.
                </p>
            </div>
        </div>
        
        <h2>📈 Teacher Evaluation Summary</h2>
        <div class="table-responsive">
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Teacher Name</th>
                        <th>Department</th>
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
                                        <td>" . htmlspecialchars($row['department']) . "</td>
                                        <td><span style='background: #FFF8DC; padding: 3px 8px; border-radius: 10px; color: #800000; font-weight: bold;'>{$row['evaluation_count']}</span></td>
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
        
        <div style="margin-top: 40px; padding: 25px; background: linear-gradient(135deg, #FFF8DC 0%, #FAF0C9 100%); border-radius: 10px; border-left: 5px solid #D4AF37;">
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
            <div style="margin-top: 20px;">
                <a href="logout.php" class="btn btn-logout">🚪 Logout</a>
            </div>
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

            // Add confirmation for report generation
            document.querySelector('.btn-enhanced').addEventListener('click', function(e) {
                if (!confirm('Generate organized evaluation reports? This may take a few moments for large datasets.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
