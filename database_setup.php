<?php
// database_setup.php - Database initialization and table creation
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security check - Only allow access during setup phase
// You can comment out these lines during initial setup, then uncomment for security

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    die('Access Denied: Admin access required for database setup.');
}

// Include database connection with correct path
require_once 'includes/db_connection.php';

$setup_messages = [];
$errors = [];

try {
    $pdo = getDatabaseConnection();
    $setup_messages[] = "✅ Database connection successful!";
    
    // Create users table
    $create_users_table = "CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        user_type VARCHAR(20) NOT NULL DEFAULT 'student',
        full_name VARCHAR(100) NOT NULL,
        student_id VARCHAR(20),
        program VARCHAR(50),
        section VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL
    )";
    
    $pdo->exec($create_users_table);
    $setup_messages[] = "✅ Users table created/verified";
    
    // Create evaluations table
    $create_evaluations_table = "CREATE TABLE IF NOT EXISTS evaluations (
        id SERIAL PRIMARY KEY,
        user_id INTEGER REFERENCES users(id),
        student_id VARCHAR(20) NOT NULL,
        student_name VARCHAR(100) NOT NULL,
        section VARCHAR(50) NOT NULL,
        program VARCHAR(50) NOT NULL,
        teacher_id INTEGER REFERENCES teachers(id),
        subject VARCHAR(100) NOT NULL,
        q1_1 INTEGER NOT NULL CHECK (q1_1 >= 1 AND q1_1 <= 5),
        q1_2 INTEGER NOT NULL CHECK (q1_2 >= 1 AND q1_2 <= 5),
        q1_3 INTEGER NOT NULL CHECK (q1_3 >= 1 AND q1_3 <= 5),
        q1_4 INTEGER NOT NULL CHECK (q1_4 >= 1 AND q1_4 <= 5),
        q1_5 INTEGER NOT NULL CHECK (q1_5 >= 1 AND q1_5 <= 5),
        q1_6 INTEGER NOT NULL CHECK (q1_6 >= 1 AND q1_6 <= 5),
        q2_1 INTEGER NOT NULL CHECK (q2_1 >= 1 AND q2_1 <= 5),
        q2_2 INTEGER NOT NULL CHECK (q2_2 >= 1 AND q2_2 <= 5),
        q2_3 INTEGER NOT NULL CHECK (q2_3 >= 1 AND q2_3 <= 5),
        q2_4 INTEGER NOT NULL CHECK (q2_4 >= 1 AND q2_4 <= 5),
        q3_1 INTEGER NOT NULL CHECK (q3_1 >= 1 AND q3_1 <= 5),
        q3_2 INTEGER NOT NULL CHECK (q3_2 >= 1 AND q3_2 <= 5),
        q3_3 INTEGER NOT NULL CHECK (q3_3 >= 1 AND q3_3 <= 5),
        q3_4 INTEGER NOT NULL CHECK (q3_4 >= 1 AND q3_4 <= 5),
        q4_1 INTEGER NOT NULL CHECK (q4_1 >= 1 AND q4_1 <= 5),
        q4_2 INTEGER NOT NULL CHECK (q4_2 >= 1 AND q4_2 <= 5),
        q4_3 INTEGER NOT NULL CHECK (q4_3 >= 1 AND q4_3 <= 5),
        q4_4 INTEGER NOT NULL CHECK (q4_4 >= 1 AND q4_4 <= 5),
        q4_5 INTEGER NOT NULL CHECK (q4_5 >= 1 AND q4_5 <= 5),
        q4_6 INTEGER NOT NULL CHECK (q4_6 >= 1 AND q4_6 <= 5),
        comments TEXT,
        evaluation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, teacher_id)
    )";
    
    $pdo->exec($create_evaluations_table);
    $setup_messages[] = "✅ Evaluations table created/verified";
    
    // ===============================================
    // NEW TABLES FOR COMPLETE DATABASE IMPLEMENTATION
    // ===============================================
    
    // 1. CREATE SECTIONS TABLE
    $create_sections_table = "CREATE TABLE IF NOT EXISTS sections (
        id SERIAL PRIMARY KEY,
        section_code VARCHAR(20) UNIQUE NOT NULL,
        section_name VARCHAR(100) NOT NULL,
        program VARCHAR(50) NOT NULL,
        year_level VARCHAR(20),
        is_active BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($create_sections_table);
    $setup_messages[] = "✅ Sections table created/verified";
    
    // 2. CREATE STUDENTS TABLE
    $create_students_table = "CREATE TABLE IF NOT EXISTS students (
        id SERIAL PRIMARY KEY,
        student_id VARCHAR(30) UNIQUE,
        last_name VARCHAR(50) NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        middle_name VARCHAR(50),
        full_name VARCHAR(150) NOT NULL,
        section_id INTEGER REFERENCES sections(id),
        is_active BOOLEAN DEFAULT true,
        enrolled_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($create_students_table);
    $setup_messages[] = "✅ Students table created/verified";
    
    // 3. CREATE SECTION_TEACHERS TABLE
    $create_section_teachers_table = "CREATE TABLE IF NOT EXISTS section_teachers (
        id SERIAL PRIMARY KEY,
        section_id INTEGER REFERENCES sections(id),
        teacher_id INTEGER REFERENCES teachers(id),
        subject VARCHAR(100),
        is_active BOOLEAN DEFAULT true,
        assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(section_id, teacher_id)
    )";
    
    $pdo->exec($create_section_teachers_table);
    $setup_messages[] = "✅ Section Teachers table created/verified";
    
    // 4. UPDATE EXISTING USERS TABLE
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS student_table_id INTEGER REFERENCES students(id)");
        $setup_messages[] = "✅ Users table updated with student_table_id column";
    } catch (Exception $e) {
        $setup_messages[] = "ℹ️ Users table already has student_table_id column";
    }
    
    try {
        $pdo->exec("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_student_id_key");
        $setup_messages[] = "✅ Removed student_id unique constraint if it existed";
    } catch (Exception $e) {
        $setup_messages[] = "ℹ️ No student_id constraint to remove";
    }
    
    // 5. UPDATE TEACHERS TABLE
    try {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN IF NOT EXISTS department VARCHAR(50)");
        $setup_messages[] = "✅ Teachers table updated with department column";
    } catch (Exception $e) {
        $setup_messages[] = "ℹ️ Teachers table already has department column";
    }
    
    try {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true");
        $setup_messages[] = "✅ Teachers table updated with is_active column";
    } catch (Exception $e) {
        $setup_messages[] = "ℹ️ Teachers table already has is_active column";
    }
    
    // Insert sample admin user (only if doesn't exist)
    $check_admin = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $check_admin->execute(['admin']);
    
    if ($check_admin->fetchColumn() == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_admin = $pdo->prepare("INSERT INTO users (username, password, user_type, full_name) VALUES (?, ?, ?, ?)");
        $insert_admin->execute(['admin', $admin_password, 'admin', 'System Administrator']);
        $setup_messages[] = "✅ Admin user created (username: admin, password: admin123)";
    } else {
        $setup_messages[] = "ℹ️ Admin user already exists";
    }
    
    // ===============================================
    // INSERT SECTIONS DATA
    // ===============================================
    
    $sections = [
        // College Sections
        ['BSCS-1M1', 'BS Computer Science 1st Year Morning Section 1', 'COLLEGE', '1st Year'],
        ['BSCS-2N1', 'BS Computer Science 2nd Year Night Section 1', 'COLLEGE', '2nd Year'],
        ['BSCS-3M1', 'BS Computer Science 3rd Year Morning Section 1', 'COLLEGE', '3rd Year'],
        ['BSCS-4N1', 'BS Computer Science 4th Year Night Section 1', 'COLLEGE', '4th Year'],
        ['BSOA-1M1', 'BS Office Administration 1st Year Morning Section 1', 'COLLEGE', '1st Year'],
        ['BSOA-2N1', 'BS Office Administration 2nd Year Night Section 1', 'COLLEGE', '2nd Year'],
        ['BSOA-3M1', 'BS Office Administration 3rd Year Morning Section 1', 'COLLEGE', '3rd Year'],
        ['BSOA-4N1', 'BS Office Administration 4th Year Night Section 1', 'COLLEGE', '4th Year'],
        ['EDUC-1M1', 'Bachelor of Elementary Education 1st Year Morning Section 1', 'COLLEGE', '1st Year'],
        ['EDUC-2N1', 'Bachelor of Elementary Education 2nd Year Night Section 1', 'COLLEGE', '2nd Year'],
        ['EDUC-3M1', 'Bachelor of Elementary Education 3rd Year Morning Section 1', 'COLLEGE', '3rd Year'],
        ['EDUC-4M1', 'Bachelor of Elementary Education 4th Year Morning Section 1', 'COLLEGE', '4th Year'],
        ['EDUC-4N1', 'Bachelor of Elementary Education 4th Year Night Section 1', 'COLLEGE', '4th Year'],
        ['BSCS-1SC', 'BS Computer Science 1st Year Sunday Class', 'COLLEGE', '1st Year'],
        ['BSCS-2SC', 'BS Computer Science 2nd Year Sunday Class', 'COLLEGE', '2nd Year'],
        ['BSOA-1SC', 'BS Office Administration 1st Year Sunday Class', 'COLLEGE', '1st Year'],
        ['BSOA-2SC', 'BS Office Administration 2nd Year Sunday Class', 'COLLEGE', '2nd Year'],
        ['EDUC-1SC', 'Bachelor of Technical Vocational Teacher Education 1st Year Sunday Class', 'COLLEGE', '1st Year'],
        ['EDUC-2SC', 'Bachelor of Technical Vocational Teacher Education 2nd Year Sunday Class', 'COLLEGE', '2nd Year'],
        
        // SHS Grade 11 Sections
        ['ABM-1M1', 'Accountancy Business Management Grade 11 Morning Section 1', 'SHS', 'Grade 11'],
        ['ABM-1M2', 'Accountancy Business Management Grade 11 Morning Section 2', 'SHS', 'Grade 11'],
        ['ABM-1N1', 'Accountancy Business Management Grade 11 Night Section 1', 'SHS', 'Grade 11'],
        ['HUMSS-1M1', 'Humanities and Social Sciences Grade 11 Morning Section 1', 'SHS', 'Grade 11'],
        ['HUMSS-1M2', 'Humanities and Social Sciences Grade 11 Morning Section 2', 'SHS', 'Grade 11'],
        ['HUMSS-1M3', 'Humanities and Social Sciences Grade 11 Morning Section 3', 'SHS', 'Grade 11'],
        ['HUMSS-1M4', 'Humanities and Social Sciences Grade 11 Morning Section 4', 'SHS', 'Grade 11'],
        ['HUMSS-1M5', 'Humanities and Social Sciences Grade 11 Morning Section 5', 'SHS', 'Grade 11'],
        ['HUMSS-1N1', 'Humanities and Social Sciences Grade 11 Night Section 1', 'SHS', 'Grade 11'],
        ['HUMSS-1N2', 'Humanities and Social Sciences Grade 11 Night Section 2', 'SHS', 'Grade 11'],
        ['HUMSS-1N3', 'Humanities and Social Sciences Grade 11 Night Section 3', 'SHS', 'Grade 11'],
        ['HE-1M1', 'Home Economics Grade 11 Morning Section 1', 'SHS', 'Grade 11'],
        ['HE-1M2', 'Home Economics Grade 11 Morning Section 2', 'SHS', 'Grade 11'],
        ['HE-1M3', 'Home Economics Grade 11 Morning Section 3', 'SHS', 'Grade 11'],
        ['HE-1M4', 'Home Economics Grade 11 Morning Section 4', 'SHS', 'Grade 11'],
        ['HE-1N1', 'Home Economics Grade 11 Night Section 1', 'SHS', 'Grade 11'],
        ['HE-1N2', 'Home Economics Grade 11 Night Section 2', 'SHS', 'Grade 11'],
        ['ICT-1M1', 'Information and Communication Technology Grade 11 Morning Section 1', 'SHS', 'Grade 11'],
        ['ICT-1M2', 'Information and Communication Technology Grade 11 Morning Section 2', 'SHS', 'Grade 11'],
        ['ICT-1N1', 'Information and Communication Technology Grade 11 Night Section 1', 'SHS', 'Grade 11'],
        ['ICT-1N2', 'Information and Communication Technology Grade 11 Night Section 2', 'SHS', 'Grade 11'],
        
        // SHS Grade 12 Sections  
        ['HUMSS-3M1', 'Humanities and Social Sciences Grade 12 Morning Section 1', 'SHS', 'Grade 12'],
        ['HUMSS-3M2', 'Humanities and Social Sciences Grade 12 Morning Section 2', 'SHS', 'Grade 12'],
        ['HUMSS-3M3', 'Humanities and Social Sciences Grade 12 Morning Section 3', 'SHS', 'Grade 12'],
        ['HUMSS-3M4', 'Humanities and Social Sciences Grade 12 Morning Section 4', 'SHS', 'Grade 12'],
        ['HUMSS-3N1', 'Humanities and Social Sciences Grade 12 Night Section 1', 'SHS', 'Grade 12'],
        ['HUMSS-3N2', 'Humanities and Social Sciences Grade 12 Night Section 2', 'SHS', 'Grade 12'],
        ['HUMSS-3N3', 'Humanities and Social Sciences Grade 12 Night Section 3', 'SHS', 'Grade 12'],
        ['HUMSS-3N4', 'Humanities and Social Sciences Grade 12 Night Section 4', 'SHS', 'Grade 12'],
        ['HE-3M1', 'Home Economics Grade 12 Morning Section 1', 'SHS', 'Grade 12'],
        ['HE-3M2', 'Home Economics Grade 12 Morning Section 2', 'SHS', 'Grade 12'],
        ['HE-3M3', 'Home Economics Grade 12 Morning Section 3', 'SHS', 'Grade 12'],
        ['HE-3M4', 'Home Economics Grade 12 Morning Section 4', 'SHS', 'Grade 12'],
        ['HE-3N1', 'Home Economics Grade 12 Night Section 1', 'SHS', 'Grade 12'],
        ['HE-3N2', 'Home Economics Grade 12 Night Section 2', 'SHS', 'Grade 12'],
        ['HE-3N3', 'Home Economics Grade 12 Night Section 3', 'SHS', 'Grade 12'],
        ['HE-3N4', 'Home Economics Grade 12 Night Section 4', 'SHS', 'Grade 12'],
        ['ICT-3M1', 'Information and Communication Technology Grade 12 Morning Section 1', 'SHS', 'Grade 12'],
        ['ICT-3M2', 'Information and Communication Technology Grade 12 Morning Section 2', 'SHS', 'Grade 12'],
        ['ICT-3N1', 'Information and Communication Technology Grade 12 Night Section 1', 'SHS', 'Grade 12'],
        ['ICT-3N2', 'Information and Communication Technology Grade 12 Night Section 2', 'SHS', 'Grade 12'],
        ['ABM-3M1', 'Accountancy Business Management Grade 12 Morning Section 1', 'SHS', 'Grade 12'],
        ['ABM-3M2', 'Accountancy Business Management Grade 12 Morning Section 2', 'SHS', 'Grade 12'],
        ['ABM-3N1', 'Accountancy Business Management Grade 12 Night Section 1', 'SHS', 'Grade 12'],
        
        // Grade 11 Sunday Classes
        ['HE-11SC', 'Home Economics Grade 11 Sunday Class', 'SHS', 'Grade 11'],
        ['ICT-11SC', 'Information and Communication Technology Grade 11 Sunday Class', 'SHS', 'Grade 11'],
        ['HUMSS-11SC', 'Humanities and Social Sciences Grade 11 Sunday Class', 'SHS', 'Grade 11'],
        ['ABM-11SC', 'Accountancy Business Management Grade 11 Sunday Class', 'SHS', 'Grade 11'],
    
        // Grade 12 Sunday Classes
        ['HE-12SC', 'Home Economics Grade 12 Sunday Class', 'SHS', 'Grade 12'],
        ['ICT-12SC', 'Information and Communication Technology Grade 12 Sunday Class', 'SHS', 'Grade 12'],
        ['HUMSS-12SC', 'Humanities and Social Sciences Grade 12 Sunday Class', 'SHS', 'Grade 12'],
        ['ABM-12SC', 'Accountancy Business Management Grade 12 Sunday Class', 'SHS', 'Grade 12']
        ];
    
    $sections_created = 0;
    foreach ($sections as $section) {
        $check_section = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE section_code = ?");
        $check_section->execute([$section[0]]);
        
        if ($check_section->fetchColumn() == 0) {
            $insert_section = $pdo->prepare("INSERT INTO sections (section_code, section_name, program, year_level) VALUES (?, ?, ?, ?)");
            $insert_section->execute($section);
            $sections_created++;
        }
    }
    $setup_messages[] = "✅ {$sections_created} sections created/verified";
    
    // ===============================================
    // INSERT STUDENTS DATA
    // ===============================================
    
    // COLLEGE STUDENTS
    
    // BSCS-1M1 (Complete list)
    $bscs1m1_students = [
        ['ABAÑO', 'SHAWN ROVIC', 'PARREÑO', 'SHAWN ROVIC PARREÑO ABAÑO'],
        ['ANDRES', 'ALLYSA', 'LIBRANDO', 'ALLYSA LIBRANDO ANDRES'],
        ['CARDIENTE', 'SHIENA', 'AMANTE', 'SHIENA AMANTE CARDIENTE'],
        ['CERNA', 'SHAYRA', 'OMILIG', 'SHAYRA OMILIG CERNA'],
        ['CORTES', 'MARRY JOY', 'LUVIDECES', 'MARRY JOY LUVIDECES CORTES'],
        ['DALUZ', 'MA. KATE JASMIN', 'PORTACIO', 'MA. KATE JASMIN PORTACIO DALUZ'],
        ['ESTOCADO', 'LEOVY JOY', 'CARBONELLO', 'LEOVY JOY CARBONELLO ESTOCADO'],
        ['GARCERA', 'ANGELA', '', 'ANGELA GARCERA'],
        ['JACINTO', 'MA. CLARISSA', 'BAUTISTA', 'MA. CLARISSA BAUTISTA JACINTO'],
        ['LABAGALA', 'TRISHA MAE', 'CADILE', 'TRISHA MAE CADILE LABAGALA'],
        ['MUSCA', 'ROSALINDA', '', 'ROSALINDA MUSCA'],
        ['RAFANAN', 'ROSELYN', 'LANORIA', 'ROSELYN LANORIA RAFANAN'],
        ['SAYNO', 'JANILLE', 'BASINANG', 'JANILLE BASINANG SAYNO'],
        ['ZARSUELO', 'JENNICA ROSE', 'BAMBIBO', 'JENNICA ROSE BAMBIBO ZARSUELO'],
        ['ABIGUELA', 'RAYBAN', 'NAVEA', 'RAYBAN NAVEA ABIGUELA'],
        ['ADONIS', 'JARED', 'DICHUPA', 'JARED DICHUPA ADONIS'],
        ['AMABAO', 'EZEKIEL JEAN', '', 'EZEKIEL JEAN AMABAO'],
        ['BULADO', 'JOHN PAUL', 'VALEROSO', 'JOHN PAUL VALEROSO BULADO'],
        ['CADILE', 'BENCH JOSH', 'MENDOZA', 'BENCH JOSH MENDOZA CADILE'],
        ['CUEVAS', 'CYRUS', 'MARTINEZ', 'CYRUS MARTINEZ CUEVAS'],
        ['DIOQUINO', 'FRANCIS', 'RECTO', 'FRANCIS RECTO DIOQUINO'],
        ['FALCON', 'KARL ANTHONY', 'VERSOZA', 'KARL ANTHONY VERSOZA FALCON'],
        ['KEMPIS', 'BRIAN JOSH', 'ACIDO', 'BRIAN JOSH ACIDO KEMPIS'],
        ['LACUARIN', 'CYRUS', 'MARMETO', 'CYRUS MARMETO LACUARIN'],
        ['LLEGANIA', 'LANCE ALLANDY', 'MONTILLA', 'LANCE ALLANDY MONTILLA LLEGANIA'],
        ['LOREDO', 'CLARENCE', 'CASTUCIANO', 'CLARENCE CASTUCIANO LOREDO'],
        ['LORICA', 'JOSEPH', 'PAMIN', 'JOSEPH PAMIN LORICA'],
        ['MEDROSO', 'VICENTE', 'VENCIO', 'VICENTE VENCIO MEDROSO'],
        ['NOVICIO', 'ACESON', 'LAWA', 'ACESON LAWA NOVICIO'],
        ['QUIRANTE', 'IZEUS JAKE', 'E', 'IZEUS JAKE E QUIRANTE'],
        ['ROA', 'MICHAEL', 'RAMALLOSA', 'MICHAEL RAMALLOSA ROA'],
        ['SAMILLANO', 'EDRIAN', 'PESTAÑO', 'EDRIAN PESTAÑO SAMILLANO'],
        ['VALLEJO', 'JOHN KENNETH ADRIAN', 'EMBESTRO', 'JOHN KENNETH ADRIAN EMBESTRO VALLEJO'],
        ['VERDOQUILLO', 'LEMUEL', 'HITALIA', 'LEMUEL HITALIA VERDOQUILLO'],
        ['VILLANUEVA', 'ROWIE', 'PADEL', 'ROWIE PADEL VILLANUEVA']
    ];
    
    $students_created = 0;
    $section_id = $pdo->query("SELECT id FROM sections WHERE section_code = 'BSCS-1M1'")->fetchColumn();
    
    foreach ($bscs1m1_students as $student) {
        $check_student = $pdo->prepare("SELECT COUNT(*) FROM students WHERE full_name = ? AND section_id = ?");
        $check_student->execute([$student[3], $section_id]);
        
        if ($check_student->fetchColumn() == 0) {
            $insert_student = $pdo->prepare("INSERT INTO students (last_name, first_name, middle_name, full_name, section_id) VALUES (?, ?, ?, ?, ?)");
            $insert_student->execute([$student[0], $student[1], $student[2], $student[3], $section_id]);
            $students_created++;
        }
    }
    $setup_messages[] = "✅ {$students_created} students created for BSCS-1M1";
    
    // BSCS-3M1 (Complete list)
    $bscs3m1_students = [
        ['AMADO', 'MYRLINE', 'CASUPANG', 'MYRLINE CASUPANG AMADO'],
        ['AWAT', 'ARIANNE LEIH', 'AZUPARDO', 'ARIANNE LEIH AZUPARDO AWAT'],
        ['DALUZ', 'LORRAINE', 'PORTACIO', 'LORRAINE PORTACIO DALUZ'],
        ['MARTINEZ', 'ANGELICA ANNE', 'CAJEPE', 'ANGELICA ANNE CAJEPE MARTINEZ'],
        ['TRINIDAD', 'JESLYN', 'SURUIZ', 'JESLYN SURUIZ TRINIDAD'],
        ['ADORNA', 'KENNETH', 'WAGA', 'KENNETH WAGA ADORNA'],
        ['CABRITIT', 'JAYNIEL', 'SANTIAGO', 'JAYNIEL SANTIAGO CABRITIT'],
        ['CELMAR', 'PETER PAUL', '', 'PETER PAUL CELMAR'],
        ['CORDIAL', 'LOUIS ALFRED', 'GUTIERREZ', 'LOUIS ALFRED GUTIERREZ CORDIAL'],
        ['DACULA', 'CHRISTIAN', 'PASCO', 'CHRISTIAN PASCO DACULA'],
        ['DE RAMOS', 'JOHN VINCENT', 'PAILONA', 'JOHN VINCENT PAILONA DE RAMOS'],
        ['DELA CRUZ', 'PRINCE WILLIAM VINCENT', 'ESCARDA', 'PRINCE WILLIAM VINCENT ESCARDA DELA CRUZ'],
        ['ELIJORDE', 'DANILO', 'LAVILLA', 'DANILO LAVILLA ELIJORDE'],
        ['ISRAEL', 'GABRIEL', 'VARGAS', 'GABRIEL VARGAS ISRAEL'],
        ['LEVARDO', 'RIQUIE LARREY', 'MANGILIT', 'RIQUIE LARREY MANGILIT LEVARDO'],
        ['MANGILIT', 'MARK LANDER', 'GARCIA', 'MARK LANDER GARCIA MANGILIT'],
        ['MICOSA', 'MERVIN', 'D', 'MERVIN D MICOSA'],
        ['MONTESCLAROS', 'MICO', 'C', 'MICO C MONTESCLAROS'],
        ['NAVARRO', 'CHRISTIAN SHUCKS', 'CABANLIT', 'CHRISTIAN SHUCKS CABANLIT NAVARRO'],
        ['OMAYAN', 'KING NATHANIEL', 'A', 'KING NATHANIEL A OMAYAN'],
        ['RAÑESES', 'MHAYTHAN JAMES', 'APOSTOL', 'MHAYTHAN JAMES APOSTOL RAÑESES'],
        ['RESTRIVERA', 'ZAIMON JAMES', 'SEGUILLA', 'ZAIMON JAMES SEGUILLA RESTRIVERA'],
        ['TOQUE', 'CHRISTOPHER GLEN', 'RESURRECCION', 'CHRISTOPHER GLEN RESURRECCION TOQUE']
    ];
    
    $students_created = 0;
    $section_id = $pdo->query("SELECT id FROM sections WHERE section_code = 'BSCS-3M1'")->fetchColumn();
    
    foreach ($bscs3m1_students as $student) {
        $check_student = $pdo->prepare("SELECT COUNT(*) FROM students WHERE full_name = ? AND section_id = ?");
        $check_student->execute([$student[3], $section_id]);
        
        if ($check_student->fetchColumn() == 0) {
            $insert_student = $pdo->prepare("INSERT INTO students (last_name, first_name, middle_name, full_name, section_id) VALUES (?, ?, ?, ?, ?)");
            $insert_student->execute([$student[0], $student[1], $student[2], $student[3], $section_id]);
            $students_created++;
        }
    }
    $setup_messages[] = "✅ {$students_created} students created for BSCS-3M1";
    
    // SHS STUDENTS - ABM1M1 (First 20 students as example)
    $abm1m1_students = [
        ['ADVINCULA', 'LEBRON JAMES', 'B.', 'LEBRON JAMES B. ADVINCULA'],
        ['ARTIOLA', 'ALBERT', 'N.', 'ALBERT N. ARTIOLA'],
        ['BRAGAT', 'JOHN MICO', 'L.', 'JOHN MICO L. BRAGAT'],
        ['CLAVO', 'JOSHUA', 'C.', 'JOSHUA C. CLAVO'],
        ['HUIT', 'ADRIAN DAVE', 'G.', 'ADRIAN DAVE G. HUIT'],
        ['JORGE', 'ROGERICK', 'L.', 'ROGERICK L. JORGE'],
        ['ORGAS', 'JOHN', 'J.', 'JOHN J. ORGAS'],
        ['OTACAN', 'DARYL', 'C.', 'DARYL C. OTACAN'],
        ['ALANO', 'JHILIAN', 'G.', 'JHILIAN G. ALANO'],
        ['ARLANTE', 'ARLIE MAY', 'R.', 'ARLIE MAY R. ARLANTE'],
        ['AUDENCIAL', 'MARY ELAINE', 'A.', 'MARY ELAINE A. AUDENCIAL'],
        ['BERNARDINO', 'RAZHEN', 'M.', 'RAZHEN M. BERNARDINO'],
        ['CERTIZA', 'ALMERA JOY', 'B.', 'ALMERA JOY B. CERTIZA'],
        ['CLARIDAD', 'JESSA', 'E.', 'JESSA E. CLARIDAD'],
        ['CTRUZ', 'CHRISTINE', 'C.', 'CHRISTINE C. CRUZ'],
        ['DABODA', 'ROXANNE', 'B.', 'ROXANNE B. DABODA'],
        ['DACULA', 'KAREN CLAIRE', 'P.', 'KAREN CLAIRE P. DACULA'],
        ['ESTRADA', 'MARIVIC', 'M.', 'MARIVIC M. ESTRADA'],
        ['INABANG', 'NORJANNAH', 'D.', 'NORJANNAH D. INABANG'],
        ['ONGCAL', 'CLARISSE ANNE', 'B.', 'CLARISSE ANNE B. ONGCAL']
    ];
    
    $students_created = 0;
    $section_id = $pdo->query("SELECT id FROM sections WHERE section_code = 'ABM1M1'")->fetchColumn();
    
    foreach ($abm1m1_students as $student) {
        $check_student = $pdo->prepare("SELECT COUNT(*) FROM students WHERE full_name = ? AND section_id = ?");
        $check_student->execute([$student[3], $section_id]);
        
        if ($check_student->fetchColumn() == 0) {
            $insert_student = $pdo->prepare("INSERT INTO students (last_name, first_name, middle_name, full_name, section_id) VALUES (?, ?, ?, ?, ?)");
            $insert_student->execute([$student[0], $student[1], $student[2], $student[3], $section_id]);
            $students_created++;
        }
    }
    $setup_messages[] = "✅ {$students_created} students created for ABM1M1";
    
    // ===============================================
    // COMPLETE SECTION TEACHER ASSIGNMENTS
    // ===============================================
    
    // First, let's insert the teachers from your data
    $college_teachers = [
        ['MR. VELE','COLLEGE'],
        ['MR. RODRIGUEZ', 'COLLEGE'],
        ['MR. JIMENEZ', 'COLLEGE'],
        ['MS. RENDORA', 'COLLEGE'],
        ['MR. LACERNA', 'COLLEGE'],
        ['MR. ATIENZA', 'COLLEGE'],
        ['MR. ICABANDE', 'COLLEGE'],
        ['MR. PATIAM', 'COLLEGE'],
        ['MS. VELE', 'COLLEGE'],
        ['MR. RAIVEN GORDON', 'COLLEGE'],
        ['MS. DIMAPILIS', 'COLLEGE'],
        ['MR. ELLO', 'COLLEGE'],
        ['MS. IGHARAS', 'COLLEGE'],
        ['MS. OCTAVO', 'COLLEGE'],
        ['MR. CALCEÑA', 'COLLEGE'],
        ['MS. CARMONA', 'COLLEGE'],
        ['MR. MATILA', 'COLLEGE'],
        ['MR. VALENZUELA', 'COLLEGE'],
        ['MR. ORNACHO', 'COLLEGE'],
        ['MS. TESORO', 'COLLEGE'],
        ['MS. MAGNO', 'COLLEGE'],
        ['MR. PATALEN', 'COLLEGE'],
        ['MR. ESPEÑA', 'COLLEGE'],
        ['MS. GENTEROY', 'COLLEGE']
    ];
    
    $shs_teachers = [
        ['MS. TINGSON', 'SHS'],
        ['MRS. YABUT', 'SHS'],
        ['MS. LAGUADOR', 'SHS'],
        ['MR. SANTOS', 'SHS'],
        ['MS. ANGELES', 'SHS'],
        ['MR. ALCEDO', 'SHS'],
        ['MRS. TESORO', 'SHS'],
        ['MR. UMALI', 'SHS'],
        ['MR. RAINIEL GORDON'],
        ['MR. GARCIA', 'SHS'],
        ['MS. ROQUIOS', 'SHS'],
        ['MS. GAJIRAN', 'SHS'],
        ['MS. RIVERA', 'SHS'],
        ['MR. BATILES', 'SHS'],
        ['MS. LIBRES', 'SHS'],
        ['MS. CARMONA', 'SHS'],
        ['MR. CALCEÑA','SHS'],
        ['MS. GENTEROY','SHS'],
        ['MR. RAIVEN GORDON','SHS'],
        ['MR. ICABANDE','SHS'],
        ['MS. IGHARAS','SHS'],
        ['MR. JIMENEZ','SHS'],
        ['MR. LACERNA','SHS'],
        ['MS. MAGNO','SHS'],
        ['MR. MATILA','SHS'],
        ['MR. ORNACHO','SHS'],
        ['MR. PATIAM','SHS'],
        ['MS. RENDORA','SHS'],
        ['MR. RODRIGUEZ','SHS'],
        ['MR. VALENZUELA','SHS'],
        ['MR. VELE','SHS'],
        ['MS. VELE','SHS']
    ];
    
    $teachers_created = 0;
