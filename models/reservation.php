<?php

require_once __DIR__ . "/../config/db.php";
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
