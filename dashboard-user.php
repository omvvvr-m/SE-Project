<?php

session_start();

if (!isset($_SESSION["user_id"])) {
  header("Location: login.html");
  exit;
}

$currentUserId = $_SESSION["user_id"];

$bookingError = $_SESSION['booking_error'] ?? null;
unset($_SESSION['booking_error']);

require_once "config/db.php";
require_once "models/equipment.php";
require_once "models/reservation.php";

$reservation = new Reservation($conn);
$equipment = new Equipment($conn);
$reservation->normalizeStatusesForUser((int)$currentUserId);

$sessionActionMsg = $_SESSION['session_action_msg'] ?? null;
unset($_SESSION['session_action_msg']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['session_action'])) {
  $action = $_POST['session_action'];
  $resID = isset($_POST['res_id']) ? (int)$_POST['res_id'] : 0;

  if ($action === 'terminate' && $resID > 0) {
    $reservation->terminateSession($resID, (int)$currentUserId);
    $_SESSION['session_action_msg'] = 'Session terminated successfully.';
  } elseif ($action === 'emergency' && $resID > 0) {
    $message = trim($_POST['emergency_message'] ?? '');
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? '';
    if ($message === '') {
      $_SESSION['session_action_msg'] = 'Please write the emergency issue details.';
    } else {
      $reservation->createEmergencyReport($resID, (int)$currentUserId, $message, $startTime, $endTime);
      $reservation->terminateSession($resID, (int)$currentUserId);
      $_SESSION['session_action_msg'] = 'Emergency report sent to admin and session terminated.';
    }
  }

  header("Location: dashboard-user.php#session-panel");
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

/** @return DateTime|null */
function parse_session_end_datetime($resDate, $endTime)
{
  $endTime = trim((string)$endTime);
  if ($endTime === '') {
    return null;
  }
  $end = null;
  $resDate = trim((string)$resDate);
  if ($resDate !== '') {
    $end = DateTime::createFromFormat('Y-m-d H:i', $resDate . ' ' . $endTime)
      ?: DateTime::createFromFormat('Y-m-d H:i:s', $resDate . ' ' . $endTime);
  }
  if (!$end && preg_match('/^\d{4}-\d{2}-\d{2}/', $endTime)) {
    $end = DateTime::createFromFormat('Y-m-d H:i:s', $endTime)
      ?: DateTime::createFromFormat('Y-m-d H:i', $endTime);
  }
  if (!$end) {
    $end = DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' ' . $endTime)
      ?: DateTime::createFromFormat('Y-m-d H:i', date('Y-m-d') . ' ' . $endTime);
  }
  return $end ?: null;
}

function format_remaining_time($resDate, $endTime)
{
  $end = parse_session_end_datetime($resDate, $endTime);
  if (!$end) {
    return "00:00:00";
  }
  $now = new DateTime();
  if ($now >= $end) {
    return "00:00:00";
  }
  $seconds = $end->getTimestamp() - $now->getTimestamp();
  $hours = floor($seconds / 3600);
  $minutes = floor(($seconds % 3600) / 60);
  $secs = $seconds % 60;
  return sprintf("%02d:%02d:%02d", $hours, $minutes, $secs);
}

function format_hms_from_seconds($seconds)
{
  $seconds = max(0, (int) $seconds);
  $hours = intdiv($seconds, 3600);
  $minutes = intdiv($seconds % 3600, 60);
  $secs = $seconds % 60;
  return sprintf("%02d:%02d:%02d", $hours, $minutes, $secs);
}


function session_sql_now_and_end_ts(mysqli $conn, array $activeSession)
{
  $nowRes = $conn->query("SELECT UNIX_TIMESTAMP(NOW()) AS ts");
  $nowTs = 0;
  if ($nowRes && $row = $nowRes->fetch_assoc()) {
    $nowTs = (int) $row['ts'];
  }

  $endTs = null;
  $resDate = trim((string) ($activeSession['resDate'] ?? ''));
  $endTime = trim((string) ($activeSession['endTime'] ?? ''));

  if ($endTime !== '') {
    $er = null;
    if ($resDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $resDate)) {
      $d = $conn->real_escape_string($resDate);
      $t = $conn->real_escape_string($endTime);
      $er = $conn->query("SELECT UNIX_TIMESTAMP(CONCAT('$d', ' ', '$t')) AS ts");
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $endTime)) {
      $t = $conn->real_escape_string($endTime);
      $er = $conn->query("SELECT UNIX_TIMESTAMP('$t') AS ts");
    } else {
      $t = $conn->real_escape_string($endTime);
      $er = $conn->query("SELECT UNIX_TIMESTAMP(CONCAT(CURDATE(), ' ', '$t')) AS ts");
    }
    if ($er) {
      $row = $er->fetch_assoc();
      if (is_array($row) && isset($row['ts']) && is_numeric($row['ts'])) {
        $endTs = (int) $row['ts'];
      }
    }
  }

  return ['now_ts' => $nowTs, 'end_ts' => $endTs];
}

