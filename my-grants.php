<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "config/db.php";
require_once __DIR__ . "/includes/audit.php";
audit_init($conn);

$sessionUserID = isset($_SESSION["vlms_user_id"]) ? (int)$_SESSION["vlms_user_id"] : 0;
$requestedUserID = isset($_GET["user_id"]) ? (int)$_GET["user_id"] : 0;
$userID = $requestedUserID > 0 ? $requestedUserID : $sessionUserID;

$grants = [];
$errorMessage = "";

if ($userID > 0) {
    $sql = "SELECT grantID, userID, balance, expiryDate, name, status
            FROM grants
            WHERE userID = ?
            ORDER BY expiryDate ASC, grantID DESC";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $grants[] = $row;
            }
        } else {
            $errorMessage = "Failed to load grants from database.";
        }

        $stmt->close();
    } else {
        $errorMessage = "Failed to prepare grants query.";
    }
} else {
    $errorMessage = "Please login first.";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Grants | Virtual Lab</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>
  <div class="page-wrapper">
    <div class="container py-3">
      <div class="topbar p-3 mb-3 d-flex justify-content-between align-items-center">
        <div>
          <h1 class="h4 mb-1">My Grants</h1>
          <small class="text-secondary">Your grants from the database</small>
        </div>
        <a href="dashboard-user.html" class="btn btn-outline-primary btn-outline-soft">
          <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
      </div>

      <div class="card-soft p-3">
        <?php if ($errorMessage !== "") { ?>
          <div class="alert alert-warning mb-0"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php } elseif (count($grants) === 0) { ?>
          <div class="alert alert-secondary mb-0">Grant not found.</div>
        <?php } else { ?>
          <div class="table-responsive">
            <table class="table table-striped mb-0">
              <thead>
                <tr>
                  <th>Grant ID</th>
                  <th>User ID</th>
                  <th>Name</th>
                  <th>Balance</th>
                  <th>Expiry Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($grants as $grant) {
                  $gStat = strtolower((string)($grant["status"] ?? "active"));
                ?>
                  <tr>
                    <td><?php echo htmlspecialchars((string)$grant["grantID"]); ?></td>
                    <td><?php echo htmlspecialchars((string)$grant["userID"]); ?></td>
                    <td><?php echo htmlspecialchars((string)$grant["name"]); ?></td>
                    <td>$<?php echo number_format((float)$grant["balance"], 2); ?></td>
                    <td><?php echo htmlspecialchars((string)$grant["expiryDate"]); ?></td>
                    <td>
                      <?php if ($gStat === "expired") { ?>
                        <span class="badge text-bg-secondary">expired</span>
                      <?php } else { ?>
                        <span class="badge text-bg-success">active</span>
                      <?php } ?>
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
