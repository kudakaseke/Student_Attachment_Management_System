<?php
declare(strict_types=1);

// Avoid Windows permission issues with default XAMPP session directory.
$sessionDir = __DIR__ . '/../.tmp/sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0777, true);
}
ini_set('session.save_path', $sessionDir);
session_start();
header('Content-Type: application/json');

$config = require __DIR__ . '/../config/config.php';

function db(): PDO
{
    static $pdo = null;
    global $config;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = $config['db'];
    $driver = strtolower(trim((string)($db['driver'] ?? 'mysql')));
    $host = (string)$db['host'];
    $port = trim((string)($db['port'] ?? ''));
    $charset = (string)($db['charset'] ?? 'utf8mb4');

    if ($driver === 'sqlsrv') {
        $server = $host;
        $instance = trim((string)($db['instance'] ?? ''));
        if ($instance !== '') {
            $server .= '\\' . $instance;
        }
        if ($port !== '') {
            $server .= ',' . $port;
        }
        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s;Encrypt=%s;TrustServerCertificate=%s',
            $server,
            $db['name'],
            $db['encrypt'],
            $db['trust_cert']
        );
    } else {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port !== '' ? $port : '3306',
            $db['name'],
            $charset
        );
    }

    $user = trim((string)($db['user'] ?? ''));
    $pass = (string)($db['pass'] ?? '');
    if ($user === '') {
        $user = null;
        $pass = null;
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function require_auth(?array $roles = null): array
{
    if (!isset($_SESSION['user'])) {
        respond(['ok' => false, 'message' => 'Unauthorized'], 401);
    }

    $user = $_SESSION['user'];
    if ($roles && !in_array($user['role'], $roles, true)) {
        respond(['ok' => false, 'message' => 'Forbidden'], 403);
    }
    return $user;
}

function safe_filename(string $name): string
{
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'file.bin';
    return substr($name, 0, 120);
}

function now_ts(): string
{
    return date('Y-m-d H:i:s');
}

function ensure_document_grades_table(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    global $config;
    $driver = strtolower(trim((string)($config['db']['driver'] ?? 'mysql')));

    if ($driver === 'sqlsrv') {
        db()->exec(
            "IF OBJECT_ID('document_grades', 'U') IS NULL
             BEGIN
                CREATE TABLE document_grades (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    document_id INT NOT NULL UNIQUE,
                    lecturer_id INT NOT NULL,
                    score INT NOT NULL CHECK (score BETWEEN 0 AND 100),
                    comment NVARCHAR(MAX) NULL,
                    graded_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
                    CONSTRAINT fk_doc_grade_document FOREIGN KEY (document_id) REFERENCES documents(id),
                    CONSTRAINT fk_doc_grade_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id)
                )
             END"
        );
    } else {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS document_grades (
                id INT AUTO_INCREMENT PRIMARY KEY,
                document_id INT NOT NULL UNIQUE,
                lecturer_id INT NOT NULL,
                score INT NOT NULL,
                comment TEXT NULL,
                graded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_doc_grade_document FOREIGN KEY (document_id) REFERENCES documents(id),
                CONSTRAINT fk_doc_grade_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id),
                CONSTRAINT chk_doc_grade_score CHECK (score BETWEEN 0 AND 100)
            ) ENGINE=InnoDB"
        );
    }

    $ready = true;
}

function ensure_announcements_table(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    global $config;
    $driver = strtolower(trim((string)($config['db']['driver'] ?? 'mysql')));

    if ($driver === 'sqlsrv') {
        db()->exec(
            "IF OBJECT_ID('announcements', 'U') IS NULL
             BEGIN
                CREATE TABLE announcements (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    lecturer_id INT NOT NULL,
                    title NVARCHAR(180) NOT NULL,
                    message NVARCHAR(MAX) NOT NULL,
                    due_date DATE NULL,
                    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
                    CONSTRAINT fk_announcement_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id)
                )
             END"
        );
    } else {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS announcements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                lecturer_id INT NOT NULL,
                title VARCHAR(180) NOT NULL,
                message TEXT NOT NULL,
                due_date DATE NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_announcement_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id)
            ) ENGINE=InnoDB"
        );
    }

    $ready = true;
}

