<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../config/db.php");
require_once __DIR__ . "/../includes/audit.php";
audit_init($conn);
header("Content-Type: application/json");

$sessionUserID = isset($_SESSION["vlms_user_id"]) ? (int)$_SESSION["vlms_user_id"] : 0;
$userID = $sessionUserID > 0 ? $sessionUserID : (isset($_GET["user_id"]) ? (int)$_GET["user_id"] : 0);

if ($userID <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid user ID."
    ]);
    exit();
}

$sql = "SELECT userID, fname, lname, phoneNO, role FROM users WHERE userID = '$userID' LIMIT 1";
$res = $conn->query($sql);

if (!$res) {
    echo json_encode([
        "status" => "error",
        "message" => $conn->error
    ]);
    exit();
}

if ($res->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "User not found."
    ]);
    exit();
}

$user = $res->fetch_assoc();

echo json_encode([
    "status" => "success",
    "user" => [
        "id" => $user["userID"],
        "fullName" => trim(($user["fname"] ?? "") . " " . ($user["lname"] ?? "")),
        "phone" => $user["phoneNO"] ?? "",
        "role" => $user["role"] ?? ""
    ]
]);
