<?php
require_once 'db_connect.php';

$student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';

if (empty($student_id)) {
    echo json_encode(['success' => false, 'message' => 'Student ID required']);
    exit;
}

$conn = getDBConnection();

$sql = "SELECT * FROM (
            SELECT 
                EventID,
                EventName,
                CategoryName,
                popularity,
                (popularity * 10) AS RecommendationScore
            FROM STUDENT_EVENT_RECOMMENDATION
            WHERE StudentID = :sid
            ORDER BY popularity DESC
        )
        WHERE ROWNUM <= 5";

$stmt = oci_parse($conn, $sql);

oci_bind_by_name($stmt, ':sid', $student_id);

if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    error_log('[recommendations.php] ' . $e['message']);

    echo json_encode([
        'success' => true,
        'count' => 0,
        'recommendations' => []
    ]);
    exit;
}

$recommendations = [];

while ($row = oci_fetch_assoc($stmt)) {
    $recommendations[] = [
        'EVENT_ID'             => $row['EVENTID'],
        'EVENT_NAME'           => $row['EVENTNAME'],
        'CATEGORY_NAME'        => $row['CATEGORYNAME'],
        'RECOMMENDATION_SCORE' => (int)$row['RECOMMENDATIONSCORE'],
        'STATUS'               => 'UPCOMING'
    ];
}

echo json_encode([
    'success' => true,
    'count' => count($recommendations),
    'recommendations' => $recommendations
]);

oci_free_statement($stmt);
oci_close($conn);
?>