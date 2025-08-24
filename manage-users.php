<?php
require_once 'config.php';

// Protect this page - only admins should access it
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php?error=unauthorized');
    exit;
}

// --- HANDLE FILTERS AND SEARCH ---
$search_term = $_GET['search'] ?? '';
$course_filter = $_GET['course_id'] ?? '';

// --- FETCH DATA FROM DATABASE ---

// 1. Fetch Teachers
$teacher_sql = "SELECT prn, name, email FROM users WHERE role = 'teacher'";
if (!empty($search_term)) {
    $teacher_sql .= " AND (name LIKE ? OR prn LIKE ? OR email LIKE ?)";
}
$stmt_teacher = $conn->prepare($teacher_sql);
if (!empty($search_term)) {
    $like_search = "%" . $search_term . "%";
    $stmt_teacher->bind_param('sss', $like_search, $like_search, $like_search);
}
$stmt_teacher->execute();
$teachers = $stmt_teacher->get_result();

// 2. Fetch Students
$student_sql = "SELECT u.prn, u.name, u.email, u.roll_no, u.division, c.course_name 
                FROM users u 
                LEFT JOIN courses c ON u.course_id = c.course_id 
                WHERE u.role = 'student'";
$params_student = [];
$types_student = '';
if (!empty($search_term)) {
    $student_sql .= " AND (u.name LIKE ? OR u.prn LIKE ? OR u.email LIKE ?)";
    $like_search = "%" . $search_term . "%";
    array_push($params_student, $like_search, $like_search, $like_search);
    $types_student .= 'sss';
}
if (!empty($course_filter)) {
    $student_sql .= " AND u.course_id = ?";
    array_push($params_student, $course_filter);
    $types_student .= 'i';
}
$student_sql .= " ORDER BY c.course_name, u.roll_no";
$stmt_student = $conn->prepare($student_sql);
if (!empty($params_student)) {
    $stmt_student->bind_param($types_student, ...$params_student);
}
$stmt_student->execute();
$students = $stmt_student->get_result();

// 3. Fetch courses for the filter dropdown
$courses_result = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        /* Using the same professional styles from your other pages for consistency */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f7f8fc; color: #333; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: #2c3e50; color: #ecf0f1; display: flex; flex-direction: column; padding: 20px; flex-shrink: 0; }
        .sidebar .logo { text-align: center; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid #34495e; }
        .sidebar .logo h2 { font-weight: 700; }
        .sidebar .nav-links { list-style: none; flex-grow: 1; }
        .sidebar .nav-links a { display: flex; align-items: center; color: #ecf0f1; text-decoration: none; padding: 15px 10px; border-radius: 8px; margin-bottom: 10px; }
        .sidebar .nav-links a:hover, .sidebar .nav-links a.active { background-color: #3498db; }
        .sidebar .nav-links a i { margin-right: 15px; width: 20px; text-align: center; }
        .sidebar .logout-link a { background-color: #e74c3c; padding: 12px; border-radius: 6px; display: block; text-align: center; color: #fff; text-decoration: none; }
        .main-content { flex-grow: 1; padding: 40px; }
        .header h1 { font-size: 28px; font-weight: 700; margin-bottom: 30px; }
        .card { background-color: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .card h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; font-weight: 600; }
        .filter-form { display: flex; flex-wrap: wrap; gap: 20px; align-items: center; }
        .filter-form input, .filter-form select { padding: 12px; border: 1px solid #ddd; border-radius: 8px; flex-grow: 1; min-width: 200px; }
        .filter-form button { padding: 12px 25px; border: none; background: #3498db; color: #fff; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .filter-form a { color: #555; text-decoration: none; padding: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; white-space: nowrap; }
        th { background-color: #f8f9fa; font-weight: 600; }
        .action-btn { display: inline-block; padding: 6px 12px; font-size: 13px; font-weight: 600; color: #fff; background-color: #3498db; border-radius: 6px; text-decoration: none; }
    </style>
</head>
<body>
<div class="sidebar">
        <div class="logo"><h2>Admin Panel</h2></div>
        <ul class="nav-links">
            <li><a href="admin_dashboard.php" class="<?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="manage-users.php" class="active"><i class="fas fa-users-cog"></i> Manage Users</a></li>
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
        <div class="header"><h1>Manage Users</h1></div>

        <div class="card">
            <form action="manage_users.php" method="get" class="filter-form">
                <input type="search" name="search" placeholder="Search by name, PRN, or email..." value="<?php echo htmlspecialchars($search_term); ?>">
                <select name="course_id">
                    <option value="">-- Filter by Course --</option>
                    <?php mysqli_data_seek($courses_result, 0); ?>
                    <?php while ($course = $courses_result->fetch_assoc()): ?>
                        <option value="<?php echo $course['course_id']; ?>" <?php echo ($course_filter == $course['course_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit">Filter</button>
                <a href="manage_users.php">Clear</a>
            </form>
        </div>

        <div class="card">
            <h2>Teachers</h2>
            <div style="overflow-x:auto;">
                <table>
                    <thead><tr><th>PRN / ID</th><th>Name</th><th>Email</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if ($teachers->num_rows > 0): while ($row = $teachers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['prn']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><a href="edit_user.php?prn=<?php echo urlencode($row['prn']); ?>" class="action-btn">Edit</a></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="4" style="text-align:center;">No teachers found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Students</h2>
            <div style="overflow-x:auto;">
                <table>
                    <thead><tr><th>Course</th><th>Roll No.</th><th>Name</th><th>PRN</th><th>Division</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if ($students->num_rows > 0): while ($row = $students->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['course_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['roll_no']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['prn']); ?></td>
                                <td><?php echo htmlspecialchars($row['division']); ?></td>
                                <td><a href="edit_user.php?prn=<?php echo urlencode($row['prn']); ?>" class="action-btn">Edit</a></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="6" style="text-align:center;">No students found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
