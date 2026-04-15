<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

$name = $input['name'] ?? '';
$dept = $input['department_id'] ?? '';
$year = $input['year'] ?? '';
$dob  = $input['dob'] ?? '';

if (!$name || !$dept || !$dob) {
    echo json_encode(['success'=>false,'message'=>'Missing fields']);
    exit;
}

$conn = getDBConnection();

$sql = "SELECT COUNT(*) AS CNT FROM STUDENT";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);

$num = str_pad($row['CNT'] + 1, 4, '0', STR_PAD_LEFT);
$sid = 'S' . $num;

$sql2 = "INSERT INTO STUDENT
(StudentID, Name, DepartmentID, Year, DOB)
VALUES (:sid, :name, :dept, :year, TO_DATE(:dob,'YYYY-MM-DD'))";

$stmt2 = oci_parse($conn, $sql2);

oci_bind_by_name($stmt2, ':sid', $sid);
oci_bind_by_name($stmt2, ':name', $name);
oci_bind_by_name($stmt2, ':dept', $dept);
oci_bind_by_name($stmt2, ':year', $year);
oci_bind_by_name($stmt2, ':dob', $dob);

if (oci_execute($stmt2)) {
    oci_commit($conn);
    echo json_encode(['success'=>true,'message'=>"Student added ($sid)"]);
} else {
    $e = oci_error($stmt2);
    echo json_encode(['success'=>false,'message'=>$e['message']]);
}

oci_close($conn);
?>