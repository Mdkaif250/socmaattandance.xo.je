<?php
// Include the database configuration file.
require_once 'config.php';

$prn = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prn = trim($_POST['prn'] ?? '');
    $password_input = $_POST['password'] ?? '';

    if (empty($prn) || empty($password_input)) {
        $error_message = "Please enter both PRN and password.";
    } else {
        // --- THE FIX IS HERE ---
        // Changed 'password_hash' to 'password' to match your database table.
        // Also added 'course_id' to be safe.
        $stmt = $conn->prepare("SELECT prn, name, password, role, course_id FROM users WHERE prn = ?");
        $stmt->bind_param("s", $prn);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify the input password against the hashed password from the database.
            if (password_verify($password_input, $user['password'])) {
                // Set common session variables
                $_SESSION['loggedin'] = true;
                $_SESSION['user_prn'] = $user['prn'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];

                // Correctly redirect based on the user's role
                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($user['role'] === 'teacher') {
                    $_SESSION['teacher_course_id'] = $user['course_id']; // Set teacher-specific session
                    header('Location: teacher_dashboard.php');
                } else {
                    header("Location: student_dashboard.php");
                }
                exit(); // Stop the script after redirecting

            } else {
                $error_message = "Invalid PRN or password.";
            }
        } else {
            $error_message = "Invalid PRN or password.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login</title>
    <!-- Your CSS and other header content remains the same -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f6; min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        .container { display: flex; width: 100%; max-width: 1000px; min-height: 600px; background-color: #fff; border-radius: 15px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .form-panel { flex: 1.2; padding: 50px; display: flex; flex-direction: column; justify-content: center; }
        .form-panel h2 { font-size: 26px; font-weight: 700; color: #333; margin-bottom: 8px; }
        .form-panel .subtitle { color: #666; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; position: relative; }
        .form-group label { display: block; font-size: 13px; color: #555; margin-bottom: 8px; font-weight: 600; }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; font-family: 'Poppins', sans-serif; padding-right: 50px; }
        .password-toggle { position: absolute; top: 38px; right: 10px; background: none; border: none; cursor: pointer; color: #888; font-size: 12px; font-weight: 600; }
        .submit-btn { width: 100%; padding: 15px; border: none; border-radius: 8px; background-color: #4a69bd; color: #fff; font-size: 16px; font-weight: 700; cursor: pointer; transition: background-color 0.3s; margin-top: 10px; }
        .submit-btn:hover { background-color: #3b5998; }
        .footer-links { display: flex; justify-content: space-between; align-items: center; margin-top: 25px; font-size: 14px; }
        .footer-links a { color: #4a69bd; text-decoration: none; font-weight: 600; }
        .footer-links a:hover { text-decoration: underline; }
        .info-panel { flex: 1; background: linear-gradient(to bottom right, #4a69bd, #1e3a5f); color: #fff; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px; text-align: center; }
        .info-panel h1 { font-size: 32px; font-weight: 700; margin-bottom: 15px; line-height: 1.3; }
        .info-panel p { font-size: 16px; line-height: 1.6; max-width: 300px; opacity: 0.9; }
        .alert-error { padding: 12px; margin-bottom: 20px; border-radius: 8px; font-size: 14px; border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; }
        @media (max-width: 920px) { .info-panel { display: none; } .container { max-width: 500px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-panel">
            <h2>Welcome Back!</h2>
            <p class="subtitle">Please log in to access your account.</p>

            <?php if (!empty($error_message)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form action="login.php" method="post" novalidate>
                <div class="form-group">
                    <label for="prn">PRN (Permanent Register Number)</label>
                    <input type="text" id="prn" name="prn" required placeholder="e.g., S123456789" value="<?php echo htmlspecialchars($prn); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('password', this)">Show</button>
                </div>

                <button type="submit" class="submit-btn">Log In</button>
            </form>

            <div class="footer-links">
                <a href="register.php">Create an Account</a>
                <a href="forgot-password.php">Forgot Password?</a>
            </div>
        </div>
        <div class="info-panel">
            <h1>Attendance Management System</h1>
            <p>A seamless, digital solution to track and manage student attendance with ease and accuracy.</p>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, toggleButton) {
            const passwordField = document.getElementById(fieldId);
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            toggleButton.textContent = type === 'password' ? 'Show' : 'Hide';
        }
    </script>
</body>
</html>
