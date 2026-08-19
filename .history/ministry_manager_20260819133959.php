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
$canEdit = canEdit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ministry Management - New Beginnings Baptist Tabernacle Ministries</title>
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
    .read-only-banner {
      background-color: #f1f5f9;
      border-left: 4px solid #0ea5e9;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 4px;
      color: #334155;
    }

    .form-control { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.95rem; box-sizing: border-box; color: #28089a; background-color: #ffffff; }
    textarea.form-control { resize: vertical; min-height: 70px; }
    label { font-weight: 600; color: #28089a; font-size: 0.9rem; margin-bottom: 0.25rem; display: inline-block; }
    .form-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1rem; grid-column: span 12; }
    .checkbox-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600; color: #28089a; }
    .badge { padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; }
    .badge-success { background-color: #d1fae5; color: #065f46; }
    .badge-danger { background-color: #fee2e2; color: #991b1b; }
    .filter-bar { background: #fff; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); display: flex; align-items: center; gap: 1rem; }
    
    /* View Details Modal Content Styling */
    .modal-content {
      background: #ffffff; padding: 2.5rem; border-radius: 8px;
      width: 90%; max-width: 600px; position: relative;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }
    .modal-close {
      position: absolute; top: 10px; right: 15px; background: none;
      border: none; font-size: 1.5rem; font-weight: bold; color: #64748b; cursor: pointer;
    }
    .modal-close:hover { color: #dc2626; }
  </style>

  <script>
    $(document).ready(function() {
      const CAN_EDIT = <?php echo $canEdit ? 'true' : 'false'; ?>;
      const filterSelect = $('#filter_type');
      const formTypeSelect = $('#min_comm_type_id');
      const clearFilterBtn = $('#clear_filter_btn');
      const ministryForm = $('#ministryForm');
      const tableBody = $('#committeesTableBody');
      const formTitle = $('#form-title');
      const cancelEditBtn = $('#cancel_edit_btn');
      const saveBtn = $('#save_btn');

      function showStatusMessage(message, type = 'success') {
        let $box = $('#status-message');
        $box.removeClass('alert-success alert-error')
            .addClass(type === 'success' ? 'alert-success' : 'alert-error')
            .html(message)
            .stop(true, true)
            .fadeIn(200);

        if (type === 'success') {
          setTimeout(function() { $box.fadeOut(500); }, 5000);
        }
      }

      function clearStatusMessage() {
        $('#status-message').fadeOut(200).empty();
      }

      // Initial AJAX Setup
      loadGroupTypes();
      loadCommittees();

      // 1. Fetch Group Types via AJAX
      function loadGroupTypes() {
        $.ajax({
          url: 'ministry_api.php',
          type: 'GET',
          data: { action: 'get_group_types' },
          dataType: 'json',
          success: function(res) {
            if (res.status === 'success') {
              let options = '<option value="">-- All Group Types --</option>';
              let formOptions = '<option value="">-- Select Group Type --</option>';
              
              $.each(res.data, function(i, gt) {
                options += `<option value="${gt.min_grp_type_id}">${escapeHtml(gt.min_grp_type_desc)}</option>`;
                formOptions += `<option value="${gt.min_grp_type_id}">${escapeHtml(gt.min_grp_type_desc)}</option>`;
              });
              
              filterSelect.html(options);
              if (formTypeSelect.length) formTypeSelect.html(formOptions);
            } else {
              console.error('Group Types Error:', res.message);
              filterSelect.html('<option value="">Failed to load</option>');
              if (formTypeSelect.length) formTypeSelect.html('<option value="">Failed to load</option>');
            }
          },
          error: function(xhr) {
            console.error('AJAX Group Types Error:', xhr.responseText);
            filterSelect.html('<option value="">Error loading data</option>');
            if (formTypeSelect.length) formTypeSelect.html('<option value="">Error loading data</option>');
          }
        });
      }

      // 2. Filter Trigger
      filterSelect.on('change', function() {
        if ($(this).val() !== '') {
          clearFilterBtn.show();
        } else {
          clearFilterBtn.hide();
        }
        loadCommittees($(this).val());
      });

      // Clear Filter
      clearFilterBtn.on('click', function() {
        filterSelect.val('');
        $(this).hide();
        loadCommittees();
      });

      // 3. Load Committees Function
      function loadCommittees(filterType = '') {
        $.ajax({
          url: 'ministry_api.php',
          type: 'GET',
          data: { action: 'get_committees', filter_type: filterType },
          dataType: 'json',
          success: function(res) {
            if (res.status === 'success') {
              renderTable(res.data);
            } else {
              showStatusMessage('Backend Error: ' + res.message, 'error');
            }
          },
          error: function(xhr) {
            console.error('AJAX Load Error:', xhr.responseText);
            tableBody.html('<tr><td colspan="6" style="text-align: center; color: #dc2626; padding: 2rem;">Error loading data from server. Check console.</td></tr>');
          }
        });
      }

      // 4. Render Table
      function renderTable(data) {
        tableBody.empty();
        if (data && data.length > 0) {
          let html = '';
          $.each(data, function(index, row) {
            const badgeClass = row.is_active == 1 ? 'badge-success' : 'badge-danger';
            const badgeText = row.is_active == 1 ? 'Active' : 'Inactive';
            const shortDesc = row.description ? escapeHtml(row.description).substring(0, 50) + (row.description.length > 50 ? '...' : '') : '';

            // Generate view button for all roles, Edit/Delete for editors
            let viewBtn = `<a href="#" class="link-btn view-btn" data-id="${row.min_comm_id}">View</a>`;
            let actionsHtml = CAN_EDIT ? `
              ${viewBtn} | 
              <a href="#" class="link-btn edit-btn" data-id="${row.min_comm_id}">Edit</a> | 
              <a href="#" class="link-btn delete-btn" style="color: #dc2626;" data-id="${row.min_comm_id}">Delete</a>
            ` : viewBtn;

            html += `
              <tr>
                <td>${row.min_comm_id}</td>
                <td><strong>${escapeHtml(row.min_comm_name)}</strong></td>
                <td>${escapeHtml(row.min_grp_type_desc || 'Unassigned')}</td>
                <td>${shortDesc}</td>
                <td><span class="badge ${badgeClass}">${badgeText}</span></td>
                <td style="text-align: right;">${actionsHtml}</td>
              </tr>
            `;
          });
          tableBody.html(html);
        } else {
          tableBody.html('<tr><td colspan="6" style="text-align: center; color: #64748b; padding: 2rem;">No committees found.</td></tr>');
        }
      }

      // 5. View Details Click Handler (Opens Modal)
      tableBody.on('click', '.view-btn', function(e) {
        e.preventDefault();
        clearStatusMessage();
        const id = $(this).data('id');
        
        $.ajax({
          url: 'ministry_api.php',
          type: 'GET',
          data: { action: 'get_committee', id: id },
          dataType: 'json',
          success: function(res) {
            if (res.status === 'success' && res.data) {
              const d = res.data;
              $('#modal-comm-name').text(d.min_comm_name);
              $('#modal-desc').text(d.description ? d.description : 'No description provided.');
              $('#modal-mission').text(d.mission_purpose ? d.mission_purpose : 'No mission or purpose recorded.');
              $('#view-details-modal').removeClass('hidden');
            } else {
              showStatusMessage('Could not load details.', 'error');
            }
          },
          error: function() {
            showStatusMessage('Server error while retrieving details.', 'error');
          }
        });
      });

      // Close Details Modal
      $('#close-details-btn').on('click', function() {
        $('#view-details-modal').addClass('hidden');
      });

      $('#view-details-modal').on('click', function(e) {
        if (e.target === this) {
          $(this).addClass('hidden');
        }
      });

      // 6. Form Submit (Save / Update)
      ministryForm.on('submit', function(e) {
        e.preventDefault();
        if (!CAN_EDIT) return;
        clearStatusMessage();
        
        let formData = new FormData(this);
        formData.append('action', 'save_committee');

        $.ajax({
          url: 'ministry_api.php',
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          dataType: 'json',
          success: function(res) {
            if (res.status === 'success') {
              showStatusMessage(res.message, 'success');
              resetForm();
              loadCommittees(filterSelect.val());
            } else {
              showStatusMessage('Save Error: ' + res.message, 'error');
            }
          },
          error: function(xhr) {
            showStatusMessage('Server error while saving committee.', 'error');
          }
        });
      });

      // 7. Edit Click
      tableBody.on('click', '.edit-btn', function(e) {
        e.preventDefault();
        if (!CAN_EDIT) return;
        clearStatusMessage();

        const id = $(this).data('id');
        
        $.ajax({
          url: 'ministry_api.php',
          type: 'GET',
          data: { action: 'get_committee', id: id },
          dataType: 'json',
          success: function(res) {
            if (res.status === 'success' && res.data) {
              const d = res.data;
              $('#min_comm_id').val(d.min_comm_id);
              $('#min_comm_name').val(d.min_comm_name);
              $('#min_comm_type_id').val(d.min_comm_type_id);
              $('#description').val(d.description || '');
              $('#mission_purpose').val(d.mission_purpose || '');
              $('#is_active').prop('checked', d.is_active == 1);

              formTitle.text('Edit Ministry Committee');
              saveBtn.text('Update Committee');
              cancelEditBtn.show();
              
              $('html, body').animate({ scrollTop: $('#ministryForm').offset().top - 20 }, 'fast');
            }
          }
        });
      });

      // 8. Delete Click
      tableBody.on('click', '.delete-btn', function(e) {
        e.preventDefault();
        if (!CAN_EDIT) return;
        clearStatusMessage();

        if (confirm('Are you sure you want to delete this committee?')) {
          const id = $(this).data('id');
          
          $.ajax({
            url: 'ministry_api.php',
            type: 'POST',
            data: { action: 'delete_committee', id: id },
            dataType: 'json',
            success: function(res) {
              if (res.status === 'success') {
                showStatusMessage(res.message, 'success');
                loadCommittees(filterSelect.val());
              } else {
                showStatusMessage('Delete Error: ' + res.message, 'error');
              }
            }
          });
        }
      });

      // Cancel Edit
      cancelEditBtn.on('click', function() {
        resetForm();
      });

      function resetForm() {
        clearStatusMessage();
        if (ministryForm.length) {
          ministryForm[0].reset();
          $('#min_comm_id').val('');
          formTitle.text('Add New Ministry Committee');
          saveBtn.text('Save Committee');
          cancelEditBtn.hide();
        }
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

    <!-- FILTER BAR -->
    <div class="filter-bar">
      <label for="filter_type">Filter by Group Type:</label>
      <select id="filter_type" name="filter_type">
        <option value="">Loading Group Types...</option>
      </select>
      <button type="button" id="clear_filter_btn" class="btn-secondary" style="padding: 6px 12px; font-size: 0.85rem; display: none;">Clear Filter</button>
    </div>

    <!-- DATA TABLE -->
    <div class="card">
      <h3>Ministry/Committees List</h3>
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Committee Name</th>
            <th>Group Type</th>
            <th>Description</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody id="committeesTableBody">
          <!-- Populated dynamically via AJAX -->
        </tbody>
      </table>
    </div>

    <br>

    <?php if ($canEdit): ?>
      <!-- ENTRY / EDIT FORM -->
      <form id="ministryForm">
        <input type="hidden" id="min_comm_id" name="min_comm_id" value="">
        <fieldset class="form-grid-section-9 fieldset-relative">
          <legend>
            <h2 id="form-title">Add/Update Ministry/Committee</h2>
          </legend>
          
          <div class="field-group" style="--colspan: 5;">
            <label for="min_comm_name">Name *</label>
            <input type="text" id="min_comm_name" name="min_comm_name" class="form-control" required>
          </div>

          <div class="field-group" style="--colspan: 3;">
            <label for="min_comm_type_id">Group Type *</label>
            <select id="min_comm_type_id" name="min_comm_type_id" class="form-control" required>
              <option value="">Loading Group Types...</option>
            </select>
          </div>

          <div class="field-group" style="--colspan: 9;">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="form-control" rows="2"></textarea>
          </div>

          <div class="field-group" style="--colspan: 9;">
            <label for="mission_purpose">Mission</label>
            <textarea id="mission_purpose" name="mission_purpose" class="form-control" rows="3"></textarea>
          </div>

          <div class="field-group" style="--colspan: 1;">
            <label class="checkbox-label">
              <input type="checkbox" id="is_active" name="is_active" value="1" checked>
              Is Active
            </label>
          </div>

          <div class="form-actions">
            <button type="button" id="cancel_edit_btn" class="btn-secondary" style="display: none; line-height: 2.2;">Cancel Edit</button>
            <button type="submit" id="save_btn" class="btn-primary">Save Committee</button>
          </div>
        </fieldset>
      </form>
    <?php else: ?>
      <div class="read-only-banner">
        <strong>Read-Only Mode:</strong> You must be signed in as staff or an administrator to add or modify ministry committee details.
      </div>
    <?php endif; ?>

  </div>
  
  <!-- VIEW DETAILS MODAL -->
  <div id="view-details-modal" class="modal-overlay hidden">
    <div class="modal-content">
      <button type="button" class="modal-close" id="close-details-btn">&times;</button>
      <h3 id="modal-comm-name" style="color: #28089a; margin-top: 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem; margin-bottom: 1.25rem;">Ministry Name</h3>
      
      <div style="margin-bottom: 1.5rem;">
        <h4 style="color: #64748b; margin: 0 0 0.25rem 0; font-size: 0.85rem; text-transform: uppercase;">Description</h4>
        <p id="modal-desc" style="font-size: 1rem; color: #1e293b; margin: 0; white-space: pre-wrap; line-height: 1.5;"></p>
      </div>
      
      <div>
        <h4 style="color: #64748b; margin: 0 0 0.25rem 0; font-size: 0.85rem; text-transform: uppercase;">Mission & Purpose</h4>
        <p id="modal-mission" style="font-size: 1rem; color: #1e293b; margin: 0; white-space: pre-wrap; line-height: 1.5;"></p>
      </div>
    </div>
  </div>

<?php include_once 'include/footer.php'; ?>
</body>
</html>