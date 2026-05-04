<?php
require_once __DIR__ . "/includes/require_admin.php";
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/audit.php";
require_once __DIR__ . "/includes/training.php";

audit_init($conn);

$msg = null;
$error = null;
$adminID = isset($_SESSION["vlms_user_id"]) ? (int)$_SESSION["vlms_user_id"] : null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string)($_POST["training_action"] ?? ""));
    if ($action === "set_requirement") {
        $eqID = (int)($_POST["equipment_id"] ?? 0);
        $required = isset($_POST["is_required"]) && $_POST["is_required"] === "1";
        $title = trim((string)($_POST["training_title"] ?? ""));
        if ($eqID <= 0) {
            $error = "Choose valid equipment.";
        } else {
            if (training_set_requirement($conn, $eqID, $required, $title, $adminID)) {
                audit_event($conn, "training.requirement_update", [
                    "equipmentID" => $eqID,
                    "required" => $required ? 1 : 0,
                    "trainingTitle" => $title
                ]);
                $msg = "Training requirement updated.";
            } else {
                $error = "Failed to update requirement.";
            }
        }
    } elseif ($action === "mark_passed") {
        $userID = (int)($_POST["user_id"] ?? 0);
        $eqID = (int)($_POST["equipment_id"] ?? 0);
        $title = trim((string)($_POST["training_title"] ?? ""));
        if ($userID <= 0 || $eqID <= 0) {
            $error = "Choose valid user and equipment.";
        } else {
            if (training_mark_user_passed($conn, $userID, $eqID, $title, $adminID)) {
                audit_event($conn, "training.user_passed", [
                    "userID" => $userID,
                    "equipmentID" => $eqID,
                    "trainingTitle" => $title
                ]);
                $msg = "User training marked as passed.";
            } else {
                $error = "Failed to mark training.";
            }
        }
    }
}

$equipments = [];
$eqRes = $conn->query("SELECT eqID, eqName FROM equipments ORDER BY eqID ASC");
if ($eqRes) while ($r = $eqRes->fetch_assoc()) $equipments[] = $r;

$users = [];
$uRes = $conn->query("SELECT userID, fname, lname, username, role FROM users ORDER BY userID ASC");
if ($uRes) while ($r = $uRes->fetch_assoc()) $users[] = $r;

$requirements = [];
$reqRes = $conn->query(
    "SELECT r.eqID, r.is_required, r.training_title, r.updated_at, e.eqName
     FROM equipment_training_requirements r
     LEFT JOIN equipments e ON e.eqID = r.eqID
     ORDER BY r.eqID ASC"
);
if ($reqRes) while ($r = $reqRes->fetch_assoc()) $requirements[] = $r;

$passes = [];
$passRes = $conn->query(
    "SELECT t.userID, t.eqID, t.training_title, t.passed_at, t.expires_at, u.fname, u.lname, u.username, e.eqName
     FROM user_training_records t
     LEFT JOIN users u ON u.userID = t.userID
     LEFT JOIN equipments e ON e.eqID = t.eqID
     ORDER BY t.passed_at DESC"
);
if ($passRes) while ($r = $passRes->fetch_assoc()) $passes[] = $r;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Training Logbook | Virtual Lab</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>
  <div class="page-wrapper">
    <div class="container py-3">
      <div class="topbar p-3 mb-3 d-flex justify-content-between align-items-center">
        <h1 class="h5 mb-0">Training Logbook</h1>
        <a href="dashboard-admin.html" class="btn btn-outline-primary btn-outline-soft btn-sm">Back</a>
      </div>
      <?php if ($msg) { ?><div class="alert alert-success py-2"><?php echo htmlspecialchars($msg); ?></div><?php } ?>
      <?php if ($error) { ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div><?php } ?>

      <div class="card-soft p-3 mb-3">
        <h2 class="h6">Set Equipment Training Requirement</h2>
        <form method="POST" class="row g-2 align-items-end">
          <input type="hidden" name="training_action" value="set_requirement" />
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
            <label class="form-label mb-1">Training Title</label>
            <input name="training_title" class="form-control" placeholder="e.g. Microscope Safety" />
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Required?</label>
            <select name="is_required" class="form-select">
              <option value="1">Yes (block booking)</option>
              <option value="0">No</option>
            </select>
          </div>
          <div class="col-md-2 d-grid"><button class="btn btn-gradient">Save</button></div>
        </form>
      </div>

      <div class="card-soft p-3 mb-3">
        <h2 class="h6">Mark User Passed Training</h2>
        <form method="POST" class="row g-2 align-items-end">
          <input type="hidden" name="training_action" value="mark_passed" />
          <div class="col-md-4">
            <label class="form-label mb-1">User</label>
            <select name="user_id" class="form-select" required>
              <option value="">Select user</option>
              <?php foreach ($users as $u) {
                $nm = trim((string)$u["fname"] . " " . (string)$u["lname"]);
                if ($nm === "") $nm = (string)$u["username"];
              ?>
                <option value="<?php echo htmlspecialchars((string)$u["userID"]); ?>">
                  <?php echo htmlspecialchars((string)$u["userID"] . " - " . $nm . " (" . (string)$u["role"] . ")"); ?>
                </option>
              <?php } ?>
            </select>
          </div>
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
          <div class="col-md-2">
            <label class="form-label mb-1">Training Title</label>
            <input name="training_title" class="form-control" placeholder="Optional" />
          </div>
          <div class="col-md-2 d-grid"><button class="btn btn-gradient">Mark Passed</button></div>
        </form>
      </div>

      <div class="card-soft p-3 mb-3">
        <h2 class="h6">Equipment Requirements</h2>
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Equipment</th><th>Required</th><th>Training</th><th>Updated</th></tr></thead>
            <tbody>
              <?php if (count($requirements) === 0) { ?>
                <tr><td colspan="4" class="text-secondary">No requirements configured yet.</td></tr>
              <?php } else foreach ($requirements as $r) { ?>
                <tr>
                  <td><?php echo htmlspecialchars((string)$r["eqID"] . " - " . (string)($r["eqName"] ?? "Unknown")); ?></td>
                  <td><?php echo ((int)$r["is_required"] === 1) ? "Yes" : "No"; ?></td>
                  <td><?php echo htmlspecialchars((string)($r["training_title"] ?? "")); ?></td>
                  <td><?php echo htmlspecialchars((string)$r["updated_at"]); ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card-soft p-3">
        <h2 class="h6">Training Pass Records</h2>
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>User</th><th>Equipment</th><th>Training</th><th>Passed At</th><th>Expires At</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (count($passes) === 0) { ?>
                <tr><td colspan="6" class="text-secondary">No pass records yet.</td></tr>
              <?php } else foreach ($passes as $p) {
                $nm = trim((string)$p["fname"] . " " . (string)$p["lname"]);
                if ($nm === "") $nm = (string)$p["username"];
                $expiresAt = (string)($p["expires_at"] ?? "");
                $isExpired = $expiresAt !== "" && strtotime($expiresAt) <= time();
              ?>
                <tr>
                  <td><?php echo htmlspecialchars((string)$p["userID"] . " - " . $nm); ?></td>
                  <td><?php echo htmlspecialchars((string)$p["eqID"] . " - " . (string)($p["eqName"] ?? "Unknown")); ?></td>
                  <td><?php echo htmlspecialchars((string)($p["training_title"] ?? "")); ?></td>
                  <td><?php echo htmlspecialchars((string)$p["passed_at"]); ?></td>
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