foreach ($all_teachers as $teacher) {
    $check_teacher = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE name = ? AND department = ?");
    $check_teacher->execute([$teacher[0], $teacher[1]]);
    
    if ($check_teacher->fetchColumn() == 0) {
        $insert_teacher = $pdo->prepare("INSERT INTO teachers (name, subject, department) VALUES (?, ?, ?)");
        $insert_teacher->execute([$teacher[0], 'General', $teacher[1]]);
        $teachers_created++;
    }
}
$setup_messages[] = "✅ {$teachers_created} additional teachers created/verified";
    $setup_messages[] = "✅ {$teachers_created} teachers created/verified";
    
   // COLLEGE SECTION ASSIGNMENTS

// BSCS Sections
$college_assignments = [
    // BSCS-1M1
    'BSCS-1M1' => [
        ['MR. VELE', 'Computer Programming'],
        ['MR. RODRIGUEZ', 'Database Systems'],
        ['MR. JIMENEZ', 'Web Development'],
        ['MR. JIMENEZ', 'Programming Logic'],
        ['MS. RENDORA', 'Mathematics'],
        ['MR. LACERNA', 'Programming Fundamentals'],
        ['MS. RENDORA', 'Statistics'],
        ['MR. ATIENZA', 'Business Management']
    ],
    
    // BSCS-2N1
    'BSCS-2N1' => [
        ['MR. RODRIGUEZ', 'Advanced Programming'],
        ['MR. ICABANDE', 'System Analysis'],
        ['MR. RENDORA', 'Technical Writing'],
        ['MR. V. GORDON', 'Networking']
    ],
    
    'ICT-3M2' => [
        ['MS. LIBRES', 'Programming'],
        ['MR. LACERNA', 'Web Development'],
        ['MR. ICABANDE', 'Database Systems'],
        ['MR. ICABANDE', 'System Analysis'],
        ['MR. UMALI', 'Physical Education'],
        ['MR. V. GORDON', 'Networking']
    ],
    
    'ICT-3N1' => [
        ['MS. LIBRES', 'Programming'],
        ['MR. LACERNA', 'Web Development'],
        ['MR. ICABANDE', 'Database Systems'],
        ['MR. ICABANDE', 'System Analysis'],
        ['MR. UMALI', 'Physical Education'],
        ['MR. V. GORDON', 'Networking']
    ],
    
    'ICT-3N2' => [
        ['MS. LIBRES', 'Programming'],
        ['MR. LACERNA', 'Web Development'],
        ['MR. ICABANDE', 'Database Systems'],
        ['MR. ICABANDE', 'System Analysis'],
        ['MR. UMALI', 'Physical Education'],
        ['MR. V. GORDON', 'Networking']
    ],
    
    // HUMSS Grade 12
    'HUMSS-3M1' => [
        ['MS. CARMONA', 'Creative Nonfiction'],
        ['MR. LACERNA', 'Disciplines and Ideas'],
        ['MS. LIBRES', 'Statistics'],
        ['MR. PATIAM', 'Research'],
        ['MS. RENDORA', 'English'],
        ['MR. GARCIA', 'Philippine Politics'],
        ['MR. BATILES', 'Community Engagement']
    ],
    
    'HUMSS-3M2' => [
        ['MS. CARMONA', 'Creative Nonfiction'],
        ['MR. LACERNA', 'Disciplines and Ideas'],
        ['MS. LIBRES', 'Statistics'],
        ['MR. PATIAM', 'Research'],
        ['MS. RENDORA', 'English'],
        ['MR. GARCIA', 'Philippine Politics'],
        ['MR. BATILES', 'Community Engagement']
    ],
    
    'HUMSS-3M3' => [
        ['MS. CARMONA', 'Creative Nonfiction'],
        ['MR. LACERNA', 'Disciplines and Ideas'],
        ['MS. LIBRES', 'Statistics'],
        ['MR. PATIAM', 'Research'],
        ['MS. RENDORA', 'English'],
        ['MR. GARCIA', 'Philippine Politics'],
        ['MR. BATILES', 'Community Engagement']
    ],
    
    'HUMSS-3M4' => [
        ['MS. CARMONA', 'Creative Nonfiction'],
        ['MR. LACERNA', 'Disciplines and Ideas'],
        ['MS. LIBRES', 'Statistics'],
        ['MR. PATIAM', 'Research'],
        ['MS. RENDORA', 'English'],
        ['MR. GARCIA', 'Philippine Politics'],
        ['MR. BATILES', 'Community Engagement']
    ],
    
    'HUMSS-3N1' => [
        ['MS. CARMONA', 'Creative Nonfiction'],
        ['MR. LACERNA', 'Disciplines and Ideas'],
        ['MS. LIBRES', 'Statistics'],
        ['MR. PATIAM', 'Research'],
        ['MS. RENDORA', 'English'],
        ['MR. GARCIA', 'Philippine Politics']
    ],
    
    'HUMSS-3N2' => [
        ['MS. CARMONA', 'Creative Nonfiction'],
        ['MR. LACERNA', 'Disciplines and Ideas'],
        ['MS. LIBRES', 'Statistics'],
        ['MR. PATIAM', 'Research'],
        ['MR. UMALI', 'Physical Education'],
        ['MR. GARCIA', 'Philippine Politics']
    ],
    
    'HUMSS-3N3' => [
        ['MS. CARMONA', 'Creative Nonfiction'],
        ['MR. LACERNA', 'Disciplines and Ideas'],
        ['MS. LIBRES', 'Statistics'],
        ['MR. PATIAM', 'Research'],
        ['MS. RENDORA', 'English'],
        ['MR. GARCIA', 'Philippine Politics']
    ],
    
    'HUMSS-3N4' => [
        ['MS. CARMONA', 'Creative Nonfiction'],
        ['MR. LACERNA', 'Disciplines and Ideas'],
        ['MS. LIBRES', 'Statistics'],
        ['MR. PATIAM', 'Research'],
        ['MR. UMALI', 'Physical Education'],
        ['MR. GARCIA', 'Philippine Politics']
    ],
    
    // ABM Grade 12
    'ABM-3M1' => [
        ['MS. CARMONA', 'Business Ethics'],
        ['MR. BATILES', 'Business Finance'],
        ['MS. RIVERA', 'Applied Economics'],
        ['MR. PATIAM', 'Research'],
        ['MR. UMALI', 'Physical Education'],
        ['MR. CALCEÑA', 'Business Law'],
        ['MR. CALCEÑA', 'Organization Management']
    ],
    
    'ABM-3M2' => [
        ['MS. CARMONA', 'Business Ethics'],
        ['MR. BATILES', 'Business Finance'],
        ['MS. LIBRES', 'Applied Economics'],
        ['MR. PATIAM', 'Research'],
        ['MS. RENDORA', 'English'],
        ['MR. CALCEÑA', 'Business Law'],
        ['MR. CALCEÑA', 'Organization Management']
    ],
    
    'ABM-3N1' => [
        ['MS. CARMONA', 'Business Ethics'],
        ['MR. BATILES', 'Business Finance'],
        ['MS. LIBRES', 'Applied Economics'],
        ['MR. PATIAM', 'Research'],
        ['MR. UMALI', 'Physical Education'],
        ['MR. CALCEÑA', 'Business Law']
    ]
];

