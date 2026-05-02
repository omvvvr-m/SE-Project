<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";
$userModel = new User($conn);

if (isset($_GET["delete_id"])) {
    $userModel->removeUser($_GET["delete_id"]);
    header("Location: ../users-management.php");
    exit();
}

if (
    isset($_POST["first_name"]) &&
    isset($_POST["last_name"]) &&
    isset($_POST["username"]) &&
    isset($_POST["phone_no"]) &&
    isset($_POST["password"]) &&
    isset($_POST["role"])
) {
    $firstName = $_POST["first_name"];
    $lastName = $_POST["last_name"];
    $username = $_POST["username"];
    $phoneDigits = preg_replace('/\D/u', '', (string)$_POST["phone_no"]);
    if (!preg_match('/^01\d{9}$/', $phoneDigits)) {
        $_SESSION["user_mgmt_error"] = "رقم الهاتف لازم يكون 11 رقم بالظبط ويبدأ بـ 01 (مثل 01234567890).";
        header("Location: users-management.php");
        exit();
    }
    $phoneNo = $phoneDigits;
    $password = $_POST["password"];
    $role = $_POST["role"];

    if (isset($_POST["user_id"]) && $_POST["user_id"] !== "") {
        $userModel->updateUser($_POST["user_id"], $username, $firstName, $lastName, $phoneNo, $password, $role);
    } else {
        $userModel->addUser($username, $firstName, $lastName, $phoneNo, $password, $role);
    }

    header("Location: users-management.php");
    exit();
}
$user = new User($conn);


class User
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAll()
    {
        $sql = "SELECT * FROM users ORDER BY userID DESC";
        return $this->conn->query($sql);
    }

    public function addUser($username, $firstName, $lastName, $phoneNo, $password, $role)
    {
        $newUserID = $this->getNextUserID();
        $sql = "INSERT INTO users (userID, username, fname, lname, phoneNO, password, role)
                VALUES ('$newUserID', '$username', '$firstName', '$lastName', '$phoneNo', '$password', '$role')";
        return $this->conn->query($sql);
    }

    public function removeUser($userID)
    {
        $sql = "DELETE FROM users WHERE userID = '$userID'";
        return $this->conn->query($sql);
    }

    public function updateUser($userID, $username, $firstName, $lastName, $phoneNo, $password, $role)
    {
        $sql = "UPDATE users SET
                username = '$username',
                fname = '$firstName',
                lname = '$lastName',
                phoneNO = '$phoneNo',
                password = '$password',
                role = '$role'
                WHERE userID = '$userID'";
        return $this->conn->query($sql);
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
    public function getUserData()
    {
        return $this->conn->query("SELECT * FROM users WHERE userID = " . (int)$_SESSION['user_id']);
    }
    public function getUserGrant()
    {
        return $this->conn->query("SELECT * FROM grants WHERE 
        userID = " . (int) $_SESSION["user_id"]);
    }
    public function deduct($deductAmount)
    {
        if ($this->checkBalance() >= $deductAmount) {
            $this->conn->query(
                "
                UPDATE grants
                SET balance = balance - " . (int)$deductAmount . "
                WHERE userID = " . (int)$_SESSION['user_id']
            );
            return 0;
        } else {
            return 1;
        }
    }
    public function checkBalance()
    {
        return (float)(($this->conn->query("SELECT balance FROM grants WHERE 
        userID = " . (int)$_SESSION["user_id"]))->fetch_assoc()["balance"]);
    }
}
