<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

$student_id = $input['student_id'] ?? '';
$dept_id    = $input['department_id'] ?? '';
$admin_id   = $input['admin_id'] ?? '';

if (!$student_id || !$dept_id || !$admin_id) {
    echo json_encode(['success'=>false,'message'=>'Missing data']);
    exit;
}

$conn = getDBConnection();

$sql = "SELECT Name FROM STUDENT WHERE StudentID = :sid";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':sid', $student_id);
oci_execute($stmt);

$row = oci_fetch_assoc($stmt);

if (!$row) {
    echo json_encode(['success'=>false,'message'=>'Student not found']);
    exit;
}

$name = $row['NAME'];

$sql_check = "SELECT COUNT(*) AS CNT FROM COORDINATOR WHERE Name = :name";
$stmt_check = oci_parse($conn, $sql_check);
oci_bind_by_name($stmt_check, ':name', $name);
oci_execute($stmt_check);

$row_check = oci_fetch_assoc($stmt_check);

if ($row_check['CNT'] > 0) {
    echo json_encode(['success'=>false,'message'=>'Already a coordinator']);
    exit;
}

$sql2 = "SELECT COUNT(*) AS CNT FROM COORDINATOR";
$stmt2 = oci_parse($conn, $sql2);
oci_execute($stmt2);
$row2 = oci_fetch_assoc($stmt2);

$num = str_pad($row2['CNT'] + 1, 3, '0', STR_PAD_LEFT);
$cid = 'C' . $num;

$sql3 = "INSERT INTO COORDINATOR
(CoordinatorID, Name, DepartmentID, CreatedByAdminID)
VALUES (:cid, :name, :dept, :admin)";

$stmt3 = oci_parse($conn, $sql3);

oci_bind_by_name($stmt3, ':cid', $cid);
oci_bind_by_name($stmt3, ':name', $name);
oci_bind_by_name($stmt3, ':dept', $dept_id);
oci_bind_by_name($stmt3, ':admin', $admin_id);

if (oci_execute($stmt3)) {
    oci_commit($conn);
    echo json_encode(['success'=>true,'message'=>"Coordinator added ($cid)"]);
} else {
    $e = oci_error($stmt3);
    echo json_encode(['success'=>false,'message'=>$e['message']]);
}

oci_close($conn);
?>