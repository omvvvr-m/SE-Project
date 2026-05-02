<?php
// require_once __DIR__ . "/../config/db.php";
// $reservationModel = new Reservation($conn);

// if (isset($_GET["delete_id"])) {
//     $reservationModel->removeReservation($_GET["delete_id"]);
//     header("Location: ../reservations-management.php");
//     exit();
// }

// if (
//     isset($_POST["user_id"]) &&
//     isset($_POST["start_time"]) &&
//     isset($_POST["end_time"]) &&
//     isset($_POST["equipment_id"]) &&
//     isset($_POST["status"])
// ) {
//     $userID = $_POST["user_id"];
//     $startTime = $_POST["start_time"];
//     $endTime = $_POST["end_time"];
//     $equipmentID = $_POST["equipment_id"];
//     $status = $_POST["status"];

//     if (isset($_POST["booking_id"]) && $_POST["booking_id"] !== "") {
//         $reservationModel->updateReservation($_POST["booking_id"], $userID, $startTime, $endTime, $equipmentID, $status);
//     } else {
//         $reservationModel->addReservation($userID, $startTime, $endTime, $equipmentID, $status);
//     }

//     header("Location: reservations-management.php");
//     exit();
// }


/*class Reservation
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
}
    */
require_once __DIR__ . "/../models/user.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** @return string|null error message or null if OK */
function booking_validation_error($resDate, $startTime, $endTime)
{
    $day = DateTime::createFromFormat('Y-m-d', $resDate);
    if (!$day) {
        return 'Invalid date.';
    }
    $today = new DateTime('today');
    if ($day < $today) {
        return 'Choose today or a future date.';
    }

    $start = DateTime::createFromFormat('Y-m-d H:i', $resDate . ' ' . $startTime);
    $end = DateTime::createFromFormat('Y-m-d H:i', $resDate . ' ' . $endTime);
    if (!$start || !$end) {
        return 'Invalid start or end time.';
    }
    if ($end <= $start) {
        return 'End time must be after start time.';
    }

    $now = new DateTime();
    if ($day->format('Y-m-d') === $now->format('Y-m-d') && $start < $now) {
        return 'Start time cannot be in the past.';
    }

    return null;
}



// What to do????
// Well, just deduct the balance from the grant and create the reservation
// Then, update the reservation status to ongoing when the user starts the session
// And check whether the user has grant or not




$reservation = new Reservation($conn);
$user = new User($conn);

if (
    isset($_POST['eqID']) &&
    isset($_POST['resDate']) &&
    isset($_POST['startTime']) &&
    isset($_POST['endTime']) &&
    isset($_POST['price'])
) {
    $userID = $_SESSION['user_id'];
    $grandID = $_SESSION["grant_id"];
    $eqID =  $_POST['eqID'];
    $resDate =  $_POST['resDate'];
    $startTime =  $_POST['startTime'];
    $endTime =  $_POST['endTime'];
    $price =  $_POST['price'];
    $bookingErr = booking_validation_error($resDate, $startTime, $endTime);
    if ($bookingErr !== null) {
        $_SESSION['booking_error'] = $bookingErr;
        header('Location: dashboard-user.php');
        exit();
    }

    if (!$reservation->createBooking($userID, $eqID, $grandID, $resDate, $startTime, $endTime, $price)) {
        $_SESSION['booking_error'] = 'Insufficient balance.';
    }
    header('Location: dashboard-user.php');
    exit();
}

//reamining is setting the grandid 


class Reservation
{
    public $user;
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
        $this->user = new User($db);
    }
    public function getAll()
    {
        $sql = "SELECT * FROM reservation ORDER BY bookingID DESC";
        return $this->conn->query($sql);
    }
    public function getAllForUser()
    {

        $sql = "SELECT * FROM reservation where userID = " . $_SESSION["user_id"];
        return $this->conn->query($sql);
    }
    public function createBooking($userID, $eqID, $grantID, $resDate, $startTime, $endTime, $price)
    {

        $userID = (int)$userID;
        $eqID = (int)$eqID;
        $price = (int)$price;
        $resDate = $this->conn->real_escape_string($resDate);
        $startTime = $this->conn->real_escape_string($startTime);
        $endTime = $this->conn->real_escape_string($endTime);
        if ($this->user->deduct($price) == 0) {
            $sql = "INSERT INTO reservation (userID, eqID, grantID, resDate, startTime, endTime, status)
            VALUES ($userID, $eqID,$grantID, '$resDate', '$startTime', '$endTime', 'ready')";

            $result = $this->conn->query($sql);

            if (!$result) {
                die($this->conn->error);
            }

            return true;
        }

        return false;
    }
}
