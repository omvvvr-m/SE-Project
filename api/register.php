<?php
include("../config/db.php");
require_once __DIR__ . "/../includes/audit.php";
header("Content-Type: application/json");
$firstName = $_POST['firstName'];
$lastName = $_POST['lastName'];
$username = $_POST['username'];
$password = $_POST['password'];
$phoneNumber = $_POST['phoneNumber'];

$SQL = "INSERT INTO users(fname,lname, username, password, phoneNO, role) 
values('$firstName','$lastName', '$username', '$password', '$phoneNumber', 'researcher')";
$res = $conn->query($SQL);

if ($res) {
    audit_init($conn);
    audit_event($conn, "auth.register", [
        "username" => (string)$username,
        "firstName" => (string)$firstName,
        "lastName" => (string)$lastName
    ]);
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => $conn->error
    ]);
}
