<?php
// logout.php - Logs the user out and records activity

session_start();
require_once __DIR__ . '/includes/db_connection.php';

// Store user info before destroying session
$user_id   = $_SESSION['user_id']   ?? null;
$username  = $_SESSION['username']  ?? 'Unknown';
$full_name = $_SESSION['full_name'] ?? 'User';
$user_type = $_SESSION['user_type'] ?? '';

// ✅ Log activity if user was logged in
if ($user_id) {
    logActivity("logout", "$username logged out", "success", $user_id);
}

// Prepare goodbye message
$logout_message = "👋 Goodbye, " . htmlspecialchars($full_name) . "! You have been logged out successfully.";

// Destroy session
session_unset();
session_destroy();

// Encode message for redirect
$logout_message = urlencode($logout_message);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Redirecting...</title>
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
        }
        .circle-border img {
            width: 100px;
            height: 100px;
            animation: spinLogo 5s linear infinite;
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
    </style>
</head>
<body>
   <div class="preloader-overlay" id="preloader">
        <div class="circle-border">
            <img src="logo.png" alt="Logo" onerror="this.style.display='none'">
        </div>
    </div>

    <script>
        // Fade out before redirect
        setTimeout(() => {
            document.getElementById("preloader").style.opacity = "0";
        }, 2500);

        // Redirect after 3 seconds
        setTimeout(() => {
            window.location.href = "login.php?logout_message=<?php echo $logout_message; ?>";
        }, 3000);
    </script>
</body>
</html>
