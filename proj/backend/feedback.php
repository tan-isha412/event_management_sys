<?php
error_reporting(0);
ini_set('display_errors', '0');

require_once 'db_connect.php';  // sets Content-Type: application/json + CORS

$input = json_decode(file_get_contents('php://input'), true);

// Guard: if body wasn't valid JSON at all, $input will be null
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit;
}

$student_id = $input['student_id'] ?? '';
$event_id   = $input['event_id'] ?? '';
$rating     = $input['rating'] ?? 0;
$comments   = $input['comments'] ?? '';

if (empty($student_id) || empty($event_id) || empty($rating)) {
    echo json_encode([
        'success' => false,
        'message' => 'Student ID, Event ID and Rating required'
    ]);
    exit;
}

$conn = getDBConnection();


// ============================================
// CHECK DUPLICATE FEEDBACK
// ============================================
$chkSql = "SELECT COUNT(*) AS CNT
           FROM FEEDBACK
           WHERE StudentID = :sid AND EventID = :eid";

$chkStmt = oci_parse($conn, $chkSql);

oci_bind_by_name($chkStmt, ':sid', $student_id);
oci_bind_by_name($chkStmt, ':eid', $event_id);

oci_execute($chkStmt);

$chk = oci_fetch_assoc($chkStmt);

oci_free_statement($chkStmt);

if ($chk['CNT'] > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Feedback already submitted'
    ]);
    oci_close($conn);
    exit;
}


// ============================================
// INSERT FEEDBACK
// ============================================

function generateFID($conn) {
    do {
        $feedback_id = rand(1000, 9999);

        $checkSql = "SELECT COUNT(*) AS CNT 
                     FROM Feedback 
                     WHERE Feedbackid = :fid";

        $checkStmt = oci_parse($conn, $checkSql);
        oci_bind_by_name($checkStmt, ':fid', $feedback_id);
        oci_execute($checkStmt);

        $row = oci_fetch_assoc($checkStmt);
        oci_free_statement($checkStmt);

    } while ($row['CNT'] > 0);  

    return $feedback_id;
}
$feedback_id = generateFID($conn);

$sql = "INSERT INTO FEEDBACK (FeedbackID, StudentID, EventID, Rating, Comments)
        VALUES (:fid, :sid, :eid, :rating, :comments)";

$stmt = oci_parse($conn, $sql);

oci_bind_by_name($stmt, ':fid', $feedback_id);
oci_bind_by_name($stmt, ':sid', $student_id);
oci_bind_by_name($stmt, ':eid', $event_id);
oci_bind_by_name($stmt, ':rating', $rating);
oci_bind_by_name($stmt, ':comments', $comments);

if (oci_execute($stmt)) {

    echo json_encode([
        'success' => true,
        'message' => 'Feedback submitted successfully'
    ]);

} else {
    $e = oci_error($stmt);
    $error = $e['message'] ?? '';

    if (strpos($error, '-20002') !== false) {
        $msg = "🚫 Absent students cannot give feedback.";
    } 
    else if (strpos($error, '-20003') !== false) {
        $msg = "⚠️ Attendance record not found.";
    } 
    else {
        $msg = "⚠️ Unable to submit feedback. Please try again.";
    }

    echo json_encode([
        'success' => false,
        'message' => $msg
    ]);
}

oci_free_statement($stmt);
oci_close($conn);
?>