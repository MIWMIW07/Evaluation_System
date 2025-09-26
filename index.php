<?php
// index.php – Login Page (UI Only)
session_start();
if (isset($_SESSION['user_id'])) {
    // Already logged in → go to dashboard
    if ($_SESSION['user_type'] === 'student') {
        header("Location: student_dashboard.php");
    } else {
        header("Location: admin.php");
    }
    exit();
}

// Error handling
$errorMessage = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'required') {
        $errorMessage = 'Please enter both username and password.';
    } elseif ($_GET['error'] === 'invalid') {
        $errorMessage = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TES Evaluation System – Login</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      background: linear-gradient(135deg, #6A0DAD, #FFD700); /* Maroon/Gold theme */
      animation: gradientBG 10s ease infinite;
    }

    @keyframes gradientBG {
      0% { background: linear-gradient(135deg, #6A0DAD, #FFD700); }
      50% { background: linear-gradient(135deg, #FFD700, #6A0DAD); }
      100% { background: linear-gradient(135deg, #6A0DAD, #FFD700); }
    }

    .login-container {
      background: rgba(255, 255, 255, 0.95);
      padding: 40px;
      border-radius: 20px;
      width: 400px;
      box-shadow: 0px 8px 20px rgba(0, 0, 0, 0.3);
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .login-container h2 {
      color: #6A0DAD;
      font-size: 28px;
      margin-bottom: 25px;
    }

    .form-group {
      margin-bottom: 20px;
      text-align: left;
    }

    .form-group label {
      display: block;
      font-weight: bold;
      margin-bottom: 8px;
      color: #333;
    }

    .form-group input {
      width: 100%;
      padding: 12px;
      border-radius: 10px;
      border: 1px solid #ccc;
      font-size: 16px;
      transition: 0.3s;
    }

    .form-group input:focus {
      border-color: #6A0DAD;
      outline: none;
      box-shadow: 0px 0px 8px rgba(106, 13, 173, 0.5);
    }

    .btn {
      width: 100%;
      padding: 14px;
      background: #6A0DAD;
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 18px;
      cursor: pointer;
      transition: 0.3s;
    }

    .btn:hover {
      background: #FFD700;
      color: #6A0DAD;
      transform: scale(1.05);
    }

    .error-message {
      color: red;
      margin-bottom: 15px;
      font-weight: bold;
    }

    /* Floating shapes */
    .shape {
      position: absolute;
      border-radius: 50%;
      opacity: 0.3;
      animation: float 6s ease-in-out infinite;
    }

    .shape1 { width: 100px; height: 100px; background: #FFD700; top: -50px; left: -50px; }
    .shape2 { width: 120px; height: 120px; background: #6A0DAD; bottom: -60px; right: -60px; }

    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-20px); }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="shape shape1"></div>
    <div class="shape shape2"></div>
    <h2>TES Evaluation System</h2>
    <?php if (!empty($errorMessage)): ?>
      <p class="error-message"><?= htmlspecialchars($errorMessage) ?></p>
    <?php endif; ?>
    <form action="login.php" method="POST">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn">Sign In</button>
    </form>
  </div>
</body>
</html>
