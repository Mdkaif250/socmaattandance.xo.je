<?php
require_once 'config.php';

// Protect the page
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php?error=unauthorized');
    exit;
}

$student_name = $_SESSION['user_name'] ?? 'Student';
$student_prn  = $_SESSION['user_prn']  ?? '';

// --- 1. FETCH SUBJECT-WISE ATTENDANCE ---
$sql = "SELECT 
            s.subject_name,
            COUNT(DISTINCT a.attendance_date) AS total_classes,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_classes
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.subject_id
        WHERE a.student_prn = ?
        GROUP BY a.subject_id, s.subject_name
        ORDER BY s.subject_name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_prn);
$stmt->execute();
$result = $stmt->get_result();
$subjects_rows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- 2. CALCULATE OVERALL STATS AND PREPARE DATA FOR FEATURES ---
$total_classes = 0;
$total_present = 0;
$chart_labels = [];
$chart_present_data = [];
$low_attendance_subjects = [];
$attendance_threshold = 75;

foreach ($subjects_rows as $row) {
    $total_classes += (int)$row['total_classes'];
    $total_present += (int)$row['present_classes'];
    $chart_labels[] = $row['subject_name'];
    $chart_present_data[] = (int)$row['present_classes'];
    $subject_percent = ($row['total_classes'] > 0) ? round(($row['present_classes'] / $row['total_classes']) * 100, 2) : 0;
    if ($subject_percent < $attendance_threshold) {
        $low_attendance_subjects[] = ['subject' => $row['subject_name'], 'percent' => $subject_percent];
    }
}

$overall_percentage = ($total_classes > 0) ? round(($total_present / $total_classes) * 100, 2) : 0;
$overall_absent = max(0, $total_classes - $total_present);
$is_overall_low = $overall_percentage < $attendance_threshold && $total_classes > 0;

