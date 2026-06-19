<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

require_auth(['admin', 'lecturer']);

$type = strtolower(trim((string)($_GET['type'] ?? 'students_on_attachment')));
if (!in_array($type, ['students_on_attachment', 'pending_approvals'], true)) {
    http_response_code(422);
    echo 'Invalid report type';
    exit;
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

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $type . '_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'wb');
fputcsv($out, ['Reg Number', 'First Name', 'Last Name', 'Company', 'Status', 'Start Date', 'End Date']);
foreach ($rows as $row) {
    fputcsv($out, [
        $row['reg_number'],
        $row['first_name'],
        $row['last_name'],
        $row['company_name'],
        $row['status'],
        $row['start_date'],
        $row['end_date'],
    ]);
}
fclose($out);
exit;
