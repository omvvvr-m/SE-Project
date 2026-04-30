<?php
include("../config/db.php");
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
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => $conn->error
    ]);
}
