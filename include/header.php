<?php
if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}

require_once __DIR__ . '/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$username    = $_SESSION['username'] ?? '';
$userRole    = $_SESSION['user_role'] ?? 'browser';
$mustChangePassword = !empty($_SESSION['must_change_password']);
?>
<header class="site-header">
  <div class="header-content">
    <div class="logo-container">
      <img src="assets/img/nbbtm-logo-white-web826.png" alt="NBBTM Logo" class="scaled-svg">
    </div>

    <h1>New Beginnings Baptist Tabernacle Ministries</h1>
    <h2 class="break-row">Central Management System</h2>
  </div>

  <?php if (empty($hide_menu)): ?>
    <nav class="navbar">
      <ul class="nav-links">
        <li><a href="index.php" class="<?= in_array($currentPage, ['index.php', 'index1.php']) ? 'active' : '' ?>">Home</a></li>

        <?php if ($isLoggedIn && isMember()): ?>
          <li><a href="member_documents.php" class="<?= ($currentPage === 'member_documents.php') ? 'active' : '' ?>">NBBTM Documents</a></li>

        <?php elseif ($isLoggedIn && (isAdmin() || isDeveloper())): ?>
          <!-- Full Admin & Developer Navigation -->
          <li class="dropdown">
            <a href="#" class="<?= in_array($currentPage, ['contacts.php', 'contact_dashboard.php']) ? 'active' : '' ?>">Contacts</a>
            <ul class="submenu">
              <li><a href="contacts.php" class="<?= ($currentPage === 'contacts.php') ? 'active' : '' ?>">New/Update Contacts</a></li>
              <li><a href="contact_dashboard.php" class="<?= ($currentPage === 'contact_dashboard.php') ? 'active' : '' ?>">Contact Dashboard</a></li>
            </ul>
          </li>

          <li class="dropdown">
            <a href="#" class="<?= in_array($currentPage, ['ministry_manager.php', 'ministry_members.php']) ? 'active' : '' ?>">Ministries</a>
            <ul class="submenu">
              <li><a href="ministry_manager.php" class="<?= ($currentPage === 'ministry_manager.php') ? 'active' : '' ?>">Ministries</a></li>
              <li><a href="ministry_members.php" class="<?= ($currentPage === 'ministry_members.php') ? 'active' : '' ?>">Ministry Participants</a></li>
            </ul>
          </li>

          <li class="dropdown">
            <a href="#" class="<?= in_array($currentPage, ['events.php', 'event_dashboard_main.php', 'event_dashboard.php', 'event_registration.php', 'event_checkin.php']) ? 'active' : '' ?>">Programs / Events</a>
            <ul class="submenu">
              <li><a href="events.php" class="<?= ($currentPage === 'events.php') ? 'active' : '' ?>">Program / Event Updates</a></li>
              <li><a href="event_dashboard_main.php" class="<?= ($currentPage === 'event_dashboard_main.php') ? 'active' : '' ?>">Event Information</a></li>
              <li><a href="event_dashboard.php" class="<?= ($currentPage === 'event_dashboard.php') ? 'active' : '' ?>">Event Financials</a></li>
              <li><a href="event_registration.php" class="<?= ($currentPage === 'event_registration.php') ? 'active' : '' ?>">Event Registration</a></li>
              <li><a href="event_checkin.php" class="<?= ($currentPage === 'event_checkin.php') ? 'active' : '' ?>">Event Check-in</a></li>
            </ul>
          </li>

          <li class="dropdown">
            <a href="#" class="<?= in_array($currentPage, ['vbs_sessions.php', 'vbs_manager.php', 'vbs_attendance.php', 'vbs_registration.php']) ? 'active' : '' ?>">VBS</a>
            <ul class="submenu">
              <li><a href="vbs_sessions.php" class="<?= ($currentPage === 'vbs_sessions.php') ? 'active' : '' ?>">Sessions</a></li>
              <li><a href="vbs_registration.php" class="<?= ($currentPage === 'vbs_registration.php') ? 'active' : '' ?>">Registration</a></li>
              <li><a href="vbs_attendance.php" class="<?= ($currentPage === 'vbs_attendance.php') ? 'active' : '' ?>">Attendance</a></li>
            </ul>
          </li>

          <!-- Admin / Full Control Menu Dropdown -->
          <li class="dropdown">
            <a href="#" class="<?= in_array($currentPage, ['admin_mailer.php', 'ministry_mailer.php', 'user_management.php', 'admin_reports.php', 'manage_reports.php', 'report_files.php']) ? 'active' : '' ?>" style="border-left: 2px solid #f59e0b;">
              Admin &#9662;
            </a>
            <ul class="submenu">
              <li class="dropdown-nested">
                <a href="#" class="<?= in_array($currentPage, ['admin_mailer.php', 'ministry_mailer.php']) ? 'active' : '' ?>">
                  Emails &#9656;
                </a>
                <ul class="submenu-nested">
                  <li><a href="admin_mailer.php" class="<?= ($currentPage === 'admin_mailer.php') ? 'active' : '' ?>">Send Email(s) - Contacts</a></li>
                  <li><a href="ministry_mailer.php" class="<?= ($currentPage === 'ministry_mailer.php') ? 'active' : '' ?>">Send Email(s) - Ministries & Committees</a></li>
                </ul>
              </li>

              <li class="dropdown-nested">
                <a href="#" class="<?= in_array($currentPage, ['admin_reports.php', 'manage_reports.php', 'report_files.php']) ? 'active' : '' ?>">
                  Reports &#9656;
                </a>
                <ul class="submenu-nested">
                  <li><a href="admin_reports.php" class="<?= ($currentPage === 'admin_reports.php') ? 'active' : '' ?>">Run Jasper Reports</a></li>
                  <li><a href="manage_reports.php" class="<?= ($currentPage === 'manage_reports.php') ? 'active' : '' ?>">Configure Reports</a></li>
                  <li><a href="report_files.php" class="<?= ($currentPage === 'report_files.php') ? 'active' : '' ?>">Saved Reports</a></li>
                </ul>
              </li>
              <li><a href="self_register.php" class="<?= ($currentPage === 'self_register.php') ? 'active' : '' ?>">Self Registration</a></li>
              <li><a href="user_management.php" class="<?= ($currentPage === 'user_management.php') ? 'active' : '' ?>">👥 User Management</a></li>
              <li><a href="admin_documents.php" class="<?= ($currentPage === 'admin_documents.php') ? 'active' : '' ?>">NBBTM Documents</a></li>
            </ul>
          </li>

        <?php elseif ($isLoggedIn && canEdit()): ?>
          <!-- Staff Navigation: Operational access without Admin menu -->
          <li class="dropdown">
            <a href="#" class="<?= in_array($currentPage, ['contacts.php', 'contact_dashboard.php']) ? 'active' : '' ?>">Contacts</a>
            <ul class="submenu">
              <li><a href="contacts.php" class="<?= ($currentPage === 'contacts.php') ? 'active' : '' ?>">New/Update Contacts</a></li>
              <li><a href="contact_dashboard.php" class="<?= ($currentPage === 'contact_dashboard.php') ? 'active' : '' ?>">Contact Dashboard</a></li>
            </ul>
          </li>

          <li class="dropdown">
            <a href="#" class="<?= in_array($currentPage, ['ministry_manager.php', 'ministry_members.php']) ? 'active' : '' ?>">Ministries</a>
            <ul class="submenu">
              <li><a href="ministry_manager.php" class="<?= ($currentPage === 'ministry_manager.php') ? 'active' : '' ?>">Ministries</a></li>
              <li><a href="ministry_members.php" class="<?= ($currentPage === 'ministry_members.php') ? 'active' : '' ?>">Ministry Participants</a></li>
            </ul>
          </li>

          <li class="dropdown">
            <a href="#" class="<?= in_array($currentPage, ['events.php', 'event_dashboard_main.php', 'event_dashboard.php', 'event_registration.php', 'event_checkin.php']) ? 'active' : '' ?>">Programs / Events</a>
            <ul class="submenu">
              <li><a href="events.php" class="<?= ($currentPage === 'events.php') ? 'active' : '' ?>">Program / Event Updates</a></li>
              <li><a href="event_dashboard_main.php" class="<?= ($currentPage === 'event_dashboard_main.php') ? 'active' : '' ?>">Event Information</a></li>
              <li><a href="event_dashboard.php" class="<?= ($currentPage === 'event_dashboard.php') ? 'active' : '' ?>">Event Financials</a></li>
              <li><a href="event_registration.php" class="<?= ($currentPage === 'event_registration.php') ? 'active' : '' ?>">Event Registration</a></li>
              <li><a href="event_checkin.php" class="<?= ($currentPage === 'event_checkin.php') ? 'active' : '' ?>">Event Check-in</a></li>
            </ul>
          </li>

          <li class="dropdown">
            <a href="#" class="<?= in_array($currentPage, ['vbs_sessions.php', 'vbs_manager.php', 'vbs_attendance.php', 'vbs_registration.php']) ? 'active' : '' ?>">VBS</a>
            <ul class="submenu">
              <li><a href="vbs_sessions.php" class="<?= ($currentPage === 'vbs_sessions.php') ? 'active' : '' ?>">Sessions</a></li>
              <li><a href="vbs_registration.php" class="<?= ($currentPage === 'vbs_registration.php') ? 'active' : '' ?>">Registration</a></li>
              <li><a href="vbs_attendance.php" class="<?= ($currentPage === 'vbs_attendance.php') ? 'active' : '' ?>">Attendance</a></li>
            </ul>
          </li>

        <?php elseif ($isLoggedIn): ?>
          <!-- Browser / View Role Navigation -->
          <li><a href="contact_dashboard.php" class="<?= ($currentPage === 'contact_dashboard.php') ? 'active' : '' ?>">Contacts Dashboard</a></li>
          <li><a href="ministry_manager.php" class="<?= ($currentPage === 'ministry_manager.php') ? 'active' : '' ?>">Ministries</a></li>
          <li><a href="ministry_members.php" class="<?= ($currentPage === 'ministry_members.php') ? 'active' : '' ?>">Ministry Participants</a></li>
          <li><a href="event_dashboard_main.php" class="<?= ($currentPage === 'event_dashboard_main.php') ? 'active' : '' ?>">Event Information</a></li>

          <li><a href="event_dashboard.php" class="<?= ($currentPage === 'event_dashboard.php') ? 'active' : '' ?>">Event Financials</a></li>
          <li><a href="vbs_sessions.php" class="<?= ($currentPage === 'vbs_sessions.php') ? 'active' : '' ?>">Sessions</a></li>
          <li><a href="vbs_registration.php" class="<?= ($currentPage === 'vbs_registration.php') ? 'active' : '' ?>">Registration</a></li>
        <?php endif; ?>

        <!-- Authentication Action Buttons -->
        <?php if ($isLoggedIn): ?>
          <li class="dropdown" style="margin-left: auto;">
            <a href="#" style="background-color: #0d9488; color: #ffffff;">
              👤 <?= htmlspecialchars($username) ?> (<?= htmlspecialchars(ucfirst($userRole)) ?>) &#9662;
            </a>
            <ul class="submenu" style="right: 0; left: auto;">
              <li><a href="#" id="open-change-password-btn">Change Password</a></li>
              <li><a href="#" id="header-logout-btn">Sign Out</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li style="margin-left: auto; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 0.85rem; opacity: 0.9; font-style: normal;">Mode: <strong>Browser</strong></span>
            <a href="#" id="open-login-btn" style="background-color: #2563eb; color: #ffffff;">Sign In</a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
  <?php endif; ?>
