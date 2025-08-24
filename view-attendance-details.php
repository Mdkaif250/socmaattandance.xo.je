<?php
require_once 'config.php';

// Protect the page
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php?error=unauthorized');
    exit;
}

$teacher_name = $_SESSION['user_name'];

// --- 1. GET PARAMETERS FROM URL ---
$attendance_date = $_GET['date'] ?? '';
$course_id = $_GET['course_id'] ?? '';
$subject_id = $_GET['subject_id'] ?? '';

if (empty($attendance_date) || empty($course_id) || empty($subject_id)) {
    header('Location: view-attendance-history.php');
    exit;
}

// --- 2. FETCH DETAILED ATTENDANCE DATA ---
$sql = "SELECT 
            u.name as student_name,
            u.roll_no,
            a.status
        FROM attendance a
        JOIN users u ON a.student_prn = u.prn
        WHERE a.attendance_date = ? AND a.course_id = ? AND a.subject_id = ?
        ORDER BY u.roll_no ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $attendance_date, $course_id, $subject_id);
$stmt->execute();
$attendance_details = $stmt->get_result();

// Fetch course and subject names for the header
$course_info = $conn->query("SELECT course_name FROM courses WHERE course_id = $course_id")->fetch_assoc();
$subject_info = $conn->query("SELECT subject_name FROM subjects WHERE subject_id = $subject_id")->fetch_assoc();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        /* This is the same professional CSS from the other pages */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f7f8fc; color: #333; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: #2c3e50; color: #ecf0f1; display: flex; flex-direction: column; padding: 20px; flex-shrink: 0; }
        .sidebar .logo { text-align: center; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid #34495e; }
        .sidebar .logo h2 { font-weight: 700; }
        .sidebar .nav-links { list-style: none; flex-grow: 1; }
        .sidebar .nav-links a { display: flex; align-items: center; color: #ecf0f1; text-decoration: none; padding: 15px 10px; border-radius: 8px; margin-bottom: 10px; transition: background-color 0.3s, color 0.3s; }
        .sidebar .nav-links a:hover, .sidebar .nav-links a.active { background-color: #3498db; color: #fff; }
        .sidebar .nav-links a i { margin-right: 15px; width: 20px; text-align: center; }
        .sidebar .logout-link a { background-color: #e74c3c; } .sidebar .logout-link a:hover { background-color: #c0392b; }
        .main-content { flex-grow: 1; padding: 40px; overflow-x: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .header h1 { font-size: 28px; font-weight: 700; }
        .header .user-info { font-size: 16px; font-weight: 500; }
        .card { background-color: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); }
        .card h3 { margin-bottom: 10px; font-weight: 600; }
        .card .sub-header { color: #666; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; }
        .status-present { color: #28a745; font-weight: 600; }
        .status-absent { color: #dc3545; font-weight: 600; }
        .back-link { display: inline-block; margin-top: 30px; color: #3498db; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><h2>Teacher Panel</h2></div>
        <ul class="nav-links">
            <li><a href="teacher_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="take-attendance.php"><i class="fas fa-check-circle"></i> Take Attendance</a></li>
            <li><a href="view-attendance-history.php" class="active"><i class="fas fa-history"></i> View History</a></li>
        </ul>
        <div class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Attendance Details</h1>
            <div class="user-info">Welcome, <strong><?php echo htmlspecialchars($teacher_name); ?></strong></div>
        </div>

        <div class="card">
            <h3><?php echo htmlspecialchars($course_info['course_name'] . ' - ' . $subject_info['subject_name']); ?></h3>
            <p class="sub-header">Date: <?php echo date("F j, Y", strtotime($attendance_date)); ?></p>
            
            <table>
                <thead>
                    <tr>
                        <th>Roll No.</th>
                        <th>Student Name</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($attendance_details->num_rows > 0): ?>
                        <?php while($row = $attendance_details->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['roll_no']); ?></td>
                                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td>
                                    <span class="status-<?php echo strtolower($row['status']); ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 20px;">No details found for this session.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <a href="view-attendance-history.php" class="back-link">&larr; Back to History Summary</a>
        </div>
    </div>
</body>
</html>
