<?php
// Bootstrap file for PHPUnit tests

// Define test environment
define('TESTING', true);

// Include configuration
require_once __DIR__ . '/../private/config.php';

// Include classes
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/FormHandler.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/Auth.php';

// Set up test database connection (you might want to use a separate test database)
global $pdo;
// For testing, you might want to use a test database
// $pdo = new PDO("mysql:host=localhost;dbname=student_db_test;charset=utf8", "root", "");

// Clean up any test data or setup test fixtures here
?>
