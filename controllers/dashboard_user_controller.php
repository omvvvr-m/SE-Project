<?php

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/session.php";
require_once __DIR__ . "/../includes/audit.php";
require_once __DIR__ . "/../includes/booking_rate.php";
require_once __DIR__ . "/../includes/training.php";
require_once __DIR__ . "/../includes/safety.php";
require_once __DIR__ . "/../models/equipment.php";
require_once __DIR__ . "/../models/reservation.php";

$currentUserId = session::requireUserOrRedirectToLogin();

$bookingError = session::pullFlash('booking_error');

$reservation = new Reservation($conn);
$equipment = new Equipment($conn);
$hourlyBookingRate = booking_get_effective_hourly_rate($conn, (int)$currentUserId);
$reservation->normalizeStatusesForUser((int)$currentUserId);
$requiredTrainingMap = training_get_required_map($conn);
$passedTrainingMap = training_get_user_passed_map($conn, (int)$currentUserId);
$safetyMap = safety_get_map($conn);

$sessionActionMsg = session::pullFlash('session_action_msg');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && session::handleDashboardUserSessionAction($conn, $reservation, (int)$currentUserId, $_POST)) {
  header("Location: dashboard-user.php#session-panel");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_action']) && $_POST['booking_action'] === 'delete') {
  $bookingID = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
  if ($bookingID > 0) {
    if ($reservation->removeReservationForUser($bookingID, (int)$currentUserId)) {
      session::setFlash('session_action_msg', 'Booking deleted successfully.');
    } else {
      session::setFlash('booking_error', $reservation->errorMsg ?: 'Could not delete booking.');
    }
  } else {
    session::setFlash('booking_error', 'Invalid booking id.');
  }
  header("Location: dashboard-user.php#booking-panel");
  exit;
}

$result = $equipment->getAll();
$reservResult = $reservation->getAllForUser();
$activeSession = $reservation->getActiveSessionForUser((int)$currentUserId);
$sessionEquipmentLabel = null;
$sessionRemainingTime = null;
$sessionEndTimestampMs = null;
$serverNowTimestampMs = null;
$sessionReservationId = null;
$currentUserRole = "researcher";
$guestExpiresAt = null;
$guestExpiresMs = null;
$userMetaRes = $conn->query("SELECT role, guest_expires_at FROM users WHERE userID = " . (int)$currentUserId . " LIMIT 1");
if ($userMetaRes && $meta = $userMetaRes->fetch_assoc()) {
  $currentUserRole = (string)($meta["role"] ?? "researcher");
  $guestExpiresAt = $meta["guest_expires_at"] ?? null;
  if ($currentUserRole === "guest" && !empty($guestExpiresAt)) {
    $ts = strtotime((string)$guestExpiresAt);
    if ($ts !== false) {
      $guestExpiresMs = (int)$ts * 1000;
    }
  }
}

$sessionEquipmentLabel = $activeSession ? ($activeSession['eqName'] ?? ('Equipment #' . ($activeSession['eqID'] ?? ($activeSession['equipmentID'] ?? '-')))) : null;

if ($activeSession) {
  $sessionReservationId = $activeSession['resID'] ?? $activeSession['bookingID'] ?? null;
  $sqlTimes = session::sqlNowAndEndTs($conn, $activeSession);
  $serverNowTimestampMs = $sqlTimes['now_ts'] * 1000;
  if ($sqlTimes['end_ts'] !== null) {
    $sessionEndTimestampMs = $sqlTimes['end_ts'] * 1000;
    $sessionRemainingTime = session::formatHmsFromSeconds($sqlTimes['end_ts'] - $sqlTimes['now_ts']);
  } else {
    $sessionRemainingTime = session::formatRemainingTime($activeSession['resDate'] ?? '', $activeSession['endTime']);
    $endDt = session::parseSessionEndDateTime($activeSession['resDate'] ?? '', $activeSession['endTime']);
    if ($endDt) {
      $sessionEndTimestampMs = (int) round($endDt->getTimestamp() * 1000);
    }
  }
}
