<?php
require_once 'db.php';

try {
    // Clear existing data to avoid duplicates (optional, based on preference)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE profits;");
    $pdo->exec("TRUNCATE TABLE employees;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Sample Employees
    $employees = [
        ['name' => 'John Doe', 'place' => 'Manila Branch'],
        ['name' => 'Jane Smith', 'place' => 'Cebu Branch'],
        ['name' => 'Michael Brown', 'place' => 'Davao Branch'],
        ['name' => 'Emily Davis', 'place' => 'Quezon City'],
        ['name' => 'Chris Wilson', 'place' => 'Makati Office'],
        ['name' => 'Sarah Miller', 'place' => 'Taguig Office'],
        ['name' => 'David Taylor', 'place' => 'Pasig Warehouse'],
        ['name' => 'Jessica Moore', 'place' => 'Ortigas Hub'],
        ['name' => 'Kevin Anderson', 'place' => 'Alabang Branch'],
        ['name' => 'Laura Thomas', 'place' => 'Baguio Office'],
        ['name' => 'Robert Garcia', 'place' => 'Batangas Hub'],
        ['name' => 'Maria Santos', 'place' => 'Laguna Branch'],
        ['name' => 'William Lopez', 'place' => 'Cavite Office'],
        ['name' => 'Linda Perez', 'place' => 'Pampanga Warehouse'],
        ['name' => 'James Cruz', 'place' => 'Bulacan Office']
    ];

    foreach ($employees as $emp) {
        $stmt = $pdo->prepare("INSERT INTO employees (name, assigned_place) VALUES (?, ?)");
        $stmt->execute([$emp['name'], $emp['place']]);
    }

    // Get all employee IDs
    $employeeIds = $pdo->query("SELECT id FROM employees")->fetchAll(PDO::FETCH_COLUMN);

    // Generate Profits for the entire current month for each employee
    $daysInMonth = date('t');
    $currentYear = date('Y');
    $currentMonth = date('m');

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dateStr = sprintf("%s-%s-%02d", $currentYear, $currentMonth, $day);
        
        foreach ($employeeIds as $id) {
            // Random chance (70%) that an employee has a profit entry for a given day
            if (rand(1, 10) > 3) {
                $amount = rand(500, 5000) + (rand(0, 99) / 100);
                $status = 'Regular';
                
                // Randomly assign different statuses
                $statusRoll = rand(1, 20);
                if ($statusRoll == 1) $status = 'Training';
                elseif ($statusRoll == 2) $status = 'Leave';
                elseif ($statusRoll == 3) $status = 'Sick';
                elseif ($statusRoll == 4) $status = 'Others';

                $otherText = ($status === 'Others') ? 'Half Day' : null;
                
                $stmt = $pdo->prepare("INSERT INTO profits (employee_id, amount, status, other_status_text, profit_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id, $amount, $status, $otherText, $dateStr]);
            }
        }
    }

    echo count($employees) . " sample employees and a full month of profit records have been created successfully!";
} catch (PDOException $e) {
    echo "Error seeding data: " . $e->getMessage();
}
?>
