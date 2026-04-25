document.addEventListener("DOMContentLoaded", () => {
  handleRoleBasedLogin();
  handleSignupValidation();
  handleSessionPanelVisibility();
  handleSimpleProfileEdit();
  applyRoleAwareBackLinks();
});

function handleRoleBasedLogin() {
  const loginForm = document.getElementById("loginForm");
  if (!loginForm) return;

  loginForm.addEventListener("submit", (event) => {
    event.preventDefault();
    const role = document.getElementById("loginRole")?.value;
    localStorage.setItem("vlms_role", role || "researcher");
    localStorage.setItem(
      "vlms_user",
      JSON.stringify({
        id: "U-1024",
        fullName: "Dr. Sarah Ahmed",
        phone: "+20 100 000 0000",
        role: role === "admin" ? "Admin" : "Researcher",
      })
    );

    if (role === "admin") {
      window.location.href = "dashboard-admin.html";
      return;
    }

    window.location.href = "dashboard-user.html";
  });
}

function handleSignupValidation() {
  const signupForm = document.getElementById("signupForm");
  if (!signupForm) return;

  signupForm.addEventListener("submit", (event) => {
    event.preventDefault();

    const password = document.getElementById("password")?.value || "";
    const confirmPassword = document.getElementById("confirmPassword")?.value || "";

    if (password.length < 6) {
      alert("Password must be at least 6 characters.");
      return;
    }

    if (password !== confirmPassword) {
      alert("Password and confirm password do not match.");
      return;
    }

    alert("Registration successful. You can now log in.");
    window.location.href = "login.html";
  });
}

function handleSessionPanelVisibility() {
  const sessionToggle = document.getElementById("toggleSession");
  const sessionPanel = document.getElementById("sessionPanel");
  const noSessionPanel = document.getElementById("noSessionPanel");

  if (!sessionToggle || !sessionPanel || !noSessionPanel) return;

  const updateView = () => {
    if (sessionToggle.checked) {
      sessionPanel.classList.remove("d-none");
      noSessionPanel.classList.add("d-none");
    } else {
      sessionPanel.classList.add("d-none");
      noSessionPanel.classList.remove("d-none");
    }
  };

  sessionToggle.addEventListener("change", updateView);
  updateView();
}

function handleSimpleProfileEdit() {
  const editBtn = document.getElementById("editProfileBtn");
  const profileInputs = document.querySelectorAll(".profile-editable");

  if (!editBtn || !profileInputs.length) return;

  editBtn.addEventListener("click", () => {
    profileInputs.forEach((input) => {
      input.disabled = !input.disabled;
    });
  });
}

function applyRoleAwareBackLinks() {
  const role = localStorage.getItem("vlms_role") || "researcher";
  const dashboardHref = role === "admin" ? "dashboard-admin.html" : "dashboard-user.html";
  const backLinks = document.querySelectorAll("[data-back-dashboard]");
  backLinks.forEach((link) => {
    link.setAttribute("href", dashboardHref);
  });
}