$sessionEquipmentLabel = $activeSession ? ($activeSession['eqName'] ?? ('Equipment #' . ($activeSession['eqID'] ?? ($activeSession['equipmentID'] ?? '-')))) : null;

if ($activeSession) {
  $sessionReservationId = $activeSession['resID'] ?? $activeSession['bookingID'] ?? null;
  $sqlTimes = session_sql_now_and_end_ts($conn, $activeSession);
  $serverNowTimestampMs = $sqlTimes['now_ts'] * 1000;
  if ($sqlTimes['end_ts'] !== null) {
    $sessionEndTimestampMs = $sqlTimes['end_ts'] * 1000;
    $sessionRemainingTime = format_hms_from_seconds($sqlTimes['end_ts'] - $sqlTimes['now_ts']);
  } else {
    $sessionRemainingTime = format_remaining_time($activeSession['resDate'] ?? '', $activeSession['endTime']);
    $endDt = parse_session_end_datetime($activeSession['resDate'] ?? '', $activeSession['endTime']);
    if ($endDt) {
      $sessionEndTimestampMs = (int) round($endDt->getTimestamp() * 1000);
    }
  }
}
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Dashboard | Virtual Lab</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="css/style.css" />
</head>

<script>




</script>

<body>
  <div class="page-wrapper">
    <div class="container-fluid">
      <div class="row g-3">
        <aside class="col-lg-3 col-xl-2">
          <div class="sidebar p-3">
            <h2 class="h5 mb-4">User Panel</h2>
            <nav class="nav flex-column">
              <a class="nav-link active" href="dashboard-user.php"><i class="bi bi-house me-2"></i>Dashboard</a>
              <a class="nav-link" href="profile.php?from=user&user_id=<?php echo urlencode((string)$currentUserId); ?>" data-profile-link><i class="bi bi-person me-2"></i>Profile</a>
              <a class="nav-link" href="#booking-panel" data-open-booking-modal><i class="bi bi-calendar2-check me-2"></i>Booking Panel</a>
              <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#sessionInfoModal"><i class="bi bi-stopwatch me-2"></i>Session Panel</a>
              <a class="nav-link" href="my-grants.php"><i class="bi bi-cash-coin me-2"></i>Grants Panel</a>
              <a class="nav-link" href="login.html"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
            </nav>
          </div>
        </aside>

        <section class="col-lg-9 col-xl-10">
          <?php if (!empty($bookingError)) { ?>
            <div class="alert alert-warning py-2 mb-3" role="alert"><?php echo htmlspecialchars($bookingError); ?></div>
          <?php } ?>

          <div class="topbar p-3 mb-3 d-flex justify-content-between align-items-center">
            <div>
              <h1 class="h4 mb-1">Researcher Dashboard</h1>
              <small id="welcomeText" class="text-secondary">Welcome back, <span class="fullName"></span></small>
            </div>
            <a href="#" class="btn btn-outline-primary btn-outline-soft btn-sm" data-bs-toggle="modal" data-bs-target="#sessionInfoModal">Open Session Panel</a>
          </div>
          <?php if (!empty($sessionActionMsg)) { ?>
            <div class="alert alert-info py-2 mb-3" role="alert"><?php echo htmlspecialchars($sessionActionMsg); ?></div>
          <?php } ?>


          <div class="row g-3">
            <div id="profile-panel" class="col-12">
              <div class="card-soft p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h2 class="panel-title mb-0">Profile Panel</h2>
                  <a href="profile.php?from=user&user_id=<?php echo urlencode((string)$currentUserId); ?>" data-profile-link class="btn btn-outline-primary btn-outline-soft btn-sm">Open Full Profile</a>
                </div>
                <div class="row g-2">
                  <div class="col-md-3"><span class="muted-label">Full Name:</span>
                    <div id="fullName" class="fullName">Dr. Sarah Ahmed</div>
                  </div>
                  <div class="col-md-3"><span class="muted-label">Phone:</span>
                    <div class="phoneNumber">+20 100 000 0000</div>
                  </div>
                  <div class="col-md-3"><span class="muted-label">Role:</span>
                    <div class="role">Researcher</div>
                  </div>
                  <div class="col-md-3"><span class="muted-label">User ID:</span>
                    <div class="userID">U-1024</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="booking-panel" class="col-12">
              <div class="card-soft p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h2 class="panel-title mb-0">Booking Panel</h2>
                  <button class="btn btn-gradient btn-sm px-3" data-bs-toggle="modal"
                    data-bs-target="#bookingModal">Book Equipment</button>
                </div>
                <div class="table-responsive">
                  <table class="table table-striped mb-0">
                    <thead>
                      <tr>
                        <th>Booking ID</th>
                        <th>Equipment</th>
                        <th>Date</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php while ($row = $reservResult->fetch_assoc()) {
                        $bookingRowId = (int) ($row['resID'] ?? $row['bookingID'] ?? 0);
                        $eqRowId = (int) ($row['eqID'] ?? $row['equipmentID'] ?? 0);
                        $eqName = '—';
                        if ($eqRowId > 0) {
                          $eqRes = $conn->query('SELECT eqName FROM equipments WHERE eqID = ' . $eqRowId);
                          if ($eqRes && $eqRow = $eqRes->fetch_assoc()) {
                            $eqName = $eqRow['eqName'];
                          }
                        }
                      ?>
                        <tr>
                          <td><?php echo htmlspecialchars((string) $bookingRowId); ?></td>
                          <td><?php echo htmlspecialchars($eqName); ?></td>
                          <td><?php echo htmlspecialchars($row['resDate'] ?? date('Y-m-d', strtotime($row['startTime']))); ?></td>
                          <td><?php echo htmlspecialchars((string) $row['startTime']); ?></td>
                          <td><?php echo htmlspecialchars((string) $row['endTime']); ?></td>
                          <td> <?php if ($row['status'] == 'ongoing') { ?>
                              <span class="badge text-bg-success">ongoing</span>
                            <?php } else if ($row['status'] == 'terminated') { ?>
                              <span class="badge text-bg-secondary">terminated</span>
                            <?php } else { ?>
                              <span class="badge text-bg-primary">ready</span>
                            <?php } ?>
                          </td>
                          <td class="text-end">
                            <?php if ($row['status'] === 'ongoing') { ?>
                              <span class="small text-secondary" title="Terminate the session in the Session Panel first">—</span>
                            <?php } elseif ($bookingRowId > 0) { ?>
                              <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirm('Delete this reservation?');">Delete</button>
                            <?php } ?>
                          </td>

                        </tr>
                      <?php } ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div id="session-panel" class="col-12 col-xl-6">
              <div class="card-soft p-3 h-100">
                <h2 class="panel-title">Session Panel</h2>
                <?php if ($activeSession) { ?>
                  <p class="mb-1"><span class="muted-label">Equipment:</span> <?php echo htmlspecialchars($sessionEquipmentLabel); ?></p>
                  <p class="mb-1"><span class="muted-label">Remaining Time:</span> <span class="js-session-remaining"><?php echo htmlspecialchars($sessionRemainingTime); ?></span></p>
                  <p class="mb-1"><span class="muted-label">Start Time:</span> <?php echo htmlspecialchars($activeSession['startTime']); ?></p>
                  <p class="mb-3"><span class="muted-label">End Time:</span> <?php echo htmlspecialchars($activeSession['endTime']); ?></p>

                  <div class="d-flex gap-2 mb-3">
                    <form method="POST" class="m-0">
                      <input type="hidden" name="session_action" value="terminate" />
                      <input type="hidden" name="res_id" value="<?php echo htmlspecialchars((string)$sessionReservationId); ?>" />
                      <button type="submit" class="btn btn-outline-danger btn-sm">Terminate</button>
                    </form>
                    <button class="btn btn-danger btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#emergencyBox" aria-expanded="false" aria-controls="emergencyBox">
                      Emergency
                    </button>
                  </div>

                  <div class="collapse" id="emergencyBox">
                    <form method="POST">
                      <input type="hidden" name="session_action" value="emergency" />
                      <input type="hidden" name="res_id" value="<?php echo htmlspecialchars((string)$sessionReservationId); ?>" />
                      <input type="hidden" name="start_time" value="<?php echo htmlspecialchars($activeSession['startTime']); ?>" />
                      <input type="hidden" name="end_time" value="<?php echo htmlspecialchars($activeSession['endTime']); ?>" />
                      <label for="emergency_message" class="form-label mb-1">Describe the issue</label>
                      <textarea id="emergency_message" name="emergency_message" class="form-control mb-2" rows="3" placeholder="Write the emergency issue..." required></textarea>
                      <button type="submit" class="btn btn-danger btn-sm">Send Report & Terminate</button>
                    </form>
                  </div>
                <?php } else { ?>
                  <p class="text-secondary mb-0">No ongoing session currently.</p>
                <?php } ?>
              </div>
            </div>
            <div id="grants-panel" class="col-12 col-xl-6">
              <div class="card-soft p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h2 class="panel-title mb-0">My Grants</h2>
                  <a href="my-grants.php" class="btn btn-outline-primary btn-outline-soft btn-sm">Open Grants Page</a>
                </div>
                <div id="myGrantsList">
                  <div class="small text-secondary">Loading grants...</div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>

  <div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form id="bookingForm" method="POST">
        <div class="modal-content">
          <div class="modal-header">
            <h2 class="modal-title fs-5">Book Equipment</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Equipment</label>

                <select name="eqID" class="form-select">
                  <?php while ($row = $result->fetch_assoc()) { ?>
                    <option><?php echo $row['eqID'] . " - " . $row['eqName']  ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Booking Date</label>
                <input name="resDate" type="date" class="form-control" id="bookingDate" required />
              </div>
              <div class="col-6">
                <label class="form-label">Required Qualification</label>
                <input name="qual" type="text" class="form-control" placeholder="Microscopy Certificate" />
              </div>
              <div class="col-6">
                <label class="form-label">Start Time</label>
                <input type="time" name="startTime" class="form-control" id="bookingStartTime" />
              </div>
              <div class="col-6">
                <label class="form-label">End Time</label>
                <input type="time" name="endTime" class="form-control" id="bookingEndTime" />
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline-secondary btn-outline-soft" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-gradient" id="confirmBookingBtn">Confirm Booking</button>
            <input id="priceLabel" name="price" hidden></input>
          </div>
        </div>
      </form>
    </div>
  </div>
  <div class="modal fade" id="sessionInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5">Session Panel</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php if ($activeSession) { ?>
            <p class="mb-1"><span class="muted-label">Equipment:</span> <?php echo htmlspecialchars($sessionEquipmentLabel); ?></p>
            <p class="mb-1"><span class="muted-label">Remaining Time:</span> <span class="js-session-remaining"><?php echo htmlspecialchars($sessionRemainingTime); ?></span></p>
            <p class="mb-1"><span class="muted-label">Start Time:</span> <?php echo htmlspecialchars($activeSession['startTime']); ?></p>
            <p class="mb-3"><span class="muted-label">End Time:</span> <?php echo htmlspecialchars($activeSession['endTime']); ?></p>

            <div class="d-flex gap-2 mb-3">
              <form method="POST" class="m-0">
                <input type="hidden" name="session_action" value="terminate" />
                <input type="hidden" name="res_id" value="<?php echo htmlspecialchars((string)$sessionReservationId); ?>" />
                <button type="submit" class="btn btn-outline-danger btn-sm">Terminate</button>
              </form>
              <button class="btn btn-danger btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#modalEmergencyBox" aria-expanded="false" aria-controls="modalEmergencyBox">
                Emergency
              </button>
            </div>

            <div class="collapse" id="modalEmergencyBox">
              <form method="POST">
                <input type="hidden" name="session_action" value="emergency" />
                <input type="hidden" name="res_id" value="<?php echo htmlspecialchars((string)$sessionReservationId); ?>" />
                <input type="hidden" name="start_time" value="<?php echo htmlspecialchars($activeSession['startTime']); ?>" />
                <input type="hidden" name="end_time" value="<?php echo htmlspecialchars($activeSession['endTime']); ?>" />
                <label for="modal_emergency_message" class="form-label mb-1">Describe the issue</label>
                <textarea id="modal_emergency_message" name="emergency_message" class="form-control mb-2" rows="3" placeholder="Write the emergency issue..." required></textarea>
                <button type="submit" class="btn btn-danger btn-sm">Send Report & Terminate</button>
              </form>
            </div>
          <?php } else { ?>
            <p class="text-secondary mb-0">No ongoing session currently.</p>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>

  <script type="application/json" id="session-countdown-data">
    <?php echo json_encode(['endMs' => $sessionEndTimestampMs, 'serverNowMs' => $serverNowTimestampMs]); ?>
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    (function() {
      const cfgEl = document.getElementById("session-countdown-data");
      if (!cfgEl) return;
      let cfg = {};
      try {
        cfg = JSON.parse(cfgEl.textContent || "{}");
      } catch (e) {
        return;
      }
      const endMs = cfg.endMs;
      if (endMs == null || typeof endMs !== "number") return;

      const clientAtLoad = Date.now();
      const serverSkew =
        cfg.serverNowMs != null && typeof cfg.serverNowMs === "number" ?
        cfg.serverNowMs - clientAtLoad :
        0;

      function formatRemaining(ms) {
        if (ms <= 0) return "00:00:00";
        const s = Math.floor(ms / 1000);
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        return String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0") + ":" + String(sec).padStart(2, "0");
      }

      function tick() {
        const serverNow = Date.now() + serverSkew;
        const rem = endMs - serverNow;
        const text = formatRemaining(rem);
        document.querySelectorAll(".js-session-remaining").forEach(function(el) {
          el.textContent = text;
        });
        if (rem <= 0) {
          clearInterval(timer);
        }
      }

      tick();
      const timer = setInterval(tick, 1000);
    })();
  </script>
  <script>
    const bookingForm = document.getElementById("bookingForm");
    const bookingDate = document.getElementById("bookingDate");
    const bookingStartTime = document.getElementById("bookingStartTime");
    const bookingEndTime = document.getElementById("bookingEndTime");
    const confirmBookingBtn = document.getElementById("confirmBookingBtn");
    const bookingModal = document.getElementById("bookingModal");
    const hourlyBookingRate = 15;
    const defaultConfirmLabel = "Confirm Booking";
    const priceLabel = document.getElementById("priceLabel");

    function todayStr() {
      const n = new Date();
      return n.getFullYear() + "-" + String(n.getMonth() + 1).padStart(2, "0") + "-" + String(n.getDate()).padStart(2, "0");
    }

    function syncBookingMins() {
      bookingDate.min = todayStr();
      if (bookingDate.value === todayStr()) {
        const n = new Date();
        bookingStartTime.min = String(n.getHours()).padStart(2, "0") + ":" + String(n.getMinutes()).padStart(2, "0");
      } else {
        bookingStartTime.removeAttribute("min");
      }
    }

    bookingDate.addEventListener("change", syncBookingMins);
    bookingModal.addEventListener("shown.bs.modal", syncBookingMins);
    syncBookingMins();

    bookingForm.addEventListener("submit", function(e) {
      const d = bookingDate.value;
      const t0 = bookingStartTime.value;
      const t1 = bookingEndTime.value;
      const now = new Date();
      if (!d || !t0 || !t1) return;
      if (d < todayStr()) {
        e.preventDefault();
        alert("Choose today or a future date.");
        return;
      }
      const [sh, sm] = t0.split(":").map(Number);
      const [eh, em] = t1.split(":").map(Number);
      const startM = sh * 60 + sm;
      const endM = eh * 60 + em;
      if (endM <= startM) {
        e.preventDefault();
        alert("End time must be after start time.");
        return;
      }
      if (d === todayStr()) {
        const curM = now.getHours() * 60 + now.getMinutes();
        if (startM < curM) {
          e.preventDefault();
          alert("Start time cannot be in the past.");
        }
      }
    });

    function updateBookingPriceLabel() {
      const startValue = bookingStartTime.value;
      const endValue = bookingEndTime.value;

      if (!startValue || !endValue) {
        confirmBookingBtn.textContent = defaultConfirmLabel;
        return;
      }

      const [startHour, startMinute] = startValue.split(":").map(Number);
      const [endHour, endMinute] = endValue.split(":").map(Number);

      const startTotalMinutes = startHour * 60 + startMinute;
      const endTotalMinutes = endHour * 60 + endMinute;
      const durationMinutes = endTotalMinutes - startTotalMinutes;

      if (durationMinutes <= 0) {
        confirmBookingBtn.textContent = defaultConfirmLabel;
        return;
      }

      const bookedHours = Math.ceil(durationMinutes / 60);
      const totalPrice = bookedHours * hourlyBookingRate;
      confirmBookingBtn.textContent = `${totalPrice}$ - ${defaultConfirmLabel}`;
      priceLabel.value = totalPrice;
    }

    bookingStartTime.addEventListener("input", updateBookingPriceLabel);
    bookingEndTime.addEventListener("input", updateBookingPriceLabel);
  </script>
  <script>
    document.querySelectorAll('[data-open-booking-modal]').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();

        const bookingPanel = document.getElementById('booking-panel');
        if (bookingPanel) {
          bookingPanel.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }

        const bookingModalEl = document.getElementById('bookingModal');
        if (bookingModalEl && window.bootstrap && window.bootstrap.Modal) {
          const modal = window.bootstrap.Modal.getOrCreateInstance(bookingModalEl);
          modal.show();
        }
      });
    });
  </script>
  <script src="js/app.js?v=20260503-0051"></script>
</body>

</html>