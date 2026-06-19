<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

require_auth(['admin']);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $entity = strtolower(trim((string)($_GET['entity'] ?? 'dashboard')));

    if ($entity === 'dashboard') {
        $summary = [
            'students_on_attachment' => (int)db()->query("SELECT COUNT(*) AS c FROM placements WHERE status IN ('approved','confirmed','active')")->fetch()['c'],
            'pending_approvals' => (int)db()->query("SELECT COUNT(*) AS c FROM placements WHERE status = 'pending'")->fetch()['c'],
            'total_companies' => (int)db()->query("SELECT COUNT(*) AS c FROM companies")->fetch()['c'],
            'total_users' => (int)db()->query("SELECT COUNT(*) AS c FROM users WHERE is_active = 1")->fetch()['c'],
        ];
        respond(['ok' => true, 'summary' => $summary]);
    }

    if ($entity === 'users') {
        $rows = db()->query(
            'SELECT u.id, u.role, u.reg_number, u.email, u.first_name, u.last_name, u.is_active,
                    d.name AS department,
                    COALESCE(
                        c.name,
                        (
                            SELECT MAX(p.company_name)
                            FROM placements p
                            WHERE p.student_id = u.id
                              AND p.company_name IS NOT NULL
                              AND p.company_name <> \'\'
                        )
                    ) AS company,
                    c.contact_phone AS company_phone
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN companies c ON c.id = u.company_id
             ORDER BY u.created_at DESC'
        )->fetchAll();
        respond(['ok' => true, 'users' => $rows]);
    }

    if ($entity === 'departments') {
        $rows = db()->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
        respond(['ok' => true, 'departments' => $rows]);
    }

    if ($entity === 'programs') {
        $rows = db()->query(
            'SELECT p.id, p.name, d.name AS department
             FROM programs p INNER JOIN departments d ON d.id = p.department_id
             ORDER BY p.name'
        )->fetchAll();
        respond(['ok' => true, 'programs' => $rows]);
    }

    if ($entity === 'companies') {
        $companies = db()->query(
            'SELECT id, name, address, contact_name, contact_email, contact_phone
             FROM companies
             ORDER BY name'
        )->fetchAll();

        $placementRows = db()->query(
            'SELECT company_name, company_address
             FROM placements
             WHERE company_name IS NOT NULL AND company_name <> \'\''
        )->fetchAll();

        $result = [];
        $knownCompanyKeys = [];
        foreach ($companies as $company) {
            $name = trim((string)($company['name'] ?? ''));
            $key = strtolower($name);
            if ($key !== '') {
                $knownCompanyKeys[$key] = true;
            }
            $result[] = [
                'id' => $company['id'] ?? '',
                'name' => $name,
                'address' => (string)($company['address'] ?? ''),
                'contact_name' => (string)($company['contact_name'] ?? ''),
                'contact_email' => (string)($company['contact_email'] ?? ''),
                'contact_phone' => (string)($company['contact_phone'] ?? ''),
                'source' => 'registry',
            ];
        }

        $placementByName = [];
        foreach ($placementRows as $placement) {
            $name = trim((string)($placement['company_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = strtolower($name);
            if (isset($knownCompanyKeys[$key]) || isset($placementByName[$key])) {
                continue;
            }
            $placementByName[$key] = [
                'id' => '',
                'name' => $name,
                'address' => trim((string)($placement['company_address'] ?? '')),
                'contact_name' => '',
                'contact_email' => '',
                'contact_phone' => '',
                'source' => 'student_placement',
            ];
        }

        $result = array_merge($result, array_values($placementByName));
        usort($result, static function (array $a, array $b): int {
            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        respond(['ok' => true, 'companies' => $result]);
    }

    if ($entity === 'report') {
        $type = strtolower(trim((string)($_GET['type'] ?? 'students_on_attachment')));
        if (!in_array($type, ['students_on_attachment', 'pending_approvals'], true)) {
            respond(['ok' => false, 'message' => 'Invalid report type'], 422);
        }
        $statusFilter = $type === 'students_on_attachment'
            ? "IN ('approved','confirmed','active')"
            : "= 'pending'";
        $stmt = db()->query(
            "SELECT s.reg_number, s.first_name, s.last_name, p.company_name, p.status, p.start_date, p.end_date
             FROM placements p
             INNER JOIN users s ON s.id = p.student_id
             WHERE p.status {$statusFilter}
             ORDER BY s.reg_number"
        );
        $rows = $stmt->fetchAll();
        respond(['ok' => true, 'report_type' => $type, 'rows' => $rows]);
    }
}

if ($method === 'POST') {
    $data = json_input();
    $entity = strtolower(trim((string)($data['entity'] ?? '')));

    if ($entity === 'reset_password') {
        $userId = (int)($data['user_id'] ?? 0);
        if ($userId < 1) {
            respond(['ok' => false, 'message' => 'user_id is required'], 422);
        }

        $userStmt = db()->prepare('SELECT id, role, first_name, last_name, is_active FROM users WHERE id = ?');
        $userStmt->execute([$userId]);
        $targetUser = $userStmt->fetch();
        if (!$targetUser) {
            respond(['ok' => false, 'message' => 'User not found'], 404);
        }

        $role = strtolower(trim((string)($targetUser['role'] ?? '')));
        if (!in_array($role, ['student', 'lecturer', 'supervisor'], true)) {
            respond(['ok' => false, 'message' => 'Password reset is only allowed for student, lecturer, and supervisor'], 422);
        }
        if ((int)($targetUser['is_active'] ?? 0) !== 1) {
            respond(['ok' => false, 'message' => 'Cannot reset password for inactive user'], 422);
        }

        $firstName = trim((string)($targetUser['first_name'] ?? ''));
        $tempPassword = ($firstName !== '' ? $firstName : 'User') . '123';

        $updateStmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $updateStmt->execute([password_hash($tempPassword, PASSWORD_DEFAULT), $userId]);

        $fullName = trim((string)($targetUser['first_name'] ?? '') . ' ' . (string)($targetUser['last_name'] ?? ''));
        $label = $fullName !== '' ? $fullName : ('User #' . $userId);
        respond([
            'ok' => true,
            'message' => "Temporary password generated for {$label}",
            'temporary_password' => $tempPassword,
            'user_id' => $userId,
            'role' => $role,
        ]);
    }

    if ($entity === 'department') {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            respond(['ok' => false, 'message' => 'Department name required'], 422);
        }
        db()->prepare('INSERT INTO departments (name) VALUES (?)')->execute([$name]);
        respond(['ok' => true, 'message' => 'Department created']);
    }

    if ($entity === 'program') {
        $name = trim((string)($data['name'] ?? ''));
        $departmentId = (int)($data['department_id'] ?? 0);
        if ($name === '' || $departmentId < 1) {
            respond(['ok' => false, 'message' => 'Program name and department_id required'], 422);
        }
        db()->prepare('INSERT INTO programs (department_id, name) VALUES (?, ?)')->execute([$departmentId, $name]);
        respond(['ok' => true, 'message' => 'Program created']);
    }

    if ($entity === 'company') {
        $required = ['name', 'address', 'contact_name', 'contact_email', 'contact_phone'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                respond(['ok' => false, 'message' => "Missing field: {$field}"], 422);
            }
        }
        db()->prepare(
            'INSERT INTO companies (name, address, contact_name, contact_email, contact_phone) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            trim((string)$data['name']),
            trim((string)$data['address']),
            trim((string)$data['contact_name']),
            trim((string)$data['contact_email']),
            trim((string)$data['contact_phone']),
        ]);
        respond(['ok' => true, 'message' => 'Company created']);
    }

    if ($entity === 'user') {
        $role = strtolower(trim((string)($data['role'] ?? '')));
        $first = trim((string)($data['first_name'] ?? ''));
        $last = trim((string)($data['last_name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $reg = trim((string)($data['reg_number'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $departmentId = (int)($data['department_id'] ?? 0);
        $companyId = (int)($data['company_id'] ?? 0);

        if (!in_array($role, ['student', 'lecturer', 'supervisor', 'admin'], true) || $first === '' || $last === '') {
            respond(['ok' => false, 'message' => 'Invalid user payload'], 422);
        }
        if ($role === 'student' && !preg_match('/^r\d{6}[a-z]$/i', $reg)) {
            respond(['ok' => false, 'message' => 'Student reg_number must be r + 6 digits + letter (e.g. r218270v)'], 422);
        }
        if ($password === '') {
            respond(['ok' => false, 'message' => 'Password is required for all new users'], 422);
        }
        if ($role !== 'student' && $email === '') {
            respond(['ok' => false, 'message' => 'Email is required for non-students'], 422);
        }
        if ($role !== 'student' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(['ok' => false, 'message' => 'Provide a valid email address for non-students'], 422);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        db()->prepare(
            'INSERT INTO users (role, reg_number, email, password_hash, first_name, last_name, department_id, company_id)
             VALUES (?, NULLIF(?, \'\'), NULLIF(?, \'\'), ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0))'
        )->execute([$role, $reg, $email, $hash, $first, $last, $departmentId, $companyId]);

        respond(['ok' => true, 'message' => 'User created']);
    }

    if ($entity === 'placement') {
        $regNumber = trim((string)($data['reg_number'] ?? ''));
        $companyName = trim((string)($data['company_name'] ?? ''));
        $companyAddress = trim((string)($data['company_address'] ?? ''));
        $department = trim((string)($data['department'] ?? ''));
        $city = trim((string)($data['city'] ?? ''));
        $supervisorName = trim((string)($data['supervisor_name'] ?? ''));
        $supervisorEmail = trim((string)($data['supervisor_email'] ?? ''));
        $supervisorPhone = trim((string)($data['supervisor_phone'] ?? ''));
        $startDate = trim((string)($data['start_date'] ?? ''));
        $endDate = trim((string)($data['end_date'] ?? ''));
        $notes = trim((string)($data['notes'] ?? ''));
        $lecturerId = (int)($data['lecturer_id'] ?? 0);

        if (!preg_match('/^r\d{6}[a-z]$/i', $regNumber)) {
            respond(['ok' => false, 'message' => 'Valid student reg_number is required'], 422);
        }
        $required = [
            'company_name' => $companyName,
            'company_address' => $companyAddress,
            'department' => $department,
            'city' => $city,
            'supervisor_name' => $supervisorName,
            'supervisor_email' => $supervisorEmail,
            'supervisor_phone' => $supervisorPhone,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
        foreach ($required as $field => $value) {
            if ($value === '') {
                respond(['ok' => false, 'message' => "Missing field: {$field}"], 422);
            }
        }
        if (!filter_var($supervisorEmail, FILTER_VALIDATE_EMAIL)) {
            respond(['ok' => false, 'message' => 'Supervisor email is invalid'], 422);
        }

        $studentStmt = db()->prepare(
            "SELECT id, active_lecturer_id
             FROM users
             WHERE role = 'student' AND LOWER(reg_number) = LOWER(?) AND is_active = 1"
        );
        $studentStmt->execute([$regNumber]);
        $student = $studentStmt->fetch();
        if (!$student) {
            respond(['ok' => false, 'message' => 'Student not found'], 404);
        }
        $studentId = (int)$student['id'];

        if ($lecturerId < 1) {
            $lecturerId = (int)($student['active_lecturer_id'] ?? 0);
        }
        if ($lecturerId > 0) {
            $lecturerCheck = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'lecturer' AND is_active = 1");
            $lecturerCheck->execute([$lecturerId]);
            if (!$lecturerCheck->fetch()) {
                respond(['ok' => false, 'message' => 'Selected lecturer is invalid'], 422);
            }
        } else {
            $lecturerId = null;
        }

        $insertStmt = db()->prepare(
            'INSERT INTO placements
             (student_id, company_name, company_address, department, city, supervisor_name, supervisor_email, supervisor_phone, start_date, end_date, notes, status, lecturer_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertStmt->execute([
            $studentId,
            $companyName,
            $companyAddress,
            $department,
            $city,
            $supervisorName,
            $supervisorEmail,
            $supervisorPhone,
            $startDate,
            $endDate,
            $notes,
            'pending',
            $lecturerId,
        ]);

        respond(['ok' => true, 'message' => 'Placement added successfully']);
    }

    respond(['ok' => false, 'message' => 'Unknown entity'], 400);
}

respond(['ok' => false, 'message' => 'Method not allowed'], 405);
