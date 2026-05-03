<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once "config/db.php";
require_once __DIR__ . "/includes/audit.php";
audit_init($conn);
require_once "models/profile.php";

$profile = new Profile($conn);
$sessionUserID = isset($_SESSION["vlms_user_id"]) ? (int)$_SESSION["vlms_user_id"] : 0;
$sessionRole = $_SESSION["vlms_role"] ?? "";

$selectedUserID = $sessionUserID > 0 ? $sessionUserID : (isset($_GET["user_id"]) ? (int)$_GET["user_id"] : 0);
$from = $sessionRole === "admin" ? "admin" : ($_GET["from"] ?? "user");

if ($selectedUserID > 0) {
  $user = $profile->getUserById($selectedUserID);
} else {
  $user = $profile->getFirstUser();
}

if (!$user) {
  die("No users found in database.");
}

$backUrl = $from === "admin" ? "dashboard-admin.html" : "dashboard-user.php";
$fullName = trim(($user["fname"] ?? "") . " " . ($user["lname"] ?? ""));
$effectiveSessionRole = $sessionRole !== "" ? $sessionRole : ($user["role"] ?? "");
$canEditRole = $effectiveSessionRole === "admin";

?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Profile | Virtual Lab</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="css/style.css" />
</head>

<body>
  <div class="page-wrapper">
    <div class="container">
      <div class="topbar p-3 mb-3 d-flex justify-content-between align-items-center">
        <h1 class="h4 mb-0">Profile Page</h1>
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-primary btn-outline-soft">Back to Dashboard</a>
      </div>

      <?php if (isset($_GET["error"])) { ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($_GET["error"]); ?></div>
      <?php } ?>
      <?php if (isset($_GET["success"])) { ?>
        <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($_GET["success"]); ?></div>
      <?php } ?>

      <div class="row g-3">
        <div class="col-lg-4">
          <div class="card-soft p-4 text-center h-100">
            <div class="profile-avatar mx-auto mb-3">
              <i class="bi bi-person"></i>
            </div>
            <h2 class="h5 mb-1"><?php echo htmlspecialchars($fullName); ?></h2>
            <p class="text-secondary mb-0"><?php echo htmlspecialchars($user["role"]); ?></p>
          </div>
        </div>

        <div class="col-lg-8">
          <div class="card-soft p-4 mb-3">
            <h2 class="h5 mb-3">Account Information</h2>
            <form method="POST" class="row g-3">
              <input type="hidden" name="profile_action" value="update_info" />
              <input type="hidden" name="user_id" value="<?php echo htmlspecialchars((string)$user["userID"]); ?>" />
              <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>" />

              <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input name="first_name" class="form-control" value="<?php echo htmlspecialchars($user["fname"]); ?>" required />
              </div>
              <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input name="last_name" class="form-control" value="<?php echo htmlspecialchars($user["lname"]); ?>" required />
              </div>
              <div class="col-md-6">
                <label class=" form-label">Phone Number</label>
                <input name="phone_no" class="form-control" value="<?php echo htmlspecialchars($user["phoneNO"]); ?>" required />
              </div>
              <div class="col-md-6">
                <label class="form-label">Role</label>
                <?php if (!$canEditRole) { ?>
                  <input type="hidden" name="role" value="<?php echo htmlspecialchars((string)$user["role"]); ?>" />
                <?php } ?>
                <select name="role" class="form-select" <?php echo $canEditRole ? "" : "disabled"; ?>>
                  <option value="researcher" <?php echo $user["role"] === "researcher" ? "selected" : ""; ?>>researcher</option>
                  <option value="admin" <?php echo $user["role"] === "admin" ? "selected" : ""; ?>>admin</option>
                  <option value="guest" <?php echo $user["role"] === "guest" ? "selected" : ""; ?>>guest</option>
                </select>
                <?php if (!$canEditRole) { ?>
                  <small class="text-secondary">Only admin can change role permissions.</small>
                <?php } ?>
              </div>
              <div class="col-md-12">
                <label class="form-label">User ID</label>
                <input class="form-control" value="<?php echo htmlspecialchars((string)$user["userID"]); ?>" disabled />
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-gradient btn-sm px-4">Save Profile</button>
              </div>
            </form>
          </div>

          <div class="card-soft p-4">
            <h2 class="h5 mb-3">Change Password</h2>
            <form method="POST" class="row g-3">
              <input type="hidden" name="profile_action" value="update_password" />
              <input type="hidden" name="user_id" value="<?php echo htmlspecialchars((string)$user["userID"]); ?>" />
              <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>" />

              <div class="col-md-4">
                <input type="password" name="current_password" class="form-control" placeholder="Current password" required />
              </div>
              <div class="col-md-4">
                <input type="password" name="new_password" class="form-control" placeholder="New password" required />
              </div>
              <div class="col-md-4">
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required />
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-gradient btn-sm mt-1">Update Password</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>
