<?php
include("../config/db.php");


$username = $_POST['username'];
$password = $_POST['password'];


$SQL = "SELECT * FROM users where username = '$username' AND password = '$password'";
$res = $conn->query($SQL);

if ($res->num_rows > 0) {
    $user = $res->fetch_assoc();
    echo json_encode([

        "status" => "success",
        "id" => $user["userID"],
        "fullName" => $user["fname"] . " " . $user["lname"],
        "phone" => $user["phoneNO"],
        "role" => $user["role"]
    ]);
} else {
    echo json_encode(["status" => "error"]);
}
