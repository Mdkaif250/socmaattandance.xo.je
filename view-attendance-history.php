<?php
require_once 'config.php';

// Protect the page
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php?error=unauthorized');
    exit;
}

$teacher_name = $_SESSION['user_name'];
$teacher_prn = $_SESSION['user_prn'];

// --- 1. HANDLE FILTERS FROM USER INPUT ---
$course_filter = $_GET['course_id'] ?? '';
$subject_filter = $_GET['subject_id'] ?? '';
$date_filter = $_GET['attendance_date'] ?? '';

// --- 2. FETCH GROUPED ATTENDANCE DATA ---
$sql = "SELECT 
            a.attendance_date,
            a.course_id,
            a.subject_id,
            c.course_name,
            s.subject_name,
            COUNT(a.student_prn) as total_students,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_students
        FROM attendance a
        JOIN courses c ON a.course_id = c.course_id
        JOIN subjects s ON a.subject_id = s.subject_id
        WHERE a.teacher_prn = ?";

$params = [$teacher_prn];
$types = 's';

// Dynamically add filters to the query if they are provided
if (!empty($course_filter)) {
    $sql .= " AND a.course_id = ?";
    array_push($params, $course_filter);
    $types .= 'i';
}
if (!empty($subject_filter)) {
    $sql .= " AND a.subject_id = ?";
    array_push($params, $subject_filter);
    $types .= 'i';
}
if (!empty($date_filter)) {
    $sql .= " AND a.attendance_date = ?";
    array_push($params, $date_filter);
    $types .= 's';
}

// Group the results into sessions
$sql .= " GROUP BY a.attendance_date, a.course_id, a.subject_id";
// Sort the results: newest date first, then by course and subject name
$sql .= " ORDER BY a.attendance_date DESC, c.course_name ASC, s.subject_name ASC";

$stmt = $conn->prepare($sql);
if (count($params) > 1) { // Only bind if there are more params than the initial teacher_prn
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param($types, $teacher_prn);
}
$stmt->execute();
$attendance_summary = $stmt->get_result();

// --- 3. FETCH DATA FOR DROPDOWNS ---
$courses = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name");
$subjects = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f7f8fc; color: #333; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: #2c3e50; color: #ecf0f1; display: flex; flex-direction: column; padding: 20px; flex-shrink: 0; }
        .sidebar .logo { text-align: center; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid #34495e; }
        .sidebar .logo h2 { font-weight: 700; }
        .sidebar .nav-links { list-style: none; flex-grow: 1; }
        .sidebar .nav-links a { display: flex; align-items: center; color: #ecf0f1; text-decoration: none; padding: 15px 10px; border-radius: 8px; margin-bottom: 10px; transition: background-color 0.3s, color 0.3s; }
        .sidebar .nav-links a:hover, .sidebar .nav-links a.active { background-color: #3498db; color: #fff; }
        .sidebar .nav-links a i { margin-right: 15px; width: 20px; text-align: center; }
        .sidebar .logout-link a { background-color: #e74c3c; } .sidebar .logout-link a:hover { background-color: #c0392b; }
        .main-content { flex-grow: 1; padding: 40px; overflow-x: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .header h1 { font-size: 28px; font-weight: 700; }
        .header .user-info { font-size: 16px; font-weight: 500; }
        .card { background-color: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .card h3 { margin-bottom: 20px; font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: 500; }
        .form-group select, .form-group input { padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Poppins', sans-serif; }
        .btn { display: inline-block; padding: 12px 30px; background-color: #3498db; color: #fff; text-decoration: none; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: background-color 0.3s; }
        .btn-secondary { background-color: #6c757d; }
        .button-group { display: flex; gap: 10px; }
        .button-group .btn { flex: 1; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; white-space: nowrap; }
        th { background-color: #f8f9fa; }
        .btn-details { padding: 8px 15px; background-color: #3498db; color: #fff; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 500; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><h2>Teacher Panel</h2></div>
        <ul class="nav-links">
            <li><a href="teacher_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="take-attendance.php"><i class="fas fa-check-circle"></i> Take Attendance</a></li>
            <li><a href="view-attendance-history.php" class="active"><i class="fas fa-history"></i> View History</a></li>
        </ul>
        <div class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Attendance History</h1>
            <div class="user-info">Welcome, <strong><?php echo htmlspecialchars($teacher_name); ?></strong></div>
        </div>

        <div class="card">
            <h3>Filter Sessions</h3>
            <form action="view-attendance-history.php" method="get">
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_id">Course</label>
                        <select id="course_id" name="course_id">
                            <option value="">-- All Courses --</option>
                            <?php while($row = $courses->fetch_assoc()): ?>
                                <option value="<?php echo $row['course_id']; ?>" <?php echo ($course_filter == $row['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['course_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="subject_id">Subject</label>
                        <select id="subject_id" name="subject_id">
                            <option value="">-- All Subjects --</option>
                            <?php while($row = $subjects->fetch_assoc()): ?>
                                <option value="<?php echo $row['subject_id']; ?>" <?php echo ($subject_filter == $row['subject_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['subject_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="attendance_date">Date</label>
                        <input type="date" id="attendance_date" name="attendance_date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                    <div class="form-group button-group">
                        <button type="submit" class="btn">Filter</button>
                        <a href="view-attendance-history.php" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Attendance Sessions</h3>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Course</th>
                            <th>Subject</th>
                            <th>Attendance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($attendance_summary->num_rows > 0): ?>
                            <?php while($row = $attendance_summary->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date("d-M-Y", strtotime($row['attendance_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                    <td><strong><?php echo $row['present_students']; ?> / <?php echo $row['total_students']; ?></strong> Present</td>
                                    <td>
                                        <a href="view-attendance-details.php?date=<?php echo $row['attendance_date']; ?>&course_id=<?php echo $row['course_id']; ?>&subject_id=<?php echo $row['subject_id']; ?>" class="btn-details">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px;">No attendance sessions found matching your criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
