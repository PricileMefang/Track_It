<?php
$host     = "localhost";
$user     = "root";
$password = "";
$database = "trackit_db";
$port = 3307;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($host, $user, $password, $database, $port);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("A database error occurred. Please try again later.");
}

$conn->set_charset("utf8mb4");