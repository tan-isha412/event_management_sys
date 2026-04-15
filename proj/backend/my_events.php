<?php
// ============================================================
// my_events.php
// FIXES:
// 1. Key names now exactly match what normalizeRegistration()
//    in app.js expects:
//      event_id, event_name, date, venue, category,
//      attendance, feedback_given
//    The old file returned 'date' correctly but some keys were
//    subtly wrong after previous edits — confirmed here.
// 2. Oracle DATE cast to string with TO_CHAR so formatDate()
//    in JS receives "2026-04-10" not an OCI object.
// 3. bind variable :sid used twice in the same query — OCI8
//    requires both binds to use the same PHP variable, which
//    already works correctly here.
// 4. Added oci_execute error handling.
// ============================================================
require_once 'db_connect.php';

$student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';

if (empty($student_id)) {
    echo json_encode(['success' => false, 'message' => 'Student ID required']);
    exit;
}

$conn = getDBConnection();

$sql = "SELECT E.EventID,
               E.EventName,
               TO_CHAR(E.EventDate, 'YYYY-MM-DD') AS EventDate,
               V.VenueName,
               C.CategoryName,
               NVL(A.Status, 'NOT MARKED') AS ATTENDANCE,
               (SELECT COUNT(*) FROM FEEDBACK F
                WHERE F.EventID = E.EventID AND F.StudentID = :sid2) AS FEEDBACK_GIVEN
        FROM REGISTRATION R
        JOIN EVENT E ON R.EventID = E.EventID
        LEFT JOIN EVENTCATEGORY C ON E.CategoryID = C.CategoryID
        LEFT JOIN VENUE V ON E.VenueID = V.VenueID
        LEFT JOIN ATTENDANCE A ON A.EventID = E.EventID AND A.StudentID = R.StudentID
        WHERE R.StudentID = :sid
        ORDER BY E.EventDate DESC";

$stmt = oci_parse($conn, $sql);
if (!$stmt) {
    $e = oci_error($conn);
    echo json_encode(['success' => false, 'message' => $e['message']]);
    exit;
}

// OCI8 needs separate bind calls for each placeholder name
oci_bind_by_name($stmt, ':sid',  $student_id);
oci_bind_by_name($stmt, ':sid2', $student_id);

if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    error_log('[my_events.php] Query failed: ' . $e['message']);
    echo json_encode(['success' => false, 'message' => $e['message']]);
    oci_free_statement($stmt);
    oci_close($conn);
    exit;
}

$events = [];
while ($row = oci_fetch_assoc($stmt)) {
    // Keys must match normalizeRegistration() in app.js exactly:
    // r.event_id  → EVENT_ID
    // r.event_name→ EVENT_NAME
    // r.date      → EVENT_DATE
    // r.venue     → VENUE
    // r.category  → CATEGORY_NAME
    // r.attendance→ ATTENDANCE
    // r.feedback_given → FEEDBACK_GIVEN
    $events[] = [
        'event_id'       => $row['EVENTID'],
        'event_name'     => $row['EVENTNAME'],
        'date'           => $row['EVENTDATE'],
        'venue'          => $row['VENUENAME'],
        'category'       => $row['CATEGORYNAME'],
        'attendance'     => $row['ATTENDANCE'],
        'feedback_given' => (int)$row['FEEDBACK_GIVEN']
    ];
}

echo json_encode([
    'success'       => true,
    'count'         => count($events),
    'registrations' => $events    // JS reads regRes.registrations
]);

oci_free_statement($stmt);
oci_close($conn);
?>
