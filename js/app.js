document.addEventListener("DOMContentLoaded", () => {

  const user = getStoredUser();



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
  loadCurrentUserProfile();
  applyProfileLinksWithCurrentUser();
  bindProfileLinkClicks();
  loadMyGrantsForCurrentUser();
  bindGrantsPanelReload();
  bindBookingPanelNavigation();
  bindSessionPanelNavigation();
});

function getStoredUser() {
  try {
    return JSON.parse(localStorage.getItem("vlms_user"));
  } catch (error) {
    return null;
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
          const basicUser = {
            id: d.id,
            fullName: d.fullName,
            phone: d.phone,
            role: role
          };

          fetch("api/my-profile.php?user_id=" + encodeURIComponent(d.id))
            .then(profileRes => profileRes.json())
            .then(profileData => {
              if (profileData.status === "success" && profileData.user) {
                localStorage.setItem(
                  "vlms_user",
                  JSON.stringify({
                    id: profileData.user.id,
                    fullName: profileData.user.fullName,
                    phone: profileData.user.phone,
                    role: profileData.user.role
                  })
                );
              } else {
                localStorage.setItem("vlms_user", JSON.stringify(basicUser));
              }
            })
            .catch(() => {
              localStorage.setItem("vlms_user", JSON.stringify(basicUser));
            })
            .finally(() => {
              if (role === "admin") {
                window.location.href = "dashboard-admin.html";
                return;
              }
              window.location.href = "dashboard-user.php";
            });
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

    const username = document.getElementById("username");
    const firstName = document.getElementById("firstName");
    const lastName = document.getElementById("lastName");
    const phoneNumber = document.getElementById("phoneNumber");


    fetch("api/register.php", {

      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body:
        "username=" + encodeURIComponent(username.value)
        + "&password=" + encodeURIComponent(password)
        + "&firstName=" + encodeURIComponent(firstName.value)
        + "&lastName=" + encodeURIComponent(lastName.value)
        + "&phoneNumber=" + encodeURIComponent(phoneNumber.value)
    })
      .then(r => r.json())
      .then(d => {
        if (d.status === "success") {
          alert("Registration successful. You can now log in.");
          window.location.href = "login.html";
        }
        else {
          alert("Something went wrong!");
          return;
        }

      });
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
  const user = getStoredUser();
  const role = (user && user.role) ? user.role : "researcher";
  const dashboardHref = role === "admin" ? "dashboard-admin.html" : "dashboard-user.php";
  const backLinks = document.querySelectorAll("[data-back-dashboard]");
  backLinks.forEach((link) => {
    link.setAttribute("href", dashboardHref);
  });
}

function loadCurrentUserProfile() {
  const user = getStoredUser();
  if (!user || !user.id) return;

  function setNodeValue(el, value) {
    if ("value" in el) {
      el.value = value;
    } else {
      el.textContent = value;
    }
  }

  fetch("api/my-profile.php?user_id=" + encodeURIComponent(user.id))
    .then(r => r.json())
    .then(d => {
      if (d.status !== "success" || !d.user) return;

      const freshUser = {
        id: d.user.id,
        fullName: d.user.fullName,
        phone: d.user.phone,
        role: d.user.role
      };

      localStorage.setItem("vlms_user", JSON.stringify(freshUser));

      const normalizedRole = freshUser.role
        ? freshUser.role.charAt(0).toUpperCase() + freshUser.role.slice(1)
        : "";

      document.querySelectorAll(".fullName").forEach(el => setNodeValue(el, freshUser.fullName || "-"));
      document.querySelectorAll(".userID").forEach(el => setNodeValue(el, freshUser.id));
      document.querySelectorAll(".phoneNumber").forEach(el => setNodeValue(el, freshUser.phone || "-"));
      document.querySelectorAll(".role").forEach(el => setNodeValue(el, normalizedRole || "-"));
    })
    .catch(() => {
      // keep existing localStorage-based values on fetch failure
    });
}

function applyProfileLinksWithCurrentUser() {
  const links = document.querySelectorAll("[data-profile-link]");
  if (!links.length) return;

  const user = getStoredUser();
  const role = user && user.role ? user.role : "researcher";
  const fromValue = role === "admin" ? "admin" : "user";
  const userIdParam = user && user.id ? "&user_id=" + encodeURIComponent(user.id) : "";
  links.forEach((link) => {
    link.setAttribute("href", "profile.php?from=" + encodeURIComponent(fromValue) + userIdParam);
  });
}

function bindProfileLinkClicks() {
  const links = document.querySelectorAll("[data-profile-link]");
  if (!links.length) return;

  links.forEach((link) => {
    link.addEventListener("click", (event) => {
      const user = getStoredUser();
      if (!user || !user.id) return;

      event.preventDefault();
      const role = user.role || "researcher";
      const fromValue = role === "admin" ? "admin" : "user";
      const targetUrl = "profile.php?from=" + encodeURIComponent(fromValue) + "&user_id=" + encodeURIComponent(user.id);
      window.location.href = targetUrl;
    });
  });
}

function loadMyGrantsForCurrentUser() {
  const grantsContainer = document.getElementById("myGrantsList");
  const user = getStoredUser();
  if (!grantsContainer) return;

  if (!user || !user.id) {
    grantsContainer.innerHTML = '<div class="small text-secondary">Grant not found.</div>';
    return;
  }

  grantsContainer.innerHTML = '<div class="small text-secondary">Loading grants...</div>';
  const fetchPromise = fetch("api/my-grants.php?user_id=" + encodeURIComponent(user.id))
    .then(r => {
      if (!r.ok) {
        throw new Error("Failed response");
      }
      return r.json();
    });
  const timeoutPromise = new Promise((_, reject) => {
    setTimeout(() => reject(new Error("Timeout")), 8000);
  });

  Promise.race([fetchPromise, timeoutPromise])
    .then(d => {
      if (d.status !== "success") {
        grantsContainer.innerHTML = '<div class="small text-danger">Failed to load grants.</div>';
        return;
      }

      if (!d.grants || d.grants.length === 0) {
        grantsContainer.innerHTML = '<div class="small text-secondary">Grant not found.</div>';
        return;
      }

      const rowsHtml = d.grants.map((grant) => {
        const grantID = grant.grantID || "-";
        const grantUserID = grant.userID || user.id || "-";
        const grantName = grant.name || "-";
        const balance = Number(grant.balance || 0).toFixed(2);
        const expiry = grant.expiryDate || "-";
        return (
          "<tr>"
          + "<td>" + String(grantID) + "</td>"
          + "<td>" + String(grantUserID) + "</td>"
          + "<td>" + String(grantName) + "</td>"
          + "<td>$" + String(balance) + "</td>"
          + "<td>" + String(expiry) + "</td>"
          + "</tr>"
        );
      }).join("");

      grantsContainer.innerHTML =
        '<div class="table-responsive">'
        + '<table class="table table-striped mb-0">'
        + '<thead><tr><th>Grant ID</th><th>User ID</th><th>Name</th><th>Balance</th><th>Expiry Date</th></tr></thead>'
        + '<tbody>' + rowsHtml + '</tbody>'
        + '</table>'
        + '</div>';
    })
    .catch(() => {
      grantsContainer.innerHTML = '<div class="small text-danger">Failed to load grants.</div>';
    });
}

function bindGrantsPanelReload() {
  const grantsLinks = document.querySelectorAll('a[href="#grants-panel"]');
  const grantsPanel = document.getElementById("grants-panel");
  const grantsTitle = document.querySelector("#grants-panel .panel-title");
  if (!grantsLinks.length && !grantsPanel && !grantsTitle) return;

  grantsLinks.forEach((link) => {
    link.addEventListener("click", (event) => {
      event.preventDefault();
      loadMyGrantsForCurrentUser();
      if (grantsPanel) {
        grantsPanel.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    });
  });

  if (grantsPanel) {
    grantsPanel.addEventListener("click", () => {
      loadMyGrantsForCurrentUser();
    });
  }

  if (grantsTitle) {
    grantsTitle.style.cursor = "pointer";
    grantsTitle.addEventListener("click", () => {
      loadMyGrantsForCurrentUser();
    });
  }
}

function bindBookingPanelNavigation() {
  const bookingLinks = document.querySelectorAll("[data-open-booking-modal]");
  const bookingPanel = document.getElementById("booking-panel");
  const bookingModalElement = document.getElementById("bookingModal");

  if (!bookingLinks.length) return;

  bookingLinks.forEach((link) => {
    link.addEventListener("click", (event) => {
      event.preventDefault();

      if (bookingPanel) {
        bookingPanel.scrollIntoView({ behavior: "smooth", block: "start" });
      }

      if (bookingModalElement && window.bootstrap && window.bootstrap.Modal) {
        const modal = window.bootstrap.Modal.getOrCreateInstance(bookingModalElement);
        modal.show();
      }
    });
  });
}

function bindSessionPanelNavigation() {
  const sessionLinks = document.querySelectorAll('a[href="#session-panel"]');
  const sessionPanel = document.getElementById("session-panel");
  if (!sessionLinks.length || !sessionPanel) return;

  sessionLinks.forEach((link) => {
    link.addEventListener("click", (event) => {
      event.preventDefault();
      sessionPanel.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });
}
