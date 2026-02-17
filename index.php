<?php
session_start();
require_once 'db.php';

$session_id = session_id();
$message = "";

// Show status message from redirect
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $message = "<div class='alert alert-success'>Entry submitted successfully!</div>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_profit'])) {
    $employee_id = $_POST['employee_id'];
    $profit_amount = $_POST['profit_amount'];
    $status = $_POST['status'] ?? 'Regular';
    $other_status_text = ($status == 'Others') ? ($_POST['other_status_text'] ?? '') : null;

    if (!empty($employee_id)) {
        try {
            // Normalize amount
            $normalized_amount = $profit_amount !== '' ? (float)$profit_amount : 0;

            // Today in Y-m-d (same basis used in weekly_report.php)
            $today = date('Y-m-d');

            // Ensure only ONE row per employee per day:
            // delete any existing rows for this employee & date, then insert a fresh one.
            $deleteStmt = $pdo->prepare("DELETE FROM profits WHERE employee_id = ? AND DATE(profit_date) = ?");
            $deleteStmt->execute([$employee_id, $today]);

            // Insert new record for today with accurate current time and session ID
            $dateTime = date('Y-m-d H:i:s');
            $insertStmt = $pdo->prepare("INSERT INTO profits (employee_id, amount, profit_date, status, other_status_text, session_id) VALUES (?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([$employee_id, $normalized_amount, $dateTime, $status, $other_status_text, $session_id]);

            // Redirect to avoid duplicate submissions on refresh (POST-Redirect-GET)
            header('Location: index.php?status=success');
            exit;

        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Please select an employee.</div>";
    }
}

// Fetch employees for dropdown
$employees = $pdo->query("SELECT id, name FROM employees ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BBMS - Employee Profit Entry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 600px; margin-top: 50px; }
        .card { border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .toast-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1055;
            width: auto;
            min-width: 300px;
            pointer-events: none;
        }
        .custom-toast {
            pointer-events: auto;
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            padding: 12px 20px;
            animation: slideDown 0.4s ease-out;
        }
        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .toast-danger { 
            background: #f8d7da;
            color: #842029;
            border-color: #f5c2c7;
        }
        .toast-warning { 
            background: #fff3cd;
            color: #664d03;
            border-color: #ffecb5;
        }
        .toast-icon { font-size: 1.25rem; margin-right: 12px; }
    </style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">BBMS</a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#userNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="userNavbar">
            <div class="ms-auto navbar-nav">
                <a href="login.php" class="nav-link btn btn-light text-dark px-3 mt-2 mt-lg-0 fw-semibold">Management</a>
            </div>
        </div>
    </div>
</nav>

<div class="container">
    <div class="card p-4">
        <h2 class="text-center mb-4">Daily Profit Entry</h2>
        
        <form method="POST" action="">
            

            <div class="mb-3">
                <label for="employee_id" class="form-label"><b>Employee Name</b></label>
                <select name="employee_id" id="employee_id" class="form-select" required>
                    <option value="">-- Select Employee --</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>">
                            <?php echo htmlspecialchars($emp['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="profit_amount" class="form-label"><b>Profit Amount</b></label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" step="0.01" name="profit_amount" id="profit_amount" class="form-control" placeholder="0.00" oninput="updateSessionIdWithDetails()">
                </div>
                
            </div>
            <div class="mb-3">
                <label class="form-label"><b>Session ID</b></label>
                <div class="input-group">
                    <input type="text" class="form-control bg-light" id="sessionIdInput" value="<?php echo htmlspecialchars($session_id); ?>" readonly>
                    <button class="btn btn-outline-success" type="button" onclick="copySessionId()" title="Copy Session ID">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label d-block"><b>Status</b></label>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="status" id="statusRegular" value="Regular" checked onchange="toggleOtherInput(); updateSessionIdWithDetails();">
                        <label class="form-check-label" for="statusRegular">Regular</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="status" id="statusTraining" value="Training" onchange="toggleOtherInput(); updateSessionIdWithDetails();">
                        <label class="form-check-label" for="statusTraining">Training</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="status" id="statusLeave" value="Leave" onchange="toggleOtherInput(); updateSessionIdWithDetails();">
                        <label class="form-check-label" for="statusLeave">Leave</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="status" id="statusSick" value="Sick" onchange="toggleOtherInput(); updateSessionIdWithDetails();">
                        <label class="form-check-label" for="statusSick">Sick</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="status" id="statusOthers" value="Others" onchange="toggleOtherInput(); updateSessionIdWithDetails();">
                        <label class="form-check-label" for="statusOthers">Others</label>
                    </div>
                </div>
            </div>

            <div id="other_status_div" class="mb-3 d-none">
                <label for="other_status_text" class="form-label">Specify Other Status</label>
                <input type="text" name="other_status_text" id="other_status_text" class="form-control" placeholder="Please specify..." oninput="updateSessionIdWithDetails()">
            </div>
            <br><br><br>
            <div class="d-grid">
                <button type="submit" name="submit_profit" class="btn btn-success btn-lg">Submit Entry</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateSessionIdWithDetails() {
    const amount = document.getElementById('profit_amount').value;
    const statusRadio = document.querySelector('input[name="status"]:checked');
    const status = statusRadio ? statusRadio.value : '';
    const baseSessionId = "<?php echo htmlspecialchars($session_id); ?>";
    const sessionIdInput = document.getElementById('sessionIdInput');
    
    let suffix = '';
    if (amount && amount > 0) {
        suffix += '-' + amount;
    }
    if (status) {
        // Simple mapping for display
        let displayStatus = status;
        if (status === 'Regular') displayStatus = 'REG';
        else if (status === 'Training') displayStatus = 'TRN';
        else if (status === 'Leave') displayStatus = 'LV';
        else if (status === 'Sick') displayStatus = 'SK';
        else if (status === 'Others') {
            const otherText = document.getElementById('other_status_text').value;
            displayStatus = otherText ? 'OT-' + otherText.replace(/[^a-zA-Z0-9]/g, '') : 'OT';
        }
        
        suffix += '-' + displayStatus;
    }
    
    sessionIdInput.value = baseSessionId + suffix;
}

function copySessionId() {
    const copyText = document.getElementById("sessionIdInput");
    copyText.select();
    copyText.setSelectionRange(0, 99999); // For mobile devices
    navigator.clipboard.writeText(copyText.value);
    
    // Feedback
    const btn = event.currentTarget;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2"></i> Copied!';
    btn.classList.replace('btn-outline-success', 'btn-success');
    setTimeout(() => {
        btn.innerHTML = originalHtml;
        btn.classList.replace('btn-success', 'btn-outline-success');
    }, 2000);
}

function toggleOtherInput() {
    const status = document.querySelector('input[name="status"]:checked').value;
    const otherDiv = document.getElementById('other_status_div');
    const otherInput = document.getElementById('other_status_text');
    
    if (status === 'Others') {
        otherDiv.classList.remove('d-none');
        otherInput.required = true;
    } else {
        otherDiv.classList.add('d-none');
        otherInput.required = false;
    }
}

// Toast functionality
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `custom-toast toast-${type}`;
    
    let icon = 'bi-check-circle-fill text-success';
    if (type === 'danger') icon = 'bi-exclamation-octagon-fill text-danger';
    if (type === 'warning') icon = 'bi-exclamation-triangle-fill text-warning';
    
    toast.innerHTML = `
        <i class="bi ${icon} toast-icon"></i>
        <div class="toast-body fw-medium">${message}</div>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.transition = 'all 0.4s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-20px)';
        setTimeout(() => toast.remove(), 400);
    }, 4000);
}

// Check for PHP message on load
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($message): 
        $msgText = strip_tags($message);
        $type = 'success';
        if (strpos($message, 'alert-danger') !== false) $type = 'danger';
        if (strpos($message, 'alert-warning') !== false) $type = 'warning';
    ?>
    showToast("<?php echo addslashes($msgText); ?>", "<?php echo $type; ?>");
    <?php endif; ?>
});
</script>
</body>
</html>