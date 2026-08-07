<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Live Event Attendance Check-In</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .checkin-wrapper {
      width: 100%;
      max-width: 1100px;
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
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script>
    $(document).ready(function() {
        let currentRegistrations = [];
        let currentCheckedInIds = [];

        // Parse query parameters (e.g. ?prg_evnt_id=12)
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

                        $.each(data.programs_events, function(i, ev) {
                            let dispDate = ev.primary_start ? ` (${ev.primary_start.substring(0, 10)})` : '';
                            $select.append(`<option value="${ev.prg_evnt_id}">${ev.prg_evnt_name}${dispDate}</option>`);
                        });

                        if (selectedId) {
                            $select.val(selectedId).trigger('change');
                        }
                    }
                }
            });
        }

        // Handle Event Selection
        $('#prg_evnt_id').on('change', function() {
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
                        currentRegistrations = data.registrations || [];
                        currentCheckedInIds = (data.attendance || []).map(a => parseInt(a.contact_id));

                        updateKPIDashboard();
                        renderRosterTable();
                        $('#checkinContent').slideDown();
                    }
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

            let filteredList = currentRegistrations.filter(r => {
                let nameMatches = r.full_name.toLowerCase().includes(searchFilter);
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
                let isCheckedIn = currentCheckedInIds.includes(contactId);

                let badge = isCheckedIn 
                    ? '<span class="status-badge badge-present">Present ✓</span>'
                    : '<span class="status-badge badge-absent">Not Checked In</span>';

                let actionBtn = isCheckedIn
                    ? `<button type="button" class="btn-toggle-present btn-toggle" data-contact="${contactId}">Mark Absent</button>`
                    : `<button type="button" class="btn-toggle-absent btn-toggle" data-contact="${contactId}">Check In</button>`;

                let amountPaidFormatted = '$' + parseFloat(r.amount_paid || 0).toFixed(2);

                $tbody.append(`
                    <tr>
                        <td><strong>${r.full_name}</strong></td>
                        <td>${r.payment_status || 'N/A'}</td>
                        <td><strong>${amountPaidFormatted}</strong></td>
                        <td>${badge}</td>
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
                    }
                }
            });
        });
    });
  </script>
</head>
<body>
  <?php require_once("config/db.php"); ?>
  <?php include 'header.php'; ?> 

  <h1>Live Event Attendance Check-In</h1>

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
              <th>Payment Status</th>
              <th>Amount Paid</th>
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
</body>
</html>