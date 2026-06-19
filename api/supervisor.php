<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_auth(['supervisor', 'admin']);
$method = $_SERVER['REQUEST_METHOD'];
ensure_feedback_score_range();

if ($method === 'GET') {
    $stmt = db()->prepare(
        "SELECT p.id, p.status, p.company_name, p.company_address, p.start_date, p.end_date,
                s.reg_number, s.first_name, s.last_name
         FROM placements p
         INNER JOIN users s ON s.id = p.student_id
         WHERE p.supervisor_email = ? OR ? = 'admin'
         ORDER BY p.created_at DESC"
    );
    $identifier = $user['role'] === 'admin' ? 'admin' : ($user['email'] ?? '');
    $stmt->execute([$identifier, $user['role']]);
    respond(['ok' => true, 'placements' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = json_input();
    $action = strtolower(trim((string)($data['action'] ?? '')));

    if ($action === 'confirm_placement') {
        $regNumber = trim((string)($data['reg_number'] ?? ''));
        if ($regNumber === '') {
            respond(['ok' => false, 'message' => 'reg_number required'], 422);
        }

        global $config;
        $driver = strtolower(trim((string)($config['db']['driver'] ?? 'mysql')));
        if ($user['role'] === 'admin') {
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
        } else {
            $lookupSql = "SELECT TOP 1 p.id
                          FROM placements p
                          INNER JOIN users s ON s.id = p.student_id
                          WHERE LOWER(s.reg_number) = LOWER(?)
                            AND p.supervisor_email = ?
                          ORDER BY p.created_at DESC";
            if ($driver !== 'sqlsrv') {
                $lookupSql = str_replace('SELECT TOP 1 p.id', 'SELECT p.id', $lookupSql);
                $lookupSql .= ' LIMIT 1';
            }
            $lookupStmt = db()->prepare($lookupSql);
            $lookupStmt->execute([$regNumber, $user['email'] ?? '']);
        }

        $placement = $lookupStmt->fetch();
        if (!$placement) {
            respond(['ok' => false, 'message' => 'No placement found for that registration number'], 404);
        }
        $placementId = (int)($placement['id'] ?? 0);

        $stmt = db()->prepare(
            'UPDATE placements SET status = CASE WHEN status = ? THEN ? ELSE status END, supervisor_confirmed = 1, supervisor_confirmed_at = ? WHERE id = ?'
        );
        $stmt->execute(['pending', 'confirmed', now_ts(), $placementId]);
        respond(['ok' => true, 'message' => 'Placement confirmed']);
    }

    if ($action === 'feedback') {
        $regNumber = trim((string)($data['reg_number'] ?? ''));
        $comment = trim((string)($data['comment'] ?? ''));
        $score = (int)($data['score'] ?? 0);
        if ($regNumber === '' || $comment === '' || $score < 0 || $score > 100) {
            respond(['ok' => false, 'message' => 'reg_number, comment and score(0-100) required'], 422);
        }

        global $config;
        $driver = strtolower(trim((string)($config['db']['driver'] ?? 'mysql')));
        if ($user['role'] === 'admin') {
            $lookupSql = "SELECT TOP 1 p.id, s.id AS student_id
                          FROM placements p
                          INNER JOIN users s ON s.id = p.student_id
                          WHERE LOWER(s.reg_number) = LOWER(?)
                          ORDER BY p.created_at DESC";
            if ($driver !== 'sqlsrv') {
                $lookupSql = str_replace('SELECT TOP 1 p.id, s.id AS student_id', 'SELECT p.id, s.id AS student_id', $lookupSql);
                $lookupSql .= ' LIMIT 1';
            }
            $lookupStmt = db()->prepare($lookupSql);
            $lookupStmt->execute([$regNumber]);
        } else {
            $lookupSql = "SELECT TOP 1 p.id, s.id AS student_id
                          FROM placements p
                          INNER JOIN users s ON s.id = p.student_id
                          WHERE LOWER(s.reg_number) = LOWER(?)
                            AND p.supervisor_email = ?
                          ORDER BY p.created_at DESC";
            if ($driver !== 'sqlsrv') {
                $lookupSql = str_replace('SELECT TOP 1 p.id, s.id AS student_id', 'SELECT p.id, s.id AS student_id', $lookupSql);
                $lookupSql .= ' LIMIT 1';
            }
            $lookupStmt = db()->prepare($lookupSql);
            $lookupStmt->execute([$regNumber, $user['email'] ?? '']);
        }
        $resolved = $lookupStmt->fetch();
        if (!$resolved) {
            respond(['ok' => false, 'message' => 'No placement found for that registration number'], 404);
        }
        $placementId = (int)($resolved['id'] ?? 0);
        $studentId = (int)($resolved['student_id'] ?? 0);

        $stmt = db()->prepare(
            "INSERT INTO feedback (placement_id, student_id, source_role, source_id, comment, score)
             VALUES (?, ?, 'supervisor', ?, ?, ?)"
        );
        $stmt->execute([$placementId, $studentId, $user['id'], $comment, $score]);
        respond(['ok' => true, 'message' => 'Feedback submitted']);
    }

    respond(['ok' => false, 'message' => 'Unknown action'], 400);
}

respond(['ok' => false, 'message' => 'Method not allowed'], 405);
