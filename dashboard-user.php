
<?php 

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit;
}

$currentUserId = $_SESSION["user_id"];

require_once "config/db.php";
require_once "models/equipment.php";
require_once "models/reservation.php";

$reservation = new Reservation($conn);
$equipment = new Equipment($conn);

$result = $equipment->getAll();
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
              <a class="nav-link active" href="dashboard-user.html"><i class="bi bi-house me-2"></i>Dashboard</a>
              <a class="nav-link" href="profile.php?from=user" data-profile-link><i class="bi bi-person me-2"></i>Profile</a>
              <a class="nav-link" href="#booking-panel"><i class="bi bi-calendar2-check me-2"></i>Booking Panel</a>
              <a class="nav-link" href="#session-panel"><i class="bi bi-stopwatch me-2"></i>Session Panel</a>
              <a class="nav-link" href="my-grants.php"><i class="bi bi-cash-coin me-2"></i>Grants Panel</a>
              <a class="nav-link" href="login.html"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
            </nav>
          </div>
        </aside>

        <section class="col-lg-9 col-xl-10">
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
                  <a href="profile.php?from=user" data-profile-link class="btn btn-outline-primary btn-outline-soft btn-sm">Open Full Profile</a>
                </div>
                <div class="row g-2">
                  <div class="col-md-3"><span class="muted-label">Full Name:</span>
                    <div id="fullName" class="fullName">Loading...</div>
                  </div>
                  <div class="col-md-3"><span class="muted-label">Phone:</span>
                    <div class="phoneNumber">Loading...</div>
                  </div>
                  <div class="col-md-3"><span class="muted-label">Role:</span>
                    <div class="role">Loading...</div>
                  </div>
                  <div class="col-md-3"><span class="muted-label">User ID:</span>
                    <div class="userID">Loading...</div>
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
                      <tr>
                        <td>B-501</td>
                        <td>Electron Microscope</td>
                        <td>2026-04-26</td>
                        <td>09:00</td>
                        <td>11:00</td>
                        <td><span class="badge text-bg-success">ongoing</span></td>
                      </tr>
                      <tr>
                        <td>B-615</td>
                        <td>Spectrometer</td>
                        <td>2026-04-28</td>
                        <td>10:00</td>
                        <td>12:00</td>
                        <td><span class="badge text-bg-primary">ready</span></td>
                      </tr>
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
      <form id="bookingForm">
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
              <input name = "resDate" type="date" class="form-control" />
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
              <input type="time" name = "endTime" class="form-control" id="bookingEndTime" />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary btn-outline-soft" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-gradient" id="confirmBookingBtn">Confirm Booking</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/app.js?v=20260501-1026"></script>
  <script>
    const bookingStartTime = document.getElementById("bookingStartTime");
    const bookingEndTime = document.getElementById("bookingEndTime");
    const confirmBookingBtn = document.getElementById("confirmBookingBtn");
    const hourlyBookingRate = 15;
    const defaultConfirmLabel = "Confirm Booking";

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
    }

    bookingStartTime.addEventListener("input", updateBookingPriceLabel);
    bookingEndTime.addEventListener("input", updateBookingPriceLabel);
  </script>
  <script src="js/app.js"></script>
</body>

</html>