// --- 3. FETCH DETAILED DATE-WISE RECORDS ---
$sql2 = "SELECT a.attendance_date, s.subject_name, a.status FROM attendance a JOIN subjects s ON a.subject_id = s.subject_id WHERE a.student_prn = ? ORDER BY a.attendance_date DESC, s.subject_name ASC";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("s", $student_prn);
$stmt2->execute();
$details_result = $stmt2->get_result();
$details_rows = $details_result->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Attendance Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f7f8fc; color: #333; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: #2c3e50; color: #ecf0f1; display: flex; flex-direction: column; padding: 20px; flex-shrink: 0; }
        .sidebar .logo { text-align: center; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid #34495e; }
        .sidebar .logo h2 { font-weight: 700; }
        .sidebar .nav-links { list-style: none; flex-grow: 1; }
        .sidebar .nav-links a { display: flex; align-items: center; color: #ecf0f1; text-decoration: none; padding: 15px 10px; border-radius: 8px; margin-bottom: 10px; transition: 0.3s; }
        .sidebar .nav-links a:hover, .sidebar .nav-links a.active { background-color: #3498db; color: #fff; }
        .sidebar .nav-links a i { margin-right: 15px; width: 20px; text-align: center; }
        .sidebar .logout-link a { background-color: #e74c3c; padding: 12px; border-radius: 6px; display: block; text-align: center; }
        .sidebar .logout-link a:hover { background-color: #c0392b; }
        .main-content { flex-grow: 1; padding: 40px; overflow-x: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-size: 26px; font-weight: 700; }
        .user-info { font-size: 16px; font-weight: 500; }
        .card { background: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,.05); margin-bottom: 30px; }
        .card h3 { margin-bottom: 20px; font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f8f9fa; }
        .status-present { color: #27ae60; font-weight: 600; }
        .status-absent { color: #e74c3c; font-weight: 600; }
        .highlight { font-size: 22px; font-weight: 700; color: #3498db; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #3498db; color: #fff; text-decoration: none; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: background-color 0.3s; margin-right: 10px; }
        .btn-secondary { background-color: #6c757d; }
        .alert-warning { border-left: 6px solid #e67e22; background-color: #fef9e7; padding: 20px; }
        .alert-warning h3 { border: none; color: #e67e22; }

        /* --- THE FIX FOR THE CHART --- */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        .chart-container {
            position: relative;
            width: 100%;
            max-width: 400px; /* Prevents pie chart from becoming too large */
            margin: auto; /* Center the pie chart in its grid cell */
        }
        /* --- END OF FIX --- */

        @media print {
            body { display: block; } .sidebar, .header, .export-buttons, .alert-warning { display: none; }
            .card { box-shadow: none; border: 1px solid #ddd; margin: 0; padding: 15px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><h2>Student Panel</h2></div>
        <ul class="nav-links">
            <li><a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="student-attendance.php" class="active"><i class="fas fa-clipboard-list"></i> My Attendance</a></li>
            <li><a href="student-profile.php"><i class="fas fa-user"></i> Profile</a></li>
        </ul>
        <div class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>My Attendance Dashboard</h1>
            <div class="user-info">Welcome, <strong><?php echo htmlspecialchars($student_name); ?></strong></div>
        </div>

        <?php if ($is_overall_low || !empty($low_attendance_subjects)): ?>
        <div class="card alert-warning">
            <h3><i class="fas fa-exclamation-triangle"></i> Attendance Alert</h3>
            <p>Your attendance is below the <?php echo $attendance_threshold; ?>% requirement in the following areas:</p>
            <ul style="margin-left: 20px; margin-top: 5px;">
                <?php if ($is_overall_low): ?><li><strong>Overall Attendance:</strong> <?php echo $overall_percentage; ?>%</li><?php endif; ?>
                <?php foreach ($low_attendance_subjects as $s): ?><li><strong><?php echo htmlspecialchars($s['subject']); ?></strong>: <?php echo $s['percent']; ?>%</li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="export-buttons" style="margin-bottom: 20px;">
            <a href="export_attendance_csv.php" class="btn"><i class="fas fa-file-csv"></i> Download CSV</a>
            <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Print / Save PDF</button>
        </div>

        <div class="card">
            <h3>Attendance Charts</h3>
            <div class="chart-grid">
                <div><canvas id="barChart"></canvas></div>
                <div class="chart-container"><canvas id="pieChart"></canvas></div>
            </div>
        </div>

        <div class="card">
            <h3>Subject-wise Summary</h3>
            <table>
                <thead><tr><th>Subject</th><th>Present</th><th>Total Classes</th><th>Percentage</th></tr></thead>
                <tbody>
                    <?php if (!empty($subjects_rows)): ?>
                        <?php foreach ($subjects_rows as $row): 
                            $percent = ($row['total_classes'] > 0) ? round(($row['present_classes'] / $row['total_classes']) * 100, 2) : 0; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                <td><?php echo (int)$row['present_classes']; ?></td>
                                <td><?php echo (int)$row['total_classes']; ?></td>
                                <td class="<?php echo ($percent < $attendance_threshold) ? 'status-absent' : 'status-present'; ?>"><?php echo $percent; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;">No attendance records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>Detailed Daily Records</h3>
            <table>
                <thead><tr><th>Date</th><th>Subject</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if (!empty($details_rows)): ?>
                        <?php foreach ($details_rows as $row): ?>
                            <tr>
                                <td><?php echo date("d-M-Y", strtotime($row['attendance_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                <td><span class="status-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align:center;">No detailed records available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
      const labels = <?php echo json_encode($chart_labels); ?>;
      const presentData = <?php echo json_encode($chart_present_data); ?>;

      // Bar Chart
      const ctxBar = document.getElementById('barChart').getContext('2d');
      new Chart(ctxBar, {
        type: 'bar',
        data: { labels: labels, datasets: [{ label: 'Classes Attended', data: presentData, backgroundColor: 'rgba(52, 152, 219, 0.7)' }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { title: { display: true, text: 'Attendance per Subject' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
      });

      // Pie Chart
      const ctxPie = document.getElementById('pieChart').getContext('2d');
      new Chart(ctxPie, {
        type: 'pie',
        data: { labels: ['Present', 'Absent'], datasets: [{ data: [<?php echo $total_present; ?>, <?php echo $overall_absent; ?>], backgroundColor: ['#27ae60', '#e74c3c'] }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { title: { display: true, text: 'Overall Attendance Ratio' } } }
      });
    </script>
</body>
</html>
