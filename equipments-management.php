<?php


require_once "config/db.php";
require_once "models/equipment.php";

$equipment = new Equipment($conn);
$result = $equipment->getAll();

?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Equipments Management | Virtual Lab</title>
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
        <h1 class="h4 mb-0">Equipment Management Panel</h1>
        <div class="d-flex gap-2">
          <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#equipmentModal">
            <i class="bi bi-plus-lg me-1"></i>New Equipment
          </button>
          <a href="dashboard-admin.html" class="btn btn-outline-primary btn-outline-soft">Back</a>
        </div>
      </div>

      <div class="table-wrapper">
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>Equipment ID</th>
                <th>Eq-Qualifications</th>
                <th>Equipment Name</th>
                <th>Description</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                  <td><?php echo $row['eqID']; ?></td>
                  <td><?php echo $row['eqQualifications']; ?></td>
                  <td><?php echo $row['eqName']; ?></td>
                  <td><?php echo $row['eqDescription']; ?></td>
                  <td>
                    <?php if ($row['status'] == 'ready') { ?>
                      <span class="badge text-bg-success">ready</span>
                    <?php } else { ?>
                      <span class="badge text-bg-warning">under maintenance</span>
                    <?php } ?>
                  </td>
                  <td>
                    <button
                      class="btn btn-sm btn-outline-primary edit-equipment-btn"
                      data-bs-toggle="modal"
                      data-bs-target="#equipmentModal"
                      data-id="<?php echo htmlspecialchars($row['eqID']); ?>"
                      data-name="<?php echo htmlspecialchars($row['eqName']); ?>"
                      data-qual="<?php echo htmlspecialchars($row['eqQualifications']); ?>"
                      data-desc="<?php echo htmlspecialchars($row['eqDescription']); ?>"
                      data-stat="<?php echo htmlspecialchars($row['status']); ?>">
                      Edit
                    </button>
                    <a href="models/equipment.php?delete_id=<?php echo $row['eqID'] ?>" onclick="return confirm('Delete this item?')" class="btn btn-sm btn-outline-danger">Delete</button>
                  </td>
                </tr>
              <?php } ?>
            </tbody>

            <!-- <tbody>
                <tr>
                  <td>EQ-11</td>
                  <td>Microscopy Certificate</td>
                  <td>Electron Microscope</td>
                  <td>High precision imaging equipment</td>
                  <td><span class="badge text-bg-success">ready</span></td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary btn-outline-soft" data-bs-toggle="modal" data-bs-target="#equipmentModal">Edit</button>
                    <button class="btn btn-sm btn-outline-danger btn-outline-soft">Delete</button>
                  </td>
                </tr>
                <tr>
                  <td>EQ-22</td>
                  <td>Lab Safety Level 2</td>
                  <td>Centrifuge X2</td>
                  <td>Sample separation and molecular prep</td>
                  <td><span class="badge text-bg-warning">under maintenance</span></td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary btn-outline-soft" data-bs-toggle="modal" data-bs-target="#equipmentModal">Edit</button>
                    <button class="btn btn-sm btn-outline-danger btn-outline-soft">Delete</button>
                  </td>
                </tr>
              </tbody> -->
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="equipmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5">Add / Edit Equipment</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" id="equipmentForm">
          <div class="modal-body">
            <div class="row g-3">

              <input type="hidden" name="eq_id" id="eq_id" />


              <div class="col-12">
                <input name="eq_name" id="eq_name" class="form-control" placeholder="Equipment Name" />
              </div>

              <div class="col-12">
                <input name="eq_qual" id="eq_qual" class="form-control" placeholder="Qualifications" />
              </div>

              <div class="col-12">
                <textarea name="eq_desc" id="eq_desc" class="form-control" rows="3" placeholder="Description"></textarea>
              </div>

              <div class="col-12">
                <select name="eq_stat" id="eq_stat" class="form-select">
                  <option value="ready">ready</option>
                  <option value="under maintenance">under maintenance</option>
                </select>
              </div>

            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary btn-outline-soft" data-bs-dismiss="modal">
              Cancel
            </button><button type="submit" class="btn btn-gradient" id="equipmentSubmitBtn">Save Equipment</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const equipmentModal = document.getElementById("equipmentModal");
    const modalTitle = equipmentModal.querySelector(".modal-title");
    const equipmentForm = document.getElementById("equipmentForm");
    const submitBtn = document.getElementById("equipmentSubmitBtn");

    const eqIdInput = document.getElementById("eq_id");
    const eqNameInput = document.getElementById("eq_name");
    const eqQualInput = document.getElementById("eq_qual");
    const eqDescInput = document.getElementById("eq_desc");
    const eqStatInput = document.getElementById("eq_stat");

    equipmentModal.addEventListener("show.bs.modal", function(event) {
      const triggerButton = event.relatedTarget;
      const isEdit = triggerButton && triggerButton.classList.contains("edit-equipment-btn");

      if (isEdit) {
        modalTitle.textContent = "Edit Equipment";
        submitBtn.textContent = "Update Equipment";
        eqIdInput.value = triggerButton.getAttribute("data-id");
        eqNameInput.value = triggerButton.getAttribute("data-name");
        eqQualInput.value = triggerButton.getAttribute("data-qual");
        eqDescInput.value = triggerButton.getAttribute("data-desc");
        eqStatInput.value = triggerButton.getAttribute("data-stat");
      } else {
        modalTitle.textContent = "Add Equipment";
        submitBtn.textContent = "Save Equipment";
        equipmentForm.reset();
        eqIdInput.value = "";
      }
    });
  </script>
</body>

</html>