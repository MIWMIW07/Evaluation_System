<?php
// debug_login.php - Test the actual login process
session_start();
require_once 'includes/db_connection.php';

echo "<h2>Debug Login Test</h2>";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    
    echo "<h3>Login Attempt</h3>";
    echo "<p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>";
    echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
    
    try {
        $manager = getDataManager();
        $auth = $manager->authenticateUser($username, $password);
        
        echo "<p><strong>Authentication Result:</strong> " . ($auth ? 'SUCCESS' : 'FAILED') . "</p>";
        
        if ($auth) {
            echo "<pre>Auth Data: " . print_r($auth, true) . "</pre>";
            
            if ($auth['type'] === 'student') {
                // Get the student data using reflection (same as in the actual login)
                $reflection = new ReflectionClass($manager);
                $method = $reflection->getMethod('findStudent');
                $method->setAccessible(true);
                $studentData = $method->invoke($manager, $username, $password);
                
                echo "<pre>Student Data: " . print_r($studentData, true) . "</pre>";
                
                if ($studentData) {
                    // Set session variables (same as in login.php)
                    $_SESSION['user_id'] = $studentData['student_id'];
                    $_SESSION['user_type'] = 'student';
                    $_SESSION['username'] = $studentData['username'];
                    $_SESSION['full_name'] = $studentData['full_name'];
                    $_SESSION['first_name'] = $studentData['first_name'];
                    $_SESSION['last_name'] = $studentData['last_name'];
                    $_SESSION['section'] = $studentData['section'];
                    $_SESSION['program'] = $studentData['program'];
                    $_SESSION['student_id'] = $studentData['student_id'];
                    
                    echo "<h3>Session Variables Set:</h3>";
                    echo "<pre>";
                    foreach ($_SESSION as $key => $value) {
                        echo "$key: " . htmlspecialchars($value) . "\n";
                    }
                    echo "</pre>";
                    
                    echo "<p><a href='student_dashboard.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Student Dashboard</a></p>";
                }
            }
        } else {
            echo "<p style='color: red;'>Login failed - check credentials</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    // Show login form
    ?>
    <form method="POST" style="max-width: 400px;">
        <div style="margin-bottom: 15px;">
            <label>Username:</label><br>
            <input type="text" name="username" value="ADVINCULALEBRONJAMES" style="width: 100%; padding: 8px;">
        </div>
        <div style="margin-bottom: 15px;">
            <label>Password:</label><br>
            <input type="text" name="password" value="pass123" style="width: 100%; padding: 8px;">
        </div>
        <button type="submit" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px;">Test Login</button>
    </form>
    <?php
}

echo "<br><a href='index.php'>← Back to Main Login</a>";
?>
