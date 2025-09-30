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
                    $_SESSION['username'] = 'admin';
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
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
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
            margin: 0 auto 20px;
            position: relative;
        }
        
        .circle-border img {
            width: 100px;
            height: 100px;
            animation: spinLogo 5s linear infinite;
            border-radius: 50%;
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
            animation: spinLogo 5s linear infinite;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }
        
        .loading-content {
            text-align: center;
            color: white;
        }
        
        .welcome-message {
            font-size: 24px;
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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes spinLogo {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(-360deg); }
        }
        
        @keyframes glow {
            0% { 
                box-shadow: 0 0 10px goldenrod, 0 0 20px maroon;
                border-width: 6px;
            }
            100% { 
                box-shadow: 0 0 30px gold, 0 0 60px darkred;
                border-width: 8px;
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
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="preloader-overlay" id="preloader">
        <div class="loading-content">
            <div class="logo-container">
                <div class="circle-border pulse">
                    <!-- Logo with fallback -->
                    <img src="assets/logo.png" alt="School Logo" id="school-logo" 
                         onerror="this.style.display='none'; document.getElementById('logo-placeholder').style.display='flex';">
                    <div id="logo-placeholder" class="logo-placeholder" style="display: none;">
                        SCHOOL<br>LOGO
                    </div>
                </div>
            </div>
            
            <div class="welcome-message">Login Successful!</div>
            <div class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>!</div>
            <div class="loading-text">Loading your dashboard...</div>
            
            <div class="progress-bar">
                <div class="progress"></div>
            </div>
            
            <div class="redirect-info">
                You will be automatically redirected in <span id="countdown">3</span> seconds
            </div>
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
