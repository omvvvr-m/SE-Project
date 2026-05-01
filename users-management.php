<?php

require_once "config/db.php";
require_once "models/user.php";

$user = new User($conn);
$result = $user->getAll();

?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Users Management | Virtual Lab</title>
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
        <h1 class="h4 mb-0">User Management Panel</h1>
        <div class="d-flex gap-2">
          <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#userModal">
            <i class="bi bi-person-plus me-1"></i>New User
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
                <th>First Name</th>
                <th>Last Name</th>
                <th>Username</th>
                <th>Phone</th>
                <th>Password</th>
                <th>Role</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                  <td><?php echo $row["userID"]; ?></td>
                  <td><?php echo $row["fname"]; ?></td>
                  <td><?php echo $row["lname"]; ?></td>
                  <td><?php echo $row["username"]; ?></td>
                  <td><?php echo $row["phoneNO"]; ?></td>
                  <td>******</td>
                  <td>
                    <?php if ($row["role"] == "admin") { ?>
                      <span class="badge text-bg-dark">admin</span>
                    <?php } elseif ($row["role"] == "guest") { ?>
                      <span class="badge text-bg-secondary">guest</span>
                    <?php } else { ?>
                      <span class="badge text-bg-primary">researcher</span>
                    <?php } ?>
                  </td>
                  <td>
                    <button
                      class="btn btn-sm btn-outline-primary edit-user-btn"
                      data-bs-toggle="modal"
                      data-bs-target="#userModal"
                      data-id="<?php echo htmlspecialchars($row["userID"]); ?>"
                      data-first-name="<?php echo htmlspecialchars($row["fname"]); ?>"
                      data-last-name="<?php echo htmlspecialchars($row["lname"]); ?>"
                      data-username="<?php echo htmlspecialchars($row["username"]); ?>"
                      data-phone="<?php echo htmlspecialchars($row["phoneNO"]); ?>"
                      data-password="<?php echo htmlspecialchars($row["password"]); ?>"
                      data-role="<?php echo htmlspecialchars($row["role"]); ?>">
                      Edit
                    </button>
                    <a href="models/user.php?delete_id=<?php echo $row["userID"]; ?>" onclick="return confirm('Delete this user?')" class="btn btn-sm btn-outline-danger">Delete</a>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5">Add / Edit User</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" id="userForm">
          <div class="modal-body">
            <div class="row g-3">
              <input type="hidden" name="user_id" id="user_id" />

              <div class="col-6">
                <input name="first_name" id="first_name" class="form-control" placeholder="First name" required />
              </div>
              <div class="col-6">
                <input name="last_name" id="last_name" class="form-control" placeholder="Last name" required />
              </div>
              <div class="col-12">
                <input name="username" id="username" class="form-control" placeholder="Username" required />
              </div>
              <div class="col-6">
                <input name="phone_no" id="phone_no" class="form-control" placeholder="Phone number" required />
              </div>
              <div class="col-6">
                <input name="password" id="password" class="form-control" placeholder="Password" required />
              </div>
              <div class="col-12">
                <select name="role" id="role" class="form-select">
                  <option value="researcher">researcher</option>
                  <option value="admin">admin</option>
                  <option value="guest">guest</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary btn-outline-soft" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-gradient" id="userSubmitBtn">Save User</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const userModal = document.getElementById("userModal");
    const modalTitle = userModal.querySelector(".modal-title");
    const userForm = document.getElementById("userForm");
    const submitBtn = document.getElementById("userSubmitBtn");

    const userIdInput = document.getElementById("user_id");
    const firstNameInput = document.getElementById("first_name");
    const lastNameInput = document.getElementById("last_name");
    const usernameInput = document.getElementById("username");
    const phoneInput = document.getElementById("phone_no");
    const passwordInput = document.getElementById("password");
    const roleInput = document.getElementById("role");

    userModal.addEventListener("show.bs.modal", function(event) {
      const triggerButton = event.relatedTarget;
      const isEdit = triggerButton && triggerButton.classList.contains("edit-user-btn");

      if (isEdit) {
        modalTitle.textContent = "Edit User";
        submitBtn.textContent = "Update User";
        userIdInput.value = triggerButton.getAttribute("data-id");
        firstNameInput.value = triggerButton.getAttribute("data-first-name");
        lastNameInput.value = triggerButton.getAttribute("data-last-name");
        usernameInput.value = triggerButton.getAttribute("data-username");
        phoneInput.value = triggerButton.getAttribute("data-phone");
        passwordInput.value = triggerButton.getAttribute("data-password");
        roleInput.value = triggerButton.getAttribute("data-role");
      } else {
        modalTitle.textContent = "Add User";
        submitBtn.textContent = "Save User";
        userForm.reset();
        userIdInput.value = "";
      }
    });
  </script>
</body>

</html>
