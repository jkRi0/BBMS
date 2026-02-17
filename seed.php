<?php
require_once 'db.php';

try {
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
        ['name' => 'Laura Thomas', 'place' => 'Bagui Office']
    ];

    foreach ($employees as $emp) {
        $stmt = $pdo->prepare("INSERT INTO employees (name, assigned_place) VALUES (?, ?)");
        $stmt->execute([$emp['name'], $emp['place']]);
    }

    // Get all employee IDs
    $employeeIds = $pdo->query("SELECT id FROM employees")->fetchAll(PDO::FETCH_COLUMN);

    // Sample Profits
    foreach ($employeeIds as $id) {
        $amount = rand(100, 1000) + (rand(0, 99) / 100);
        $training = rand(0, 1);
        $stmt = $pdo->prepare("INSERT INTO profits (employee_id, profit_amount, is_training) VALUES (?, ?, ?)");
        $stmt->execute([$id, $amount, $training]);
    }

    echo "10 sample employees and their initial profit records have been created successfully!";
} catch (PDOException $e) {
    echo "Error seeding data: " . $e->getMessage();
}
?>
