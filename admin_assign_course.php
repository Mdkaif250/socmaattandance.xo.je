<?php
require_once 'config.php';

// Protect the page: only admins can access
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php?error=unauthorized');
    exit;
}

$message = '';
$success = false;

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_course'])) {
    $teacher_prn = $_POST['teacher_prn'] ?? '';
    $course_id = $_POST['course_id'] ?? '';

    if (empty($teacher_prn) || empty($course_id)) {
        $message = "Please select both a teacher and a course to assign.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET course_id = ? WHERE prn = ? AND role = 'teacher'");
        $stmt->bind_param("is", $course_id, $teacher_prn);

        if ($stmt->execute()) {
            $success = true;
            $message = "Course assigned successfully!";
        } else {
            $message = "Error: Failed to assign the course. Please try again.";
        }
        $stmt->close();
    }
}

// --- FETCH DATA FOR DROPDOWNS ---
$teachers_result = $conn->query("SELECT prn, name FROM users WHERE role = 'teacher' ORDER BY name ASC");
$courses_result = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name ASC");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin: Assign Course to Teacher</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        /* Your existing professional styles... (unchanged) */
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
        .header h1 { font-size: 28px; font-weight: 700; margin-bottom: 30px; }
        .card { background-color: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); max-width: 600px; }
        .card h3 { margin-top: 0; margin-bottom: 20px; font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Poppins', sans-serif; }
        .btn { padding: 12px 30px; background-color: #3498db; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<div class="sidebar">
        <div class="logo"><h2>Admin Panel</h2></div>
        <ul class="nav-links">
            <li><a href="admin_dashboard.php" class="<?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="manage-users.php" class="<?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <!-- We assume you have an add_student.php page, if not, you can remove this link -->
            <li><a href="add-student.php" class="<?php echo ($current_page == 'add_student.php') ? 'active' : ''; ?>"><i class="fas fa-user-plus"></i> Add Student</a></li>
            <!-- The current page is now correctly highlighted as active -->
            <li><a href="add-teacher.php" class="<?php echo ($current_page == 'add_student.php') ? 'active' : ''; ?>""><i class="fas fa-user-tie"></i> Add Teacher</a></li>
            <li><a href="manage_courses.php" class="<?php echo ($current_page == 'manage_courses.php') ? 'active' : ''; ?>"><i class="fas fa-book"></i> Manage Courses</a></li>
            <li><a href="manage_subjects.php" class="<?php echo ($current_page == 'manage_subjects.php') ? 'active' : ''; ?>"><i class="fas fa-sitemap"></i> Manage Subjects</a></li>
            <li><a href="admin_assign_course.php" class="active"><i class="fas fa-chalkboard-teacher"></i> Assign Course</a></li>
        </ul>
        <div class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="header"><h1>Assign Teacher to Course</h1></div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>Assignment Form</h3>
            <form action="admin_assign_course.php" method="post">
                <div class="form-group">
                    <label for="teacher_prn">Select Teacher</label>
                    <select id="teacher_prn" name="teacher_prn" required>
                        <option value="">-- Choose a Teacher --</option>
                        <?php while($teacher = $teachers_result->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($teacher['prn']); ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="course_id">Select Course to Assign</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">-- Choose a Course --</option>
                        <?php while($course = $courses_result->fetch_assoc()): ?>
                            <option value="<?php echo $course['course_id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" name="assign_course" class="btn">Assign Course</button>
            </form>
        </div>
    </div>
</body>
</html>
