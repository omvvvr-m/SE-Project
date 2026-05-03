<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/audit.php";
// Enforce admin access only when this file is requested directly.
$isDirectRequest = isset($_SERVER["SCRIPT_FILENAME"]) && realpath((string)$_SERVER["SCRIPT_FILENAME"]) === __FILE__;
if ($isDirectRequest) {
    require_once __DIR__ . "/../includes/require_admin.php";
}

if ($isDirectRequest && isset($_GET["delete_id"])) {
    $userModel = new User($conn);
    audit_init($conn);
    $deletedUserId = (int)$_GET["delete_id"];
    audit_event($conn, "user.delete", [
        "userID" => $deletedUserId
    ]);
    $userModel->removeUser($deletedUserId);
    header("Location: ../users-management.php");
    exit();
}

if (
    $isDirectRequest &&
    isset($_POST["first_name"]) &&
    isset($_POST["last_name"]) &&
    isset($_POST["username"]) &&
    isset($_POST["phone_no"]) &&
    isset($_POST["password"]) &&
    isset($_POST["role"])
) {
    $userModel = new User($conn);
    audit_init($conn);
    $firstName = $_POST["first_name"];
    $lastName = $_POST["last_name"];
    $username = $_POST["username"];
    $phoneDigits = preg_replace('/\D/u', '', (string)$_POST["phone_no"]);
    if (!preg_match('/^01\d{9}$/', $phoneDigits)) {
        $_SESSION["user_mgmt_error"] = "رقم الهاتف لازم يكون 11 رقم بالظبط ويبدأ بـ 01 (مثل 01234567890).";
        header("Location: ../users-management.php");
        exit();
    }
    $phoneNo = $phoneDigits;
    $password = $_POST["password"];
    $role = $_POST["role"];

    if (isset($_POST["user_id"]) && $_POST["user_id"] !== "") {
        $editedUserId = (int)$_POST["user_id"];
        audit_event($conn, "user.update", [
            "userID" => $editedUserId,
            "username" => (string)$username,
            "role" => (string)$role
        ]);
        $userModel->updateUser($editedUserId, $username, $firstName, $lastName, $phoneNo, $password, $role);
    } else {
        $newUserID = $userModel->peekNextUserID();
        audit_event($conn, "user.create", [
            "userID" => $newUserID,
            "username" => (string)$username,
            "role" => (string)$role
        ]);
        $userModel->addUser($username, $firstName, $lastName, $phoneNo, $password, $role);
    }

    header("Location: ../users-management.php");
    exit();
}
$user = new User($conn);


