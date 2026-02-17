<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$employee_id = $_GET['id'] ?? null;
if (!$employee_id) {
    header("Location: manage_employees.php");
    exit;
}

// Fetch employee details
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();

if (!$employee) {
    header("Location: manage_employees.php");
    exit;
}

// Fetch all profit records for this employee
$stmt = $pdo->prepare("
    SELECT * FROM profits 
    WHERE employee_id = ? 
    ORDER BY profit_date DESC, submitted_at DESC
");
$stmt->execute([$employee_id]);
$records = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Records - <?php echo htmlspecialchars($employee['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 15px; border: none; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">BBMS Admin</a>
        <div class="collapse navbar-collapse">
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="manage_employees.php">Employees</a>
                <a class="nav-link text-white opacity-75" href="logout.php">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="manage_employees.php" class="text-success">Employees</a></li>
                    <li class="breadcrumb-item active">View Records</li>
                </ol>
            </nav>
            <h2 class="fw-bold"><?php echo htmlspecialchars($employee['name']); ?></h2>
            <p class="text-muted mb-0"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($employee['assigned_place'] ?? 'No assigned place'); ?></p>
        </div>
        <a href="manage_employees.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <label class="form-label fw-bold"><i class="bi bi-search me-1"></i> Compare Session ID</label>
            <div class="input-group">
                <input type="text" id="compareIdInput" class="form-control" placeholder="Paste Session ID here to highlight matches...">
                <button class="btn btn-outline-secondary" type="button" onclick="clearCompare()">Clear</button>
            </div>
            <div id="compareResult" class="mt-2 small"></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>Profit History</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Session ID</th>
                        <th>Location</th>
                        <th>Submitted At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($records) > 0): ?>
                        <?php foreach ($records as $row): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($row['profit_date'])); ?></td>
                            <td class="fw-bold text-success">₱<?php echo number_format($row['amount'], 2); ?></td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?php 
                                        echo htmlspecialchars($row['status']); 
                                        if ($row['status'] == 'Others' && !empty($row['other_status_text'])) {
                                            echo " (" . htmlspecialchars($row['other_status_text']) . ")";
                                        }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted font-monospace session-id-cell"><?php echo htmlspecialchars($row['session_id'] ?? 'N/A'); ?></small>
                            </td>
                            <td>
                                <?php
                                    $lat = $row['latitude'] ?? null;
                                    $lng = $row['longitude'] ?? null;
                                    $hasLoc = ($lat !== null && $lat !== '' && $lng !== null && $lng !== '');
                                    $label = $hasLoc ? (intval(round((float)$lat)) . ',' . intval(round((float)$lng))) : 'N/A';
                                ?>
                                <?php if ($hasLoc): ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-success location-btn"
                                        data-lat="<?php echo htmlspecialchars((string)$lat); ?>"
                                        data-lng="<?php echo htmlspecialchars((string)$lng); ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#locationModal"
                                    >
                                        <?php echo htmlspecialchars($label); ?>
                                    </button>
                                <?php else: ?>
                                    <small class="text-muted">N/A</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo $row['submitted_at'] ? date('M d, Y g:i A', strtotime($row['submitted_at'])) : 'N/A'; ?>
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No records found for this employee.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Location Modal -->
<div class="modal fade" id="locationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-geo-alt-fill me-2 text-success"></i>Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <div class="small text-muted">Exact Coordinates</div>
                    <div class="fw-semibold" id="locationExact">-</div>
                </div>
                <iframe
                    id="locationMapFrame"
                    title="Map preview"
                    width="100%"
                    height="320"
                    style="border: 1px solid #e5e7eb; border-radius: 12px;"
                    loading="lazy"
                    referrerpolicy="no-referrer"
                ></iframe>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const compareInput = document.getElementById('compareIdInput');
const resultDiv = document.getElementById('compareResult');
const sessionCells = document.querySelectorAll('.session-id-cell');

const locationExactEl = document.getElementById('locationExact');
const locationMapFrame = document.getElementById('locationMapFrame');

function updateComparison() {
    const val = compareInput.value.trim();
    let matches = 0;
    
    sessionCells.forEach(cell => {
        const row = cell.closest('tr');
        const sessionId = cell.textContent.trim();
        
        if (val !== '' && sessionId === val) {
            row.classList.add('table-success');
            cell.classList.add('fw-bold', 'text-dark');
            matches++;
        } else {
            row.classList.remove('table-success');
            cell.classList.remove('fw-bold', 'text-dark');
        }
    });
    
    if (val === '') {
        resultDiv.innerHTML = '';
    } else if (matches > 0) {
        resultDiv.innerHTML = `<span class="text-success"><i class="bi bi-check-lg"></i> Found ${matches} matching record(s)</span>`;
    } else {
        resultDiv.innerHTML = `<span class="text-danger"><i class="bi bi-x-lg"></i> No matches found</span>`;
    }
}

function clearCompare() {
    compareInput.value = '';
    updateComparison();
}

compareInput.addEventListener('input', updateComparison);

// Location modal
document.querySelectorAll('.location-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const lat = parseFloat(btn.getAttribute('data-lat'));
        const lng = parseFloat(btn.getAttribute('data-lng'));

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        if (locationExactEl) {
            locationExactEl.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }

        if (locationMapFrame) {
            const pad = 0.005;
            const left = lng - pad;
            const right = lng + pad;
            const bottom = lat - pad;
            const top = lat + pad;
            const marker = `marker=${encodeURIComponent(lat + ',' + lng)}`;
            const bbox = `bbox=${encodeURIComponent(left + ',' + bottom + ',' + right + ',' + top)}`;
            locationMapFrame.src = `https://www.openstreetmap.org/export/embed.html?${bbox}&layer=mapnik&${marker}`;
        }
    });
});
</script>
</body>
</html>
