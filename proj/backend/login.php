<?php
require_once 'db_connect.php'; // Sets Content-Type: application/json header
session_start();

// ---- Read JSON body ----
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

// Debug log — writes to PHP error log (check xampp/php/logs/php_error_log)
error_log("[LOGIN] Raw input: " . $raw);

if (!is_array($input)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON body received'
    ]);
    exit;
}

$role = isset($input['role']) ? trim($input['role']) : '';
error_log("[LOGIN] Role: " . $role);


// ============================================
// STUDENT LOGIN
// ============================================
if ($role === 'student') {

    $studentId = isset($input['student_id']) ? trim($input['student_id']) : '';
    $dob       = isset($input['dob'])        ? trim($input['dob'])        : '';

    error_log("[LOGIN] Student attempt — ID: $studentId | DOB: $dob");

    if (empty($studentId) || empty($dob)) {
        echo json_encode([
            'success' => false,
            'message' => 'Student ID and Date of Birth are required'
        ]);
        exit;
    }

    $conn = getDBConnection();

    // ---- Query ----
    // Column names must match YOUR table exactly (Oracle stores them uppercase).
    // Adjust StudentID / Name / DepartmentID / Year / DOB below if your
    // column names differ (check with: SELECT column_name FROM user_tab_columns
    // WHERE table_name = 'STUDENT').
    $sql = "SELECT StudentID, Name, DepartmentID, Year, DOB
            FROM STUDENT
            WHERE StudentID = :sid
              AND TRUNC(DOB) = TO_DATE(:dob, 'YYYY-MM-DD')";

    $stmt = oci_parse($conn, $sql);

    if (!$stmt) {
        $e = oci_error($conn);
        error_log("[LOGIN] oci_parse failed: " . $e['message']);
        echo json_encode(['success' => false, 'message' => 'Query preparation failed']);
        exit;
    }

    oci_bind_by_name($stmt, ':sid', $studentId);
    oci_bind_by_name($stmt, ':dob', $dob);

    $exec = oci_execute($stmt);

    if (!$exec) {
        $e = oci_error($stmt);
        error_log("[LOGIN] Student query failed: " . $e['message']);
        echo json_encode([
            'success' => false,
            'message' => 'Query error: ' . $e['message'] // remove in production
        ]);
        oci_free_statement($stmt);
        oci_close($conn);
        exit;
    }

    $row = oci_fetch_assoc($stmt);
    error_log("[LOGIN] Student row found: " . ($row ? 'YES' : 'NO'));

    if ($row) {
        $_SESSION['user_id']   = $row['STUDENTID'];
        $_SESSION['user_name'] = $row['NAME'];
        $_SESSION['role']      = 'student';

        echo json_encode([
            'success'  => true,
            'role'     => 'student',
            'redirect' => 'student_dashboard.html',
            'user' => [
    'student_id' => $row['STUDENTID'],   // ✅ FIX
    'name'       => $row['NAME'],
    'department' => $row['DEPARTMENTID'],
    'year'       => $row['YEAR']
]
        ]);

    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid Student ID or Date of Birth'
        ]);
    }

    oci_free_statement($stmt);
    oci_close($conn);


// ============================================
// ADMIN LOGIN
// ============================================
} elseif ($role === 'admin') {

    $email    = isset($input['email'])    ? trim($input['email'])    : '';
    $password = isset($input['password']) ? trim($input['password']) : '';

    error_log("[LOGIN] Admin attempt — Email: $email");

    if (empty($email) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email and password are required'
        ]);
        exit;
    }

    $conn = getDBConnection();

    // ---- BUG FIX: ADMIN is a reserved word in Oracle ----
    // It MUST be wrapped in double-quotes to use as a table name.
    // Without quotes → ORA-00903: invalid table name
    // -------------------------------------------------------
    // Also: if you stored passwords as MD5/SHA hash, wrap :pwd accordingly.
    // For plain-text passwords (college project), this query works as-is.
    $sql = 'SELECT AdminID, Name, Email, Password
            FROM "ADMIN"
            WHERE Email   = :email
              AND Password = :pwd';

    $stmt = oci_parse($conn, $sql);

    if (!$stmt) {
        $e = oci_error($conn);
        error_log("[LOGIN] oci_parse failed: " . $e['message']);
        echo json_encode(['success' => false, 'message' => 'Query preparation failed']);
        exit;
    }

    oci_bind_by_name($stmt, ':email', $email);
    oci_bind_by_name($stmt, ':pwd',   $password);

    $exec = oci_execute($stmt);

    if (!$exec) {
        $e = oci_error($stmt);
        error_log("[LOGIN] Admin query failed: " . $e['message']);
        echo json_encode([
            'success' => false,
            'message' => 'Query error: ' . $e['message'] // remove in production
        ]);
        oci_free_statement($stmt);
        oci_close($conn);
        exit;
    }

    $row = oci_fetch_assoc($stmt);
    error_log("[LOGIN] Admin row found: " . ($row ? 'YES' : 'NO'));

    if ($row) {
        $_SESSION['user_id']   = $row['ADMINID'];
        $_SESSION['user_name'] = $row['NAME'];
        $_SESSION['role']      = 'admin';

        echo json_encode([
            'success'  => true,
            'role'     => 'admin',
            'redirect' => 'admin_dashboard.html',
            'user'     => [
                'admin_id'    => $row['ADMINID'],
                'name'  => $row['NAME'],
                'email' => $row['EMAIL']
            ]
        ]);

    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
    }

    oci_free_statement($stmt);
    oci_close($conn);


// ============================================
// INVALID ROLE
// ============================================
} else {
    error_log("[LOGIN] Invalid role received: '$role'");
    echo json_encode([
        'success' => false,
        'message' => 'Invalid role. Expected: student or admin'
    ]);
}
?>