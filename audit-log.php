<?php
require_once __DIR__ . "/includes/require_admin.php";
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/audit.php";
audit_init($conn);

$actorType = trim((string)($_GET["actor_type"] ?? ""));
$userId = trim((string)($_GET["user_id"] ?? ""));
$from = trim((string)($_GET["from"] ?? ""));
$to = trim((string)($_GET["to"] ?? ""));
$page = max(1, (int)($_GET["page"] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];
$types = "";

// Show only business actions in sentence format.
$where[] = "(action LIKE 'reservation.%' OR action LIKE 'grant.%' OR action LIKE 'session.%' OR action LIKE 'auth.%' OR action LIKE 'user.%' OR action LIKE 'equipment.%' OR action LIKE 'profile.%' OR action LIKE 'pricing.%' OR action LIKE 'training.%' OR action LIKE 'safety.%')";
if ($actorType !== "") {
  if (in_array($actorType, ["user", "guest"], true)) {
    $where[] = "actor_type = ?";
    $params[] = $actorType;
    $types .= "s";
  } elseif ($actorType === "admin") {
    $where[] = "actor_type = 'user' AND LOWER(COALESCE(actor_role, '')) = 'admin'";
  }
}
if ($userId !== "" && ctype_digit($userId)) {
  $where[] = "actor_user_id = ?";
  $params[] = (int)$userId;
  $types .= "i";
}
if ($from !== "") {
  $where[] = "created_at >= ?";
  $params[] = $from . " 00:00:00";
  $types .= "s";
}
if ($to !== "") {
  $where[] = "created_at <= ?";
  $params[] = $to . " 23:59:59";
  $types .= "s";
}

$whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

$countSql = "SELECT COUNT(*) AS c FROM audit_logs $whereSql";
$countStmt = $conn->prepare($countSql);
$total = 0;
if ($countStmt) {
  if ($types !== "") {
    $countStmt->bind_param($types, ...$params);
  }
  $countStmt->execute();
  $countRes = $countStmt->get_result();
  if ($countRes && $row = $countRes->fetch_assoc()) {
    $total = (int)$row["c"];
  }
  $countStmt->close();
}

$sql = "SELECT l.id, l.created_at, l.actor_type, l.actor_user_id, l.actor_role, l.action, l.payload_json,
               u.username, u.fname, u.lname
        FROM audit_logs l
        LEFT JOIN users u ON u.userID = l.actor_user_id
        $whereSql
        ORDER BY l.id DESC
        LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$rows = [];
if ($stmt) {
  if ($types !== "") {
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $rows[] = $r;
    }
  }
  $stmt->close();
}

$totalPages = max(1, (int)ceil($total / $limit));
if ($page > $totalPages) $page = $totalPages;

function build_query(array $overrides = []): string
{
  $q = array_merge($_GET, $overrides);
  return http_build_query($q);
}

function actor_display_name(array $r): string
{
  if (($r["actor_type"] ?? "") === "guest" || empty($r["actor_user_id"])) {
    return "Guest";
  }
  $full = trim((string)($r["fname"] ?? "") . " " . (string)($r["lname"] ?? ""));
  if ($full !== "") return $full;
  if (!empty($r["username"])) return (string)$r["username"];
  return "User #" . (string)$r["actor_user_id"];
}

