<?php
// ============================================================
// get_budget.php — Budget Usage API
// Smart Event Management System
// ============================================================
require_once 'db_connect.php';

$conn = getDBConnection();

// ----------------------------------------------------------------
// Query: EventName, BudgetAllocated, BudgetUsed, Remaining
// BudgetUsed = SUM(EXPENSE.Amount) for that event
// Remaining  = BudgetAllocated - BudgetUsed (can be negative = overspent)
// ----------------------------------------------------------------
$sql = "
    SELECT
        E.EventID,
        E.EventName,
        NVL(E.BudgetAllocated, 0)         AS BudgetAllocated,
        NVL(SUM(EX.Amount), 0)            AS BudgetUsed,
        NVL(E.BudgetAllocated, 0)
            - NVL(SUM(EX.Amount), 0)      AS Remaining,
        CASE
            WHEN NVL(E.BudgetAllocated, 0) > 0 THEN
                ROUND(
                    (NVL(SUM(EX.Amount), 0) / E.BudgetAllocated) * 100,
                    2
                )
            ELSE 0
        END                               AS UsagePct
    FROM EVENT E
    LEFT JOIN EXPENSE EX ON E.EventID = EX.EventID
    GROUP BY E.EventID, E.EventName, E.BudgetAllocated
    ORDER BY UsagePct DESC
";

$stmt = oci_parse($conn, $sql);

if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    echo json_encode([
        'success' => false,
        'message' => $e['message']
    ]);
    exit;
}

$rows = [];
$total_allocated = 0;
$total_used      = 0;

while ($row = oci_fetch_assoc($stmt)) {
    $allocated = (float)$row['BUDGETALLOCATED'];
    $used      = (float)$row['BUDGETUSED'];
    $remaining = (float)$row['REMAINING'];
    $pct       = (float)$row['USAGEPCT'];

    $rows[] = [
        'event_id'        => $row['EVENTID'],
        'event_name'      => $row['EVENTNAME'],
        'budget_allocated'=> $allocated,
        'budget_used'     => $used,
        'remaining'       => $remaining,
        'usage_pct'       => $pct,
        'status'          => $pct > 100
                             ? 'overspent'
                             : ($pct >= 80 ? 'warning' : 'ok')
    ];

    $total_allocated += $allocated;
    $total_used      += $used;
}

oci_free_statement($stmt);
oci_close($conn);

echo json_encode([
    'success' => true,
    'data' => [
        'budget_rows'      => $rows,
        'total_allocated'  => $total_allocated,
        'total_used'       => $total_used,
        'total_remaining'  => $total_allocated - $total_used
    ]
]);
?>