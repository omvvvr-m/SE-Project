<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../config/db.php");
require_once __DIR__ . "/../includes/audit.php";
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
    audit_init($conn);
    audit_event($conn, "auth.login_success", [
        "userID" => (int)$user["userID"],
        "role" => (string)($user["role"] ?? ""),
        "username" => (string)($user["username"] ?? "")
    ]);
    $uid = (int)$_SESSION["user_id"];
    $grantSql = "SELECT grantID FROM grants
                 WHERE userID = $uid AND status = 'active'
                 ORDER BY grantID ASC
                 LIMIT 1";
    $grantRes = $conn->query($grantSql);
    if ($grantRes && ($grantRow = $grantRes->fetch_assoc())) {
        $gid = $grantRow["grantID"] ?? $grantRow["grantid"] ?? null;
        if ($gid !== null) {
            $_SESSION["grant_id"] = (int)$gid;
        }
    } else {
        unset($_SESSION["grant_id"]);
    }
    echo json_encode([

        "status" => "success",
        "id" => $user["userID"],
        "fullName" => $user["fname"] . " " . $user["lname"],
        "phone" => $user["phoneNO"],
        "role" => $user["role"]
    ]);
} else {
    audit_init($conn);
    audit_event($conn, "auth.login_failed", [
        "username" => (string)$username
    ]);
    echo json_encode(["status" => "error"]);
}
