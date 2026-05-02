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
    $grantID = $_SESSION["grant_id"] ?? null;
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

    if (!$reservation->createBooking($userID, $eqID, $grantID, $resDate, $startTime, $endTime, $price)) {
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
        $idColumn = $this->getReservationIdColumn();
        $sql = "SELECT * FROM reservation ORDER BY $idColumn DESC";
        return $this->conn->query($sql);
    }
    public function addReservationFromAdmin($userID, $startDateTime, $endDateTime, $equipmentID, $status)
    {
        $userID = (int)$userID;
        $equipmentID = (int)$equipmentID;
        $status = in_array($status, ['ready', 'ongoing', 'terminated'], true) ? $status : 'ready';
        $safeStatus = $this->conn->real_escape_string($status);
        $startDateTime = $this->normalizeDateTimeInput($startDateTime);
        $endDateTime = $this->normalizeDateTimeInput($endDateTime);

        $eqColumn = $this->getEquipmentColumn();
        $hasResDate = $this->columnExists('reservation', 'resDate');

        if ($hasResDate) {
            $resDate = substr($startDateTime, 0, 10);
            $startTime = substr($startDateTime, 11, 8);
            $endTime = substr($endDateTime, 11, 8);
            $sql = "INSERT INTO reservation (userID, $eqColumn, resDate, startTime, endTime, status)
                    VALUES ($userID, $equipmentID, '$resDate', '$startTime', '$endTime', '$safeStatus')";
        } else {
            $sql = "INSERT INTO reservation (userID, $eqColumn, startTime, endTime, status)
                    VALUES ($userID, $equipmentID, '$startDateTime', '$endDateTime', '$safeStatus')";
        }
        return $this->conn->query($sql);
    }
    public function updateReservationFromAdmin($reservationID, $userID, $startDateTime, $endDateTime, $equipmentID, $status)
    {
        $reservationID = (int)$reservationID;
        $userID = (int)$userID;
        $equipmentID = (int)$equipmentID;
        $status = in_array($status, ['ready', 'ongoing', 'terminated'], true) ? $status : 'ready';
        $safeStatus = $this->conn->real_escape_string($status);
        $startDateTime = $this->normalizeDateTimeInput($startDateTime);
        $endDateTime = $this->normalizeDateTimeInput($endDateTime);

        $idColumn = $this->getReservationIdColumn();
        $eqColumn = $this->getEquipmentColumn();
        $hasResDate = $this->columnExists('reservation', 'resDate');

        if ($hasResDate) {
            $resDate = substr($startDateTime, 0, 10);
            $startTime = substr($startDateTime, 11, 8);
            $endTime = substr($endDateTime, 11, 8);
            $sql = "UPDATE reservation
                    SET userID = $userID,
                        $eqColumn = $equipmentID,
                        resDate = '$resDate',
                        startTime = '$startTime',
                        endTime = '$endTime',
                        status = '$safeStatus'
                    WHERE $idColumn = $reservationID";
        } else {
            $sql = "UPDATE reservation
                    SET userID = $userID,
                        $eqColumn = $equipmentID,
                        startTime = '$startDateTime',
                        endTime = '$endDateTime',
                        status = '$safeStatus'
                    WHERE $idColumn = $reservationID";
        }
        return $this->conn->query($sql);
    }
    public function removeReservation($reservationID)
    {
        $reservationID = (int)$reservationID;
        $idColumn = $this->getReservationIdColumn();
        $sql = "DELETE FROM reservation WHERE $idColumn = $reservationID";
        return $this->conn->query($sql);
    }
    public function getAllForUser()
    {

        $sql = "SELECT * FROM reservation where userID = " . $_SESSION["user_id"];
        return $this->conn->query($sql);
    }
    public function getActiveSessionForUser($userID)
    {
        $userID = (int)$userID;
        $sql = "SELECT r.*, e.eqName
                FROM reservation r
                LEFT JOIN equipments e ON e.eqID = r.eqID
                WHERE r.userID = $userID AND r.status = 'ongoing'
                ORDER BY r.resID DESC
                LIMIT 1";
        $result = $this->conn->query($sql);
        if (!$result) {
            return null;
        }
        $active = $result->fetch_assoc();
        if ($active) {
            return $active;
        }

        // Auto-start the latest ready reservation if no ongoing one exists.
        $readySql = "SELECT r.*, e.eqName
                     FROM reservation r
                     LEFT JOIN equipments e ON e.eqID = r.eqID
                     WHERE r.userID = $userID
                       AND r.status = 'ready'
                     ORDER BY r.resID DESC
                     LIMIT 1";
        $readyResult = $this->conn->query($readySql);
        if (!$readyResult) {
            return null;
        }
        $readyRow = $readyResult->fetch_assoc();
        if (!$readyRow) {
            return null;
        }
        $resID = (int)$readyRow['resID'];
        $this->conn->query("UPDATE reservation SET status = 'ongoing' WHERE resID = $resID");
        $readyRow['status'] = 'ongoing';
        return $readyRow;
    }
    public function terminateSession($resID, $userID)
    {
        $resID = (int)$resID;
        $userID = (int)$userID;
        $sql = "UPDATE reservation
                SET status = 'terminated'
                WHERE resID = $resID AND userID = $userID AND status = 'ongoing'";
        return $this->conn->query($sql);
    }
    public function createEmergencyReport($resID, $userID, $message, $startTime, $endTime)
    {
        $this->conn->query("CREATE TABLE IF NOT EXISTS emergency_reports (
            reportID INT NOT NULL AUTO_INCREMENT,
            resID INT NOT NULL,
            userID INT NOT NULL,
            startTime TIME NOT NULL,
            endTime TIME NOT NULL,
            message TEXT NOT NULL,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (reportID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $resID = (int)$resID;
        $userID = (int)$userID;
        $safeStart = $this->conn->real_escape_string($startTime);
        $safeEnd = $this->conn->real_escape_string($endTime);
        $safeMessage = $this->conn->real_escape_string($message);

        $sql = "INSERT INTO emergency_reports (resID, userID, startTime, endTime, message)
                VALUES ($resID, $userID, '$safeStart', '$safeEnd', '$safeMessage')";
        return $this->conn->query($sql);
    }
    public function getEmergencyReports()
    {
        $this->conn->query("CREATE TABLE IF NOT EXISTS emergency_reports (
            reportID INT NOT NULL AUTO_INCREMENT,
            resID INT NOT NULL,
            userID INT NOT NULL,
            startTime TIME NOT NULL,
            endTime TIME NOT NULL,
            message TEXT NOT NULL,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (reportID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        return $this->conn->query("SELECT * FROM emergency_reports ORDER BY reportID DESC");
    }
    public function createBooking($userID, $eqID, $grantID, $resDate, $startTime, $endTime, $price)
    {

        $userID = (int)$userID;
        $eqID = (int)$eqID;
        $price = (int)$price;
        $grantIDValue = is_null($grantID) ? "NULL" : (string)((int)$grantID);
        $resDate = $this->conn->real_escape_string($resDate);
        $startTime = $this->conn->real_escape_string($startTime);
        $endTime = $this->conn->real_escape_string($endTime);
        if ($this->user->deduct($price) == 0) {
            $eqColumn = $this->getEquipmentColumn();
            if ($this->columnExists('reservation', 'resDate')) {
                $sql = "INSERT INTO reservation (userID, $eqColumn, grantID, resDate, startTime, endTime, status)
                VALUES ($userID, $eqID, $grantIDValue, '$resDate', '$startTime', '$endTime', 'ready')";
            } else {
                $startDateTime = $this->conn->real_escape_string($resDate . ' ' . $startTime . ':00');
                $endDateTime = $this->conn->real_escape_string($resDate . ' ' . $endTime . ':00');
                $sql = "INSERT INTO reservation (userID, $eqColumn, grantID, startTime, endTime, status)
                VALUES ($userID, $eqID, $grantIDValue, '$startDateTime', '$endDateTime', 'ready')";
            }

            $result = $this->conn->query($sql);

            if (!$result) {
                die($this->conn->error);
            }

            return true;
        }

        return false;
    }
    private function columnExists($tableName, $columnName)
    {
        $safeTable = $this->conn->real_escape_string($tableName);
        $safeColumn = $this->conn->real_escape_string($columnName);
        $sql = "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'";
        $result = $this->conn->query($sql);
        if (!$result) {
            return false;
        }
        return $result->num_rows > 0;
    }
    private function getReservationIdColumn()
    {
        return $this->columnExists('reservation', 'resID') ? 'resID' : 'bookingID';
    }
    private function getEquipmentColumn()
    {
        return $this->columnExists('reservation', 'eqID') ? 'eqID' : 'equipmentID';
    }
    private function normalizeDateTimeInput($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return date('Y-m-d H:i:s');
        }
        $value = str_replace('T', ' ', $value);
        if (strlen($value) === 16) {
            $value .= ':00';
        }
        return $this->conn->real_escape_string($value);
    }
}
