<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');

$start_date = "$year-$month-01";
$end_date = date('Y-m-t', strtotime($start_date));

// Fetch profit existence for each day in the month
$stmt = $pdo->prepare("SELECT DISTINCT DATE(profit_date) as date FROM profits");
$stmt->execute();
$active_days = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch data for ranking, chart, and submission log
$month_start = "$year-$month-01";
$month_end = date('Y-m-t', strtotime($month_start));

// Ranking data (full month)
$stmt = $pdo->prepare("
    SELECT e.name, SUM(p.amount) as total_profit
    FROM employees e
    JOIN profits p ON e.id = p.employee_id
    WHERE p.profit_date BETWEEN :start AND :end
    GROUP BY e.id
    ORDER BY total_profit DESC

");
$stmt->bindValue(':start', $month_start);
$stmt->bindValue(':end', $month_end);
$stmt->execute();
$ranking_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Chart data (daily totals for the month)
$stmt = $pdo->prepare("
    SELECT DATE(profit_date) as date, SUM(amount) as daily_total
    FROM profits
    WHERE profit_date BETWEEN ? AND ?
    GROUP BY DATE(profit_date)
    ORDER BY date ASC
");
$stmt->execute([$month_start, $month_end]);
$chart_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent submission log for the current month (latest 20 entries)
// Use submitted_at so we can show the exact submission time
$log_stmt = $pdo->prepare("
    SELECT p.employee_id, e.name, p.amount, p.submitted_at, p.status, p.session_id
    FROM profits p
    JOIN employees e ON e.id = p.employee_id
    WHERE p.submitted_at BETWEEN ? AND ?
    ORDER BY p.submitted_at DESC, p.id DESC
    LIMIT 20
");
$log_stmt->execute([$month_start . ' 00:00:00', $month_end . ' 23:59:59']);
$submission_logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);

$chart_data = [];
$chart_labels = [];
$current_date_check = $month_start;
while (strtotime($current_date_check) <= strtotime($month_end)) {
    $found = false;
    foreach ($chart_results as $row) {
        if ($row['date'] == $current_date_check) {
            $chart_data[] = (float)$row['daily_total'];
            $found = true;
            break;
        }
    }
    if (!$found) $chart_data[] = 0;
    $chart_labels[] = date('d', strtotime($current_date_check));
    $current_date_check = date('Y-m-d', strtotime($current_date_check . ' +1 day'));
}
$stmt->execute([$start_date, $end_date]);
$active_days = $stmt->fetchAll(PDO::FETCH_COLUMN);

$month_name = date('F', mktime(0, 0, 0, $month, 10));
$first_day_of_month = date('w', strtotime($start_date));
$days_in_month = date('t', strtotime($start_date));

$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month == 0) { $prev_month = 12; $prev_year--; }

$next_month = $month + 1;
$next_year = $year;
if ($next_month == 13) { $next_month = 1; $next_year++; }
// AJAX Partial Update Logic
if (isset($_GET['ajax'])) {
    // Handle Admin Account Update via AJAX
    if (isset($_GET['ajax']) && isset($_POST['update_admin_account'])) {
        header('Content-Type: application/json');
        $update_type = $_POST['update_type'] ?? ''; // 'username' or 'password'
        $current_password = $_POST['current_password'] ?? '';
        $current_admin_username = $_SESSION['admin_username'] ?? '';
        $current_admin_id = $_SESSION['admin_id'] ?? null;

        if (!$current_admin_id) {
            echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
            exit;
        }

        try {
            // Verify current password first for ANY sensitive change
            $stmt = $pdo->prepare("SELECT * FROM admin WHERE id = ?");
            $stmt->execute([$current_admin_id]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($current_password, $admin['password'])) {
                echo json_encode(['success' => false, 'message' => 'Incorrect current password.']);
                exit;
            }

            if ($update_type === 'username') {
                $new_username = trim($_POST['admin_username'] ?? '');
                if ($new_username === '') {
                    echo json_encode(['success' => false, 'message' => 'Username cannot be empty.']);
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE admin SET username = ? WHERE id = ?");
                $stmt->execute([$new_username, $current_admin_id]);
                if ($stmt->rowCount() < 1) {
                    echo json_encode(['success' => false, 'message' => 'No changes were saved.']);
                    exit;
                }
                $_SESSION['admin_username'] = $new_username;
                echo json_encode(['success' => true, 'message' => 'Username updated successfully!', 'username' => $new_username]);
            } elseif ($update_type === 'password') {
                $new_password = $_POST['admin_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                if (empty($new_password)) {
                    echo json_encode(['success' => false, 'message' => 'New password cannot be empty.']);
                    exit;
                }
                if ($new_password !== $confirm_password) {
                    echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
                    exit;
                }
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admin SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $current_admin_id]);
                if ($stmt->rowCount() < 1) {
                    echo json_encode(['success' => false, 'message' => 'No changes were saved.']);
                    exit;
                }
                echo json_encode(['success' => true, 'message' => 'Password updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid update type.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    if (isset($_GET['submissions'])) {
        ob_start();
        if (empty($submission_logs)): ?>
            <li class="list-group-item text-center py-3 text-muted">No submissions this month yet.</li>
        <?php else: ?>
            <?php foreach ($submission_logs as $log): ?>
                <li class="list-group-item d-flex justify-content-between align-items-start py-2">
                    <div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($log['name']); ?></div>
                        <div class="text-muted small">
                            <?php echo date('M d, Y g:i A', strtotime($log['submitted_at'])); ?>
                        </div>
                        <?php if (!empty($log['session_id'])): ?>
                            <div class="text-muted" style="font-size: 0.7rem;">ID: <?php echo htmlspecialchars($log['session_id']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="text-end">
                        <div class="text-success fw-bold">₱<?php echo number_format($log['amount'], 0); ?></div>
                        <div class="badge bg-light text-dark border mt-1"><?php echo htmlspecialchars($log['status']); ?></div>
                    </div>
                </li>
            <?php endforeach; ?>
        <?php endif;
        echo ob_get_clean();
        exit;
    }
    
    if (isset($_GET['ranking'])) {
        ob_start();
        ?>
        <ul class="list-group list-group-flush" id="ranking-list">
            <?php if (empty($ranking_data)): ?>
                <li class="list-group-item text-center py-4 text-muted">No data available for this month</li>
            <?php else: ?>
                <?php foreach ($ranking_data as $index => $rank): ?>
                    <?php $globalRank = $index + 1; ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <div class="d-flex align-items-center">
                            <span class="badge <?php 
                                echo $globalRank == 1 ? 'bg-warning' : ($globalRank == 2 ? 'bg-secondary-subtle text-dark' : ($globalRank == 3 ? 'bg-danger-subtle text-dark' : 'bg-light text-dark border')); 
                            ?> rounded-circle me-3" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                <?php echo $globalRank; ?>
                            </span>
                            <span class="fw-bold"><?php echo htmlspecialchars($rank['name']); ?></span>
                        </div>
                        <span class="text-success fw-bold">₱<?php echo number_format($rank['total_profit'], 0); ?></span>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
        <?php
        echo ob_get_clean();
        exit;
    }
    
    if (isset($_GET['chart'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'labels' => $chart_labels,
            'data' => $chart_data
        ]);
        exit;
    }

    ob_start();
    ?>
    <div class="card-body p-0">
        <table class="table table-bordered calendar-table mb-0">
            <thead>
                <tr>
                    <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php
                    // Empty cells before first day
                    for ($i = 0; $i < $first_day_of_month; $i++) {
                        echo '<td class="other-month"></td>';
                    }

                    $current_day = 1;
                    $weekday = $first_day_of_month;

                    while ($current_day <= $days_in_month) {
                        if ($weekday == 7) {
                            echo '</tr><tr>';
                            $weekday = 0;
                        }

                        $date_str = sprintf("%04d-%02d-%02d", $year, $month, $current_day);
                        $is_active = in_array($date_str, $active_days);
                        $is_today = ($date_str == date('Y-m-d'));
                        
                        $classes = 'calendar-day';
                        if ($is_active) $classes .= ' has-data';
                        if ($is_today) $classes .= ' today';

                        echo "<td class='$classes' onclick='window.location=\"weekly_report.php?date=$date_str\"'>";
                        echo "<div class='day-number'>$current_day</div>";
                        if ($is_active) {
                            echo "<div class='text-center mt-1'><span class='badge bg-success badge-data'>Data Available</span></div>";
                        }
                        echo "</td>";

                        $current_day++;
                        $weekday++;
                    }

                    // Empty cells after last day
                    while ($weekday < 7) {
                        echo '<td class="other-month"></td>';
                        $weekday++;
                    }
                    ?>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
    echo ob_get_clean();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Calendar View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .calendar-table { table-layout: fixed; width: 100%; min-width: 700px; border-collapse: collapse; }
        .calendar-table th, .calendar-table td { border: 1px solid #dee2e6; }
        .calendar-card { min-height: 350px; overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid #dee2e6; border-radius: 8px; }
        .calendar-table th { background-color: #198754; color: white; width: 14.28%; font-size: 0.85rem; padding: 12px 5px; text-align: center; }
        .calendar-day { height: 110px; position: relative; cursor: pointer; transition: background 0.2s; padding: 10px; vertical-align: top; background-color: #fff; }
        .calendar-day:hover { background-color: #f8f9fa; }
        .day-number { font-weight: bold; font-size: 1.1rem; color: #333; margin-bottom: 8px; display: block; }
        .has-data { background-color: #e8f5e9 !important; }
        .today { background-color: #fffde7 !important; outline: 2px solid #fbc02d; outline-offset: -2px; }
        .other-month { background-color: #fafafa; color: #bdbdbd; cursor: default; }
        .badge-data { 
            font-size: 0.75rem; 
            padding: 5px 10px; 
            border-radius: 20px; 
            font-weight: 500; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            background-color: #2e7d32; 
            color: white;
            display: inline-block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ranking-container {
            max-height: 420px;
            overflow-y: auto;
        }
        
        @media (max-width: 768px) {
            .calendar-day { height: 95px; padding: 6px; }
            .day-number { font-size: 1rem; margin-bottom: 4px; }
            .badge-data { font-size: 0.65rem; padding: 3px 8px; }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">BBMS Admin</a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbar">
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#editAdminModal">
                    <i class="bi bi-person-circle me-1"></i> <span id="header-admin-username"><?php echo htmlspecialchars(trim($_SESSION['admin_username'] ?? '') !== '' ? $_SESSION['admin_username'] : 'Admin'); ?></span>
                </a>
                <a class="nav-link active" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="manage_employees.php">Employees</a>
                <a class="nav-link text-white opacity-75" href="logout.php">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="d-flex justify-content-start mb-3">
        <div class="btn-group">
            <a href="?year=<?php echo $prev_year; ?>&month=<?php echo $prev_month; ?>" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i> Prev</a>
            <button class="btn btn-dark disabled px-2 px-sm-3"><?php echo "$month_name $year"; ?></button>
            <a href="?year=<?php echo $next_year; ?>&month=<?php echo $next_month; ?>" class="btn btn-outline-secondary">Next <i class="bi bi-chevron-right"></i></a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Calendar (Left) -->
        <div class="col-lg-8">
            <div class="card shadow calendar-card h-100" id="calendar-container">
                <div class="card-body p-0">
                    <table class="table table-bordered calendar-table mb-0">
                <thead>
                    <tr>
                        <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        // Empty cells before first day
                        for ($i = 0; $i < $first_day_of_month; $i++) {
                            echo '<td class="other-month"></td>';
                        }

                        $current_day = 1;
                        $weekday = $first_day_of_month;

                        while ($current_day <= $days_in_month) {
                            if ($weekday == 7) {
                                echo '</tr><tr>';
                                $weekday = 0;
                            }

                            $date_str = sprintf("%04d-%02d-%02d", $year, $month, $current_day);
                            $is_active = in_array($date_str, $active_days);
                            $is_today = ($date_str == date('Y-m-d'));
                            
                            $classes = 'calendar-day';
                            if ($is_active) $classes .= ' has-data';
                            if ($is_today) $classes .= ' today';

                            echo "<td class='$classes' onclick='window.location=\"weekly_report.php?date=$date_str\"'>";
                            echo "<div class='day-number'>$current_day</div>";
                            if ($is_active) {
                                echo "<div class='text-center mt-1'><span class='badge bg-success badge-data'>Data Available</span></div>";
                            }
                            echo "</td>";

                            $current_day++;
                            $weekday++;
                        }

                        // Empty cells after last day
                        while ($weekday < 7) {
                            echo '<td class="other-month"></td>';
                            $weekday++;
                        }
                        ?>
                    </tr>
                </tbody>
            </table>
                </div>
            </div>
        </div>

        <!-- Recent Submissions Log (Right of calendar) -->
        <div class="col-lg-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-success text-white py-3">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Submission Logs</h6>
                </div>
                <div class="card-body p-0" style="max-height: 420px; overflow-y: auto;">
                    <ul class="list-group list-group-flush small mb-0" id="submissions-log-list">
                        <?php if (empty($submission_logs)): ?>
                            <li class="list-group-item text-center py-3 text-muted">No submissions this month yet.</li>
                        <?php else: ?>
                            <?php foreach ($submission_logs as $log): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-start py-2">
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($log['name']); ?></div>
                                        <div class="text-muted small">
                                            <?php echo date('M d, Y g:i A', strtotime($log['submitted_at'])); ?>
                                        </div>
                                        <?php if (!empty($log['session_id'])): ?>
                                            <div class="text-muted" style="font-size: 0.7rem;">ID: <?php echo htmlspecialchars($log['session_id']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-success fw-bold">₱<?php echo number_format($log['amount'], 0); ?></div>
                                        <div class="badge bg-light text-dark border mt-1"><?php echo htmlspecialchars($log['status']); ?></div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-3 text-muted small">
        <!-- <i class="bi bi-info-circle"></i> Click on any day to view the detailed weekly report for that period. Days highlighted in green have submitted data. -->
    </div>

    <!-- Analytics Section -->
    <div class="row mt-5 mb-5 g-4">
        <!-- Monthly Profit Bar Graph (Left - Wider) -->
        <div class="col-lg-8">
            <div class="card shadow border-0 h-100">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-bar-chart-fill me-2"></i> Monthly Profit Overview (<?php echo $month_name; ?>)</h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlyProfitChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- Profit Ranking (Right) -->
        <div class="col-lg-4">
            <div class="card shadow border-0 h-100">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-trophy-fill me-2"></i> Rankings</h5>
                </div>
                <div class="card-body p-0 d-flex flex-column">
                    <div class="ranking-container flex-grow-1">
                        <ul class="list-group list-group-flush" id="ranking-list">
                            <?php if (empty($ranking_data)): ?>
                                <li class="list-group-item text-center py-4 text-muted">No data available for this month</li>
                            <?php else: ?>
                            <?php foreach ($ranking_data as $index => $rank): ?>
                                <?php $globalRank = $index + 1; ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <div class="d-flex align-items-center">
                                        <span class="badge <?php 
                                            echo $globalRank == 1 ? 'bg-warning' : ($globalRank == 2 ? 'bg-secondary-subtle text-dark' : ($globalRank == 3 ? 'bg-danger-subtle text-dark' : 'bg-light text-dark border')); 
                                        ?> rounded-circle me-3" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                            <?php echo $globalRank; ?>
                                        </span>
                                        <span class="fw-bold"><?php echo htmlspecialchars($rank['name']); ?></span>
                                    </div>
                                    <span class="text-success fw-bold">₱<?php echo number_format($rank['total_profit'], 0); ?></span>
                                </li>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Admin Account Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-person-gear me-2"></i>Edit Admin Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editAdminForm">
                <div class="modal-body p-0">
                    <!-- Nav Tabs -->
                    <ul class="nav nav-tabs nav-justified" id="accountTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-3" id="username-tab" data-bs-toggle="tab" data-bs-target="#username-pane" type="button" role="tab" aria-selected="true">
                                <i class="bi bi-person me-1"></i> Username
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-3" id="password-tab" data-bs-toggle="tab" data-bs-target="#password-pane" type="button" role="tab" aria-selected="false">
                                <i class="bi bi-key me-1"></i> Password
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content p-4">
                        <div id="adminUpdateAlert" class="alert d-none mb-3"></div>
                        
                        <input type="hidden" name="update_type" id="update_type" value="username">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Confirm Identity</label>
                            <input type="password" name="current_password" id="current_password" class="form-control" placeholder="Enter current password" required>
                        </div>

                        <!-- Username Section -->
                        <div class="tab-pane fade show active" id="username-pane" role="tabpanel" aria-labelledby="username-tab">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark">New Username</label>
                                <input type="text" name="admin_username" id="admin_username" class="form-control" value="<?php echo htmlspecialchars(trim($_SESSION['admin_username'] ?? '') !== '' ? $_SESSION['admin_username'] : 'Admin'); ?>">
                            </div>
                        </div>

                        <!-- Password Section -->
                        <div class="tab-pane fade" id="password-pane" role="tabpanel" aria-labelledby="password-tab">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark">New Password</label>
                                <input type="password" name="admin_password" id="admin_password" class="form-control" placeholder="Min. 6 characters">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Re-enter new password">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4">Apply Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Handle Tab Switching for Update Type
document.getElementById('username-tab').addEventListener('click', () => {
    document.getElementById('update_type').value = 'username';
    document.getElementById('admin_username').required = true;
    document.getElementById('admin_password').required = false;
    document.getElementById('confirm_password').required = false;
});
document.getElementById('password-tab').addEventListener('click', () => {
    document.getElementById('update_type').value = 'password';
    document.getElementById('admin_username').required = false;
    document.getElementById('admin_password').required = true;
    document.getElementById('confirm_password').required = true;
});

// Handle Admin Account Update
document.getElementById('editAdminForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('update_admin_account', '1');
    
    const alertDiv = document.getElementById('adminUpdateAlert');
    const saveBtn = this.querySelector('button[type="submit"]');
    
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    fetch('dashboard.php?ajax=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = 'Save Changes';
        
        alertDiv.textContent = data.message;
        alertDiv.classList.remove('d-none', 'alert-success', 'alert-danger');
        alertDiv.classList.add(data.success ? 'alert-success' : 'alert-danger');
        
        if (data.success) {
            if (typeof data.username === 'string' && data.username.trim() !== '') {
                document.getElementById('header-admin-username').textContent = data.username;
            }
            setTimeout(() => {
                const modalEl = document.getElementById('editAdminModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                alertDiv.classList.add('d-none');
                document.getElementById('current_password').value = '';
                document.getElementById('admin_password').value = '';
                document.getElementById('confirm_password').value = '';
            }, 1500);
        }
    })
    .catch(err => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = 'Save Changes';
        alertDiv.textContent = 'An error occurred. Please try again.';
        alertDiv.classList.remove('d-none', 'alert-success');
        alertDiv.classList.add('alert-danger');
    });
});

const currentYear = '<?php echo $year; ?>';
const currentMonth = '<?php echo $month; ?>';

// Chart Initialization
let monthlyChart;
const ctx = document.getElementById('monthlyProfitChart').getContext('2d');

function initChart(labels, data) {
    if (monthlyChart) {
        monthlyChart.destroy();
    }
    monthlyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Daily Profit (₱)',
                data: data,
                backgroundColor: 'rgba(25, 135, 84, 0.7)',
                borderColor: 'rgb(25, 135, 84)',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Profit: ₱' + context.raw.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Day of Month'
                    }
                }
            }
        }
    });
}

initChart(<?php echo json_encode($chart_labels); ?>, <?php echo json_encode($chart_data); ?>);

function refreshData() {
    // 1. Refresh Calendar
    fetch(`dashboard.php?year=${currentYear}&month=${currentMonth}&ajax=1`)
        .then(response => response.text())
        .then(html => {
            const container = document.getElementById('calendar-container');
            if (container.innerHTML !== html) {
                container.innerHTML = html;
            }
        })
        .catch(err => console.error('Calendar refresh error:', err));

    // 2. Refresh Recent Submissions
    fetch(`dashboard.php?year=${currentYear}&month=${currentMonth}&ajax=1&submissions=1`)
        .then(response => response.text())
        .then(html => {
            const container = document.getElementById('submissions-log-list');
            if (container && container.innerHTML !== html) {
                container.innerHTML = html;
            }
        })
        .catch(err => console.error('Submissions refresh error:', err));

    // 3. Refresh Ranking
    fetch(`dashboard.php?year=${currentYear}&month=${currentMonth}&ajax=1&ranking=1`)
        .then(response => response.text())
        .then(html => {
            const container = document.getElementById('ranking-list');
            if (container.innerHTML !== html) {
                container.innerHTML = html;
            }
        })
        .catch(err => console.error('Ranking refresh error:', err));

    // 3. Refresh Chart
    fetch(`dashboard.php?year=${currentYear}&month=${currentMonth}&ajax=1&chart=1`)
        .then(response => response.json())
        .then(json => {
            // Compare data to avoid chart flicker if nothing changed
            const currentData = monthlyChart.data.datasets[0].data;
            if (JSON.stringify(currentData) !== JSON.stringify(json.data)) {
                monthlyChart.data.labels = json.labels;
                monthlyChart.data.datasets[0].data = json.data;
                monthlyChart.update('none'); // Update without animation for seamless feel
            }
        })
        .catch(err => console.error('Chart refresh error:', err));
}

// Start polling
setInterval(refreshData, 3000);
</script>
</body>
</html>
