<?php
require_once 'config.php';

// --- SECURITY: Ensure only a logged-in student can access this file ---
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'student') {
    http_response_code(403); // Send a "Forbidden" status code
    exit('Unauthorized Access. Please log in as a student.');
}

// Get the logged-in student's PRN from the session
$student_prn = $_SESSION['user_prn'] ?? '';
if (empty($student_prn)) {
    http_response_code(400); // Bad Request
    exit('Student identifier not found in session.');
}

// --- HTTP HEADERS: Tell the browser to download the file ---
// Set the content type to CSV and define the filename for the download.
$filename = 'attendance_report_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $student_prn) . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// --- DATA FETCHING: Get the detailed attendance records ---
// This query is identical to the one in the dashboard for consistency.
$sql = "SELECT 
            a.attendance_date, 
            s.subject_name, 
            a.status
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.subject_id
        WHERE a.student_prn = ?
        ORDER BY a.attendance_date DESC, s.subject_name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_prn);
$stmt->execute();
$result = $stmt->get_result();

// --- CSV GENERATION: Write data directly to the browser ---
// Open the PHP output stream to write the CSV data.
$output = fopen('php://output', 'w');

// Write the header row for the CSV file
fputcsv($output, ['Date', 'Subject', 'Status']);

// Loop through the database results and write each row to the CSV file
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            date("d-M-Y", strtotime($row['attendance_date'])), // Format date for readability
            $row['subject_name'],
            $row['status']
        ]);
    }
}

// --- CLEANUP: Close resources ---
fclose($output);
$stmt->close();
$conn->close();
exit(); // Stop the script from outputting anything else
