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

// Require auth helper
require_once __DIR__ . '/include/auth.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Dashboard - NBBTM</title>
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
    .alert-error {
      background-color: #fee2e2;
      border: 1px solid #fca5a5;
      color: #991b1b;
    }

    /* Profile Summary Grid Layout */
    .profile-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.25rem;
      margin-bottom: 1.5rem;
    }

    .info-group {
      display: flex;
      flex-direction: column;
    }

    .info-label {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #64748b;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }

    .info-value {
      font-size: 1rem;
      color: #1e293b;
      font-weight: 500;
    }

    .status-badge {
      display: inline-block;
      padding: 0.25rem 0.6rem;
      border-radius: 9999px;
      font-size: 0.8rem;
      font-weight: 600;
    }

    .badge-success {
      background-color: #d1fae5;
      color: #065f46;
    }

    .badge-secondary {
      background-color: #f1f5f9;
      color: #475569;
    }

    /* Dynamic Group Type Header for Ministries */
    .ministry-group-header {
      color: #28089a;
      font-size: 1.05rem;
      margin-top: 1.25rem;
      margin-bottom: 0.5rem;
      border-bottom: 2px solid #e2e8f0;
      padding-bottom: 0.25rem;
    }

    .empty-state {
      text-align: center;
      padding: 2.5rem 1rem;
      color: #64748b;
    }

    .btn-switch-family {
      background-color: #28089a;
      color: #ffffff;
      border: none;
      padding: 4px 10px;
      border-radius: 4px;
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
      transition: background-color 0.15s ease;
    }

    .btn-switch-family:hover {
      background-color: #1e0573;
    }
  </style>

  <script>
    $(document).ready(function() {

      function showOnScreenError(msg) {
        $('#status-message').addClass('alert-error').html(msg).stop(true, true).fadeIn(200);
      }

      function clearOnScreenMessage() {
        $('#status-message').fadeOut(200).empty();
      }

      // Load initial dropdown list
      loadContactList();

      // Toggle list filtering options
      $(document).on('change', 'input[name="contact_filter"], input[name="age_filter"]', function() {
        loadContactList();
      });

      // Fetch and display dashboard details when a contact is selected
      $('#contactID').change(function() {
        clearOnScreenMessage();
        let contactid = $(this).val();

        if (!contactid) {
          $('#contact-dashboard-view').hide();
          $('#empty-state').show();
          return;
        }

        $.ajax({
          url: 'getContact.php',
          type: 'POST',
          data: {
            contactid: contactid
          },
          dataType: 'json',
          success: function(data) {
            if (data.error) {
              showOnScreenError("Error: " + data.error);
              return;
            }

            renderDashboard(data);
          },
          error: function(xhr, status, error) {
            showOnScreenError("Error loading dashboard data. Please try again.");
          }
        });
      });

      function loadContactList() {
        let membersOnly = $('input[name="contact_filter"]:checked').val();
        let ageFilter = $('input[name="age_filter"]:checked').val();
        let currentSelection = $('#contactID').val();

        $.ajax({
          url: "getLists.php",
          type: 'POST',
          data: {
            members_only: membersOnly,
            age_filter: ageFilter
          },
          dataType: 'json',
          success: function(data) {
            $('#contactID').empty().append($('<option>', {
              value: '',
              text: '-- Select Contact --'
            }));
            if (data.contacts && Array.isArray(data.contacts)) {
              $.each(data.contacts, function(i, item) {
                $('#contactID').append($('<option>', {
                  value: item.contact_id,
                  text: item.fullname
                }));
              });
            }
            if (currentSelection) {
              $('#contactID').val(currentSelection);
            }
          }
        });
      }

      // Helper to format 10-digit phone numbers as (XXX) XXX-XXXX
      function formatPhoneNumber(val) {
        if (!val) return '';
        let digits = String(val).replace(/\D/g, '');
        if (digits.length === 10) {
          return `(${digits.slice(0,3)}) ${digits.slice(3,6)}-${digits.slice(6)}`;
        }
        return val;
      }

      function renderDashboard(data) {
        let c = data.contact || {};
        let familyMembers = data.familyMembers || [];
        let ministries = data.ministries || [];
        let ministryList = data.ministryList || [];
        let roleList = data.roleList || [];

        // Primary Information Header
        let fullName = [c.first_name, c.middle_name, c.last_name].filter(Boolean).join(' ');
        $('#dash-fullname').text(fullName || 'N/A');

        let isMember = String(c.is_member) === "1" || c.is_member === true;
        let isActive = String(c.is_active) === "1" || c.is_active === true;
        let isDeceased = c.date_of_death ? true : false;

        $('#dash-member-badge')
          .text(isMember ? 'Member' : 'Non-Member')
          .attr('class', 'status-badge ' + (isMember ? 'badge-success' : 'badge-secondary'));

        if (isDeceased) {
          $('#dash-active-badge')
            .text('Deceased')
            .attr('class', 'status-badge badge-secondary')
            .css({'background-color': '#475569', 'color': '#ffffff'}); // Distinct dark pill
            
          $('#deceased-group').show();
          $('#dash-deceased').text(c.date_of_death);
        } else {
          $('#dash-active-badge')
            .text(isActive ? 'Active' : 'Inactive')
            .attr('class', 'status-badge ' + (isActive ? 'badge-success' : 'badge-secondary'))
            .css({'background-color': '', 'color': ''}); // Reset custom styling
            
          $('#deceased-group').hide();
        }

        // Demographics
        let maritalDesc = c.marital_status_desc || '—';
        $('#dash-dob').text(c.date_of_birth || '—');
        $('#dash-gender').text(c.gender === 'M' ? 'Male' : (c.gender === 'F' ? 'Female' : '—'));
        $('#dash-marital').text(maritalDesc);
        $('#dash-head').text(String(c.is_head) === "1" ? 'Yes' : 'No');

        if (String(maritalDesc).toLowerCase() === 'married' && c.anniv_date) {
            // Manually parse to prevent timezone day-shifting
            const [year, month, day] = c.anniv_date.split('-');
            const d = new Date(year, month - 1, day);
            const options = { month: 'short', day: 'numeric' };
            $('#dash-anniv').text(d.toLocaleDateString('en-US', options));
            $('#anniv-group').show();
        } else {
            $('#anniv-group').hide();
        }

        // Address & Email
        let addressParts = [c.address_1, c.city, c.state, c.zipcode].filter(Boolean);
        $('#dash-address').text(addressParts.length > 0 ? addressParts.join(', ') : '—');
        $('#dash-email').text(c.c_email || '—');

        // Format Phone Numbers with Actual Phone Type Text Descriptions
        let p1Type = c.phone_1_type_desc ? ` (${c.phone_1_type_desc})` : '';
        let p2Type = c.phone_2_type_desc ? ` (${c.phone_2_type_desc})` : '';
        let p3Type = c.phone_3_type_desc ? ` (${c.phone_3_type_desc})` : '';

        $('#dash-phone1').text(c.phone_1 ? `${formatPhoneNumber(c.phone_1)}${p1Type}` : '—');
        $('#dash-phone2').text(c.phone_2 ? `${formatPhoneNumber(c.phone_2)}${p2Type}` : '—');

        if (c.emergency_contact || c.phone_3) {
          let emergencyText = [c.emergency_contact, formatPhoneNumber(c.phone_3)].filter(Boolean).join(' - ');
          $('#dash-emergency').text(`${emergencyText}${p3Type}`);
        } else {
          $('#dash-emergency').text('—');
        }

        // Membership Info
        $('#dash-joined').text(c.join_date || '—');
        $('#dash-baptized').text(String(c.is_baptized) === "1" ? (c.baptized_date ? `Yes (${c.baptized_date})` : 'Yes') : 'No');

        // Render Sub-Tables
        renderFamilyMembers(familyMembers);
        renderMinistryAssociations(ministries, ministryList, roleList);

        // Show Dashboard
        $('#empty-state').hide();
        $('#contact-dashboard-view').fadeIn(200);
      }

      function renderFamilyMembers(members) {
        let container = $('#family-table-container');
        container.empty();

        if (!members || members.length === 0) {
          container.html('<p style="color: #64748b; font-style: italic;">No associated family members found in this household.</p>');
          return;
        }

        let table = $(`
          <table class="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Household Role</th>
                <th>Membership</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        `);

        let tbody = table.find('tbody');
        $.each(members, function(i, m) {
          let mName = `${m.first_name || ''} ${m.last_name || ''}`.trim() || 'N/A';
          
          let roleTag = 'Family Member';
          if (String(m.is_head) === "1") {
            roleTag = 'Head of Household';
          } else if (String(m.is_child) === "1") {
            roleTag = 'Child';
          }

          let isMem = String(m.is_member) === "1";

          tbody.append(`
            <tr>
              <td><strong>${mName}</strong></td>
              <td><span class="status-badge badge-secondary">${roleTag}</span></td>
              <td><span class="status-badge ${isMem ? 'badge-success' : 'badge-secondary'}">${isMem ? 'Member' : 'Non-Member'}</span></td>
              <td style="text-align: right;">
                <button type="button" class="btn-switch-family" data-id="${m.contact_id}">
                  View Dashboard
                </button>
              </td>
            </tr>
          `);
        });

        container.append(table);
      }

      // Quick Switch to Family Member's Dashboard
      $(document).on('click', '.btn-switch-family', function() {
        let targetId = $(this).data('id');
        if (targetId) {
          $('#contactID').val(targetId).trigger('change');
        }
      });

      function renderMinistryAssociations(userMinistries, fullMinistryList, roleList) {
        let container = $('#ministry-table-container');
        container.empty();

        if (!userMinistries || userMinistries.length === 0) {
          container.html('<p style="color: #64748b; font-style: italic;">No active ministry or committee involvement recorded.</p>');
          return;
        }

        // Map roles & ministry details for easy lookup
        let rolesMap = {};
        $.each(roleList, function(i, r) {
          rolesMap[r.role_id] = r.role_desc;
        });

        let minDetailsMap = {};
        $.each(fullMinistryList, function(i, m) {
          minDetailsMap[m.min_comm_id] = {
            name: m.min_comm_name,
            groupType: m.min_grp_type_desc || 'General Associations'
          };
        });

        // Group active user ministries by Group Type
        let grouped = {};
        $.each(userMinistries, function(i, m) {
          let detail = minDetailsMap[m.min_comm_id] || {
            name: `Ministry #${m.min_comm_id}`,
            groupType: 'General'
          };
          let gType = detail.groupType;

          if (!grouped[gType]) grouped[gType] = [];
          grouped[gType].push({
            name: detail.name,
            role: rolesMap[m.role_id] || 'Member',
            status: String(m.is_active) === "1" ? 'Active' : 'Inactive'
          });
        });

        // Render grouped tables
        $.each(grouped, function(groupName, items) {
          container.append(`<div class="ministry-group-header">${groupName}</div>`);

          let table = $(`
          <table class="data-table">
            <thead>
              <tr>
                <th>Ministry / Committee</th>
                <th>Role / Position</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        `);

          let tbody = table.find('tbody');
          $.each(items, function(i, item) {
            tbody.append(`
            <tr>
              <td><strong>${item.name}</strong></td>
              <td>${item.role}</td>
              <td><span class="status-badge ${item.status === 'Active' ? 'badge-success' : 'badge-secondary'}">${item.status}</span></td>
            </tr>
          `);
          });

          container.append(table);
        });
      }

    });
  </script>
</head>

<body>
  <?php require_once("config/db.php") ?>
  <?php include 'include/header.php' ?>

  <div class="dashboard-container">

    <div id="status-message" class="alert-box"></div>

    <!-- TOP CONTROL BAR -->
    <div class="card" style="margin-bottom: 1.5rem;">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">

          <!-- Membership Filter Group -->
          <div>
            <label style="font-weight: bold; color: #28089a; margin-right: 10px;">Membership:</label>
            <label style="cursor: pointer; margin-right: 10px;">
              <input type="radio" name="contact_filter" value="0" checked> All
            </label>
            <label style="cursor: pointer; margin-right: 10px;">
              <input type="radio" name="contact_filter" value="1"> Members
            </label>
            <label style="cursor: pointer;">
              <input type="radio" name="contact_filter" value="2"> Non-Members
            </label>
          </div>

          <!-- Age Filter Group -->
          <div>
            <label style="font-weight: bold; color: #28089a; margin-right: 10px;">Age Group:</label>
            <label style="cursor: pointer; margin-right: 10px;">
              <input type="radio" name="age_filter" value="0" checked> All
            </label>
            <label style="cursor: pointer; margin-right: 10px;">
              <input type="radio" name="age_filter" value="1"> Adults
            </label>
            <label style="cursor: pointer;">
              <input type="radio" name="age_filter" value="2"> Children
            </label>
          </div>

        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-grow: 1; max-width: 400px;">
          <select id="contactID" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 10px;">
            <option value="">-- Select Contact --</option>
          </select>
        </div>
      </div>
    </div>

    <!-- INITIAL EMPTY STATE -->
    <div id="empty-state" class="card empty-state">
      <h2>No Contact Selected</h2>
      <p>Please select a member or contact from the dropdown above to view their dashboard.</p>
    </div>

    <!-- DASHBOARD VIEW CONTAINER -->
    <div id="contact-dashboard-view" style="display: none;">

      <!-- MAIN HEADER CARD -->
      <div class="card" style="margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
          <div>
            <h1 id="dash-fullname" style="margin: 0; font-size: 1.8rem; color: #28089a;">—</h1>
            <div style="display: flex; gap: 8px; margin-top: 6px;">
              <span id="dash-member-badge" class="status-badge badge-secondary">—</span>
              <span id="dash-active-badge" class="status-badge badge-secondary">—</span>
            </div>
          </div>
        </div>
      </div>

      <!-- MAIN LAYOUT (2 COLUMNS) -->
      <div class="dashboard-layout">

        <!-- LEFT COLUMN: PERSONAL DETAILS, FAMILY & MINISTRIES -->
        <div class="main-content">

          <!-- PERSONAL INFORMATION -->
          <div class="card">
            <h3>Personal Information</h3>
            <div class="profile-grid">
              <div class="info-group">
                <span class="info-label">Date of Birth</span>
                <span class="info-value" id="dash-dob">—</span>
              </div>
              <div class="info-group">
                <span class="info-label">Gender</span>
                <span class="info-value" id="dash-gender">—</span>
              </div>
              <div class="info-group">
                <span class="info-label">Marital Status</span>
                <span class="info-value" id="dash-marital">—</span>
              </div>
              <div class="info-group">
                <span class="info-label">Head of Household</span>
                <span class="info-value" id="dash-head">—</span>
              </div>
              <div class="info-group" id="anniv-group" style="display: none;">
                <span class="info-label">Anniversary</span>
                <span class="info-value" id="dash-anniv">—</span>
              </div>
            </div>
          </div>

          <!-- HOUSEHOLD / FAMILY MEMBERS -->
          <div class="card">
            <h3>Household / Family Members</h3>
            <div id="family-table-container">
              <!-- Dynamically populated table -->
            </div>
          </div>

          <!-- MINISTRY & COMMITTEE INVOLVEMENT -->
          <div class="card">
            <h3>Ministry & Committee Involvement</h3>
            <div id="ministry-table-container">
              <!-- Dynamically populated tables -->
            </div>
          </div>

        </div>

        <!-- RIGHT COLUMN: CONTACT & MEMBERSHIP -->
        <div class="sidebar">

          <!-- CONTACT DETAILS -->
          <div class="card">
            <h3>Contact Details</h3>
            <div class="info-group" style="margin-bottom: 1rem;">
              <span class="info-label">Address</span>
              <span class="info-value" id="dash-address">—</span>
            </div>
            <div class="info-group" style="margin-bottom: 1rem;">
              <span class="info-label">Email</span>
              <span class="info-value" id="dash-email">—</span>
            </div>
            <div class="info-group" style="margin-bottom: 1rem;">
              <span class="info-label">Primary Phone</span>
              <span class="info-value" id="dash-phone1">—</span>
            </div>
            <div class="info-group" style="margin-bottom: 1rem;">
              <span class="info-label">Secondary Phone</span>
              <span class="info-value" id="dash-phone2">—</span>
            </div>
            <div class="info-group">
              <span class="info-label">Emergency Contact</span>
              <span class="info-value" id="dash-emergency">—</span>
            </div>
          </div>

          <!-- MEMBERSHIP DETAILS -->
          <div class="card">
            <h3>Membership Details</h3>
            <div class="info-group" id="deceased-group" style="display: none; margin-bottom: 1rem;">
              <span class="info-label">Deceased</span>
              <span class="info-value" id="dash-deceased">—</span>
            </div>
            <div class="info-group" style="margin-bottom: 1rem;">
              <span class="info-label">Date Joined</span>
              <span class="info-value" id="dash-joined">—</span>
            </div>
            <div class="info-group">
              <span class="info-label">Baptized</span>
              <span class="info-value" id="dash-baptized">—</span>
            </div>
          </div>

        </div>

      </div>

    </div>

  </div>
  
  <?php include_once 'include/footer.php'; ?>

</body>

</html>