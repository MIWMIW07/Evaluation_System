<?php
session_start();
require_once 'includes/db_connection.php';

// Initialize variables
$redirect_url = null;
$show_preloader = false;
$login_message = "";
$message_type = ""; // success or error

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($username) || empty($password)) {
        $login_message = "Please enter both username and password.";
        $message_type = "error";
        $_SESSION['error'] = $login_message;
    } else {
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
                    
                    $login_message = "Admin login successful! Redirecting to admin panel...";
                    $message_type = "success";
                    $redirect_url = "admin.php";
                    $show_preloader = true;
                    
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

                        $login_message = "Login successful! Welcome back, " . htmlspecialchars($studentData['first_name']) . "!";
                        $message_type = "success";
                        $redirect_url = "student_dashboard.php";
                        $show_preloader = true;
                    } else {
                        $login_message = "Could not retrieve student information.";
                        $message_type = "error";
                        $_SESSION['error'] = $login_message;
                    }
                }
            } else {
                // Invalid credentials
                if (function_exists('logActivity')) {
                    logActivity("login_failed", "Invalid login attempt for username: $username", "error", null);
                }
                $login_message = "Invalid username or password. Please try again.";
                $message_type = "error";
                $_SESSION['error'] = $login_message;
            }

        } catch (Exception $e) {
            // Handle errors
            error_log("Login error: " . $e->getMessage());
            $login_message = "Login system temporarily unavailable. Please try again later.";
            $message_type = "error";
            $_SESSION['error'] = $login_message;
        }
    }
    
    // If there's an error and we're not showing preloader, redirect back to login
    if ($message_type === "error" && !$show_preloader) {
        header("Location: index.php");
        exit;
    }
} else {
    // Not a POST request, redirect to login page
    header("Location: index.php");
    exit;
}

// If we have a redirect URL and should show preloader, display the preloader page with message
if ($show_preloader && $redirect_url) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Successful - Redirecting...</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body, html {
            width: 100%;
            height: 100%;
            margin: 0;
            background: linear-gradient(135deg, #4A0012 0%, #800020 25%, #DAA520 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            font-family: Arial, sans-serif;
        }
        .preloader-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100vw;
            height: 100vh;
            background: linear-gradient(135deg, maroon, darkred, goldenrod);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            transition: opacity 0.6s ease;
        }
        .circle-border {
            width: 170px;
            height: 170px;
            border-radius: 50%;
            border: 6px solid goldenrod;
            border-top: 6px solid gold;
            border-bottom: 6px solid maroon;
            border-left: 6px solid lightgoldenrodyellow;
            border-right: 6px solid darkred;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: spinBorder 3s linear infinite, glow 2s ease-in-out infinite alternate;
            box-shadow: 0 0 20px goldenrod, 0 0 40px maroon;
            margin-bottom: 20px;
        }
        .circle-border img {
            width: 100px;
            height: 100px;
            animation: spinLogo 5s linear infinite;
        }
        .message-container {
            text-align: center;
            max-width: 80%;
            margin-top: 20px;
        }
        .success-message {
            color: #90EE90;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 0 0 10px rgba(0,0,0,0.5);
            animation: fadeIn 1s ease-in;
        }
        .loading-text {
            color: white;
            font-size: 16px;
            text-shadow: 0 0 10px rgba(0,0,0,0.5);
        }
        .error-message {
            color: #FFB6C1;
            font-size: 18px;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(0,0,0,0.5);
            animation: shake 0.5s ease-in-out;
        }
        @keyframes spinBorder {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes spinLogo {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(-360deg); }
        }
        @keyframes glow {
            0% { box-shadow: 0 0 10px goldenrod, 0 0 20px maroon; }
            100% { box-shadow: 0 0 30px gold, 0 0 60px darkred; }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>
   <div class="preloader-overlay" id="preloader">
        <div class="circle-border">
            <img src="logo.png" alt="Logo" onerror="this.style.display='none'">
        </div>
        <div class="message-container">
            <?php if ($message_type === 'success'): ?>
                <div class="success-message">✓ <?php echo htmlspecialchars($login_message); ?></div>
                <div class="loading-text">Redirecting to your dashboard...</div>
            <?php else: ?>
                <div class="error-message">✗ <?php echo htmlspecialchars($login_message); ?></div>
                <div class="loading-text">Redirecting back to login...</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Fade out before redirect
        setTimeout(() => {
            document.getElementById("preloader").style.opacity = "0";
        }, 2500);

        // Redirect after 3 seconds
        setTimeout(() => {
            window.location.href = "<?php echo $message_type === 'success' ? $redirect_url : 'index.php'; ?>";
        }, 3000);
    </script>
</body>
</html>
<?php
    exit; // Important: stop further execution
}
?>
