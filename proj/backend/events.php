<?php

require_once 'db_connect.php';

$conn     = getDBConnection();
$event_id = isset($_GET['event_id']) ? trim($_GET['event_id']) : '';

// ---- SINGLE EVENT (used by openEventModal) ----
if (!empty($event_id)) {

    $sql = "SELECT E.EventID,
                   E.EventName,
                   TO_CHAR(E.EventDate, 'YYYY-MM-DD HH24:MI') AS EventDate,
                   E.BudgetAllocated,
                   C.CategoryName,
                   V.VenueName,
                   V.Capacity,
                   (SELECT COUNT(*) FROM REGISTRATION R WHERE R.EventID = E.EventID) AS REG_COUNT,
                   CO.Name as ORGANIZER_NAME
            FROM EVENT E
            LEFT JOIN EVENTCATEGORY C ON E.CategoryID = C.CategoryID
            LEFT JOIN VENUE        V ON E.VenueID     = V.VenueID
            LEFT JOIN COORDINATOR CO ON E.CoordinatorID = CO.CoordinatorID
            WHERE E.EventID = :eid";

    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $e = oci_error($conn);
        echo json_encode(['success' => false, 'message' => $e['message']]);
        exit;
    }

    oci_bind_by_name($stmt, ':eid', $event_id);

    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        echo json_encode(['success' => false, 'message' => $e['message']]);
        oci_free_statement($stmt);
        oci_close($conn);
        exit;
    }

    $row = oci_fetch_assoc($stmt);

    if ($row) {
        // Modal reads res.event with UPPERCASE keys directly (no normalizeEvent call)
        echo json_encode([
            'success' => true,
            'event' => [
    'EVENT_ID'        => $row['EVENTID'],
    'EVENT_NAME'      => $row['EVENTNAME'],
    'EVENT_DATE'      => $row['EVENTDATE'],
    'VENUE'           => $row['VENUENAME'],
    'CATEGORY_NAME'   => $row['CATEGORYNAME'],
    'REG_COUNT'       => (int)$row['REG_COUNT'],
    'MAX_CAPACITY'    => $row['CAPACITY'],
    'REGISTRATION_FEE'=> 0,
    'ORGANIZER_NAME'  => $row['ORGANIZER_NAME'],
    'REGISTRATION_FEE' => 0,
    'STATUS'          => 'UPCOMING',
    'DESCRIPTION'     => 'Visit and know ;)'
]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
    }

    oci_free_statement($stmt);
    oci_close($conn);
    exit;
}

$sql = "SELECT E.EventID,
               E.EventName,
               TO_CHAR(E.EventDate, 'YYYY-MM-DD') AS EventDate,
               E.BudgetAllocated,
               C.CategoryName,
               V.VenueName,
               V.Capacity,
               (SELECT COUNT(*) FROM REGISTRATION R WHERE R.EventID = E.EventID) AS REG_COUNT
        FROM EVENT E
        LEFT JOIN EVENTCATEGORY C ON E.CategoryID = C.CategoryID
        LEFT JOIN VENUE        V ON E.VenueID     = V.VenueID
        ORDER BY E.EventDate ASC";

$stmt = oci_parse($conn, $sql);
if (!$stmt) {
    $e = oci_error($conn);
    echo json_encode(['success' => false, 'message' => $e['message']]);
    exit;
}

if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    echo json_encode(['success' => false, 'message' => $e['message']]);
    oci_free_statement($stmt);
    oci_close($conn);
    exit;
}

$events = [];
while ($row = oci_fetch_assoc($stmt)) {
    
    $events[] = [
        'event_id'      => $row['EVENTID'],
        'name'          => $row['EVENTNAME'],
        'date'          => $row['EVENTDATE'],
        'budget'        => $row['BUDGETALLOCATED'],
        'category'      => $row['CATEGORYNAME'],
        'venue'         => $row['VENUENAME'],
        'registrations' => (int)$row['REG_COUNT'],
        'capacity' => (int)$row['CAPACITY']
    ];
}

echo json_encode([
    'success' => true,
    'count'   => count($events),
    'events'  => $events
]);

oci_free_statement($stmt);
oci_close($conn);
?>
