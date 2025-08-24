<?php
require_once 'config.php';

// Protect this page - only admins should access it
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php?error=unauthorized');
    exit;
}

$message = '';
$success = false;
$edit_mode = false;
$course_to_edit = null;

// --- HANDLE DELETE REQUEST ---
if (isset($_GET['delete'])) {
    $course_id_to_delete = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
    $stmt->bind_param("i", $course_id_to_delete);
    if ($stmt->execute()) {
        $success = true;
        $message = "Course deleted successfully.";
    } else {
        $message = "Error: Could not delete course. It might be in use.";
    }
    $stmt->close();
}

// --- HANDLE ADD/UPDATE REQUEST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // UPDATE an existing course
    if (isset($_POST['update_course'])) {
        $course_id = $_POST['course_id'];
        $course_name = trim($_POST['course_name']);
        $stmt = $conn->prepare("UPDATE courses SET course_name = ? WHERE course_id = ?");
        $stmt->bind_param("si", $course_name, $course_id);
        if ($stmt->execute()) {
            $success = true;
            $message = "Course updated successfully.";
        }
        $stmt->close();
    }
    // ADD a new course
    elseif (isset($_POST['add_course'])) {
        $course_name = trim($_POST['course_name']);
        $stmt = $conn->prepare("INSERT INTO courses (course_name) VALUES (?)");
        $stmt->bind_param("s", $course_name);
        if ($stmt->execute()) {
            $success = true;
            $message = "Course added successfully.";
        }
        $stmt->close();
    }
}

// --- HANDLE EDIT REQUEST (to show the form) ---
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $course_id_to_edit = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ?");
    $stmt->bind_param("i", $course_id_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    $course_to_edit = $result->fetch_assoc();
    $stmt->close();
}

// --- FETCH ALL COURSES FOR THE LIST ---
$courses_result = $conn->query("SELECT * FROM courses ORDER BY course_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin: Manage Courses</title>
    <!-- Head content with styles and scripts -->
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
        .sidebar .nav-links a:hover, .sidebar .nav-links a.active { background-color: #3498db; }
        .sidebar .nav-links a i { margin-right: 15px; width: 20px; text-align: center; }
        .sidebar .logout-link a { background-color: #e74c3c; padding: 12px; border-radius: 6px; display: block; text-align: center; color: #fff; text-decoration: none; }
        .sidebar .logout-link a:hover { background-color: #c0392b; }
        .main-content { flex-grow: 1; padding: 40px; }
        .header h1 { font-size: 28px; margin-bottom: 30px; }
        .card { background-color: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; }
        .btn { padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .btn-primary { background-color: #3498db; color: #fff; }
        .btn-secondary { background-color: #6c757d; color: #fff; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        .action-links a { margin-right: 10px; color: #3498db; text-decoration: none; }
        .action-links a.delete { color: #e74c3c; }
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
            <li><a href="manage_courses.php" class="active"><i class="fas fa-book"></i> Manage Courses</a></li>
            <li><a href="manage_subjects.php" class="<?php echo ($current_page == 'manage_subjects.php') ? 'active' : ''; ?>"><i class="fas fa-sitemap"></i> Manage Subjects</a></li>
            <li><a href="admin_assign_course.php" class="<?php echo ($current_page == 'admin_assign_course.php') ? 'active' : ''; ?>"><i class="fas fa-chalkboard-teacher"></i> Assign Course</a></li>
        </ul>
        <div class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>
    <div class="main-content">
        <div class="header"><h1>Manage Courses</h1></div>
        <div class="card">
            <h3><?php echo $edit_mode ? 'Edit Course' : 'Add New Course'; ?></h3>
            <form action="manage_courses.php" method="post">
                <div class="form-group" style="display:flex; gap:15px; align-items:flex-end;">
                    <div style="flex-grow:1;">
                        <label for="course_name">Course Name</label>
                        <input type="text" id="course_name" name="course_name" value="<?php echo htmlspecialchars($course_to_edit['course_name'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="course_id" value="<?php echo $course_to_edit['course_id']; ?>">
                            <button type="submit" name="update_course" class="btn btn-primary">Update Course</button>
                            <a href="manage_courses.php" class="btn btn-secondary">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_course" class="btn btn-primary">Add Course</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        <div class="card">
            <h3>Existing Courses</h3>
            <table>
                <thead><tr><th>Course Name</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php while($row = $courses_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                        <td class="action-links">
                            <a href="manage_courses.php?edit=<?php echo $row['course_id']; ?>">Edit</a>
                            <a href="manage_courses.php?delete=<?php echo $row['course_id']; ?>" class="delete" onclick="return confirm('Are you sure you want to delete this course?');">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
