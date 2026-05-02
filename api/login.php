<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../config/db.php");
header("Content-Type: application/json");


$username = $_POST['username'];
$password = $_POST['password'];


$SQL = "SELECT * FROM users where username = '$username' AND password = '$password'";
$res = $conn->query($SQL);


if ($res->num_rows > 0) {
    $user = $res->fetch_assoc();
    $_SESSION["vlms_user_id"] = (int)$user["userID"];
    $_SESSION["vlms_role"] = $user["role"];
    $_SESSION["user_id"] = $user["userID"];
    $grantGrapperSql = "SELECT * FROM grants where userID = " . $_SESSION["user_id"];
    $grantRow = $conn->query($grantGrapperSql)->fetch_assoc();
    if ($grantRow && isset($grantRow['grantid'])) {
        $_SESSION['grant_id'] = $grantRow['grantid'];
    }
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
