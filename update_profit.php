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
    $status = $_POST['status'] ?? 'Regular';
    $other_status_text = $_POST['other_status_text'] ?? null;

    $status = is_string($status) ? trim($status) : 'Regular';
    if ($status === '' || strtolower($status) === 'none') {
        $status = 'Regular';
    }
    $other_status_text = is_string($other_status_text) ? trim($other_status_text) : null;
    if ($status !== 'Others') {
        $other_status_text = null;
    }

    if ($employee_id && $date) {
        try {
            // Check if record exists for this employee on this date
            // Using DATE() on profit_date (new schema)
            $stmt = $pdo->prepare("SELECT id FROM profits WHERE employee_id = ? AND DATE(profit_date) = ? LIMIT 1");
            $stmt->execute([$employee_id, $date]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            $normalizedAmount = is_numeric($amount) ? (float)$amount : 0.0;
            $isBlank = ($normalizedAmount == 0.0 && $status === 'Regular' && ($other_status_text === null || $other_status_text === ''));

            if ($existing) {
                if ($isBlank) {
                    $stmt = $pdo->prepare("DELETE FROM profits WHERE id = ?");
                    $stmt->execute([$existing['id']]);
                } else {
                    // Update existing record
                    $stmt = $pdo->prepare("UPDATE profits SET amount = ?, status = ?, other_status_text = ? WHERE id = ?");
                    $stmt->execute([$normalizedAmount, $status, $other_status_text, $existing['id']]);
                }
            } else {
                if (!$isBlank) {
                    // Insert new record
                    // Set time to midday to avoid timezone edge cases
                    $dateTime = $date . ' 12:00:00';
                    $stmt = $pdo->prepare("INSERT INTO profits (employee_id, amount, profit_date, status, other_status_text) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$employee_id, $normalizedAmount, $dateTime, $status, $other_status_text]);
                }
            }

            error_log("BBMS Update: Saved amount $normalizedAmount / status $status for emp $employee_id on $date");
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
