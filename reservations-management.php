<?php

require_once __DIR__ . "/includes/require_admin.php";

require_once "config/db.php";
require_once __DIR__ . "/includes/audit.php";
audit_init($conn);
require_once "models/reservation.php";

$reservation = new Reservation($conn);

if (isset($_GET["delete_id"]) && $_GET["delete_id"] !== "") {
  audit_event($conn, "reservation.admin_delete", [
    "reservationID" => (int)$_GET["delete_id"]
  ]);
  $reservation->removeReservation($_GET["delete_id"]);
  header("Location: reservations-management.php");
  exit();
}

if (
  isset($_POST["user_id"]) &&
  isset($_POST["start_time"]) &&
  isset($_POST["end_time"]) &&
  isset($_POST["equipment_id"]) &&
  isset($_POST["status"])
) {
  $reservationID = $_POST["booking_id"] ?? "";
  $userID = $_POST["user_id"];
  $startTime = $_POST["start_time"];
  $endTime = $_POST["end_time"];
  $equipmentID = $_POST["equipment_id"];
  $status = $_POST["status"];
  $grantIDPost = isset($_POST["grant_id"]) ? trim((string)$_POST["grant_id"]) : "";

  if ($reservationID !== "") {
    $grantForUpdate = $grantIDPost !== "" ? $grantIDPost : null;
    audit_event($conn, "reservation.admin_update", [
      "reservationID" => (int)$reservationID,
      "userID" => (int)$userID,
      "equipmentID" => (int)$equipmentID,
      "status" => (string)$status
    ]);
    $reservation->updateReservationFromAdmin($reservationID, $userID, $startTime, $endTime, $equipmentID, $status, $grantForUpdate);
  } else {
    $grantForInsert = $grantIDPost !== "" ? $grantIDPost : null;
    audit_event($conn, "reservation.admin_create", [
      "userID" => (int)$userID,
      "equipmentID" => (int)$equipmentID,
      "status" => (string)$status
    ]);
    $ok = $reservation->addReservationFromAdmin($userID, $startTime, $endTime, $equipmentID, $status, $grantForInsert);
    if (!$ok) {
      $_SESSION["res_admin_error"] = "No valid grant found for this user. Add a grant in Grants Management first, or pick an existing Grant ID.";
    }
  }

  header("Location: reservations-management.php");
  exit();
}

$result = $reservation->getAll();
$emergencyReports = $reservation->getEmergencyReports();
$supportRequests = $reservation->getSessionSupportRequests();
$users = [];
$usersResult = $conn->query("SELECT userID, fname, lname, username FROM users ORDER BY userID ASC");
if ($usersResult) {
  while ($userRow = $usersResult->fetch_assoc()) {
    $fullName = trim(($userRow["fname"] ?? "") . " " . ($userRow["lname"] ?? ""));
    if ($fullName === "") {
      $fullName = $userRow["username"] ?? "Unknown User";
    }
    $users[] = [
      "id" => $userRow["userID"],
      "name" => $fullName
    ];
  }
}

$grantsList = [];
$grantsQuery = $conn->query(
  "SELECT g.grantID, g.userID, g.balance, g.name
   FROM grants g
   ORDER BY g.userID ASC, g.grantID ASC"
);
if ($grantsQuery) {
  while ($gRow = $grantsQuery->fetch_assoc()) {
    $grantsList[] = $gRow;
  }
}

$adminResError = $_SESSION["res_admin_error"] ?? null;
unset($_SESSION["res_admin_error"]);

?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reservations Management | Virtual Lab</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet" />
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="css/style.css" />
</head>

