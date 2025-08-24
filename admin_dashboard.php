<?php
require_once 'config.php';

// Protect the page: only admins can access
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php?error=unauthorized');
    exit;
}

$admin_name = $_SESSION['user_name'];

// --- FETCH DASHBOARD STATS ---
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
$total_teachers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")->fetch_assoc()['count'] ?? 0;
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'] ?? 0;
$total_courses = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'] ?? 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        /* Your existing professional CSS here... (unchanged) */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f7f8fc; color: #333; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: #2c3e50; color: #ecf0f1; display: flex; flex-direction: column; padding: 20px; flex-shrink: 0; }
        .sidebar .logo { text-align: center; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid #34495e; }
        .sidebar .logo h2 { font-weight: 700; }
        .sidebar .nav-links { list-style: none; flex-grow: 1; }
        .sidebar .nav-links a { display: flex; align-items: center; color: #ecf0f1; text-decoration: none; padding: 15px 10px; border-radius: 8px; margin-bottom: 10px; transition: background-color 0.3s; }
        .sidebar .nav-links a:hover, .sidebar .nav-links a.active { background-color: #3498db; }
        .sidebar .nav-links a i { margin-right: 15px; width: 20px; text-align: center; }
        .sidebar .logout-link a { background-color: #e74c3c; padding: 12px; border-radius: 6px; display: block; text-align: center; color: #fff; text-decoration: none; }
        .sidebar .logout-link a:hover { background-color: #c0392b; }
        .main-content { flex-grow: 1; padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-size: 28px; font-weight: 700; }
        .header .user-info { font-size: 16px; font-weight: 500; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 30px; margin-bottom: 30px; }
        .stat-card { background-color: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; }
        .stat-card .icon { font-size: 28px; color: #fff; width: 60px; height: 60px; border-radius: 12px; display: grid; place-items: center; }
        .stat-card.blue .icon { background-color: #3498db; }
        .stat-card.green .icon { background-color: #2ecc71; }
        .stat-card.purple .icon { background-color: #9b59b6; }
        .stat-card.orange .icon { background-color: #f39c12; }
        .stat-card .info .value { font-size: 24px; font-weight: 700; }
        .stat-card .info .label { color: #666; font-size: 14px; }
        .card { background-color: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .card h3 { margin-top: 0; margin-bottom: 20px; font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .action-links { display: flex; flex-wrap: wrap; gap: 20px; }
        .action-links .btn { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; width: 180px; height: 120px; padding: 20px; background-color: #f8f9fa; color: #333; text-decoration: none; border-radius: 10px; border: 1px solid #eee; transition: transform 0.2s, box-shadow 0.2s; }
        .action-links .btn:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .action-links .btn i { font-size: 28px; margin-bottom: 10px; color: #3498db; }
        .action-links .btn span { font-weight: 500; }
    </style>
</head>
<body>
<div class="sidebar">
        <div class="logo"><h2>Admin Panel</h2></div>
        <ul class="nav-links">
            <li><a href="admin_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="manage-users.php" class="<?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <!-- We assume you have an add_student.php page, if not, you can remove this link -->
            <li><a href="add-student.php" class="<?php echo ($current_page == 'add_student.php') ? 'active' : ''; ?>"><i class="fas fa-user-plus"></i> Add Student</a></li>
            <!-- The current page is now correctly highlighted as active -->
            <li><a href="add-teacher.php" class="<?php echo ($current_page == 'add_student.php') ? 'active' : ''; ?>""><i class="fas fa-user-tie"></i> Add Teacher</a></li>
            <li><a href="manage_courses.php" class="<?php echo ($current_page == 'manage_courses.php') ? 'active' : ''; ?>"><i class="fas fa-book"></i> Manage Courses</a></li>
            <li><a href="manage_subjects.php" class="<?php echo ($current_page == 'manage_subjects.php') ? 'active' : ''; ?>"><i class="fas fa-sitemap"></i> Manage Subjects</a></li>
            <li><a href="admin_assign_course.php" class="<?php echo ($current_page == 'admin_assign_course.php') ? 'active' : ''; ?>"><i class="fas fa-chalkboard-teacher"></i> Assign Course</a></li>
        </ul>
        <div class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <div class="user-info">Welcome, <strong><?php echo htmlspecialchars($admin_name); ?></strong></div>
        </div>

        <div class="stat-grid">
            <div class="stat-card blue"><div class="icon"><i class="fas fa-users"></i></div><div class="info"><div class="value"><?php echo $total_users; ?></div><div class="label">Total Users</div></div></div>
            <div class="stat-card green"><div class="icon"><i class="fas fa-user-graduate"></i></div><div class="info"><div class="value"><?php echo $total_students; ?></div><div class="label">Total Students</div></div></div>
            <div class="stat-card purple"><div class="icon"><i class="fas fa-chalkboard-teacher"></i></div><div class="info"><div class="value"><?php echo $total_teachers; ?></div><div class="label">Total Teachers</div></div></div>
            <div class="stat-card orange"><div class="icon"><i class="fas fa-book-open"></i></div><div class="info"><div class="value"><?php echo $total_courses; ?></div><div class="label">Total Courses</div></div></div>
        </div>

        <div class="card">
            <h3>Quick Actions</h3>
            <div class="action-links">
                <a href="manage-users.php" class="btn">
                    <i class="fas fa-users-cog"></i>
                    <span>Manage Users</span>
                </a>
                <a href="add-student.php" class="btn">
                    <i class="fas fa-user-plus"></i>
                    <span>Add Student</span>
                </a>
                <a href="add-teacher.php" class="btn">
                    <i class="fas fa-user-tie"></i>
                    <span>Add Teacher</span>
                </a>
                <a href="admin_assign_course.php" class="btn">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Assign Teacher</span>
                </a>
                <a href="manage_courses.php" class="btn">
                    <i class="fas fa-book"></i>
                    <span>Manage Courses</span>
                </a>
                <a href="manage_subjects.php" class="btn">
                    <i class="fas fa-sitemap"></i>
                    <span>Manage Subjects</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