// UPDATED SHS SUNDAY CLASS ASSIGNMENTS - Based on SHS_SC_TEACHERS.docx
$shs_sunday_assignments = [
    // Grade 11 Sunday Classes
    'HE-11SC' => [
        ['MR. LACERNA', 'Home Economics Fundamentals'],
        ['MR. RODRIGUEZ', 'Mathematics'],
        ['MR. VALENZUELA', 'Science'],
        ['MR. MATILA', 'English'],
        ['MR. UMALI', 'Physical Education'],
        ['MS. GENTEROY', 'Values Education']
    ],
    
    'ICT-11SC' => [
        ['MR. LACERNA', 'Programming Fundamentals'],
        ['MR. RODRIGUEZ', 'Computer Systems'],
        ['MR. VALENZUELA', 'Web Development'],
        ['MR. MATILA', 'Technical Writing'],
        ['MR. JIMENEZ', 'Database Management'],
        ['MR. JIMENEZ', 'System Analysis']
    ],
    
    'HUMSS-11SC' => [
        ['MR. ICABANDE', 'Creative Writing'],
        ['MR. PATIAM', 'Research Methods'],
        ['MS. VELE', 'English Literature'],
        ['MS. VELE', 'Filipino Literature'],
        ['MR. MATILA', 'Social Studies']
    ],
    
    'ABM-11SC' => [
        ['MR. ICABANDE', 'Business Mathematics'],
        ['MR. PATIAM', 'Applied Economics'],
        ['MS. VELE', 'Business English'],
        ['MS. VELE', 'Business Communication'],
        ['MR. VALENZUELA', 'Statistics'],
        ['MR. RODRIGUEZ', 'Computer Applications']
    ],
    
    // Grade 12 Sunday Classes
    'HE-12SC' => [
        ['MR. VELE', 'Advanced Home Economics'],
        ['MR. ICABANDE', 'Food Technology'],
        ['MR. PATIAM', 'Entrepreneurship'],
        ['MS. GENTEROY', 'Research Project']
    ],
    
    'ICT-12SC' => [
        ['MR. VELE', 'Advanced Programming'],
        ['MR. ICABANDE', 'System Development'],
        ['MR. PATIAM', 'Capstone Project'],
        ['MR. JIMENEZ', 'Network Administration'],
        ['MR. JIMENEZ', 'Database Administration']
    ],
    
    'HUMSS-12SC' => [
        ['MR. LACERNA', 'Thesis Writing'],
        ['MR. UMALI', 'Community Engagement'],
        ['MR. PATIAM', 'Research Defense'],
        ['MR. ICABANDE', 'Creative Nonfiction'],
        ['MR. VELE', 'Contemporary Issues']
    ],
    
    'ABM-12SC' => [
        ['MR. LACERNA', 'Business Plan Development'],
        ['MR. UMALI', 'Financial Management'],
        ['MR. PATIAM', 'Business Research'],
        ['MS. IGHARAS', 'Accounting Systems'],
        ['MS. IGHARAS', 'Business Ethics']
    ]
];

