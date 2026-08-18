<?php
// Enforce persistent cookie scope before session start
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400, // 24 Hours
        'path'     => '/',   // Root path ensures session spans all sub-folders
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Require auth helper & enforce admin access
require_once __DIR__ . '/include/auth.php';
requireAdmin();

$AVAILABLE_PAGES = [
    'contacts'           => 'Contacts & Members Profile',
    'contact_dashboard'  => 'Contact Overview Dashboard',
    'ministries'         => 'Ministry/Committee Manager',
    'ministry_members'   => 'Ministry Member Rosters',
    'events'             => 'Program & Event Manager',
    'event_registration' => 'Event Registration Portal',
    'event_checkin'      => 'Live Event Attendance Check-In',
    'vbs_sessions'       => 'VBS Sessions & Class Setup',
    'vbs_attendance'     => 'VBS Daily Attendance Tracker'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User & Permissions Management - NBBTM</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

  <style>
    .alert-box {
      padding: 12px 16px;
      margin-bottom: 20px;
      border-radius: 6px;
      font-weight: 500;
      font-size: 0.95rem;
      display: none;
    }
    .alert-success { background-color: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
    .alert-error { background-color: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }

    .form-control {
      width: 100%;
      height: 38px;
      padding: 0 10px;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      font-size: 0.95rem;
      box-sizing: border-box;
      color: #28089a;
      background-color: #ffffff;
      font-family: inherit;
    }
    label { font-weight: 600; color: #28089a; font-size: 0.9rem; margin-bottom: 0.25rem; display: inline-block; }

    .badge { padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; }
    .badge-admin { background-color: #fce7f3; color: #9d174d; }
    .badge-staff { background-color: #e0f2fe; color: #0369a1; }
    .badge-browse { background-color: #f1f5f9; color: #475569; }
    .badge-active { background-color: #d1fae5; color: #065f46; }
    .badge-inactive { background-color: #fee2e2; color: #991b1b; }

    .matrix-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
      background: #ffffff;
    }
    .matrix-table th, .matrix-table td {
      border: 1px solid #cbd5e1;
      padding: 8px 12px;
    }
    .matrix-table th {
      background-color: #f8fafc;
      color: #28089a;
      font-weight: 700;
    }
    .matrix-table td.center {
      text-align: center;
    }
  </style>

  <script>
    $(document).ready(function() {
      let usersList = [];

      function showStatusMessage(message, type = 'success') {
        let $box = $('#status-message');
        $box.removeClass('alert-success alert-error')
            .addClass(type === 'success' ? 'alert-success' : 'alert-error')
            .html(message)
            .stop(true, true)
            .fadeIn(200);

        $('html, body').animate({ scrollTop: $box.offset().top - 20 }, 200);

        if (type === 'success') {
          setTimeout(function() { $box.fadeOut(500); }, 5000);
        }
      }

      function clearStatusMessage() {
        $('#status-message').fadeOut(200).empty();
      }

      loadUsers();

      // Fetch User List
      function loadUsers() {
        $.ajax({
          url: 'user_api.php',
          type: 'GET',
          data: { action: 'get_users' },
          dataType: 'json',
          success: function(res) {
            if (res.status === 'success') {
              usersList = res.data;
              renderUsersTable(usersList);
            } else {
              showStatusMessage(res.message || 'Error loading users.', 'error');
            }
          },
          error: function() {
            showStatusMessage('Server error fetching user records.', 'error');
          }
        });
      }

      function renderUsersTable(users) {
        let $tbody = $('#usersTableBody').empty();

        if (!users || users.length === 0) {
          $tbody.append('<tr><td colspan="6" style="text-align:center; color:#64748b; padding:20px;">No user accounts found.</td></tr>');
          return;
        }

        $.each(users, function(i, u) {
          let roleBadge = `<span class="badge badge-${u.role}">${u.role.toUpperCase()}</span>`;
          let statusBadge = parseInt(u.is_active) === 1
            ? '<span class="badge badge-active">Active</span>'
            : '<span class="badge badge-inactive">Inactive</span>';

          $tbody.append(`
            <tr>
              <td><strong>${escapeHtml(u.full_name)}</strong></td>
              <td>${escapeHtml(u.username)}</td>
              <td>${escapeHtml(u.email)}</td>
              <td>${roleBadge}</td>
              <td>${statusBadge}</td>
              <td style="text-align:right;">
                <button type="button" class="btn-sm btn-secondary btn-edit-user" data-id="${u.user_id}">Edit</button>
                <button type="button" class="btn-sm btn-danger btn-delete-user" data-id="${u.user_id}">Delete</button>
              </td>
            </tr>
          `);
        });
      }

      // Handle Role Preset Adjustments
      $('#role').on('change', function() {
        let r = $(this).val();
        if (r === 'admin') {
          $('.perm-radio[value="edit"]').prop('checked', true);
          $('#permissionsSection').slideUp();
        } else if (r === 'browse') {
          $('.perm-radio[value="view"]').prop('checked', true);
          $('#permissionsSection').slideUp();
        } else {
          // Staff (Granular)
          $('#permissionsSection').slideDown();
        }
      });

      // Edit Button Handler
      $(document).on('click', '.btn-edit-user', function() {
        clearStatusMessage();
        let userId = $(this).data('id');

        $.ajax({
          url: 'user_api.php',
          type: 'GET',
          data: { action: 'get_user', user_id: userId },
          dataType: 'json',
          success: function(res) {
            if (res.status === 'success') {
              let u = res.user;
              let perms = res.permissions;

              $('#user_id').val(u.user_id);
              $('#username').val(u.username);
              $('#full_name').val(u.full_name);
              $('#email').val(u.email);
              $('#password').val('').attr('placeholder', 'Leave blank to retain current password');
              $('#role').val(u.role).trigger('change');
              $('#is_active').prop('checked', parseInt(u.is_active) === 1);

              // Set Matrix Perms
              $('.perm-radio[value="none"]').prop('checked', true);
              $.each(perms, function(slug, p) {
                if (p.can_edit === 1) {
                  $(`input[name="permissions[${slug}]"][value="edit"]`).prop('checked', true);
                } else if (p.can_view === 1) {
                  $(`input[name="permissions[${slug}]"][value="view"]`).prop('checked', true);
                }
              });

              $('#formLegend').text('Edit User Account');
              $('#submitBtn').text('Update User');
              $('#cancelEditBtn').show();
              $('html, body').animate({ scrollTop: $('#userForm').offset().top - 20 }, 200);
            }
          }
        });
      });

      // Delete User Handler
      $(document).on('click', '.btn-delete-user', function() {
        clearStatusMessage();
        let userId = $(this).data('id');

        if (confirm('Are you sure you want to delete this user? This will revoke all system permissions.')) {
          $.ajax({
            url: 'user_api.php',
            type: 'POST',
            data: { action: 'delete_user', user_id: userId },
            dataType: 'json',
            success: function(res) {
              if (res.status === 'success') {
                showStatusMessage(res.message, 'success');
                resetForm();
                loadUsers();
              } else {
                showStatusMessage(res.message || 'Failed to delete user.', 'error');
              }
            }
          });
        }
      });

      // Form Submission
      $('#userForm').on('submit', function(e) {
        e.preventDefault();
        clearStatusMessage();

        let formData = $(this).serialize() + '&action=save_user';
        let $btn = $('#submitBtn');
        $btn.prop('disabled', true).text('Saving...');

        $.ajax({
          url: 'user_api.php',
          type: 'POST',
          data: formData,
          dataType: 'json',
          success: function(res) {
            if (res.status === 'success') {
              showStatusMessage(res.message, 'success');
              resetForm();
              loadUsers();
            } else {
              showStatusMessage(res.message || 'Error saving user.', 'error');
            }
          },
          error: function(xhr) {
            let res = xhr.responseJSON || {};
            showStatusMessage(res.message || 'Server error saving user.', 'error');
          },
          complete: function() {
            $btn.prop('disabled', false).text($('#user_id').val() ? 'Update User' : 'Create User');
          }
        });
      });

      function resetForm() {
        clearStatusMessage();
        $('#userForm')[0].reset();
        $('#user_id').val('');
        $('#password').attr('placeholder', 'Enter strong password');
        $('#role').val('staff').trigger('change');
        $('#is_active').prop('checked', true);
        $('.perm-radio[value="none"]').prop('checked', true);
        $('#formLegend').text('Add New User');
        $('#submitBtn').text('Create User');
        $('#cancelEditBtn').hide();
      }

      $('#resetBtn, #cancelEditBtn').on('click', resetForm);

      function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      }
    });
  </script>
</head>
<body>

  <?php include 'include/header.php'; ?> 

  <div class="dashboard-container">
    <h1>System User & Page Permission Management</h1>

    <div id="status-message" class="alert-box"></div>

    <!-- ADD / EDIT USER FORM -->
    <form id="userForm">
      <input type="hidden" id="user_id" name="user_id" value="">

      <fieldset class="form-grid-section-8 fieldset-relative">
        <legend>
          <h2 id="formLegend">Add New User</h2>
        </legend>

        <div class="field-group" style="--colspan: 4;">
          <label for="full_name">Full Name *</label>
          <input type="text" id="full_name" name="full_name" class="form-control" required placeholder="e.g. Johnathan Smith">
        </div>

        <div class="field-group" style="--colspan: 4;">
          <label for="email">Email Address *</label>
          <input type="email" id="email" name="email" class="form-control" required placeholder="user@nbbtm.org">
        </div>

        <div class="field-group" style="--colspan: 3;">
          <label for="username">Username *</label>
          <input type="text" id="username" name="username" class="form-control" required placeholder="jsmith">
        </div>

        <div class="field-group" style="--colspan: 3;">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="Enter strong password">
        </div>

        <div class="field-group" style="--colspan: 2;">
          <label for="role">Base Role Preset *</label>
          <select id="role" name="role" class="form-control">
            <option value="staff" selected>Staff (Granular Access)</option>
            <option value="admin">Administrator (All Access)</option>
            <option value="browse">Browse Only (All Read-Only)</option>
          </select>
        </div>

        <div class="field-group" style="--colspan: 8; margin-top: 10px;">
          <label style="cursor: pointer;">
            <input type="checkbox" id="is_active" name="is_active" value="1" checked> 
            <strong>Account is Active</strong> (Decheck to prevent user login)
          </label>
        </div>
      </fieldset>

      <!-- GRANULAR PAGE ACCESS MATRIX -->
      <fieldset id="permissionsSection" style="margin-top: 20px;">
        <legend>
          <h2>Granular Page Permissions</h2>
        </legend>
        <p style="color: #64748b; font-size: 0.9rem; margin-top: 0;">Specify View and Edit capabilities per page module for Staff accounts:</p>
        
        <table class="matrix-table">
          <thead>
            <tr>
              <th>Page / Module Name</th>
              <th style="width: 140px; text-align: center;">No Access</th>
              <th style="width: 140px; text-align: center;">View Only</th>
              <th style="width: 140px; text-align: center;">View & Edit</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($AVAILABLE_PAGES as $slug => $label): ?>
              <tr>
                <td><strong><?= htmlspecialchars($label) ?></strong></td>
                <td class="center">
                  <input type="radio" class="perm-radio" name="permissions[<?= $slug ?>]" value="none" checked>
                </td>
                <td class="center">
                  <input type="radio" class="perm-radio" name="permissions[<?= $slug ?>]" value="view">
                </td>
                <td class="center">
                  <input type="radio" class="perm-radio" name="permissions[<?= $slug ?>]" value="edit">
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </fieldset>

      <!-- FORM ACTIONS -->
      <fieldset class="form-grid-section-short-rght" style="margin-top: 20px;">
        <div class="field-group" style="--colspan: 3;">
          <button type="submit" id="submitBtn" class="btn-pulse nbtn">Create User</button>
        </div>
        <div class="field-group" style="--colspan: 3;">
          <button type="button" id="cancelEditBtn" class="btn-secondary" style="display: none;">Cancel Edit</button>
        </div>
        <div class="field-group" style="--colspan: 3;">
          <button type="button" id="resetBtn" class="btn-secondary">Reset</button>
        </div>
      </fieldset>
    </form>

    <br><hr><br>

    <!-- CURRENT USERS DIRECTORY TABLE -->
    <div class="card">
      <h2>Active User Directory</h2>
      <table class="data-table">
        <thead>
          <tr>
            <th>Full Name</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody id="usersTableBody">
          <!-- Dynamically populated via AJAX -->
        </tbody>
      </table>
    </div>

  </div>

  <?php include_once 'include/footer.php'; ?>
</body>
</html>