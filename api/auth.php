<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
ensure_user_program_column();
ensure_program_catalog_seeded();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $lookup = strtolower(trim((string)($_GET['lookup'] ?? '')));
    if ($lookup === 'programs') {
        $stmt = db()->query(
            "SELECT id, name
             FROM programs
             ORDER BY name ASC"
        );
        respond(['ok' => true, 'programs' => $stmt->fetchAll()]);
    }
    if (!isset($_SESSION['user'])) {
        respond(['ok' => true, 'authenticated' => false]);
    }
    respond(['ok' => true, 'authenticated' => true, 'user' => $_SESSION['user']]);
}

if ($method === 'DELETE') {
    session_unset();
    session_destroy();
    respond(['ok' => true, 'message' => 'Logged out']);
}

if ($method !== 'POST') {
    respond(['ok' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_input();
$action = strtolower(trim((string)($data['action'] ?? 'login')));
$role = strtolower(trim((string)($data['role'] ?? '')));
$identifier = trim((string)($data['identifier'] ?? ''));
$password = (string)($data['password'] ?? '');
$firstName = trim((string)($data['first_name'] ?? ''));
$lastName = trim((string)($data['last_name'] ?? ''));
$programId = (int)($data['program_id'] ?? 0);
$newPassword = (string)($data['new_password'] ?? '');

$validRoles = ['student', 'lecturer', 'supervisor', 'admin'];
if (!in_array($role, $validRoles, true)) {
    respond(['ok' => false, 'message' => 'Invalid role'], 422);
}

if ($action === 'create_account') {
    if (!in_array($role, ['student', 'lecturer', 'supervisor', 'admin'], true)) {
        respond(['ok' => false, 'message' => 'Invalid role for account creation'], 422);
    }
    if ($firstName === '' || $lastName === '') {
        respond(['ok' => false, 'message' => 'First name and last name are required'], 422);
    }

    if ($role === 'student') {
        if (!preg_match('/^r\d{6}[a-z]$/i', $identifier)) {
            respond(['ok' => false, 'message' => 'Use valid registration number format: r + 6 digits + letter, e.g. r218270v'], 422);
        }
        if ($password === '') {
            respond(['ok' => false, 'message' => 'Password is required for student account'], 422);
        }
        if ($programId < 1) {
            respond(['ok' => false, 'message' => 'Programme is required for student account'], 422);
        }
        $programCheck = db()->prepare('SELECT id FROM programs WHERE id = ?');
        $programCheck->execute([$programId]);
        if (!$programCheck->fetch()) {
            respond(['ok' => false, 'message' => 'Selected programme is invalid'], 422);
        }
        $exists = db()->prepare('SELECT id FROM users WHERE role = ? AND LOWER(reg_number) = LOWER(?)');
        $exists->execute([$role, $identifier]);
        if ($exists->fetch()) {
            respond(['ok' => false, 'message' => 'Student account already exists'], 409);
        }
        $stmt = db()->prepare(
            'INSERT INTO users (role, reg_number, password_hash, first_name, last_name, program_id, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([$role, $identifier, password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName, $programId]);
        respond(['ok' => true, 'message' => 'Account successfully created. You can now sign in.']);
    }

    if ($identifier === '' || $password === '') {
        respond(['ok' => false, 'message' => 'Email and password are required'], 422);
    }
    if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        respond(['ok' => false, 'message' => 'Provide a valid email address'], 422);
    }
    $exists = db()->prepare('SELECT id FROM users WHERE role = ? AND LOWER(email) = LOWER(?)');
    $exists->execute([$role, $identifier]);
    if ($exists->fetch()) {
        respond(['ok' => false, 'message' => 'Account already exists for this role and email'], 409);
    }
    $stmt = db()->prepare(
        'INSERT INTO users (role, email, password_hash, first_name, last_name, is_active)
         VALUES (?, ?, ?, ?, ?, 1)'
    );
    $stmt->execute([$role, $identifier, password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName]);
    respond(['ok' => true, 'message' => 'Account successfully created. You can now sign in.']);
}

if ($action === 'forgot_password') {
    if ($newPassword === '') {
        respond(['ok' => false, 'message' => 'New password is required'], 422);
    }
    if ($role === 'student') {
        if (!preg_match('/^r\d{6}[a-z]$/i', $identifier)) {
            respond(['ok' => false, 'message' => 'Use valid registration number format: r + 6 digits + letter, e.g. r218270v'], 422);
        }
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE role = ? AND LOWER(reg_number) = LOWER(?) AND is_active = 1');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $role, $identifier]);
    } else {
        if ($identifier === '') {
            respond(['ok' => false, 'message' => 'Email is required'], 422);
        }
        if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            respond(['ok' => false, 'message' => 'Provide a valid email address'], 422);
        }
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE role = ? AND LOWER(email) = LOWER(?) AND is_active = 1');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $role, $identifier]);
    }
    if ($stmt->rowCount() < 1) {
        respond(['ok' => false, 'message' => 'Account not found'], 404);
    }
    respond(['ok' => true, 'message' => 'Password reset successful. Login with your new password.']);
}

if ($role === 'student') {
    if (!preg_match('/^r\d{6}[a-z]$/i', $identifier)) {
        respond(['ok' => false, 'message' => 'Use valid registration number format: r + 6 digits + letter, e.g. r218270v'], 422);
    }
    if ($password === '') {
        respond(['ok' => false, 'message' => 'Password is required for student login'], 422);
    }
    $stmt = db()->prepare(
        'SELECT u.id, u.role, u.reg_number, u.first_name, u.last_name, u.password_hash, d.name AS department, p.name AS program
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         LEFT JOIN programs p ON p.id = u.program_id
         WHERE u.role = ? AND LOWER(u.reg_number) = LOWER(?) AND u.is_active = 1'
    );
    $stmt->execute([$role, $identifier]);
    $candidate = $stmt->fetch();
    $hash = (string)($candidate['password_hash'] ?? '');
    $valid = false;
    if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$') || str_starts_with($hash, '$2b$')) {
        $valid = password_verify($password, $hash);
    } else {
        $valid = ($hash !== '' && hash_equals($hash, $password));
    }
    if (!$candidate || !$valid) {
        respond(['ok' => false, 'message' => 'Invalid credentials'], 401);
    }
    unset($candidate['password_hash']);
    $user = $candidate;
} else {
    if ($identifier === '' || $password === '') {
        respond(['ok' => false, 'message' => 'Email and password are required'], 422);
    }
    if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        respond(['ok' => false, 'message' => 'Lecturer, supervisor and admin login require an email address'], 422);
    }
    $stmt = db()->prepare(
        'SELECT u.id, u.role, u.email, u.first_name, u.last_name, u.password_hash, d.name AS department, p.name AS program
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         LEFT JOIN programs p ON p.id = u.program_id
         WHERE u.role = ? AND LOWER(u.email) = LOWER(?) AND u.is_active = 1'
    );
    $stmt->execute([$role, $identifier]);
    $candidate = $stmt->fetch();
    $hash = (string)($candidate['password_hash'] ?? '');
    $hashTrimmed = trim($hash);
    $valid = false;
    if ($candidate && $hashTrimmed === '') {
        respond(['ok' => false, 'message' => 'Account has no password set. Use Forgot Password to set one.'], 422);
    }
    if (str_starts_with($hashTrimmed, '$2y$') || str_starts_with($hashTrimmed, '$2a$') || str_starts_with($hashTrimmed, '$2b$')) {
        $valid = password_verify($password, $hashTrimmed);
    } else {
        $valid = ($hash !== '' && hash_equals($hash, $password))
            || ($hashTrimmed !== '' && hash_equals($hashTrimmed, $password));
    }
    if (!$candidate || !$valid) {
        respond(['ok' => false, 'message' => 'Invalid credentials'], 401);
    }
    unset($candidate['password_hash']);
    $user = $candidate;
}

if (!$user) {
    respond(['ok' => false, 'message' => 'Invalid credentials'], 401);
}

$_SESSION['user'] = $user;
respond(['ok' => true, 'user' => $user]);
