<?php
// contact_dashboard.php
if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0, // 24 Hours
    'path'     => '/',   // Root path ensures session spans all sub-folders
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}

require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/config/db.php';

$contactTypesList = [];
if (isset($db) && $db instanceof mysqli) {
    $ctRes = $db->query("SELECT contact_type_id, contact_desc FROM contact_type ORDER BY contact_desc ASC");
    if ($ctRes) {
        $contactTypesList = $ctRes->fetch_all(MYSQLI_ASSOC);
        $ctRes->free();
    }
}
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

    .doc-list {
      list-style-type: none;
      padding-left: 0;
      margin: 0;
    }

    .doc-list li {
      padding: 8px 0;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .doc-list li:last-child {
      border-bottom: none;
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

      loadContactList();

      $(document).on('change', '#contact_type_id', function() {
        loadContactList();
      });

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
          dataType: 'text',
          success: function(responseText) {
            let data;
            try {
              const jsonStart = responseText.indexOf('{');
              const jsonEnd = responseText.lastIndexOf('}');
              if (jsonStart < 0 || jsonEnd < jsonStart) {
                throw new Error('No JSON object found in response.');
              }
              data = JSON.parse(responseText.slice(jsonStart, jsonEnd + 1));
            } catch (parseError) {
              console.error("getContact invalid JSON response:", responseText);
              showOnScreenError("Error loading dashboard data. The server returned invalid data.");
              return;
            }
            if (data.error) {
              showOnScreenError("Error: " + data.error);
              return;
            }

            renderDashboard(data);
          },
          error: function(xhr, status, error) {
            console.error("getContact error:", xhr.responseText);
            showOnScreenError("Error loading dashboard data. Please try again.");
          }
        });
      });

      function loadContactList() {
        let contactTypeId = $('#contact_type_id').val() || '';
        let currentSelection = $('#contactID').val();

        $.ajax({
          url: "getLists.php",
          type: 'POST',
          data: {
            contact_type_id: contactTypeId
          },
          dataType: 'text',
          success: function(responseText) {
            let data;
            try {
              const jsonStart = responseText.indexOf('{');
              const jsonEnd = responseText.lastIndexOf('}');
              if (jsonStart < 0 || jsonEnd < jsonStart) {
                throw new Error('No JSON object found in response.');
              }
              data = JSON.parse(responseText.slice(jsonStart, jsonEnd + 1));
            } catch (parseError) {
              console.error("getLists invalid JSON response:", responseText);
              showOnScreenError("Error loading contact list. The server returned invalid data.");
              return;
            }

            if (data.status === 'error' || data.error) {
              showOnScreenError(data.message || data.error || "Failed to load contacts list.");
              return;
            }

            let $dropdown = $('#contactID').empty().append($('<option>', {
              value: '',
              text: '-- Select Contact --'
            }));

            if (data.contacts && Array.isArray(data.contacts) && data.contacts.length > 0) {
              $.each(data.contacts, function(i, item) {
                $dropdown.append($('<option>', {
                  value: item.contact_id,
                  text: item.fullname
                }));
              });
            } else {
              $dropdown.append($('<option>', {
                value: '',
                text: 'No contacts found matching filter'
              }));
            }

            if (currentSelection) {
              $('#contactID').val(currentSelection);
            }
          },
          error: function(xhr, status, error) {
            console.error("getLists error response:", xhr.responseText);
            showOnScreenError("Error loading contact list. Please try again.");
          }
        });
      }

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
        let attachments = data.attachments || [];

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
            .css({
              'background-color': '#475569',
              'color': '#ffffff'
            });

          $('#deceased-group').show();
          $('#dash-deceased').text(c.date_of_death);
        } else {
          $('#dash-active-badge')
            .text(isActive ? 'Active' : 'Inactive')
            .attr('class', 'status-badge ' + (isActive ? 'badge-success' : 'badge-secondary'))
            .css({
              'background-color': '',
              'color': ''
            });

          $('#deceased-group').hide();
        }

        let maritalDesc = c.marital_status_desc || '—';

        if (c.date_of_birth) {
          const [bYear, bMonth, bDay] = c.date_of_birth.split('-');
          const bDate = new Date(bYear, bMonth - 1, bDay);
          $('#dash-dob').text(bDate.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
          }));
        } else {
          $('#dash-dob').text('—');
        }

        $('#dash-gender').text(c.gender === 'M' ? 'Male' : (c.gender === 'F' ? 'Female' : '—'));
        $('#dash-marital').text(maritalDesc);
        $('#dash-head').text(String(c.is_head) === "1" ? 'Yes' : 'No');

        if (String(maritalDesc).toLowerCase() === 'married' && c.anniv_date) {
          const [aYear, aMonth, aDay] = c.anniv_date.split('-');
          const aDate = new Date(aYear, aMonth - 1, aDay);
          $('#dash-anniv').text(aDate.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
          }));
          $('#anniv-group').show();
        } else {
          $('#anniv-group').hide();
        }

        let addressParts = [c.address_1, c.city, c.state, c.zipcode].filter(Boolean);
        $('#dash-address').text(addressParts.length > 0 ? addressParts.join(', ') : '—');
        $('#dash-email').text(c.c_email || '—');

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

        $('#dash-contact-type').text(c.contact_desc || '—');
        $('#dash-joined').text(c.join_date || '—');
        $('#dash-baptized').text(String(c.is_baptized) === "1" ? (c.baptized_date ? `Yes (${c.baptized_date})` : 'Yes') : 'No');

        renderFamilyMembers(familyMembers);
        renderMinistryAssociations(ministries, ministryList, roleList);
        renderAttachmentsDashboard(attachments);

        $('#empty-state').hide();
        $('#contact-dashboard-view').fadeIn(200);
      }

      function renderFamilyMembers(members) {
        let container = $('#family-table-container').empty();

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
          } else if (String(m.is_spouse) === "1") {
            roleTag = 'Spouse';
          } else if (String(m.is_child) === "1") {
            roleTag = 'Child';
          }

          tbody.append(`
            <tr>
              <td><strong>${mName}</strong></td>
              <td><span class="status-badge badge-secondary">${roleTag}</span></td>
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

      $(document).on('click', '.btn-switch-family', function() {
        let targetId = $(this).data('id');
        if (targetId) {
          $('#contactID').val(targetId).trigger('change');
        }
      });

      function renderMinistryAssociations(userMinistries, fullMinistryList, roleList) {
        let container = $('#ministry-table-container').empty();

        if (!userMinistries || userMinistries.length === 0) {
          container.html('<p style="color: #64748b; font-style: italic;">No active ministry or committee involvement recorded.</p>');
          return;
        }

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

        $.each(grouped, function(groupName, items) {
          container.append(`<div class="ministry-group-header"><strong>${groupName}</strong></div>`);

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
                <td>${item.name}</td>
                <td>${item.role}</td>
                <td><span class="status-badge ${item.status === 'Active' ? 'badge-success' : 'badge-secondary'}">${item.status}</span></td>
              </tr>
            `);
          });

          container.append(table);
        });
      }

      function renderAttachmentsDashboard(attachments) {
        let $list = $('#dash-document-list').empty();
        if (!attachments || attachments.length === 0) {
          $list.append('<li style="color: #64748b; font-style: italic;">No attached documents on file.</li>');
          return;
        }

        $.each(attachments, function(i, att) {
          let readUrl = `include/document_reader.php?document_id=${att.document_id}`;
          let downloadUrl = `include/download_document.php?document_id=${att.document_id}`;
          let name = att.document_name || '';

          $list.append(`
            <li>
              <div>
                <span class="status-badge badge-secondary" style="margin-right: 6px;">${escapeHtml(att.document_short_name || 'Document')}</span>
                <strong>${escapeHtml(name)}</strong>
              </div>
              <div style="display: flex; gap: 6px;">
                <a href="${readUrl}" class="btn-switch-family" target="_blank" rel="noopener" style="text-decoration:none; padding:3px 8px;">Read</a>
                <a href="${downloadUrl}" class="btn-switch-family" target="_blank" style="text-decoration:none; padding:3px 8px;">Download</a>
              </div>
            </li>
          `);
        });
      }

      function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
      }

    });
  </script>
