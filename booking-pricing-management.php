<?php
require_once __DIR__ . "/includes/require_admin.php";
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/audit.php";
require_once __DIR__ . "/includes/booking_rate.php";

audit_init($conn);
$msg = null;
$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = (string)($_POST["pricing_action"] ?? "global");
    if ($action === "user_override") {
        $targetUserID = (int)($_POST["target_user_id"] ?? 0);
        $newUserRateRaw = trim((string)($_POST["target_hourly_rate"] ?? ""));
        if ($targetUserID <= 0) {
            $error = "Please choose a valid user.";
        } elseif ($newUserRateRaw === "" || !is_numeric($newUserRateRaw)) {
            $error = "Please enter a valid user rate.";
        } else {
            $newUserRate = (float)$newUserRateRaw;
            if ($newUserRate < 0) {
                $error = "User rate cannot be negative.";
            } else {
                $oldUserRate = booking_get_user_hourly_rate($conn, $targetUserID);
                if (booking_set_user_hourly_rate($conn, $targetUserID, $newUserRate)) {
                    audit_event($conn, "pricing.user_rate_update", [
                        "userID" => (int)$targetUserID,
                        "oldRate" => $oldUserRate,
                        "newRate" => (float)$newUserRate
                    ]);
                    $msg = "User-specific hourly rate updated successfully.";
                } else {
                    $error = "Failed to update user-specific rate.";
                }
            }
        }
    } elseif ($action === "clear_user_override") {
        $targetUserID = (int)($_POST["target_user_id"] ?? 0);
        if ($targetUserID <= 0) {
            $error = "Please choose a valid user.";
        } else {
            $oldUserRate = booking_get_user_hourly_rate($conn, $targetUserID);
            if (booking_clear_user_hourly_rate($conn, $targetUserID)) {
                audit_event($conn, "pricing.user_rate_clear", [
                    "userID" => (int)$targetUserID,
                    "oldRate" => $oldUserRate
                ]);
                $msg = "User-specific rate removed. Global rate will apply.";
            } else {
                $error = "Failed to remove user-specific rate.";
            }
        }
    }
}

$users = [];
$usersRes = $conn->query("SELECT userID, fname, lname, username, role FROM users ORDER BY userID ASC");
if ($usersRes) {
    while ($u = $usersRes->fetch_assoc()) {
        $users[] = $u;
    }
}
$userRateRows = [];
$overridesRes = $conn->query(
    "SELECT ur.user_id, ur.hourly_rate, ur.updated_at, u.fname, u.lname, u.username, u.role
     FROM booking_user_rates ur
     LEFT JOIN users u ON u.userID = ur.user_id
     ORDER BY ur.user_id ASC"
);
if ($overridesRes) {
    while ($r = $overridesRes->fetch_assoc()) {
        $userRateRows[] = $r;
    }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Booking Pricing | Virtual Lab</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
</head>

<body>
  <div class="page-wrapper">
    <div class="container py-3">
      <div class="topbar p-3 mb-3 d-flex justify-content-between align-items-center">
        <h1 class="h5 mb-0">Booking Pricing Management</h1>
        <a href="dashboard-admin.html" class="btn btn-outline-primary btn-outline-soft btn-sm">Back</a>
      </div>

      <?php if ($msg) { ?>
        <div class="alert alert-success py-2"><?php echo htmlspecialchars($msg); ?></div>
      <?php } ?>
      <?php if ($error) { ?>
        <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
      <?php } ?>

      <div class="card-soft p-4">
        <p class="text-secondary mb-3">Set custom hourly rate for a specific user only.</p>
        <form method="POST" class="row g-3 align-items-end">
          <input type="hidden" name="pricing_action" value="user_override" />
          <div class="col-12 col-md-5">
            <label class="form-label mb-1">Target User</label>
            <select name="target_user_id" class="form-select" required>
              <option value="">Select user</option>
              <?php foreach ($users as $u) {
                $display = trim((string)($u["fname"] ?? "") . " " . (string)($u["lname"] ?? ""));
                if ($display === "") $display = (string)($u["username"] ?? "User");
                $role = (string)($u["role"] ?? "");
              ?>
                <option value="<?php echo htmlspecialchars((string)$u["userID"]); ?>">
                  <?php echo htmlspecialchars((string)$u["userID"] . " - " . $display . " (" . $role . ")"); ?>
                </option>
              <?php } ?>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label mb-1">Custom Hourly Rate (USD)</label>
            <input type="number" name="target_hourly_rate" class="form-control" min="0" step="0.01" required />
          </div>
          <div class="col-12 col-md-2 d-grid">
            <button class="btn btn-gradient">Save User Rate</button>
          </div>
        </form>
      </div>

      <div class="card-soft p-4 mt-3">
        <h2 class="h6 mb-3">Current User-Specific Rates</h2>
        <?php if (count($userRateRows) === 0) { ?>
          <div class="text-secondary small">No user-specific rates yet.</div>
        <?php } else { ?>
          <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Role</th>
                  <th>Hourly Rate</th>
                  <th>Updated At</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($userRateRows as $row) {
                  $display = trim((string)($row["fname"] ?? "") . " " . (string)($row["lname"] ?? ""));
                  if ($display === "") $display = (string)($row["username"] ?? "User");
                ?>
                  <tr>
                    <td><?php echo htmlspecialchars((string)$row["user_id"] . " - " . $display); ?></td>
                    <td><?php echo htmlspecialchars((string)($row["role"] ?? "-")); ?></td>
                    <td>$<?php echo htmlspecialchars(number_format((float)$row["hourly_rate"], 2, ".", "")); ?></td>
                    <td><?php echo htmlspecialchars((string)$row["updated_at"]); ?></td>
                    <td>
                      <form method="POST" class="m-0">
                        <input type="hidden" name="pricing_action" value="clear_user_override" />
                        <input type="hidden" name="target_user_id" value="<?php echo htmlspecialchars((string)$row["user_id"]); ?>" />
                        <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove custom rate for this user?');">Remove</button>
                      </form>
                    </td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</body>

</html>
