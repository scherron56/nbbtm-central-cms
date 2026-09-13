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
$canEdit = canEdit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Live Event Attendance Check-In</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
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

    .checkin-wrapper {
      width: 100%;
      max-width: 1150px;
      margin: 2rem auto;
    }
    .status-badge {
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 0.8rem;
      font-weight: bold;
      text-transform: uppercase;
    }
    .badge-present {
      background: #dcfce7;
      color: #15803d;
    }
    .badge-absent {
      background: #f1f5f9;
      color: #64748b;
    }
    .badge-completed {
      background: #dbeafe;
      color: #1e40af;
    }
    .badge-incomplete {
      background: #fee2e2;
      color: #991b1b;
    }
    .search-filter-box {
      display: flex;
      gap: 15px;
      margin-bottom: 15px;
      align-items: center;
    }
    .search-filter-box input {
      flex: 1;
      height: 38px;
      padding: 0 12px;
      border-radius: 6px;
      border: 1px solid #cbd5e1;
    }
    select {
      height: 38px;
      padding: 0 10px;
      border-radius: 6px;
      border: 1px solid #cbd5e1;
      color: #28089a;
      font-family: inherit;
    }
    .btn-toggle-present {
      background-color: #16a34a;
      color: #ffffff;
      padding: 6px 14px;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-toggle-absent {
      background-color: #0ea5e9;
      color: #ffffff;
      padding: 6px 14px;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-toggle-disabled {
      background-color: #94a3b8 !important;
      color: #ffffff !important;
      cursor: not-allowed !important;
      opacity: 0.6;
    }
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script>
    $(document).ready(function() {
        const CAN_EDIT = <?php echo $canEdit ? 'true' : 'false'; ?>;
        let currentRegistrations = [];
        let currentCheckedInIds = [];
        let currentEvent = null;

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

        const urlParams = new URLSearchParams(window.location.search);
        const preselectedEventId = urlParams.get('prg_evnt_id');

        loadEventsDropdown(preselectedEventId);

        function loadEventsDropdown(selectedId = null) {
            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_events' },
                dataType: 'json',
                success: function(data) {
                    if (data.success && data.programs_events) {
                        let $select = $('#prg_evnt_id');
                        $select.find('option:not(:first)').remove();

                        // Filter to only include events requiring registration
                        let registrableEvents = data.programs_events.filter(ev => parseInt(ev.requires_registration) === 1);

                        if (registrableEvents.length === 0) {
                            $select.append('<option value="" disabled>No upcoming events requiring registration</option>');
                        } else {
                            $.each(registrableEvents, function(i, ev) {
                                let dispDate = ev.primary_start ? ` (${ev.primary_start.substring(0, 10)})` : '';
                                $select.append(`<option value="${ev.prg_evnt_id}">${ev.prg_evnt_name}${dispDate}</option>`);
                            });
                        }

                        if (selectedId) {
                            $select.val(selectedId).trigger('change');
                        }
                    } else if (data.error) {
                        showStatusMessage('Error loading events: ' + data.error, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showStatusMessage('Server error fetching events list: ' + error, 'error');
                }
            });
        }

        $('#prg_evnt_id').on('change', function() {
            clearStatusMessage();
            let eventId = $(this).val();
            if (!eventId) {
                $('#checkinContent').slideUp();
                return;
            }
            loadRoster(eventId);
        });

        function loadRoster(eventId) {
            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_event_roster', prg_evnt_id: eventId },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        currentEvent = data.event || {};
                        currentRegistrations = data.registrations || [];
                        currentCheckedInIds = (data.attendance || []).map(a => parseInt(a.contact_id));

                        updateKPIDashboard();
                        renderRosterTable();
                        $('#checkinContent').slideDown();
                    } else {
                        showStatusMessage('Error fetching roster: ' + (data.error || data.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showStatusMessage('Server error loading event roster: ' + error, 'error');
                }
            });
        }

        function updateKPIDashboard() {
            let total = currentRegistrations.length;
            let checkedIn = currentCheckedInIds.length;
            let remaining = total - checkedIn;

            $('#kpiTotal').text(total);
            $('#kpiCheckedIn').text(checkedIn);
            $('#kpiRemaining').text(remaining < 0 ? 0 : remaining);
        }

        function renderRosterTable() {
            let $tbody = $('#rosterTableBody').empty();
            let searchFilter = $('#searchAttendee').val().toLowerCase().trim();
            let statusFilter = $('#filterStatus').val();

            let reqFee = currentEvent && parseInt(currentEvent.requires_fee) === 1;

            let filteredList = currentRegistrations.filter(r => {
                let fullName = (r.full_name || 'Unknown').toLowerCase();
                let nameMatches = fullName.includes(searchFilter);
                let isCheckedIn = currentCheckedInIds.includes(parseInt(r.contact_id));
                
                let statusMatches = true;
                if (statusFilter === 'present') statusMatches = isCheckedIn;
                if (statusFilter === 'absent') statusMatches = !isCheckedIn;

                return nameMatches && statusMatches;
            });

            if (filteredList.length === 0) {
                $tbody.append('<tr><td colspan="5" style="text-align:center; color:#64748b; padding:20px;">No matching registered attendees found.</td></tr>');
                return;
            }

            $.each(filteredList, function(i, r) {
                let contactId = parseInt(r.contact_id);
                let regId = parseInt(r.registration_id);
                let isCheckedIn = currentCheckedInIds.includes(contactId);

                let isCompleted = false;
                if (!reqFee) {
                    isCompleted = true; 
                } else {
                    let statusLower = String(r.payment_status || '').toLowerCase();
                    isCompleted = parseInt(r.is_completed) === 1 || ['paid', 'completed', 'waived', 'n/a'].includes(statusLower);
                }

                // Completion status badge
                let regBadge = isCompleted
                    ? '<span class="status-badge badge-completed">Completed</span>'
                    : '<span class="status-badge badge-incomplete">Not Completed</span>';

                // Attendance status badge
                let attBadge = isCheckedIn 
                    ? '<span class="status-badge badge-present">Present ✓</span>'
                    : '<span class="status-badge badge-absent">Not Checked In</span>';

                // Check-In Button Logic
                let actionBtn = '';
                if (!CAN_EDIT) {
                    actionBtn = '<button type="button" class="btn-toggle-disabled" disabled>Read Only</button>';
                } else if (isCheckedIn) {
                    actionBtn = `<button type="button" class="btn-toggle-present btn-toggle" data-contact="${contactId}">Mark Absent</button>`;
                } else if (isCompleted) {
                    actionBtn = `<button type="button" class="btn-toggle-absent btn-toggle" data-contact="${contactId}">Check In</button>`;
                } else {
                    actionBtn = `<button type="button" class="btn-toggle-disabled" disabled title="Registration must be Paid or Waived before check-in.">Check In</button>`;
                }

                let paymentStatusDisp = reqFee ? (r.payment_status || 'Pending') : 'N/A (Free Event)';

                $tbody.append(`
                    <tr>
                        <td><strong>${r.full_name || 'Unknown Contact'}</strong></td>
                        <td>${regBadge}</td>
                        <td>${paymentStatusDisp}</td>
                        <td>${attBadge}</td>
                        <td style="text-align:right;">${actionBtn}</td>
                    </tr>
                `);
            });
        }

        // Live Search / Filter Triggers
        $(document).on('input', '#searchAttendee', renderRosterTable);
        $(document).on('change', '#filterStatus', renderRosterTable);

        // Toggle Attendance Action
        $(document).on('click', '.btn-toggle', function() {
            if (!CAN_EDIT) return;
            clearStatusMessage();
            let contactId = $(this).data('contact');
            let eventId = $('#prg_evnt_id').val();

            $.ajax({
                url: 'prg_event_api.php',
                type: 'POST',
                data: { action: 'toggle_attendance', prg_evnt_id: eventId, contact_id: contactId },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        loadRoster(eventId);
                    } else {
                        showStatusMessage(res.error || res.message || 'Error updating attendance status.', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showStatusMessage('Communication error updating attendance: ' + error, 'error');
                }
            });
        });
    });
  </script>
</head>
<body>
  <?php require_once("config/db.php"); ?>
  <?php include 'include/header.php'; ?> 

  <h1>Live Event Attendance Check-In</h1>

  <div id="status-message" class="alert-box"></div>

  <div class="checkin-wrapper">
    <!-- EVENT SELECTOR FIELDSET -->
    <fieldset class="form-grid-section-8">
      <legend>
        <h2>Select Event</h2>
      </legend>
      <div class="field-group" style="--colspan: 8;">
        <label for="prg_evnt_id"><h3 style="color: #28089a; margin: 0 0 5px 0;">Active Event:</h3></label>
        <select id="prg_evnt_id" name="prg_evnt_id">
          <option value="">-- Choose an Event to Begin Check-In --</option>
        </select>
      </div>
    </fieldset>

    <!-- CHECK-IN DASHBOARD CONTAINER -->
    <div id="checkinContent" style="display: none; margin-top: 20px;">
      
      <!-- KPI DASHBOARD METRICS -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-title">Total Registrations</div>
          <div id="kpiTotal" class="kpi-value">0</div>
          <div class="kpi-subtext">Registered contacts</div>
        </div>
        <div class="kpi-card highlight">
          <div class="kpi-title">Checked In</div>
          <div id="kpiCheckedIn" class="kpi-value" style="color: #0d9488;">0</div>
          <div class="kpi-subtext">Verified present</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Pending Arrival</div>
          <div id="kpiRemaining" class="kpi-value" style="color: #dc2626;">0</div>
          <div class="kpi-subtext">Awaiting arrival</div>
        </div>
      </div>

      <!-- ROSTER TABLE CARD -->
      <div class="card">
        <h3>Attendee Check-In Roster</h3>

        <!-- SEARCH AND FILTERS -->
        <div class="search-filter-box">
          <input type="text" id="searchAttendee" placeholder="Search attendee by name...">
          <select id="filterStatus">
            <option value="all">All Attendees</option>
            <option value="present">Present Only</option>
            <option value="absent">Not Checked In Only</option>
          </select>
        </div>

        <table class="data-table">
          <thead>
            <tr>
              <th>Attendee Name</th>
              <th>Reg. Status</th>
              <th>Payment Status</th>
              <th>Attendance Status</th>
              <th style="text-align:right;">Action</th>
            </tr>
          </thead>
          <tbody id="rosterTableBody">
            <!-- Dynamically populated rows -->
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php include_once 'include/footer.php'; ?>
</body>
</html>