<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$target_date = isset($_GET['date']) ? $_GET['date'] : (isset($_GET['start']) ? $_GET['start'] : date('Y-m-d'));
$timestamp = strtotime($target_date);

// Get start (Sunday) and end (Saturday) of the week for the target date
$dayOfWeek = date('w', $timestamp);
$startOfWeek = date('Y-m-d', strtotime("-" . $dayOfWeek . " days", $timestamp));
$endOfWeek = date('Y-m-d', strtotime("+" . (6 - $dayOfWeek) . " days", $timestamp));

// Fetch summary data for the selected week
$summary_stmt = $pdo->prepare("SELECT SUM(amount) FROM profits WHERE DATE(profit_date) BETWEEN ? AND ?");
$summary_stmt->execute([$startOfWeek, $endOfWeek]);
$total_profit = $summary_stmt->fetchColumn();

$total_employees = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();

// Fetch weekly data grouped by employee and day for the specific week
$weekly_sql = "
    SELECT 
        e.id, 
        e.name, 
        DAYOFWEEK(p.profit_date) as day_num,
        SUM(p.amount) as daily_total
    FROM employees e
    LEFT JOIN profits p ON e.id = p.employee_id 
        AND p.profit_date BETWEEN :start AND :end
    GROUP BY e.id, day_num
    ORDER BY e.name ASC
";
$employee_data = [];
$employees = $pdo->query("SELECT id, name FROM employees ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($employees as $emp) {
    $employee_data[$emp['id']] = [
        'name' => $emp['name'],
        'days' => array_fill(1, 7, ['amount' => 0, 'status' => 'Regular', 'other_text' => null]),
        'total' => 0
    ];
}

