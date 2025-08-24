<?php
// --- ERROR REPORTING (VERY IMPORTANT FOR DEBUGGING) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- DATABASE CREDENTIALS ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'college_attendance');

// --- START THE SESSION ---
// This must be called before any HTML is output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- DATABASE CONNECTION ---
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
} catch(mysqli_sql_exception $e) {
    die("ERROR: Could not connect to the database. " . $e->getMessage());
}
?>
