<?php
session_start();

// Store user info for goodbye message
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';

// Save message to session
$_SESSION['logout_message'] = "👋 Goodbye, " . htmlspecialchars($user_name) . "! You have been logged out successfully.";
$_SESSION['user_type_was'] = $user_type;

// Clear session but keep logout_message
$temp_message = $_SESSION['logout_message'];
$temp_user_type = $_SESSION['user_type_was'];
session_unset();
session_destroy();

// Keep values in PHP for passing to JS redirect
$logout_message = urlencode($temp_message);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Logging out...</title>
  <style>
    .preloader {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: white;
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }
    .loader {
      border: 8px solid #f3f3f3;
      border-top: 8px solid maroon;
      border-radius: 50%;
      width: 60px; height: 60px;
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
  <div class="preloader">
    <div class="loader"></div>
  </div>

  <script>
    setTimeout(() => {
      window.location.href = "login.php?logout_message=<?php echo $logout_message; ?>";
    }, 3000);
  </script>
</body>
</html>
