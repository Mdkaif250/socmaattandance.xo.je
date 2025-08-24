<?php
require_once 'config.php';

// Guard: only students
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php?error=unauthorized');
    exit;
}

$student_prn  = $_SESSION['user_prn'];
$student_name = $_SESSION['user_name'] ?? 'Student';

$message = '';
$success = false;

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update email (and optionally name if you want to allow)
    if (isset($_POST['update_profile'])) {
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE prn = ? AND role = 'student'");
            $stmt->bind_param('ss', $email, $student_prn);
            if ($stmt->execute()) {
                $success = true;
                $message = 'Profile updated successfully.';
                // Keep session email in sync if you store it
                $_SESSION['user_email'] = $email;
            } else {
                $message = 'Failed to update profile. Please try again.';
            }
            $stmt->close();
        }
    }

    // Update password
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (strlen($new_password) < 6) {
            $message = 'New password must be at least 6 characters.';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New password and confirmation do not match.';
        } else {
            // Fetch existing hash
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE prn = ? AND role = 'student' LIMIT 1");
            $stmt->bind_param('s', $student_prn);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            if (!$row) {
                $message = 'User not found.';
            } else {
                $hash = $row['password_hash'];
                if (!password_verify($current_password, $hash)) {
                    $message = 'Current password is incorrect.';
                } else {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE prn = ? AND role = 'student'");
                    $stmt->bind_param('ss', $new_hash, $student_prn);
                    if ($stmt->execute()) {
                        $success = true;
                        $message = 'Password updated successfully.';
                    } else {
                        $message = 'Failed to update password. Please try again.';
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Fetch student profile data
$stmt = $conn->prepare("
    SELECT u.prn, u.name, u.email, u.roll_no, u.division, u.course_id, c.course_name
    FROM users u
    LEFT JOIN courses c ON u.course_id = c.course_id
    WHERE u.prn = ? AND u.role = 'student'
    LIMIT 1
");
$stmt->bind_param('s', $student_prn);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

$email     = $profile['email'] ?? '';
$roll_no   = $profile['roll_no'] ?? '';
$division  = $profile['division'] ?? '';
$course    = $profile['course_name'] ?? 'N/A';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f7f8fc; color: #333; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: #2c3e50; color: #ecf0f1; display: flex; flex-direction: column; padding: 20px; flex-shrink: 0; }
        .sidebar .logo { text-align: center; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid #34495e; }
        .sidebar .logo h2 { font-weight: 700; }
        .nav-links { list-style: none; flex-grow: 1; }
        .nav-links a { display: flex; align-items: center; color: #ecf0f1; text-decoration: none; padding: 15px 10px; border-radius: 8px; margin-bottom: 10px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background-color: #3498db; color: #fff; }
        .nav-links a i { margin-right: 15px; width: 20px; text-align: center; }
        .logout-link a { background-color: #e74c3c; padding: 12px; border-radius: 6px; display: block; text-align: center; }
        .logout-link a:hover { background-color: #c0392b; }
        .main-content { flex-grow: 1; padding: 40px; overflow-x: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-size: 26px; font-weight: 700; }
        .user-info { font-size: 16px; font-weight: 500; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 30px; }
        .card { background: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,.05); }
        .card h3 { margin-bottom: 15px; font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 12px; }
        .info-row { display: grid; grid-template-columns: 150px 1fr; gap: 10px; padding: 10px 0; border-bottom: 1px dashed #eee; }
        .info-row:last-child { border-bottom: none; }
        .label { color: #666; font-weight: 500; }
        .value { font-weight: 600; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Poppins', sans-serif; }
        .btn { display: inline-block; padding: 12px 20px; background-color: #3498db; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn.secondary { background-color: #6c757d; }
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid transparent; }
        .alert-success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><h2>Student Panel</h2></div>
        <ul class="nav-links">
            <li><a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="student-attendance.php"><i class="fas fa-clipboard-list"></i> My Attendance</a></li>
            <li><a href="student-profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
        </ul>
        <div class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>My Profile</h1>
            <div class="user-info">Welcome, <strong><?php echo htmlspecialchars($student_name); ?></strong></div>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="grid">
            <!-- Profile Summary -->
            <div class="card">
                <h3>Profile Summary</h3>
                <div class="info-row">
                    <div class="label">PRN</div>
                    <div class="value"><?php echo htmlspecialchars($profile['prn'] ?? ''); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Name</div>
                    <div class="value"><?php echo htmlspecialchars($profile['name'] ?? ''); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Email</div>
                    <div class="value"><?php echo htmlspecialchars($email); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Course</div>
                    <div class="value"><?php echo htmlspecialchars($course); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Division</div>
                    <div class="value"><?php echo htmlspecialchars($division); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Roll No.</div>
                    <div class="value"><?php echo htmlspecialchars($roll_no); ?></div>
                </div>
            </div>

            <!-- Update Email -->
            <div class="card">
                <h3>Update Email</h3>
                <form action="student-profile.php" method="post">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <button type="submit" name="update_profile" class="btn">Save Changes</button>
                </form>
            </div>

            <!-- Update Password -->
            <div class="card">
                <h3>Change Password</h3>
                <form action="student-profile.php" method="post" autocomplete="off">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password (min 6 chars)</label>
                        <input type="password" id="new_password" name="new_password" minlength="6" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                    </div>
                    <button type="submit" name="update_password" class="btn">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
