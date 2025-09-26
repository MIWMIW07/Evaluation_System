<?php
session_start();
require_once __DIR__ . '/includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$manager = getDataManager();
$teachers = $manager->getTeachers();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Admin Dashboard</h1>
    <p>Manage Teachers & Evaluations</p>
    <a href="logout.php">Logout</a>

    <h2>Teachers</h2>
    <table border="1">
        <tr>
            <th>Name</th>
            <th>Department</th>
            <th>Subject</th>
        </tr>
        <?php foreach ($teachers as $teacher): ?>
        <tr>
            <td><?= htmlspecialchars($teacher[0] ?? '') ?></td>
            <td><?= htmlspecialchars($teacher[1] ?? '') ?></td>
            <td><?= htmlspecialchars($teacher[2] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
