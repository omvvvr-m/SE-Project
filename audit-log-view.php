<?php
require_once __DIR__ . "/includes/require_admin.php";
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/audit.php";
audit_init($conn);

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($id <= 0) {
  header("Location: audit-log.php");
  exit;
}

$stmt = $conn->prepare("SELECT * FROM audit_logs WHERE id = ? LIMIT 1");
$row = null;
if ($stmt) {
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res) {
    $row = $res->fetch_assoc();
  }
  $stmt->close();
}

if (!$row) {
  header("Location: audit-log.php");
  exit;
}

$payloadPretty = "";
if (isset($row["payload_json"]) && $row["payload_json"] !== null && $row["payload_json"] !== "") {
  $decoded = json_decode((string)$row["payload_json"], true);
  if (json_last_error() === JSON_ERROR_NONE) {
    $payloadPretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } else {
    $payloadPretty = (string)$row["payload_json"];
  }
}

function h($v): string
{
  return htmlspecialchars((string)$v);
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Audit Log Entry #<?php echo h($id); ?> | Virtual Lab</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
</head>

<body>
  <div class="page-wrapper">
    <div class="container py-3">
      <div class="topbar p-3 mb-3 d-flex justify-content-between align-items-center">
        <h1 class="h5 mb-0">Audit Entry #<?php echo h($id); ?></h1>
        <a href="audit-log.php" class="btn btn-outline-primary btn-outline-soft btn-sm">Back</a>
      </div>

      <div class="card-soft p-3 mb-3">
        <div class="row g-2">
          <div class="col-md-4"><span class="text-secondary small">Time</span>
            <div><?php echo h($row["created_at"] ?? ""); ?></div>
          </div>
          <div class="col-md-4"><span class="text-secondary small">Actor</span>
            <div><?php echo h(($row["actor_type"] ?? "") . " #" . ($row["actor_user_id"] ?? "") . " (" . ($row["actor_role"] ?? "") . ")"); ?></div>
          </div>
          <div class="col-md-4"><span class="text-secondary small">Action</span>
            <div><code><?php echo h($row["action"] ?? ""); ?></code></div>
          </div>
          <div class="col-md-4"><span class="text-secondary small">Method</span>
            <div><?php echo h($row["request_method"] ?? ""); ?></div>
          </div>
          <div class="col-md-8"><span class="text-secondary small">URI</span>
            <div class="text-break"><?php echo h($row["request_uri"] ?? ""); ?></div>
          </div>
          <div class="col-md-4"><span class="text-secondary small">IP</span>
            <div><?php echo h($row["ip"] ?? ""); ?></div>
          </div>
          <div class="col-md-4"><span class="text-secondary small">Status</span>
            <div><?php echo h($row["status_code"] ?? ""); ?></div>
          </div>
          <div class="col-md-4"><span class="text-secondary small">Duration</span>
            <div><?php echo h($row["duration_ms"] ?? ""); ?>ms</div>
          </div>
          <div class="col-12"><span class="text-secondary small">User-Agent</span>
            <div class="text-break"><?php echo h($row["user_agent"] ?? ""); ?></div>
          </div>
          <div class="col-12"><span class="text-secondary small">Referrer</span>
            <div class="text-break"><?php echo h($row["referrer"] ?? ""); ?></div>
          </div>
        </div>
      </div>

      <div class="card-soft p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0">Payload</h2>
        </div>
        <pre class="mb-0" style="white-space: pre-wrap;"><?php echo h($payloadPretty); ?></pre>
      </div>
    </div>
  </div>
</body>

</html>

