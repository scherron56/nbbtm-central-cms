<?php
// Enforce persistent cookie scope before session start
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, // 24 Hours
        'path'     => '/',   // Root path ensures session spans all sub-folders
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Require auth helper
require_once __DIR__ . '/include/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ministry, Committee and Operations Participants - New Beginnings Baptist Tabernacle Ministries</title>
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
    .alert-error { background-color: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }

    .form-control { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.95rem; box-sizing: border-box; color: #28089a; background-color: #ffffff; }
    label { font-weight: 600; color: #28089a; font-size: 0.9rem; margin-bottom: 0.25rem; display: inline-block; }
    .filter-bar { background: #fff; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
    .no-records { text-align: center; padding: 2rem; color: #64748b; font-style: italic; }
  </style>

  <script>
    $(document).ready(function() {
      const filterGroupSelect = $('#filter_group_type');
      const selectMinistry = $('#min_comm_id');
      const clearFilterBtn = $('#clear_filter_btn');
      const membersTableBody = $('#membersTableBody');
      const rosterCard = $('#rosterCard');
      const rosterTitle = $('#rosterTitle');

      function showStatusMessage(message, type = 'error') {
        let $box = $('#status-message');
        $box.removeClass('alert-error').addClass('alert-error').html(message).stop(true, true).fadeIn(200);
      }

      function clearStatusMessage() {
        $('#status-message').fadeOut(200).empty();
      }

      // 1. Initial Load via AJAX
      loadGroupTypes();
      loadCommittees();

      // AJAX Call 1: Load Group Types Dropdown
      function loadGroupTypes() {
        $.ajax({
          url: 'ministry_api.php',
          type: 'GET',
          data: { action: 'get_group_types' },
          dataType: 'json',
          success: function(res) {
            if (res.status === 'success') {
              let options = '<option value="">-- All Group Types --</option>';
              $.each(res.data, function(i, gt) {
                options += `<option value="${gt.min_grp_type_id}">${escapeHtml(gt.min_grp_type_desc)}</option>`;
              });
              filterGroupSelect.html(options);
            } else {
              console.error('Group Types Error:', res.message);
              filterGroupSelect.html('<option value="">Failed to load types</option>');
            }
          },
          error: function(xhr) {
            console.error('AJAX Group Types Error:', xhr.responseText);
            filterGroupSelect.html('<option value="">Error loading data</option>');
          }
        });
      }

      // AJAX Call 2: Load Committees Dropdown (Filtered by Group Type if passed)
      function loadCommittees(filterType = '') {
        $.ajax({
          url: 'ministry_api.php',
          type: 'GET',
          data: { action: 'get_committees', filter_type: filterType },
          dataType: 'json',
          success: function(res) {
            if (res.status === 'success') {
              let options = '<option value="">-- Select Ministry / Committee / Operation --</option>';
              $.each(res.data, function(i, c) {
                options += `<option value="${c.min_comm_id}">${escapeHtml(c.min_comm_name)}</option>`;
              });
              selectMinistry.html(options);
            } else {
              console.error('Committees Error:', res.message);
              selectMinistry.html('<option value="">Failed to load list</option>');
            }
          },
          error: function(xhr) {
            console.error('AJAX Committees Error:', xhr.responseText);
            selectMinistry.html('<option value="">Error loading list</option>');
          }
        });
      }

      // Filter Trigger for Group Type Dropdown
      filterGroupSelect.on('change', function() {
        clearStatusMessage();
        const typeId = $(this).val();
        if (typeId !== '') {
          clearFilterBtn.show();
        } else {
          clearFilterBtn.hide();
        }
        selectMinistry.val('');
        rosterCard.hide();
        loadCommittees(typeId);
      });

      // Clear Filter Button Trigger
      clearFilterBtn.on('click', function() {
        clearStatusMessage();
        filterGroupSelect.val('');
        $(this).hide();
        selectMinistry.val('');
        rosterCard.hide();
        loadCommittees();
      });

      // AJAX Call 3: Fetch Participants when a Ministry/Committee is selected
      selectMinistry.on('change', function() {
        clearStatusMessage();
        const minCommId = $(this).val();
        const minCommName = $(this).find('option:selected').text();

        if (!minCommId) {
          rosterCard.hide();
          return;
        }

        rosterTitle.text(`Participant Roster: ${minCommName}`);
        membersTableBody.html('<tr><td colspan="4" class="no-records">Loading members...</td></tr>');
        rosterCard.show();

        $.ajax({
          url: 'ministry_api.php',
          type: 'GET',
          data: { action: 'get_members', min_comm_id: minCommId },
          dataType: 'json',
          success: function(res) {
            if (res.status === 'success' && res.data.length > 0) {
              renderMembersTable(res.data);
            } else if (res.status === 'success') {
              membersTableBody.html('<tr><td colspan="4" class="no-records">No members found for this ministry or committee.</td></tr>');
            } else {
              showStatusMessage(`Error: ${escapeHtml(res.message)}`);
              membersTableBody.html('<tr><td colspan="4" class="no-records">Unable to load roster.</td></tr>');
            }
          },
          error: function(xhr) {
            console.error('AJAX Members Error:', xhr.responseText);
            showStatusMessage('Error loading member data from server.');
            membersTableBody.html('<tr><td colspan="4" class="no-records">Unable to load roster.</td></tr>');
          }
        });
      });

     function formatPhoneNumber(val) {
        if (!val) return 'N/A';
        let digits = String(val).replace(/\D/g, '');
        if (digits.length === 10) {
          return `(${digits.slice(0,3)}) ${digits.slice(3,6)}-${digits.slice(6)}`;
        }
        return val;
      }

      // Render Table Rows Function
      function renderMembersTable(members) {
        let html = '';
        $.each(members, function(i, m) {
          const fullName = `${escapeHtml(m.first_name)} ${escapeHtml(m.last_name)}`;
          const role = escapeHtml(m.role_desc || 'Unassigned');
          const phone = m.phone_1 ? escapeHtml(formatPhoneNumber(m.phone_1)) : 'N/A';
          const email = m.c_email ? `<a href="mailto:${escapeHtml(m.c_email)}" class="link-btn">${escapeHtml(m.c_email)}</a>` : 'N/A';

          html += `
            <tr>
              <td><strong>${fullName}</strong></td>
              <td>${role}</td>
              <td>${phone}</td>
              <td>${email}</td>
            </tr>
          `;
        });
        membersTableBody.html(html);
      }

      function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
      }
    });
  </script>
</head>
<body>

  <?php include 'include/header.php'; ?> 

  <div class="dashboard-container">

    <div id="status-message" class="alert-box"></div>

    <!-- SELECTION & FILTER BAR -->
    <div class="filter-bar">
      <div>
        <label for="filter_group_type">Filter Group Type:</label>
        <select id="filter_group_type" class="form-control" style="width: auto; min-width: 200px;">
          <option value="">Loading Group Types...</option>
        </select>
        <button type="button" id="clear_filter_btn" class="btn-secondary" style="padding: 6px 12px; font-size: 0.85rem; display: none;">Clear Filter</button>
      </div>

      <div style="flex-grow: 1; max-width: 400px;">
        <label for="min_comm_id">Select Ministry / Committee / Operation: *</label>
        <select id="min_comm_id" class="form-control">
          <option value="">Loading list...</option>
        </select>
      </div>
    </div>

    <!-- MEMBERS ROSTER DATA TABLE -->
    <div id="rosterCard" class="card" style="display: none;">
      <h3 id="rosterTitle">Participant Roster</h3>
      <table class="data-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Role</th>
            <th>Phone Number</th>
            <th>Email</th>
          </tr>
        </thead>
        <tbody id="membersTableBody">
          <!-- Populated dynamically via AJAX -->
        </tbody>
      </table>
    </div>

  </div>
<?php include_once 'include/footer.php'; ?>
</body>
</html>