<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$message = "";

// Handle employee addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_employee'])) {
    $name = $_POST['employee_name'];
    $place = $_POST['assigned_place'];
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO employees (name, assigned_place) VALUES (?, ?)");
        $stmt->execute([$name, $place]);
        $message = "<div class='alert alert-success'>Employee added successfully!</div>";
    }
}

// Handle employee update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_employee'])) {
    $id = $_POST['employee_id'];
    $name = $_POST['employee_name'];
    $place = $_POST['assigned_place'];
    if (!empty($name)) {
        $stmt = $pdo->prepare("UPDATE employees SET name = ?, assigned_place = ? WHERE id = ?");
        $stmt->execute([$name, $place, $id]);
        $message = "<div class='alert alert-success'>Employee updated successfully!</div>";
    }
}

// Handle employee deletion
if (isset($_GET['delete_emp'])) {
    $id = $_GET['delete_emp'];
    try {
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: manage_employees.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Cannot delete employee. They might have profit records.</div>";
    }
}

// Search, Filter and Sort
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Allowed sort columns and orders to prevent SQL injection
$allowed_sorts = ['id', 'name', 'assigned_place'];
$allowed_orders = ['ASC', 'DESC'];

if (!in_array($sort, $allowed_sorts)) $sort = 'id';
if (!in_array($order, $allowed_orders)) $order = 'ASC';

$query = "SELECT * FROM employees";
$params = [];

if (!empty($search)) {
    $query .= " WHERE id LIKE ? OR name LIKE ? OR assigned_place LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

$query .= " ORDER BY $sort $order";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Toggle order for the next click
$next_order = ($order == 'ASC') ? 'DESC' : 'ASC';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employees - BBMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .sort-link { text-decoration: none; color: white; }
        .sort-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">BBMS Admin</a>
        <div class="navbar-nav">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link active" href="manage_employees.php">Employees</a>
            <a class="nav-link text-danger" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Manage Employees</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
            <i class="bi bi-plus-lg"></i> Add Employee
        </button>
    </div>

    <?php echo $message; ?>

    <!-- Search Box -->
    <div class="card p-3 mb-4">
        <form method="GET" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="search" class="form-control" placeholder="Search by ID, Name, or Place..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-secondary">Search</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>
                        <a href="?search=<?php echo urlencode($search); ?>&sort=id&order=<?php echo ($sort == 'id') ? $next_order : 'ASC'; ?>" class="sort-link">
                            ID <?php echo ($sort == 'id') ? ($order == 'ASC' ? '↑' : '↓') : ''; ?>
                        </a>
                    </th>
                    <th>
                        <a href="?search=<?php echo urlencode($search); ?>&sort=name&order=<?php echo ($sort == 'name') ? $next_order : 'ASC'; ?>" class="sort-link">
                            Name <?php echo ($sort == 'name') ? ($order == 'ASC' ? '↑' : '↓') : ''; ?>
                        </a>
                    </th>
                    <th>
                        <a href="?search=<?php echo urlencode($search); ?>&sort=assigned_place&order=<?php echo ($sort == 'assigned_place') ? $next_order : 'ASC'; ?>" class="sort-link">
                            Assigned Place <?php echo ($sort == 'assigned_place') ? ($order == 'ASC' ? '↑' : '↓') : ''; ?>
                        </a>
                    </th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($employees) > 0): ?>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td><?php echo $emp['id']; ?></td>
                        <td><?php echo htmlspecialchars($emp['name']); ?></td>
                        <td><?php echo htmlspecialchars($emp['assigned_place'] ?? 'Not Assigned'); ?></td>
                        <td>
                            <button class="btn btn-warning btn-sm edit-btn" 
                                    data-id="<?php echo $emp['id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($emp['name']); ?>" 
                                    data-place="<?php echo htmlspecialchars($emp['assigned_place'] ?? ''); ?>"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editEmployeeModal">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <a href="?delete_emp=<?php echo $emp['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">No employees found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Employee Name</label>
                        <input type="text" name="employee_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assigned Place</label>
                        <input type="text" name="assigned_place" class="form-control" placeholder="e.g. Branch A, Warehouse, etc.">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_employee" class="btn btn-primary">Add Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Employee Name</label>
                        <input type="text" name="employee_name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assigned Place</label>
                        <input type="text" name="assigned_place" id="edit_place" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_employee" class="btn btn-warning">Update Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.edit-btn').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('edit_name').value = this.dataset.name;
        document.getElementById('edit_place').value = this.dataset.place;
    });
});
</script>
</body>
</html>
