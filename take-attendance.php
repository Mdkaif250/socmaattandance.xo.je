<?php
require_once 'config.php';

// Protect the page: only logged-in teachers can access
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php?error=unauthorized');
    exit;
}

$teacher_name = $_SESSION['user_name'];
$teacher_prn = $_SESSION['user_prn'];
$teacher_course_id = $_SESSION['teacher_course_id'] ?? 0;

$message = '';
$success = false;
$show_student_list = false;

// --- HANDLE SAVING THE ATTENDANCE DATA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    $course_id = $_POST['course_id'];
    $subject_id = $_POST['subject_id'];
    $academic_year = $_POST['academic_year'];
    $attendance_date = $_POST['attendance_date'];
    $all_students_prn = $_POST['all_students'] ?? [];
    $present_students_prn = $_POST['present_students'] ?? [];

    $stmt = $conn->prepare("INSERT INTO attendance (student_prn, teacher_prn, course_id, subject_id, academic_year, attendance_date, status) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");

    foreach ($all_students_prn as $student_prn) {
        $status = in_array($student_prn, $present_students_prn) ? 'Present' : 'Absent';
        $stmt->bind_param("ssiiiss", $student_prn, $teacher_prn, $course_id, $subject_id, $academic_year, $attendance_date, $status);
        $stmt->execute();
    }
    $stmt->close();
    $success = true;
    $message = "Attendance has been saved successfully!";
}

// --- HANDLE FETCHING STUDENTS FOR THE GRID ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_students'])) {
    $subject_id = $_POST['subject_id'];
    $attendance_date = $_POST['attendance_date'];
    $academic_year = $_POST['academic_year'];
    $course_id = $teacher_course_id;

    if (empty($subject_id) || empty($attendance_date) || empty($academic_year)) {
        $message = "Please select a subject, date, and academic year.";
    } else {
        $stmt = $conn->prepare("SELECT prn, name, roll_no FROM users WHERE role = 'student' AND course_id = ? AND academic_year = ? ORDER BY roll_no ASC");
        $stmt->bind_param("ii", $course_id, $academic_year);
        $stmt->execute();
        $students_result = $stmt->get_result();
        $show_student_list = true;
    }
}

