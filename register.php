<?php
// Include the database configuration file
require_once 'config.php';

// --- FORM SUBMISSION LOGIC ---
$message = "";
$success = false;
$prn = $_POST['prn'] ?? '';
$roll_no = $_POST['roll_no'] ?? '';
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone_number = $_POST['phone_number'] ?? '';
$division = $_POST['division'] ?? '';
$course_id = $_POST['course_id'] ?? '';
$academic_year = $_POST['academic_year'] ?? ''; // <-- Get the new academic year

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prn = trim($prn);
    $roll_no = trim($roll_no);
    $name = trim($name);
    $email = trim($email);
    $phone_number = trim($phone_number);
    $division = trim($division);
    $course_id = trim($course_id);
    $academic_year = trim($academic_year); // <-- Trim the new academic year
    $raw_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // --- VALIDATION (now includes academic_year) ---
    if (empty($prn) || empty($roll_no) || empty($name) || empty($email) || empty($division) || empty($course_id) || empty($academic_year) || empty($raw_password)) {
        $message = "Please fill in all required fields.";
    } elseif (!preg_match('/^S\d{9}$/', $prn)) {
        $message = "Invalid PRN format. It must start with 'S' followed by 9 numbers.";
    } elseif ($raw_password !== $confirm_password) {
        $message = "Passwords do not match. Please try again.";
    } elseif (strlen($raw_password) < 6) {
        $message = "Password must be at least 6 characters long.";
    } else {
        try {
            $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
            
            // --- MODIFIED INSERT STATEMENT ---
            $stmt = $conn->prepare(
                "INSERT INTO users (prn, roll_no, name, email, phone_number, division, course_id, academic_year, password) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            // Added 'i' for the integer academic_year
            $stmt->bind_param("ssssssiis", $prn, $roll_no, $name, $email, $phone_number, $division, $course_id, $academic_year, $hashed_password);
            
            if ($stmt->execute()) {
                $success = true;
                $message = "Registration successful! You can now log in.";
                // Clear POST data to reset the form
                $_POST = [];
                $prn = $roll_no = $name = $email = $phone_number = $division = $course_id = $academic_year = '';
            }
            $stmt->close();
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() == 1062) { // Handles duplicate entry errors
                if (str_contains($ex->getMessage(), 'prn')) {
                    $message = "Error: This PRN is already registered.";
                } elseif (str_contains($ex->getMessage(), 'roll_no')) {
                    $message = "Error: This Roll Number is already registered.";
                } else {
                    $message = "Error: This Email is already in use.";
                }
            } else {
                $message = "An unexpected error occurred during registration.";
            }
        }
    }
}

// --- FETCH COURSES FOR DROPDOWN ---
$courses = [];
$result = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f6; min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        .container { display: flex; width: 100%; max-width: 1000px; background-color: #fff; border-radius: 15px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .form-panel { flex: 1.2; padding: 40px; display: flex; flex-direction: column; justify-content: center; }
        .form-panel h2 { font-size: 26px; font-weight: 700; color: #333; margin-bottom: 8px; }
        .form-panel .subtitle { color: #666; margin-bottom: 30px; }
        .form-group { margin-bottom: 18px; position: relative; }
        .form-group label { display: block; font-size: 13px; color: #555; margin-bottom: 8px; font-weight: 600; }
        .form-group input, .form-group select { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .form-group-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .password-toggle { position: absolute; top: 38px; right: 10px; background: none; border: none; cursor: pointer; color: #888; }
        .submit-btn { width: 100%; padding: 15px; border: none; border-radius: 8px; background-color: #4a69bd; color: #fff; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 10px; }
        .login-link { text-align: center; margin-top: 20px; font-size: 14px; }
        .login-link a { color: #4a69bd; text-decoration: none; font-weight: 600; }
        .info-panel { flex: 1; background: linear-gradient(to bottom right, #4a69bd, #1e3a5f); color: #fff; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px; text-align: center; }
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 8px; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-panel">
            <h2>Create Your Account</h2>
            <p class="subtitle">Register to get started with the attendance system.</p>
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form action="register.php" method="post" novalidate>
                <div class="form-group-row">
                    <div class="form-group"><label for="prn">PRN</label><input type="text" id="prn" name="prn" required placeholder="e.g., S123456789" value="<?php echo htmlspecialchars($prn); ?>"></div>
                    <div class="form-group"><label for="roll_no">Roll Number</label><input type="text" id="roll_no" name="roll_no" required value="<?php echo htmlspecialchars($roll_no); ?>"></div>
                </div>
                <div class="form-group"><label for="name">Full Name</label><input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($name); ?>"></div>
                <div class="form-group"><label for="email">Email Address</label><input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>"></div>
                <div class="form-group-row">
                    <div class="form-group">
                        <label for="course_id">Course</label>
                        <select id="course_id" name="course_id" required>
                            <option value="" disabled <?php if(empty($course_id)) echo 'selected'; ?>>Select a course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo htmlspecialchars($course['course_id']); ?>" <?php if($course_id == $course['course_id']) echo 'selected'; ?>><?php echo htmlspecialchars($course['course_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- NEW ACADEMIC YEAR DROPDOWN -->
                    <div class="form-group">
                        <label for="academic_year">Academic Year</label>
                        <select id="academic_year" name="academic_year" required>
                            <option value="" disabled <?php if(empty($academic_year)) echo 'selected'; ?>>Select your year</option>
                            <option value="1" <?php if($academic_year == 1) echo 'selected'; ?>>First Year (FY)</option>
                            <option value="2" <?php if($academic_year == 2) echo 'selected'; ?>>Second Year (SY)</option>
                            <option value="3" <?php if($academic_year == 3) echo 'selected'; ?>>Third Year (TY)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group-row">
                    <div class="form-group"><label for="phone_number">Phone Number (Optional)</label><input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>"></div>
                    <div class="form-group"><label for="division">Division</label><input type="text" id="division" name="division" required placeholder="e.g., A, B" value="<?php echo htmlspecialchars($division); ?>"></div>
                </div>
                <div class="form-group"><label for="password">Password</label><input type="password" id="password" name="password" required><button type="button" class="password-toggle" onclick="togglePassword('password', this)">Show</button></div>
                <div class="form-group"><label for="confirm_password">Confirm Password</label><input type="password" id="confirm_password" name="confirm_password" required><button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">Show</button></div>
                <button type="submit" class="submit-btn">Create Account</button>
            </form>
            <p class="login-link">Already have an account? <a href="login.php">Log In</a></p>
        </div>
        <div class="info-panel"><!-- Info Panel HTML --></div>
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
