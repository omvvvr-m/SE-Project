<?php

require_once __DIR__ . "/includes/require_admin.php";
require_once "config/db.php";
require_once __DIR__ . "/includes/audit.php";
audit_init($conn);
require_once "models/grant.php";

$grant = new Grant($conn);
$result = $grant->getAll();
$minExpiryDate = date("Y-m-d");
$usersResult = $conn->query("SELECT userID, fname, lname FROM users ORDER BY userID ASC");
$users = [];
if ($usersResult) {
  while ($u = $usersResult->fetch_assoc()) {
    $users[] = $u;
  }
}

?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Grants Management | Virtual Lab</title>
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
        <h1 class="h4 mb-0">Grants Management Panel</h1>
        <div class="d-flex gap-2">
          <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#grantModal">
            <i class="bi bi-plus-lg me-1"></i>New Grant
          </button>
          <a href="dashboard-admin.html" class="btn btn-outline-primary btn-outline-soft">Back</a>
        </div>
      </div>

      <?php if (isset($_GET["error"])) { ?>
        <div class="alert alert-danger" role="alert">
          <?php echo htmlspecialchars($_GET["error"]); ?>
        </div>
      <?php } ?>
      <?php if (isset($_GET["success"])) { ?>
        <div class="alert alert-success" role="alert">
          Grant saved successfully.
        </div>
      <?php } ?>

      <div class="table-wrapper">
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>Grant ID</th>
                <th>User ID</th>
                <th>Balance</th>
                <th>Expiry Date</th>
                <th>Status</th>
                <th>Name</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()) { ?>
                <?php
                $grantID = $row["grantID"] ?? $row["grantId"] ?? "";
                $userID = $row["userID"] ?? "";
                $balance = $row["balance"] ?? 0;
                $expiryDate = $row["expiryDate"] ?? "";
                $grantName = $row["name"] ?? "";
                $grantStatus = strtolower((string)($row["status"] ?? "active"));
                ?>
                <tr>
                  <td><?php echo htmlspecialchars((string)$grantID); ?></td>
                  <td><?php echo htmlspecialchars((string)$userID); ?></td>
                  <td>$<?php echo number_format((float)$balance, 2); ?></td>
                  <td><?php echo htmlspecialchars((string)$expiryDate); ?></td>
                  <td>
                    <?php if ($grantStatus === "expired") { ?>
                      <span class="badge text-bg-secondary">expired</span>
                    <?php } else { ?>
                      <span class="badge text-bg-success">active</span>
                    <?php } ?>
                  </td>
                  <td><?php echo htmlspecialchars((string)$grantName); ?></td>
                  <td>
                    <button
                      class="btn btn-sm btn-outline-primary edit-grant-btn"
                      data-bs-toggle="modal"
                      data-bs-target="#grantModal"
                      data-grant-id="<?php echo htmlspecialchars((string)$grantID); ?>"
                      data-user-id="<?php echo htmlspecialchars((string)$userID); ?>"
                      data-balance="<?php echo htmlspecialchars((string)$balance); ?>"
                      data-expiry-date="<?php echo htmlspecialchars((string)$expiryDate); ?>"
                      data-name="<?php echo htmlspecialchars((string)$grantName); ?>">
                      Edit
                    </button>
                    <a href="models/grant.php?delete_id=<?php echo urlencode((string)$grantID); ?>" onclick="return confirm('Delete this grant?')" class="btn btn-sm btn-outline-danger">Delete</a>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="grantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5">Add / Edit Grant</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" id="grantForm">
          <div class="modal-body">
            <div class="row g-3">
              <input type="hidden" name="grant_id" id="grant_id" />

              <div class="col-12">
                <label for="user_id" class="form-label mb-1">User ID</label>
                <select name="user_id" id="user_id" class="form-select" required>
                  <option value="">Select user ID</option>
                  <?php foreach ($users as $u) { ?>
                    <option value="<?php echo htmlspecialchars((string)$u["userID"]); ?>">
                      <?php
                      $optionText = "ID: " . $u["userID"];
                      if (!empty($u["fname"]) || !empty($u["lname"])) {
                        $optionText .= " - " . trim(($u["fname"] ?? "") . " " . ($u["lname"] ?? ""));
                      }
                      echo htmlspecialchars($optionText);
                      ?>
                    </option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-12">
                <label for="balance" class="form-label mb-1">Balance</label>
                <input type="number" step="0.01" min="0" name="balance" id="balance" class="form-control" placeholder="Balance" required />
              </div>
              <div class="col-12">
                <label for="expiry_date" class="form-label mb-1">Expiry Date</label>
                <input
                  type="date"
                  name="expiry_date"
                  id="expiry_date"
                  class="form-control"
                  min="<?php echo htmlspecialchars($minExpiryDate); ?>"
                  required />
                <div class="form-text">Must be today or a future date.</div>
              </div>
              <div class="col-12">
                <label for="grant_name" class="form-label mb-1">Grant Name</label>
                <input name="grant_name" id="grant_name" class="form-control" placeholder="Grant name" required />
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary btn-outline-soft" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-gradient" id="grantSubmitBtn">Save Grant</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const grantModal = document.getElementById("grantModal");
    const modalTitle = grantModal.querySelector(".modal-title");
    const grantForm = document.getElementById("grantForm");
    const submitBtn = document.getElementById("grantSubmitBtn");

    const grantIDInput = document.getElementById("grant_id");
    const userIDInput = document.getElementById("user_id");
    const balanceInput = document.getElementById("balance");
    const expiryDateInput = document.getElementById("expiry_date");
    const grantNameInput = document.getElementById("grant_name");
    const minExpiry = "<?php echo htmlspecialchars($minExpiryDate); ?>";

    grantModal.addEventListener("show.bs.modal", function(event) {
      const triggerButton = event.relatedTarget;
      const isEdit = triggerButton && triggerButton.classList.contains("edit-grant-btn");

      expiryDateInput.min = minExpiry;

      if (isEdit) {
        modalTitle.textContent = "Edit Grant";
        submitBtn.textContent = "Update Grant";
        grantIDInput.value = triggerButton.getAttribute("data-grant-id");
        userIDInput.value = triggerButton.getAttribute("data-user-id");
        balanceInput.value = triggerButton.getAttribute("data-balance");
        expiryDateInput.value = triggerButton.getAttribute("data-expiry-date");
        grantNameInput.value = triggerButton.getAttribute("data-name");
      } else {
        modalTitle.textContent = "Add Grant";
        submitBtn.textContent = "Save Grant";
        grantForm.reset();
        grantIDInput.value = "";
        expiryDateInput.min = minExpiry;
      }
    });
  </script>
</body>

</html>
