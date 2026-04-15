<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$conn = getDBConnection();
$sql = "SELECT CoordinatorID, Name FROM COORDINATOR";

$stmt = oci_parse($conn, $sql);

if (!$stmt) {
    $e = oci_error($conn);
    echo json_encode(['success'=>false,'message'=>$e['message']]);
    exit;
}

oci_execute($stmt);

$data = [];

while ($row = oci_fetch_assoc($stmt)) {
    $data[] = $row;
}

echo json_encode($data);

oci_free_statement($stmt);
oci_close($conn);
?>