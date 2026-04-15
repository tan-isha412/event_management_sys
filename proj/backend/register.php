<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);
// ============================================================
// register.php
// FIXES:
// 1. Added oci_commit() — Oracle in OCI8 defaults to manual
//    transaction mode. Without commit the INSERT is rolled back
//    when the connection closes, so the row never persists.
// 2. Added oci_execute error check with oci_error() response.
// 3. rand() ID is kept for simplicity (college project).
//    If you have a sequence REG_SEQ, replace rand() with:
//    SELECT REG_SEQ.NEXTVAL FROM DUAL
// ============================================================
require_once 'db_connect.php';

$input      = json_decode(file_get_contents('php://input'), true);
$student_id = isset($input['student_id']) ? trim($input['student_id']) : '';
$event_id   = isset($input['event_id'])   ? trim($input['event_id'])   : '';

if (empty($student_id) || empty($event_id)) {
    echo json_encode(['success' => false, 'message' => 'Student ID and Event ID are required']);
    exit;
}

$conn = getDBConnection();
$attendance_id = uniqid("A"); // simple ID

$sql2 = "INSERT INTO ATTENDANCE (ATTENDANCEID, STUDENTID, EVENTID, STATUS)
         VALUES (:aid, :sid, :eid, 'ABSENT')";

$stmt2 = oci_parse($conn, $sql2);

oci_bind_by_name($stmt2, ':aid', $attendance_id);
oci_bind_by_name($stmt2, ':sid', $student_id);
oci_bind_by_name($stmt2, ':eid', $event_id);

oci_execute($stmt2);
// ---- Duplicate check ----
$chkSql  = "SELECT COUNT(*) AS CNT FROM REGISTRATION WHERE StudentID = :sid AND EventID = :eid";
$chkStmt = oci_parse($conn, $chkSql);
oci_bind_by_name($chkStmt, ':sid', $student_id);
oci_bind_by_name($chkStmt, ':eid', $event_id);
oci_execute($chkStmt);

$chkRow = oci_fetch_assoc($chkStmt);
oci_free_statement($chkStmt);

if ((int)$chkRow['CNT'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Already registered for this event']);
    oci_close($conn);
    exit;
}
function generateUniqueRegID($conn) {
    do {
        $reg_id = rand(10000, 99999);

        $checkSql = "SELECT COUNT(*) AS CNT 
                     FROM REGISTRATION 
                     WHERE RegistrationID = :rid";

        $checkStmt = oci_parse($conn, $checkSql);
        oci_bind_by_name($checkStmt, ':rid', $reg_id);
        oci_execute($checkStmt);

        $row = oci_fetch_assoc($checkStmt);
        oci_free_statement($checkStmt);

    } while ($row['CNT'] > 0);  

    return $reg_id;
}
$reg_id = generateUniqueRegID($conn);

$sql = "INSERT INTO REGISTRATION (RegistrationID, StudentID, EventID) 
        VALUES (:rid, :sid, :eid)";

$stmt = oci_parse($conn, $sql);

oci_bind_by_name($stmt, ':rid', $reg_id);
oci_bind_by_name($stmt, ':sid', $student_id);
oci_bind_by_name($stmt, ':eid', $event_id);
if (oci_execute($stmt)) {
    oci_commit($conn);  
    echo json_encode(['success' => true, 'message' => 'Registration successful']);
} else {
    $e = oci_error($stmt);
    error_log('[register.php] INSERT failed: ' . $e['message']);
    echo json_encode(['success' => false, 'message' => $e['message']]);
}

oci_free_statement($stmt);
oci_close($conn);
?>
