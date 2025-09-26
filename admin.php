<?php
session_start();
require_once 'includes/db_connection.php';

// Security check: only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// ✅ Use HybridDataManager
$dataManager = getDataManager();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f4f6f9;
      margin: 0;
      padding: 0;
    }
    header {
      background: linear-gradient(135deg, #4a90e2, #357abd);
      color: white;
      padding: 20px;
      text-align: center;
    }
    header h1 {
      margin: 0;
      font-size: 28px;
    }
    header p {
      margin: 5px 0 0;
    }
    nav {
      text-align: center;
      margin: 20px 0;
    }
    nav button {
      background: #4a90e2;
      border: none;
      color: white;
      padding: 10px 20px;
      margin: 0 8px;
      border-radius: 6px;
      cursor: pointer;
      transition: background 0.3s;
    }
    nav button:hover {
      background: #357abd;
    }
    .container {
      max-width: 1100px;
      margin: auto;
      padding: 20px;
    }
    .card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 3px 6px rgba(0,0,0,0.1);
    }
    .card h2 {
      margin-top: 0;
      color: #333;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }
    th, td {
      padding: 12px;
      border: 1px solid #ddd;
      text-align: left;
    }
    th {
      background: #f1f1f1;
    }
    .logout-btn {
      background: #d9534f;
    }
  </style>
</head>
<body>
  <header>
    <h1>Admin Dashboard</h1>
    <p>Manage Teachers & Evaluations</p>
  </header>

  <nav>
    <button onclick="location.href='database_setup.php'">⚙ Database Setup</button>
    <button onclick="location.href='refresh.php'">🔄 Refresh Data</button>
    <button onclick="location.href='google_integration_dashboard.php'">🔗 Google Integration</button>
    <button class="logout-btn" onclick="location.href='logout.php'">🚪 Logout</button>
  </nav>

  <div class="container">
    <!-- Teachers Section -->
    <div class="card">
      <h2>Teachers (from Google Sheets)</h2>
      <?php
      try {
          $teachers = $dataManager->getTeachers();
          if (!empty($teachers)) {
              echo "<table>";
              echo "<tr><th>#</th><th>Name</th><th>Department</th><th>Subject</th></tr>";
              foreach ($teachers as $index => $row) {
                  $name = htmlspecialchars($row[0] ?? '');
                  $department = htmlspecialchars($row[1] ?? '');
                  $subject = htmlspecialchars($row[2] ?? '');
                  echo "<tr>";
                  echo "<td>" . ($index + 1) . "</td>";
                  echo "<td>$name</td>";
                  echo "<td>$department</td>";
                  echo "<td>$subject</td>";
                  echo "</tr>";
              }
              echo "</table>";
          } else {
              echo "<p>No teachers found in Google Sheets.</p>";
          }
      } catch (Exception $e) {
          echo "<p>Error fetching teachers: " . htmlspecialchars($e->getMessage()) . "</p>";
      }
      ?>
    </div>
  </div>
</body>
</html>
