<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "smart_pos";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

?>