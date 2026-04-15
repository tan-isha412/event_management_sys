<?php

error_reporting(0);
ini_set('display_errors', '0');

require_once 'db_connect.php';

$input    = json_decode(file_get_contents('php://input'), true);
$event_id = isset($input['event_id']) ? trim($input['event_id']) : '';
$amount   = isset($input['amount'])   ? $input['amount']         : '';

// ---- Validate ----
if ($event_id === '') {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}
if ($amount === '' || !is_numeric($amount)) {
    echo json_encode(['success' => false, 'message' => 'Amount must be a valid number']);
    exit;
}
$amount = (float)$amount;
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than zero']);
    exit;
}

$conn = getDBConnection();

// ---- Verify event exists ----
$chk = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM EVENT WHERE EventID = :eid");
oci_bind_by_name($chk, ':eid', $event_id);
oci_execute($chk);
$chk_row = oci_fetch_assoc($chk);
oci_free_statement($chk);
if ((int)$chk_row['CNT'] === 0) {
    echo json_encode(['success' => false, 'message' => 'Event not found: ' . $event_id]);
    oci_close($conn);
    exit;
}

// ---- Insert using EXPENSE_SEQ (see triggers.sql) ----
$sql  = "INSERT INTO EXPENSE (ExpenseID, EventID, Amount)
         VALUES (EXPENSE_SEQ.NEXTVAL, :event_id, :amount)";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':event_id', $event_id);
oci_bind_by_name($stmt, ':amount',   $amount);

if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
    // Trigger TRG_UPDATE_BUDGET_EFFICIENCY has already fired.
    // Return updated analytics for real-time dashboard refresh.
    $analSql  = "SELECT BudgetEfficiency, SuccessScore, AttendanceScore
                   FROM EVENT_ANALYTICS WHERE EventID = :eid";
    $analStmt = oci_parse($conn, $analSql);
    oci_bind_by_name($analStmt, ':eid', $event_id);
    oci_execute($analStmt);
    $anal = oci_fetch_assoc($analStmt);
    oci_free_statement($analStmt);

    echo json_encode([
        'success'   => true,
        'message'   => 'Expense added. Analytics updated by trigger.',
        'analytics' => $anal ? [
            'budget_efficiency' => (float)$anal['BUDGETEFFICIENCY'],
            'success_score'     => (float)$anal['SUCCESSSCORE'],
            'attendance_score'  => (float)$anal['ATTENDANCESCORE']
        ] : null
    ]);
} else {
    $e = oci_error($stmt);
    echo json_encode([
        'success'     => false,
        'message'     => 'Insert failed: ' . $e['message'],
        'oracle_code' => $e['code']
    ]);
}

oci_free_statement($stmt);
oci_close($conn);
?>