<?php
require_once 'config.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') { header('Location: login.php'); exit; }

$search_term = $_GET['search'] ?? '';

$sql = "SELECT prn, name, email FROM users WHERE role = 'teacher'";
$params = [];
if (!empty($search_term)) {
    $sql .= " AND (name LIKE ? OR prn LIKE ? OR email LIKE ?)";
    $like_search = "%" . $search_term . "%";
    $params = [$like_search, $like_search, $like_search];
}
$sql .= " ORDER BY name";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param('sss', ...$params);
}
$stmt->execute();
$teachers = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Teachers</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 30px; }
        .filter-form { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; gap: 15px; align-items: center; }
        .filter-form input, .filter-form button, .filter-form a { padding: 10px; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 14px; }
        .filter-form input { border: 1px solid #ddd; flex-grow: 1; }
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
        <h1>Manage Teachers</h1>
        <form action="manage-teachers.php" method="get" class="filter-form">
            <input type="search" name="search" placeholder="Search by name, PRN, or email..." value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit">Search</button>
            <a href="manage-teachers.php">Clear</a>
        </form>
        <div class="user-section">
            <table>
                <thead>
                    <tr><th>PRN / ID</th><th>Name</th><th>Email</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($teachers->num_rows > 0): ?>
                        <?php while ($row = $teachers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['prn']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><a href="edit-user.php?prn=<?php echo urlencode($row['prn']); ?>&return_to=manage-teachers" class="action-btn">Edit</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; padding: 20px;">No teachers found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <a href="admin_dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    </div>
</body>
</html>
