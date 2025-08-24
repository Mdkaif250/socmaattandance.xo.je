<?php
require_once 'config.php';

// Protect this page - only admins should access it
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php?error=unauthorized');
    exit;
}

$message = "";
$success = false;
$user_prn = $_GET['prn'] ?? '';
$user = null;

if (empty($user_prn)) {
    header('Location: manage_users.php');
    exit;
}

// --- HANDLE FORM SUBMISSION FOR UPDATING USER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $prn_to_update = $_POST['prn'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $new_password = $_POST['password']; // Can be empty

    // Student-specific fields
    $course_id = $_POST['course_id'] ?? null;
    $academic_year = $_POST['academic_year'] ?? null;
    $roll_no = $_POST['roll_no'] ?? null;
    $division = $_POST['division'] ?? null;
    $role = $_POST['role']; // Get the user's role

    // Basic validation
    if (empty($name) || empty($email)) {
        $message = "Name and Email are required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } else {
        // Build the query dynamically
        $sql = "UPDATE users SET name = ?, email = ?";
        $params = [$name, $email];
        $types = "ss";

        // Only add password to the query if a new one was provided
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                $message = "New password must be at least 6 characters long.";
            } else {
                $sql .= ", password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                $types .= "s";
            }
        }

        // Add student-specific fields if the user is a student
        if ($role === 'student') {
            $sql .= ", course_id = ?, academic_year = ?, roll_no = ?, division = ?";
            array_push($params, $course_id, $academic_year, $roll_no, $division);
            $types .= "iiss";
        }

        $sql .= " WHERE prn = ?";
        $params[] = $prn_to_update;
        $types .= "s";

        // Execute the query only if no password validation error occurred
        if (empty($message)) {
            $stmt_update = $conn->prepare($sql);
            $stmt_update->bind_param($types, ...$params);

            if ($stmt_update->execute()) {
                $success = true;
                $message = "User details updated successfully!";
            } else {
                $message = "An error occurred. Could not update user.";
            }
            $stmt_update->close();
        }
    }
}

// --- FETCH USER DATA TO PRE-FILL THE FORM ---
$stmt_fetch = $conn->prepare("SELECT * FROM users WHERE prn = ?");
$stmt_fetch->bind_param("s", $user_prn);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();
if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
} else {
    // If user not found, redirect back to the manage page
    header('Location: manage-users.php');
    exit;
}
$stmt_fetch->close();

// Fetch courses for the dropdown menu
$courses_result = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin: Edit User</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        /* Your standard admin panel CSS */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f7f8fc; color: #333; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: #2c3e50; color: #ecf0f1; display: flex; flex-direction: column; padding: 20px; flex-shrink: 0; }
        .sidebar .logo { text-align: center; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid #34495e; }
        .sidebar .nav-links { list-style: none; flex-grow: 1; }
        .sidebar .nav-links a { display: flex; align-items: center; color: #ecf0f1; text-decoration: none; padding: 15px 10px; border-radius: 8px; margin-bottom: 10px; }
        .sidebar .nav-links a:hover, .sidebar .nav-links a.active { background-color: #3498db; }
        .main-content { flex-grow: 1; padding: 40px; }
        .header h1 { font-size: 28px; font-weight: 700; margin-bottom: 30px; }
        .card { background-color: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); max-width: 800px; }
        .card h3 { margin-top: 0; margin-bottom: 20px; font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; }
        .form-group .locked-field { background-color: #f0f0f0; }
        .btn { padding: 15px; border: none; border-radius: 8px; background-color: #3498db; color: #fff; font-size: 16px; cursor: pointer; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
    <div class="sidebar">
        <!-- Your full admin sidebar HTML here -->
        <div class="logo"><h2>Admin Panel</h2></div>
        <ul class="nav-links">
            <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="manage-users.php" class="active"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <!-- ... other links -->
        </ul>
        <div class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="header"><h1>Edit User Details</h1></div>
        
        <div class="card">
            <h3>Editing account for "<?php echo htmlspecialchars($user['name']); ?>"</h3>
            
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form action="edit_user.php?prn=<?php echo urlencode($user['prn']); ?>" method="post" novalidate>
                <!-- Hidden fields to pass the PRN and role back to the server -->
                <input type="hidden" name="prn" value="<?php echo htmlspecialchars($user['prn']); ?>">
                <input type="hidden" name="role" value="<?php echo htmlspecialchars($user['role']); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="prn_display">PRN (Cannot be changed)</label>
                        <input type="text" id="prn_display" class="locked-field" value="<?php echo htmlspecialchars($user['prn']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password">New Password (leave blank to keep current)</label>
                        <input type="password" id="password" name="password" placeholder="Enter new password...">
                    </div>

                    <?php if ($user['role'] === 'student'): ?>
                        <!-- These fields only show if the user is a student -->
                        <div class="form-group">
                            <label for="course_id">Course</label>
                            <select id="course_id" name="course_id" required>
                                <option value="">-- Select a Course --</option>
                                <?php mysqli_data_seek($courses_result, 0); ?>
                                <?php while($course = $courses_result->fetch_assoc()): ?>
                                    <option value="<?php echo $course['course_id']; ?>" <?php echo ($user['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="academic_year">Academic Year</label>
                            <select id="academic_year" name="academic_year" required>
                                <option value="">-- Select a Year --</option>
                                <option value="1" <?php echo ($user['academic_year'] == 1) ? 'selected' : ''; ?>>First Year (FY)</option>
                                <option value="2" <?php echo ($user['academic_year'] == 2) ? 'selected' : ''; ?>>Second Year (SY)</option>
                                <option value="3" <?php echo ($user['academic_year'] == 3) ? 'selected' : ''; ?>>Third Year (TY)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="roll_no">Roll Number</label>
                            <input type="text" id="roll_no" name="roll_no" value="<?php echo htmlspecialchars($user['roll_no']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="division">Division</label>
                            <input type="text" id="division" name="division" value="<?php echo htmlspecialchars($user['division']); ?>" required>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn" style="width: auto;">Update User Details</button>
                <a href="manage-users.php" style="display:inline-block; margin-left: 15px;">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>