class User
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
        $this->ensureGuestLifecycleColumns();
        $this->purgeExpiredGuests();
    }

    public function getAll()
    {
        $sql = "SELECT *,
                       CASE
                         WHEN role = 'guest' AND guest_expires_at IS NOT NULL
                         THEN TIMESTAMPDIFF(SECOND, NOW(), guest_expires_at)
                         ELSE NULL
                       END AS guest_remaining_seconds
                FROM users
                ORDER BY userID DESC";
        return $this->conn->query($sql);
    }

    public function addUser($username, $firstName, $lastName, $phoneNo, $password, $role)
    {
        $newUserID = $this->getNextUserID();
        if ($role === "guest") {
            $sql = "INSERT INTO users (userID, username, fname, lname, phoneNO, password, role, guest_created_at, guest_expires_at)
                    VALUES ('$newUserID', '$username', '$firstName', '$lastName', '$phoneNo', '$password', '$role', NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY))";
        } else {
            $sql = "INSERT INTO users (userID, username, fname, lname, phoneNO, password, role, guest_created_at, guest_expires_at)
                    VALUES ('$newUserID', '$username', '$firstName', '$lastName', '$phoneNo', '$password', '$role', NULL, NULL)";
        }
        return $this->conn->query($sql);
    }

    public function removeUser($userID)
    {
        $sql = "DELETE FROM users WHERE userID = '$userID'";
        return $this->conn->query($sql);
    }

    public function updateUser($userID, $username, $firstName, $lastName, $phoneNo, $password, $role)
    {
        $userID = (int)$userID;
        $oldRole = "";
        $roleRes = $this->conn->query("SELECT role FROM users WHERE userID = $userID LIMIT 1");
        if ($roleRes && $roleRow = $roleRes->fetch_assoc()) {
            $oldRole = (string)($roleRow["role"] ?? "");
        }

        $guestDatesSql = ", guest_created_at = NULL, guest_expires_at = NULL";
        if ($role === "guest") {
            if ($oldRole !== "guest") {
                $guestDatesSql = ", guest_created_at = NOW(), guest_expires_at = DATE_ADD(NOW(), INTERVAL 1 DAY)";
            } else {
                $guestDatesSql = ", guest_created_at = COALESCE(guest_created_at, NOW()),
                                  guest_expires_at = COALESCE(guest_expires_at, DATE_ADD(NOW(), INTERVAL 1 DAY))";
            }
        }

        $sql = "UPDATE users SET
                username = '$username',
                fname = '$firstName',
                lname = '$lastName',
                phoneNO = '$phoneNo',
                password = '$password',
                role = '$role'
                $guestDatesSql
                WHERE userID = $userID";
        return $this->conn->query($sql);
    }

    public function purgeExpiredGuests()
    {
        return $this->conn->query("DELETE FROM users
                                   WHERE role = 'guest'
                                     AND guest_expires_at IS NOT NULL
                                     AND guest_expires_at <= NOW()");
    }

    private function getNextUserID()
    {
        $sql = "SELECT MAX(userID) AS maxID FROM users";
        $result = $this->conn->query($sql);

        if ($result && $row = $result->fetch_assoc()) {
            return ((int)$row["maxID"]) + 1;
        }

        return 1;
    }
    public function peekNextUserID()
    {
        return $this->getNextUserID();
    }
    private function ensureGuestLifecycleColumns()
    {
        $hasCreated = $this->conn->query("SHOW COLUMNS FROM users LIKE 'guest_created_at'");
        if (!$hasCreated || $hasCreated->num_rows === 0) {
            $this->conn->query("ALTER TABLE users ADD COLUMN guest_created_at DATETIME NULL");
        }
        $hasExpires = $this->conn->query("SHOW COLUMNS FROM users LIKE 'guest_expires_at'");
        if (!$hasExpires || $hasExpires->num_rows === 0) {
            $this->conn->query("ALTER TABLE users ADD COLUMN guest_expires_at DATETIME NULL");
        }
    }
    public function getUserData()
    {
        return $this->conn->query("SELECT * FROM users WHERE userID = " . (int)$_SESSION['user_id']);
    }
    public function getUserGrant()
    {
        return $this->conn->query("SELECT * FROM grants WHERE 
        userID = " . (int) $_SESSION["user_id"]);
    }
    public function deduct($deductAmount, $grantID = null, $userID = null)
    {
        $userID = $userID !== null ? (int)$userID : (int)($_SESSION['user_id'] ?? 0);
        if ($userID <= 0) {
            return 1;
        }
        $amount = (float)$deductAmount;
        if ($amount <= 0) {
            return 0;
        }
        $safeAmount = number_format($amount, 2, ".", "");

        $targetGrantID = null;
        if ($grantID !== null && $grantID !== "") {
            $targetGrantID = (int)$grantID;
        } elseif (isset($_SESSION["grant_id"])) {
            $targetGrantID = (int)$_SESSION["grant_id"];
        }

        if ($this->checkBalance($targetGrantID, $userID) < $amount) {
            return 1;
        }

        if ($targetGrantID !== null && $targetGrantID > 0) {
            $sql = "UPDATE grants
                    SET balance = balance - $safeAmount
                    WHERE grantID = $targetGrantID
                      AND userID = $userID";
        } else {
            $sql = "UPDATE grants g
                    JOIN (
                        SELECT grantID FROM grants
                        WHERE userID = $userID
                        ORDER BY grantID ASC
                        LIMIT 1
                    ) pick ON pick.grantID = g.grantID
                    SET g.balance = g.balance - $safeAmount";
        }
        $ok = $this->conn->query($sql);
        if (!$ok || $this->conn->affected_rows <= 0) {
            return 1;
        }
        return 0;
    }
    public function checkBalance($grantID = null, $userID = null)
    {
        $userID = $userID !== null ? (int)$userID : (int)($_SESSION["user_id"] ?? 0);
        if ($userID <= 0) {
            return 0.0;
        }
        if ($grantID !== null && $grantID !== "") {
            $gid = (int)$grantID;
            $res = $this->conn->query("SELECT balance FROM grants
                                       WHERE grantID = $gid AND userID = $userID
                                       LIMIT 1");
        } else {
            $res = $this->conn->query("SELECT balance FROM grants
                                       WHERE userID = $userID
                                       ORDER BY grantID ASC
                                       LIMIT 1");
        }
        if (!$res || !($row = $res->fetch_assoc())) {
            return 0.0;
        }
        return (float)$row["balance"];
    }
}
