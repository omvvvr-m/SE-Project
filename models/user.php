<?php

require_once __DIR__ . "/../config/db.php";
$user = new User($conn);


class User
{
    private $conn;
    public function __construct($db)
    {
        $this->conn = $db;
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
        return $this->conn->query("SELECT balance FROM grants WHERE 
        userID = " . (int)$_SESSION["user_id"]);
    }
}
