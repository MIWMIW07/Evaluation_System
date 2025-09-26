<?php
// admin.php - Admin Dashboard

session_start();
require_once 'includes/security.php';
require_once 'includes/db_connection.php';

// Check if logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Teacher Evaluations</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f4f6f9;
            color: #333;
        }

        header {
            background: #4a90e2;
            color: white;
            padding: 1rem;
            text-align: center;
        }

        .container {
            padding: 2rem;
            max-width: 1100px;
            margin: auto;
        }

        h1, h2 {
            margin: 0 0 1rem;
        }

        .actions-container {
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 0.6rem 1.2rem;
            border-radius: 5px;
            background: #4a90e2;
            color: white;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s ease;
        }

        .btn:hover {
            background: #357ABD;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #565e64;
        }

        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        table th, table td {
            border: 1px solid #ddd;
            padding: 0.8rem;
            text-align: left;
        }

        table th {
            background: #4a90e2;
            color: white;
        }

        table tr:nth-child(even) {
            background: #f9f9f9;
        }

        footer {
            margin-top: 3rem;
            text-align: center;
            padding: 1rem;
            background: #f1f1f1;
            color: #555;
        }
    </style>
</head>
<body>
    <header>
        <h1>Admin Dashboard</h1>
        <p>Manage Teachers & Evaluations</p>
    </header>

    <div class="container">

        <div class="actions-container">
            <a href="database_setup.php" class="btn btn-secondary">🔧 Database Setup</a>
            <a href="#" onclick="location.reload();" class="btn btn-secondary">🔄 Refresh Data</a>
            <a href="google_integration_dashboard.php" class="btn btn-secondary">🔗 Google Integration</a>
            <a href="logout.php" class="btn">🚪 Logout</a>
        </div>

        <div class="card">
            <h2>Teachers</h2>
            <?php
            try {
                $teachers = getTeachers();
                if (count($teachers) > 0) {
                    echo "<table><tr><th>ID</th><th>Name</th><th>Email</th></tr>";
                    foreach ($teachers as $teacher) {
                        echo "<tr>
                                <td>{$teacher['id']}</td>
                                <td>{$teacher['name']}</td>
                                <td>{$teacher['email']}</td>
                              </tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p>No teachers found.</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color: red;'>Error fetching teachers: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>

        <div class="card">
            <h2>Recent Evaluations</h2>
            <?php
            try {
                $evaluations = getRecentEvaluations();
                if (count($evaluations) > 0) {
                    echo "<table><tr><th>ID</th><th>Teacher</th><th>Rating</th><th>Comments</th><th>Date</th></tr>";
                    foreach ($evaluations as $eval) {
                        echo "<tr>
                                <td>{$eval['id']}</td>
                                <td>{$eval['teacher_name']}</td>
                                <td>{$eval['rating']}</td>
                                <td>{$eval['comments']}</td>
                                <td>{$eval['created_at']}</td>
                              </tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p>No evaluations found.</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color: red;'>Error fetching evaluations: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>

    </div>

    <footer>
        &copy; <?php echo date("Y"); ?> Teacher Evaluation System - Admin
    </footer>
</body>
</html>
