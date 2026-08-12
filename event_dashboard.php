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
$adminUser = isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Event Viewer Dashboard</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .dashboard-container {
      display: grid;
      grid-template-columns: 1fr 2fr;
      gap: 20px;
      margin-top: 20px;
    }
    @media (max-width: 900px) {
      .dashboard-container {
        grid-template-columns: 1fr;
      }
    }
    .metrics-row {
      display: flex;
      gap: 15px;
      margin-bottom: 20px;
    }
    .metric-card {
      flex: 1;
      padding: 15px;
      border-radius: 8px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      text-align: center;
    }
    .metric-card h3 {
      margin: 8px 0 0 0;
      font-size: 1.5rem;
    }
    .metric-card.income { background: #f0fdf4; border-color: #dcfce7; color: #166534; }
    .metric-card.expense { background: #fef2f2; border-color: #fee2e2; color: #991b1b; }
    .metric-card.net { background: #eff6ff; border-color: #dbeafe; color: #1e40af; }
    
    .detail-section {
      margin-bottom: 20px;
      padding: 15px;
      background: #ffffff;
      border-radius: 6px;
      border: 1px solid #e2e8f0;
    }
    .detail-label {
      font-weight: bold;
      color: #64748b;
      font-size: 0.85rem;
      text-transform: uppercase;
      margin-bottom: 4px;
    }
    .detail-value {
      font-size: 1rem;
      color: #0f172a;
    }
    .badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.8rem;
      font-weight: 600;
    }
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-secondary { background: #e2e8f0; color: #475569; }
    
    table.data-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    table.data-table th, table.data-table td {
      border: 1px solid #e2e8f0;
      padding: 8px 12px;
      text-align: left;
    }
    table.data-table th {
      background-color: #f1f5f9;
    }
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script>
    $(document).ready(function() {
        function loadEventDropdown() {
            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_events' },
                dataType: 'json',
                success: function(data) {
                    if(data.success) {
                        let $select = $('#event_select');
                        $select.find('option:not(:first)').remove();
                        $.each(data.programs_events, function(i, ev) {
                            let dispDate = ev.primary_start ? ` (${ev.primary_start.substring(0,10)})` : '';
                            $select.append(`<option value="${ev.prg_evnt_id}">${ev.prg_evnt_name}${dispDate}</option>`);
                        });
                    }
                }
            });
        }

        $('#event_select').on('change', function() {
            let id = $(this).val();
            if(!id) {
                $('#dashboardContent').hide();
                $('#dashboardPlaceholder').show();
                return;
            }

            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_events', prg_evnt_id: id },
                dataType: 'json',
                success: function(data) {
                    if(data.success && data.prgevnt) {
                        let ev = data.prgevnt;
                        
                        // Populate Overview Details
                        $('#dash_event_name').text(ev.prg_evnt_name);
                        $('#dash_location').text(ev.location || 'N/A');
                        $('#dash_goal').text(ev.goal || 'N/A');
                        $('#dash_notes').text(ev.notes || 'N/A');
                        
                        let reqReg = parseInt(ev.requires_registration) === 1;
                        let reqFee = parseInt(ev.requires_fee) === 1;
                        
                        $('#dash_registration').html(reqReg ? '<span class="badge badge-success">Required</span>' : '<span class="badge badge-secondary">Not Required</span>');
                        $('#dash_fee').html(reqFee ? `$${parseFloat(ev.registration_fee || 0).toFixed(2)}` : 'Free');

                        if (reqReg) {
                            $('#btnGoRegister').attr('href', 'event_registration.php?prg_evnt_id=' + ev.prg_evnt_id).show();
                        } else {
                            $('#btnGoRegister').hide();
                        }

                        // Schedule List (Handles optional end_datetime)
                        let $schedList = $('#dash_schedules').empty();
                        if (data.schedules && data.schedules.length > 0) {
                            $.each(data.schedules, function(i, sch) {
                                let endDisp = sch.end_datetime ? ` | <strong>Ends:</strong> ${sch.end_datetime}` : '';
                                $schedList.append(`<li><strong>Starts:</strong> ${sch.start_datetime}${endDisp}</li>`);
                            });
                        } else {
                            $schedList.append('<li>No schedule available.</li>');
                        }

                        // Financial Calculations & Ledger Table
                        let totalExpenses = 0;
                        let totalIncome = 0;
                        let $budgetBody = $('#dash_budget_table tbody').empty();

                        if (data.budgets && data.budgets.length > 0) {
                            $.each(data.budgets, function(i, bud) {
                                let amt = parseFloat(bud.amount) || 0;
                                if (bud.item_type === 'Expense') {
                                    totalExpenses += amt;
                                } else {
                                    totalIncome += amt;
                                }

                                $budgetBody.append(`
                                    <tr>
                                        <td>${bud.item_description}</td>
                                        <td><span class="badge ${bud.item_type === 'Expense' ? 'badge-secondary' : 'badge-success'}">${bud.item_type}</span></td>
                                        <td>$${amt.toFixed(2)}</td>
                                    </tr>
                                `);
                            });
                        } else {
                            $budgetBody.append('<tr><td colspan="3">No budget entries recorded.</td></tr>');
                        }

                        let net = totalIncome - totalExpenses;
                        $('#dash_expenses').text('$' + totalExpenses.toFixed(2));
                        $('#dash_income').text('$' + totalIncome.toFixed(2));
                        $('#dash_net').text((net >= 0 ? '+' : '') + '$' + net.toFixed(2));

                        // Load Attendance Roster Data
                        loadDashboardRoster(id);

                        $('#dashboardPlaceholder').hide();
                        $('#dashboardContent').fadeIn(200);
                    }
                }
            });
        });

        function loadDashboardRoster(eventId) {
            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_event_roster', prg_evnt_id: eventId },
                dataType: 'json',
                success: function(data) {
                    if(data.success) {
                        let checkedInIds = data.attendance.map(a => parseInt(a.contact_id));
                        let $rosterBody = $('#dash_roster_table tbody').empty();
                        let totalRegistered = data.registrations.length;
                        let totalCheckedIn = 0;

                        if (totalRegistered === 0) {
                            $rosterBody.append('<tr><td colspan="2">No registrations found.</td></tr>');
                        } else {
                            $.each(data.registrations, function(i, r) {
                                let isCheckedIn = checkedInIds.includes(parseInt(r.contact_id));
                                if (isCheckedIn) totalCheckedIn++;

                                $rosterBody.append(`
                                    <tr>
                                        <td>${r.full_name}</td>
                                        <td>${isCheckedIn ? '<span class="badge badge-success">Checked In</span>' : '<span class="badge badge-secondary">Registered</span>'}</td>
                                    </tr>
                                `);
                            });
                        }

                        $('#dash_reg_count').text(totalRegistered);
                        $('#dash_checkin_count').text(totalCheckedIn);
                    }
                }
            });
        }

        loadEventDropdown();
    });
  </script>
