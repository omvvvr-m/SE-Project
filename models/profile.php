<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";
$profileModel = new Profile($conn);

if (isset($_POST["profile_action"])) {
    $sessionUserID = isset($_SESSION["vlms_user_id"]) ? (int)$_SESSION["vlms_user_id"] : 0;
    $sessionRole = isset($_SESSION["vlms_role"]) ? trim((string)$_SESSION["vlms_role"]) : "";
    $userID = $sessionUserID > 0 ? $sessionUserID : (isset($_POST["user_id"]) ? (int)$_POST["user_id"] : 0);
    $from = isset($_POST["from"]) ? $_POST["from"] : "user";

    if ($userID <= 0) {
        header("Location: profile.php?error=" . urlencode("Invalid user ID.") . "&from=" . urlencode($from));
        exit();
    }

    if ($_POST["profile_action"] === "update_info") {
        $firstName = trim($_POST["first_name"] ?? "");
        $lastName = trim($_POST["last_name"] ?? "");
        $phoneNo = trim($_POST["phone_no"] ?? "");
        $existingUser = $profileModel->getUserById($userID);
        if (!$existingUser) {
            header("Location: profile.php?user_id=$userID&from=" . urlencode($from) . "&error=" . urlencode("User not found."));
            exit();
        }

        $currentRole = trim((string)($existingUser["role"] ?? ""));
        $requestedRole = trim($_POST["role"] ?? $currentRole);

        // Researchers are not allowed to change role/permissions.
        $role = ($sessionRole === "researcher") ? $currentRole : $requestedRole;

        if ($firstName === "" || $lastName === "" || $phoneNo === "" || $role === "") {
            header("Location: profile.php?user_id=$userID&from=" . urlencode($from) . "&error=" . urlencode("All profile fields are required."));
            exit();
        }

        $profileModel->updateProfileInfo($userID, $firstName, $lastName, $phoneNo, $role);
        header("Location: profile.php?user_id=$userID&from=" . urlencode($from) . "&success=" . urlencode("Profile updated successfully."));
        exit();
    }

    if ($_POST["profile_action"] === "update_password") {
        $currentPassword = $_POST["current_password"] ?? "";
        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        if ($newPassword === "" || $confirmPassword === "" || $currentPassword === "") {
            header("Location: profile.php?user_id=$userID&from=" . urlencode($from) . "&error=" . urlencode("All password fields are required."));
            exit();
        }

        if ($newPassword !== $confirmPassword) {
            header("Location: profile.php?user_id=$userID&from=" . urlencode($from) . "&error=" . urlencode("New password and confirmation do not match."));
            exit();
        }

        if (!$profileModel->checkCurrentPassword($userID, $currentPassword)) {
            header("Location: profile.php?user_id=$userID&from=" . urlencode($from) . "&error=" . urlencode("Current password is incorrect."));
            exit();
        }

        $profileModel->updatePassword($userID, $newPassword);
        header("Location: profile.php?user_id=$userID&from=" . urlencode($from) . "&success=" . urlencode("Password changed successfully."));
        exit();
    }
}

class Profile
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getUserById($userID)
    {
        $sql = "SELECT * FROM users WHERE userID = '$userID' LIMIT 1";
        $result = $this->conn->query($sql);
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }

    public function getFirstUser()
    {
        $sql = "SELECT * FROM users ORDER BY userID ASC LIMIT 1";
        $result = $this->conn->query($sql);
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }

    public function updateProfileInfo($userID, $firstName, $lastName, $phoneNo, $role)
    {
        $sql = "UPDATE users SET
                fname = '$firstName',
                lname = '$lastName',
                phoneNO = '$phoneNo',
                role = '$role'
                WHERE userID = '$userID'";
        return $this->conn->query($sql);
    }

    public function checkCurrentPassword($userID, $currentPassword)
    {
        $sql = "SELECT userID FROM users WHERE userID = '$userID' AND password = '$currentPassword' LIMIT 1";
        $result = $this->conn->query($sql);
        return $result && $result->num_rows > 0;
    }

    public function updatePassword($userID, $newPassword)
    {
        $sql = "UPDATE users SET password = '$newPassword' WHERE userID = '$userID'";
        return $this->conn->query($sql);
    }
}