function ensure_student_lecturer_assignment_columns(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    global $config;
    $driver = strtolower(trim((string)($config['db']['driver'] ?? 'mysql')));

    if ($driver === 'sqlsrv') {
        db()->exec(
            "IF COL_LENGTH('users', 'active_lecturer_id') IS NULL
             BEGIN
                ALTER TABLE users ADD active_lecturer_id INT NULL
             END"
        );
        db()->exec(
            "IF COL_LENGTH('placements', 'lecturer_id') IS NULL
             BEGIN
                ALTER TABLE placements ADD lecturer_id INT NULL
             END"
        );
    } else {
        $usersHasColumn = (bool)db()->query(
            "SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'active_lecturer_id'"
        )->fetchColumn();
        if (!$usersHasColumn) {
            db()->exec('ALTER TABLE users ADD COLUMN active_lecturer_id INT NULL');
        }

        $placementsHasColumn = (bool)db()->query(
            "SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'placements'
               AND COLUMN_NAME = 'lecturer_id'"
        )->fetchColumn();
        if (!$placementsHasColumn) {
            db()->exec('ALTER TABLE placements ADD COLUMN lecturer_id INT NULL');
        }
    }

    $ready = true;
}

function ensure_placement_city_column(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    global $config;
    $driver = strtolower(trim((string)($config['db']['driver'] ?? 'mysql')));

    if ($driver === 'sqlsrv') {
        db()->exec(
            "IF COL_LENGTH('placements', 'city') IS NULL
             BEGIN
                ALTER TABLE placements ADD city NVARCHAR(120) NULL
             END"
        );
    } else {
        $hasCityColumn = (bool)db()->query(
            "SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'placements'
               AND COLUMN_NAME = 'city'"
        )->fetchColumn();
        if (!$hasCityColumn) {
            db()->exec('ALTER TABLE placements ADD COLUMN city VARCHAR(120) NULL');
        }
    }

    $ready = true;
}

function ensure_user_program_column(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    global $config;
    $driver = strtolower(trim((string)($config['db']['driver'] ?? 'mysql')));

    if ($driver === 'sqlsrv') {
        db()->exec(
            "IF COL_LENGTH('users', 'program_id') IS NULL
             BEGIN
                ALTER TABLE users ADD program_id INT NULL
             END"
        );
    } else {
        $hasProgramColumn = (bool)db()->query(
            "SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'program_id'"
        )->fetchColumn();
        if (!$hasProgramColumn) {
            db()->exec('ALTER TABLE users ADD COLUMN program_id INT NULL');
        }
    }

    $ready = true;
}

function ensure_program_catalog_seeded(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo = db();
    $defaults = [
        ['department' => 'Information Systems', 'program' => 'BSc Information Systems'],
        ['department' => 'Computer Science', 'program' => 'BSc Computer Science'],
        ['department' => 'Accounting', 'program' => 'BCom Accounting'],
    ];

    $findDepartment = $pdo->prepare('SELECT id FROM departments WHERE name = ? LIMIT 1');
    $insertDepartment = $pdo->prepare('INSERT INTO departments (name) VALUES (?)');
    $findProgram = $pdo->prepare('SELECT id FROM programs WHERE name = ? LIMIT 1');
    $insertProgram = $pdo->prepare('INSERT INTO programs (department_id, name) VALUES (?, ?)');

    foreach ($defaults as $item) {
        $departmentName = (string)$item['department'];
        $programName = (string)$item['program'];

        $findDepartment->execute([$departmentName]);
        $departmentId = (int)$findDepartment->fetchColumn();
        if ($departmentId < 1) {
            $insertDepartment->execute([$departmentName]);
            $departmentId = (int)$pdo->lastInsertId();
        }

        $findProgram->execute([$programName]);
        $programId = (int)$findProgram->fetchColumn();
        if ($programId < 1) {
            $insertProgram->execute([$departmentId, $programName]);
        }
    }

    $ready = true;
}