// Fetch data for the initial selection form dropdowns
$course_result = $conn->query("SELECT course_name FROM courses WHERE course_id = $teacher_course_id");
$course = $course_result ? $course_result->fetch_assoc() : null;
$subjects = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Take Attendance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        /* Standard two-column layout and sidebar styles */
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
        .main-content { flex-grow: 1; padding: 40px; }
        .header h1 { font-size: 28px; font-weight: 700; margin-bottom: 30px; }
        .card { background-color: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .card h3 { margin-bottom: 20px; font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; }
        .form-group .locked-field { background-color: #f0f0f0; }
        .btn { padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .btn-primary { background-color: #3498db; color: #fff; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-error { background-color: #f8d7da; color: #721c24; }

        /* Styles for the enhanced attendance grid experience */
        .controls { display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-bottom: 20px; }
        #search-students { padding: 12px; border: 1px solid #ddd; border-radius: 8px; flex-grow: 1; min-width: 250px; }
        .student-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 20px; }
        .student-item { padding: 10px 15px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.2s; user-select: none; }
        .student-item.present { background-color: #e8f5e9; border-color: #2ecc71; }
        .student-item.absent { background-color: #ffebee; border-color: #ef9a9a; }
        .student-item .roll-no { font-weight: 700; color: #333; }
        .student-item .name { font-size: 14px; color: #555; }
    </style>
</head>
<body>
    <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
    <div class="sidebar">
        <div class="logo"><h2>Teacher Panel</h2></div>
        <ul class="nav-links">
            <li><a href="teacher_dashboard.php" class="<?php echo ($current_page == 'teacher_dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="take-attendance.php" class="active"><i class="fas fa-check-circle"></i> Take Attendance</a></li>
            <li><a href="view-attendance-history.php" class="<?php echo ($current_page == 'view-attendance-history.php') ? 'active' : ''; ?>"><i class="fas fa-history"></i> View History</a></li>
        </ul>
        <div class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>
    <div class="main-content">
        <div class="header"><h1>Take Attendance</h1></div>

        <?php if ($message): ?><div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

        <div class="card">
            <?php if (!$show_student_list): ?>
                <h3>Select Attendance Criteria</h3>
                <form action="take-attendance.php" method="post">
                    <div class="form-row">
                        <div class="form-group"><label>Your Assigned Course</label><input type="text" class="locked-field" value="<?php echo htmlspecialchars($course['course_name'] ?? 'Not Assigned'); ?>" readonly></div>
                        <div class="form-group"><label for="academic_year">Select Year</label><select id="academic_year" name="academic_year" required><option value="">-- Select Academic Year --</option><option value="1">First Year (FY)</option><option value="2">Second Year (SY)</option><option value="3">Third Year (TY)</option></select></div>
                        <div class="form-group"><label for="subject_id">Select Subject</label><select id="subject_id" name="subject_id" required><option value="">-- Select Subject --</option><?php mysqli_data_seek($subjects, 0); while($row = $subjects->fetch_assoc()): ?><option value="<?php echo $row['subject_id']; ?>"><?php echo htmlspecialchars($row['subject_name']); ?></option><?php endwhile; ?></select></div>
                        <div class="form-group"><label for="attendance_date">Date</label><input type="date" id="attendance_date" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required></div>
                    </div>
                    <button type="submit" name="fetch_students" class="btn btn-primary">Fetch Students</button>
                </form>
            <?php else: ?>
                <h3>Mark Students Who Are Present</h3>
                <form action="take-attendance.php" method="post">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                    <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                    <input type="hidden" name="academic_year" value="<?php echo $academic_year; ?>">
                    <input type="hidden" name="attendance_date" value="<?php echo $attendance_date; ?>">
                    
                    <div class="controls">
                        <input type="text" id="search-students" placeholder="Search by name or roll number...">
                        <button type="button" class="btn btn-primary" onclick="markAll('present')" style="background-color:#2ecc71;">Mark All Present</button>
                        <button type="button" class="btn" onclick="markAll('absent')" style="background-color: #e74c3c;">Mark All Absent</button>
                    </div>

                    <div class="student-grid">
                        <?php if($students_result->num_rows > 0): ?>
                            <?php while($student = $students_result->fetch_assoc()): ?>
                                <div class="student-item absent" data-search-term="<?php echo strtolower(htmlspecialchars($student['name'] . ' ' . $student['roll_no'])); ?>" onclick="toggleStatus(this)">
                                    <div class="roll-no"><?php echo htmlspecialchars($student['roll_no']); ?></div>
                                    <div class="name"><?php echo htmlspecialchars($student['name']); ?></div>
                                    <input type="hidden" name="all_students[]" value="<?php echo $student['prn']; ?>">
                                    <input type="checkbox" name="present_students[]" value="<?php echo $student['prn']; ?>" style="display: none;">
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p>No students found for the selected course and year.</p>
                        <?php endif; ?>
                    </div>
                    <button type="submit" name="submit_attendance" class="btn btn-primary" style="margin-top: 20px;">Submit Attendance</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function toggleStatus(element) {
            const checkbox = element.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            element.classList.toggle('present');
            element.classList.toggle('absent');
        }

        function markAll(status) {
            document.querySelectorAll('.student-item').forEach(item => {
                const checkbox = item.querySelector('input[type="checkbox"]');
                const isPresent = (status === 'present');
                checkbox.checked = isPresent;
                item.classList.toggle('present', isPresent);
                item.classList.toggle('absent', !isPresent);
            });
        }

        document.getElementById('search-students').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('.student-item').forEach(item => {
                item.style.display = item.dataset.searchTerm.includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
