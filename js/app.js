document.addEventListener("DOMContentLoaded", () => {

  const user = JSON.parse(localStorage.getItem("vlms_user"));



  function setValue(el, value) {
    if ("value" in el) {
      el.value = value;       // for input, textarea, select
    } else {
      el.textContent = value; // for span, div, label, etc.
    }
  }

  if (user && user.fullName) {
    document.querySelectorAll(".fullName").forEach(el => {
      setValue(el, user.fullName);
    });

    document.querySelectorAll(".userID").forEach(el => {
      setValue(el, user.id);
    });

    document.querySelectorAll(".phoneNumber").forEach(el => {
      setValue(el, user.phone);
    });

    document.querySelectorAll(".role").forEach(el => {
      setValue(el, user.role);
    });
  }

  handleRoleBasedLogin();
  handleSignupValidation();
  handleSessionPanelVisibility();
  handleSimpleProfileEdit();
  applyRoleAwareBackLinks();
});

function handleDashboardUpdate() {
  const user = JSON.parse(localStorage.getItem("vlms_user"));

  if (user && user.fullName) {
    document.getElementById("welcomeText").textContent =
      "Welcome back, " + user.fullName;
  }
}

function handleRoleBasedLogin() {
  const loginForm = document.getElementById("loginForm");
  if (!loginForm) return;

  loginForm.addEventListener("submit", (event) => {
    event.preventDefault();
    const username = document.getElementById("username");
    const password = document.getElementById("password");


    fetch("api/login.php", {

      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body:
        "username=" + encodeURIComponent(username.value) + "&password=" + encodeURIComponent(password.value)
    })
      .then(r => r.json())
      .then(d => {
        const role = d.role;
        if (d.status === "success") {
          alert("Logged in!");
          localStorage.setItem(
            "vlms_user",
            JSON.stringify({
              id: d.id,
              fullName: d.fullName,
              phone: d.phone,
              role: role
            })
          );

          if (role === "admin") {
            window.location.href = "dashboard-admin.html";
            handleDashboardUpdate()
            return;
          }

          window.location.href = "dashboard-user.html";
          handleDashboardUpdate()
        }
        else {
          alert("Incorrect username or password!");
        }

      });

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
