<<?php
// event_dashboard.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/include/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Event Information Dashboard - NBBTM CMS</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .dashboard-container { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-top: 20px; }
    @media (max-width: 900px) { .dashboard-container { grid-template-columns: 1fr; } }
    .metrics-row { display: flex; gap: 15px; margin-bottom: 20px; }
    .metric-card { flex: 1; padding: 15px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center; }
    .metric-card h3 { margin: 8px 0 0 0; font-size: 1.5rem; }
    .metric-card.income { background: #f0fdf4; border-color: #dcfce7; color: #166534; }
    .metric-card.expense { background: #fef2f2; border-color: #fee2e2; color: #991b1b; }
    .metric-card.net { background: #eff6ff; border-color: #dbeafe; color: #1e40af; }
    .detail-section { margin-bottom: 16px; padding: 14px; background: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; }
    .detail-label { font-weight: bold; color: #64748b; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 4px; }
    .detail-value { font-size: 0.95rem; color: #0f172a; line-height: 1.45; }
    .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; }
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-secondary { background: #e2e8f0; color: #475569; }
    table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.data-table th, table.data-table td { border: 1px solid #e2e8f0; padding: 8px 12px; text-align: left; }
    table.data-table th { background-color: #f1f5f9; color: #28089a; }
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    $(document).ready(function() {
        let contactsCache = [];
        let ministriesCache = [];

        function loadReferenceData() {
            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_contacts_list' },
                dataType: 'json',
                success: function(data) { if (data.success) contactsCache = data.contacts || []; }
            });
            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_ministries_list' },
                dataType: 'json',
                success: function(data) { if (data.success) ministriesCache = data.ministries || []; }
            });
        }

        function loadEventDropdown() {
            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_events' },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        let $select = $('#event_select').empty().append('<option value="">-- Choose an Event --</option>');
                        $.each(data.programs_events, function(i, ev) {
                            let dispDate = ev.primary_start ? ` (${ev.primary_start.substring(0,10)})` : '';
                            $select.append(`<option value="${ev.prg_evnt_id}">${escapeHtml(ev.prg_evnt_name)}${dispDate}</option>`);
                        });
                    }
                }
            });
        }

        $('#event_select').on('change', function() {
            let id = $(this).val();
            if (!id) {
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
                    if (data.success && data.prgevnt) {
                        let ev = data.prgevnt;
                        $('#dash_event_name').text(ev.prg_evnt_name);

                        let sponsorMin = ministriesCache.find(m => String(m.min_comm_id) === String(ev.min_comm_id));
                        $('#dash_sponsor').text(sponsorMin ? sponsorMin.min_comm_name : (ev.ministry_name || 'N/A'));

                        let contactName = 'Unassigned';
                        if (ev.contact_id) {
                            let found = contactsCache.find(c => String(c.contact_id) === String(ev.contact_id));
                            if (found) contactName = found.full_name;
                        }
                        
                        let contactDetails = [];
                        if (ev.contact_phone && ev.contact_phone.trim() !== '') contactDetails.push(`📞 ${escapeHtml(ev.contact_phone)}`);
                        if (ev.contact_email && ev.contact_email.trim() !== '') contactDetails.push(`✉️ ${escapeHtml(ev.contact_email)}`);

                        let contactDisplay = `<strong>${escapeHtml(contactName)}</strong>`;
                        if (contactDetails.length > 0) contactDisplay += `<br><span style="color:#475569; font-size:0.9rem;">${contactDetails.join(' &nbsp;|&nbsp; ')}</span>`;
                        $('#dash_contact').html(contactDisplay);

                        $('#dash_location').text(ev.location || 'N/A');
                        $('#dash_purpose').text(ev.prg_evnt_purpose || 'N/A');
                        $('#dash_goal').text(ev.goal || 'N/A');
                        $('#dash_notes').text(ev.notes || 'None recorded.');

                        // Collaborations
                        let $supList = $('#dash_support_list').empty();
                        if (data.support_ministries && data.support_ministries.length > 0) {
                            $.each(data.support_ministries, function(i, sm) {
                                let optBadge = sm.option ? ` &nbsp;<span class="badge badge-secondary">${escapeHtml(sm.option)}</span>` : '';
                                $supList.append(`<li style="margin-bottom: 4px;"><strong>${escapeHtml(sm.min_comm_name)}</strong>${optBadge}</li>`);
                            });
                        } else {
                            $supList.append('<li style="color:#64748b;">No collaborative ministries assigned.</li>');
                        }

                        // Schedules
                        let $schedList = $('#dash_schedules').empty();
                        if (data.schedules && data.schedules.length > 0) {
                            $.each(data.schedules, function(i, sch) {
                                let endDisp = sch.end_datetime ? ` &nbsp;|&nbsp; <strong>Ends:</strong> ${sch.end_datetime}` : '';
                                $schedList.append(`<li style="margin-bottom: 4px;"><strong>Starts:</strong> ${sch.start_datetime}${endDisp}</li>`);
                            });
                        } else {
                            $schedList.append('<li style="color:#64748b;">No schedule entries recorded.</li>');
                        }

                        // Registration & Fee
                        let reqReg = parseInt(ev.requires_registration) === 1;
                        let reqFee = parseInt(ev.requires_fee) === 1;
                        $('#dash_registration').html(reqReg ? '<span class="badge badge-success">Required</span>' : '<span class="badge badge-secondary">Not Required</span>');
                        $('#dash_fee').html(reqFee ? `$${parseFloat(ev.registration_fee || 0).toFixed(2)}` : 'Free');

                        if (reqReg) $('#btnGoRegister').attr('href', 'event_registration.php?prg_evnt_id=' + ev.prg_evnt_id).show();
                        else $('#btnGoRegister').hide();

                        // Render Attachments List
                        let $attList = $('#dash_attachment_list').empty();
                        if (data.attachments && data.attachments.length > 0) {
                            $('#dash_attachments_section').show();
                            $.each(data.attachments, function(i, att) {
                                let downloadUrl = `download_document.php?attachment_id=${att.attachment_id}`;
                                $attList.append(`
                                    <li style="margin-bottom: 6px;">
                                        <span class="badge badge-secondary">${escapeHtml(att.category_name || 'General')}</span>
                                        <a href="${downloadUrl}" target="_blank" style="font-weight: 600; margin-left: 6px;">
                                            📄 ${escapeHtml(att.document_name)}
                                        </a>
                                    </li>
                                `);
                            });
                        } else {
                            $('#dash_attachments_section').hide();
                        }

                        // Budgets Ledger
                        let totalExpenses = 0, totalIncome = 0;
                        let $budgetBody = $('#dash_budget_table tbody').empty();

                        if (data.budgets && data.budgets.length > 0) {
                            $.each(data.budgets, function(i, bud) {
                                let amt = parseFloat(bud.amount) || 0;
                                if (bud.item_type === 'Expense') totalExpenses += amt;
                                else totalIncome += amt;

                                $budgetBody.append(`
                                    <tr>
                                        <td>${escapeHtml(bud.item_description)}</td>
                                        <td><span class="badge ${bud.item_type === 'Expense' ? 'badge-secondary' : 'badge-success'}">${escapeHtml(bud.item_type)}</span></td>
                                        <td>$${amt.toFixed(2)}</td>
                                    </tr>
                                `);
                            });
                        } else {
                            $budgetBody.append('<tr><td colspan="3" style="text-align:center; color:#64748b;">No budget entries recorded.</td></tr>');
                        }

                        let net = totalIncome - totalExpenses;
                        $('#dash_expenses').text('$' + totalExpenses.toFixed(2));
                        $('#dash_income').text('$' + totalIncome.toFixed(2));
                        $('#dash_net').text((net >= 0 ? '+' : '') + '$' + net.toFixed(2));

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
                    if (data.success) {
                        let checkedInIds = (data.attendance || []).map(a => parseInt(a.contact_id));
                        let $rosterBody = $('#dash_roster_table tbody').empty();
                        let totalRegistered = (data.registrations || []).length;
                        let totalCheckedIn = 0;

                        if (totalRegistered === 0) {
                            $rosterBody.append('<tr><td colspan="2" style="text-align:center; color:#64748b;">No registrations found.</td></tr>');
                        } else {
                            $.each(data.registrations, function(i, r) {
                                let isCheckedIn = checkedInIds.includes(parseInt(r.contact_id));
                                if (isCheckedIn) totalCheckedIn++;

                                $rosterBody.append(`
                                    <tr>
                                        <td><strong>${escapeHtml(r.full_name)}</strong></td>
                                        <td>${isCheckedIn ? '<span class="badge badge-success">Checked In ✓</span>' : '<span class="badge badge-secondary">Registered</span>'}</td>
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

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        loadReferenceData();
        loadEventDropdown();
    });
  </script>
</head>
<body>
  <?php include_once __DIR__ . '/include/header.php'; ?> 

  <main style="max-width: 1200px; margin: 20px auto; padding: 0 15px;">
      
      <div class="card">
          <h2 style="margin-top:0; color:#28089a;">Event Information Dashboard</h2>
          <div class="form-group" style="max-width: 450px;">
              <label for="event_select" style="font-weight:600; color:#28089a; margin-bottom:5px; display:inline-block;">Select Program / Event:</label>
              <select id="event_select" class="form-control" style="width:100%; padding:8px; border-radius:4px; border:1px solid #cbd5e1;">
                  <option value="">-- Choose an Event --</option>
              </select>
          </div>
      </div>

      <div id="dashboardPlaceholder" class="card" style="margin-top: 20px; text-align: center; color: #64748b; padding: 3rem 1rem;">
          <p style="font-size: 1.1rem; margin:0;">Please select an event above to view its operational details, schedule, collaborators, attachments, budget ledger, and attendance.</p>
      </div>

      <div id="dashboardContent" class="dashboard-container" style="display: none;">
          
          <!-- Left Column -->
          <div>
              <div class="card">
                  <h2 id="dash_event_name" style="margin-top: 0; color:#28089a; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">Event Details</h2>
                  
                  <div class="detail-section">
                      <div class="detail-label">Sponsoring Ministry</div>
                      <div id="dash_sponsor" class="detail-value">-</div>
                  </div>

                  <div class="detail-section">
                      <div class="detail-label">Primary Contact</div>
                      <div id="dash_contact" class="detail-value">-</div>
                  </div>

                  <div class="detail-section">
                      <div class="detail-label">Location</div>
                      <div id="dash_location" class="detail-value">-</div>
                  </div>

                  <div class="detail-section">
                      <div class="detail-label">Event Purpose</div>
                      <div id="dash_purpose" class="detail-value">-</div>
                  </div>

                  <div class="detail-section">
                      <div class="detail-label">Key Deliverables / Goals</div>
                      <div id="dash_goal" class="detail-value">-</div>
                  </div>

                  <div class="detail-section">
                      <div class="detail-label">Ministry Collaboration</div>
                      <ul id="dash_support_list" style="padding-left: 18px; margin: 6px 0 0 0;"></ul>
                  </div>

                  <div class="detail-section">
                      <div class="detail-label">Schedule(s)</div>
                      <ul id="dash_schedules" style="padding-left: 18px; margin: 6px 0 0 0;"></ul>
                  </div>

                  <div class="detail-section">
                      <div class="detail-label">Registration & Fee</div>
                      <div class="detail-value">
                          Registration: <span id="dash_registration"></span><br>
                          Cost: <strong id="dash_fee">$0.00</strong>
                      </div>
                  </div>

                  <div class="detail-section">
                      <div class="detail-label">Special Requests & Support Notes</div>
                      <div id="dash_notes" class="detail-value">-</div>
                  </div>

                  <div class="detail-section" id="dash_attachments_section" style="display: none;">
                      <div class="detail-label">Attached Documents & Packets</div>
                      <ul id="dash_attachment_list" style="padding-left: 18px; margin: 6px 0 0 0; list-style-type: none;"></ul>
                  </div>

                  <div style="margin-top: 15px;">
                      <a id="btnGoRegister" href="#" class="btn btn-accent" style="display:none; text-decoration:none; width: 100%; text-align: center; box-sizing: border-box;">
                          Register Attendees &rarr;
                      </a>
                  </div>
              </div>
          </div>

          <!-- Right Column -->
          <div>
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

              <div class="card" style="margin-bottom: 20px;">
                  <h2 style="margin-top:0; color:#28089a;">Attendance Summary</h2>
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

              <div class="card" style="margin-bottom: 20px;">
                  <h2 style="margin-top:0; color:#28089a;">Budget Breakdown Ledger</h2>
                  <table class="data-table" id="dash_budget_table">
                      <thead>
                          <tr>
                              <th>Description</th>
                              <th>Type</th>
                              <th>Amount</th>
                          </tr>
                      </thead>
                      <tbody></tbody>
                  </table>
              </div>

              <div class="card">
                  <h2 style="margin-top:0; color:#28089a;">Registered Attendees Roster</h2>
                  <table class="data-table" id="dash_roster_table">
                      <thead>
                          <tr>
                              <th>Attendee Name</th>
                              <th>Check-In Status</th>
                          </tr>
                      </thead>
                      <tbody></tbody>
                  </table>
              </div>
          </div>

      </div>
  </main>

  <?php include_once __DIR__ . '/include/footer.php'; ?>
</body>
</html>