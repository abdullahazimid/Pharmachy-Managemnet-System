<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "pharmacy_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");
