<?php
require_once 'db.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_profit'])) {
    $employee_id = $_POST['employee_id'];
    $profit_amount = $_POST['profit_amount'];
    $is_training = isset($_POST['is_training']) ? 1 : 0;

    if (!empty($employee_id) && !empty($profit_amount)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO profits (employee_id, profit_amount, is_training) VALUES (?, ?, ?)");
            $stmt->execute([$employee_id, $profit_amount, $is_training]);
            $message = "<div class='alert alert-success'>Profit submitted successfully!</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Please fill in all required fields.</div>";
    }
}

// Fetch employees for dropdown with their assigned places
$employees = $pdo->query("SELECT id, name, assigned_place FROM employees ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BBMS - Employee Profit Entry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 600px; margin-top: 50px; }
        .card { border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">BBMS</a>
        <div class="ms-auto">
            <a href="login.php" class="btn btn-outline-light">Management</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="card p-4">
        <h2 class="text-center mb-4">Daily Profit Entry</h2>
        
        <?php echo $message; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="employee_id" class="form-label">Employee Name</label>
                <select name="employee_id" id="employee_id" class="form-select" required>
                    <option value="">-- Select Employee --</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>">
                            <?php echo htmlspecialchars($emp['name']); ?> 
                            <?php echo !empty($emp['assigned_place']) ? "(".htmlspecialchars($emp['assigned_place']).")" : ""; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="profit_amount" class="form-label">Profit Amount</label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" step="0.01" name="profit_amount" id="profit_amount" class="form-control" placeholder="0.00" required>
                </div>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" name="is_training" id="is_training" class="form-check-input">
                <label class="form-check-label" for="is_training">Undergoing Training?</label>
            </div>

            <div class="d-grid">
                <button type="submit" name="submit_profit" class="btn btn-primary btn-lg">Submit Profit</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>