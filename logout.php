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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logging Out...</title>
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
            margin-bottom: 30px;
        }
        
        .circle-border img {
            width: 100px;
            height: 100px;
            animation: spinLogo 5s linear infinite;
        }
        
        .message-box {
            background: linear-gradient(135deg, rgba(128, 0, 32, 0.9), rgba(74, 0, 18, 0.9));
            border: 3px solid goldenrod;
            border-radius: 15px;
            padding: 25px 40px;
            text-align: center;
            color: gold;
            box-shadow: 0 0 30px rgba(218, 165, 32, 0.5), 
                        0 0 60px rgba(128, 0, 32, 0.3),
                        inset 0 0 20px rgba(255, 215, 0, 0.2);
            max-width: 400px;
            margin: 20px;
            animation: messageGlow 3s ease-in-out infinite alternate;
            backdrop-filter: blur(10px);
        }
        
        .message-box h2 {
            margin: 0 0 15px 0;
            font-size: 1.8em;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
            color: #FFD700;
        }
        
        .message-box p {
            margin: 0;
            font-size: 1.2em;
            line-height: 1.5;
            color: #FFF8DC;
        }
        
        .redirect-text {
            margin-top: 15px;
            font-size: 0.9em;
            color: #DAA520;
            font-style: italic;
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
        
        @keyframes messageGlow {
            0% { 
                box-shadow: 0 0 20px rgba(218, 165, 32, 0.5), 
                           0 0 40px rgba(128, 0, 32, 0.3),
                           inset 0 0 15px rgba(255, 215, 0, 0.2);
                transform: scale(1);
            }
            100% { 
                box-shadow: 0 0 40px rgba(255, 215, 0, 0.7), 
                           0 0 80px rgba(128, 0, 32, 0.5),
                           inset 0 0 25px rgba(255, 215, 0, 0.3);
                transform: scale(1.02);
            }
        }
    </style>
</head>
<body>
   <div class="preloader-overlay" id="preloader">
        <div class="circle-border">
            <img src="logo.png" alt="Logo" onerror="this.style.display='none'">
        </div>
        
        <div class="message-box">
            <h2>Logout Successful</h2>
            <p><?php echo $logout_message; ?></p>
            <div class="redirect-text">Redirecting to homepage...</div>
        </div>
    </div>

    <script>
        // Fade out before redirect
        setTimeout(() => {
            document.getElementById("preloader").style.opacity = "0";
        }, 2500);

        // Redirect after 3 seconds
        setTimeout(() => {
            window.location.href = "index.php";
        }, 3000);
    </script>
</body>
</html>
