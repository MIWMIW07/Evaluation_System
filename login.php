<?php
// Start output buffering at the VERY beginning to prevent header errors
ob_start();

session_start();
require_once 'includes/db_connection.php';

// Initialize variables
$redirect_url = null;
$show_preloader = false;
$error_message = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // Basic validation
    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password.";
    } else {
        try {
            $manager = getDataManager();
            $auth = $manager->authenticateUser($username, $password);

            if ($auth) {
                if ($auth['type'] === 'admin') {
                    // Admin login
                    $_SESSION['user_id'] = 'admin';
                    $_SESSION['user_type'] = 'admin';
                    $_SESSION['username'] = 'GUIDANCE';
                    $_SESSION['full_name'] = 'System Administrator';
                    
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

                        $redirect_url = "student_dashboard.php";
                        $show_preloader = true;
                    } else {
                        $error_message = "Could not retrieve student information.";
                    }
                }
            } else {
                // Invalid credentials
                if (function_exists('logActivity')) {
                    logActivity("login_failed", "Invalid login attempt for username: $username", "error", null);
                }
                $error_message = "Invalid username or password.";
            }

        } catch (Exception $e) {
            // Handle errors
            error_log("Login error: " . $e->getMessage());
            $error_message = "Login system temporarily unavailable. Please try again later.";
        }
    }

    // If there's an error, set session error and redirect
    if ($error_message) {
        $_SESSION['error'] = $error_message;
        header("Location: index.php");
        exit;
    }
} else {
    // Not a POST request, redirect to login page
    header("Location: index.php");
    exit;
}

// If we reach here, login was successful and we should show preloader
// Clear any output buffer before sending HTML
ob_clean();

// If we have a redirect URL and should show preloader, display the preloader page
if ($show_preloader && $redirect_url) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Successful - Redirecting...</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body, html {
            width: 100%;
            height: 100%;
            margin: 0;
            background: linear-gradient(135deg, #4A0012 0%, #800020 25%, #DAA520 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .preloader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: linear-gradient(135deg, #4A0012, #800020, #DAA520);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            transition: opacity 0.8s ease;
        }
        
        .logo-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            position: relative;
        }
        
        .logo-container {
            position: relative;
            width: 140px;
            height: 140px;
            margin-bottom: 20px;
        }
        
        .logo-image {
            width: 100px;
            height: 100px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -45%);
            z-index: 2;
        }
        
        .rotating-border {
            position: absolute;
            top: 0;
            left: 0;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 3px solid transparent;
            border-top: 3px solid gold;
            border-right: 3px solid maroon;
            border-bottom: 3px solid goldenrod;
            border-left: 3px solid #800020;
            animation: spinBorder 2s linear infinite;
            box-shadow: 0 0 15px rgba(218, 165, 32, 0.5);
        }
        
        .logo-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, gold, goldenrod);
            display: flex;
            justify-content: center;
            align-items: center;
            color: #4A0012;
            font-weight: bold;
            font-size: 14px;
            text-align: center;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 2;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }
        
        .loading-content {
            text-align: center;
            color: white;
        }
        
        .welcome-message {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
        
        .user-info {
            font-size: 18px;
            margin-bottom: 20px;
            opacity: 0.9;
        }
        
        .loading-text {
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .progress-bar {
            width: 300px;
            height: 6px;
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
            margin: 20px auto;
            overflow: hidden;
        }
        
        .progress {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, gold, goldenrod);
            border-radius: 3px;
            animation: progress 3s ease-in-out forwards;
        }
        
        .redirect-info {
            font-size: 14px;
            opacity: 0.7;
            margin-top: 15px;
        }
        
        @keyframes spinBorder {
            0% { 
                transform: rotate(0deg);
                border-top-color: gold;
                border-right-color: maroon;
            }
            25% {
                border-top-color: goldenrod;
                border-right-color: #800020;
            }
            50% {
                border-top-color: #DAA520;
                border-right-color: #4A0012;
            }
            75% {
                border-top-color: #800020;
                border-right-color: gold;
            }
            100% { 
                transform: rotate(360deg);
                border-top-color: gold;
                border-right-color: maroon;
            }
        }
        
        @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="preloader-overlay" id="preloader">
        <div class="logo-section">
            <div class="logo-container">
                <div class="rotating-border"></div>
                <!-- Logo with fallback -->
                <img src="logo.png" alt="School Logo" class="logo-image" id="school-logo" 
                     onerror="this.style.display='none'; document.getElementById('logo-placeholder').style.display='flex';">
                <div id="logo-placeholder" class="logo-placeholder" style="display: none;">
                    School Logo
                </div>
            </div>
            
            <div class="loading-content">
                <div class="welcome-message">Login Successful!</div>
                <div class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>!</div>
            </div>
        </div>
        
        <div class="loading-text">Loading your dashboard...</div>
        
        <div class="progress-bar">
            <div class="progress"></div>
        </div>
        
        <div class="redirect-info">
            You will be automatically redirected in <span id="countdown">3</span> seconds
        </div>
    </div>

    <script>
        // Countdown timer
        let seconds = 3;
        const countdownElement = document.getElementById('countdown');
        
        const countdown = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdown);
            }
        }, 1000);

        // Fade out animation before redirect
        setTimeout(() => {
            const preloader = document.getElementById('preloader');
            preloader.style.opacity = '0';
            preloader.style.transform = 'scale(1.1)';
        }, 2500);

        // Redirect after 3 seconds
        setTimeout(() => {
            window.location.href = "<?php echo $redirect_url; ?>";
        }, 3000);

        // Allow user to click to skip waiting
        document.getElementById('preloader').addEventListener('click', () => {
            window.location.href = "<?php echo $redirect_url; ?>";
        });

        // Auto-hide logo if not found and show placeholder
        window.addEventListener('load', function() {
            const logo = document.getElementById('school-logo');
            const placeholder = document.getElementById('logo-placeholder');
            
            // Check if logo loaded successfully
            if (logo.complete && logo.naturalHeight === 0) {
                logo.style.display = 'none';
                placeholder.style.display = 'flex';
            }
        });
    </script>
</body>
</html>
<?php
}

// End output buffering
ob_end_flush();
exit;
?>
