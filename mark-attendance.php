<?php
require_once 'config.php';

// Protect the page
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

// Fetch all courses for the dropdown
$courses = $conn->query("SELECT * FROM courses ORDER BY course_name");
$students = null;
$selected_course = $_POST['course_id'] ?? '';
$selected_division = $_POST['division'] ?? '';

// If the form has been submitted to fetch students
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_students'])) {
    if (!empty($selected_course) && !empty($selected_division)) {
        $stmt = $conn->prepare("SELECT prn, roll_no, name FROM users WHERE course_id = ? AND division = ? AND role = 'student' ORDER BY roll_no ASC");
        $stmt->bind_param("is", $selected_course, $selected_division);
        $stmt->execute();
        $students = $stmt->get_result();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mark Attendance</title>
    <!-- Add your CSS link here -->
</head>
<body>
    <h1>Mark Student Attendance</h1>

    <!-- Selection Form -->
    <form action="mark-attendance.php" method="post">
        <label for="course_id">Select Course:</label>
        <select name="course_id" required>
            <option value="">--Select a Course--</option>
            <?php while ($course = $courses->fetch_assoc()): ?>
                <option value="<?php echo $course['course_id']; ?>" <?php if($selected_course == $course['course_id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($course['course_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="division">Select Division:</label>
        <input type="text" name="division" placeholder="e.g., A" required value="<?php echo htmlspecialchars($selected_division); ?>">
        
        <button type="submit" name="fetch_students">Fetch Students</button>
    </form>

    <hr>

    <!-- Student List Form -->
    <?php if ($students && $students->num_rows > 0): ?>
        <h2>Student List</h2>
        <form action="save-attendance.php" method="post">
            <input type="hidden" name="course_id" value="<?php echo $selected_course; ?>">
            <input type="hidden" name="division" value="<?php echo $selected_division; ?>">
            <table>
                <thead>
                    <tr>
                        <th>Roll No.</th>
                        <th>Name</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $students->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['roll_no']); ?></td>
                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                            <td>
                                <label><input type="radio" name="attendance[<?php echo $student['prn']; ?>]" value="Present" checked> Present</label>
                                <label><input type="radio" name="attendance[<?php echo $student['prn']; ?>]" value="Absent"> Absent</label>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <button type="submit">Save Attendance</button>
        </form>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <p>No students found for the selected course and division.</p>
    <?php endif; ?>

</body>
</html>
