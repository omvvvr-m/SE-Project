<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../config/db.php");
require_once __DIR__ . "/../includes/audit.php";
audit_init($conn);
header("Content-Type: application/json");

$sessionUserID = isset($_SESSION["vlms_user_id"]) ? (int)$_SESSION["vlms_user_id"] : 0;
$requestedUserID = isset($_GET["user_id"]) ? (int)$_GET["user_id"] : 0;
$userID = $requestedUserID > 0 ? $requestedUserID : $sessionUserID;

if ($userID <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid user ID."
    ]);
    exit();
}

$sql = "SELECT grantID, userID, balance, expiryDate, name, status
        FROM grants
        WHERE userID = ?
        ORDER BY expiryDate ASC, grantID DESC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to prepare grants query."
    ]);
    exit();
}

$stmt->bind_param("i", $userID);
$stmt->execute();
$res = $stmt->get_result();

if (!$res) {
    echo json_encode([
        "status" => "error",
        "message" => $conn->error
    ]);
    exit();
}

$grants = [];
while ($row = $res->fetch_assoc()) {
    $grants[] = [
        "grantID" => $row["grantID"] ?? ($row["grantId"] ?? ""),
        "userID" => $row["userID"] ?? $userID,
        "name" => $row["name"] ?? "",
        "balance" => $row["balance"] ?? 0,
        "expiryDate" => $row["expiryDate"] ?? "",
        "status" => $row["status"] ?? "active"
    ];
}

$stmt->close();

echo json_encode([
    "status" => "success",
    "message" => count($grants) ? "Grants loaded successfully." : "No grants found for this user.",
    "grants" => $grants
]);
