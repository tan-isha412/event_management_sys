<?php


define('DB_HOST', 'localhost');
define('DB_PORT', '1521');
define('DB_SID',  'XE');         
define('DB_USER', 'system');     
define('DB_PASS', 'student');    

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


function getDBConnection() {
    $dsn = DB_HOST . ':' . DB_PORT . '/' . DB_SID;

    $conn = oci_connect(DB_USER, DB_PASS, $dsn, 'AL32UTF8');

    if (!$conn) {
        $e = oci_error();
        error_log("[DB] Connection failed: " . $e['message']);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed',
            'detail'  => $e['message'] 
        ]);
        exit;
    }

    return $conn;
}
?>