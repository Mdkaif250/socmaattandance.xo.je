<?php
require_once 'config.php';

// Protect this page - only admins should access it
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php?error=unauthorized');
    exit;
}

$message = "";
$success = false;

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and trim form data
    $prn = trim($_POST['prn']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $course_id = $_POST['course_id'];
    $academic_year = $_POST['academic_year']; // <-- Get the new academic year
    $roll_no = trim($_POST['roll_no']);
    $division = trim($_POST['division']);

    // --- Validation (now includes academic_year) ---
    if (empty($prn) || empty($name) || empty($email) || empty($password) || empty($course_id) || empty($academic_year) || empty($roll_no) || empty($division)) {
        $message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "The email address is not in a valid format.";
    } elseif (strlen($password) < 6) {
        $message = "The password must be at least 6 characters long.";
    } else {
        // Check for duplicates
        $stmt_check = $conn->prepare("SELECT prn FROM users WHERE prn = ? OR email = ?");
        $stmt_check->bind_param("ss", $prn, $email);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $message = "A user with this PRN or Email already exists.";
        } else {
            // --- Create the student account with the academic year ---
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'student';
            
            // MODIFIED INSERT STATEMENT to include the new column
            $stmt_insert = $conn->prepare("INSERT INTO users (prn, name, email, password, role, course_id, academic_year, roll_no, division) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("sssssiiss", $prn, $name, $email, $hashed_password, $role, $course_id, $academic_year, $roll_no, $division);

            if ($stmt_insert->execute()) {
                $success = true;
                $message = "Student account for '" . htmlspecialchars($name) . "' has been created successfully!";
            } else {
                $message = "An error occurred. Could not create the account.";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}

// Fetch courses for the dropdown menu
$courses_result = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin: Add New Student</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        /* Your standard admin panel CSS */
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
        .card { background-color: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); max-width: 800px; }
        .card h3 { margin-top: 0; margin-bottom: 20px; font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Poppins', sans-serif; }
        .btn { width: 100%; padding: 15px; border: none; border-radius: 8px; background-color: #3498db; color: #fff; font-size: 16px; cursor: pointer; margin-top: 10px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid transparent; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
    <div class="sidebar">
        <div class="logo"><h2>Admin Panel</h2></div>
        <ul class="nav-links">
            <li><a href="admin_dashboard.php" class="<?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="manage_users.php" class="<?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="add-student.php" class="active"><i class="fas fa-user-plus"></i> Add Student</a></li>
            <li><a href="add-teacher.php" class="<?php echo ($current_page == 'add_teacher.php') ? 'active' : ''; ?>"><i class="fas fa-user-tie"></i> Add Teacher</a></li>
            <li><a href="manage_courses.php" class="<?php echo ($current_page == 'manage_courses.php') ? 'active' : ''; ?>"><i class="fas fa-book"></i> Manage Courses</a></li>
            <li><a href="manage_subjects.php" class="<?php echo ($current_page == 'manage_subjects.php') ? 'active' : ''; ?>"><i class="fas fa-sitemap"></i> Manage Subjects</a></li>
            <li><a href="admin_assign_course.php" class="<?php echo ($current_page == 'admin_assign_course.php') ? 'active' : ''; ?>"><i class="fas fa-chalkboard-teacher"></i> Assign Course</a></li>
        </ul>
        <div class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="header"><h1>Add New Student</h1></div>
        
        <div class="card">
            <h3>Student Registration Form</h3>
            
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form action="add-student.php" method="post" novalidate>
                <div class="form-grid">
                    <div class="form-group"><label for="prn">Student PRN</label><input type="text" id="prn" name="prn" required></div>
                    <div class="form-group"><label for="name">Full Name</label><input type="text" id="name" name="name" required></div>
                    <div class="form-group"><label for="email">Email Address</label><input type="email" id="email" name="email" required></div>
                    <div class="form-group"><label for="password">Initial Password</label><input type="password" id="password" name="password" required></div>
                    <div class="form-group">
                        <label for="course_id">Course</label>
                        <select id="course_id" name="course_id" required>
                            <option value="">-- Select a Course --</option>
                            <?php while($course = $courses_result->fetch_assoc()): ?>
                                <option value="<?php echo $course['course_id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <!-- NEW ACADEMIC YEAR DROPDOWN -->
                    <div class="form-group">
                        <label for="academic_year">Academic Year</label>
                        <select id="academic_year" name="academic_year" required>
                            <option value="">-- Select a Year --</option>
                            <option value="1">First Year (FY)</option>
                            <option value="2">Second Year (SY)</option>
                            <option value="3">Third Year (TY)</option>
                        </select>
                    </div>
                    <div class="form-group"><label for="roll_no">Roll Number</label><input type="text" id="roll_no" name="roll_no" required></div>
                    <div class="form-group"><label for="division">Division</label><input type="text" id="division" name="division" placeholder="e.g., A, B" required></div>
                </div>
                <button type="submit" class="btn">Create Student Account</button>
            </form>
        </div>
    </div>
</body>
</html>
