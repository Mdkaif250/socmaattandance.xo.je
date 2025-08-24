<?php
require_once 'config.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') { header('Location: login.php'); exit; }

$search_term = $_GET['search'] ?? '';
$course_filter = $_GET['course_id'] ?? '';

$sql = "SELECT u.prn, u.name, u.email, u.roll_no, u.division, c.course_name 
        FROM users u 
        LEFT JOIN courses c ON u.course_id = c.course_id 
        WHERE u.role = 'student'";
$params = [];
$types = '';

if (!empty($search_term)) {
    $sql .= " AND (u.name LIKE ? OR u.prn LIKE ?)";
    $like_search = "%" . $search_term . "%";
    array_push($params, $like_search, $like_search);
    $types .= 'ss';
}
if (!empty($course_filter)) {
    $sql .= " AND u.course_id = ?";
    array_push($params, $course_filter);
    $types .= 'i';
}
$sql .= " ORDER BY c.course_name, u.roll_no";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result();

$courses_result = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 30px; }
        .filter-form { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; gap: 15px; align-items: center; }
        .filter-form input, .filter-form select, .filter-form button, .filter-form a { padding: 10px; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 14px; }
        .filter-form input, .filter-form select { border: 1px solid #ddd; flex-grow: 1; }
        .filter-form button { border: none; background: #4a69bd; color: #fff; cursor: pointer; font-weight: 600; }
        .filter-form a { color: #555; text-decoration: none; }
        .user-section { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; white-space: nowrap; }
        th { background-color: #f8f9fa; font-weight: 600; }
        .action-btn { padding: 6px 12px; font-weight: 600; color: #fff; background-color: #4a69bd; border-radius: 6px; text-decoration: none; }
        .back-link { display: block; text-align: center; margin-top: 20px; font-weight: 600; color: #4a69bd; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage Students</h1>
        <form action="manage-students.php" method="get" class="filter-form">
            <input type="search" name="search" placeholder="Search by name or PRN..." value="<?php echo htmlspecialchars($search_term); ?>">
            <select name="course_id">
                <option value="">-- Filter by Course --</option>
                <?php while ($course = $courses_result->fetch_assoc()): ?>
                    <option value="<?php echo $course['course_id']; ?>" <?php echo ($course_filter == $course['course_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['course_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="submit">Filter</button>
            <a href="manage-students.php">Clear</a>
        </form>
        <div class="user-section">
            <table>
                <thead>
                    <tr><th>Course</th><th>Roll No.</th><th>Name</th><th>PRN</th><th>Division</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($students->num_rows > 0): ?>
                        <?php while ($row = $students->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['course_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['roll_no']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['prn']); ?></td>
                                <td><?php echo htmlspecialchars($row['division']); ?></td>
                                <td><a href="edit-user.php?prn=<?php echo urlencode($row['prn']); ?>&return_to=manage-students" class="action-btn">Edit</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding: 20px;">No students found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <a href="admin_dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    </div>
</body>
</html>
