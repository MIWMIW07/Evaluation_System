<?php
// logout.php - Logs the user out and displays a logout screen with preloader

session_start();
require_once __DIR__ . '/includes/db_connection.php';

// Capture session info before destroying it
$user_id   = $_SESSION['user_id']   ?? null;
$username  = $_SESSION['username']  ?? 'Unknown';
$full_name = $_SESSION['full_name'] ?? 'User';

// ✅ Log activity if user was logged in
if ($user_id) {
    logActivity("logout", "User '{$username}' logged out", "success", $user_id);
}

// Destroy session safely
session_unset();
session_destroy();

// Prepare safe text
$safe_name = htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8');
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
      font-family: Arial, sans-serif;
      background: linear-gradient(135deg, #4A0012 0%, #800020 25%, #DAA520 100%);
      overflow: hidden;
    }

    /* Preloader */
    .preloader-overlay {
      position: fixed;
      top: 0; left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, maroon, darkred, goldenrod);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      transition: opacity 0.6s ease;
      z-index: 9999;
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

    /* Logout card */
    .wrap {
      display: none; /* hidden at first */
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.25rem;
      box-sizing: border-box;
    }
    .card {
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 8px 30px rgba(15,23,42,0.2);
      padding: 2rem;
      max-width: 600px;
      width: 100%;
      text-align: center;
    }
    .emoji {
      font-size: 3rem;
      display: block;
      margin-bottom: 0.5rem;
    }
    h1 { margin: 0.5rem 0; }
    .lead { color: #555; }
    .btn {
      display: inline-block;
      margin-top: 1rem;
      padding: 0.6rem 1rem;
      border-radius: 8px;
      text-decoration: none;
      font-weight: bold;
      background: linear-gradient(90deg, #2563eb, #1e40af);
      color: #fff;
    }
  </style>
</head>
<body>
  <!-- Preloader -->
  <div class="preloader-overlay" id="preloader">
    <div class="circle-border">
      <img src="logo.png" alt="Logo" onerror="this.style.display='none'">
    </div>
  </div>

  <!-- Logout Message -->
  <div class="wrap" id="logoutMessage">
    <div class="card">
      <span class="emoji">👋</span>
      <h1>Goodbye, <?= $safe_name ?>!</h1>
      <p class="lead">You have been logged out successfully.</p>
      <a class="btn" href="index.php">Return to Home</a>
    </div>
  </div>

  <script>
    // Hide preloader and show logout message
    setTimeout(() => {
      document.getElementById("preloader").style.opacity = "0";
      setTimeout(() => {
        document.getElementById("preloader").style.display = "none";
        document.getElementById("logoutMessage").style.display = "flex";
      }, 600);
    }, 2500);

    // Redirect after 5s
    setTimeout(() => {
      window.location.href = "index.php";
    }, 5000);
  </script>
</body>
</html>
