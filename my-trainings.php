<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit;
}
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/training.php";

$userID = (int)$_SESSION["user_id"];
$rows = [];
$res = $conn->query(
    "SELECT t.eqID, t.training_title, t.passed_at, t.expires_at, e.eqName
     FROM user_training_records t
     LEFT JOIN equipments e ON e.eqID = t.eqID
     WHERE t.userID = $userID
     ORDER BY t.passed_at DESC"
);
if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Trainings | Virtual Lab</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>
  <div class="page-wrapper">
    <div class="container py-3">
      <div class="topbar p-3 mb-3 d-flex justify-content-between align-items-center">
        <h1 class="h5 mb-0">My Trainings</h1>
        <a href="dashboard-user.php" class="btn btn-outline-primary btn-outline-soft btn-sm">Back</a>
      </div>
      <div class="card-soft p-3">
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Equipment</th><th>Training</th><th>Passed At</th><th>Expires At</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (count($rows) === 0) { ?>
                <tr><td colspan="5" class="text-secondary">No trainings completed yet.</td></tr>
              <?php } else foreach ($rows as $r) { ?>
                <?php
                $expiresAt = (string)($r["expires_at"] ?? "");
                $isExpired = $expiresAt !== "" && strtotime($expiresAt) <= time();
                ?>
                <tr>
                  <td><?php echo htmlspecialchars((string)$r["eqID"] . " - " . (string)($r["eqName"] ?? "Unknown")); ?></td>
                  <td><?php echo htmlspecialchars((string)($r["training_title"] ?? "")); ?></td>
                  <td><?php echo htmlspecialchars((string)$r["passed_at"]); ?></td>
                  <td><?php echo htmlspecialchars($expiresAt); ?></td>
                  <td>
                    <?php if ($isExpired) { ?>
                      <span class="badge text-bg-secondary">Expired</span>
                    <?php } else { ?>
                      <span class="badge text-bg-success">Valid</span>
                    <?php } ?>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
