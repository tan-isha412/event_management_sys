<?php
header('Content-Type: application/json');
require_once 'db_connect.php';
$conn = getDBConnection();
$sql = "SELECT DepartmentID, DepartmentName FROM DEPARTMENT";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);
$data = [];
while ($row = oci_fetch_assoc($stmt)) {
    $data[] = $row;
}
echo json_encode($data);
oci_close($conn);
?>