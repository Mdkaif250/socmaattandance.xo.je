<?php
require_once 'config.php';

// Protect the page
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php?error=unauthorized');
    exit;
}

$teacher_name = $_SESSION['user_name'];
$teacher_prn = $_SESSION['user_prn'];

// --- 1. QUICK STATS ---
// Number of unique courses the teacher has taken attendance for
$courses_taught_sql = "SELECT COUNT(DISTINCT course_id) as count FROM attendance WHERE teacher_prn = ?";
$stmt = $conn->prepare($courses_taught_sql);
$stmt->bind_param("s", $teacher_prn);
$stmt->execute();
$courses_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Number of unique students the teacher has marked
$students_taught_sql = "SELECT COUNT(DISTINCT student_prn) as count FROM attendance WHERE teacher_prn = ?";
$stmt = $conn->prepare($students_taught_sql);
$stmt->bind_param("s", $teacher_prn);
$stmt->execute();
$students_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Total attendance records submitted
$records_taken_sql = "SELECT COUNT(*) as count FROM attendance WHERE teacher_prn = ?";
$stmt = $conn->prepare($records_taken_sql);
$stmt->bind_param("s", $teacher_prn);
$stmt->execute();
$records_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// --- 2. RECENT ATTENDANCE SESSIONS ---
$recent_sessions_sql = "SELECT 
                            a.attendance_date, c.course_name, s.subject_name,
                            a.course_id, a.subject_id, -- Added these for the dynamic link
                            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                            COUNT(a.student_prn) as total_count
                        FROM attendance a
                        JOIN courses c ON a.course_id = c.course_id
                        JOIN subjects s ON a.subject_id = s.subject_id
                        WHERE a.teacher_prn = ?
                        GROUP BY a.attendance_date, a.course_id, a.subject_id, c.course_name, s.subject_name
                        ORDER BY a.attendance_date DESC
                        LIMIT 4";
$stmt = $conn->prepare($recent_sessions_sql);
$stmt->bind_param("s", $teacher_prn);
$stmt->execute();
$recent_sessions = $stmt->get_result();

// --- 3. LOW ATTENDANCE ALERTS ---
$low_attendance_threshold = 75.0;
$low_attendance_sql = "SELECT 
                            u.name as student_name,
                            c.course_name,
                            (SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) * 100.0 / COUNT(a.student_prn)) AS percentage
                       FROM attendance a
                       JOIN users u ON a.student_prn = u.prn
                       JOIN courses c ON a.course_id = c.course_id
                       WHERE a.teacher_prn = ?
                       GROUP BY a.student_prn, a.course_id
                       HAVING percentage < ?
                       ORDER BY percentage ASC
                       LIMIT 5";
$stmt = $conn->prepare($low_attendance_sql);
$stmt->bind_param("sd", $teacher_prn, $low_attendance_threshold);
$stmt->execute();
$low_attendance_students = $stmt->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f7f8fc; color: #333; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: #2c3e50; color: #ecf0f1; display: flex; flex-direction: column; padding: 20px; flex-shrink: 0; }
        .sidebar .logo { text-align: center; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid #34495e; }
        .sidebar .logo h2 { font-weight: 700; }
        .sidebar .nav-links { list-style: none; flex-grow: 1; }
        .sidebar .nav-links a { display: flex; align-items: center; color: #ecf0f1; text-decoration: none; padding: 15px 10px; border-radius: 8px; margin-bottom: 10px; transition: background-color 0.3s; }
        .sidebar .nav-links a:hover, .sidebar .nav-links a.active { background-color: #3498db; color: #fff; }
        .sidebar .nav-links a i { margin-right: 15px; width: 20px; text-align: center; }
        .sidebar .logout-link a { background-color: #e74c3c; } .sidebar .logout-link a:hover { background-color: #c0392b; }
        .main-content { flex-grow: 1; padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-size: 28px; font-weight: 700; }
        .header .user-info { font-size: 16px; font-weight: 500; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin-bottom: 30px; }
        .stat-card { background-color: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; }
        .stat-card .icon { font-size: 30px; color: #fff; width: 60px; height: 60px; border-radius: 50%; display: grid; place-items: center; }
        .stat-card.blue .icon { background-color: #3498db; }
        .stat-card.green .icon { background-color: #2ecc71; }
        .stat-card.orange .icon { background-color: #f39c12; }
        .stat-card .info .value { font-size: 24px; font-weight: 700; }
        .stat-card .info .label { color: #666; font-size: 14px; }
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .card { background-color: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .card h3 { margin-top: 0; margin-bottom: 20px; font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .list-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .list-item:last-child { border-bottom: none; }
        .list-item .details .title { font-weight: 500; }
        .list-item .details .subtitle { font-size: 14px; color: #777; }
        .list-item .value { font-weight: 600; }
        .list-item .value.low { color: #e74c3c; }
        .no-data { text-align: center; color: #999; padding: 20px; }
        @media (max-width: 992px) { .content-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><h2>Teacher Panel</h2></div>
        <ul class="nav-links">
            <li><a href="teacher_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="take-attendance.php"><i class="fas fa-check-circle"></i> Take Attendance</a></li>
            <li><a href="view-attendance-history.php"><i class="fas fa-history"></i> View History</a></li>
        </ul>
        <div class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Dashboard</h1>
            <div class="user-info">Welcome, <strong><?php echo htmlspecialchars($teacher_name); ?></strong></div>
        </div>

        <div class="dashboard-grid">
            <div class="stat-card blue"><div class="icon"><i class="fas fa-chalkboard"></i></div><div class="info"><div class="value"><?php echo $courses_count; ?></div><div class="label">Courses Taught</div></div></div>
            <div class="stat-card green"><div class="icon"><i class="fas fa-users"></i></div><div class="info"><div class="value"><?php echo $students_count; ?></div><div class="label">Students Taught</div></div></div>
            <div class="stat-card orange"><div class="icon"><i class="fas fa-clipboard-check"></i></div><div class="info"><div class="value"><?php echo $records_count; ?></div><div class="label">Records Taken</div></div></div>
        </div>

        <div class="content-grid">
            <div class="card">
                <h3>Recent Attendance Sessions</h3>
                <?php if ($recent_sessions->num_rows > 0): ?>
                    <?php while($row = $recent_sessions->fetch_assoc()): ?>
                    <div class="list-item">
                        <div class="details">
                            <div class="title"><?php echo htmlspecialchars($row['course_name'] . ' - ' . $row['subject_name']); ?></div>
                            <div class="subtitle"><?php echo date("d M, Y", strtotime($row['attendance_date'])); ?></div>
                        </div>
                        <div class="value"><?php echo $row['present_count']; ?> / <?php echo $row['total_count']; ?> Present</div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="no-data">No recent sessions found.</p>
                <?php endif; ?>
            </div>
            <div class="card">
                <h3>Low Attendance Alerts</h3>
                <?php if ($low_attendance_students->num_rows > 0): ?>
                    <?php while($row = $low_attendance_students->fetch_assoc()): ?>
                    <div class="list-item">
                        <div class="details">
                            <div class="title"><?php echo htmlspecialchars($row['student_name']); ?></div>
                            <div class="subtitle"><?php echo htmlspecialchars($row['course_name']); ?></div>
                        </div>
                        <div class="value low"><?php echo round($row['percentage'], 1); ?>%</div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="no-data">No students are below the attendance threshold. Great job!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
