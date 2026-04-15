<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$conn = getDBConnection();

$sql = "SELECT c.CoordinatorID, c.Name FROM STUDENT s JOIN COORDINATOR c ON s.name = c.name and s.departmentid=c.departmentid";
$stmt = oci_parse($conn, $sql);

if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    echo json_encode(['success' => false, 'message' => $e['message']]);
    exit;
}

$data = [];
while ($row = oci_fetch_assoc($stmt)) {
    $data[] = $row;
}

echo json_encode($data);

oci_free_statement($stmt);
oci_close($conn);
?>