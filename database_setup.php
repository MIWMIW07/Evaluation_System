<?php
// Enhanced database setup for Railway deployment
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Setup for Teacher Evaluation System</h2>";

// Database connection using environment variables
$db_host = $_ENV['MYSQL_HOST'] ?? $_ENV['DB_HOST'] ?? getenv('MYSQL_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$db_name = $_ENV['MYSQL_DATABASE'] ?? $_ENV['DB_NAME'] ?? getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: 'railway';
$db_user = $_ENV['MYSQL_USER'] ?? $_ENV['DB_USER'] ?? getenv('MYSQL_USER') ?: getenv('DB_USER') ?: 'root';
$db_pass = $_ENV['MYSQL_PASSWORD'] ?? $_ENV['DB_PASS'] ?? getenv('MYSQL_PASSWORD') ?: getenv('DB_PASS') ?: '';
$db_port = $_ENV['MYSQL_PORT'] ?? $_ENV['DB_PORT'] ?? getenv('MYSQL_PORT') ?: getenv('DB_PORT') ?: 3306;

// Also try Railway's DATABASE_URL format
$database_url = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
if ($database_url) {
    $url_parts = parse_url($database_url);
    if ($url_parts) {
        $db_host = $url_parts['host'] ?? $db_host;
        $db_user = $url_parts['user'] ?? $db_user;
        $db_pass = $url_parts['pass'] ?? $db_pass;
        $db_name = ltrim($url_parts['path'] ?? '', '/') ?: $db_name;
        $db_port = $url_parts['port'] ?? $db_port;
    }
}

echo "<p>Connecting to: Host=$db_host, Database=$db_name, User=$db_user, Port=$db_port</p>";

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

// Check connection
if ($conn->connect_error) {
    die("<p style='color:red;'>Connection failed: " . $conn->connect_error . "</p>");
}

echo "<p style='color:green;'>✓ Connected to database successfully!</p>";

// Set charset
$conn->set_charset("utf8mb4");

// Create teachers table
$sql = "CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "<p style='color:green;'>✓ Teachers table created successfully</p>";
} else {
    echo "<p style='color:red;'>✗ Error creating teachers table: " . $conn->error . "</p>";
}

// Create evaluations table
$sql = "CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    student_name VARCHAR(100) NOT NULL,
    section VARCHAR(50) NOT NULL,
    program VARCHAR(100) NOT NULL,
    teacher_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    q1_1 INT NOT NULL CHECK (q1_1 BETWEEN 1 AND 5),
    q1_2 INT NOT NULL CHECK (q1_2 BETWEEN 1 AND 5),
    q1_3 INT NOT NULL CHECK (q1_3 BETWEEN 1 AND 5),
    q1_4 INT NOT NULL CHECK (q1_4 BETWEEN 1 AND 5),
    q1_5 INT NOT NULL CHECK (q1_5 BETWEEN 1 AND 5),
    q1_6 INT NOT NULL CHECK (q1_6 BETWEEN 1 AND 5),
    q2_1 INT NOT NULL CHECK (q2_1 BETWEEN 1 AND 5),
    q2_2 INT NOT NULL CHECK (q2_2 BETWEEN 1 AND 5),
    q2_3 INT NOT NULL CHECK (q2_3 BETWEEN 1 AND 5),
    q2_4 INT NOT NULL CHECK (q2_4 BETWEEN 1 AND 5),
    q3_1 INT NOT NULL CHECK (q3_1 BETWEEN 1 AND 5),
    q3_2 INT NOT NULL CHECK (q3_2 BETWEEN 1 AND 5),
    q3_3 INT NOT NULL CHECK (q3_3 BETWEEN 1 AND 5),
    q3_4 INT NOT NULL CHECK (q3_4 BETWEEN 1 AND 5),
    q4_1 INT NOT NULL CHECK (q4_1 BETWEEN 1 AND 5),
    q4_2 INT NOT NULL CHECK (q4_2 BETWEEN 1 AND 5),
    q4_3 INT NOT NULL CHECK (q4_3 BETWEEN 1 AND 5),
    q4_4 INT NOT NULL CHECK (q4_4 BETWEEN 1 AND 5),
    q4_5 INT NOT NULL CHECK (q4_5 BETWEEN 1 AND 5),
    q4_6 INT NOT NULL CHECK (q4_6 BETWEEN 1 AND 5),
    comments TEXT,
    evaluation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_teacher (student_id, teacher_id)
)";

if ($conn->query($sql)) {
    echo "<p style='color:green;'>✓ Evaluations table created successfully</p>";
} else {
    echo "<p style='color:red;'>✗ Error creating evaluations table: " . $conn->error . "</p>";
}

// Check if teachers already exist
$check_sql = "SELECT COUNT(*) as count FROM teachers";
$result = $conn->query($check_sql);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Insert sample teachers
    $teachers = [
        ['ABSALON, JIMMY P.', 'Computer Science'],
        ['CRUZ, MARIA SANTOS', 'Mathematics'],
        ['REYES, JUAN DELA', 'English Literature'],
        ['SANTOS, ANNA LOPEZ', 'General Science'],
        ['GARCIA, PEDRO MANUEL', 'Physics'],
        ['TORRES, ELENA ROSE', 'Chemistry'],
        ['VALDEZ, ROBERTO CARLOS', 'History'],
        ['MENDOZA, SARAH JANE', 'Filipino']
    ];
    
    $stmt = $conn->prepare("INSERT INTO teachers (name, subject) VALUES (?, ?)");
    
    $inserted = 0;
    foreach ($teachers as $teacher) {
        $stmt->bind_param("ss", $teacher[0], $teacher[1]);
        if ($stmt->execute()) {
            $inserted++;
        }
    }
    
    $stmt->close();
    echo "<p style='color:green;'>✓ $inserted sample teachers inserted successfully</p>";
} else {
    echo "<p style='color:blue;'>ℹ Teachers already exist ({$row['count']} found), skipping insertion</p>";
}

// Show current teachers
echo "<h3>Current Teachers in Database:</h3>";
$teachers_sql = "SELECT id, name, subject FROM teachers ORDER BY name";
$result = $conn->query($teachers_sql);

if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse:collapse; width:100%; margin:10px 0;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Subject</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['subject']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No teachers found in database.</p>";
}

// Show database info
echo "<h3>Database Information:</h3>";
echo "<p><strong>Tables created:</strong></p>";
echo "<ul>";
echo "<li>✓ teachers - Stores teacher information</li>";
echo "<li>✓ evaluations - Stores student evaluations</li>";
echo "</ul>";

echo "<p><strong>Features:</strong></p>";
echo "<ul>";
echo "<li>✓ Prevents duplicate evaluations (student can only evaluate each teacher once)</li>";
echo "<li>✓ Rating validation (1-5 scale)</li>";
echo "<li>✓ Foreign key constraints</li>";
echo "<li>✓ UTF8MB4 character set support</li>";
echo "</ul>";

echo "<p style='color:green; font-weight:bold; margin:20px 0;'>🎉 Database setup completed successfully!</p>";

echo "<p><a href='index.php' style='background:#4CAF50; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Go to Evaluation Form</a></p>";
echo "<p><a href='admin.php' style='background:#2196F3; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; margin-left:10px;'>Go to Admin Dashboard</a></p>";

$conn->close();
?>
