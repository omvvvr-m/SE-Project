<?php

require_once __DIR__ . "/../models/user.php";
require_once __DIR__ . "/../includes/audit.php";
require_once __DIR__ . "/../includes/booking_rate.php";
require_once __DIR__ . "/../includes/training.php";
require_once __DIR__ . "/../includes/safety.php";

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
    isset($_POST['endTime'])
) {
    // $conn comes from models/user.php -> config/db.php include chain
    audit_init($conn);
    $userID = $_SESSION['user_id'];
    $grantID = $_SESSION["grant_id"] ?? null;
    $eqID =  $_POST['eqID'];
    $resDate =  $_POST['resDate'];
    $startTime =  $_POST['startTime'];
    $endTime =  $_POST['endTime'];
    $bookingAgreement = isset($_POST['booking_agreement']) && $_POST['booking_agreement'] === '1';
    $bookingErr = booking_validation_error($resDate, $startTime, $endTime);
    if ($bookingErr !== null) {
        $_SESSION['booking_error'] = $bookingErr;
        header('Location: dashboard-user.php');
        exit();
    }

    $currentHourlyRate = booking_get_effective_hourly_rate($conn, (int)$userID);
    $startMinutes = reservation_minutes_from_time((string)$startTime);
    $endMinutes = reservation_minutes_from_time((string)$endTime);
    $durationMinutes = max(0, $endMinutes - $startMinutes);
    $bookedHours = (int)ceil($durationMinutes / 60);
    $price = (float)round($bookedHours * $currentHourlyRate, 2);

    $safetyFee = 0.0;
    $safetyRequirement = safety_get_for_equipment($conn, (int)$eqID);
    $needsSafety = !empty($safetyRequirement["is_required"]);
    if ($needsSafety) {
        $safetyConfirmed = isset($_POST['safety_confirm']) && $_POST['safety_confirm'] === '1';
        if (!$safetyConfirmed) {
            $_SESSION['booking_error'] = "You must confirm mandatory safety requirement for this equipment before booking.";
            header('Location: dashboard-user.php');
            exit();
        }
        $safetyFee = 10.0;
        $price = (float)round($price + $safetyFee, 2);
    }
    if (!$bookingAgreement) {
        $_SESSION['booking_error'] = "You must agree to the booking safety terms before booking.";
        header('Location: dashboard-user.php');
        exit();
    }

    audit_event($conn, "reservation.create", [
        "userID" => (int)$userID,
        "eqID" => (int)$eqID,
        "resDate" => (string)$resDate,
        "startTime" => (string)$startTime,
        "endTime" => (string)$endTime,
        "price" => (float)$price,
        "hourlyRate" => (float)$currentHourlyRate,
        "safetyFee" => (float)$safetyFee
    ]);
    if (!$reservation->createBooking($userID, $eqID, $grantID, $resDate, $startTime, $endTime, $price)) {
        $_SESSION['booking_error'] = $reservation->errorMsg;
    }
    header('Location: dashboard-user.php');
    exit();
}

