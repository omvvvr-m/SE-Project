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

$result = $equipment->getAll();
$reservResult = $reservation->getAllForUser();
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
              <a class="nav-link" href="#booking-panel"><i class="bi bi-calendar2-check me-2"></i>Booking Panel</a>
              <a class="nav-link" href="#session-panel"><i class="bi bi-stopwatch me-2"></i>Session Panel</a>
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
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="toggleSession" />
              <label class="form-check-label" for="toggleSession">Simulate ongoing session</label>
            </div>
          </div>

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
                      </tr>
                    </thead>
                    <tbody>
                      <?php while ($row = $reservResult->fetch_assoc()) { ?>
                        <tr>
                          <td><?php echo $row['resID']; ?></td>
                          <td><?php
                              $sql = "SELECT eqName FROM equipments where eqID = " .
                                $row['eqID'];
                              $res = $conn->query($sql);
                              $resu = $res->fetch_assoc();
                              echo $resu['eqName'];
                              ?></td>
                          <td><?php echo $row['resDate']; ?></td>
                          <td><?php echo $row['startTime']; ?></td>
                          <td><?php echo $row['endTime']; ?></td>
                          <td> <?php if ($row['status'] == 'ongoing') { ?>
                              <span class="badge text-bg-success">ongoing</span>
                            <?php } else if ($row['status'] == 'terminated') { ?>
                              <span class="badge text-bg-secondary">terminated</span>
                            <?php } else { ?>
                              <span class="badge text-bg-primary">ready</span>
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
              <div id="noSessionPanel" class="card-soft p-3 h-100">
                <h2 class="panel-title">Session Panel</h2>
                <p class="text-secondary mb-0">No ongoing session currently</p>
              </div>
              <div id="sessionPanel" class="card-soft p-3 h-100 d-none">
                <h2 class="panel-title">Active Session</h2>
                <p class="mb-1"><span class="muted-label">Equipment:</span> Electron Microscope</p>
                <p class="mb-1"><span class="muted-label">Remaining Time:</span> 01:15:20</p>
                <p class="mb-1"><span class="muted-label">Status:</span> <span
                    class="status-badge bg-success-subtle text-success">Ongoing</span></p>
                <p class="mb-1"><span class="muted-label">Start:</span> 09:00 AM</p>
                <p class="mb-0"><span class="muted-label">End:</span> 11:00 AM</p>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
  <script src="js/app.js?v=20260501-2301"></script>
</body>

</html>