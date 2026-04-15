<?php
// Suppress PHP notices/warnings — stray output breaks JSON parse in apiFetch
error_reporting(0);
ini_set('display_errors', '0');

require_once 'db_connect.php';  // sets Content-Type: application/json + CORS

$type = isset($_GET['type']) ? trim($_GET['type']) : 'overview';
$conn = getDBConnection();

$response = [];

switch ($type) {

    // ============================================
    // OVERVIEW
    // ============================================
    case 'overview':

        $queries = [
            'total_events' => "SELECT COUNT(*) AS VAL FROM EVENT",
            'total_students' => "SELECT COUNT(*) AS VAL FROM STUDENT",
            'total_registrations' => "SELECT COUNT(*) AS VAL FROM REGISTRATION"
        ];

        foreach ($queries as $key => $sql) {
            $stmt = oci_parse($conn, $sql);
            oci_execute($stmt);
            $row = oci_fetch_assoc($stmt);
            $response[$key] = $row['VAL'];
            oci_free_statement($stmt);
        }
        break;


    // ============================================
    // EVENT ANALYTICS (YOUR VIEW)
    // ============================================
    case 'analytics':

        $sql = "SELECT EventID, SuccessScore, BudgetEfficiency, AttendanceScore
                FROM EVENT_ANALYTICS
                ORDER BY SuccessScore DESC";

        $stmt = oci_parse($conn, $sql);
        oci_execute($stmt);

        $rows = [];

        while ($row = oci_fetch_assoc($stmt)) {
            $rows[] = [
                'event_id' => $row['EVENTID'],
                'success' => $row['SUCCESSSCORE'],
                'budget_eff' => $row['BUDGETEFFICIENCY'],
                'attendance' => $row['ATTENDANCESCORE']
            ];
        }

        $response['analytics'] = $rows;
        oci_free_statement($stmt);
        break;


    // ============================================
    // POPULAR EVENTS
    // ============================================
    case 'popular':

        $sql = "SELECT E.EventName, V.VenueName,
                       COUNT(R.RegistrationID) AS REG_COUNT,
                       E.EventDate
                FROM EVENT E
                LEFT JOIN REGISTRATION R ON E.EventID = R.EventID
                LEFT JOIN VENUE V ON E.VenueID = V.VenueID
                GROUP BY E.EventID, E.EventName, V.VenueName, E.EventDate
                ORDER BY REG_COUNT DESC
                FETCH FIRST 10 ROWS ONLY";

        $stmt = oci_parse($conn, $sql);
        oci_execute($stmt);

        $rows = [];

        while ($row = oci_fetch_assoc($stmt)) {
            $rows[] = [
                'event_name' => $row['EVENTNAME'],
                'venue' => $row['VENUENAME'],
                'registrations' => $row['REG_COUNT'],
                'date' => $row['EVENTDATE']
            ];
        }

        $response['popular_events'] = $rows;
        oci_free_statement($stmt);
        break;


    // ============================================
    // FEEDBACK ANALYSIS
    // ============================================
    case 'feedback':

        $sql = "SELECT E.EventName,
                       AVG(F.Rating) AS AVG_RATING,
                       COUNT(F.FeedbackID) AS COUNT
                FROM EVENT E
                LEFT JOIN FEEDBACK F ON E.EventID = F.EventID
                GROUP BY E.EventID, E.EventName
                ORDER BY AVG_RATING DESC NULLS LAST";

        $stmt = oci_parse($conn, $sql);
        oci_execute($stmt);

        $rows = [];

        while ($row = oci_fetch_assoc($stmt)) {
            $rows[] = [
                'event_name' => $row['EVENTNAME'],
                'avg_rating' => $row['AVG_RATING'] !== null ? round((float)$row['AVG_RATING'], 2) : null,
                'count'      => (int)$row['COUNT']
            ];
        }

        $response['feedback'] = $rows;
        oci_free_statement($stmt);
        break;


    // ============================================
    // CATEGORY DISTRIBUTION
    // ============================================
    case 'category_dist':

        $sql = "SELECT C.CategoryName,
                       COUNT(E.EventID) AS CNT
                FROM EVENTCATEGORY C
                LEFT JOIN EVENT E ON C.CategoryID = E.CategoryID
                GROUP BY C.CategoryName
                ORDER BY CNT DESC";

        $stmt = oci_parse($conn, $sql);
        oci_execute($stmt);

        $rows = [];

        while ($row = oci_fetch_assoc($stmt)) {
            $rows[] = [
                'category' => $row['CATEGORYNAME'],
                'count' => $row['CNT']
            ];
        }

        $response['category_dist'] = $rows;
        oci_free_statement($stmt);
        break;


    // ============================================
    // ATTENDANCE LIST
    // ============================================
    case 'attendance_list':

        $event_id = isset($_GET['event_id']) ? trim($_GET['event_id']) : '';

        if (empty($event_id)) {
            echo json_encode(['success' => false, 'message' => 'Event ID required']);
            exit;
        }

        $sql = "SELECT S.StudentID, S.Name,
                       NVL(A.Status, 'NOT MARKED') AS STATUS
                FROM REGISTRATION R
                JOIN STUDENT S ON R.StudentID = S.StudentID
                LEFT JOIN ATTENDANCE A
                  ON R.EventID = A.EventID AND R.StudentID = A.StudentID
                WHERE R.EventID = :eid
                ORDER BY S.Name";

        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':eid', $event_id);

        oci_execute($stmt);

        $rows = [];

        while ($row = oci_fetch_assoc($stmt)) {
            $rows[] = [
                'student_id' => $row['STUDENTID'],
                'name' => $row['NAME'],
                'status' => $row['STATUS']
            ];
        }

        $response['attendance'] = $rows;
        oci_free_statement($stmt);
        break;
}

echo json_encode([
    'success' => true,
    'data' => $response
]);

oci_close($conn);
?>