</header>

<!-- Sign In Modal -->
<div id="login-modal" class="modal-overlay hidden">
  <div class="modal-content">
    <button type="button" class="modal-close" id="close-login-btn">&times;</button>
    <h3>Sign In to Your Account</h3>

    <div id="login-error-msg" class="text-danger hidden" style="margin-bottom: 1rem;"></div>

    <form id="header-login-form">
      <div class="field-group">
        <label for="login-identity"><strong>Username or Email</strong></label>
        <input type="text" id="login-identity" name="username" required placeholder="Enter username or email" style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
      </div>

      <div class="field-group" style="margin-top: 1rem;">
        <label for="login-password"><strong>Password</strong></label>
        <div class="password-wrapper">
          <input type="password" id="login-password" name="password" required placeholder="Enter password" style="padding: 8px 36px 8px 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
          <button type="button" class="toggle-password-btn" data-target="login-password" title="Toggle password visibility">👁️</button>
        </div>
      </div>

      <button type="submit" id="login-submit-btn" class="btn-primary" style="margin-top: 1.25rem; width: 100%;">
        Sign In <span class="spinner" id="login-spinner">⚙</span>
      </button>
    </form>
  </div>
</div>

<!-- Change Password Modal -->
<div id="change-password-modal" class="modal-overlay hidden">
  <div class="modal-content">
    <button type="button" class="modal-close" id="close-change-pwd-btn">&times;</button>
    <h3>Change Password</h3>

    <div id="change-pwd-msg" class="text-danger hidden" style="margin-bottom: 1rem;"></div>

    <form id="header-change-password-form">
      <div class="field-group">
        <label for="current-password"><strong>Current Password</strong></label>
        <div class="password-wrapper">
          <input type="password" id="current-password" name="current_password" required placeholder="Enter current password" style="padding: 8px 36px 8px 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
          <button type="button" class="toggle-password-btn" data-target="current-password">👁️</button>
        </div>
      </div>

      <div class="field-group" style="margin-top: 1rem;">
        <label for="new-password"><strong>New Password</strong></label>
        <div class="password-wrapper">
          <input type="password" id="new-password" name="new_password" required minlength="8" placeholder="Enter new password (min. 8 chars)" style="padding: 8px 36px 8px 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
          <button type="button" class="toggle-password-btn" data-target="new-password">👁️</button>
        </div>
      </div>

      <div class="field-group" style="margin-top: 1rem;">
        <label for="confirm-password"><strong>Confirm New Password</strong></label>
        <div class="password-wrapper">
          <input type="password" id="confirm-password" name="confirm_password" required minlength="8" placeholder="Confirm new password" style="padding: 8px 36px 8px 8px; border-radius: 4px; border: 1px solid #cbd5e1; width: 100%;">
          <button type="button" class="toggle-password-btn" data-target="confirm-password">👁️</button>
        </div>
      </div>

      <button type="submit" id="change-pwd-submit-btn" class="btn-primary" style="margin-top: 1.25rem; width: 100%;">
        Update Password <span class="spinner" id="change-pwd-spinner">⚙</span>
      </button>
    </form>
  </div>