// Function to assign teachers to sections
function assignTeachersToSection($pdo, $section_code, $teachers, &$total_assignments) {
    // Get section ID
    $section_id = $pdo->prepare("SELECT id FROM sections WHERE section_code = ?");
    $section_id->execute([$section_code]);
    $section_id = $section_id->fetchColumn();
    
    if (!$section_id) {
        return "Section {$section_code} not found";
    }
    
    $assignments_created = 0;
    foreach ($teachers as $teacher_info) {
        $teacher_name = $teacher_info[0];
        $subject = $teacher_info[1];
        
        // Get teacher ID
        $teacher_id = $pdo->prepare("SELECT id FROM teachers WHERE name = ? LIMIT 1");
        $teacher_id->execute([$teacher_name]);
        $teacher_id = $teacher_id->fetchColumn();
        
        if ($teacher_id) {
            // Check if assignment already exists
            $check_assignment = $pdo->prepare("SELECT COUNT(*) FROM section_teachers WHERE section_id = ? AND teacher_id = ? AND subject = ?");
            $check_assignment->execute([$section_id, $teacher_id, $subject]);
            
            if ($check_assignment->fetchColumn() == 0) {
                $insert_assignment = $pdo->prepare("INSERT INTO section_teachers (section_id, teacher_id, subject) VALUES (?, ?, ?)");
                $insert_assignment->execute([$section_id, $teacher_id, $subject]);
                $assignments_created++;
                $total_assignments++;
            }
        }
    }
    
    return $assignments_created;
}

