<?php
require_once 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

$student_id = isset($input['student_id']) ? trim($input['student_id']) : '';
$event_id   = isset($input['event_id']) ? trim($input['event_id']) : '';

if (empty($student_id) || empty($event_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Student ID and Event ID required'
    ]);
    exit;
}

$conn = getDBConnection();

$sql = "BEGIN register_student_proc(:sid, :eid); END;";
$stmt = oci_parse($conn, $sql);

oci_bind_by_name($stmt, ":sid", $student_id);
oci_bind_by_name($stmt, ":eid", $event_id);

$res = oci_execute($stmt);

// ============================================
// INSERT REGISTRATION
// ============================================

// If you DO NOT have sequence → use manual ID
// Otherwise tell me, I’ll create sequence

$reg_id = rand(1000, 9999); // temporary unique ID

$sql = "INSERT INTO REGISTRATION (RegistrationID, StudentID, EventID)
        VALUES (:rid, :sid, :eid)";

$stmt = oci_parse($conn, $sql);

oci_bind_by_name($stmt, ':rid', $reg_id);
oci_bind_by_name($stmt, ':sid', $student_id);
oci_bind_by_name($stmt, ':eid', $event_id);

if (oci_execute($stmt)) {

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful'
    ]);

} else {

    $e = oci_error($stmt);

    echo json_encode([
        'success' => false,
        'message' => $e['message']
    ]);
}

oci_free_statement($chkStmt);
oci_free_statement($stmt);
oci_close($conn);
?>