</div>

<style>
  .logo-container {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .logo-container img.scaled-svg {
    width: 65px;
    height: 65px;
    object-fit: contain;
  }

  .dropdown-nested {
    position: relative;
  }

  .dropdown-nested .submenu-nested {
    display: none;
    position: absolute;
    left: 100%;
    top: 0;
    min-width: 250px;
    background-color: #175bbb;
    box-shadow: 0 4px 12px rgba(8, 21, 130, 0.15);
    border-radius: 4px;
    list-style: none;
    padding: 0.5rem 0;
    margin: 0;
    z-index: 1001;
  }

  .dropdown-nested:hover>.submenu-nested {
    display: block;
  }

  .dropdown-nested .submenu-nested li a {
    padding: 8px 16px;
    display: block;
    color: #40587d;
    text-decoration: none;
    white-space: nowrap;
    font-size: 0.9rem;
  }

  .dropdown-nested .submenu-nested li a:hover,
  .dropdown-nested .submenu-nested li a.active {
    background-color: #f1f5f9;
    color: #043b8f;
  }

  .modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 2000;
  }

  .modal-content {
    background: #ffffff;
    padding: 2rem;
    border-radius: 8px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    position: relative;
    color: #28089a;
  }

  .modal-content h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0.5rem;
  }

  .modal-close {
    position: absolute;
    top: 10px;
    right: 15px;
    background: none;
    border: none;
    font-size: 1.5rem;
    font-weight: bold;
    color: #64748b;
    cursor: pointer;
  }

  .modal-close:hover {
    color: #dc2626;
  }

  .password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
  }

  .toggle-password-btn {
    position: absolute;
    right: 8px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    padding: 0;
    opacity: 0.7;
    transition: opacity 0.2s ease;
  }

  .toggle-password-btn:hover {
    opacity: 1;
  }

  .text-success {
    color: #16a34a !important;
    font-size: 0.85rem;
  }

  .hidden {
    display: none !important;
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const loginModal = document.getElementById('login-modal');
    const openLoginBtn = document.getElementById('open-login-btn');
    const closeLoginBtn = document.getElementById('close-login-btn');
    const loginForm = document.getElementById('header-login-form');
    const loginErrorMsg = document.getElementById('login-error-msg');
    const logoutBtn = document.getElementById('header-logout-btn');

    const changePwdModal = document.getElementById('change-password-modal');
    const openChangePwdBtn = document.getElementById('open-change-password-btn');
    const closeChangePwdBtn = document.getElementById('close-change-pwd-btn');
    const changePwdForm = document.getElementById('header-change-password-form');
    const changePwdMsg = document.getElementById('change-pwd-msg');

    document.querySelectorAll('.toggle-password-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (input) {
          const isPassword = input.getAttribute('type') === 'password';
          input.setAttribute('type', isPassword ? 'text' : 'password');
          btn.textContent = isPassword ? '🙈' : '👁️';
        }
      });
    });

    if (openLoginBtn) openLoginBtn.addEventListener('click', (e) => {
      e.preventDefault();
      loginModal.classList.remove('hidden');
    });
    if (closeLoginBtn) closeLoginBtn.addEventListener('click', () => {
      loginModal.classList.add('hidden');
      loginErrorMsg.classList.add('hidden');
    });

    if (openChangePwdBtn) openChangePwdBtn.addEventListener('click', (e) => {
      e.preventDefault();
      changePwdModal.classList.remove('hidden');
    });
    if (closeChangePwdBtn) closeChangePwdBtn.addEventListener('click', () => {
      changePwdModal.classList.add('hidden');
      changePwdMsg.classList.add('hidden');
    });

    if (loginForm) {
      loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        loginErrorMsg.classList.add('hidden');
        const formData = new FormData(loginForm);

        try {
          const res = await fetch('./include/auth.php?action=login', {
            method: 'POST',
            body: formData
          });
          const responseText = await res.text();
          if (res.status === 429) {
            let rateLimitMessage = 'Too many sign-in attempts. Please try again later.';
            try {
              const rateLimitResult = JSON.parse(responseText);
              rateLimitMessage = rateLimitResult.message || rateLimitMessage;
            } catch (parseError) {
              // Keep a safe message if the server response contains unexpected output.
            }
            throw new Error(rateLimitMessage);
          }
          let result;
          try {
            result = JSON.parse(responseText);
          } catch (parseError) {
            throw new Error(`Sign-in service returned an invalid response (HTTP ${res.status}).`);
          }
          if (!res.ok && result.message) {
            throw new Error(result.message);
          }
          if (result.success) {
            if (result.must_change_password) {
              loginModal.classList.add('hidden');
              changePwdModal.classList.remove('hidden');
              if (closeChangePwdBtn) closeChangePwdBtn.style.display = 'none';
              changePwdMsg.className = 'text-danger';
              changePwdMsg.textContent = 'Please change your temporary password before continuing.';
            } else {
              window.location.reload();
            }
          } else {
            loginErrorMsg.textContent = result.message || 'Login failed.';
            loginErrorMsg.classList.remove('hidden');
          }
        } catch (err) {
          loginErrorMsg.textContent = err.message || 'Server connection error.';
          loginErrorMsg.classList.remove('hidden');
        }
      });
    }

    if (changePwdForm) {
      changePwdForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        changePwdMsg.className = 'text-danger hidden';

        const formData = new FormData(changePwdForm);

        try {
          const res = await fetch('./include/auth.php?action=change_password', {
            method: 'POST',
            body: formData
          });
          const responseText = await res.text();
          let result;
          try {
            result = JSON.parse(responseText);
          } catch (parseError) {
            throw new Error(`Password service returned an invalid response (HTTP ${res.status}).`);
          }
          if (!res.ok && result.message) {
            throw new Error(result.message);
          }

          if (result.success) {
            window.location.reload();
          } else {
            changePwdMsg.textContent = result.message || 'Password update failed.';
            changePwdMsg.className = 'text-danger';
          }

        } catch (err) {
          changePwdMsg.textContent = err.message || 'Server connection error.';
          changePwdMsg.className = 'text-danger';
        }
      });
    }

    if (<?= $mustChangePassword ? 'true' : 'false' ?> && changePwdModal) {
      changePwdModal.classList.remove('hidden');
      if (closeChangePwdBtn) closeChangePwdBtn.style.display = 'none';
      changePwdMsg.className = 'text-danger';
      changePwdMsg.textContent = 'Please change your temporary password before continuing.';
    }

    if (logoutBtn) {
      logoutBtn.addEventListener('click', (e) => {
        e.preventDefault();
        // Use a normal navigation so the server's logout redirect is followed.
        window.location.href = './include/auth.php?action=logout';
      });
    }
  });
</script>