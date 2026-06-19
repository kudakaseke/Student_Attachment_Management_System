<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_auth(['student']);
$method = $_SERVER['REQUEST_METHOD'];
$studentId = (int)$user['id'];
ensure_document_grades_table();
ensure_announcements_table();
ensure_student_lecturer_assignment_columns();
ensure_placement_city_column();
ensure_placement_department_column();

if ($method === 'GET') {
    $stmt = db()->prepare(
        'SELECT p.id, p.status, p.company_name, p.company_address, p.supervisor_name, p.supervisor_email,
                p.supervisor_phone, p.department, p.city, p.start_date, p.end_date, p.notes, p.created_at, p.lecturer_id,
                l.first_name AS lecturer_first_name, l.last_name AS lecturer_last_name
         FROM placements p
         LEFT JOIN users l ON l.id = p.lecturer_id
         WHERE p.student_id = ?
         ORDER BY p.created_at DESC'
    );
    $stmt->execute([$studentId]);
    $placements = $stmt->fetchAll();

    $docsStmt = db()->prepare(
        'SELECT d.id, d.placement_id, d.document_type, d.file_name, d.uploaded_at,
                dg.score AS grade_score, dg.comment AS grade_comment, dg.graded_at,
                g.first_name AS graded_by_first_name, g.last_name AS graded_by_last_name
         FROM documents d
         LEFT JOIN document_grades dg ON dg.document_id = d.id
         LEFT JOIN users g ON g.id = dg.lecturer_id
         WHERE d.student_id = ?
         ORDER BY d.uploaded_at DESC'
    );
    $docsStmt->execute([$studentId]);

    $feedbackStmt = db()->prepare(
        'SELECT f.id, f.source_role, f.comment, f.score, f.created_at
         FROM feedback f
         WHERE f.student_id = ?
         ORDER BY f.created_at DESC'
    );
    $feedbackStmt->execute([$studentId]);

    $visitStmt = db()->prepare(
        'SELECT vr.id, vr.placement_id, vr.visit_date, vr.summary, vr.created_at,
                l.first_name AS lecturer_first_name, l.last_name AS lecturer_last_name
         FROM visit_reports vr
         INNER JOIN placements p ON p.id = vr.placement_id
         INNER JOIN users l ON l.id = vr.lecturer_id
         WHERE p.student_id = ?
         ORDER BY vr.created_at DESC'
    );
    $visitStmt->execute([$studentId]);

    $lecturerStmt = db()->query(
        "SELECT id, first_name, last_name, email
         FROM users
         WHERE role = 'lecturer' AND is_active = 1
         ORDER BY first_name ASC, last_name ASC"
    );

    $activeLecturerStmt = db()->prepare(
        'SELECT u.active_lecturer_id, l.first_name, l.last_name, l.email
         FROM users u
         LEFT JOIN users l ON l.id = u.active_lecturer_id
         WHERE u.id = ?'
    );
    $activeLecturerStmt->execute([$studentId]);
    $activeLecturer = $activeLecturerStmt->fetch() ?: null;

    $activeLecturerId = (int)($activeLecturer['active_lecturer_id'] ?? 0);
    if ($activeLecturerId > 0) {
        $noticeStmt = db()->prepare(
            'SELECT a.id, a.title, a.message, a.due_date, a.created_at,
                    u.first_name AS posted_by_first_name, u.last_name AS posted_by_last_name
             FROM announcements a
             INNER JOIN users u ON u.id = a.lecturer_id
             WHERE a.lecturer_id = ?
             ORDER BY a.created_at DESC'
        );
        $noticeStmt->execute([$activeLecturerId]);
        $notices = $noticeStmt->fetchAll();
    } else {
        $notices = [];
    }

    respond([
        'ok' => true,
        'placements' => $placements,
        'documents' => $docsStmt->fetchAll(),
        'visit_reports' => $visitStmt->fetchAll(),
        'notices' => $notices,
        'announcements' => $notices,
        'feedback' => $feedbackStmt->fetchAll(),
        'lecturers' => $lecturerStmt->fetchAll(),
        'active_lecturer' => $activeLecturer,
    ]);
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'placement') {
        $data = $_POST;
        $required = ['company_name', 'company_address', 'department', 'city', 'supervisor_name', 'supervisor_email', 'supervisor_phone', 'start_date', 'end_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                respond(['ok' => false, 'message' => "Missing field: {$field}"], 422);
            }
        }

        $requestedLecturerId = (int)($data['lecturer_id'] ?? 0);
        if ($requestedLecturerId < 1) {
            $activeStmt = db()->prepare('SELECT active_lecturer_id FROM users WHERE id = ?');
            $activeStmt->execute([$studentId]);
            $requestedLecturerId = (int)($activeStmt->fetchColumn() ?: 0);
        }
        if ($requestedLecturerId < 1) {
            respond(['ok' => false, 'message' => 'Select an active lecturer before submitting placement'], 422);
        }
        $lecturerCheck = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'lecturer' AND is_active = 1");
        $lecturerCheck->execute([$requestedLecturerId]);
        if (!$lecturerCheck->fetch()) {
            respond(['ok' => false, 'message' => 'Selected lecturer is invalid'], 422);
        }

        $stmt = db()->prepare(
            'INSERT INTO placements (student_id, company_name, company_address, department, city, supervisor_name, supervisor_email, supervisor_phone, start_date, end_date, notes, status, lecturer_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $studentId,
            trim((string)$data['company_name']),
            trim((string)$data['company_address']),
            trim((string)$data['department']),
            trim((string)$data['city']),
            trim((string)$data['supervisor_name']),
            trim((string)$data['supervisor_email']),
            trim((string)$data['supervisor_phone']),
            $data['start_date'],
            $data['end_date'],
            trim((string)($data['notes'] ?? '')),
            'pending',
            $requestedLecturerId,
        ]);
        respond(['ok' => true, 'message' => 'Placement submitted']);
    }

    if ($action === 'set_active_lecturer') {
        $lecturerId = (int)($_POST['lecturer_id'] ?? 0);
        if ($lecturerId < 1) {
            respond(['ok' => false, 'message' => 'lecturer_id is required'], 422);
        }
        $check = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'lecturer' AND is_active = 1");
        $check->execute([$lecturerId]);
        if (!$check->fetch()) {
            respond(['ok' => false, 'message' => 'Selected lecturer is invalid'], 422);
        }

        try {
            db()->beginTransaction();

            $stmt = db()->prepare('UPDATE users SET active_lecturer_id = ? WHERE id = ? AND role = ?');
            $stmt->execute([$lecturerId, $studentId, 'student']);

            // Keep lecturer dashboard in sync by re-assigning non-rejected placements for this student.
            $placementStmt = db()->prepare(
                "UPDATE placements
                 SET lecturer_id = ?
                 WHERE student_id = ?
                   AND (status IS NULL OR status <> 'rejected')"
            );
            $placementStmt->execute([$lecturerId, $studentId]);
            $affected = (int)$placementStmt->rowCount();

            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            respond(['ok' => false, 'message' => 'Failed to update active lecturer'], 500);
        }

        $message = $affected > 0
            ? "Active lecturer selected successfully. {$affected} placement(s) reassigned."
            : 'Active lecturer selected successfully.';
        respond(['ok' => true, 'message' => $message, 'updated_placements' => $affected]);
    }

    if ($action === 'document') {
        $type = trim((string)($_POST['document_type'] ?? ''));
        if ($type === '') {
            respond(['ok' => false, 'message' => 'document_type is required'], 422);
        }
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            respond(['ok' => false, 'message' => 'Valid file upload required'], 422);
        }
        if ($_FILES['file']['size'] > $config['max_file_size']) {
            respond(['ok' => false, 'message' => 'File too large'], 422);
        }

        global $config;
        $driver = strtolower(trim((string)($config['db']['driver'] ?? 'mysql')));
        $placementSql = "SELECT TOP 1 p.id
                         FROM placements p
                         WHERE p.student_id = ?
                         ORDER BY p.created_at DESC";
        if ($driver !== 'sqlsrv') {
            $placementSql = str_replace('SELECT TOP 1 p.id', 'SELECT p.id', $placementSql);
            $placementSql .= ' LIMIT 1';
        }
        $placementStmt = db()->prepare($placementSql);
        $placementStmt->execute([$studentId]);
        $placementId = (int)($placementStmt->fetchColumn() ?: 0);
        if ($placementId < 1) {
            respond(['ok' => false, 'message' => 'No placement found. Submit placement first.'], 404);
        }

        $original = safe_filename($_FILES['file']['name']);
        $stored = uniqid('doc_', true) . '_' . $original;
        $target = rtrim($config['upload_path'], '/\\') . DIRECTORY_SEPARATOR . $stored;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            respond(['ok' => false, 'message' => 'Failed to save file'], 500);
        }

        $stmt = db()->prepare(
            'SELECT id, file_path
             FROM documents
             WHERE placement_id = ? AND student_id = ? AND document_type = ?
             ORDER BY uploaded_at DESC
             LIMIT 1'
        );
        $stmt->execute([$placementId, $studentId, $type]);
        $existing = $stmt->fetch();

        if ($existing) {
            $oldStored = trim((string)($existing['file_path'] ?? ''));
            if ($oldStored !== '') {
                $oldTarget = rtrim($config['upload_path'], '/\\') . DIRECTORY_SEPARATOR . $oldStored;
                if (is_file($oldTarget)) {
                    @unlink($oldTarget);
                }
            }

            $updateStmt = db()->prepare(
                'UPDATE documents
                 SET file_name = ?, file_path = ?, uploaded_at = ?
                 WHERE id = ?'
            );
            $updateStmt->execute([$original, $stored, now_ts(), (int)$existing['id']]);
            respond(['ok' => true, 'message' => 'Document replaced successfully']);
        }

        $insertStmt = db()->prepare(
            'INSERT INTO documents (placement_id, student_id, document_type, file_name, file_path)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insertStmt->execute([$placementId, $studentId, $type, $original, $stored]);
        respond(['ok' => true, 'message' => 'Document uploaded successfully']);
    }

    if ($action === 'delete_document') {
        $documentId = (int)($_POST['document_id'] ?? 0);
        if ($documentId < 1) {
            respond(['ok' => false, 'message' => 'document_id is required'], 422);
        }

        $docStmt = db()->prepare(
            'SELECT d.id, d.file_path, dg.score AS grade_score
             FROM documents d
             LEFT JOIN document_grades dg ON dg.document_id = d.id
             WHERE d.id = ? AND d.student_id = ?'
        );
        $docStmt->execute([$documentId, $studentId]);
        $doc = $docStmt->fetch();
        if (!$doc) {
            respond(['ok' => false, 'message' => 'Document not found'], 404);
        }
        if (isset($doc['grade_score']) && $doc['grade_score'] !== null && trim((string)$doc['grade_score']) !== '') {
            respond(['ok' => false, 'message' => 'Graded documents cannot be deleted'], 422);
        }

        try {
            db()->beginTransaction();
            db()->prepare('DELETE FROM document_grades WHERE document_id = ?')->execute([$documentId]);
            $deleteStmt = db()->prepare('DELETE FROM documents WHERE id = ? AND student_id = ?');
            $deleteStmt->execute([$documentId, $studentId]);
            if ($deleteStmt->rowCount() < 1) {
                db()->rollBack();
                respond(['ok' => false, 'message' => 'Document not found'], 404);
            }
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            respond(['ok' => false, 'message' => 'Failed to delete document'], 500);
        }

        $stored = trim((string)($doc['file_path'] ?? ''));
        if ($stored !== '') {
            $target = rtrim($config['upload_path'], '/\\') . DIRECTORY_SEPARATOR . $stored;
            if (is_file($target)) {
                @unlink($target);
            }
        }

        respond(['ok' => true, 'message' => 'Document deleted successfully']);
    }

    respond(['ok' => false, 'message' => 'Unknown action'], 400);
}

respond(['ok' => false, 'message' => 'Method not allowed'], 405);
