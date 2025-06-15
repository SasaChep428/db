<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '8889');
define('DB_NAME', 'network_installation');
define('DB_USER', 'root');
define('DB_PASS', 'root');

// Establish database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Session configuration
session_start();
?>