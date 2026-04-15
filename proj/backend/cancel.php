<?php
header('Content-Type: application/json');

require_once 'db_connect.php';

$conn = getDBConnection();

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}

$student_id = $input['student_id'] ?? '';
$event_id   = $input['event_id'] ?? '';

$sql = "BEGIN cancel_registration_proc(:sid, :eid); END;";
$stmt = oci_parse($conn, $sql);

oci_bind_by_name($stmt, ":sid", $student_id);
oci_bind_by_name($stmt, ":eid", $event_id);

$res = oci_execute($stmt);

if ($res) {
    echo json_encode([
        "success" => true,
        "message" => "✅ Registration cancelled successfully."
    ]);
} else {
    $e = oci_error($stmt);
    $error = $e['message'] ?? '';

    if (strpos($error, '-20020') !== false) {
        $msg = "⚠️ You are not registered for this event.";
    } else {
        $msg = "⚠️ Failed to cancel registration.";
    }

    echo json_encode([
        "success" => false,
        "message" => $msg
    ]);
}

oci_free_statement($stmt);
oci_close($conn);
?>