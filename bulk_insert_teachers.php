<?php
// bulk_insert_teachers.php - Insert all teacher assignments without subject
require_once 'includes/db_connection.php';

// Function to clean teacher names
function cleanTeacherName($name) {
    $name = trim($name);
    $name = str_replace(['**', '*'], '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

// Function to insert teacher assignments
function insertTeacherAssignments($pdo) {
    echo "<h2>🔧 Inserting Teacher Assignments</h2>";
    
    // Clear existing assignments first
    echo "<p>Clearing existing teacher assignments...</p>";
    $pdo->exec("DELETE FROM teacher_assignments");
    
    $inserted = 0;
    $stmt = $pdo->prepare("
        INSERT INTO teacher_assignments (teacher_name, section, program, school_year, semester, is_active)
        VALUES (?, ?, ?, '2025-2026', '1st', true)
        ON CONFLICT (teacher_name, section, program, school_year, semester) DO NOTHING
    ");
    
    // COLLEGE TEACHERS
    echo "<h3>📚 Inserting College Teachers</h3>";
    $collegeTeachers = [
        'BSCS-1M1' => ['MR. VELE','MR. RODRIGUEZ','MR. JIMENEZ','MS. RENDORA','MR. LACERNA','MR. ATIENZA'],
        'BSCS-2N1' => ['MR. RODRIGUEZ','MR. ICABANDE','MR. PATIAM','MS. VELE','MR. JIMENEZ','MR. GORDON'],
        'BSCS-3M1' => ['MR. PATALEN','MS. DIMAPILIS','MR. V. GORDON','MR. JIMENEZ'],
        'BSCS-4N1' => ['MS. DIMAPILIS','MR. ELLO','MR. V. GORDON','MR. PATALEN'],
        'BSOA-1M1' => ['MR. VELE','MR. LACERNA','MR. RODRIGUEZ','MS. IGHARAS','MS. OCTAVO','MS. RENDORA','MR. ATIENZA'],
        'BSOA-2N1' => ['MR. LACERNA','MS. RENDORA','MS. VELE','MR. CALCEÑA','MS. CARMONA','MS. IGHARAS'],
        'BSOA-3M1' => ['MR. MATILA','MR. ELLO','MS. IGHARAS','MR. CALCEÑA','MR. V. GORDON'],
        'BSOA-4N1' => ['MR. CALCEÑA','MS. IGHARAS'],
        'EDUC-1M1' => ['MR. VELE','MR. MATILA','MR. V. GORDON','MS. TESORO','MR. LACERNA','MR. RODRIGUEZ','MS. RENDORA','MR. ATIENZA'],
        'EDUC-2N1' => ['MS. VELE','MR. VALENZUELA','MR. ICABANDE','MR. ELLO','MR. ORNACHO','MS. OCTAVO','MS. RENDORA'],
        'EDUC-3M1' => ['MS. OCTAVO','MR. VALENZUELA','MR. MATILA','MR. CALCEÑA','MS. MAGNO','MS. TESORO','MS. CARMONA'],
        'EDUC-4M1' => ['MS. TESORO','MR. ELLO'],
        'EDUC-4N1' => ['MS. TESORO'],
    ];
    foreach ($collegeTeachers as $section => $teachers) {
        foreach ($teachers as $teacher) {
            $teacher = cleanTeacherName($teacher);
            if (!empty($teacher)) {
                $stmt->execute([$teacher, $section, 'COLLEGE']);
                $inserted++;
            }
        }
        echo "<p>✓ Added " . count($teachers) . " teachers to {$section}</p>";
    }

    // SHS GRADE 11 TEACHERS
    echo "<h3>🎓 Inserting SHS Grade 11 Teachers</h3>";
    $shs11Teachers = [
        'HE1M1' => ['MS. TINGSON','MS. LAGUADOR','MS. GAJIRAN','MS. ANGELES','MS. ROQUIOS','MRS. TESORO','MR. UMALI','MR. R. GORDON','MR. GARCIA'],
        'HE1M2' => ['MS. TINGSON','MRS. YABUT','MR. SANTOS','MR. ORNACHO','MR. ALCEDO','MRS. TESORO','MS. RENDORA','MR. UMALI','MR. R. GORDON','MR. GARCIA'],
        'ICT1M1' => ['MS. ROQUIOS','MRS. YABUT','MR. SANTOS','MS. ANGELES','MR. ALCEDO','MR. R. GORDON','MS. RENDORA','MR. UMALI','MR. JIMENEZ','MR. V. GORDON'],
        'ICT1N1' => ['MS. ROQUIOS','MRS. YABUT','MR. RODRIGUEZ','MS. ANGELES','MS. TINGSON','MR. R. GORDON','MR. UMALI','MR. JIMENEZ','MR. V. GORDON'],
        'HUMSS1M1' => ['MS. ROQUIOS','MRS. YABUT','MR. SANTOS','MS. ANGELES','MS. ROQUIOS','MS. LAGUADOR','MS. RENDORA','MR. ALCEDO'],
        'HUMSS1N1' => ['MS. ROQUIOS','MRS. YABUT','MR. SANTOS','MS. ANGELES','MS. TINGSON','MS. LAGUADOR','MS. RENDORA','MR. ALCEDO'],
        'ABM1M1' => ['MS. TINGSON','MS. RIVERA','MS. SANTOS','MS. ANGELES','MR. ALCEDO','MS. TESORO','MR. UMALI','MS. LAGUADOR','MR. CALCEÑA','MS. GAJIRAN'],
        'ABM1N1' => ['MS. TINGSON','MS. LAGUADOR','MR. SANTOS','MS. ANGELES','MR. ALCEDO','MS. TESORO','MS. RENDORA','MR. CALCEÑA','MS. GAJIRAN'],
    ];
    foreach ($shs11Teachers as $section => $teachers) {
        foreach ($teachers as $teacher) {
            $teacher = cleanTeacherName($teacher);
            if (!empty($teacher)) {
                $stmt->execute([$teacher, $section, 'SHS']);
                $inserted++;
            }
        }
        echo "<p>✓ Added " . count($teachers) . " teachers to {$section}</p>";
    }

    // SHS GRADE 12 TEACHERS
    echo "<h3>🎓 Inserting SHS Grade 12 Teachers</h3>";
    $shs12Teachers = [
        'HE3M1' => ['MS. CARMONA','MR. BATILES','MR. ICABANDE','MR. PATIAM','MR. UMALI','MS. MAGNO'],
        'ICT3M1' => ['MS. LIBRES','MR. LACERNA','MR. ICABANDE','MR. RENDORA','MR. V. GORDON'],
        'HUMSS3M1' => ['MS. CARMONA','MR. LACERNA','MS. LIBRES','MR. PATIAM','MS. RENDORA','MR. GARCIA','MR. BATILES'],
        'ABM3M1' => ['MS. CARMONA','MR. BATILES','MS. RIVERA','MR. PATIAM','MR. UMALI','MR. CALCEÑA'],
    ];
    foreach ($shs12Teachers as $section => $teachers) {
        foreach ($teachers as $teacher) {
            $teacher = cleanTeacherName($teacher);
            if (!empty($teacher)) {
                $stmt->execute([$teacher, $section, 'SHS']);
                $inserted++;
            }
        }
        echo "<p>✓ Added " . count($teachers) . " teachers to {$section}</p>";
    }

    // SHS SPECIAL CLASSES
    echo "<h3>🌟 Inserting SHS Special Classes Teachers</h3>";
    $shsSCTeachers = [
        'HE-11SC' => ['MR. LACERNA','MR. RODRIGUEZ','MR. VALENZUELA','MR. MATILA','MR. UMALI','MS. GENTEROY'],
        'ICT-11SC' => ['MR. LACERNA','MR. RODRIGUEZ','MR. VALENZUELA','MR. MATILA','MR. JIMENEZ'],
        'HUMSS-11SC' => ['MR. ICABANDE','MR. PATIAM','MS. VELE','MR. MATILA'],
        'ABM-11SC' => ['MR. ICABANDE','MR. PATIAM','MS. VELE','MR. VALENZUELA','MR. RODRIGUEZ'],
        'HE-12SC' => ['MR. VELE','MR. ICABANDE','MR. PATIAM','MS. GENTEROY'],
        'ICT-12SC' => ['MR. VELE','MR. ICABANDE','MR. PATIAM','MR. JIMENEZ'],
        'HUMSS-12SC' => ['MR. LACERNA','MR. UMALI','MR. PATIAM','MR. ICABANDE','MR. VELE'],
        'ABM-12SC' => ['MR. LACERNA','MR. UMALI','MR. PATIAM','MS. IGHARAS'],
    ];
    foreach ($shsSCTeachers as $section => $teachers) {
        foreach ($teachers as $teacher) {
            $teacher = cleanTeacherName($teacher);
            if (!empty($teacher)) {
                $stmt->execute([$teacher, $section, 'SHS']);
                $inserted++;
            }
        }
        echo "<p>✓ Added " . count($teachers) . " teachers to {$section}</p>";
    }
    
    return $inserted;
}

try {
    $pdo = getPDO();
    echo "<!DOCTYPE html><html><head><title>Bulk Insert Teachers</title>";
    echo "<style>body{font-family:Arial,sans-serif;margin:20px;line-height:1.6;}h2{color:#800000;}h3{color:#A52A2A;}p{margin:5px 0;}.summary{background:#f0f0f0;padding:15px;border-radius:5px;margin:20px 0;}.success{color:green;}.error{color:red;}</style>";
    echo "</head><body>";
    echo "<h1>🏫 Bulk Teacher Assignment Import</h1>";
    echo "<p>Importing all teacher assignments from your documents...</p>";
    $totalInserted = insertTeacherAssignments($pdo);
    echo "<div class='summary'><h3>📊 Import Summary</h3>";
    echo "<p class='success'><strong>Total teachers assigned: {$totalInserted}</strong></p>";
    echo "<p>All teacher assignments have been successfully imported!</p></div>";
    $stmt = $pdo->query("SELECT program, COUNT(*) as count FROM teacher_assignments GROUP BY program ORDER BY program");
    $stats = $stmt->fetchAll();
    echo "<h3>📈 Assignment Statistics by Program</h3>";
    foreach ($stats as $stat) {
        echo "<p>• {$stat['program']}: {$stat['count']} assignments</p>";
    }
    $stmt = $pdo->query("SELECT COUNT(DISTINCT teacher_name) as unique_teachers FROM teacher_assignments");
    $uniqueTeachers = $stmt->fetchColumn();
    echo "<p><strong>Unique teachers: {$uniqueTeachers}</strong></p>";
    $stmt = $pdo->query("SELECT COUNT(DISTINCT section) as unique_sections FROM teacher_assignments");
    $uniqueSections = $stmt->fetchColumn();
    echo "<p><strong>Sections covered: {$uniqueSections}</strong></p>";
    echo "<br><p><a href='index.php' style='background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>← Back to Login</a></p>";
    echo "<p><a href='admin.php' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;margin-left:10px;'>Go to Admin Panel</a></p>";
    echo "</body></html>";
} catch (Exception $e) {
    echo "<div class='error'><h3>❌ Error</h3>";
    echo "<p>Failed to insert teacher assignments: " . htmlspecialchars($e->getMessage()) . "</p></div>";
}
?>
