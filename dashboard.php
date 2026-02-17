<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// Fetch summary data
$total_profit = $pdo->query("SELECT SUM(profit_amount) FROM profits")->fetchColumn();
$total_employees = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$recent_submissions = $pdo->query("SELECT p.*, e.name as employee_name 
                                    FROM profits p 
                                    JOIN employees e ON p.employee_id = e.id 
                                    ORDER BY p.submitted_at DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BBMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">BBMS Admin</a>
        <div class="navbar-nav">
            <a class="nav-link active" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="manage_employees.php">Employees</a>
            <a class="nav-link text-danger" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h2 class="mb-4">Dashboard</h2>
    
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card bg-primary text-white p-3 text-center">
                <h3>Total Profit</h3>
                <h2>₱<?php echo number_format($total_profit, 2); ?></h2>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card bg-success text-white p-3 text-center">
                <h3>Total Employees</h3>
                <h2><?php echo $total_employees; ?></h2>
            </div>
        </div>
    </div>

    <h3>Recent Profit Submissions</h3>
    <table class="table table-hover mt-3">
        <thead class="table-light">
            <tr>
                <th>Employee</th>
                <th>Amount</th>
                <th>Training</th>
                <th>Date/Time</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recent_submissions as $sub): ?>
            <tr>
                <td><?php echo htmlspecialchars($sub['employee_name']); ?></td>
                <td>₱<?php echo number_format($sub['profit_amount'], 2); ?></td>
                <td><?php echo $sub['is_training'] ? '<span class="badge bg-warning text-dark">Yes</span>' : 'No'; ?></td>
                <td><?php echo date('M d, Y H:i', strtotime($sub['submitted_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
