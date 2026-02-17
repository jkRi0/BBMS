<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'] ?? null;
    $date = $_POST['date'] ?? null;
    $amount = $_POST['amount'] ?? 0;

    if ($employee_id && $date) {
        try {
            // Check if record exists for this employee on this date
            // Using DATE() on profit_date (new schema)
            $stmt = $pdo->prepare("SELECT id FROM profits WHERE employee_id = ? AND DATE(profit_date) = ? LIMIT 1");
            $stmt->execute([$employee_id, $date]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing record using amount column
                $stmt = $pdo->prepare("UPDATE profits SET amount = ? WHERE id = ?");
                $stmt->execute([$amount, $existing['id']]);
            } else {
                // Insert new record
                // Set time to midday to avoid timezone edge cases
                $dateTime = $date . ' 12:00:00';
                $stmt = $pdo->prepare("INSERT INTO profits (employee_id, amount, profit_date, status) VALUES (?, ?, ?, 'Regular')");
                $stmt->execute([$employee_id, $amount, $dateTime]);
            }

            error_log("BBMS Update: Saved amount $amount for emp $employee_id on $date");
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log("BBMS Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