</head>

<body>
  <?php include 'include/header.php' ?>

  <div class="dashboard-container">

    <div id="status-message" class="alert-box"></div>

    <!-- TOP CONTROL BAR -->
    <div class="card" style="margin-bottom: 1.5rem;">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">

          <div style="display: flex; align-items: center; gap: 8px;">
            <label for="contact_type_id" style="font-weight: bold; color: #28089a;">Census:</label>
            <select id="contact_type_id" name="contact_type_id" style="min-width: 180px; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 10px;">
              <option value="">-- All Census Types --</option>
              <?php foreach ($contactTypesList as $ct): ?>
                <option value="<?= htmlspecialchars($ct['contact_type_id']) ?>">
                  <?= htmlspecialchars($ct['contact_desc']) ?>
                </option>
              <?php endforeach; ?>
            </select>
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

        <!-- LEFT COLUMN -->
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
            <div id="family-table-container"></div>
          </div>

          <!-- MINISTRY & COMMITTEE INVOLVEMENT -->
          <div class="card">
            <h3>Ministry & Committee Involvement</h3>
            <div id="ministry-table-container"></div>
          </div>

        </div>

        <!-- RIGHT COLUMN -->
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

          <!-- NBBTM CENSUS DETAILS -->
          <div class="card" style="margin-bottom: 1.5rem;">
            <h3>NBBTM Census Details</h3>
            <div class="info-group" id="deceased-group" style="display: none; margin-bottom: 1rem;">
              <span class="info-label">Deceased</span>
              <span class="info-value" id="dash-deceased">—</span>
            </div>
            <div class="info-group" style="margin-bottom: 1rem;">
              <span class="info-label">Contact Type</span>
              <span class="info-value" id="dash-contact-type">—</span>
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

          <!-- IMPORTANT DOCUMENTS CARD -->
          <div class="card">
            <h3>Important Documents</h3>
            <ul id="dash-document-list" class="doc-list"></ul>
          </div>

        </div>

      </div>

    </div>

  </div>

  <?php include_once 'include/footer.php'; ?>
</body>

</html>