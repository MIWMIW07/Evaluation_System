<?php
session_start();
require_once 'includes/db_connection.php';

// Security check: only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}
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
    .actions {
      display: flex;
      gap: 10px;
    }
    .actions a {
      text-decoration: none;
      padding: 6px 12px;
      border-radius: 4px;
      font-size: 14px;
      color: white;
    }
    .edit { background: #f0ad4e; }
    .delete { background: #d9534f; }
    .add-btn {
      display: inline-block;
      margin-bottom: 10px;
      background: #5cb85c;
      color: white;
      padding: 8px 16px;
      border-radius: 6px;
      text-decoration: none;
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
      <h2>Teachers</h2>
      <a href="add_teacher.php" class="add-btn">➕ Add Teacher</a>
      <?php
      try {
          $stmt = $pdo->query("SELECT id, name, email FROM teachers ORDER BY id ASC");
          if ($stmt->rowCount() > 0) {
              echo "<table>";
              echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Actions</th></tr>";
              while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                  echo "<tr>";
                  echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                  echo "<td class='actions'>
                          <a class='edit' href='edit_teacher.php?id=" . urlencode($row['id']) . "'>Edit</a>
                          <a class='delete' href='delete_teacher.php?id=" . urlencode($row['id']) . "' onclick=\"return confirm('Delete this teacher?');\">Delete</a>
                        </td>";
                  echo "</tr>";
              }
              echo "</table>";
          } else {
              echo "<p>No teachers found.</p>";
          }
      } catch (PDOException $e) {
          echo "<p>Error fetching teachers: " . htmlspecialchars($e->getMessage()) . "</p>";
      }
      ?>
    </div>

    <!-- Add more sections later (Evaluations, Students, Reports, etc.) -->
  </div>
</body>
</html>
