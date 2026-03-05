<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload) || !isset($payload['changes']) || !is_array($payload['changes'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$changes = $payload['changes'];

try {
    $pdo->beginTransaction();

    $insertStmt = $pdo->prepare("INSERT INTO claims (employee_id, claim_date) VALUES (?, ?)");
    $deleteStmt = $pdo->prepare("DELETE FROM claims WHERE employee_id = ? AND claim_date = ?");

    foreach ($changes as $ch) {
        $empId = $ch['employee_id'] ?? null;
        $date = $ch['date'] ?? null;
        $claimed = $ch['claimed'] ?? null;

        if (!is_numeric($empId) || !is_string($date) || ($claimed !== true && $claimed !== false)) {
            continue;
        }

        if ($claimed) {
            try {
                $insertStmt->execute([(int)$empId, $date]);
            } catch (PDOException $e) {
                // Ignore duplicate inserts due to UNIQUE constraint
                if ((int)($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e;
                }
            }
        } else {
            $deleteStmt->execute([(int)$empId, $date]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error']);
}
