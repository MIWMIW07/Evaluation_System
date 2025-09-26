<?php
// admin.php - Admin Dashboard

session_start();
require_once 'includes/security.php';
require_once 'includes/db_connection.php';

// ✅ Check if logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// ✅ Fetch teachers from Google Sheets
$teachers = getTeachers();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; }
        h1 { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        table th { background: #3498db; color: white; }
        .logout { float: right; background: #e74c3c; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; }
        .logout:hover { background: #c0392b; }
    </style>
</head>
<body>
    <div class="container">
        <a href="logout.php" class="logout">Logout</a>
        <h1>Admin Dashboard</h1>

        <h2>📋 Teacher List (from Google Sheets)</h2>
        <?php if (empty($teachers)): ?>
            <p>No teachers found in Google Sheet (Sheet2).</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Subject</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teachers as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['name']) ?></td>
                            <td><?= htmlspecialchars($t['department']) ?></td>
                            <td><?= htmlspecialchars($t['subject']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
