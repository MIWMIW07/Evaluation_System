<?php
// logout.php - Logs the user out and records activity

session_start();
require_once __DIR__ . '/includes/db_connection.php';

// Capture session info before destroying it
$user_id   = $_SESSION['user_id']   ?? null;
$username  = $_SESSION['username']  ?? 'Unknown';
$full_name = $_SESSION['full_name'] ?? 'User';
$user_type = $_SESSION['user_type'] ?? '';

// ✅ Log activity if user was logged in
if ($user_id) {
    logActivity("logout", "User '{$username}' logged out", "success", $user_id);
}

// Goodbye message
$logout_message = "👋 Goodbye, " . htmlspecialchars($full_name) . "! You have been logged out successfully.";

// Destroy session safely
session_unset();
session_destroy();

// Encode message for safe redirect
$logout_message = urlencode($logout_message);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logging Out...</title>
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
        
        .goodbye-message {
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
                <div class="goodbye-message">Logging Out...</div>
                <div class="user-info">Goodbye, <?php echo htmlspecialchars($full_name); ?>!</div>
            </div>
        </div>
        
        <div class="loading-text">Closing your session...</div>
        
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

        // Redirect after 3 seconds to index.php with logout message
        setTimeout(() => {
            window.location.href = "index.php?logout_message=<?php echo $logout_message; ?>";
        }, 3000);

        // Allow user to click to skip waiting
        document.getElementById('preloader').addEventListener('click', () => {
            window.location.href = "index.php?logout_message=<?php echo $logout_message; ?>";
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
