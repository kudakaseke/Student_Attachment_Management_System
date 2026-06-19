<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_auth(['lecturer', 'admin']);
$method = $_SERVER['REQUEST_METHOD'];
ensure_document_grades_table();
ensure_announcements_table();
ensure_student_lecturer_assignment_columns();
ensure_placement_city_column();
ensure_placement_department_column();

if ($method === 'GET') {
    if ($user['role'] === 'lecturer') {
        // Heal stale assignments: if a student selected this lecturer as active, map their non-rejected placements here.
        $syncStmt = db()->prepare(
            "UPDATE placements
             SET lecturer_id = ?
             WHERE student_id IN (
                 SELECT id
                 FROM users
                 WHERE role = 'student' AND active_lecturer_id = ?
             )
               AND (status IS NULL OR status <> 'rejected')
               AND (lecturer_id IS NULL OR lecturer_id <> ?)"
        );
        $syncStmt->execute([$user['id'], $user['id'], $user['id']]);
    }

    $placementsSql = "SELECT p.id, p.student_id, p.status, p.company_name, p.company_address, p.department AS placement_department, p.city, p.start_date, p.end_date, p.created_at,
                             p.lecturer_id,
                             u.reg_number, u.first_name, u.last_name, dep.name AS department,
                             l.first_name AS lecturer_first_name, l.last_name AS lecturer_last_name
                      FROM placements p
                      INNER JOIN users u ON u.id = p.student_id
                      LEFT JOIN departments dep ON dep.id = u.department_id
                      LEFT JOIN users l ON l.id = p.lecturer_id";
    if ($user['role'] === 'lecturer') {
        $placementsSql .= " WHERE (p.lecturer_id = ? OR u.active_lecturer_id = ?)";
    }
    $placementsSql .= " ORDER BY p.created_at DESC";
    $stmt = db()->prepare($placementsSql);
    if ($user['role'] === 'lecturer') {
        $stmt->execute([$user['id'], $user['id']]);
    } else {
        $stmt->execute();
    }

    $documentsSql = "SELECT d.id, d.placement_id, d.document_type, d.file_name, d.file_path, d.uploaded_at,
                            u.reg_number, u.first_name, u.last_name,
                            dg.score AS grade_score, dg.comment AS grade_comment, dg.graded_at,
                            g.first_name AS graded_by_first_name, g.last_name AS graded_by_last_name
                     FROM documents d
                     INNER JOIN users u ON u.id = d.student_id
                     INNER JOIN placements p ON p.id = d.placement_id
                     LEFT JOIN document_grades dg ON dg.document_id = d.id
                     LEFT JOIN users g ON g.id = dg.lecturer_id";
    if ($user['role'] === 'lecturer') {
        $documentsSql .= " WHERE (p.lecturer_id = ? OR u.active_lecturer_id = ?)";
    }
    $documentsSql .= " ORDER BY d.uploaded_at DESC";
    $docStmt = db()->prepare($documentsSql);
    if ($user['role'] === 'lecturer') {
        $docStmt->execute([$user['id'], $user['id']]);
    } else {
        $docStmt->execute();
    }

    $visitsSql = 'SELECT vr.id, vr.placement_id, vr.visit_date, vr.summary, vr.created_at,
                         u.first_name, u.last_name,
                         s.reg_number, s.first_name AS student_first_name, s.last_name AS student_last_name
                  FROM visit_reports vr
                  INNER JOIN users u ON u.id = vr.lecturer_id
                  INNER JOIN placements p ON p.id = vr.placement_id
                  INNER JOIN users s ON s.id = p.student_id';
    if ($user['role'] === 'lecturer') {
        $visitsSql .= ' WHERE vr.lecturer_id = ?';
    }
    $visitsSql .= ' ORDER BY vr.created_at DESC';
    $visitStmt = db()->prepare($visitsSql);
    if ($user['role'] === 'lecturer') {
        $visitStmt->execute([$user['id']]);
    } else {
        $visitStmt->execute();
    }

    $announcementStmt = db()->prepare(
        "SELECT a.id, a.title, a.message, a.due_date, a.created_at,
                u.first_name AS posted_by_first_name, u.last_name AS posted_by_last_name
         FROM announcements a
         INNER JOIN users u ON u.id = a.lecturer_id
         WHERE a.lecturer_id = ? OR ? = 'admin'
         ORDER BY a.created_at DESC"
    );
    $announcementStmt->execute([$user['id'], $user['role']]);

    $assignedStudentsSql = "SELECT s.id, s.reg_number, s.first_name, s.last_name, dep.name AS department,
                                   p.id AS latest_placement_id, p.status AS latest_placement_status,
                                   p.company_name AS latest_company_name, p.created_at AS latest_placement_created_at
                            FROM users s
                            LEFT JOIN departments dep ON dep.id = s.department_id
                            LEFT JOIN placements p ON p.id = (
                                SELECT TOP 1 p2.id
                                FROM placements p2
                                WHERE p2.student_id = s.id
                                ORDER BY p2.created_at DESC
                            )
                            WHERE s.role = 'student' AND s.active_lecturer_id = ?";
    if ($user['role'] !== 'lecturer') {
        $assignedStudentsSql = "SELECT s.id, s.reg_number, s.first_name, s.last_name, dep.name AS department,
                                       l.first_name AS lecturer_first_name, l.last_name AS lecturer_last_name,
                                       p.id AS latest_placement_id, p.status AS latest_placement_status,
                                       p.company_name AS latest_company_name, p.created_at AS latest_placement_created_at
                                FROM users s
                                LEFT JOIN departments dep ON dep.id = s.department_id
                                LEFT JOIN users l ON l.id = s.active_lecturer_id
                                LEFT JOIN placements p ON p.id = (
                                    SELECT TOP 1 p2.id
                                    FROM placements p2
                                    WHERE p2.student_id = s.id
                                    ORDER BY p2.created_at DESC
                                )
                                WHERE s.role = 'student' AND s.active_lecturer_id IS NOT NULL";
    }

    global $config;
    $driver = strtolower(trim((string)($config['db']['driver'] ?? 'mysql')));
    if ($driver !== 'sqlsrv') {
        $assignedStudentsSql = str_replace('SELECT TOP 1 p2.id', 'SELECT p2.id', $assignedStudentsSql);
        $assignedStudentsSql = str_replace('ORDER BY p2.created_at DESC', 'ORDER BY p2.created_at DESC LIMIT 1', $assignedStudentsSql);
    }

    $assignedStudentsSql .= ' ORDER BY s.first_name ASC, s.last_name ASC';
    $assignedStmt = db()->prepare($assignedStudentsSql);
    if ($user['role'] === 'lecturer') {
        $assignedStmt->execute([$user['id']]);
    } else {
        $assignedStmt->execute();
    }

    respond([
        'ok' => true,
        'placements' => $stmt->fetchAll(),
        'documents' => $docStmt->fetchAll(),
        'visits' => $visitStmt->fetchAll(),
        'announcements' => $announcementStmt->fetchAll(),
        'assigned_students' => $assignedStmt->fetchAll(),
    ]);
}