// Execute all assignments
$total_section_assignments = 0;

// Assign College sections
foreach ($college_assignments as $section_code => $teachers) {
    $result = assignTeachersToSection($pdo, $section_code, $teachers, $total_section_assignments);
    if (is_numeric($result)) {
        $setup_messages[] = "✅ {$result} teacher assignments created for {$section_code}";
    } else {
        $errors[] = "❌ Error assigning teachers to {$section_code}: {$result}";
    }
}

// Assign College Sunday classes
foreach ($college_sunday_assignments as $section_code => $teachers) {
    $result = assignTeachersToSection($pdo, $section_code, $teachers, $total_section_assignments);
    if (is_numeric($result)) {
        $setup_messages[] = "✅ {$result} teacher assignments created for {$section_code}";
    } else {
        $errors[] = "❌ Error assigning teachers to {$section_code}: {$result}";
    }
}

// Assign SHS Grade 11 sections
foreach ($shs_grade11_assignments as $section_code => $teachers) {
    $result = assignTeachersToSection($pdo, $section_code, $teachers, $total_section_assignments);
    if (is_numeric($result)) {
        $setup_messages[] = "✅ {$result} teacher assignments created for {$section_code}";
    } else {
        $errors[] = "❌ Error assigning teachers to {$section_code}: {$result}";
    }
}