function ensure_placement_department_column(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    global $config;
    $driver = strtolower(trim((string)($config['db']['driver'] ?? 'mysql')));

    if ($driver === 'sqlsrv') {
        db()->exec(
            "IF COL_LENGTH('placements', 'department') IS NULL
             BEGIN
                ALTER TABLE placements ADD department NVARCHAR(120) NULL
             END"
        );
    } else {
        $hasDepartmentColumn = (bool)db()->query(
            "SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'placements'
               AND COLUMN_NAME = 'department'"
        )->fetchColumn();
        if (!$hasDepartmentColumn) {
            db()->exec('ALTER TABLE placements ADD COLUMN department VARCHAR(120) NULL');
        }
    }

    $ready = true;
}

function ensure_feedback_score_range(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    global $config;
    $driver = strtolower(trim((string)($config['db']['driver'] ?? 'mysql')));

    if ($driver === 'sqlsrv') {
        $constraints = db()->query(
            "SELECT cc.name, cc.definition
             FROM sys.check_constraints cc
             INNER JOIN sys.tables t ON t.object_id = cc.parent_object_id
             WHERE t.name = 'feedback'
               AND LOWER(cc.definition) LIKE '%score%'
               AND LOWER(cc.definition) LIKE '%between%'"
        )->fetchAll();

        $alreadyValid = false;
        foreach ($constraints as $row) {
            $definition = strtolower(trim((string)($row['definition'] ?? '')));
            if ($definition !== '' && strpos($definition, 'score') !== false && strpos($definition, 'between') !== false && strpos($definition, '0') !== false && strpos($definition, '100') !== false) {
                $alreadyValid = true;
                break;
            }
        }
        if ($alreadyValid) {
            $ready = true;
            return;
        }

        foreach ($constraints as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name !== '') {
                db()->exec("ALTER TABLE feedback DROP CONSTRAINT [{$name}]");
            }
        }

        $hasRangeConstraint = (bool)db()->query(
            "SELECT COUNT(*) AS c
             FROM sys.check_constraints cc
             INNER JOIN sys.tables t ON t.object_id = cc.parent_object_id
             WHERE t.name = 'feedback'
               AND cc.name = 'chk_feedback_score_0_100'"
        )->fetchColumn();

        if (!$hasRangeConstraint) {
            db()->exec("ALTER TABLE feedback ADD CONSTRAINT chk_feedback_score_0_100 CHECK (score BETWEEN 0 AND 100)");
        }
    } else {
        $constraintStmt = db()->query(
            "SELECT tc.CONSTRAINT_NAME, cc.CHECK_CLAUSE
             FROM information_schema.TABLE_CONSTRAINTS tc
             INNER JOIN information_schema.CHECK_CONSTRAINTS cc
                 ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
                AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
             WHERE tc.TABLE_SCHEMA = DATABASE()
               AND tc.TABLE_NAME = 'feedback'
               AND tc.CONSTRAINT_TYPE = 'CHECK'
               AND LOWER(cc.CHECK_CLAUSE) LIKE '%score%'"
        );
        $constraints = $constraintStmt ? $constraintStmt->fetchAll() : [];

        $alreadyValid = false;
        foreach ($constraints as $row) {
            $clause = strtolower(trim((string)($row['CHECK_CLAUSE'] ?? '')));
            if ($clause !== '' && strpos($clause, 'score') !== false && strpos($clause, 'between') !== false && strpos($clause, '0') !== false && strpos($clause, '100') !== false) {
                $alreadyValid = true;
                break;
            }
        }
        if ($alreadyValid) {
            $ready = true;
            return;
        }

        foreach ($constraints as $row) {
            $name = trim((string)($row['CONSTRAINT_NAME'] ?? ''));
            if ($name !== '') {
                db()->exec("ALTER TABLE feedback DROP CHECK `{$name}`");
            }
        }

        db()->exec("ALTER TABLE feedback ADD CONSTRAINT chk_feedback_score_0_100 CHECK (score BETWEEN 0 AND 100)");
    }

    $ready = true;
}
