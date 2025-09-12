<?php
// Database connection using environment variables
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'evaluation_db';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';

// Create connection without database first
$conn = new mysqli($db_host, $db_user, $db_pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS `$db_name`";
if ($conn->query($sql)) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select database
$conn->select_db($db_name);

// Create teachers table
$sql = "CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    subject VARCHAR(100) NOT NULL
)";

if ($conn->query($sql)) {
    echo "Teachers table created successfully<br>";
} else {
    echo "Error creating teachers table: " . $conn->error . "<br>";
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
    q1_1 INT NOT NULL,
    q1_2 INT NOT NULL,
    q1_3 INT NOT NULL,
    q1_4 INT NOT NULL,
    q1_5 INT NOT NULL,
    q1_6 INT NOT NULL,
    q2_1 INT NOT NULL,
    q2_2 INT NOT NULL,
    q2_3 INT NOT NULL,
    q2_4 INT NOT NULL,
    q3_1 INT NOT NULL,
    q3_2 INT NOT NULL,
    q3_3 INT NOT NULL,
    q3_4 INT NOT NULL,
    q4_1 INT NOT NULL,
    q4_2 INT NOT NULL,
    q4_3 INT NOT NULL,
    q4_4 INT NOT NULL,
    q4_5 INT NOT NULL,
    q4_6 INT NOT NULL,
    comments TEXT,
    evaluation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
)";

if ($conn->query($sql)) {
    echo "Evaluations table created successfully<br>";
} else {
    echo "Error creating evaluations table: " . $conn->error . "<br>";
}

// Check if teachers already exist
$check_sql = "SELECT COUNT(*) as count FROM teachers";
$result = $conn->query($check_sql);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Insert sample teachers only if none exist
    $teachers = [
        ['ABSALON, JIMMY P.', 'Computer Science'],
        ['CRUZ, MARIA SANTOS', 'Mathematics'],
        ['REYES, JUAN DELA', 'English'],
        ['SANTOS, ANNA LOPEZ', 'Science']
    ];
    
    $stmt = $conn->prepare("INSERT INTO teachers (name, subject) VALUES (?, ?)");
    
    foreach ($teachers as $teacher) {
        $stmt->bind_param("ss", $teacher[0], $teacher[1]);
        $stmt->execute();
    }
    
    $stmt->close();
    echo "Sample teachers inserted successfully<br>";
} else {
    echo "Teachers already exist, skipping insertion<br>";
}

echo "Database setup completed!";

$conn->close();
?>