<body>
  <div class="page-wrapper">
    <div class="container-fluid">
      <?php if (!empty($adminResError)) { ?>
        <div class="alert alert-warning py-2 mb-3"><?php echo htmlspecialchars($adminResError); ?></div>
      <?php } ?>
      <div class="topbar p-3 mb-3 d-flex justify-content-between align-items-center">
        <h1 class="h4 mb-0">Reservations Management Panel</h1>
        <div class="d-flex gap-2">
          <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#reservationModal">
            <i class="bi bi-plus-lg me-1"></i>New Reservation
          </button>
          <a href="dashboard-admin.html" class="btn btn-outline-primary btn-outline-soft">Back</a>
        </div>
      </div>

      <div class="table-wrapper">
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>User ID</th>
                <th>Booking ID</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Equipment ID</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()) {
                $reservationId = $row["bookingID"] ?? $row["resID"] ?? "";
                $equipmentId = $row["equipmentID"] ?? $row["eqID"] ?? "";
              ?>
                <tr>
                  <td><?php echo $row["userID"]; ?></td>
                  <td><?php echo htmlspecialchars($reservationId); ?></td>
                  <td><?php echo $row["startTime"]; ?></td>
                  <td><?php echo $row["endTime"]; ?></td>
                  <td><?php echo htmlspecialchars($equipmentId); ?></td>
                  <td>
                    <?php if ($row["status"] == "ongoing") { ?>
                      <span class="badge text-bg-success">ongoing</span>
                    <?php } elseif ($row["status"] == "terminated") { ?>
                      <span class="badge text-bg-secondary">terminated</span>
                    <?php } else { ?>
                      <span class="badge text-bg-primary">ready</span>
                    <?php } ?>
                  </td>
                  <td>
                    <button
                      class="btn btn-sm btn-outline-primary edit-reservation-btn"
                      data-bs-toggle="modal"
                      data-bs-target="#reservationModal"
                      data-booking-id="<?php echo htmlspecialchars($reservationId); ?>"
                      data-user-id="<?php echo htmlspecialchars($row["userID"]); ?>"
                      data-start-time="<?php echo htmlspecialchars($row["startTime"]); ?>"
                      data-end-time="<?php echo htmlspecialchars($row["endTime"]); ?>"
                      data-equipment-id="<?php echo htmlspecialchars($equipmentId); ?>"
                      data-grant-id="<?php echo htmlspecialchars($row["grantID"] ?? $row["grantid"] ?? $row["grant_id"] ?? ""); ?>"
                      data-status="<?php echo htmlspecialchars($row["status"]); ?>">
                      Edit
                    </button>
                    <a href="reservations-management.php?delete_id=<?php echo urlencode((string)$reservationId); ?>" onclick="return confirm('Delete this reservation?')" class="btn btn-sm btn-outline-danger">Delete</a>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="table-wrapper mt-4">
        <h2 class="h5 mb-3">Emergency Reports</h2>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>Report ID</th>
                <th>User ID</th>
                <th>Reservation ID</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Message</th>
                <th>Created At</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($emergencyReports && $emergencyReports->num_rows > 0) { ?>
                <?php while ($reportRow = $emergencyReports->fetch_assoc()) { ?>
                  <?php
                  $reportUserName = trim((string)($reportRow["fname"] ?? "") . " " . (string)($reportRow["lname"] ?? ""));
                  if ($reportUserName === "") $reportUserName = (string)($reportRow["username"] ?? "Unknown User");
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($reportRow["reportID"]); ?></td>
                    <td><?php echo htmlspecialchars((string)$reportRow["userID"] . " - " . $reportUserName); ?></td>
                    <td><?php echo htmlspecialchars($reportRow["resID"]); ?></td>
                    <td><?php echo htmlspecialchars($reportRow["startTime"]); ?></td>
                    <td><?php echo htmlspecialchars($reportRow["endTime"]); ?></td>
                    <td><?php echo htmlspecialchars($reportRow["message"]); ?></td>
                    <td><?php echo htmlspecialchars($reportRow["createdAt"]); ?></td>
                  </tr>
                <?php } ?>
              <?php } else { ?>
                <tr>
                  <td colspan="7" class="text-secondary">No emergency reports yet.</td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="table-wrapper mt-4">
        <h2 class="h5 mb-3">Session Supervision Requests</h2>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>Request ID</th>
                <th>User</th>
                <th>Reservation ID</th>
                <th>Description</th>
                <th>Created At</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($supportRequests && $supportRequests->num_rows > 0) { ?>
                <?php while ($supportRow = $supportRequests->fetch_assoc()) { ?>
                  <?php
                  $supportUserName = trim((string)($supportRow["fname"] ?? "") . " " . (string)($supportRow["lname"] ?? ""));
                  if ($supportUserName === "") $supportUserName = (string)($supportRow["username"] ?? "Unknown User");
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($supportRow["requestID"]); ?></td>
                    <td><?php echo htmlspecialchars((string)$supportRow["userID"] . " - " . $supportUserName); ?></td>
                    <td><?php echo htmlspecialchars($supportRow["resID"]); ?></td>
                    <td><?php echo htmlspecialchars($supportRow["message"]); ?></td>
                    <td><?php echo htmlspecialchars($supportRow["createdAt"]); ?></td>
                  </tr>
                <?php } ?>
              <?php } else { ?>
                <tr>
                  <td colspan="5" class="text-secondary">No supervision requests yet.</td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="reservationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5">Add / Edit Reservation</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" id="reservationForm">
          <div class="modal-body">
            <div class="row g-3">
              <input type="hidden" name="booking_id" id="booking_id" />

              <div class="col-12">
                <label for="user_id" class="form-label mb-1">User ID</label>
                <select name="user_id" id="user_id" class="form-select" required>
                  <option value="" disabled selected>Select User ID</option>
                  <?php foreach ($users as $userOption) { ?>
                    <option value="<?php echo htmlspecialchars($userOption["id"]); ?>">
                      <?php echo htmlspecialchars($userOption["id"] . " - " . $userOption["name"]); ?>
                    </option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-12">
                <label for="start_time" class="form-label mb-1">Start Time</label>
                <input type="datetime-local" name="start_time" id="start_time" class="form-control" required />
              </div>
              <div class="col-12">
                <label for="end_time" class="form-label mb-1">End Time</label>
                <input type="datetime-local" name="end_time" id="end_time" class="form-control" required />
              </div>
              <div class="col-12">
                <input name="equipment_id" id="equipment_id" class="form-control" placeholder="Equipment ID" required />
              </div>
              <div class="col-12">
                <label for="grant_id" class="form-label mb-1">Grant</label>
                <select name="grant_id" id="grant_id" class="form-select">
                  <option value="">Auto (first grant for user)</option>
                  <?php foreach ($grantsList as $g) {
                    $gid = $g["grantID"];
                    $label = $gid . " — User " . $g["userID"] . " — " . ($g["name"] ?? "") . " ($" . $g["balance"] . ")";
                  ?>
                    <option value="<?php echo htmlspecialchars((string)$gid); ?>"><?php echo htmlspecialchars($label); ?></option>
                  <?php } ?>
                </select>
                <div class="form-text">Required by database: reservation must reference a row in <code>grants</code>.</div>
              </div>
              <div class="col-12">
                <select name="status" id="status" class="form-select">
                  <option value="ready">ready</option>
                  <option value="ongoing">ongoing</option>
                  <option value="terminated">terminated</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary btn-outline-soft" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-gradient" id="reservationSubmitBtn">Save Reservation</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function toDateTimeLocalValue(dateTimeString) {
      if (!dateTimeString) return "";
      return dateTimeString.replace(" ", "T").slice(0, 16);
    }

    const reservationModal = document.getElementById("reservationModal");
    const modalTitle = reservationModal.querySelector(".modal-title");
    const reservationForm = document.getElementById("reservationForm");
    const submitBtn = document.getElementById("reservationSubmitBtn");

    const bookingIDInput = document.getElementById("booking_id");
    const userIDInput = document.getElementById("user_id");
    const startTimeInput = document.getElementById("start_time");
    const endTimeInput = document.getElementById("end_time");
    const equipmentIDInput = document.getElementById("equipment_id");
    const grantIDInput = document.getElementById("grant_id");
    const statusInput = document.getElementById("status");

    reservationModal.addEventListener("show.bs.modal", function(event) {
      const triggerButton = event.relatedTarget;
      const isEdit = triggerButton && triggerButton.classList.contains("edit-reservation-btn");

      if (isEdit) {
        modalTitle.textContent = "Edit Reservation";
        submitBtn.textContent = "Update Reservation";
        bookingIDInput.value = triggerButton.getAttribute("data-booking-id");
        userIDInput.value = triggerButton.getAttribute("data-user-id");
        startTimeInput.value = toDateTimeLocalValue(triggerButton.getAttribute("data-start-time"));
        endTimeInput.value = toDateTimeLocalValue(triggerButton.getAttribute("data-end-time"));
        equipmentIDInput.value = triggerButton.getAttribute("data-equipment-id");
        const g = triggerButton.getAttribute("data-grant-id") || "";
        grantIDInput.value = g;
        statusInput.value = triggerButton.getAttribute("data-status");
      } else {
        modalTitle.textContent = "Add Reservation";
        submitBtn.textContent = "Save Reservation";
        reservationForm.reset();
        bookingIDInput.value = "";
        grantIDInput.value = "";
      }
    });
  </script>
</body>

</html>