function reservation_minutes_from_time(string $time): int
{
    $parts = explode(":", $time);
    if (count($parts) < 2) {
        return 0;
    }
    $h = (int)$parts[0];
    $m = (int)$parts[1];
    return ($h * 60) + $m;
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
    public function addReservationFromAdmin($userID, $startDateTime, $endDateTime, $equipmentID, $status, $grantID = null)
    {

        $userID = (int)$userID;
        $equipmentID = (int)$equipmentID;
        $status = in_array($status, ['ready', 'ongoing', 'terminated'], true) ? $status : 'ready';
        $safeStatus = $this->conn->real_escape_string($status);
        $startDateTime = $this->normalizeDateTimeInput($startDateTime);
        $endDateTime = $this->normalizeDateTimeInput($endDateTime);

        $eqColumn = $this->getEquipmentColumn();
        $hasResDate = $this->columnExists('reservation', 'resDate');
        $grantCol = $this->getReservationGrantColumn();
        $grantSQL = '';
        $grantVals = '';

        if ($grantCol !== null) {
            $gid = $this->resolveGrantIdForInsert((int)$userID, $grantID);
            if ($gid === null) {
                return false;
            }
            $grantSQL = ', ' . $grantCol;
            $grantVals = ', ' . (string)(int)$gid;
        }

        if ($hasResDate) {
            $resDate = substr($startDateTime, 0, 10);
            $startTime = substr($startDateTime, 11, 8);
            $endTime = substr($endDateTime, 11, 8);
            $sql = "INSERT INTO reservation (userID, $eqColumn, resDate, startTime, endTime, status$grantSQL)
                    VALUES ($userID, $equipmentID, '$resDate', '$startTime', '$endTime', '$safeStatus'$grantVals)";
        } else {
            $sql = "INSERT INTO reservation (userID, $eqColumn, startTime, endTime, status$grantSQL)
                    VALUES ($userID, $equipmentID, '$startDateTime', '$endDateTime', '$safeStatus'$grantVals)";
        }
        return $this->conn->query($sql);
    }
    public function updateReservationFromAdmin($reservationID, $userID, $startDateTime, $endDateTime, $equipmentID, $status, $grantID = null)
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
        $grantCol = $this->getReservationGrantColumn();
        $grantSet = '';
        if ($grantCol !== null && $grantID !== null && $grantID !== '') {
            $gid = (int)$grantID;
            if ($this->grantBelongsToUser($gid, $userID)) {
                $grantSet = ', ' . $grantCol . ' = ' . $gid;
            }
        }

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
                        status = '$safeStatus'$grantSet
                    WHERE $idColumn = $reservationID";
        } else {
            $sql = "UPDATE reservation
                    SET userID = $userID,
                        $eqColumn = $equipmentID,
                        startTime = '$startDateTime',
                        endTime = '$endDateTime',
                        status = '$safeStatus'$grantSet
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

        $timeResult = $this->conn->query("SELECT NOW() as now");
        if (!$timeResult) {
            return;
        }

        $timeRow = $timeResult->fetch_assoc();
        $nowSql = $timeRow['now'];

        if ($hasResDate) {
            $this->conn->query("UPDATE reservation
            SET status = 'ready'
            WHERE userID = $userID
              AND status = 'ongoing'
              AND CONCAT(resDate, ' ', startTime) > '$nowSql'");

            $this->conn->query("UPDATE reservation
            SET status = 'terminated'
            WHERE userID = $userID
              AND status = 'ongoing'
              AND CONCAT(resDate, ' ', endTime) < '$nowSql'");
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

    public function checkConflicts($eqID, $resDate, $startTime, $endTime)
    {
        $eqID = (int)$eqID;
        $hasResDate = $this->columnExists('reservation', 'resDate');
        $eqColumn = $this->getEquipmentColumn();
        $sql = "SELECT COUNT(*) as count FROM reservation 
            WHERE resDate = '$resDate'
            AND (
                startTime < '$endTime'
                AND endTime > '$startTime'
            )
            AND (status IS NULL OR status != 'terminated')";

        if ($hasResDate) {
            $resDate = $this->conn->real_escape_string($resDate);
            $startTime = $this->conn->real_escape_string($startTime);
            $endTime = $this->conn->real_escape_string($endTime);
            $sql = "SELECT COUNT(*) AS cnt FROM reservation
                    WHERE $eqColumn = $eqID
                      AND resDate = '$resDate'
                      AND status != 'terminated'
                      AND STR_TO_DATE(CONCAT(resDate, ' ', startTime), '%Y-%m-%d %H:%i:%s')
                          < STR_TO_DATE(CONCAT('$resDate', ' ', '$endTime'), '%Y-%m-%d %H:%i:%s')
                      AND STR_TO_DATE(CONCAT(resDate, ' ', endTime), '%Y-%m-%d %H:%i:%s')
                          > STR_TO_DATE(CONCAT('$resDate', ' ', '$startTime'), '%Y-%m-%d %H:%i:%s')";
        } else {
            $startDt = $this->conn->real_escape_string($resDate . ' ' . $startTime . ':00');
            $endDt = $this->conn->real_escape_string($resDate . ' ' . $endTime . ':00');
            $sql = "SELECT COUNT(*) AS cnt FROM reservation
                    WHERE $eqColumn = $eqID
                      AND status != 'terminated'
                      AND startTime < '$endDt'
                      AND endTime > '$startDt'";
        }

        $result = $this->conn->query($sql);
        if (!$result) {
            return 0;
        }
        $row = $result->fetch_assoc();
        return ((int)($row['cnt'] ?? 0)) > 0 ? 1 : 0;
        return $result['count'] > 0;
    }


    public function getActiveSessionForUser($userID)
    {
        $userID = (int)$userID;
        $idColumn = $this->getReservationIdColumn();
        $eqColumn = $this->getEquipmentColumn();
        $hasResDate = $this->columnExists('reservation', 'resDate');

        // ✅ Get correct time from MySQL (fix DST issue)
        $timeResult = $this->conn->query("SELECT NOW() as now");
        if (!$timeResult) {
            return null;
        }
        $timeRow = $timeResult->fetch_assoc();
        $nowSql = $timeRow['now'];
        $now = new DateTime($nowSql);

        // =========================
        // 1. CHECK ONGOING
        // =========================
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
            $start = $this->parseReservationStartDateTime($active, $hasResDate);
            $end   = $this->parseReservationEndDateTime($active, $hasResDate);

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

        // =========================
        // 2. FIND READY → ACTIVATE
        // =========================
        if ($hasResDate) {
            $readySql = "SELECT r.*, e.eqName
                     FROM reservation r
                     LEFT JOIN equipments e ON e.eqID = r.$eqColumn
                     WHERE r.userID = $userID
                       AND r.status = 'ready'
                       AND CONCAT(r.resDate, ' ', r.startTime) <= '$nowSql'
                       AND CONCAT(r.resDate, ' ', r.endTime) >= '$nowSql'
                     ORDER BY CONCAT(r.resDate, ' ', r.startTime) ASC
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

        // Activate it
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
    public function createSessionSupportRequest($resID, $userID, $message)
    {
        $this->conn->query("CREATE TABLE IF NOT EXISTS session_support_requests (
            requestID INT NOT NULL AUTO_INCREMENT,
            resID INT NOT NULL,
            userID INT NOT NULL,
            message TEXT NOT NULL,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (requestID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $resID = (int)$resID;
        $userID = (int)$userID;
        $safeMessage = $this->conn->real_escape_string($message);

        $sql = "INSERT INTO session_support_requests (resID, userID, message)
                VALUES ($resID, $userID, '$safeMessage')";
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
        return $this->conn->query(
            "SELECT er.reportID, er.userID, er.resID, er.startTime, er.endTime, er.message, er.createdAt,
                    u.fname, u.lname, u.username
             FROM emergency_reports er
             LEFT JOIN users u ON u.userID = er.userID
             ORDER BY er.reportID DESC"
        );
    }
    public function getSessionSupportRequests()
    {
        $this->conn->query("CREATE TABLE IF NOT EXISTS session_support_requests (
            requestID INT NOT NULL AUTO_INCREMENT,
            resID INT NOT NULL,
            userID INT NOT NULL,
            message TEXT NOT NULL,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (requestID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        return $this->conn->query(
            "SELECT sr.requestID, sr.userID, sr.resID, sr.message, sr.createdAt,
                    u.fname, u.lname, u.username
             FROM session_support_requests sr
             LEFT JOIN users u ON u.userID = sr.userID
             ORDER BY sr.requestID DESC"
        );
    }
    public function createBooking($userID, $eqID, $grantID, $resDate, $startTime, $endTime, $price)
    {
        $userID = (int)$userID;
        $eqID = (int)$eqID;
        $price = (float)$price;
        $eqStatusRes = $this->conn->query("SELECT status FROM equipments WHERE eqID = $eqID LIMIT 1");
        if (!$eqStatusRes || !($eqStatusRow = $eqStatusRes->fetch_assoc())) {
            $this->errorMsg = "Selected equipment was not found.";
            return false;
        }
        $eqStatus = strtolower(trim((string)($eqStatusRow["status"] ?? "")));
        if ($eqStatus !== "ready") {
            $this->errorMsg = "This equipment is under maintenance and cannot be booked now.";
            return false;
        }
        if (!training_user_has_access($this->conn, $userID, $eqID)) {
            $trainingTitle = "";
            $titleRes = $this->conn->query("SELECT training_title FROM equipment_training_requirements WHERE eqID = $eqID LIMIT 1");
            if ($titleRes && $titleRow = $titleRes->fetch_assoc()) {
                $trainingTitle = trim((string)($titleRow["training_title"] ?? ""));
            }
            $this->errorMsg = $trainingTitle !== ""
                ? ("You cannot book this equipment until you pass training: " . $trainingTitle)
                : "You cannot book this equipment until you pass the required training.";
            return false;
        }
        $grantColBooking = $this->getReservationGrantColumn();
        $resolvedGrantId = null;
        if ($grantColBooking !== null) {
            $resolvedGrantId = $this->resolveGrantIdForInsert($userID, $grantID);
            if ($resolvedGrantId === null) {
                $this->errorMsg = "No valid grant for this account. Ask admin to assign a grant.";
                return false;
            }
        } else {
            $resolvedGrantId = is_null($grantID) ? null : (int)$grantID;
        }
        $grantIDSql = $grantColBooking === null
            ? (is_null($resolvedGrantId) ? "NULL" : (string)(int)$resolvedGrantId)
            : (string)(int)$resolvedGrantId;
        $resDate = $this->conn->real_escape_string($resDate);
        $startTime = $this->conn->real_escape_string($startTime);
        $endTime = $this->conn->real_escape_string($endTime);
        if ($this->checkConflicts($eqID, $resDate, $startTime, $endTime) != 0) {
            $this->errorMsg = "There's a booking already in this timezone.";
            return false;
        }
        if ($this->user->deduct($price, $resolvedGrantId, $userID) != 0) {
            $this->errorMsg = "Insufficient Funds";
            return false;
        }
        $eqColumn = $this->getEquipmentColumn();
        $grantFrag = $grantColBooking === null ? "" : ", grantID";
        $grantVals = $grantColBooking === null ? "" : ", $grantIDSql";
        if ($this->columnExists('reservation', 'resDate')) {
            $sql = "INSERT INTO reservation (userID, $eqColumn$grantFrag, resDate, startTime, endTime, status)
                VALUES ($userID, $eqID$grantVals, '$resDate', '$startTime', '$endTime', 'ready')";
        } else {
            $startDateTime = $this->conn->real_escape_string($resDate . ' ' . $startTime . ':00');
            $endDateTime = $this->conn->real_escape_string($resDate . ' ' . $endTime . ':00');
            $sql = "INSERT INTO reservation (userID, $eqColumn$grantFrag, startTime, endTime, status)
                VALUES ($userID, $eqID$grantVals, '$startDateTime', '$endDateTime', 'ready')";
        }
        $result = $this->conn->query($sql);
        if (!$result) {
            $this->errorMsg = "Could not save reservation.";
            return false;
        }
        return true;
    }

    public function removeReservationForUser($reservationID, $userID)
    {
        $reservationID = (int)$reservationID;
        $userID = (int)$userID;
        $idColumn = $this->getReservationIdColumn();
        $sql = "DELETE FROM reservation
                WHERE $idColumn = $reservationID
                  AND userID = $userID
                  AND status != 'ongoing'";
        $result = $this->conn->query($sql);
        if (!$result) {
            $this->errorMsg = "Could not delete reservation, Make sure it's not ongoing and try again";
            return false;
        }
        return $this->conn->affected_rows > 0;
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

    /** @return string|null column name grantID/grant_id or null if no grant column */
    private function getReservationGrantColumn()
    {
        foreach (['grantID', 'grantid', 'grant_id'] as $c) {
            if ($this->columnExists('reservation', $c)) {
                return $c;
            }
        }
        return null;
    }

    private function grantBelongsToUser($grantID, $userID)
    {
        $grantID = (int)$grantID;
        $userID = (int)$userID;
        $res = $this->conn->query("SELECT grantID FROM grants WHERE grantID = $grantID AND userID = $userID LIMIT 1");
        return $res && $res->num_rows > 0;
    }

    private function firstGrantIdForUser($userID)
    {
        $userID = (int)$userID;
        $res = $this->conn->query("SELECT grantID FROM grants WHERE userID = $userID ORDER BY grantID ASC LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            return (int)$row['grantID'];
        }
        return null;
    }

    private function resolveGrantIdForInsert($userID, $requestedGrantId)
    {
        if ($requestedGrantId !== null && $requestedGrantId !== '') {
            $gid = (int)$requestedGrantId;
            if ($this->grantBelongsToUser($gid, $userID)) {
                return $gid;
            }
        }
        return $this->firstGrantIdForUser($userID);
    }
}
