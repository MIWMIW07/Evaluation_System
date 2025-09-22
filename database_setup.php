<?php
// database_setup.php - Database initialization and table creation
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security check - Only allow access during setup phase
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
        last_login TIMESTAMP NULL, student_table_id INTEGER REFERENCES students(id)
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
    // NEW & UPDATED TABLES
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

    // Create students table first as users table will reference it
    $create_students_table = "CREATE TABLE IF NOT EXISTS students (
        id SERIAL PRIMARY KEY,
        student_id VARCHAR(30) UNIQUE,
        last_name VARCHAR(50) NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        middle_name VARCHAR(50),
        full_name VARCHAR(150) NOT NULL,
        section_id INTEGER,
        is_active BOOLEAN DEFAULT true,
        enrolled_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
    $pdo->exec($create_students_table);
    $setup_messages[] = "✅ Students table created/verified";

   $create_teachers_table = "CREATE TABLE IF NOT EXISTS teachers (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        department VARCHAR(50) NOT NULL,
        is_active BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(name, department) -- Make the combination of name and department unique
    )";
    $pdo->exec($create_teachers_table);
    $setup_messages[] = "✅ Teachers table created/verified with correct unique rule.";

    
    // 4. CREATE SECTION_TEACHERS TABLE
    $create_section_teachers_table = "CREATE TABLE IF NOT EXISTS section_teachers (
        id SERIAL PRIMARY KEY,
        section_id INTEGER REFERENCES sections(id),
        teacher_id INTEGER REFERENCES teachers(id),
        is_active BOOLEAN DEFAULT true,
        assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(section_id, teacher_id)
    )";
    $pdo->exec($create_section_teachers_table);
    $setup_messages[] = "✅ Section Teachers table created/verified";
    
    // 5. UPDATE EXISTING USERS TABLE
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS student_table_id INTEGER REFERENCES students(id)");
        $setup_messages[] = "✅ Users table updated with student_table_id column";
    } catch (Exception $e) {
        $setup_messages[] = "ℹ️ Users table already has student_table_id column";
    }

     // Insert admin user
    $check_admin = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $check_admin->execute(['admin']);
    if ($check_admin->fetchColumn() == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_admin = $pdo->prepare("INSERT INTO users (username, password, user_type, full_name) VALUES (?, ?, ?, ?)");
        $insert_admin->execute(['admin', $admin_password, 'admin', 'System Administrator']);
        $setup_messages[] = "✅ Admin user created (username: admin, password: admin123)";
    }
    
    // Insert sample admin user
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
        ['BSCS-1M1', 'BS Computer Science 1st Year Morning Section 1', 'COLLEGE', '1st Year'], ['BSCS-2N1', 'BS Computer Science 2nd Year Night Section 1', 'COLLEGE', '2nd Year'], ['BSCS-3M1', 'BS Computer Science 3rd Year Morning Section 1', 'COLLEGE', '3rd Year'], ['BSCS-4N1', 'BS Computer Science 4th Year Night Section 1', 'COLLEGE', '4th Year'], ['BSOA-1M1', 'BS Office Administration 1st Year Morning Section 1', 'COLLEGE', '1st Year'], ['BSOA-2N1', 'BS Office Administration 2nd Year Night Section 1', 'COLLEGE', '2nd Year'], ['BSOA-3M1', 'BS Office Administration 3rd Year Morning Section 1', 'COLLEGE', '3rd Year'], ['BSOA-4N1', 'BS Office Administration 4th Year Night Section 1', 'COLLEGE', '4th Year'], ['EDUC-1M1', 'Bachelor of Elementary Education 1st Year Morning Section 1', 'COLLEGE', '1st Year'], ['EDUC-2N1', 'Bachelor of Elementary Education 2nd Year Night Section 1', 'COLLEGE', '2nd Year'], ['EDUC-3M1', 'Bachelor of Elementary Education 3rd Year Morning Section 1', 'COLLEGE', '3rd Year'], ['EDUC-4M1', 'Bachelor of Elementary Education 4th Year Morning Section 1', 'COLLEGE', '4th Year'], ['EDUC-4N1', 'Bachelor of Elementary Education 4th Year Night Section 1', 'COLLEGE', '4th Year'], ['BSCS-1SC', 'BS Computer Science 1st Year Sunday Class', 'COLLEGE', '1st Year'], ['BSCS-2SC', 'BS Computer Science 2nd Year Sunday Class', 'COLLEGE', '2nd Year'], ['BSOA-1SC', 'BS Office Administration 1st Year Sunday Class', 'COLLEGE', '1st Year'], ['BSOA-2SC', 'BS Office Administration 2nd Year Sunday Class', 'COLLEGE', '2nd Year'], ['EDUC-1SC', 'Bachelor of Technical Vocational Teacher Education 1st Year Sunday Class', 'COLLEGE', '1st Year'], ['EDUC-2SC', 'Bachelor of Technical Vocational Teacher Education 2nd Year Sunday Class', 'COLLEGE', '2nd Year'],
        ['ABM-1M1', 'Accountancy Business Management Grade 11 Morning Section 1', 'SHS', 'Grade 11'], ['ABM-1M2', 'Accountancy Business Management Grade 11 Morning Section 2', 'SHS', 'Grade 11'], ['ABM-1N1', 'Accountancy Business Management Grade 11 Night Section 1', 'SHS', 'Grade 11'], ['HUMSS-1M1', 'Humanities and Social Sciences Grade 11 Morning Section 1', 'SHS', 'Grade 11'], ['HUMSS-1M2', 'Humanities and Social Sciences Grade 11 Morning Section 2', 'SHS', 'Grade 11'], ['HUMSS-1M3', 'Humanities and Social Sciences Grade 11 Morning Section 3', 'SHS', 'Grade 11'], ['HUMSS-1M4', 'Humanities and Social Sciences Grade 11 Morning Section 4', 'SHS', 'Grade 11'], ['HUMSS-1M5', 'Humanities and Social Sciences Grade 11 Morning Section 5', 'SHS', 'Grade 11'], ['HUMSS-1N1', 'Humanities and Social Sciences Grade 11 Night Section 1', 'SHS', 'Grade 11'], ['HUMSS-1N2', 'Humanities and Social Sciences Grade 11 Night Section 2', 'SHS', 'Grade 11'], ['HUMSS-1N3', 'Humanities and Social Sciences Grade 11 Night Section 3', 'SHS', 'Grade 11'], ['HE-1M1', 'Home Economics Grade 11 Morning Section 1', 'SHS', 'Grade 11'], ['HE-1M2', 'Home Economics Grade 11 Morning Section 2', 'SHS', 'Grade 11'], ['HE-1M3', 'Home Economics Grade 11 Morning Section 3', 'SHS', 'Grade 11'], ['HE-1M4', 'Home Economics Grade 11 Morning Section 4', 'SHS', 'Grade 11'], ['HE-1N1', 'Home Economics Grade 11 Night Section 1', 'SHS', 'Grade 11'], ['HE-1N2', 'Home Economics Grade 11 Night Section 2', 'SHS', 'Grade 11'], ['ICT-1M1', 'Information and Communication Technology Grade 11 Morning Section 1', 'SHS', 'Grade 11'], ['ICT-1M2', 'Information and Communication Technology Grade 11 Morning Section 2', 'SHS', 'Grade 11'], ['ICT-1N1', 'Information and Communication Technology Grade 11 Night Section 1', 'SHS', 'Grade 11'], ['ICT-1N2', 'Information and Communication Technology Grade 11 Night Section 2', 'SHS', 'Grade 11'],
        ['HUMSS-3M1', 'Humanities and Social Sciences Grade 12 Morning Section 1', 'SHS', 'Grade 12'], ['HUMSS-3M2', 'Humanities and Social Sciences Grade 12 Morning Section 2', 'SHS', 'Grade 12'], ['HUMSS-3M3', 'Humanities and Social Sciences Grade 12 Morning Section 3', 'SHS', 'Grade 12'], ['HUMSS-3M4', 'Humanities and Social Sciences Grade 12 Morning Section 4', 'SHS', 'Grade 12'], ['HUMSS-3N1', 'Humanities and Social Sciences Grade 12 Night Section 1', 'SHS', 'Grade 12'], ['HUMSS-3N2', 'Humanities and Social Sciences Grade 12 Night Section 2', 'SHS', 'Grade 12'], ['HUMSS-3N3', 'Humanities and Social Sciences Grade 12 Night Section 3', 'SHS', 'Grade 12'], ['HUMSS-3N4', 'Humanities and Social Sciences Grade 12 Night Section 4', 'SHS', 'Grade 12'], ['HE-3M1', 'Home Economics Grade 12 Morning Section 1', 'SHS', 'Grade 12'], ['HE-3M2', 'Home Economics Grade 12 Morning Section 2', 'SHS', 'Grade 12'], ['HE-3M3', 'Home Economics Grade 12 Morning Section 3', 'SHS', 'Grade 12'], ['HE-3M4', 'Home Economics Grade 12 Morning Section 4', 'SHS', 'Grade 12'], ['HE-3N1', 'Home Economics Grade 12 Night Section 1', 'SHS', 'Grade 12'], ['HE-3N2', 'Home Economics Grade 12 Night Section 2', 'SHS', 'Grade 12'], ['HE-3N3', 'Home Economics Grade 12 Night Section 3', 'SHS', 'Grade 12'], ['HE-3N4', 'Home Economics Grade 12 Night Section 4', 'SHS', 'Grade 12'], ['ICT-3M1', 'Information and Communication Technology Grade 12 Morning Section 1', 'SHS', 'Grade 12'], ['ICT-3M2', 'Information and Communication Technology Grade 12 Morning Section 2', 'SHS', 'Grade 12'], ['ICT-3N1', 'Information and Communication Technology Grade 12 Night Section 1', 'SHS', 'Grade 12'], ['ICT-3N2', 'Information and Communication Technology Grade 12 Night Section 2', 'SHS', 'Grade 12'], ['ABM-3M1', 'Accountancy Business Management Grade 12 Morning Section 1', 'SHS', 'Grade 12'], ['ABM-3M2', 'Accountancy Business Management Grade 12 Morning Section 2', 'SHS', 'Grade 12'], ['ABM-3N1', 'Accountancy Business Management Grade 12 Night Section 1', 'SHS', 'Grade 12'],
        ['HE-11SC', 'Home Economics Grade 11 Sunday Class', 'SHS', 'Grade 11'], ['ICT-11SC', 'Information and Communication Technology Grade 11 Sunday Class', 'SHS', 'Grade 11'], ['HUMSS-11SC', 'Humanities and Social Sciences Grade 11 Sunday Class', 'SHS', 'Grade 11'], ['ABM-11SC', 'Accountancy Business Management Grade 11 Sunday Class', 'SHS', 'Grade 11'],
        ['HE-12SC', 'Home Economics Grade 12 Sunday Class', 'SHS', 'Grade 12'], ['ICT-12SC', 'Information and Communication Technology Grade 12 Sunday Class', 'SHS', 'Grade 12'], ['HUMSS-12SC', 'Humanities and Social Sciences Grade 12 Sunday Class', 'SHS', 'Grade 12'], ['ABM-12SC', 'Accountancy Business Management Grade 12 Sunday Class', 'SHS', 'Grade 12']
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
    $setup_messages[] = "✅ {$sections_created} new sections created/verified";

    // ===============================================
    // INSERT ALL STUDENTS DATA (NEWLY ADDED)
    // ===============================================
    
    $all_students_by_section = [
        'BSCS-1M1' => [['ABAÑO','SHAWN ROVIC','PARREÑO'],['ANDRES','ALLYSA','LIBRANDO'],['CARDIENTE','SHIENA','AMANTE'],['CERNA','SHAYRA','OMILIG'],['CORTES','MARRY JOY','LUVIDECES'],['DALUZ','MA. KATE JASMIN','PORTACIO'],['ESTOCADO','LEOVY JOY','CARBONELLO'],['GARCERA','ANGELA',''],['JACINTO','MA. CLARISSA','BAUTISTA'],['LABAGALA','TRISHA MAE','CADILE'],['MUSCA','ROSALINDA',''],['RAFANAN','ROSELYN','LANORIA'],['SAYNO','JANILLE','BASINANG'],['ZARSUELO','JENNICA ROSE','BAMBIBO'],['ABIGUELA','RAYBAN','NAVEA'],['ADONIS','JARED','DICHUPA'],['AMABAO','EZEKIEL JEAN',''],['BULADO','JOHN PAUL','VALEROSO'],['CADILE','BENCH JOSH','MENDOZA'],['CUEVAS','CYRUS','MARTINEZ'],['DIOQUINO','FRANCIS','RECTO'],['FALCON','KARL ANTHONY','VERSOZA'],['KEMPIS','BRIAN JOSH','ACIDO'],['LACUARIN','CYRUS','MARMETO'],['LLEGANIA','LANCE ALLANDY','MONTILLA'],['LOREDO','CLARENCE','CASTUCIANO'],['LORICA','JOSEPH','PAMIN'],['MEDROSO','VICENTE','VENCIO'],['NOVICIO','ACESON','LAWA'],['QUIRANTE','IZEUS JAKE','E'],['ROA','MICHAEL','RAMALLOSA'],['SAMILLANO','EDRIAN','PESTAÑO'],['VALLEJO','JOHN KENNETH ADRIAN','EMBESTRO'],['VERDOQUILLO','LEMUEL','HITALIA'],['VILLANUEVA','ROWIE','PADEL']],
        'BSOA-1M1' => [['ABRAGAN','CHERRY','SARMIENTO'],['ADRAO','SHIELA MAY','RIBAYA'],['ALMANZA','RASHEL','ALBITO'],['ARRIOLA','CHRISTEL GENESIS','DELOS REYES'],['CAINTIC','JAMAICA MAE','VALERIO'],['CANELA','RAZEL MAICCA','G'],['CELIS','IZZY','TADEO'],['DACLES','KHATE','AGRAVANTE'],['DACUYA','IVY','BACULINAO'],['DARDAGAN','JOHANNA','GUINAL'],['DEGAYO','PRINCESS','BACALSO'],['DELA CRUZ','JANEYA CASSANDRA','SOBREMONTE'],['DOMECILLO','JULY-ANN','RAMIREZ'],['DOMONDON','ABIGAIL','TUTAAN'],['EVANGELISTA','KEYCEE','MIRANDILLA'],['FORTALEZA','ABEGAIL','COLICO'],['FRIAS','JENNY VHABE',''],['FUENTES','LEONORA ANNA','HISONA'],['GABORNE','PRECIOUS JOSEPHINE','ABANDO'],['GARCIA','KRISTINE JOY','ECHON'],['JAGOLINA','TANYA','FAJARDO'],['LARIOQUE','MICHELLE JOY','SOLAYAO'],['LUCHANA','GABBY ANN','GARCERA'],['MACALINO','MICHELLE','IRINCO'],['MASUNGSONG','ROSE ANN','CAMINO'],['MOJICA','MICHAELA VENICE GAIL','BUSTAMANTE'],['PULLEDA','WINA',''],['RAFOLS','DIANNA ROME','POLLANTE'],['REMOTO','MARY DIVINE GRACE','BALGOS'],['RENDON','ELAIJAH MAE','LAURINA'],['SAMSON','ANGELINE','HERNANDEZ'],['SUMORIA','ALMIRA','CELIS'],['TAMBUNGAN','WENLIAN-MISIA','DAEF'],['TESADO','ARLENE',''],['VANZUELA','RHIANNA MAE','OSARES'],['ALBEZA','ROMEO','ESCUADRO'],['BADO','GILBERT','ALCANTARA'],['DELA PAZ','JUDEL','DEL SOCORRO'],['DURANTE','MARK LORENZ','HERNANDEZ'],['ESGUERRA','JENMORE','BACALSO'],['FAMILIAR','MARC JERIC','RODRIGUEZ'],['HISONA','CHRISTIAN',''],['MERO','NOLIE',''],['PACIS','JERALD','SIERRA'],['PAVIA','JASTIN','NIEPAS'],['PEREZ','REGGIE','ALIMANIA'],['RODRIGUEZ','ROILAND','GRUTAS'],['VICTORIA','MARJOHN','N/I']],
        'EDUC-1M1' => [['AMARANTO','JOANA MARIE','BIQUILLO'],['ANTIPOLO','CANDIES DALE',''],['ARSOLON','REBECCA AIRA','CIRUELA'],['ASANZA','CLARIZ','LAUROSA'],['ASUCENAS','APPLE MAE','JUNSY'],['BAUNTO','SAIMAH','PANGADAPEN'],['BOMBASE','ANGEL','VILLARENO'],['DAYANDAYAN','RODELYN',''],['DELOS SANTOS','NORLY','JAGOLINA'],['FERNANDES','FIRILYN','TABOTABO'],['GABAYNO','BERNADETTED','GADO'],['GABRIEL','AYESHA NICOLE','ESLAO'],['GARILAO','CRISJAELLE','SALVADOR'],['GRAFIL','JOSIE','DINGAL'],['LABASBAS','MAYCIE','PASCUAL'],['LICMUAN','KAYE EUNICE','NARAG'],['LINGHAP','REGIELYN','ADAMOS'],['LOBERISCO','MYRA',''],['MARTICIO','ANNA MARIE','CAMACHO'],['MATCHINA','SHAINE','SUELO'],['OMEDES','MIRAFE','SOMALO'],['PAGAL','PELJOY','SERON'],['RABE','CHRISTINE GRACE','TAGRIPIS'],['RAMOS','CLARISS','LAXA'],['REFAMA','JASMINE','MANALILI'],['RODRIGUEZ','WINNIECHA','DAVID'],['TANDAS','ANGELICA NICOLE','CATHEDRAL'],['TESADO','MILENA','BOHOLANO'],['ABBANG','JOHN ERIC PAUL','LINGAO LINGAO'],['ALBAO','MARK LESTER','ALEJO'],['ARGOTA','RYAN','BALINGASA'],['BALUCAS','JHANMER','GENEROSO'],['CUYOS','MHIKE ANDREI','DUMA'],['DABLO','JOMEL','ALEGADO'],['GANDEZA','STEVEN','MORENO'],['GUZMAN','MARK LUIS','RAMOS'],['JACER','ANTONIO','CAYOBIT'],['LIABRES','GEMMAR','O'],['PLOPINIO','GIAN KENNETH','CLACIO'],['ROTONI','CHRISTIAN','USERO'],['SALAC','ANGELO','CLACIO'],['SAMONTE','CRIZ GHABRIEL','DAPIOSEM'],['TORREON','DENVER','TAGCIP'],['VELASCO','KURT JON LOUIE','OFENDA']],
        'BSCS-2N1' => [['BALBIDO','FATIMA','MARAS'],['DIAZ','LYNETH','ALMARIO'],['FAJARDO','MARY GRACE','GARCIA'],['GENODEPA','ROSELLE','CORECES'],['MERCADO','MEAGAN','ESCALANTE'],['PANTI','ANGEL',''],['TULIN','THARQ JELO','PAGARAO'],['ALCOBER','EMMANUEL','BARBOSA'],['ANGCANAN','EZEKIEL','LIM'],['BAGHARI','CHRIS THIEGO','T'],['BUENAVENTURA','HARLEY','BILUAL'],['CABALLERO','JHAVIE JYBE','TACADAO'],['CORNEJO','MARLON','DUTIG'],['LAYNES','CHRISTIAN EDMAR','LUCIDO'],['MAGPAYO','ARWIN','LAURORA'],['PAYUMO','KITH NATHAN LEI','PABLO'],['QUITILEN','JOEY','DIGOS'],['ROSAL','CHRISTIAN','VILLACARLOS'],['ROSAL','CHRIZYRUZE','VILLACARLOS'],['SAÑO','NATHAN CARL','ROYO'],['TUYOR','ALDRIN','SERTIMO'],['VALENCIA','YURI','RUIZ'],['YABUT','MARI ANTHONY LORENZ','QUIRANTE']],
        'BSOA-2N1' => [['ADRIAS','ELIZA','MABBORANG'],['AMULAR','GERNITTE JARED','CACAO'],['AQUINO','ALEX DHAWN','MABINI'],['ARCIAGA','PATRICIA','MALANA'],['BASA','PRINCESS MONICA','CATAPAN'],['BASIHAN','SHEANA','GILBER'],['BELAZA','FLORENDA','CASTIDADES'],['BOÑON','VALERIE ANN',''],['CLAVERIA','ARLISH JUNE','-'],['COLARINA','MA. KARLA FE','BALATON'],['DAEF','AMIHAN MAY',''],['DE LEON','PAULINE KHIM','SANCHEZ'],['DUMAGAT','SHAINA','JERUSALEM'],['ESTOLANO','REGINE','LEGASPINA'],['GABIN','JENNILYN','CANANAO'],['GURO','AMOR CHARITY','DELA CHINA'],['HERNANDEZ','MICAELLA','ACEDERA'],['HOMBRIA','MA. FE','TABON'],['JOSUE','ANGELA','DATUL'],['LABAGALA','JOANA MAE','CADILE'],['LEUTERIO','ROVELYN','MORENO'],['LORIA','AMY GRACE','CATILO'],['MADRIAGA','MHARIS EMMNUELLE','MACHATE'],['MANAOG','AGNES DAYAH','ARINES'],['MARIANO','KIMBERLY','CABAHUG'],['NAVA','KATHERINE JOY M','MANILINGAN'],['PELOJERO','PRENCESS','CANIMO'],['PRECONCILLO','MARICAR','TAGUIAM'],['RICO','CECILIA','S'],['RIVERO','CHARISH','N/I'],['SALCEDO','GRACEL','VALENZUELA'],['SULA','MARY ANN','MARANAN'],['TAYCO','JOLINA','MARTINEZ'],['ZURITA','JOANA MAY','BAUSAMO'],['AGOS','JANIEL AXEL','FORTUNO'],['ALVAREZ','R-IAN SZHERWIN','VILLA SERAN'],['BONSUCAN','JILBER KIM','SIALONGO'],['CALIGAGAN','DAVE','CALOSA'],['CORNEJO','ALFRED','SALVACION'],['DE GALA','JERICHO','-'],['ENDRIGA','JOHN MARK','VILLA'],['GAMBOA','MARC EPHRAEM','FUENTES'],['GOMEZ','RICHARD','ABIAD'],['IBAY','WINSTON','VILLAR'],['REMOTO','JUSTIN','BALGOS'],['SALE','KENNEDY','SONA'],['SITOY','VINCENT',''],['TABARES','IAN','LIMON']],
        'EDUC-2N1' => [['AGUSTIN','ATASHA','MILCA'],['ANTAZO','JOHN REY','ABAD'],['BALEROS','KARLA SHANEA','FERNANDEZ'],['BARLAS','AIRAMIE',''],['BRUTAS','JAMILLE','SALAMANQUE'],['CHUA','BENJO','AGBUYA'],['COYNO','ROY','JUMAWAN'],['Encarquez','Mary Jane','Bautista'],['FORTALEZA','JOYCE ANN','COLICO'],['FRANCISCO','SAMANTHA KOLHYN','M'],['GONZALO','GIZELLE','MARJES'],['HERNANDEZ','SAMANTHA','DUCANTE'],['JOVES','PRINCESS JOIZA','DESPUES'],['KING','ANGELINE','BENGUET'],['KIUNESALA','CHICQUI','BAUTISTA'],['LANZUELA','ROMNICK','GAHIS'],['LEONIDA','CHESKA','CEZAR'],['LOPERA','ARJEAN','DELIMA'],['LOYOLA','SHIELA MAE','BUENAFE'],['MANGINAO','MARK ANTHONY','ANDONG'],['MARFIL','GERALDINE','ABAD'],['MENDOZA','CLARK JAYSON',''],['NAVARES','ANDREA AMOR','TRINIDAD'],['PANER','RHEA JANE','PENETRADO'],['PISTON','SHEENA MAE','FRANCISCO'],['POLENDEY','JUSTEEN','SURBONA'],['QUIZON','JAN MARK','P'],['RAMOS','FELICITY FAITH','DULOT'],['REQUIPO','FATIMA','ABIDAÑia'],['RODIL','JOHN GABRIEL','PESTAÑO'],['RULLODA','GILLEINNE JORJE','CABANG'],['SALAZAR','HANIE FRIELYN','COSME'],['SUBALISID','ALTHEA','N/I'],['VERAÑA','JUVELLE','LUCERO'],['VICENTE','RUSTY JAY','DONGON']],
        'BSCS-3M1' => [['AMADO','MYRLINE','CASUPANG'],['AWAT','ARIANNE LEIH','AZUPARDO'],['DALUZ','LORRAINE','PORTACIO'],['MARTINEZ','ANGELICA ANNE','CAJEPE'],['TRINIDAD','JESLYN','SURUIZ'],['ADORNA','KENNETH','WAGA'],['CABRITIT','JAYNIEL','SANTIAGO'],['CELMAR','PETER PAUL',''],['CORDIAL','LOUIS ALFRED','GUTIERREZ'],['DACULA','CHRISTIAN','PASCO'],['DE RAMOS','JOHN VINCENT','PAILONA'],['DELA CRUZ','PRINCE WILLIAM VINCENT','ESCARDA'],['ELIJORDE','DANILO','LAVILLA'],['ISRAEL','GABRIEL','VARGAS'],['LEVARDO','RIQUIE LARREY','MANGILIT'],['MANGILIT','MARK LANDER','GARCIA'],['MICOSA','MERVIN','D'],['MONTESCLAROS','MICO','C'],['NAVARRO','CHRISTIAN SHUCKS','CABANLIT'],['OMAYAN','KING NATHANIEL','A'],['RAÑESES','MHAYTHAN JAMES','APOSTOL'],['RESTRIVERA','ZAIMON JAMES','SEGUILLA'],['TOQUE','CHRISTOPHER GLEN','RESURRECCION']],
        'BSOA-3M1' => [['ACUÑA','STEFFHANIE','MAGPAYO'],['AURELLANO','NICOLE ANNE',''],['BALBUENA','MARVIE','PADUA'],['BAUTISTA','MA.ELAINE','VALES'],['BUENAVENTURA','MARIZ','BIMAL'],['CABALTERA','ROY ANN','BANZON'],['CAHILIG','JENYLYN','LOBEDERIO'],['CALAPARAN','MAEANN','BRIONGOS'],['DELOS SANTOS','HONEYLYN','TORFILO'],['DUMAGAN','FRITCHELE','OSAREZ'],['EJERCITO','RACHEL','BALANGUIT'],['FRANCISCO','KATE NICOLE','MASUJER'],['GINOO','ANGEL','GASPAN'],['LAS MARIAS','DEANJIE','DIANA'],['MARTIN','EUNICE','MOSQUEDA'],['MYDIN','MYBEB','RAMOS'],['OSARES','ANGELICA','MATURAN'],['RAMOS','NIELA BELLE','DELADIA'],['SABENORIO','BERYLL JOY','DELMONTE'],['TAPION','CARRIL ANGEL','SILANG'],['TEPAIT','ROMA','JAVIER'],['TORRES','JOANA FRANCIA','N/I'],['ZERRUDO','DENNISE ANNE','ZAMORA'],['APLACADOR','JOHN MEG','CERVANTES'],['MONTIERDE','JHON TROY','DEL MUNDO']],
        'EDUC-3M1' => [['ABAÑO','SHANE MARIE','PARREÑO'],['AMARAÑO','NICOLE','ROQUE'],['BANATE','VENUS LYZA','SASE'],['BARDELAS','APRIL NEL','MIRANDA'],['CUSAY','ASHLEY','DESISTO'],['DE PAZ','JOANNE','QUINIQUITO'],['GARCIA','RAZEL','APIPI'],['GARCIA','ROSALIE','APIPI'],['OLAIVAR','JOY JESUSA','ROSCO'],['RODERNO','JULIA JONIAH',''],['CASICA','ROLANDO','OLEMBERIO'],['PAGHARION','MARK JOEL','-']],
        'BSCS-4N1' => [['ALLADA','REALYN','CALWIT'],['AMBAY','LOVELY JANE','VALENTIN'],['BALDOVINO','KAYE','CARRANZA'],['LEVISTE','VEINA IRENE','DE LUNA'],['SUMATRA','MARINELLA','AMOROSO'],['AÑASCO','JULIUS','ALONZO'],['BALANA','RALVIN','A'],['CARRASCAL','CHARLSON','FERNANDEZ'],['CERBITO','MIGUEL','LOMOCSO'],['CHUA','JOHN REINER','ANAS'],['FERMAN','ADRIAN','NIEVA'],['HAYAO','KURT','SARENO'],['JAVIER','KENNETH','ARQUITA'],['LAÑOHAN','NIÑO','ROMERO'],['LOZANO','ANGELO','OCBA'],['MISLANG','ROYLAN','BUMANLAG'],['MORENO','RENZ RYAN','YTURALDE'],['SANDIG','MARK JOVEL','SERVANDE']],
        'BSOA-4N1' => [['BELETA','NICOLE','COBILLA'],['BERMILLO','SUNSHINE','LOVENDINO'],['CARIÑO','JOYCE ANNE','RIMANDO'],['ESTOLLOSO','CRIZEAL','BAGALLON'],['KARUNUNGAN','JEWEL','RAMOS'],['LAPASTORA','JEMIE JOY',''],['MANIEGO','SAMANTHA','FLORES'],['NEGRITE','SHAIRA MAE','LACHICA'],['PEREZ','CYRIL','VIOLA'],['PEROCILLO','MA.MAAN GRACE',''],['SILVERIO','STEPHANIE','M'],['TARROBAL','JANNAH MARIE','MARCELO'],['VELASQUEZ','REA MAE','ALIPAN'],['YOSORES','PEACH AVA','OLFATO'],['CRUZ','KYLE DANIELLE CESAR','GABALES'],['GARCIA','KEITH BRYAN','INFANTE'],['LAGO','EMER','OLARTE'],['MAGNAYE','DON','-'],['MALINAO','JOHN FORD','BLASABAS'],['ORDOÑA','AYAN FRANCE','ROSAROSO'],['POLO','AHLF CEDRIC','LANCETA'],['QUIANE','RYVIN','MACALLAN']],
        'EDUC-4M1' => [['GALIT','PATRICK JOHN','SUNDO'],['MIRANDA','NELSON','ALBINDO'],['OLAVIAGA','LANCE PHILIP','MONTILLA'],['PERMIJO','MARK ANTHONY','LEANA'],['ALONG','ALICIA','TABLASON'],['VALGUNA','JENNY','MAGARZO'],['ANG','RHEMYLYN','PERITO'],['DEL CASTILLO','JASMIN FAITH','ENRIQUEZ'],['DEL CASTILLO','JEMIMAH','D'],['GELBOLINGO','REGINA YSOBEL','JURIDICO'],['TAYOTO','MARJORIE','T'],['MIRANDA','CRESCHILLE','ROSENDAL'],['MONTEREY','RONALYNE','L'],['NAVEA','JOANA MAE','TEJAS'],['OLAVIAGA','REI CAMILLE','BERMAS'],['PAJE','ROSELLE','JEBULAN'],['REPOLLO','BLESSED NICOLE','GUALBERTO'],['SOLO','JERICHA','GARCIA']],
        'EDUC-4N1' => [['ALVAREZ','CRESHELLE','MARQUESES'],['IDE','ERIKA','S']],
        'ABM-1M1' => [['ADVINCULA','LEBRON JAMES','B.'],['ARTIOLA','ALBERT','N.'],['BRAGAT','JOHN MICO','L.'],['CLAVO','JOSHUA','C.'],['HUIT','ADRIAN DAVE','G.'],['JORGE','ROGERICK','L.'],['ORGAS','JOHN','J.'],['OTACAN','DARYL','C.'],['ALANO','JHILIAN','G.'],['ARLANTE','ARLIE MAY','R.'],['AUDENCIAL','MARY ELAINE','A.'],['BERNARDINO','RAZHEN','M.'],['CERTIZA','ALMERA JOY','B.'],['CLARIDAD','JESSA','E.'],['CRUZ','CHRISTINE','C.'],['DABODA','ROXANNE','B.'],['DACULA','KAREN CLAIRE','P.'],['ESTRADA','MARIVIC','M.'],['INABANG','NORJANNAH','D.'],['ONGCAL','CLARISSE ANNE','B.'],['ORILLONEDA','JURISH MAE','M.'],['PABLO','LOVELY','P.'],['PAPNA','HELEN','Q.'],['PERA','CLYDEL ROSE','I.'],['PIMENTEL','MARIANNE RAIN','A.'],['RAYSES','JACKY LOU','S.'],['SALTING','JAMAICA','M.'],['TABURNAL','SHIZKA','R.'],['TARROBAL','LYKA REEZE','M.'],['TEOXON','ABIGAIL','S.'],['TOLENTINO','LYDEL','B.'],['Asegurado','E-jay','M.'],['Caimoy','MJ','M.'],['Espino','Daniel Lawrence','-.'],['Lacdao','Christian Paul','D.'],['Ceballos','Mayca Grace','C.'],['Desquitado','Ziethly Kate','B.'],['Donato','Bhaby angel','A.'],['Filosopo','Lucille','P.'],['Geslani','Ronagrace','G.'],['Guillepa','Arlene','A.'],['Laguardia','Kimberly','D.'],['Manalang','Mica Jane','L.'],['Nablo','Rikki Mae','A.'],['Nolasco','Mary Ann','R.'],['Olitoquit','Ella Mae','V.'],['Peregrino','Aivy','S.'],['RAMOS','MARRY YVONE','C.'],['Sebial','Angel Royen','M.'],['Tulipas','Abegail Lian','R.'],['Villanueva','Lindsay Nicole','Y.'],['Pucio','Bernadette','V.'],['Solis','Precious Ann','D.'],['Togonon','Shyra','D.'],['Ababa','GemarK','C.'],['Balitustos','John loyd','A.']],
        'ABM-1M2' => [['ARCIAGA','KING LAWRENCE','B.'],['CAMBA','RESTY','A.'],['CRUZ','KENSHIN','G.'],['GATCHALIAN','KENGIE','L.'],['ORCALES','YUAN DOMINGO','P.'],['RIÑON','JAN VINCE','A.'],['AGNES','NADYN','R.'],['AMULAR','MELJANE','A.'],['BIÑAS','PRINCESS ZABNEL','A.'],['CABANTING','MILLICENT AMAYLA','S.'],['CAMIA','KRISTINE GIA','E.'],['CEDRON','PRINCESS ZAIRA','A.'],['DE LEON','LEAN NICOLE','L.'],['DESALES','KEZIAH LYN','P.'],['DIMAANO','HANNA JOYCE','N.'],['DIONEDA','RHEA MAE','P.'],['ETIC','MARY VINE','B.'],['FADULLO','JELIAN ROSE','V.'],['GARCIA','ALEXIE LEI','.'],['LUY','SHAIRA','R.'],['MISALUCHA','KRISTHEL FRANCE','C.'],['PEÑARANDA','NORLYNLUZ','M.'],['RAMOS','DENICE MARIE','G.'],['RAZONADO','BABY LOVE','C.'],['ROJAS','YHANNA','M.'],['SARMIENTO','IRISH JOY','T.'],['SICAT','RYZAMAE','V.'],['TAN','AIREN CASSANDRA','M.'],['TORRES','SKYLEHR SAVANNAH','U.'],['TUBIS','RHAINNIEL','L.'],['Agustino','Deseree','B.'],['Barbosa','Nathaly','L.'],['LOBREN','ANDREA','M.'],['MAGPANTAY','NICHOLE','H.'],['Matula','Lhara Ayesha','C.'],['Navarro','Justine Mae','D.'],['Surdilla','Elline Christine','B.'],['Manimtim','James Edward','B.'],['ALBESA','CYRIL ANN','S.'],['BINGAYAN','MARIAN','A.'],['Delos Reyes','Jonalyn','E.'],['DUCALING','ZOILA','E.'],['Ford','Sheena Mae','M.'],['Tañega','Jessel Mae','S.'],['Aguilar','Symon','S.'],['Cadao','John Jeremy','J.']],
        'ABM-1N1' => [['BACHICHA','NOVIEM VER','R.'],['BIOJON','JHON HAROLD','C.'],['DELOS REYES','JOEL','R.'],['JAEN','JASON','T.'],['LIZADA','BENCH JOSHUA','S.'],['MADRIDANO','JOHN DAVE','M.'],['MARTINEZ','ALEXIS','G.'],['MATIENZO','DAVE','.'],['PUNONGBAYAN','ARJEL','M.'],['RODRIGUEZ','XHALEEWELL','C.'],['SANQUILLOS','JAO LAWRENCE','E.'],['ANDOR','CATHERINE','L.'],['BENAVIDEZ','HANNAH','P.'],['BERANGBERANG','JHUSMINKIETH','P.'],['BORDONA','JINKY','V.'],['BRIONES','ALLYS JUANE','A.'],['DEL PILAR','CHRISTINE SHANE','F.'],['DEMETRIO','NEIRIZ JANNAIZA','N.'],['DOMING','IRISH MAE','M.'],['FARAON','ANGELICA','L.'],['FLORES','SAMANTA','E.'],['GATCHALIAN','DANICA MAE','M.'],['GUEVARRA','ALYSSA','D.'],['JARIOL','RICHELYN','M.'],['PALMES','RHEN MAE','N.'],['ROBLES','MERCEL','A.'],['TAÑON','MERLIE JOY','A.'],['TARRIELA','CHRISTINE','G.'],['VENCIO','VALERIE ANNE','L.'],['YONSON','MARY CLAIRE','A.'],['BORDIOS','CARLA MAE','B.'],['MANALO','JEAN NICOLE','B.'],['ABORQUE','RHYZEL MAE','C.'],['CAÑETE','MARIANNEL','H.'],['Corona','Ahrian Joyce','O.'],['Dematera','Kristelle','P.'],['Glifonea','Jade Ann','B.'],['GUANSING','ANDREA NICHOLE','S.'],['HERRERA','JHANNA','R.'],['Olayta','Lovely Mae','D.'],['PEDERE','ASHANTA NICOLE','P.'],['QUINIANO','GWYNETH SHANE','C.'],['ROMANO','JISELLE','B.'],['Valencia','Hanna Lheighven','-.']],
        'ABM-11SC' => [['DOYOLA','CHRISTOPER','V.'],['MARILAO','SHIELLO','G.'],['MENDIOLA','JUDY ANN','P.'],['ADUCA','MYRA','A.'],['Bautista','Mary Denz','-.'],['CELLS','MIRUMEL','L.'],['MANABAT','RACHEL ANN','H.']],
        'HUMSS-1M1' => [['BAYBAY','MIKE JARED','M.'],['CANLAS','JOHN AXEL','U.'],['DOLLOPAC','JHASTIN DAVID','D.'],['GARCIA','JEROME','S.'],['MARIN','ANGELO JAMES','S.'],['OLIQUINO','ERL COBI','A.'],['RAYNANCIA','JAY LAURENCE','N.'],['ALEJO','LHIANNE','C.'],['ALOSA','RECCA','M.'],['ASTORGA','NICOLE','A.'],['BALQUIN','ANGEL','A.'],['CABATUANDO','ALEIRA CHLOE','B.'],['DELA CRUZ','ASHLEY YVONNE','C.'],['HIPOLITO','YUNELLA','R.'],['MANUNGAS','VENIZE KRISHA','P.'],['MORATALLA','MAJAH ZHAINEDYLLE','E.'],['PAGADOR','JANEUELLE','B.'],['PEÑAFLOR','ISABEL','M.'],['REVIRO','RANA LEA','T.'],['ROBANTES','CHANEL ALLURE','C.'],['SOLLEGUE','KELLY CAZANDRA','P.'],['SUNAJO','ANGELYN','P.'],['YUBOC','SHANEEN KHRYSS','L.']],
        'HUMSS-1M2' => [['ABELLADA','JOHN CYRILL','R.'],['DE VERA','MARIONE JAMES','D.'],['LUGAS','VINCE ANGELO','B.'],['MESQUERIOLA','ZAIRO GENE','M.'],['PAZ','CHRISTIAN DAVE','D.'],['RAYMUNDO','MARS ALEN','B.'],['TAMBASACAN','LORENZO JR.','B.'],['VICENCIO','DOMINIC CHAD','F.'],['VILLAMASO','MARVIN','B.'],['ARNILLO','MA. LALAINE','R.'],['BAUTISTA','HANNAH SHANE','P.'],['BUMBOHAY','SZYRN MHY','E.'],['CARISMA','CHESKA MAE','L.'],['CUBOL','CRYSIE JHOY','O.'],['DELA CRUZ','RHEA JANE','B.'],['DELA CRUZ','RHEANNA NICHOLE','S.'],['MINGASCA','WELLA JANE','D.'],['ORTIGA','MARIFEL','G.'],['PALMA','JIMAYCA','B.'],['RESURRECCION','MICHELLE ANNE','L.'],['ROLDAN','ANGIELYN','O.'],['URING','RHIAN MAE','S.'],['VIBARES','JELLY','P.']],
        'HUMSS-1M3' => [['ABENDAÑO','SHAWN LELOUCH','O.'],['ARENDAIN','CASSEI KARL','M.'],['AUDITOR','MAZZARU','D.'],['BARRIENTOS','BARON VORGH','N.'],['DONGHIT','JEROME','V.'],['HUBILLA','JUAN CARLO','N.'],['INDANGAN','ARVY KYIEL','F.'],['MAAÑO JR.','OSCAR','M.'],['MUÑOZ','MARK ANDREI','D.'],['NAVARRO','CHRISTIAN','D.'],['RAMIREZ','VINCE MICHAEL','A.'],['SEÑORA','D-JAY','B.'],['SORIANO','KENNETH','P.'],['ASUNCION','NASHEENA','D.'],['CAMAMA','QUEEN ALLYANA','C.'],['CASTILLO','CHARMAINE','T.'],['DADOR','CHITTARA','M.'],['EMPANIA','TRISHA YVONNE','B.'],['GAMARCHA','EUREKA LOUIZE','C.'],['HENTICA','PAULLHEA','B.'],['LICMUAN','FAITH ANNE','G.'],['MAHUSAY','DENISE ALEXA','S.'],['MARCO','JULIA MARIE','O.'],['MARTINEZ','JANNAH ANNIELLE','E.'],['NONES','JAYDEN','G.'],['PALAÑA','ANNA NICOLE','.'],['PUEBLO','AYESHA','F.'],['RAPIZA','FERELYN','G.'],['SANTOS','HELEN ZHANE','D.'],['Mendoza','Shane','V.'],['Pacia','Lyka','C.'],['RIVERA','SHAINE LORAINE','L.'],['PAÑARES','JUSTINE','B.'],['SATURNO','JOHN DENVER','C.'],['Agnes','Angel Ann','B.'],['CHAVEZ','NIÑA KIM','M.'],['DEMILLO','EDRYL JOYCE','R.'],['Lipeten','Beatriz Shane','P.']],
        'HUMSS-1M4' => [['BASA','ALDWIND','A.'],['BORJA','CARL JUSTIN','C.'],['CELDA','ANTHONY','M.'],['LOZADA','NATHANIELLE','B.'],['PAPA','MIKE AIRON','M.'],['RADA','GIAN CARL','R.'],['RIVERA','CHARLIE RONNEL','E.'],['ADONIS','CHARISSE','V.'],['BALDIVIA','MA. CRIZEL','M.'],['BUCIO','DIANE ROSE','A.'],['CACAO','LYRENE JOY','M.'],['CORONEL','JHEWEL ANN','C.'],['DE GUZMAN','HAZEL ANN','J.'],['DE SAGUN','JAMAICA','S.'],['FERNANDO','ANTOINETTE','D.'],['GLEPONIO','MARHEN JOYCE','.'],['JEPONGOL','RUBY ANN','B.'],['MICOSA','TRIZZA ANN','A.'],['ORONGAN','JESSA MAE','G.'],['PAHAYAHAY','PRINCESS ANN','C.'],['SABBEN','CRYSTAL JADE','R.'],['SANDAGAN','JOYREL ANN','D.'],['SUMADSAD','WILMA','C.'],['TAGO','JEMALYN','E.'],['TURALDE','ANN FRANCIS','P.'],['VASQUEZ','CARYLE','D.']],
        'HUMSS-1M5' => [['ALVIZ','KIM DANIEL','L.'],['BAUTISTA','CHRISTLEE','A.'],['EUSEBIO','ANTHONY','E.'],['LOSARIA','KIAN','T.'],['MAALIAO','MIKE JAMES','V.'],['MALLO','BRYAN JHERIEL','I.'],['MARCELINO','CLARK','E.'],['PALACIOS','FRENZ JOHN PAUL','.'],['QUILLANO','LHEYNARD','N.'],['REMPILLO','REYMON','P.'],['SOLOMON','JESTER CARL','P.'],['ALAGDON','KAINA MAE','D.'],['GADIANE','JERIEVIN','T.'],['GOC-ONG','KRIZA JADE','R.'],['JANEO','HAILEY MARGARETH','L.'],['LU-AB','PEARL ROSE','A.'],['MANOJO','CHARES','B.'],['MANSING','LOUISE JYNNE','I.'],['PARTOSA','JANICE','M.'],['PARTOSA','VEA','M.'],['PINEDA','XYLZ ATHENA','P.'],['QUIBOL','RYNTCH CHEY','R.'],['RAYOSO','ARA','L.'],['RONGAVILLA','AKISHA JHAINE','L.'],['TAMBIAO','CODETTE','V.'],['TAÑO','PRINCESS EUNICE','A.'],['TRINIDAD','CHERRY MAE','H.'],['TUBALINAL','TRIXIE MARIE','.'],['VILLARMA','DANIEL NAOIMI','B.'],['BATOMALAKI','SAM LAWRENCE','P.'],['BAUTISTA','VHIELLE ZYRENE','P.']],
        'HUMSS-1N1' => [['ADORA','RHAE JOSEL','M.'],['ARAOJO','DARELL','B.'],['ATIENZA JR.','JESSIE','A.'],['BUENACOSA','REY JHON','E.'],['CORDERO','MARK RENDEL','T.'],['MACABENTA','LKJAM','P.'],['MALABANAN','NICK','M.'],['ROYO','RETZON RETZ','R.'],['SAGARAL','VANN ALLEN','.'],['SIMBORIO II','DIONISIO II','P.'],['ACERO','PRINCESS HENLY','B.'],['ALERE','ANDREA NICOLE','L.'],['ARMAS','HANA JENEEVE','E.'],['ASPREC','PRINCESS ANN','N.'],['CACAO','PRINCESS MAE','V.'],['CALAGO','IZEL','P.'],['COLARINA','PRINCESS MARIE','B.'],['CONCEPCION','ROSS LYN','T.'],['CORNELIO','NOELYN','B.'],['DOLOGUIN','JANE','A.'],['ELENTO','JULIANA PAULA','B.'],['GORION','EUNILYN','S.'],['MARJALINO','JHONA MHICA','N.'],['MONDOY','TRECIA MAE','.'],['PABLO','KRYSHIA MAE','A.'],['RAMOS','ALTHEA','D.'],['RIVERA','THEA VENICE','M.'],['UMALI','ANDREA','O.']],
        'HUMSS-1N2' => [['ASUNCION','MC','I.'],['LUCIANO','TRISTAN','P.'],['LUPAZ','IVAN','P.'],['NOCOS','CARL JAMES','D.'],['PABALAN','PRINCE NIÑO','T.'],['SOLO','GLANZ YUAN','J.'],['ALIPARO','LINDSEY YOHAN','.'],['AQUINO','PRINCESS EMARY','M.'],['ARAGAY','ROCHELLE ANNE','C.'],['BALIDOY','ANGELICA','P.'],['BINARAO','ANDREA ANNE','A.'],['BORJA','LEAH MAE','A.'],['CORDERO','MARCELINA','D.'],['DE LUNA','MA. IZYL EUNICE','D.'],['DE LUNA','JIRAH','A.'],['DELA CRUZ','PRINCESS','P.'],['EGOS','SHANAIA','L.'],['FLORES','EDEN ROSE','V.'],['FRANCIS','ALEXIS JEN','J.'],['GALLEGO','JANELLA VIEL','G.'],['NARCISO','DONITA','.'],['ORDOÑEZ','DAPHNE LAURICE','H.'],['PADIT','BERNADETH','.'],['PIA','HANNA MICA','E.'],['TECSON','LEESHANE','V.']],
        'HUMSS-1N3' => [['AGNER','DENVER','R.'],['ARABEJO','CHARLES','D.'],['COLIMA','EMMANUEL BIEN','B.'],['DELA CRUZ','JOHN CEDRICK','A.'],['FELICIANO','ANGELO','.'],['GLORIA','JAN RICK','L.'],['LOPEZ','JAKE FRANCIS','L.'],['MAPALAD','JOHN RUIZ','S.'],['MARQUEZ','CLARK','A.'],['PORTERIA','JAMIEL','.'],['RECTO','THERON RENZ','C.'],['SOLAON III','ARNULFO','.'],['ZUÑIGA','ADRIAN MICKO','C.'],['BERNESE','JULIE','Y.'],['DELA CRUZ','SABRINA','K.'],['DOBLAS','CHRISTINE','V.'],['EDLES','JEANEL','O.'],['MATIBAG','JANNAHROSE','A.'],['MENDOZA','KRISHA LYNNE','L.'],['PRENDON','RONELYN','C.'],['REVELLAME','LYKA','M.'],['SERNA','CATHRINA ALEXIS','C.'],['TORILLO','DANESSE','L.'],['TORRES','PATRICIA NICOLE','C.'],['VILLASIN','MA. CHARTINA DHENIZZE','E.'],['YANSON','MARIAN','M.']],
        'HUMSS-11SC' => [['PAJARON','LOYD CEDRICK','.'],['SAMPEROY','ESTEVENSON','.'],['VALENCIA','JOHN VINCENT','P.'],['ALTAREJOS','ALEANNA JANE','J.'],['CANGAYAO','PRINCESS AVRIL','A.'],['ESPADILLA','HERNELINE','R.'],['GUNDRAN','MARY GRACE','R.'],['PAGNE','JEAN ROHANA','F.'],['ROBLES','RANIELA','S.'],['SANCHEZ','JHANICA','C.'],['VINGNO','TRIXIE ANN','D.'],['YGBUHAY','ELSA REYNA','B.']],
        'HE-1M1' => [['BACLAO','JOHN DANIEL','A.'],['DIWATA','JOSHUA','C.'],['FRISCO','AUDREY','A.'],['GONZALES','AR-JAY','Q.'],['GRATUITO','DOMINIC','B.'],['HERMANOS','ARGYNE JAY','C.'],['LIBRES','MARIANO','T.'],['MONTOYA','IVAN JAMES','M.'],['SALUMBIDES','ALEXANDER JAMES','F.'],['SANZ','KIRBY','G.'],['TANDAYAG','ROLAND JUSTIN','M.'],['UMBAY','JOHN LORENZ','Q.'],['ACEBUECHE','JHENY MHAE','N.'],['AMATOSA','CHRISTINE MAY','F.'],['BETITA','MARIA CAMILLA','V.'],['CAREON','JEARLENE','V.'],['DELA ROSA','SOFIA CASSANDRA','M.'],['DERLA','AYESHA','D.'],['EDLOY','ANGEL ANN','A.'],['ESCOBER','RUTH ARIADNE','V.'],['ESPINA','ANGEL MAE','S.'],['EVANGELISTA','EDRALYN','T.'],['LEMON','KHAILYCATE YHUNICE','R.'],['MAGDAMIT','GENIEVIEVE','P.'],['MAMBA','ERA GRACE','I.'],['MINGUIN','MARIE ANGELA','D.'],['PESCANTE','ELIJAH','V.']],
        'HE-1M2' => [['AESQUIVEL','NASH','P.'],['BALANGUE','JEFFRIL','B.'],['ESTRELLA','EUREKEN','P.'],['LAGO','MHIGZ HALCY','T.'],['MARES','DUSTIN LEE','R.'],['MARINDA','RYAN JADE','T.'],['MENORCA','SHERWIN WILLIAM','J.'],['REGALARIO','NEVAEH YUAN','P.'],['SANTIAGO','ACE LOUIE','J.'],['SANTIAGO','ANDREI','P.'],['SARONO','KURT NATHANIEL','S.'],['TUTOR','ANTHONY','M.'],['VASQUEZ','ALHEXIS','D.'],['VERDIDA','CLANCE KENDRED','B.'],['YOSORES','KIRBY','O.'],['BRIN','MARIAN','M.'],['FRANCISCO','PATRICIA MAE','E.'],['MANALO','LOUGEE','M.'],['MERCADO','ALYZA','M.'],['PAKINGGAN','HERDELYN','C.'],['SANGILAN','CATHERINE','C.'],['STA. CRUZ','CRIS QUINA','C.'],['TAMA','KYLA MAE','N.'],['TAÑOLA','EINJHEL IRVINE','M.'],['TOLENTINO','BEA MAY','M.'],['VALEZA','FRANCHEZCA YUAN','C.'],['YANONG','PAULA','B.']],
        'HE-1M3' => [['CAMORAL','ARJAN JAY','R.'],['FANCUVILLA','YHAEL MARCUS','P.'],['FERRER','ARJAY','A.'],['LOPEZ','FRANK MANNY','G.'],['MALAPIT','GILBERT JR.','L.'],['MAMACLAY','ALDREW JEXIE','M.'],['PEÑARIDONDO','JAMES DYLAN','B.'],['PLACIDO','JIOVANNI FRANZ','D.'],['POBLARES','VIN RUSELLE','T.'],['RAYA','KHURT KUEIN','C.'],['RODELAS','JUSTINE CURL','D.'],['SABLAD','JOMEL','C.'],['SERASPE','ELIZAR JULES','C.'],['VEDAÑO','JAN NAZARENE','N.'],['YOSORES','KELLY','O.'],['ALEGOYOJO','ANGEL','S.'],['ARCUENO','DIANA MAE','L.'],['AROGANCIA','AYESSA MAE','C.'],['ATIENZA','ERIKAH','V.'],['BASIJAN','ROSE MARIE','C.'],['DE LUNA','CHARMVER ROCEL','.'],['IBARRA','JUDY','T.'],['MANZANO','MICHELLE ANGEL','B.'],['MELCHOR','LYCA MAE','V.'],['MEMIJE','GERBAUD MARITHE','C.'],['PALCE','MIJARA JADE','G.'],['SANGLAY','ANA NICOLE','N.'],['SECRETARIA','JULIEN MAE PATRICIA','A.'],['VICTORIA','JAZMIN HANNAH','M.'],['ZARAGA','DENIEZEL','B.']],
        'HE-1M4' => [['ALINTOSON','ERHON','G.'],['ARANDIA','RONELO','N.'],['BELARDO','DANERICK','R.'],['EROY','JUSTINE DAVE','F.'],['ESPELETA','JEBSTINE','L.'],['GAYO','JOSHUA','A.'],['GUILLANO','NICK ANGELO','B.'],['HANDIG','TEEJAY','S.'],['LIBANG','JOHNRIC LENARD PONCE','D.'],['LOJA JR.','ROLANDO JR.','A.'],['MALAPAD','JAYDEN','B.'],['MANALASTAS','JOHN AUGUSTINE','S.'],['SAGUN','ROD DANRAY','G.'],['SIWALA JR.','JAYSON','A.'],['BANCE','MERYWIN JOY','A.'],['BARBOSA','GILLIANNE','L.'],['BRIONES','APPLE','P.'],['DESUCATAN','JHANINA','L.'],['ESPERA','JAZMINE SOFIAH','Z.'],['FALCOTILO','ANDREA','D.'],['INABANG','NOR-ALIAH','D.'],['JUANERIO','LYNNEL ANNE','V.'],['LAMESERIA','SHIENALE','B.'],['LOPEZ','HERNALYN','A.'],['MARTINEZ','JANELLA FAYE','V.'],['NAZ','GEXIRIE','C.'],['PAPA','LOVELY ANNE','M.'],['PATATAG','JESSEL MAE','V.'],['PETRASANTA','AALIYAH POULINE','L.'],['SAUR','CHRISTELLE','B.'],['TOLENTINO','RHIANE','R.'],['TUMAMAK','CHESKA','O.']],
        'HE-1N1' => [['BAUTISTA','CKLEINE KEANNE','.'],['CASTOR','RICO','L.'],['DEL ROSARIO','JOHN MICHAEL','D.'],['MANZANO','JHON METHAN','C.'],['OCLARES','JUNEL','B.'],['OLITAN','MIKE LORENCE','N.'],['PURIFICACION','CEDRIC JARLS','D.'],['REYES','ARDHELL ROSS','C.'],['SALE','CHAMP JAREN','M.'],['SAMSON','LHARSE JYULRICH','C.'],['SEGUIN','JULES CYBER','R.'],['SEQUIA','KERBY','D.'],['STO. TOMAS','SHANE GABRIEL','V.'],['ALVAREZ','DANIELA ZAIRAH','.'],['BALITIAN','JAIRAH MAY','L.'],['BIGBIG','LUISA FE','A.'],['CAMIT','AUBREY','P.'],['CUSIPAG','MARIANNE JOY','B.'],['ECONG','AYESSA','D.'],['EGOS','GHINE ROSE','S.'],['GEGANTO','SHEKINA CHEIZEN','N.'],['LAGAN','ROCHELLE','D.'],['MALIKSI','JADE','C.'],['PEÑALOSA','JENNY','U.'],['POTENTE','AMBER NICOLE','S.'],['RIVERA','SOPHIA LUISA ISABEL','F.'],['SAMSON','ANGELYN','P.'],['SULIT','NICOLE SHANE','P.']],
        'HE-1N2' => [['ACEDERA','LHYNEL','C.'],['ASTILLERO','JHON CARLO','G.'],['CABERTE','ER JOHN CLYDE','A.'],['CALANZA','MIKO BENEDICT','L.'],['DITAUNON','NIKKO','M.'],['HONOR','RALPH LESTER','L.'],['NICOLAS','JAY-C ANDREW','N.'],['OBIAS','CHRISTIAN JAY','M.'],['PAMPILO','JHERVEN CARL','B.'],['PANGANIBAN','MIKE LOUIS','S.'],['SALUNDAGUIT','EARL JUSTINE','.'],['TAHANLANGIT','JOHN MICHAEL','G.'],['ASTURIAS','SHARMAINE NICOLE','M.'],['AUSAN','KLEO','A.'],['DEGORO','MARY GRACE','C.'],['DELA CERNA','ZAIRA','V.'],['DIONSON','MERCY GRACE','M.'],['ICARO','CHERRY ANN','T.'],['JORDUELA','NICOLE','G.'],['MALOLOY-ON','RYIEEN','C.'],['MAQUIRANG','ASHLEY NICOLE','O.'],['MATUSINOS','MIELLE ARIANNE','V.'],['OBLEA','SYISHA ANN JENICA','C.'],['PANGILINAN','ANDREA','L.'],['SALE','CHEZKAH TRIZHA','M.'],['TOMULTO','JUSTINE KIM','L.'],['TORIO','ASHLEY NICOLE','A.']],
        'HE-11SC' => [['GOMEZ','Raily','B.'],['GOMEZ','REANIELLE KIAN','B.'],['LEE','ALVIN','F.'],['VILLANUEVA','SEUGIO','A.'],['ANN','DANICA SOPHIA COMIA','.'],['BUENA','MICHELLE','I.'],['DEL ROSARIO','JASMINE','G.'],['DELOS ANGELES','APPLE','B.'],['MAHINAY','LOVELY','.']],
        'ICT-1M1' => [['AGUIRRE','GENESIS','B.'],['ARPILLEDA','JUSTIN JAY','P.'],['BULAC','GIAN CARLO','D.'],['CORAJE','REYMOND','C.'],['DEL ROSARIO','ROWJOHN LORENCE','M.'],['DELGADO','JOHN JOSHUA','P.'],['ESPIRITU','HERO','S.'],['GAUT','ELLIANDREI JADE','Y.'],['LAGAHIT','MARK JOHNNEL','M.'],['LAS MARIAS','IANJIE','P.'],['MAMARIL','JERSON','C.'],['MARTINEZ','JAEDAN AHNIELL','E.'],['SOLIS','MARK ANTHONY','S.'],['ZURITA','GABRIEL','A.'],['CORDOVA','SHIELA MAE','A.'],['ERGUIZA','KATRINA','A.'],['GUARIN','KIMBERLYN','O.'],['LEPALAM','ANGEL KIM','J.'],['MILLANO','MARIAN','V.'],['ONDANGAN','HANNAH ALLEYAH','U.'],['PITOS','NIÑA CHARISH','M.'],['RIBERAL','ALILIA CAMILLE','F.'],['SOLIS','DIANNE KATE','A.']],
        'ICT-1M2' => [['ADRIANO','DHREW MAXI','S.'],['AGBAY','M-JAY','L.'],['AGBAYANI','STEVE FRANCIS','B.'],['ALCARAZ','IAN','F.'],['ANDAYA','AERIEL JOHN','C.'],['DELA CRUZ','YOJ MITCHELL','A.'],['GEMARINO','RAINE','M.'],['LOREN','JHON LLOYD','D.'],['MAMANGUN','EMMANUEL','D.'],['MANAOG','RHALF RODEL','D.'],['MEDALLA','NEIL CHRISTIAN','A.'],['PONCE','CHRISTIAN','B.'],['REYES','CHRIS ARJAY','V.'],['RODRIGUEZ','THEORENZ MATTHEW','G.'],['ROMAY','LORENCE','L.'],['SIOBAL','JUSTINE CARLO','R.'],['VILLA','LOUIE FRANCIS','M.'],['ABARIENTOS','PRINCESS MAE','B.'],['ABRASALDO','TRISHA MAE','G.'],['APALIN','NORANE ANGELA','.'],['AYO','SHARINA MAE','T.'],['BOBIS','JAIRISH','G.'],['CORREOS','GLORY MAE','V.'],['CUALBAR','GERMAINE JOY','E.'],['HICANA','PRINCESS','M.'],['JAMER','EUNICE BIANCA','C.'],['JARDIO','JALEN ROSE','C.'],['PABLO','REGINE','A.'],['ROQUE','LORIEDEL','G.'],['TROPEL','PRINCESS LOVELY','E.'],['VILLANUEVA','MA. ANTHONETTE','U.']],
        'ICT-1N1' => [['ADIS','JABEZ MIEL','G.'],['ANASARIO','JEFF RYAN','T.'],['DE LUNA','JOHN CLIEYAN','M.'],['DE LUNA','LENNARD','M.'],['DE SOSA','ERIZ KHEL','V.'],['DE VERA','MIGUEL','R.'],['FERMANTE','IORI','M.'],['GARCIA','YURI','A.'],['LUMIANO','EDGIE','S.'],['OFENDA','AURIO','G.'],['POLANCOS','JOMARI','M.'],['RACAB','KING GENESIS','L.'],['TADEO','CHAZZ','.'],['TAMBIS','JOHN ZENDRICK','P.'],['TUATIS','DANILO JR.','A.'],['TULIPAT','KYLE NOELLEMAR','A.'],['VALDEZ','JAMES EVANZ','C.'],['ACID','YUNA','C.'],['ASPE','FIONA RICH','D.'],['CAÑA','JAYNALYN','.'],['LEGARDA','DAZZLE','R.'],['MANIABO','JESHLYN CASSANDRA','B.'],['OLAVERE','ZAPHIRE HYCENTH','P.'],['REDUCCION','CHARLENE','P.'],['TRINIDAD','RISHIANE','C.']],
        'ICT-1N2' => [['ACEVEDA','AL-PRICHVEUZ','B.'],['DANTE','EARL DOULTON','S.'],['DERIGAY','DWAYNE VAL ZEDRICK','T.'],['ENAMNO','MARK VINCENT','D.'],['MERENCILLA','DAREN','L.'],['MICALLER','MARK JUSTINE','D.'],['URGEL','FRENZ AIDEN','E.'],['VENEZUELA','MARK LAWRENCE','A.'],['VILLALVA','JULIAN','R.'],['BUYA','ASHERA MAE','J.'],['FRANCISCO','PRINCESS YVETTE','L.'],['EMBODO','DAN JAYSON','A.'],['Alpeche','Krystal Mae','L.'],['CASTILLO','LOVELY','B.'],['OCON','BERSON MARK','.'],['PESCANTE','RHANE','V.'],['AGABON','SCARLET JADE','V.'],['Caruyan','Trixy','A.'],['ISAAL','DARLENE','L.'],['MALLORCA','LIAN CLARISE','R.'],['MENGUITO','LOUISSE BIANCA','D.'],['Pangandaman','Faysah','M.'],['SILVA','SHERAN ANN','M.'],['UMANDAP','FRANCINE JOICE','.'],['VILLANUEVA','CHELSEY ANNE','.']],
        'ICT-11SC' => [['ERENEA','FREDERICK','L.'],['QUILLAO','ZIMON FRANCIS','P.'],['BAGAPURO','KC ANGELA','D.']],
        'HUMSS-3M1' => [['ARNALDO','MARK JASTINE','M.'],['Caballero','Paulo','C.'],['Callueng','Vaughn Louelle','B.'],['Carlos','Prince Vernie','I.'],['Delos Reyes','Jhervin Grhei','T.'],['Fuertes','Benjie Jr.','M.'],['JAVELO','JOHN ALFRED','E.'],['Manaig','John Louie','F.'],['Menguito','Prince Kyle','I.'],['NEGOLO','JOHN MARKY','C.'],['Noblefranca','Laurence James','D.'],['PAGCALIWAGAN','AERHON','D.'],['RESTRIVERA','MARK JERICK','A.'],['Talimay','John Angel','C.'],['Valles','Jay Iverson','B.'],['Bayson','Lejana','S.'],['FARO','JEAN KATHLEEN','D.'],['Fuentes','Kyle','B.'],['Garcia','Alyza Chinee','V.'],['Geniblazo','Rhizza','D.'],['Jubay','Janlex Trish','M.'],['MACAPAGAL','ANNA MARIE','R.'],['Mañosa','Diana Grace','B.'],['Modesto','Mharky','C.'],['Mustaza','Aira Camille','P.'],['Quijano','Nadine Alyssa','P.'],['Regondola','Neisha Leiyhan','D.'],['Sagang','larahvelle','H.']],
        'HUMSS-3M2' => [['Capurihan','Nelbert','D.'],['DELFIN','REY MARLOWE','A.'],['ENOC','MARK JERVIN','T.'],['JAMER','JUSTINE','C.'],['JEVIO','RYU JAZTYN','S.'],['LOZADA','JOHN CARL','D.'],['Moreno','Edward Miguel','B.'],['PURIFICACION JR.','FERNANDO','B.'],['Saut','Richmond Alfred','C.'],['TEPACIA','RIO','I.'],['Tolin','Marvyn','S.'],['VIBAR','SEAN DAVID','B.'],['Villadolid','John Raven','M.'],['YAP III','ROMEO','M.'],['Adis','Faith Anne','G.'],['Bandales','Sherleen Mae','J.'],['CENA','PRECIOUS JANEFREY','D.'],['CONCHIA','Elishia May','S.'],['GOCALIN','NICOLE','E.'],['Hilario','Irish','B.'],['Larede','Christal Faith','V.'],['LEBRERO','ANNA LOWIE','S.'],['Maagma','CLARA MORIEN','B.'],['Nual','Princess Nicole','C.'],['Ortiz','Paulyn','N.'],['Rivas','Quincy Loraine','M.'],['Tubal','Ryzelle Ann','T.'],['Valerio','Loraine Joy','R.']],
        'HUMSS-3M3' => [['AGULTO','JUSTINE MARK','M.'],['Baluncio','JUSTINE','A.'],['Bayaborda','Nandrew','G.'],['Castillo','Frank Lester','C.'],['Centeno','Angelo Macoy','R.'],['Siscar','John Kenneth','A.'],['Tuibeo','Shanley','S.'],['UDAL','ROHANN DENVER','T.'],['Cagandahan','Jovelyn','S.'],['Catalan','Cyrish Daniela','B.'],['Celis','Kayceen Krisbel','M.'],['Escalona','Rojen','S.'],['ICARO','CLAIREY','T.'],['LAÑOJAN','Pearl Joy','M.'],['Leonida','Chloe','C.'],['LLOSE','ANDREA GAILE','B.'],['MACARAIG','STEPHANIE','S.'],['MACARIOLA','ATHASIA ANN','R.'],['Maglunob','Alyza Mae','U.'],['Minguin','Marie Angelyn','D.'],['Miranda','Seiya','M.'],['Ortillo','Janelle Hazel','P.'],['Revellame','Jenilyn','M.'],['Rico','Jhoana Rose','A.'],['Rocas','Myra Stacey','C.'],['Segunla','Nicole','P.'],['SELDA','SHARLENE MAE','C.'],['Zerrudo','Danica Joy','Z.']],
        'HUMSS-3M4' => [['CABANGUNAY','JHOHANNES ARIEL','G.'],['DUMRIGUE','FRANCISCO','.'],['FULGAR','KARL BRIX','.'],['Javier','Martin Lordy','C.'],['MADRIGAL','LUIS PHILIP','R.'],['Marquez','Luke Kian','B.'],['PACLE','JHONZEI DAVE','M.'],['PANDIONG','NOEL','B.'],['Riberal','Jimfrixon Fruto','F.'],['Sigue','John Lord','T.'],['Soltes','John Patrick','M.'],['Villaflor','Aldrich','S.'],['Abrera','Princess','M.'],['Albios','Dhenise Mae','P.'],['BAUTISTA','ALLIEZA MEI','T.'],['BLEZA','MARHY JHOY','B.'],['Fabillo','Sabina','R.'],['Fugoso','Lorie Lozel','P.'],['GERALDO','LUCILLE ANGELA','C.'],['HICAP','ANGELICA','M.'],['Jimenez','Jessa','S.'],['Mabansag','Mikylla Anne','G.'],['Mallari','Mary Joy','C.'],['Rabillas','Ashley Nicole','R.'],['Rodrigo','Cristal Joy','E.'],['SIBONGA','JANELL','R.'],['Tagapan','Trisha Nicole','V.'],['TATEMURA','ZAINAKI LOUISE KHATE','S.']],
        'HUMSS-3N1' => [['Cabanday','Tj','G.'],['CARDEÑO','AARON JAMES','V.'],['CERVEZA','CHRYSLER','G.'],['CUTAMORA','KENJIE','E.'],['FELONIA','ANTHONY','C.'],['MARTINEZ','LHANZ REYVER','F.'],['MATEO','LOUIGIE','H.'],['MATUBANG','ITTSEI','B.'],['Molines','John Kerby','A.'],['RAMIREZ','VANESS','A.'],['Tumbado','Jericho','T.'],['VICENTE','JUSTINE CRIS','V.'],['Baba','Lovely','F.'],['Ballecer','Lovely May','C.'],['CAUNCERAN','GHIA','S.'],['DEMATE','JOANNA CAMILLE','D.'],['Espiritu','Kimberly','P.'],['Go','Princess Kyrylle','M.'],['LABRADOR','ALYSON ANN','T.'],['MARTINEZ','PRINCESS RYEANHNE','C.'],['Parreño','Karen','A.'],['SOLO','VANESA','M.'],['Trinidad','Pearl Joy','H.']],
        'HUMSS-3N2' => [['BALAGA','JAZZ RIEL','L.'],['CAINTO','CHRISTIAN PAUL','.'],['Mape','Jhestine Cylle','Z.'],['MORADA','ARTH ISRAEL','S.'],['NAVEA','JOHN FRANCIS','T.'],['PEREZ','JHON CYRUZ','D.'],['QUERUBIN','ARNOLD','C.'],['VENTURA','ALOYSIUS JOHN','A.'],['VILLANUEVA','RONJAY','F.'],['ALEGUIOJO','ALLYSA','N.'],['BRUTAS','SHIELAMY','S.'],['GUALIZA','REGINE','.'],['LOYOLA','JASMIN ROSE','M.'],['MANARIN','MARY ROSE','N.'],['Musni','Rochelle','C.'],['Pagayon','Jasmine Kimberly','B.'],['PANGAN','JANA MARIE','R.'],['PANTUA','ANTONETTE','F.'],['PEÑARANDA','NERISSA','.'],['PEREZ','RHAICEL','Y.'],['PONCE','ARIANNE MAE','B.'],['PUNZALAN','AIZEL','D.']],
        'HUMSS-3N3' => [['Barlao Jr.','Angelito','F.'],['CERIACO','DOMINIC','R.'],['DOBLAS','CRISTIAN','V.'],['Figueroa','Dave Clarence','L.'],['LASPRILLAS','JHASTINE','T.'],['LIZARDO','CARL MATHEW','A.'],['LOTERTE','Rainielle','Q.'],['PORTRIAS','JOHN BRENT','S.'],['Rodriguez','Rainniel','D.'],['Adan','Rainalyn','M.'],['BALDONASA','MIA','D.'],['BAZAR','CRISHEL MAE','R.'],['BERGADO','JOANNA DENIZE','F.'],['BORJA','JANICA EARD','S.'],['Cayosa','Krizz Aubrey','C.'],['Escaran','Jhennica','B.'],['Gasga','Shiela May','J.'],['GICARO','SARAH','L.'],['Jazareno','Shirene','S.'],['TRONGCOSO','BERNAJANE IRISH','P.']],
        'HUMSS-3N4' => [['ABAJA','JUSTINE DHARYLLE','A.'],['ARAGAY','NORMAN RHAZEL','C.'],['CACO','JUNE MANUEL THIRDY','A.'],['Cilmar','Bharon Jhone','H.'],['GARINGALAO','RENZ LOUIE','M.'],['ROQUE','MARK JENO','D.'],['ABUCAY','REXELLA','L.'],['Aldas','Cristine Reign','E.'],['Dela Cruz','Samantha','K.'],['DELUTE','ANGELA MAE','.'],['Deocariza','Ashly Mae','L.'],['Dimasalang','Aubrey','L.'],['Godinez','Jemarie','M.'],['LAURIO','PRESIOUS CRISTALEI','V.'],['Macalindong','Andrea Mae','L.'],['MANGENTE','JANELLE','S.'],['Mota','Rusha Ann','O.'],['Ontog','Princess Tiffany','M.'],['Robedillo','Liana Kim','G.'],['SERMENCE','MARY ANGELYN','C.'],['TARASONA','JANA MAE','C.'],['Tubos','Kesia Joy','R.'],['VILLANUEVA','RICHELLE','C.'],['Villar','Jhonaimah','.']],
        'HUMSS-12SC' => [['ELEGUE','JERICKO','C.'],['LACANILAO','MARK THADEOUS','P.'],['LUSTADO','JUSTIN','L.'],['SIMBORIO','Dennis','P.'],['Ayro','Princess Rhianne','A.'],['BABILA','MICA','M.'],['DE LEON','JAMAICA','A.'],['DEMORIN','VANESSA MAE','D.'],['FRIAL','KIMBERLY','F.'],['GUILLERMO','DECERY','S.'],['HADJI FAHAD','JOHANISAH','M.'],['Lingad','Lureyn','Z.'],['Lumor','Nicole','S.'],['Monares','Kathleen Maine','A.'],['NAYVE','ADRIE ANNE','B.'],['Villamaso','Jennifer','B.'],['VIRAY','JENNY','-.']],
        'HE-3M1' => [['BANDONG','JHON JHASPER','C.'],['Carlos','John Paul','T.'],['Cubelo','Ydran-Rick','S.'],['Dispabeladeras','Jerimiah','M.'],['Dominguez','Raja Cebastian','G.'],['Harina','Karl Daniel','D.'],['Maigue','John Harold','G.'],['Melodias','Kurt Lorens','R.'],['ODON','ALDRIN','D.'],['SANGLAY','JOHN WESLEY','G.'],['Secula','Arjay','.'],['Sengco','Mcnesse Jaerjarvi','D.'],['Valenzuela','John Miguel','E.'],['Abejo','Sheena Anne','S.'],['Adonis','Mary Grace','M.'],['Alolor','Shekainah','C.'],['Aspi','Angela','L.'],['AVENDAÑO','Princess Angel','C.'],['BAGUAL','LHAICA','B.'],['Belandres','Charlene','L.'],['Cortez','Ma. Jinky','F.'],['Dela Cruz','Hecy Jya','M.'],['Dimaano','Daphne Vhenice','V.'],['Lacdao','Angel Mae','D.'],['Mero','Lenie Ann','Y.'],['Sale','Carrie Lei','L.'],['STARLING','NATASHA','.']],
        'HE-3M2' => [['AMARO','SABINO','T.'],['ANTOLIN','RONNEL SHERWIN','C.'],['BALASA','XANDER','C.'],['BAMAN JR.','RUEL','B.'],['BORJA','JIANN','.'],['Calzado','Jhayvee','R.'],['Estrella','Wennard','F.'],['Gratil','Anthony Joseph','A.'],['Magistrado','Carlo','D.'],['PADILLA','MELL CHRISTIAN','N.'],['PLANAS','JHON LENARD','B.'],['Romero','Albrich','B.'],['Tablante','John Reynan','D.'],['YUMANG','ADRIAN','P.'],['ARISTOTELES','AIRISH JHEM','R.'],['CONCEPCION','KATE JASMINE','D.'],['Conferso','Rhiann Denise','L.'],['Crespo','Jane','G.'],['Cusay','Amira Mae','D.'],['DIONISIO','AVRIL ANN','D.'],['Ebina','Karyell','C.'],['Feliciano','Joyce','S.'],['FILLAR','RICH ANNE','M.'],['MARAÑA','MA.ANGELICA','A.'],['MICOSA','ARA MAE','R.'],['PANTANOSA','DANICA','G.'],['Tomaquin','Lianne','S.']],
        'HE-3M3' => [['CORPUZ','ELJOHN JAMES','T.'],['Escape','John Mesias','G.'],['FERRER','ROWEL','A.'],['ORTEGA','MARK JOSEPH','D.'],['PASIGNA','JOHN MICHAEL','B.'],['Ramirez','Joshua','A.'],['RIVERA','JOHN CURVIE','.'],['ACORDO','LUISA JANE','E.'],['Acosta','Rhyanna Rayne','S.'],['AGUILLON','JOLAND','B.'],['Cabardo','Dyrah','F.'],['Canillo','Anna Geniela','M.'],['CO','Lhei Anne','A.'],['CUADRA','HYACINTH','M.'],['Dela Peña','Kriezza','E.'],['DELA PEÑA','MARIAN','.'],['Fraga','Gwyneth','A.'],['GABRIEL','EDZIEL JOY','C.'],['Hernandez','Kylla May','A.'],['LAGARTO','EZECKIEL JEWEL','S.'],['Nagares','Angel','G.'],['OBEROS','AMANDA BHEA','A.'],['RAMOS','JENNIE PEAL','F.'],['REMO','PRINCESS DIANA MAE','P.'],['SAAVEDRA','JULIA ALYSSA','D.'],['SALICO','SHANICE','V.'],['SARIMOS','MIA MARJORIE','.']],
        'HE-3M4' => [['AYADE','RENGIE','P.'],['Baro','Rhod Kenneth','T.'],['BAUTISTA','JOHN CARLO','V.'],['Camba','Jhon Rich','A.'],['DISTURA','BEN','.'],['NACIS','LARENCE','A.'],['ROSALES','KIEN RUZZEL','S.'],['ASIS','PRINCESS HANNA','T.'],['Cabate','Emily','T.'],['DE VETERVO','PRINCES JOANA','D.'],['Dizon','Maria Christina Joy','B.'],['DOMASIG','JASMIN','V.'],['Doyola','Charisse Ann','V.'],['ESTREMOS','DANICA','G.'],['Gesmundo','Alyssa','L.'],['Lambunao','Angel Ann','L.'],['Mallorca','Trisha Mae','D.'],['MAPILI','RICHELLE ANN','L.'],['MARQUEZ','KIM CARLA','C.'],['Mendoza','Melnhess Ann','-.'],['Peregrin','Hanna Kim','R.'],['RAMOS','KRISTEL JEAH','.'],['Salvador','Evan','R.'],['Samson','Heizzel','C.'],['SANTIAGO','PRINCESS','D.'],['Trinidad','Astra','T.']],
        'HE-3N1' => [['FORTUNA','JAY','M.'],['GAYTANO','JOHN CARLO','M.'],['MIRANDA','MARK','R.'],['MONTILLA','JOHN KEN SHAWN','A.'],['ORTIZ','ACE','R.'],['PEÑA','JONATHAN','.'],['RESUELLO','CRIS ALBERT','C.'],['RICO','CLARK LYNELL','L.'],['SEGUIN','CHESTER BENETTON','R.'],['Vibares','Dwight Jaymer','P.'],['ALALAY','LIAN ASHENETTE','O.'],['BALTAZAR','JOLAN','B.'],['CARIÑO','KAMILLE','A.'],['CATIBOS','MICHAELLA','N.'],['DE VILLENA','ANDREA','C.'],['Dominguez','Marjorie Faye','M.'],['ENCARNACION','EDNIELYN','O.'],['LAYUG','PRINCESS ANN','.'],['MORALES','RUBIE ANN','O.'],['Policarpio','Yuri Ann','C.'],['Remorquez','Danica','V.'],['Sarabia','ma. cathryn jamille','C.'],['SIMBORIO','SHIELA MARIE','P.'],['TORRES','MARIEL','A.'],['VALDEZ','OLIVERA','M.']],
        'HE-3N2' => [['BAYABAY','ALLAN MARION','Q.'],['BEHINIO','MARK ANGELO','.'],['Clasin','Cyrus','S.'],['Dollete','Daniel','S.'],['ESCOL','RALPH CHRISTIAN','C.'],['Enardicido','Joven','V.'],['ESPENILLA','ALEXIS','L.'],['GAMBOA','JHAYPHEE','P.'],['ORTEGA','IVAN KHARL','.'],['QUEBRAL','LOMER','V.'],['Radan','Jericho','C.'],['RIVERA','JILIAN XANDER','.'],['SAMARITA','MARK LESTER','P.'],['BARBOSA','MA. FAUSTINA','P.'],['Esperanza','Elaine','G.'],['MANZANILLA','RHEA LOUISA','L.'],['Martinez','Precious Gem','B.'],['MIRAÑA','MARJO APRIL','M.'],['PALO','JESSIE','M.'],['Saguing','Sharmaine','E.']],
        'HE-3N3' => [['ARIMAN','MARK ALINUR','C.'],['Baldeo','Mark Angelo','V.'],['BERBULLA','JAMEER','P.'],['CABALES','CHRIS MARK','M.'],['Cantada','John Brix','D.'],['CINCO','RONIE','B.'],['OLEGARIO','LORENCE','D.'],['ORTEGA','KIETH','T.'],['PARCIA','MARK ANTHONY','A.'],['RIVERA','ALDWIN JOHN','O.'],['Ruga','Khenjay','R.'],['BAUTISTA','KRIZZLE','D.'],['Cabrera','Daireen','D.'],['DELA CRUZ','MEYONAH YVONNE','R.'],['ESPINOSA','MARIAN JOY','C.'],['ESPLANA','CHARLOTTE','A.'],['Ford','Shiela Mae','M.'],['MONTENEGRO','LADYLIN','Y.'],['Nieva','JERAMY','J.'],['PADUA','JENNYLYN','.'],['PIZON','ANGIELOU','-.']],
        'HE-3N4' => [['AGUIRRE','JOHNSSEN','B.'],['CARAAN','KARL','P.'],['CASEM','MICO GABRIEL','K.'],['GUARIN','RHAFAEL','-.'],['LAURENTE','SHERJUN','L.'],['REDILLAS','EARL JOHN','.'],['SAMARITA','LOUIE','M.'],['SINGSON','JANTRO','I.'],['Babagay','Ryza','A.'],['BUCIO','RUDYLIZA','A.'],['DANGANI','JENITIN','J.'],['LIMBO','JULIA','F.'],['LOTO','BLESSIE RAINE','S.'],['MARIANO','ASHLEY','D.'],['Mimay','Princess Kyla','T.'],['MONTIERDE','JESSA','S.']],
        'HE-12SC' => [['BARUNDIA','GRANT HILL','G.'],['CASAS','MIKE JOSEPH','V.'],['CATIGBE','JOHN MICHAEL','A.'],['MACASIL','MARK JOSHUA','A.'],['POLO','PETER KYLE','N.'],['RICAPLAZA','ALBERT JOSEPH','T.'],['BARLIS','RHEANEL','B.'],['BARRUN','CARELYN','S.'],['Cañada','Queency','D.'],['GABIA','MA.LUISA','H.'],['HONRUBIA','FLOR JAMINE','C.'],['IBARRETA','ALYANA REIN','A.'],['LOZADA','KIMBERLY','G.'],['PEREZ','ALYSA','A.'],['Platino','Mayrish','D.'],['RODRIGUEZ','VERONICA','S.'],['TORRES','ELIZ AUGUSTINE','B.']],
        'ICT-3M1' => [['AGUILO JR.','RAMEL','R.'],['BALAORO','JOSEPH','C.'],['Baluca','Lowel Ezekiel','T.'],['BARBA','FRANZ RUSSEL','D.'],['CAÑETE','RALPH KENNETH','P.'],['DINGLASAN','JARETT ADHAN','T.'],['EBEN','JAMIR ANGELO','A.'],['ELARDO','ELIZSAR','S.'],['ENRIQUEZ','KIAN GREY','.'],['Lutiva','Ezekiel Carl','.'],['POLENDEY','JHUDIEL','S.'],['RICAFORT','ANDRES PIO','B.'],['SANTOS','RHAINE','B.'],['SANTOS','KARL ALDRIN','M.'],['Tarriela','Justine','G.'],['Tayo','Stanley Emmanuel','D.'],['Tiangson','Lormin Jr','B.'],['Turirit','Justine carl','G.'],['VALDEZ JR.','Charlie','L.'],['Balagat','Michaela Rose','D.'],['Flores','Aryan Rose','R.'],['Maglalang','Jamaica Rain','F.'],['OCAMPO','JAZMINE','P.'],['Paderes','Andrea Nicole','P.'],['PUZON','LYN ANDRAE','A.'],['Tupaz','Sihinity Vaine','N.']],
        'ICT-3M2' => [['ABEJA','JHARED ROENDHEL','M.'],['AJOC','JHON ALBERT','P.'],['ARCE','KHELVIN RHAIN','G.'],['Calonia','Emmanuel','C.'],['Copada','Aldrin','C.'],['DE CASTRO','JOHN EVRICK','M.'],['FELICIDARIO','AARON CEDRICK','.'],['Garcia','Lenard','D.'],['Mendoza','Moises','B.'],['REFUGIO','SEVEN','A.'],['ROSTATA','KENT AUBREY','C.'],['SALDO','BHOT DARYL','M.'],['TIJING','GIAN CARLO','B.'],['AMBALAN','ROSE JANNE','C.'],['ANDRADA','ANGELA BLESS','F.'],['Castañeto','Angelica','V.'],['De Luna','Charian Aimver','-.'],['DELA CRUZ','MARIE','D.'],['DILLAGUE','RIZANETH','B.'],['FADERANGA','LORELYN','J.'],['LAGARTO','ISAAH JEWEL','S.'],['Macabanti','Princess Jmie','A.'],['MALICDEM','JINNEYFER','S.'],['MERCADO','ALEXIS JEAN','Q.'],['NIOSCO','APPLE JEAN','B.'],['PANGANIBAN','ERNESTLYN','S.'],['QUITILEN','JUDY','D.']],
        'ICT-3N1' => [['ASPE','LAURO DANIEL','D.'],['Caro','Eirhon Khim','S.'],['CASTILLO','ARBY','B.'],['Doming','Ian Cedric','M.'],['Gamba','John Peter','B.'],['GARING','DANIELLE LORENZ','G.'],['JAYNOS','STEPHEN JOHN','O.'],['Lasanas','John Romel','A.'],['Pantanosa','Jeynard','T.'],['PECUNDO','JOHN MAURICE','.'],['Pimentel','Erick','T.'],['REYES','SEAN EMERSON','M.'],['SIBOLINO','CHRISTIAN','A.'],['SUMILANG','JOHN JOSHUA','S.'],['Torejas','Gabriel Angelo','Q.'],['VERGARA','WINLOVE GOERGE','L.'],['BAUTISTA','ALTHEA','M.'],['CANO','JEZRA','Y.'],['CATIMBANG','PATRICIA ISABEL','E.'],['Convis','Micaella','N.'],['FLORENDO','RYSAH MAE','Y.'],['Macdon','Julie Anne','B.'],['VILLASIS','ALEXANDRA NICOLE','P.']],
        'ICT-3N2' => [['Alina','Voldemort Yuri','N.'],['Amaro','Cyruz','T.'],['Arsenia','Arvie','E.'],['Cellona','Jason','.'],['Cruz','Renz Jamer','D.'],['DADOR','MIGUELITO','.'],['MEDINA','MHICO EMMANUEL','.'],['PABU-AYA','JHON BERT','R.'],['PANINGBATAN','FARHAN','S.'],['Paralejas','John David','M.'],['Tadena Jr.','Jonathan','G.'],['Bacolod','Rhegine','M.'],['Borale','Krizmarie Jed','A.'],['Buado','Julia Margaret','P.'],['Maghirang','Reyanne','A.'],['PARAGILE','SOFIA','E.'],['Pascua','Deserie','B.'],['ROCABERTE','SHAILA','A.'],['TROPA','ANNABELLE','H.']],
        'ICT-12SC' => [['ALDEA','JOHN REYRENZ','M.'],['Asucenas','Emmanuel','J.'],['DAVID','LAWRENCE','C.'],['MAHUSAY','JEFF MARLOU','T.'],['MISSION','FREDRICK','D.'],['PASANA','AIDAN JAMES','C.'],['TRINIDAD','JEFFERSON','D.'],['ABORDO','PAMELA','D.'],['BALINA','BEAH','R.'],['HALON','JHAZMINE KLARIZ','H.'],['NIOSCO','STRAWBERRY PEARL','B.'],['PAUNIL','MARJORIE','D.'],['RELLAMA','KATHERINE','T.']]
    ];

     $setup_messages[] = "⏳ Creating student login accounts...";
    $students_stmt = $pdo->query("SELECT id, full_name, first_name, last_name FROM students");
    $all_students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $student_users_created = 0;
    $default_password_hashed = password_hash('pass123', PASSWORD_DEFAULT);

    foreach ($all_students as $student) {
        // Create a simple username, e.g., "delaCruzJ"
        $fname_initial = substr($student['first_name'], 0, 1);
        $username = strtolower(str_replace(' ', '', $student['last_name']) . $fname_initial);

        // Check if username already exists to avoid errors on re-run
        $check_user = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $check_user->execute([$username]);

        if ($check_user->fetchColumn() == 0) {
            $insert_user = $pdo->prepare(
                "INSERT INTO users (username, password, user_type, full_name, student_table_id) VALUES (?, ?, 'student', ?, ?)"
            );
            $insert_user->execute([
                $username,
                $default_password_hashed,
                $student['full_name'],
                $student['id']
            ]);
            $student_users_created++;
        }
    }
    $setup_messages[] = "✅ Created {$student_users_created} new student login accounts. The password for all students is 'pass123'.";


    $total_students_created = 0;
    foreach ($all_students_by_section as $section_code => $students) {
        $section_stmt = $pdo->prepare("SELECT id FROM sections WHERE section_code = ?");
        $section_stmt->execute([$section_code]);
        $section_id = $section_stmt->fetchColumn();

        if ($section_id) {
            $students_created_in_section = 0;
            foreach ($students as $student_data) {
                $last_name = $student_data[0];
                $first_name = $student_data[1];
                $middle_name = $student_data[2] ?? '';
                $middle_name = (in_array($middle_name, ['N/I', '-', 'n/a'])) ? '' : $middle_name;
                
                $full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
                
                $check_student = $pdo->prepare("SELECT COUNT(*) FROM students WHERE full_name = ? AND section_id = ?");
                $check_student->execute([$full_name, $section_id]);

                if ($check_student->fetchColumn() == 0) {
                    $insert_student = $pdo->prepare("INSERT INTO students (last_name, first_name, middle_name, full_name, section_id) VALUES (?, ?, ?, ?, ?)");
                    $insert_student->execute([$last_name, $first_name, $middle_name, $full_name, $section_id]);
                    $students_created_in_section++;
                    $total_students_created++;
                }
            }
            if ($students_created_in_section > 0) {
                $setup_messages[] = "✅ {$students_created_in_section} students created for section {$section_code}";
            }
        } else {
            $errors[] = "❌ Section code '{$section_code}' not found in the database. Students for this section were not added.";
        }
    }
    $setup_messages[] = "🎉 Total new students created: {$total_students_created}";


    // ===============================================
    // INSERT TEACHERS DATA
    // ===============================================
   $college_teachers = [['MR. VELE', 'COLLEGE'], ['MR. RODRIGUEZ', 'COLLEGE'] /* ... more college teachers */];
    $shs_teachers = [['MR. VELE', 'SHS'], ['MR. RODRIGUEZ', 'SHS'], ['MS. TINGSON', 'SHS'] /* ... more shs teachers */];
    
    $all_teachers_with_departments = array_merge($college_teachers, $shs_teachers);
    $teachers_created = 0;

    foreach ($all_teachers_with_departments as $teacher_data) {
        $name = $teacher_data[0];
        $department = $teacher_data[1];

        // Check if the specific name + department combination exists
        $check_teacher = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE name = ? AND department = ?");
        $check_teacher->execute([$name, $department]);
        
        if ($check_teacher->fetchColumn() == 0) {
            $insert_teacher = $pdo->prepare("INSERT INTO teachers (name, department) VALUES (?, ?)");
            $insert_teacher->execute([$name, $department]);
            $teachers_created++;
        }
    }
    $setup_messages[] = "✅ Created/verified {$teachers_created} teacher entries across departments.";


} catch (PDOException $e) {
    $errors[] = "❌ Database Error: " . $e->getMessage();
} catch (Exception $e) {
    $errors[] = "❌ General Error: " . $e->getMessage();
}

    // ===============================================
    // SECTION TEACHER ASSIGNMENTS
    // ===============================================
    $all_assignments = [
        'BSCS-1M1' => ['MR. VELE', 'MR. RODRIGUEZ', 'MR. JIMENEZ', 'MS. RENDORA', 'MR. LACERNA', 'MR. ATIENZA'],
        'BSCS-2N1' => ['MR. RODRIGUEZ', 'MR. ICABANDE', 'MS. RENDORA', 'MR. V. GORDON'],
        'ICT-3M2' => ['MS. LIBRES', 'MR. LACERNA', 'MR. ICABANDE', 'MR. UMALI', 'MR. V. GORDON'],
        'ICT-3N1' => ['MS. LIBRES', 'MR. LACERNA', 'MR. ICABANDE', 'MR. UMALI', 'MR. V. GORDON'],
        'ICT-3N2' => ['MS. LIBRES', 'MR. LACERNA', 'MR. ICABANDE', 'MR. UMALI', 'MR. V. GORDON'],
        'HUMSS-3M1' => ['MS. CARMONA', 'MR. LACERNA', 'MS. LIBRES', 'MR. PATIAM', 'MS. RENDORA', 'MR. GARCIA', 'MR. BATILES'],
        'ABM-3M1' => ['MS. CARMONA', 'MR. BATILES', 'MS. RIVERA', 'MR. PATIAM', 'MR. UMALI', 'MR. CALCEÑA'],
        'HE-11SC' => ['MR. LACERNA', 'MR. RODRIGUEZ', 'MR. VALENZUELA', 'MR. MATILA', 'MR. UMALI', 'MS. GENTEROY'],
        'ICT-11SC' => ['MR. LACERNA', 'MR. RODRIGUEZ', 'MR. VALENZUELA', 'MR. MATILA', 'MR. JIMENEZ'],
        'HE-12SC' => ['MR. VELE', 'MR. ICABANDE', 'MR. PATIAM', 'MS. GENTEROY'],
        'ICT-12SC' => ['MR. VELE', 'MR. ICABANDE', 'MR. PATIAM', 'MR. JIMENEZ']
    ];

    function assignTeachersToSection($pdo, $section_code, $teacher_names, &$total_assignments) {
        $section_stmt = $pdo->prepare("SELECT id FROM sections WHERE section_code = ?");
        $section_stmt->execute([$section_code]);
        $section_id = $section_stmt->fetchColumn();
        
        if (!$section_id) { return "Section {$section_code} not found"; }
        
        $assignments_created = 0;
        foreach ($teacher_names as $teacher_name) {
            $teacher_stmt = $pdo->prepare("SELECT id FROM teachers WHERE name = ?");
            $teacher_stmt->execute([$teacher_name]);
            $teacher_id = $teacher_stmt->fetchColumn();
            
            if ($teacher_id) {
                $check_assignment = $pdo->prepare("SELECT COUNT(*) FROM section_teachers WHERE section_id = ? AND teacher_id = ?");
                $check_assignment->execute([$section_id, $teacher_id]);
                
                if ($check_assignment->fetchColumn() == 0) {
                    $insert_assignment = $pdo->prepare("INSERT INTO section_teachers (section_id, teacher_id) VALUES (?, ?)");
                    $insert_assignment->execute([$section_id, $teacher_id]);
                    $assignments_created++;
                    $total_assignments++;
                }
            }
        }
        return $assignments_created;
    }

    $total_section_assignments = 0;
    foreach ($all_assignments as $section_code => $teachers) {
        $result = assignTeachersToSection($pdo, $section_code, $teachers, $total_section_assignments);
        if (is_numeric($result) && $result > 0) {
            $setup_messages[] = "✅ {$result} teacher assignments created for {$section_code}";
        } elseif (!is_numeric($result)) {
            $errors[] = "❌ Error assigning teachers to {$section_code}: {$result}";
        }
    }
    if($total_section_assignments > 0) {
        $setup_messages[] = "🎉 Total section-teacher assignments created: {$total_section_assignments}";
    }

} catch (PDOException $e) {
    $errors[] = "❌ Database Error: " . $e->getMessage();
} catch (Exception $e) {
    $errors[] = "❌ General Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Faculty Evaluation System</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; border-left: 4px solid; }
        .success { background-color: #dff0d8; border-color: #3c763d; color: #3c763d; }
        .error { background-color: #f2dede; border-color: #a94442; color: #a94442; }
        .info { background-color: #d9edf7; border-color: #31708f; color: #31708f; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Setup Results</h1>
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($setup_messages)): ?>
            <?php foreach ($setup_messages as $message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="message info">
            <strong>Note:</strong> This setup script should only be run once. For security, you should restrict access to this file after setup is complete.
        </div>
        <p><a href="index.php">Return to Login</a></p>
    </div>
</body>
</html>