// Fetch profits for the specific week
$stmt = $pdo->prepare("SELECT employee_id, amount, status, other_status_text, profit_date 
                       FROM profits 
                       WHERE profit_date >= ? AND profit_date <= ?");
$stmt->execute([$startOfWeek, $endOfWeek]);
$profits = $stmt->fetchAll();

foreach ($profits as $p) {
    if (isset($employee_data[$p['employee_id']])) {
        // Calculate day index (1=Sunday, 2=Monday, ..., 7=Saturday)
        $p_timestamp = strtotime($p['profit_date']);
        $p_day_idx = date('w', $p_timestamp) + 1; // date('w') returns 0-6, we need 1-7
        
        $employee_data[$p['employee_id']]['days'][$p_day_idx] = [
            'amount' => $p['amount'],
            'status' => $p['status'],
            'other_text' => $p['other_status_text']
        ];
        $employee_data[$p['employee_id']]['total'] += $p['amount'];
    }
}

$days = [
    1 => 'Sunday',
    2 => 'Monday',
    3 => 'Tuesday',
    4 => 'Wednesday',
    5 => 'Thursday',
    6 => 'Friday',
    7 => 'Saturday'
];
// Partial update for AJAX
if (isset($_GET['ajax'])) {
    // Only output the inner content of report-container
    ob_start();
    ?>
    <div class="table-responsive">
        <table class="table table-bordered table-hover table-weekly mb-0">
            <thead class="table-success text-dark">
                <tr>
                    <th>Employee</th>
                    <?php for ($i = 1; $i <= 7; $i++): 
                        $current_date = date('m/d/Y', strtotime("+" . ($i - 1) . " days", strtotime($startOfWeek)));
                        // Use short 3-letter day name to avoid wrapping on narrow screens
                        $day_name = date('D', strtotime($current_date));
                    ?>
                        <th>
                            <?php echo $day_name; ?><br>
                            <small class="fw-normal text-dark opacity-75"><?php echo $current_date; ?></small>
                        </th>
                    <?php endfor; ?>
                    <th class="bg-secondary text-white">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employee_data as $emp_id => $data): ?>
                <tr>
                    <td><?php echo htmlspecialchars($data['name']); ?></td>
                    <?php for ($i = 1; $i <= 7; $i++): 
                        $day = $data['days'][$i];
                        $display = '-';
                        $class = 'text-muted';
                        $current_date_obj = date('Y-m-d', strtotime("+" . ($i - 1) . " days", strtotime($startOfWeek)));
                        
                        if ($day['status'] !== 'Regular' || $day['amount'] > 0) {
                            if ($day['status'] === 'Regular') {
                                $display = '₱' . number_format($day['amount'], 0);
                                $class = 'text-success fw-bold';
                            } else {
                                $status_label = $day['status'];
                                if ($day['status'] === 'Others' && !empty($day['other_text'])) {
                                    $status_label = htmlspecialchars($day['other_text']);
                                }
                                
                                $badge_class = 'bg-secondary';
                                if ($day['status'] === 'Training') $badge_class = 'bg-info text-dark';
                                if ($day['status'] === 'Leave') $badge_class = 'bg-warning text-dark';
                                if ($day['status'] === 'Sick') $badge_class = 'bg-danger';
                                
                                $display = "<span class='badge $badge_class'>$status_label</span>";
                                if ($day['amount'] > 0) {
                                    $display .= "<br><small class='text-success'>₱" . number_format($day['amount'], 0) . "</small>";
                                }
                                $class = 'text-center';
                            }
                        }
                    ?>
                        <td class="<?php echo $class; ?> editable-cell" 
                            data-employee-id="<?php echo $emp_id; ?>" 
                            data-date="<?php echo $current_date_obj; ?>"
                            data-current-amount="<?php echo $day['amount']; ?>"
                            data-current-status="<?php echo htmlspecialchars($day['status']); ?>"
                            data-current-other="<?php echo htmlspecialchars($day['other_text'] ?? ''); ?>"
                            onclick="makeEditable(this)">
                            <?php echo $display; ?>
                        </td>
                    <?php endfor; ?>
                    <td class="bg-total">₱<?php echo number_format($data['total'], 0); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <td>Daily Totals</td>
                    <?php 
                    $grand_weekly_total = 0;
                    for ($i = 1; $i <= 7; $i++): 
                        $day_total = 0;
                        foreach ($employee_data as $data) {
                            $day_total += $data['days'][$i]['amount'];
                        }
                        $grand_weekly_total += $day_total;
                    ?>
                        <td>₱<?php echo number_format($day_total, 0); ?></td>
                    <?php endfor; ?>
                    <td class="bg-dark text-white">₱<?php echo number_format($grand_weekly_total, 2); ?></td>
                </tr>
            </tfoot>
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
    <title>Weekly Report - BBMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        /* Base table layout; scrolling behavior controlled by media queries */
        .table-weekly { 
            table-layout: fixed; 
            width: 100%; 
            border-collapse: collapse; 
        }
        .table-weekly th, .table-weekly td { 
            text-align: center; 
            vertical-align: middle; 
            font-size: 0.85rem; 
            padding: 8px 4px; 
            word-wrap: break-word; 
            overflow-wrap: break-word; 
            white-space: normal;
        }
        .editable-cell { cursor: pointer; transition: background 0.2s; position: relative; min-width: 100px; }
        .editable-cell:hover { background-color: #f8f9fa !important; }
        .edit-input { 
            width: 100%; 
            min-width: 60px;
            text-align: center; 
            border: 2px solid #198754;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 0.85rem;
            margin: 0 auto;
        }
        .edit-input:focus {
            outline: none;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }
        .table-weekly th:first-child, .table-weekly td:first-child { text-align: left; font-weight: bold; width: 150px; min-width: 150px; }
        .bg-total { background-color: #e9ecef !important; font-weight: bold; width: 100px; min-width: 100px; }

        /* Phone-sized screens: force horizontal scroll with a wider min-width */
        @media (max-width: 576px) {
            .table-weekly { 
                font-size: 0.75rem; 
                min-width: 900px; /* triggers horizontal scrolling only on small screens */
            }
            .table-weekly th, .table-weekly td { padding: 4px 2px; }
            .table-weekly th:first-child, .table-weekly td:first-child { width: 100px; min-width: 100px; }
            .editable-cell { min-width: 80px; }
        }

        /* Print: ensure full width and no scrollbars */
        @media print {
            .table-weekly {
                width: 100% !important;
                min-width: 0 !important;
            }
            .table-responsive {
                overflow: visible !important;
            }
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
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="manage_employees.php">Employees</a>
                <a class="nav-link text-white opacity-75" href="logout.php">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4 px-4 text-center text-md-start">
    <div class="mb-4">
        <h2 class="mb-1">Weekly Profit Report</h2>
        <p class="text-muted mb-0">Week of <?php echo date('M d', strtotime($startOfWeek)); ?> - <?php echo date('M d, Y', strtotime($endOfWeek)); ?></p>
    </div>
    
    <div class="card p-4 shadow-sm border-0">
        <div id="report-container">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-weekly mb-0">
                    <thead class="table-success text-dark">
                        <tr>
                            <th>Employee</th>
                            <?php for ($i = 1; $i <= 7; $i++): 
                                $current_date = date('m/d/Y', strtotime("+" . ($i - 1) . " days", strtotime($startOfWeek)));
                                // Use short 3-letter day name to avoid wrapping on narrow screens
                                $day_name = date('D', strtotime($current_date));
                            ?>
                                <th>
                                    <?php echo $day_name; ?><br>
                                    <small class="fw-normal text-dark opacity-75"><?php echo $current_date; ?></small>
                                </th>
                            <?php endfor; ?>
                            <th class="bg-secondary text-white">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employee_data as $emp_id => $data): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($data['name']); ?></td>
                            <?php for ($i = 1; $i <= 7; $i++): 
                                $day = $data['days'][$i];
                                $display = '-';
                                $class = 'text-muted';
                                $current_date_obj = date('Y-m-d', strtotime("+" . ($i - 1) . " days", strtotime($startOfWeek)));
                                
                                if ($day['status'] !== 'Regular' || $day['amount'] > 0) {
                                    if ($day['status'] === 'Regular') {
                                        $display = '₱' . number_format($day['amount'], 0);
                                        $class = 'text-success fw-bold';
                                    } else {
                                        $status_label = $day['status'];
                                        if ($day['status'] === 'Others' && !empty($day['other_text'])) {
                                            $status_label = htmlspecialchars($day['other_text']);
                                        }
                                        
                                        $badge_class = 'bg-secondary';
                                        if ($day['status'] === 'Training') $badge_class = 'bg-info text-dark';
                                        if ($day['status'] === 'Leave') $badge_class = 'bg-warning text-dark';
                                        if ($day['status'] === 'Sick') $badge_class = 'bg-danger';
                                        
                                        $display = "<span class='badge $badge_class'>$status_label</span>";
                                        if ($day['amount'] > 0) {
                                            $display .= "<br><small class='text-success'>₱" . number_format($day['amount'], 0) . "</small>";
                                        }
                                        $class = 'text-center';
                                    }
                                }
                            ?>
                                <td class="<?php echo $class; ?> editable-cell" 
                                    data-employee-id="<?php echo $emp_id; ?>" 
                                    data-date="<?php echo $current_date_obj; ?>"
                                    data-current-amount="<?php echo $day['amount']; ?>"
                                    data-current-status="<?php echo htmlspecialchars($day['status']); ?>"
                                    data-current-other="<?php echo htmlspecialchars($day['other_text'] ?? ''); ?>"
                                    onclick="makeEditable(this)">
                                    <?php echo $display; ?>
                                </td>
                            <?php endfor; ?>
                            <td class="bg-total">₱<?php echo number_format($data['total'], 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td>Daily Totals</td>
                            <?php 
                            $grand_weekly_total = 0;
                            for ($i = 1; $i <= 7; $i++): 
                                $day_total = 0;
                                foreach ($employee_data as $data) {
                                    $day_total += $data['days'][$i]['amount'];
                                }
                                $grand_weekly_total += $day_total;
                            ?>
                                <td>₱<?php echo number_format($day_total, 0); ?></td>
                            <?php endfor; ?>
                            <td class="bg-dark text-white">₱<?php echo number_format($grand_weekly_total, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center py-4">
                <div class="spinner-border text-success" role="status" aria-hidden="true"></div>
                <div class="mt-3 fw-semibold">Updating...</div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let isEditing = false;
const startOfWeek = '<?php echo $startOfWeek; ?>';
let loadingModalInstance = null;
let isManualUpdateInFlight = false;

function getLoadingModal() {
    if (loadingModalInstance) return loadingModalInstance;
    const el = document.getElementById('loadingModal');
    if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) return null;
    loadingModalInstance = bootstrap.Modal.getOrCreateInstance(el, { backdrop: 'static', keyboard: false });
    return loadingModalInstance;
}

function showLoading() {
    const m = getLoadingModal();
    if (m) m.show();
}

function hideLoading() {
    const m = getLoadingModal();
    if (m) m.hide();
}

function setOthersVisibility(selectEl, otherInputEl) {
    if (!selectEl || !otherInputEl) return;
    const show = (selectEl.value === 'Others');
    otherInputEl.style.display = show ? '' : 'none';
}

function makeEditable(cell) {
	if (cell.querySelector('input') || cell.querySelector('select')) return;
	isEditing = true;

	const originalContent = cell.innerHTML;
	const empId = cell.getAttribute('data-employee-id');
	const date = cell.getAttribute('data-date');
	const currentAmount = cell.getAttribute('data-current-amount') ?? '0';
	const currentStatus = cell.getAttribute('data-current-status') || 'Regular';
	const currentOther = cell.getAttribute('data-current-other') || '';

	const wrapper = document.createElement('div');
	wrapper.className = 'd-flex flex-column gap-1';

	const input = document.createElement('input');
	input.type = 'number';
	input.step = '0.01';
	input.className = 'form-control form-control-sm edit-input';
	input.value = currentAmount;

	const select = document.createElement('select');
	select.className = 'form-select form-select-sm';
	select.innerHTML = `
		<option value="Regular">None</option>
		<option value="Training">Training</option>
		<option value="Leave">Leave</option>
		<option value="Sick">Sick</option>
		<option value="Others">Others</option>
	`;
	select.value = currentStatus || 'Regular';

	const otherInput = document.createElement('input');
	otherInput.type = 'text';
	otherInput.className = 'form-control form-control-sm';
	otherInput.placeholder = 'Specify...';
	otherInput.value = currentOther;
	setOthersVisibility(select, otherInput);
	select.addEventListener('change', () => setOthersVisibility(select, otherInput));
	
	cell.innerHTML = '';
	wrapper.appendChild(input);
	wrapper.appendChild(select);
	wrapper.appendChild(otherInput);
	cell.appendChild(wrapper);
	input.focus();
	input.select();

    // Prevent click propagation to avoid re-triggering
    [input, select, otherInput].forEach((el) => {
        el.onclick = function(e) {
            e.stopPropagation();
        };
    });

    let isSaving = false;

    function saveOrRevertIfNeeded() {
        if (isSaving) return;
        const nextAmount = input.value;
        const nextStatus = select.value;
        const nextOther = (otherInput.value || '').trim();
        const changed = (String(nextAmount) !== String(currentAmount)) || (String(nextStatus) !== String(currentStatus)) || (String(nextOther) !== String(currentOther));
        if (!changed) {
            cell.innerHTML = originalContent;
            isEditing = false;
            return;
        }
        isSaving = true;
        saveEdit(cell, empId, date, nextAmount, nextStatus, nextOther, originalContent);
    }

	cell.addEventListener('focusout', function() {
		setTimeout(() => {
			if (cell.contains(document.activeElement)) return;
			saveOrRevertIfNeeded();
		}, 0);
	}, { once: true });

	[input, select, otherInput].forEach((el) => {
		el.onkeydown = function(e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				saveOrRevertIfNeeded();
			}
			if (e.key === 'Escape') {
				isSaving = true;
				cell.innerHTML = originalContent;
				isEditing = false;
			}
		};
	});
}

function saveEdit(cell, empId, date, newAmount, newStatus, newOther, originalContent) {
	const formData = new URLSearchParams();
	formData.append('employee_id', empId);
	formData.append('date', date);
	formData.append('amount', newAmount);
	formData.append('status', newStatus);
	formData.append('other_status_text', newOther);

	isManualUpdateInFlight = true;
	showLoading();

    fetch('update_profit.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            isEditing = false;
            refreshData(true)
                .then(() => {
                    
                })
                .finally(() => {
                    isManualUpdateInFlight = false;
                    hideLoading();
                });
        } else {
            alert('Error updating: ' + data.message);
            cell.innerHTML = originalContent;
            isEditing = false;
			isManualUpdateInFlight = false;
			hideLoading();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred.');
        cell.innerHTML = originalContent;
        isEditing = false;
		isManualUpdateInFlight = false;
		hideLoading();
    });
}

function refreshData(fromManualSave = false) {
    if (isEditing) return Promise.resolve(); // Don't refresh while user is typing

    // Only show loading for manual saves, not for the 3s polling refresh.
    if (fromManualSave && !isManualUpdateInFlight) {
        isManualUpdateInFlight = true;
        showLoading();
    }

    return fetch(`weekly_report.php?start=${startOfWeek}&ajax=1`)
        .then(response => response.text())
        .then(html => {
            const container = document.getElementById('report-container');
            if (!container) return; // Safety check

            // Only update if content changed to avoid flicker
            if (container.innerHTML !== html) {
                container.innerHTML = html;
            }
        })
		.catch(err => console.error('Refresh error:', err))
		.finally(() => {
			if (fromManualSave) {
				isManualUpdateInFlight = false;
				hideLoading();
			}
		});
}

// Start polling
setInterval(refreshData, 3000);
</script>
</body>
</html>
