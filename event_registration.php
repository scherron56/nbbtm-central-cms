<?php
// event_registration.php
if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}

require_once __DIR__ . '/include/auth.php';
$canEdit = canEdit();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Event Registration - NBBTM CMS</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .registration-layout {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-top: 20px;
    }

    @media (max-width: 850px) {
      .registration-layout {
        grid-template-columns: 1fr;
      }
    }

    .form-group {
      margin-bottom: 15px;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 5px;
      color: #1e293b;
    }

    .form-control {
      width: 100%;
      padding: 10px;
      border-radius: 6px;
      border: 1px solid #cbd5e1;
      box-sizing: border-box;
      font-size: 0.95rem;
    }

    .event-info-box {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 20px;
    }

    .event-info-box h3 {
      margin-top: 0;
      color: #28089a;
    }

    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 4px;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .badge-success {
      background: #d1fae5;
      color: #065f46;
    }

    .badge-secondary {
      background: #e2e8f0;
      color: #475569;
    }

    .alert {
      padding: 12px 16px;
      border-radius: 6px;
      margin-bottom: 15px;
      font-weight: 500;
      display: none;
    }

    .alert-success {
      background: #dcfce7;
      color: #15803d;
      border: 1px solid #bbf7d0;
    }

    .alert-danger {
      background: #fee2e2;
      color: #b91c1c;
      border: 1px solid #fecaca;
    }

    table.data-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }

    table.data-table th,
    table.data-table td {
      border: 1px solid #e2e8f0;
      padding: 10px 12px;
      text-align: left;
    }

    table.data-table th {
      background-color: #f1f5f9;
      color: #28089a;
    }
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    $(document).ready(function() {
      const CAN_EDIT = <?php echo $canEdit ? 'true' : 'false'; ?>;
      let editingRegId = null;
      let urlParams = new URLSearchParams(window.location.search);
      let preselectedEventId = urlParams.get('prg_evnt_id');
      let currentEvent = null;

      function loadEvents() {
        $.ajax({
          url: 'prg_event_api.php',
          type: 'GET',
          data: {
            action: 'get_events'
          },
          dataType: 'json',
          success: function(data) {
            if (data.success) {
              let $select = $('#event_select').empty().append('<option value="">-- Select Event --</option>');
              $.each(data.programs_events, function(i, ev) {
                if (parseInt(ev.requires_registration) === 1) {
                  let dispDate = ev.primary_start ? ` (${ev.primary_start.substring(0,10)})` : '';
                  $select.append(`<option value="${ev.prg_evnt_id}">${escapeHtml(ev.prg_evnt_name)}${dispDate}</option>`);
                }
              });

              if (preselectedEventId) {
                $('#event_select').val(preselectedEventId).trigger('change');
              }
            }
          }
        });
      }

      function loadContacts() {
        $.ajax({
          url: 'prg_event_api.php',
          type: 'GET',
          data: {
            action: 'get_contacts_list'
          },
          dataType: 'json',
          success: function(data) {
            if (data.success) {
              let $select = $('#contact_select').empty().append('<option value="">-- Select Contact --</option>');
              $.each(data.contacts, function(i, c) {
                $select.append(`<option value="${c.contact_id}">${escapeHtml(c.full_name)}</option>`);
              });
            }
          }
        });
      }

      $('#event_select').on('change', function() {
        let eventId = $(this).val();
        if (!eventId) {
          $('#eventDetailsBox').hide();
          $('#registrationSection').hide();
          return;
        }

        $.ajax({
          url: 'prg_event_api.php',
          type: 'GET',
          data: {
            action: 'get_events',
            prg_evnt_id: eventId
          },
          dataType: 'json',
          success: function(data) {
            if (data.success && data.prgevnt) {
              let ev = data.prgevnt;
              currentEvent = ev;
              $('#info_event_name').text(ev.prg_evnt_name);
              $('#info_location').text(ev.location || 'N/A');
              $('#info_fee').text(parseInt(ev.requires_fee) === 1 ? `$${parseFloat(ev.registration_fee || 0).toFixed(2)}` : 'Free');

              let reqFee = parseInt(ev.requires_fee) === 1;
              if (reqFee) {
                let fee = parseFloat(ev.registration_fee || 0);
                $('#amount_paid').val(fee.toFixed(2));
                $('#payment_status').val('Pending');
                $('#payment_method').val('Cash');
                $('#paymentFieldsSection').show();
              } else {
                $('#amount_paid').val('0.00');
                $('#payment_status').val('Paid');
                $('#payment_method').val('None');
                $('#paymentFieldsSection').hide();
              }

              $('#eventDetailsBox').show();
              $('#registrationSection').show();

              loadRoster(eventId);
            }
          }
        });
      });

      // Business rules in the UI:
      // - "Waived" forces Amount Paid to 0.00
      // - "Online" method forces Payment Status to "Pending" (until verified)
      function applyPaymentRules() {
        if ($('#payment_status').val() === 'Waived') {
          $('#amount_paid').val('0.00');
        }
        if ($('#payment_method').val() === 'Online') {
          $('#payment_status').val('Pending');
        }
      }

      $('#payment_status').on('change', applyPaymentRules);
      $('#payment_method').on('change', applyPaymentRules);

      $('#registrationForm').on('submit', function(e) {
        e.preventDefault();
        if (!CAN_EDIT) return;
        let eventId = $('#event_select').val();
        let contactId = $('#contact_select').val();

        if (!eventId || !contactId) {
          showAlert('alert-danger', 'Please select both an event and a contact.');
          return;
        }

        applyPaymentRules();

        $.ajax({
          url: 'prg_event_api.php',
          type: 'POST',
          data: {
            action: editingRegId ? 'save_registration' : 'register_attendee',
            registration_id: editingRegId || '',
            prg_evnt_id: eventId,
            contact_id: contactId,
            payment_method: $('#payment_method').val() || 'None',
            payment_status: $('#payment_status').val() || 'Pending',
            amount_paid: $('#amount_paid').val() || '0.00'
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              showAlert('alert-success', editingRegId ? 'Registration updated successfully.' : 'Attendee registered successfully.');
              resetRegistrationForm();
              loadRoster(eventId);
            } else {
              showAlert('alert-danger', response.message || response.error || 'Registration failed.');
            }
          },
          error: function() {
            showAlert('alert-danger', 'An error occurred while processing the registration.');
          }
        });
      });

      let currentRegistrations = [];

      function loadRoster(eventId) {
        $.ajax({
          url: 'prg_event_api.php',
          type: 'GET',
          data: {
            action: 'get_event_roster',
            prg_evnt_id: eventId
          },
          dataType: 'json',
          success: function(data) {
            if (data.success) {
              currentRegistrations = data.registrations || [];
              let checkedInIds = (data.attendance || []).map(a => parseInt(a.contact_id));
              let $tbody = $('#rosterTable tbody').empty();

              if (!data.registrations || data.registrations.length === 0) {
                $tbody.append('<tr><td colspan="7" style="text-align:center; color:#64748b;">No registered attendees yet.</td></tr>');
                return;
              }

              $.each(data.registrations, function(i, r) {
                let isCheckedIn = checkedInIds.includes(parseInt(r.contact_id));
                let checkInBtn = isCheckedIn ?
                  '<span class="badge badge-success">Checked In ✓</span>' :
                  `<button class="btn btn-sm btn-checkin" data-contact="${r.contact_id}" style="padding:4px 8px; font-size:0.8rem; cursor:pointer;">Check In</button>`;
                // Registration is Complete ONLY for "Waived" or "Completed/Paid"
                let statusLc = String(r.payment_status || '').toLowerCase();
                let isComplete = statusLc === 'waived' || statusLc === 'paid' || statusLc === 'completed' || parseInt(r.is_completed) === 1;
                let paidBadge = isComplete ?
                  '<span class="badge badge-success">Registration Complete</span>' :
                  '<span class="badge badge-secondary">Registration Pending</span>';
                let editBtn = CAN_EDIT ?
                  `<button class="btn btn-sm btn-edit-reg" data-id="${r.registration_id}" style="padding:4px 8px; font-size:0.8rem; cursor:pointer; background:#0ea5e9; color:#fff; border:none; border-radius:4px;">Edit</button>` :
                  '<em>Read Only</em>';

                $tbody.append(`
                                <tr>
                                    <td><strong>${escapeHtml(r.full_name)}</strong></td>
                                    <td>${escapeHtml(r.payment_status || 'Pending')}</td>
                                    <td>${escapeHtml(r.payment_method || 'None')}</td>
                                    <td>$${parseFloat(r.amount_paid || 0).toFixed(2)}</td>
                                    <td>${paidBadge}</td>
                                    <td>${checkInBtn}</td>
                                    <td>${editBtn}
                                        <button class="btn btn-sm btn-danger btn-remove" data-contact="${r.contact_id}" style="padding:4px 8px; font-size:0.8rem; cursor:pointer; background:#dc2626; color:#fff; border:none; border-radius:4px;">Remove</button>
                                    </td>
                                </tr>
                            `);
              });
            }
          }
        });
      }

      // Edit registration: load its payment details into the form
      $(document).on('click', '.btn-edit-reg', function() {
        if (!CAN_EDIT) return;
        let regId = $(this).data('id');
        let reg = (currentRegistrations || []).find(r => String(r.registration_id) === String(regId));
        if (!reg) return;

        editingRegId = reg.registration_id;
        $('#contact_select').val(reg.contact_id);
        $('#payment_method').val(reg.payment_method || 'None');
        $('#payment_status').val(reg.payment_status || 'Pending');
        applyPaymentRules();
        $('#amount_paid').val(parseFloat(reg.amount_paid || 0).toFixed(2));
        $('#paymentFieldsSection').show();
        $('#registrationSubmitBtn').text('Update Registration');
        $('#registrationSection')[0].scrollIntoView({
          behavior: 'smooth'
        });
      });

      function resetRegistrationForm() {
        editingRegId = null;
        $('#registrationForm')[0].reset();
        $('#amount_paid').val('0.00');
        $('#registrationSubmitBtn').text('Register Attendee');
        // Re-apply defaults for the currently selected event
        $('#event_select').trigger('change');
      }

      $(document).on('click', '.btn-checkin', function() {
        let eventId = $('#event_select').val();
        let contactId = $(this).data('contact');

        $.ajax({
          url: 'prg_event_api.php',
          type: 'POST',
          data: {
            action: 'record_attendance',
            prg_evnt_id: eventId,
            contact_id: contactId
          },
          dataType: 'json',
          success: function(res) {
            if (res.success) {
              loadRoster(eventId);
            } else {
              alert(res.message || 'Error checking in attendee.');
            }
          }
        });
      });

      $(document).on('click', '.btn-remove', function() {
        if (!confirm('Are you sure you want to remove this registration?')) return;

        let eventId = $('#event_select').val();
        let contactId = $(this).data('contact');

        $.ajax({
          url: 'prg_event_api.php',
          type: 'POST',
          data: {
            action: 'remove_registration',
            prg_evnt_id: eventId,
            contact_id: contactId
          },
          dataType: 'json',
          success: function(res) {
            if (res.success) {
              loadRoster(eventId);
            } else {
              alert(res.message || 'Error removing registration.');
            }
          }
        });
      });

      function showAlert(type, message) {
        $('.alert').hide().removeClass('alert-success alert-danger');
        $('#' + type).text(message).fadeIn().delay(3500).fadeOut();
      }

      function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
      }

      loadEvents();
      loadContacts();
    });
  </script>
