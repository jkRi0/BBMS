<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'bbms_db';

// Create connection
$conn = new mysqli($host, $user, $pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $db";
if ($conn->query($sql) === TRUE) {
    $conn->select_db($db);
} else {
    die("Error creating database: " . $conn->error);
}

// Create employees table
$sql = "CREATE TABLE IF NOT EXISTS employees (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    assigned_place VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Check if assigned_place column exists, if not add it (for existing databases)
$checkColumn = $conn->query("SHOW COLUMNS FROM employees LIKE 'assigned_place'");
if ($checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE employees ADD COLUMN assigned_place VARCHAR(255) DEFAULT NULL AFTER name");
}

// Create profits table
$sql = "CREATE TABLE IF NOT EXISTS profits (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    employee_id INT(11),
    profit_amount DECIMAL(10,2) NOT NULL,
    is_training TINYINT(1) DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id)
)";
$conn->query($sql);

// Create admin table (for simple login)
$sql = "CREATE TABLE IF NOT EXISTS admin (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)";
$conn->query($sql);

// Check if admin exists, if not create one (default: admin/admin123)
$checkAdmin = $conn->query("SELECT id FROM admin WHERE username = 'admin'");
if ($checkAdmin->num_rows == 0) {
    $hashedPass = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO admin (username, password) VALUES ('admin', '$hashedPass')");
}

// Global connection variable for other files
$pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>
