<?php

require_once "config/db.php";
require_once "models/reservation.php";

$reservation = new Reservation($conn);
$result = $reservation->getAll();

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
              <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                  <td><?php echo $row["userID"]; ?></td>
                  <td><?php echo $row["bookingID"]; ?></td>
                  <td><?php echo $row["startTime"]; ?></td>
                  <td><?php echo $row["endTime"]; ?></td>
                  <td><?php echo $row["equipmentID"]; ?></td>
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
                      data-booking-id="<?php echo htmlspecialchars($row["bookingID"]); ?>"
                      data-user-id="<?php echo htmlspecialchars($row["userID"]); ?>"
                      data-start-time="<?php echo htmlspecialchars($row["startTime"]); ?>"
                      data-end-time="<?php echo htmlspecialchars($row["endTime"]); ?>"
                      data-equipment-id="<?php echo htmlspecialchars($row["equipmentID"]); ?>"
                      data-status="<?php echo htmlspecialchars($row["status"]); ?>">
                      Edit
                    </button>
                    <a href="models/reservation.php?delete_id=<?php echo $row["bookingID"]; ?>" onclick="return confirm('Delete this reservation?')" class="btn btn-sm btn-outline-danger">Delete</a>
                  </td>
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
                <input name="user_id" id="user_id" class="form-control" placeholder="User ID" required />
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
        statusInput.value = triggerButton.getAttribute("data-status");
      } else {
        modalTitle.textContent = "Add Reservation";
        submitBtn.textContent = "Save Reservation";
        reservationForm.reset();
        bookingIDInput.value = "";
      }
    });
  </script>
</body>

</html>