// Assign SHS Grade 12 sections
foreach ($shs_grade12_assignments as $section_code => $teachers) {
    $result = assignTeachersToSection($pdo, $section_code, $teachers, $total_section_assignments);
    if (is_numeric($result)) {
        $setup_messages[] = "✅ {$result} teacher assignments created for {$section_code}";
    } else {
        $errors[] = "❌ Error assigning teachers to {$section_code}: {$result}";
    }
}

// Assign Updated SHS Sunday classes
foreach ($shs_sunday_assignments as $section_code => $teachers) {
    $result = assignTeachersToSection($pdo, $section_code, $teachers, $total_section_assignments);
    if (is_numeric($result)) {
        $setup_messages[] = "✅ {$result} teacher assignments created for {$section_code}";
    } else {
        $errors[] = "❌ Error assigning teachers to {$section_code}: {$result}";
    }
}

$setup_messages[] = "🎉 Total section-teacher assignments created: {$total_section_assignments}";
$setup_messages[] = "✅ All college and SHS section assignments completed successfully!";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Faculty Evaluation System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            border-left: 4px solid #3c763d;
            color: #3c763d;
        }
        .error {
            background-color: #f2dede;
            border-left: 4px solid #a94442;
            color: #a94442;
        }
        .info {
            background-color: #d9edf7;
            border-left: 4px solid #31708f;
            color: #31708f;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Setup Results</h1>
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($setup_messages)): ?>
            <?php foreach ($setup_messages as $message): ?>
                <div class="message success"><?php echo $message; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="message info">
            <strong>Note:</strong> This setup script should only be run once during initial installation.
            For security reasons, you should restrict access to this file after setup is complete.
        </div>
        
        <p><a href="index.php">Return to Login</a></p>
    </div>
</body>
</html>








