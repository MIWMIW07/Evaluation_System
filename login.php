<?php
session_start();
require_once 'includes/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Please enter both username and password.";
        header("Location: index.php");
        exit;
    }

    try {
        $manager = getDataManager();
        $auth = $manager->authenticateUser($username, $password);

        if ($auth) {
            if ($auth['type'] === 'admin') {
                // Admin login
                $_SESSION['user_id'] = 'admin';
                $_SESSION['user_type'] = 'admin';
                $_SESSION['username'] = 'admin';
                $_SESSION['full_name'] = 'System Administrator';
                
                header("Location: admin.php");
                exit;
                
            } elseif ($auth['type'] === 'student') {
                // Student login - get detailed student data
                $reflection = new ReflectionClass($manager);
                $method = $reflection->getMethod('findStudent');
                $method->setAccessible(true);
                $studentData = $method->invoke($manager, $username, $password);
                
                if ($studentData) {
                    // Set all session variables
                    $_SESSION['user_id'] = $studentData['student_id'];
                    $_SESSION['user_type'] = 'student';
                    $_SESSION['username'] = $studentData['username'];
                    $_SESSION['full_name'] = $studentData['full_name'];
                    $_SESSION['first_name'] = $studentData['first_name'];
                    $_SESSION['last_name'] = $studentData['last_name'];
                    $_SESSION['section'] = $studentData['section'];
                    $_SESSION['program'] = $studentData['program'];
                    $_SESSION['student_id'] = $studentData['student_id'];

                    // Log successful login
                    if (function_exists('logActivity')) {
                        logActivity("login", "Student {$studentData['username']} logged in", "success", $studentData['student_id']);
                    }

                    header("Location: student_dashboard.php");
                    exit;
                } else {
                    $_SESSION['error'] = "Could not retrieve student information.";
                    header("Location: index.php");
                    exit;
                }
            }
        } else {
            // Invalid credentials
            if (function_exists('logActivity')) {
                logActivity("login_failed", "Invalid login attempt for username: $username", "error", null);
            }
            $_SESSION['error'] = "Invalid username or password.";
            header("Location: index.php");
            exit;
        }

    } catch (Exception $e) {
        // Handle errors
        error_log("Login error: " . $e->getMessage());
        $_SESSION['error'] = "Login system temporarily unavailable. Please try again later.";
        header("Location: index.php");
        exit;
    }
} else {
    // Not a POST request, redirect to login page
    header("Location: index.php");
    exit;
}
?>
