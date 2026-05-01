<?php

require_once __DIR__ . "/../config/db.php";
$reservationModel = new Reservation($conn);

if (isset($_GET["delete_id"])) {
    $reservationModel->removeReservation($_GET["delete_id"]);
    header("Location: ../reservations-management.php");
    exit();
}

if (
    isset($_POST["user_id"]) &&
    isset($_POST["start_time"]) &&
    isset($_POST["end_time"]) &&
    isset($_POST["equipment_id"]) &&
    isset($_POST["status"])
) {
    $userID = $_POST["user_id"];
    $startTime = $_POST["start_time"];
    $endTime = $_POST["end_time"];
    $equipmentID = $_POST["equipment_id"];
    $status = $_POST["status"];

    if (isset($_POST["booking_id"]) && $_POST["booking_id"] !== "") {
        $reservationModel->updateReservation($_POST["booking_id"], $userID, $startTime, $endTime, $equipmentID, $status);
    } else {
        $reservationModel->addReservation($userID, $startTime, $endTime, $equipmentID, $status);
    }

    header("Location: reservations-management.php");
    exit();
}

class Reservation
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
        $this->ensureTableExists();
    }

    public function getAll()
    {
        $this->ensureTableExists();
        $sql = "SELECT * FROM reservations ORDER BY bookingID DESC";
        return $this->conn->query($sql);
    }

    public function addReservation($userID, $startTime, $endTime, $equipmentID, $status)
    {
        $this->ensureTableExists();
        $newBookingID = $this->getNextBookingID();
        $sql = "INSERT INTO reservations (bookingID, userID, startTime, endTime, equipmentID, status)
                VALUES ('$newBookingID', '$userID', '$startTime', '$endTime', '$equipmentID', '$status')";
        return $this->conn->query($sql);
    }

    public function removeReservation($bookingID)
    {
        $this->ensureTableExists();
        $sql = "DELETE FROM reservations WHERE bookingID = '$bookingID'";
        return $this->conn->query($sql);
    }

    public function updateReservation($bookingID, $userID, $startTime, $endTime, $equipmentID, $status)
    {
        $this->ensureTableExists();
        $sql = "UPDATE reservations SET
                userID = '$userID',
                startTime = '$startTime',
                endTime = '$endTime',
                equipmentID = '$equipmentID',
                status = '$status'
                WHERE bookingID = '$bookingID'";
        return $this->conn->query($sql);
    }

    private function getNextBookingID()
    {
        $this->ensureTableExists();
        $sql = "SELECT MAX(bookingID) AS maxID FROM reservations";
        $result = $this->conn->query($sql);

        if ($result && $row = $result->fetch_assoc()) {
            return ((int)$row["maxID"]) + 1;
        }

        return 1;
    }

    private function ensureTableExists()
    {
        $sql = "CREATE TABLE IF NOT EXISTS reservations (
                bookingID int(11) NOT NULL,
                userID int(11) NOT NULL,
                startTime datetime NOT NULL,
                endTime datetime NOT NULL,
                equipmentID int(11) NOT NULL,
                status enum('ready','ongoing','terminated') NOT NULL DEFAULT 'ready',
                PRIMARY KEY (bookingID)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $this->conn->query($sql);
    }
require_once __DIR__ . "/../models/user.php";

$reservation = new Reservation($conn);
$user = new User($conn);

if (
    isset($_POST['eqID']) &&
    isset($_POST['resDate']) &&
    isset($_POST['startTime']) &&
    isset($_POST['endTime'])
) {
    $userID = $_SESSION['user_id'];
    $eqID =  $_POST['eqID'];
    $resDate =  $_POST['resDate'];
    $startTime =  $_POST['startTime'];
    $endTime =  $_POST['endTime'];

    $reservation->createBooking();
    header("Location: " . dirname($_SERVER['PHP_SELF']) . "/equipments-management.php");
    exit();
}


class Reservation
{
    private $conn;
    public function __construct($db)
    {
        $this->conn = $db;
    }
    public function createBooking($userID, $eqID, $grantID, $resDate, $startTime, $endTime)
    {

        $sql = "INSERT INTO reservation (userID, eqID,resDate, grantID ,startTime, endTime, status)
            values($userID, $eqID,$resDate,$grantID,$startTime, $endTime,'ready')
            ";
        return $this->conn->query($sql);
    }
}
