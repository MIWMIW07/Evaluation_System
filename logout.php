<?php
session_start();

// Store user info for goodbye message
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';

// Save message before destroying session
$logout_message = "👋 Goodbye, " . htmlspecialchars($user_name) . "! You have been logged out successfully.";

// Clear session
session_unset();
session_destroy();

// Encode for URL
$logout_message_encoded = urlencode($logout_message);
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
      background: linear-gradient(135deg, #4A0012 0%, #800020 25%, #DAA520 100%);
      overflow: hidden;
      display: flex;
      justify-content: center;
      align-items: center;
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
  <div class="circle-border">
    <img src="logo.png" alt="Logo">
  </div>

  <script>
    setTimeout(() => {
      window.location.href = "index.php?logout_message=<?php echo $logout_message_encoded; ?>";
    }, 3000);
  </script>
</body>
</html>
