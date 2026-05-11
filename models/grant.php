<?php

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/audit.php";
$grantModel = new Grant($conn);

if (isset($_GET["delete_id"])) {
    audit_init($conn);
    audit_event($conn, "grant.delete", ["grantID" => (int)$_GET["delete_id"]]);
    $grantModel->removeGrant($_GET["delete_id"]);
    header("Location: ../grants-management.php");
    exit();
}

if (
    isset($_POST["user_id"]) &&
    isset($_POST["balance"]) &&
    isset($_POST["expiry_date"]) &&
    isset($_POST["grant_name"])
) {
    $userID = (int)$_POST["user_id"];
    $balance = (float)$_POST["balance"];
    $expiryDate = trim($_POST["expiry_date"]);
    $grantName = trim($_POST["grant_name"]);

    if ($userID <= 0) {
        header("Location: grants-management.php?error=" . urlencode("User ID must be a valid number."));
        exit();
    }

    if (!$grantModel->isValidDate($expiryDate)) {
        header("Location: grants-management.php?error=" . urlencode("Expiry date format is invalid."));
        exit();
    }

    if (!$grantModel->isExpiryOnOrAfterToday($expiryDate)) {
        header("Location: grants-management.php?error=" . urlencode("Expiry date cannot be in the past. Choose today or a future date."));
        exit();
    }

    if (!$grantModel->userExists($userID)) {
        header("Location: grants-management.php?error=" . urlencode("User ID does not exist in users table."));
        exit();
    }

    try {
        if (isset($_POST["grant_id"]) && $_POST["grant_id"] !== "") {
            audit_init($conn);
            audit_event($conn, "grant.update", [
                "grantID" => (int)$_POST["grant_id"],
                "userID" => (int)$userID,
                "balance" => (float)$balance,
                "expiryDate" => (string)$expiryDate
            ]);
            $grantModel->updateGrant((int)$_POST["grant_id"], $userID, $balance, $expiryDate, $grantName);
        } else {
            audit_init($conn);
            audit_event($conn, "grant.create", [
                "userID" => (int)$userID,
                "balance" => (float)$balance,
                "expiryDate" => (string)$expiryDate
            ]);
            $grantModel->addGrant($userID, $balance, $expiryDate, $grantName);
        }
    } catch (mysqli_sql_exception $e) {
        header("Location: grants-management.php?error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }

    header("Location: grants-management.php?success=1");
    exit();
}

class Grant
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAll()
    {
        $sql = "SELECT
                grantID AS grantID,
                userID AS userID,
                balance AS balance,
                expiryDate AS expiryDate,
                name AS name,
                status AS status
                FROM grants
                ORDER BY grantID DESC";
        return $this->conn->query($sql);
    }

    public function addGrant($userID, $balance, $expiryDate, $grantName)
    {
        $newGrantID = $this->getNextGrantID();
        $sql = "INSERT INTO grants (grantID, userID, balance, expiryDate, name, status)
                VALUES ('$newGrantID', '$userID', '$balance', '$expiryDate', '$grantName', 'active')";
        return $this->conn->query($sql);
    }

    public function removeGrant($grantID)
    {
        $sql = "DELETE FROM grants WHERE grantID = '$grantID'";
        return $this->conn->query($sql);
    }

    public function updateGrant($grantID, $userID, $balance, $expiryDate, $grantName)
    {
        $sql = "UPDATE grants SET
                userID = '$userID',
                balance = '$balance',
                expiryDate = '$expiryDate',
                name = '$grantName',
                status = IF(expiryDate < CURDATE(), 'expired', 'active')
                WHERE grantID = '$grantID'";
        return $this->conn->query($sql);
    }

    public function userExists($userID)
    {
        $sql = "SELECT userID FROM users WHERE userID = '$userID' LIMIT 1";
        $result = $this->conn->query($sql);
        return $result && $result->num_rows > 0;
    }

    public function isValidDate($date)
    {
        $check = DateTime::createFromFormat("Y-m-d", $date);
        return $check && $check->format("Y-m-d") === $date;
    }

    /** Expiry must be today or later (server local date). */
    public function isExpiryOnOrAfterToday($date)
    {
        if (!$this->isValidDate($date)) {
            return false;
        }
        $today = new DateTimeImmutable("today");
        $expiry = DateTimeImmutable::createFromFormat("Y-m-d", $date);
        return $expiry instanceof DateTimeImmutable && $expiry >= $today;
    }

    private function getNextGrantID()
    {
        $sql = "SELECT MAX(grantID) AS maxID FROM grants";
        $result = $this->conn->query($sql);

        if ($result && $row = $result->fetch_assoc()) {
            return ((int)$row["maxID"]) + 1;
        }

        return 1;
    }
}
