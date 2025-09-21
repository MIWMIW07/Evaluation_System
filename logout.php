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
            .preloader-overlay {
                position: fixed;
                top: 0; left: 0;
                width: 100vw;
                height: 100vh;
                background: linear-gradient(135deg, maroon, darkmaroon, goldenrod);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-direction: column;
                transition: opacity 0.4s;
            }
             /* Circular border */
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

    /* Rotating logo */
    .circle-border img {
      width: 100px;
      height: 100px;
      animation: spinLogo 5s linear infinite;

    }

    /* Animations */
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
   <div id="preloader">
    <div class="circle-border">
      <img src="logo.png" alt="Logo">
    </div>
  </div>

  <script>
    setTimeout(() => {
      window.location.href = "login.php?logout_message=<?php echo $logout_message; ?>";
    }, 3000);
  </script>
</body>
</html>