</head>

<body>
  <?php include_once __DIR__ . '/include/header.php'; ?>

  <main style="max-width: 1200px; margin: 20px auto; padding: 0 15px;">
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid #e2e8f0; padding-bottom:0.5rem;">
        <h2 style="margin:0; color:#28089a;">Event Registration & Check-In</h2>
        <a href="event_dashboard.php" class="btn" style="text-decoration:none; background:#e2e8f0; color:#334155; padding:6px 12px; border-radius:4px; font-weight:600;">&larr; Back to Dashboard</a>
      </div>

      <div class="form-group" style="max-width: 500px; margin-top: 20px;">
        <label for="event_select">Select Event for Registration:</label>
        <select id="event_select" class="form-control">
          <option value="">-- Select Event --</option>
        </select>
      </div>
    </div>

    <div id="eventDetailsBox" class="event-info-box" style="display:none; margin-top:20px;">
      <h3 id="info_event_name" style="margin-bottom:8px;">-</h3>
      <div><strong>Location:</strong> <span id="info_location">-</span></div>
      <div><strong>Registration Fee:</strong> <span id="info_fee">$0.00</span></div>
    </div>

    <div id="registrationSection" class="registration-layout" style="display:none;">
      <!-- Left Column: Add Registration Form -->
      <div class="card">
        <h3 style="margin-top:0; color:#28089a;">Register Contact</h3>

        <div id="alert-success" class="alert alert-success"></div>
        <div id="alert-danger" class="alert alert-danger"></div>

        <form id="registrationForm">
          <div class="form-group">
            <label for="contact_select">Select Contact / Attendee:</label>
            <select id="contact_select" class="form-control" required>
              <option value="">-- Select Contact --</option>
            </select>
          </div>

          <div id="paymentFieldsSection">
            <div class="form-group">
              <label for="payment_method">Payment Method:</label>
              <select id="payment_method" name="payment_method" class="form-control">
                <option value="None">None / Free</option>
                <option value="Cash">Cash</option>
                <option value="Check">Check</option>
                <option value="Credit Card">Credit Card</option>
                <option value="Online">Online (Givelify)</option>
              </select>
            </div>

            <div class="form-group">
              <label for="payment_status">Payment Status:</label>
              <select id="payment_status" name="payment_status" class="form-control">
                <option value="Paid">Completed / Paid</option>
                <option value="Pending">Pending</option>
                <option value="Waived">Waived</option>
              </select>
            </div>

            <div class="form-group">
              <label for="amount_paid">Amount Paid ($):</label>
              <input type="number" step="0.01" id="amount_paid" name="amount_paid" class="form-control" value="0.00">
            </div>
          </div>

          <button type="submit" id="registrationSubmitBtn" class="btn btn-accent" style="width:100%; padding:10px; font-weight:600; cursor:pointer;">Register Attendee</button>
        </form>
      </div>

      <!-- Right Column: Current Registrations Roster -->
      <div class="card">
        <h3 style="margin-top:0; color:#28089a;">Current Roster & Check-In</h3>
        <table class="data-table" id="rosterTable">
          <thead>
            <tr>
              <th>Attendee Name</th>
              <th>Payment Status</th>
              <th>Payment Method</th>
              <th>Amount Paid</th>
              <th>Registration</th>
              <th>Check-In</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </main>

  <?php include_once __DIR__ . '/include/footer.php'; ?>
</body>

</html>