</head>
<body>
  <?php include 'include/header.php'; ?> 

  <div style="max-width: 1200px; margin: 20px auto; padding: 0 15px;">
      <div class="card">
          <h2>Event Information Dashboard</h2>
          <div class="form-group" style="max-width: 400px;">
              <label for="event_select">Select Event:</label>
              <select id="event_select" class="form-control">
                  <option value="">-- Choose an Event --</option>
              </select>
          </div>
      </div>

      <div id="dashboardPlaceholder" class="card" style="margin-top: 20px; text-align: center; color: #64748b;">
          <p>Please select an event above to display its information, budget summary, and attendance roster.</p>
      </div>

      <div id="dashboardContent" class="dashboard-container" style="display: none;">
          
          <!-- Left Column -->
          <div>
              <div class="card">
                  <h2 id="dash_event_name" style="margin-top: 0;">Event Details</h2>
                  
                  <div class="detail-section">
                      <div class="detail-label">Location</div>
                      <div id="dash_location" class="detail-value">-</div>
                  </div>

                  <div class="detail-section">
                      <div class="detail-label">Goal / Purpose</div>
                      <div id="dash_goal" class="detail-value">-</div>
                  </div>

                  <div class="detail-section">
                      <div class="detail-label">Registration & Fee</div>
                      <div class="detail-value">
                          Registration: <span id="dash_registration"></span><br>
                          Cost: <strong id="dash_fee">$0.00</strong>
                      </div>
                  </div>

                  <div class="detail-section">
                      <div class="detail-label">Schedule(s)</div>
                      <ul id="dash_schedules" style="padding-left: 20px; margin: 5px 0 0 0;"></ul>
                  </div>

                  <div class="detail-section">
                      <div class="detail-label">Notes</div>
                      <div id="dash_notes" class="detail-value">-</div>
                  </div>

                  <a id="btnGoRegister" href="#" class="btn btn-accent" style="display:none; text-decoration:none; width: 100%; text-align: center; box-sizing: border-box;">Register Attendees</a>
              </div>
          </div>

          <!-- Right Column -->
          <div>
              <!-- Financial KPI Cards -->
              <div class="metrics-row">
                  <div class="metric-card expense">
                      <span class="detail-label">Total Expenses</span>
                      <h3 id="dash_expenses">$0.00</h3>
                  </div>
                  <div class="metric-card income">
                      <span class="detail-label">Projected Income</span>
                      <h3 id="dash_income">$0.00</h3>
                  </div>
                  <div class="metric-card net">
                      <span class="detail-label">Net Balance</span>
                      <h3 id="dash_net">$0.00</h3>
                  </div>
              </div>

              <!-- Attendance KPI Cards -->
              <div class="card" style="margin-bottom: 20px;">
                  <h2>Attendance Summary</h2>
                  <div class="metrics-row" style="margin-bottom: 0;">
                      <div class="metric-card">
                          <span class="detail-label">Total Registered</span>
                          <h3 id="dash_reg_count">0</h3>
                      </div>
                      <div class="metric-card">
                          <span class="detail-label">Total Checked-In</span>
                          <h3 id="dash_checkin_count">0</h3>
                      </div>
                  </div>
              </div>

              <!-- Budget Ledger Breakdown -->
              <div class="card" style="margin-bottom: 20px;">
                  <h2>Budget Breakdown Ledger</h2>
                  <table class="data-table" id="dash_budget_table">
                      <thead>
                          <tr>
                              <th>Description</th>
                              <th>Type</th>
                              <th>Amount</th>
                          </tr>
                      </thead>
                      <tbody>
                          <!-- Dynamic rows -->
                      </tbody>
                  </table>
              </div>

              <!-- Attendees Roster -->
              <div class="card">
                  <h2>Registered Attendees Roster</h2>
                  <table class="data-table" id="dash_roster_table">
                      <thead>
                          <tr>
                              <th>Attendee Name</th>
                              <th>Status</th>
                          </tr>
                      </thead>
                      <tbody>
                          <!-- Dynamic rows -->
                      </tbody>
                  </table>
              </div>
          </div>

      </div>
  </div>
  <?php include_once 'include/footer.php'; ?>
</body>
</html>