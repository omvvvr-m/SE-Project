<?php
require_once __DIR__ . "/includes/require_admin.php";
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/audit.php";
require_once __DIR__ . "/includes/safety.php";

audit_init($conn);
safety_ensure_table($conn);

$msg = null;
$error = null;
$adminID = isset($_SESSION["vlms_user_id"]) ? (int)$_SESSION["vlms_user_id"] : null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $eqID = (int)($_POST["equipment_id"] ?? 0);
    $required = (($_POST["is_required"] ?? "0") === "1");
    $reason = trim((string)($_POST["reason"] ?? ""));
    if ($eqID <= 0) {
        $error = "Choose valid equipment.";
    } elseif ($required && $reason === "") {
        $error = "Please provide safety reason when requirement is enabled.";
    } else {
        if (safety_set_requirement($conn, $eqID, $required, $reason, $adminID)) {
            audit_event($conn, "safety.requirement_update", [
                "equipmentID" => $eqID,
                "required" => $required ? 1 : 0,
                "reason" => $reason
            ]);
            $msg = "Safety requirement updated.";
        } else {
            $error = "Failed to update safety requirement.";
        }
    }
}

$equipments = [];
$eqRes = $conn->query("SELECT eqID, eqName FROM equipments ORDER BY eqID ASC");
if ($eqRes) while ($r = $eqRes->fetch_assoc()) $equipments[] = $r;

$rows = [];
$res = $conn->query(
    "SELECT s.eqID, s.is_required, s.reason, s.updated_at, e.eqName
     FROM equipment_safety_requirements s
     LEFT JOIN equipments e ON e.eqID = s.eqID
     ORDER BY s.eqID ASC"
);
if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>View Safety Requirement | Virtual Lab</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>
  <div class="page-wrapper">
    <div class="container py-3">
      <div class="topbar p-3 mb-3 d-flex justify-content-between align-items-center">
        <h1 class="h5 mb-0">View Safety Requirement</h1>
        <a href="dashboard-admin.html" class="btn btn-outline-primary btn-outline-soft btn-sm">Back</a>
      </div>
      <?php if ($msg) { ?><div class="alert alert-success py-2"><?php echo htmlspecialchars($msg); ?></div><?php } ?>
      <?php if ($error) { ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div><?php } ?>

      <div class="card-soft p-3 mb-3">
        <h2 class="h6">Set Safety Requirement Per Equipment</h2>
        <form method="POST" class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label mb-1">Equipment</label>
            <select name="equipment_id" class="form-select" required>
              <option value="">Select equipment</option>
              <?php foreach ($equipments as $e) { ?>
                <option value="<?php echo htmlspecialchars((string)$e["eqID"]); ?>">
                  <?php echo htmlspecialchars((string)$e["eqID"] . " - " . (string)$e["eqName"]); ?>
                </option>
              <?php } ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Safety Required?</label>
            <select name="is_required" class="form-select" required>
              <option value="1">Yes (mandatory)</option>
              <option value="0">No</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Reason</label>
            <input name="reason" class="form-control" placeholder="Why safety is mandatory" />
          </div>
          <div class="col-md-2 d-grid"><button class="btn btn-gradient">Save</button></div>
        </form>
      </div>

      <div class="card-soft p-3">
        <h2 class="h6">Current Safety Rules</h2>
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Equipment</th><th>Required</th><th>Reason</th><th>Updated</th></tr></thead>
            <tbody>
              <?php if (count($rows) === 0) { ?>
                <tr><td colspan="4" class="text-secondary">No safety rules configured yet.</td></tr>
              <?php } else foreach ($rows as $r) { ?>
                <tr>
                  <td><?php echo htmlspecialchars((string)$r["eqID"] . " - " . (string)($r["eqName"] ?? "Unknown")); ?></td>
                  <td><?php echo ((int)$r["is_required"] === 1) ? "Yes" : "No"; ?></td>
                  <td><?php echo htmlspecialchars((string)($r["reason"] ?? "")); ?></td>
                  <td><?php echo htmlspecialchars((string)$r["updated_at"]); ?></td>
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
