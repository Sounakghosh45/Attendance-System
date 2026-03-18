<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database connection settings
$db_host = $_ENV['DB_HOST'];
$db_user = $_ENV['DB_USER']; 
$db_pass = $_ENV['DB_PASS'];     
$db_name = $_ENV['DB_NAME'];

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: ". $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");
?>