<?php
require_once 'config.php';

// Protect the page
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php?error=unauthorized');
    exit;
}

// Student info from session
$student_name = $_SESSION['user_name'];
$student_prn = $_SESSION['user_prn'];

// --- FETCH ATTENDANCE SUMMARY ---
$total_classes_sql = "SELECT COUNT(DISTINCT attendance_date, subject_id) as total 
                      FROM attendance 
                      WHERE student_prn = ?";
$stmt = $conn->prepare($total_classes_sql);
$stmt->bind_param("s", $student_prn);
$stmt->execute();
$total_result = $stmt->get_result()->fetch_assoc();
$total_classes = $total_result['total'] ?? 0;

$present_classes_sql = "SELECT COUNT(DISTINCT attendance_date, subject_id) as present 
                        FROM attendance 
                        WHERE student_prn = ? AND status = 'Present'";
$stmt = $conn->prepare($present_classes_sql);
$stmt->bind_param("s", $student_prn);
$stmt->execute();
$present_result = $stmt->get_result()->fetch_assoc();
$present_classes = $present_result['present'] ?? 0;

// Attendance percentage
$attendance_percentage = ($total_classes > 0) ? round(($present_classes / $total_classes) * 100, 2) : 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f7f8fc; color: #333; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: #2c3e50; color: #ecf0f1; display: flex; flex-direction: column; padding: 20px; flex-shrink: 0; }
        .sidebar .logo { text-align: center; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid #34495e; }
        .sidebar .logo h2 { font-weight: 700; color: #fff; }
        .sidebar .nav-links { list-style: none; flex-grow: 1; }
        .sidebar .nav-links a { display: flex; align-items: center; color: #ecf0f1; text-decoration: none; padding: 15px 10px; border-radius: 8px; margin-bottom: 10px; transition: background-color 0.3s, color 0.3s; }
        .sidebar .nav-links a:hover, .sidebar .nav-links a.active { background-color: #3498db; color: #fff; }
        .sidebar .nav-links a i { margin-right: 15px; width: 20px; text-align: center; }
        .sidebar .logout-link a { background-color: #e74c3c; padding: 12px; border-radius: 6px; display: block; text-align: center; }
        .sidebar .logout-link a:hover { background-color: #c0392b; }
        
        .main-content { flex-grow: 1; padding: 40px; overflow-x: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .header h1 { font-size: 28px; font-weight: 700; }
        .user-info { font-size: 16px; font-weight: 500; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; }
        .card { background-color: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); text-align: center; }
        .card i { font-size: 40px; color: #3498db; margin-bottom: 20px; }
        .card h3 { margin-bottom: 15px; font-weight: 600; }
        .card p { color: #666; margin-bottom: 25px; }
        .card .btn { display: inline-block; padding: 12px 25px; background-color: #3498db; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 500; }
        .card .btn:hover { background-color: #2980b9; }
        .highlight { font-size: 28px; font-weight: 700; color: #27ae60; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><h2>Student Panel</h2></div>
        <ul class="nav-links">
            <li><a href="student_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="student-attendance.php"><i class="fas fa-clipboard-list"></i> My Attendance</a></li>
            <li><a href="student-profile.php"><i class="fas fa-user"></i> Profile</a></li>
        </ul>
        <div class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Dashboard</h1>
            <div class="user-info">Welcome, <strong><?php echo htmlspecialchars($student_name); ?></strong></div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <i class="fas fa-percentage"></i>
                <h3>Attendance Overview</h3>
                <p>Your overall attendance percentage:</p>
                <div class="highlight"><?php echo $attendance_percentage; ?>%</div>
            </div>
            <div class="card">
                <i class="fas fa-clipboard-list"></i>
                <h3>View Detailed Attendance</h3>
                <p>Check your subject-wise attendance records.</p>
                <a href="student-attendance.php" class="btn">View Attendance</a>
            </div>
            <div class="card">
                <i class="fas fa-user"></i>
                <h3>My Profile</h3>
                <p>Check and update your details.</p>
                <a href="student-profile.php" class="btn">Go to Profile</a>
            </div>
        </div>
    </div>
</body>
</html>
