<?php
session_start();
require_once __DIR__ . '/includes/db_connection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        try {
            $manager = getDataManager(); // ✅ Use HybridDataManager
            $auth = $manager->authenticateUser($username, $password);

            if ($auth) {
                // ✅ Successful login
                $_SESSION['user_id']   = $auth['id'];
                $_SESSION['username']  = $username;
                $_SESSION['user_type'] = $auth['type'];

                // Update last login if admin (stored in Postgres)
                if ($auth['type'] === 'admin') {
                    $pdo = $manager->getPDO();
                    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                        ->execute([$auth['id']]);
                }

                // Log success
                logActivity("login", "User '{$username}' logged in successfully", "success", $auth['id']);

                // Redirect
                if ($auth['type'] === 'admin') {
                    header("Location: admin.php");
                } elseif ($auth['type'] === 'teacher') {
                    header("Location: teacher_dashboard.php");
                } else {
                    header("Location: student_dashboard.php");
                }
                exit;
            } else {
                // ❌ Failed login
                logActivity("login", "Failed login attempt for username '{$username}'", "error");
                $error = "Invalid username or password.";
            }
        } catch (Exception $e) {
            logActivity("login", "Login error: " . $e->getMessage(), "error");
            $error = "An error occurred. Please try again later.";
        }
    } else {
        $error = "Please enter both username and password.";
    }
}
?>