if ($method === 'POST') {
    $data = json_input();
    $action = strtolower(trim((string)($data['action'] ?? '')));

    if ($action === 'review_placement') {
        $placementId = (int)($data['placement_id'] ?? 0);
        $regNumber = trim((string)($data['reg_number'] ?? ''));
        $status = strtolower(trim((string)($data['status'] ?? '')));
        $comment = trim((string)($data['comment'] ?? ''));

        if ($regNumber !== '') {
            global $config;
            $driver = strtolower(trim((string)($config['db']['driver'] ?? 'mysql')));
            if ($user['role'] === 'lecturer') {
                $lookupSql = "SELECT TOP 1 p.id
                              FROM placements p
                              INNER JOIN users s ON s.id = p.student_id
                              WHERE LOWER(s.reg_number) = LOWER(?)
                                AND (p.lecturer_id = ? OR s.active_lecturer_id = ?)
                              ORDER BY p.created_at DESC";
                if ($driver !== 'sqlsrv') {
                    $lookupSql = str_replace('SELECT TOP 1 p.id', 'SELECT p.id', $lookupSql);
                    $lookupSql .= ' LIMIT 1';
                }
                $lookupStmt = db()->prepare($lookupSql);
                $lookupStmt->execute([$regNumber, $user['id'], $user['id']]);
            } else {
                $lookupSql = "SELECT TOP 1 p.id
                              FROM placements p
                              INNER JOIN users s ON s.id = p.student_id
                              WHERE LOWER(s.reg_number) = LOWER(?)
                              ORDER BY p.created_at DESC";
                if ($driver !== 'sqlsrv') {
                    $lookupSql = str_replace('SELECT TOP 1 p.id', 'SELECT p.id', $lookupSql);
                    $lookupSql .= ' LIMIT 1';
                }
                $lookupStmt = db()->prepare($lookupSql);
                $lookupStmt->execute([$regNumber]);
            }
            $resolvedPlacement = $lookupStmt->fetch();
            if (!$resolvedPlacement) {
                respond(['ok' => false, 'message' => 'No placement found for that registration number'], 404);
            }
            $placementId = (int)($resolvedPlacement['id'] ?? 0);
        }

        if ($placementId < 1 || !in_array($status, ['approved', 'rejected'], true)) {
            respond(['ok' => false, 'message' => 'reg_number and status(approved/rejected) required'], 422);
        }
        if ($user['role'] === 'lecturer') {
            $assignmentCheck = db()->prepare(
                'SELECT p.id
                 FROM placements p
                 INNER JOIN users s ON s.id = p.student_id
                 WHERE p.id = ? AND (p.lecturer_id = ? OR s.active_lecturer_id = ?)'
            );
            $assignmentCheck->execute([$placementId, $user['id'], $user['id']]);
            if (!$assignmentCheck->fetch()) {
                respond(['ok' => false, 'message' => 'Placement is not assigned to you'], 403);
            }
        }

        $stmt = db()->prepare('UPDATE placements SET status = ?, lecturer_comment = ?, reviewed_at = ?, reviewed_by = ? WHERE id = ?');
        $stmt->execute([$status, $comment, now_ts(), $user['id'], $placementId]);
        respond(['ok' => true, 'message' => 'Placement updated']);
    }

    if ($action === 'visit_report') {
        global $config;
        $driver = strtolower(trim((string)($config['db']['driver'] ?? 'mysql')));
        $regNumber = trim((string)($data['reg_number'] ?? ''));
        $placementId = (int)($data['placement_id'] ?? 0);
        $visitDate = (string)($data['visit_date'] ?? '');
        $summary = trim((string)($data['summary'] ?? ''));
        if (($regNumber === '' && $placementId < 1) || $visitDate === '' || $summary === '') {
            respond(['ok' => false, 'message' => 'reg_number, visit_date, summary required'], 422);
        }

        if ($regNumber !== '') {
            $resolveSql = "SELECT TOP 1 p.id
                           FROM placements p
                           INNER JOIN users s ON s.id = p.student_id
                           WHERE LOWER(s.reg_number) = LOWER(?)";
            $resolveParams = [$regNumber];
            if ($user['role'] === 'lecturer') {
                $resolveSql .= " AND (p.lecturer_id = ? OR s.active_lecturer_id = ?)";
                $resolveParams[] = $user['id'];
                $resolveParams[] = $user['id'];
            }
            $resolveSql .= " ORDER BY p.created_at DESC";
            if ($driver !== 'sqlsrv') {
                $resolveSql = str_replace('SELECT TOP 1 p.id', 'SELECT p.id', $resolveSql);
                $resolveSql .= ' LIMIT 1';
            }
            $resolveStmt = db()->prepare($resolveSql);
            $resolveStmt->execute($resolveParams);
            $placementId = (int)($resolveStmt->fetchColumn() ?: 0);
            if ($placementId < 1) {
                respond(['ok' => false, 'message' => 'No placement found for the provided reg number'], 404);
            }
        }

        if ($user['role'] === 'lecturer') {
            $assignmentCheck = db()->prepare(
                'SELECT p.id
                 FROM placements p
                 INNER JOIN users s ON s.id = p.student_id
                 WHERE p.id = ? AND (p.lecturer_id = ? OR s.active_lecturer_id = ?)'
            );
            $assignmentCheck->execute([$placementId, $user['id'], $user['id']]);
            if (!$assignmentCheck->fetch()) {
                respond(['ok' => false, 'message' => 'Placement is not assigned to you'], 403);
            }
        }
        $stmt = db()->prepare('INSERT INTO visit_reports (placement_id, lecturer_id, visit_date, summary) VALUES (?, ?, ?, ?)');
        $stmt->execute([$placementId, $user['id'], $visitDate, $summary]);
        respond(['ok' => true, 'message' => 'Visit report saved']);
    }

    if ($action === 'grade_document') {
        $documentId = (int)($data['document_id'] ?? 0);
        $score = (int)($data['score'] ?? -1);
        $comment = trim((string)($data['comment'] ?? ''));
        if ($documentId < 1 || $score < 0 || $score > 100) {
            respond(['ok' => false, 'message' => 'document_id and score(0-100) required'], 422);
        }

        if ($user['role'] === 'lecturer') {
            $check = db()->prepare(
                'SELECT d.id
                 FROM documents d
                 INNER JOIN placements p ON p.id = d.placement_id
                 INNER JOIN users s ON s.id = p.student_id
                 WHERE d.id = ? AND (p.lecturer_id = ? OR s.active_lecturer_id = ?)'
            );
            $check->execute([$documentId, $user['id'], $user['id']]);
        } else {
            $check = db()->prepare('SELECT id FROM documents WHERE id = ?');
            $check->execute([$documentId]);
        }
        if (!$check->fetch()) {
            respond(['ok' => false, 'message' => 'Document not found or not assigned to you'], 404);
        }

        global $config;
        $driver = strtolower(trim((string)($config['db']['driver'] ?? 'mysql')));
        if ($driver === 'sqlsrv') {
            $existsStmt = db()->prepare('SELECT id FROM document_grades WHERE document_id = ?');
            $existsStmt->execute([$documentId]);
            if ($existsStmt->fetch()) {
                $stmt = db()->prepare(
                    'UPDATE document_grades
                     SET lecturer_id = ?, score = ?, comment = ?, graded_at = ?
                     WHERE document_id = ?'
                );
                $stmt->execute([$user['id'], $score, $comment, now_ts(), $documentId]);
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO document_grades (document_id, lecturer_id, score, comment, graded_at)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$documentId, $user['id'], $score, $comment, now_ts()]);
            }
        } else {
            $stmt = db()->prepare(
                'INSERT INTO document_grades (document_id, lecturer_id, score, comment, graded_at)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE lecturer_id = VALUES(lecturer_id), score = VALUES(score), comment = VALUES(comment), graded_at = VALUES(graded_at)'
            );
            $stmt->execute([$documentId, $user['id'], $score, $comment, now_ts()]);
        }
        respond(['ok' => true, 'message' => 'Document graded']);
    }

    if ($action === 'create_announcement') {
        $title = trim((string)($data['title'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));
        $dueDate = trim((string)($data['due_date'] ?? ''));

        if ($title === '' || $message === '') {
            respond(['ok' => false, 'message' => 'title and message are required'], 422);
        }

        if ($dueDate === '') {
            $dueDate = null;
        }

        $stmt = db()->prepare(
            'INSERT INTO announcements (lecturer_id, title, message, due_date, created_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user['id'], $title, $message, $dueDate, now_ts()]);
        respond(['ok' => true, 'message' => 'Announcement created successfully']);
    }

    respond(['ok' => false, 'message' => 'Unknown action'], 400);
}

respond(['ok' => false, 'message' => 'Method not allowed'], 405);
