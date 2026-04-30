<?php
$conn = new mysqli("localhost", "root", "", "virtual_lab");

if ($conn->connect_error) {
    die("DB failed: " . $conn->connect_error);
}
