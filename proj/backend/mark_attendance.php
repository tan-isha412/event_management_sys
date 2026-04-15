<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$input    = json_decode(file_get_contents('php://input'), true);
$event_id = $input['event_id'] ?? '';
$records  = $input['records']  ?? [];  // [{student_id, status}, ...]

if (empty($event_id)) {
    echo json_encode(['success' => false, 'message' => 'Event ID required']);
    exit;
}
if (empty($records) || !is_array($records)) {
    echo json_encode(['success' => false, 'message' => 'No attendance records provided']);
    exit;
}

$conn   = getDBConnection();
$errors = [];

foreach ($records as $rec) {
    $student_id = trim($rec['student_id'] ?? '');
    $status     = strtoupper(trim($rec['status'] ?? 'PRESENT'));

    if (empty($student_id)) { $errors[] = "Skipped: empty student_id"; continue; }

    // Check if row already exists
    $chkSql  = "SELECT COUNT(*) AS CNT FROM ATTENDANCE WHERE EventID = :eid AND StudentID = :sid";
    $chkStmt = oci_parse($conn, $chkSql);
    oci_bind_by_name($chkStmt, ':eid', $event_id);
    oci_bind_by_name($chkStmt, ':sid', $student_id);
    oci_execute($chkStmt, OCI_NO_AUTO_COMMIT);
    $row = oci_fetch_assoc($chkStmt);
    oci_free_statement($chkStmt);

    if ((int)($row['CNT'] ?? 0) > 0) {
        $sql  = "UPDATE ATTENDANCE SET Status = :status WHERE EventID = :eid AND StudentID = :sid";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':status', $status);
        oci_bind_by_name($stmt, ':eid',    $event_id);
        oci_bind_by_name($stmt, ':sid',    $student_id);
    } else {
        // Use SEQ_ATT (the actual sequence name in this DB)
        $sql  = "INSERT INTO ATTENDANCE (AttendanceID, EventID, StudentID, Status)
                 VALUES (SEQ_ATT.NEXTVAL, :eid, :sid, :status)";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':eid',    $event_id);
        oci_bind_by_name($stmt, ':sid',    $student_id);
        oci_bind_by_name($stmt, ':status', $status);
    }

    if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
        $e = oci_error($stmt);
        $errors[] = "Student $student_id: " . $e['message'];
    }
    oci_free_statement($stmt);
}

oci_commit($conn);
oci_close($conn);

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => 'Some records failed: ' . implode('; ', $errors)]);
} else {
    echo json_encode(['success' => true, 'message' => 'Attendance saved for ' . count($records) . ' student(s)']);
}
?>