function action_to_sentence(array $r): string
{
  $time = (string)($r["created_at"] ?? "");
  $action = (string)($r["action"] ?? "");
  $name = actor_display_name($r);

  $payload = [];
  if (!empty($r["payload_json"])) {
    $decoded = json_decode((string)$r["payload_json"], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
      $payload = $decoded;
    }
  }

  $actorType = strtolower((string)($r["actor_type"] ?? "guest"));
  $actorLabel = "[Guest]";
  if ($actorType === "user") {
    $actorLabel = (strtolower((string)($r["actor_role"] ?? "")) === "admin") ? "[Admin]" : "[User]";
  }

  $actorId = !empty($r["actor_user_id"])
    ? ("[" . (int)$r["actor_user_id"] . " - " . $name . "]")
    : "[N/A - Guest]";
  $resID = $payload["resID"] ?? ($payload["reservationID"] ?? null);
  $eqID = $payload["eqID"] ?? ($payload["equipmentID"] ?? null);
  $targetUserID = $payload["userID"] ?? null;
  $grantID = $payload["grantID"] ?? null;
  $username = $payload["username"] ?? null;
  $eqName = $payload["eqName"] ?? null;
  $oldRate = $payload["oldRate"] ?? null;
  $newRate = $payload["newRate"] ?? null;
  $required = $payload["required"] ?? null;
  $trainingTitle = $payload["trainingTitle"] ?? null;
  $safetyFee = $payload["safetyFee"] ?? null;
  $reason = $payload["reason"] ?? null;

  $sentence = $actorLabel . " " . $actorId . " performed action [" . $action . "] at [" . $time . "]";
  switch ($action) {
    case "reservation.create":
      $booking = $resID !== null ? (int)$resID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " created a booking [" . $booking . "] at [" . $time . "]";
      if ($safetyFee !== null && (float)$safetyFee > 0) {
        $sentence .= " with safety fee [$" . number_format((float)$safetyFee, 2, ".", "") . "]";
      }
      break;
    case "reservation.admin_create":
      $booking = $resID !== null ? (int)$resID : "N/A";
      $userForBooking = $targetUserID !== null ? (int)$targetUserID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " created a booking [" . $booking . "] for user [" . $userForBooking . "] at [" . $time . "]";
      break;
    case "reservation.admin_update":
      $booking = $resID !== null ? (int)$resID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " updated booking [" . $booking . "] at [" . $time . "]";
      break;
    case "reservation.admin_delete":
      $booking = $resID !== null ? (int)$resID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " deleted booking [" . $booking . "] at [" . $time . "]";
      break;
    case "grant.create":
      $userForGrant = $targetUserID !== null ? (int)$targetUserID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " created a grant for user [" . $userForGrant . "] at [" . $time . "]";
      break;
    case "grant.update":
      $gid = $grantID !== null ? (int)$grantID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " updated grant [" . $gid . "] at [" . $time . "]";
      break;
    case "grant.delete":
      $gid = $grantID !== null ? (int)$grantID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " deleted grant [" . $gid . "] at [" . $time . "]";
      break;
    case "session.terminate":
      $booking = $resID !== null ? (int)$resID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " terminated session for booking [" . $booking . "] at [" . $time . "]";
      break;
    case "session.emergency_report":
      $booking = $resID !== null ? (int)$resID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " submitted an emergency report for booking [" . $booking . "] at [" . $time . "]";
      break;
    case "session.supervision_request":
      $booking = $resID !== null ? (int)$resID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " requested session supervision for booking [" . $booking . "] at [" . $time . "]";
      break;
    case "session.auto_terminate_inactive":
      $booking = $resID !== null ? (int)$resID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " session was auto-terminated for inactivity on booking [" . $booking . "] at [" . $time . "]";
      break;
    case "auth.login_success":
      $sentence = $actorLabel . " " . $actorId . " logged in at [" . $time . "]";
      break;
    case "auth.login_failed":
      $sentence = "[Guest] [N/A] failed to log in at [" . $time . "]";
      break;
    case "auth.register":
      $sentence = "[Guest] [N/A] created a new account at [" . $time . "]";
      break;
    case "user.create":
      $userForCreate = $targetUserID !== null ? (int)$targetUserID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " created user [" . $userForCreate . "] at [" . $time . "]";
      if ($username !== null && $username !== "") {
        $sentence .= " with username [" . (string)$username . "]";
      }
      break;
    case "user.update":
      $editedUser = $targetUserID !== null ? (int)$targetUserID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " updated user [" . $editedUser . "] at [" . $time . "]";
      break;
    case "user.delete":
      $deletedUser = $targetUserID !== null ? (int)$targetUserID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " deleted user [" . $deletedUser . "] at [" . $time . "]";
      break;
    case "equipment.create":
      $sentence = $actorLabel . " " . $actorId . " created equipment at [" . $time . "]";
      if ($eqName !== null && $eqName !== "") {
        $sentence .= " named [" . (string)$eqName . "]";
      }
      break;
    case "equipment.update":
      $equipmentForEdit = $eqID !== null ? (int)$eqID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " updated equipment [" . $equipmentForEdit . "] at [" . $time . "]";
      break;
    case "equipment.delete":
      $equipmentForDelete = $eqID !== null ? (int)$eqID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " deleted equipment [" . $equipmentForDelete . "] at [" . $time . "]";
      break;
    case "profile.update_info":
      $profileUser = $targetUserID !== null ? (int)$targetUserID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " updated profile information for user [" . $profileUser . "] at [" . $time . "]";
      break;
    case "profile.update_password":
      $profileUser = $targetUserID !== null ? (int)$targetUserID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " changed password for user [" . $profileUser . "] at [" . $time . "]";
      break;
    case "pricing.hourly_rate_update":
      $old = $oldRate !== null ? number_format((float)$oldRate, 2, ".", "") : "N/A";
      $new = $newRate !== null ? number_format((float)$newRate, 2, ".", "") : "N/A";
      $sentence = $actorLabel . " " . $actorId . " updated hourly booking rate from [$" . $old . "] to [$" . $new . "] at [" . $time . "]";
      break;
    case "pricing.user_rate_update":
      $target = $targetUserID !== null ? (int)$targetUserID : "N/A";
      $old = $oldRate !== null ? number_format((float)$oldRate, 2, ".", "") : "global";
      $new = $newRate !== null ? number_format((float)$newRate, 2, ".", "") : "N/A";
      $sentence = $actorLabel . " " . $actorId . " set custom hourly rate for user [" . $target . "] from [" . $old . "] to [$" . $new . "] at [" . $time . "]";
      break;
    case "pricing.user_rate_clear":
      $target = $targetUserID !== null ? (int)$targetUserID : "N/A";
      $old = $oldRate !== null ? number_format((float)$oldRate, 2, ".", "") : "N/A";
      $sentence = $actorLabel . " " . $actorId . " removed custom hourly rate [$" . $old . "] for user [" . $target . "] at [" . $time . "]";
      break;
    case "training.requirement_update":
      $equipmentForReq = $eqID !== null ? (int)$eqID : "N/A";
      $state = ((int)$required === 1) ? "required" : "not required";
      $sentence = $actorLabel . " " . $actorId . " set training as [" . $state . "] for equipment [" . $equipmentForReq . "] at [" . $time . "]";
      if (!empty($trainingTitle)) {
        $sentence .= " (" . (string)$trainingTitle . ")";
      }
      break;
    case "training.user_passed":
      $target = $targetUserID !== null ? (int)$targetUserID : "N/A";
      $equipmentForPass = $eqID !== null ? (int)$eqID : "N/A";
      $sentence = $actorLabel . " " . $actorId . " marked user [" . $target . "] as passed training for equipment [" . $equipmentForPass . "] at [" . $time . "]";
      if (!empty($trainingTitle)) {
        $sentence .= " (" . (string)$trainingTitle . ")";
      }
      break;
    case "safety.requirement_update":
      $equipmentForSafety = $eqID !== null ? (int)$eqID : "N/A";
      $state = ((int)$required === 1) ? "required" : "not required";
      $sentence = $actorLabel . " " . $actorId . " set safety as [" . $state . "] for equipment [" . $equipmentForSafety . "] at [" . $time . "]";
      if (!empty($reason)) {
        $sentence .= " (Reason: " . (string)$reason . ")";
      }
      break;
  }

  if ($eqID !== null && strpos($action, "reservation.") === 0) {
    $sentence .= " using equipment [" . (int)$eqID . "]";
  }

  return $sentence;
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Audit Log | Virtual Lab</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="css/style.css" />
</head>

<body>
  <div class="page-wrapper">
    <div class="container-fluid">
      <div class="topbar p-3 mb-3 d-flex justify-content-between align-items-center">
        <h1 class="h4 mb-0">Audit Log</h1>
        <a href="dashboard-admin.html" class="btn btn-outline-primary btn-outline-soft">Back</a>
      </div>

      <div class="card-soft p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="text-secondary small">
            Total: <?php echo number_format($total); ?> — Page <?php echo (int)$page; ?> / <?php echo (int)$totalPages; ?>
          </div>
        </div>

        <?php if (count($rows) === 0) { ?>
          <div class="text-secondary">No logs found.</div>
        <?php } else { ?>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($rows as $r) { ?>
              <div class="border-bottom pb-2">
                <div class="small">
                  <?php echo htmlspecialchars(action_to_sentence($r)); ?>
                </div>
              </div>
            <?php } ?>
          </div>
        <?php } ?>

        <nav class="mt-3">
          <ul class="pagination mb-0">
            <li class="page-item <?php echo $page <= 1 ? "disabled" : ""; ?>">
              <a class="page-link" href="?<?php echo htmlspecialchars(build_query(["page" => max(1, $page - 1)])); ?>">Prev</a>
            </li>
            <li class="page-item disabled">
              <span class="page-link"><?php echo (int)$page; ?></span>
            </li>
            <li class="page-item <?php echo $page >= $totalPages ? "disabled" : ""; ?>">
              <a class="page-link" href="?<?php echo htmlspecialchars(build_query(["page" => min($totalPages, $page + 1)])); ?>">Next</a>
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

