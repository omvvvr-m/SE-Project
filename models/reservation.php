<?php

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
        $_SESSION['booking_error'] = $reservation->errorMsg;
    }
    header('Location: dashboard-user.php');
    exit();
}

//reamining is setting the grandid 


class Reservation
{
    public $user;
    private $conn;
    public $errorMsg = "";


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
    public function normalizeStatusesForUser($userID)
    {
        $userID = (int)$userID;
        $hasResDate = $this->columnExists('reservation', 'resDate');
        $nowSql = $this->conn->real_escape_string(date('Y-m-d H:i:s'));

        if ($hasResDate) {
            $this->conn->query("UPDATE reservation
                SET status = 'ready'
                WHERE userID = $userID
                  AND status = 'ongoing'
                  AND STR_TO_DATE(CONCAT(resDate, ' ', startTime), '%Y-%m-%d %H:%i:%s') > '$nowSql'");
            $this->conn->query("UPDATE reservation
                SET status = 'terminated'
                WHERE userID = $userID
                  AND status = 'ongoing'
                  AND STR_TO_DATE(CONCAT(resDate, ' ', endTime), '%Y-%m-%d %H:%i:%s') < '$nowSql'");
        } else {
            $this->conn->query("UPDATE reservation
                SET status = 'ready'
                WHERE userID = $userID
                  AND status = 'ongoing'
                  AND startTime > '$nowSql'");
            $this->conn->query("UPDATE reservation
                SET status = 'terminated'
                WHERE userID = $userID
                  AND status = 'ongoing'
                  AND endTime < '$nowSql'");
        }
    }

    public function checkConflicts($resDate, $startTime, $endTime)
    {
        // INTERLOCK SYSTEM
        $sql = "SELECT COUNT(*) as count FROM reservation 
                WHERE resDate = '$resDate'
                AND (
                    startTime < '$endTime'
                    AND endTime > '$startTime'
                )";

        $result = $this->conn->query($sql)->fetch_assoc();

        if ($result['count'] > 0) return 1;
        else return 0; // no conflicts
    }


    public function getActiveSessionForUser($userID)
    {
        $userID = (int)$userID;
        $idColumn = $this->getReservationIdColumn();
        $eqColumn = $this->getEquipmentColumn();
        $hasResDate = $this->columnExists('reservation', 'resDate');

        $sql = "SELECT r.*, e.eqName
                FROM reservation r
                LEFT JOIN equipments e ON e.eqID = r.$eqColumn
                WHERE r.userID = $userID AND r.status = 'ongoing'
                ORDER BY r.$idColumn DESC
                LIMIT 1";
        $result = $this->conn->query($sql);
        if (!$result) {
            return null;
        }
        $active = $result->fetch_assoc();
        if ($active) {
            $now = new DateTime();
            $start = $this->parseReservationStartDateTime($active, $hasResDate);
            $end = $this->parseReservationEndDateTime($active, $hasResDate);
            if ($start && $start > $now) {
                $rid = (int)$active[$idColumn];
                $this->conn->query("UPDATE reservation SET status = 'ready' WHERE $idColumn = $rid");
                $active = null;
            } elseif ($end && $end < $now) {
                $rid = (int)$active[$idColumn];
                $this->conn->query("UPDATE reservation SET status = 'terminated' WHERE $idColumn = $rid");
                $active = null;
            } else {
                return $active;
            }
        }

        $nowSql = $this->conn->real_escape_string(date('Y-m-d H:i:s'));
        if ($hasResDate) {
            $readySql = "SELECT r.*, e.eqName
                         FROM reservation r
                         LEFT JOIN equipments e ON e.eqID = r.$eqColumn
                         WHERE r.userID = $userID
                           AND r.status = 'ready'
                           AND STR_TO_DATE(CONCAT(r.resDate, ' ', r.startTime), '%Y-%m-%d %H:%i:%s') <= '$nowSql'
                           AND STR_TO_DATE(CONCAT(r.resDate, ' ', r.endTime), '%Y-%m-%d %H:%i:%s') >= '$nowSql'
                         ORDER BY STR_TO_DATE(CONCAT(r.resDate, ' ', r.startTime), '%Y-%m-%d %H:%i:%s') ASC
                         LIMIT 1";
        } else {
            $readySql = "SELECT r.*, e.eqName
                         FROM reservation r
                         LEFT JOIN equipments e ON e.eqID = r.$eqColumn
                         WHERE r.userID = $userID
                           AND r.status = 'ready'
                           AND r.startTime <= '$nowSql'
                           AND r.endTime >= '$nowSql'
                         ORDER BY r.startTime ASC
                         LIMIT 1";
        }
        $readyResult = $this->conn->query($readySql);
        if (!$readyResult) {
            return null;
        }
        $readyRow = $readyResult->fetch_assoc();
        if (!$readyRow) {
            return null;
        }
        $rid = (int)$readyRow[$idColumn];
        $this->conn->query("UPDATE reservation SET status = 'ongoing' WHERE $idColumn = $rid");
        $readyRow['status'] = 'ongoing';
        return $readyRow;
    }
    public function terminateSession($resID, $userID)
    {
        $resID = (int)$resID;
        $userID = (int)$userID;
        $idColumn = $this->getReservationIdColumn();
        $sql = "UPDATE reservation
                SET status = 'terminated'
                WHERE $idColumn = $resID AND userID = $userID AND status = 'ongoing'";
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
        if ($this->checkConflicts($resDate, $startTime, $endTime) != 0) {
            $this->errorMsg = "There's a booking already in this timezone.";
            return false;
        }
        if ($this->user->deduct($price) != 0) {
            $this->errorMsg = "Insufficient Funds";
            return false;
        }
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
            $this->errorMsg = "Could not save reservation.";
            return false;
        }
        return true;
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
    private function parseReservationStartDateTime(array $row, $hasResDate)
    {
        $startRaw = trim((string)($row['startTime'] ?? ''));
        if ($startRaw === '') {
            return null;
        }
        if ($hasResDate) {
            $resDate = trim((string)($row['resDate'] ?? ''));
            if ($resDate !== '') {
                return DateTime::createFromFormat('Y-m-d H:i:s', $resDate . ' ' . $startRaw)
                    ?: DateTime::createFromFormat('Y-m-d H:i', $resDate . ' ' . $startRaw);
            }
        }
        return DateTime::createFromFormat('Y-m-d H:i:s', $startRaw)
            ?: DateTime::createFromFormat('Y-m-d H:i', $startRaw);
    }
    private function parseReservationEndDateTime(array $row, $hasResDate)
    {
        $endRaw = trim((string)($row['endTime'] ?? ''));
        if ($endRaw === '') {
            return null;
        }
        if ($hasResDate) {
            $resDate = trim((string)($row['resDate'] ?? ''));
            if ($resDate !== '') {
                return DateTime::createFromFormat('Y-m-d H:i:s', $resDate . ' ' . $endRaw)
                    ?: DateTime::createFromFormat('Y-m-d H:i', $resDate . ' ' . $endRaw);
            }
        }
        return DateTime::createFromFormat('Y-m-d H:i:s', $endRaw)
            ?: DateTime::createFromFormat('Y-m-d H:i', $endRaw);
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
