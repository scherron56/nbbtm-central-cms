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
  <title>Event Registration - Administrative Portal</title>
  <link href="https://api.fontshare.com/v2/css?f%5B%5D=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&amp;f%5B%5D=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&amp;display=swap" rel="stylesheet">
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
    .read-only-banner {
      background-color: #f1f5f9;
      border-left: 4px solid #0ea5e9;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 4px;
      color: #334155;
    }

    .registration-wrapper {
      width: 100%;
      max-width: 1000px;
      margin: 2rem auto;
    }
    .event-info-box {
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 20px;
      display: none;
    }
    .badge-fee {
      background: #dbeafe;
      color: #1e40af;
      padding: 4px 8px;
      border-radius: 4px;
      font-weight: bold;
      font-size: 0.95rem;
    }
    .badge-free {
      background: #dcfce7;
      color: #15803d;
      padding: 4px 8px;
      border-radius: 4px;
      font-weight: bold;
      font-size: 0.95rem;
    }
    .contact-select-row {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    .contact-select-row select {
      flex: 1;
    }
    .btn-add-contact {
      background-color: #28089a;
      color: #ffffff;
      padding: 8px 16px;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      white-space: nowrap;
      height: 38px;
      transition: background-color 0.15s ease;
    }
    .btn-add-contact:hover {
      background-color: #1e0573;
      color: #ffffff;
      text-decoration: none;
    }
    select, input[type="number"] {
      width: 100%;
      height: 38px;
      padding: 0 10px;
      border-radius: 6px;
      border: 1px solid #cbd5e1;
      color: #28089a;
      font-family: inherit;
    }
    .status-indicator {
      font-weight: bold;
      padding: 6px 12px;
      border-radius: 6px;
      display: inline-block;
      margin-top: 5px;
    }
    .status-completed {
      background-color: #dcfce7;
      color: #15803d;
    }
    .status-pending {
      background-color: #fee2e2;
      color: #991b1b;
    }
    .btn-edit-reg {
      background-color: #0ea5e9;
      color: #ffffff;
      padding: 4px 10px;
      border: none;
      border-radius: 4px;
      font-weight: 600;
      font-size: 0.8rem;
      cursor: pointer;
    }
    .btn-delete-reg {
      background-color: #ef4444;
      color: #ffffff;
      padding: 4px 10px;
      border: none;
      border-radius: 4px;
      font-weight: 600;
      font-size: 0.8rem;
      cursor: pointer;
      margin-left: 5px;
    }
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script>
    $(document).ready(function() {
        const IS_ADMIN = <?php echo $adminUser ? 'true' : 'false'; ?>;
        let eventsData = [];
        let currentRegistrations = [];

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

        loadRegistrableEvents(preselectedEventId);
        loadContactsDropdown();

        function loadRegistrableEvents(selectedId = null) {
            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_events' },
                dataType: 'json',
                success: function(data) {
                    if (data.success && data.programs_events) {
                        eventsData = data.programs_events;
                        let $select = $('#prg_evnt_id');
                        $select.find('option:not(:first)').remove();

                        // Strict integer check for events requiring registration
                        let registrableEvents = eventsData.filter(e => parseInt(e.requires_registration) === 1);

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
                        showStatusMessage('Error fetching events: ' + data.error, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showStatusMessage('Server error fetching events list: ' + error, 'error');
                }
            });
        }

        function loadContactsDropdown() {
            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_contacts_list' },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        let $cSelect = $('#contact_id');
                        $cSelect.find('option:not(:first)').remove();
                        $.each(data.contacts, function(i, c) {
                            $cSelect.append(`<option value="${c.contact_id}">${c.full_name}</option>`);
                        });
                    }
                }
            });
        }

        function updateCompletionPreview() {
            let status = $('#payment_status').val();
            let $box = $('#completionPreviewBox');

            if (['Completed', 'Paid', 'Waived'].includes(status)) {
                $box.html('<span class="status-indicator status-completed">✓ Registration Status: FULLY COMPLETED</span>');
            } else {
                $box.html('<span class="status-indicator status-pending">⚠ Registration Status: NOT COMPLETED (Unpaid/Pending)</span>');
            }
        }

        // Handle Event Selection
        $('#prg_evnt_id').on('change', function() {
            clearStatusMessage();
            let eventId = $(this).val();
            resetForm();
            $('#prg_evnt_id').val(eventId);
            $('#form_prg_evnt_id').val(eventId); // Sync hidden form field

            if (!eventId) {
                $('#eventDetailsBox').hide();
                $('#registrationFormFields').hide();
                $('#rosterSection').slideUp();
                return;
            }

            let ev = eventsData.find(e => e.prg_evnt_id == eventId);
            if (ev) {
                $('#infoLocation').text(ev.location || 'N/A');
                $('#infoSponsor').text(ev.ministry_name || 'N/A');
                
                let reqFee = parseInt(ev.requires_fee) === 1;

                if (reqFee) {
                    let fee = parseFloat(ev.registration_fee || 0);
                    $('#infoFee').html(`<span class="badge-fee">$${fee.toFixed(2)}</span>`);
                    $('#amount_paid').val(fee.toFixed(2));
                    $('#payment_status').val('Pending');
                    $('#payment_method').val('Credit Card');
                    
                    $('#paymentFieldsSection').slideDown();
                    updateCompletionPreview();
                } else {
                    $('#infoFee').html('<span class="badge-free">Free Event (No Fee)</span>');
                    $('#amount_paid').val('0.00');
                    $('#payment_status').val('Paid');
                    $('#payment_method').val('None');
                    
                    $('#paymentFieldsSection').slideUp();
                    $('#completionPreviewBox').html('<span class="status-indicator status-completed">✓ Free Event - Instant Check-In Allowed</span>');
                }

                $('#eventDetailsBox').slideDown();
                if (IS_ADMIN) {
                    $('#registrationFormFields').slideDown();
                }
                loadExistingRegistrations(eventId);
            }
        });

        function loadExistingRegistrations(eventId) {
            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_event_registrations', prg_evnt_id: eventId },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        currentRegistrations = data.registrations || [];
                        renderRegistrationsRoster();
                        $('#rosterSection').slideDown();
                    }
                }
            });
        }

        function renderRegistrationsRoster() {
            let $tbody = $('#rosterTableBody').empty();

            if (currentRegistrations.length === 0) {
                $tbody.append('<tr><td colspan="6" style="text-align:center; color:#64748b; padding:15px;">No existing registrations recorded for this event.</td></tr>');
                return;
            }

            $.each(currentRegistrations, function(i, r) {
                let statusBadge = (parseInt(r.is_completed) === 1 || ['paid', 'completed', 'waived'].includes(String(r.payment_status).toLowerCase()))
                    ? '<span class="status-indicator status-completed" style="padding: 2px 8px; font-size:0.8rem;">Completed</span>'
                    : '<span class="status-indicator status-pending" style="padding: 2px 8px; font-size:0.8rem;">Pending</span>';

                let actionBtns = IS_ADMIN ? `
                    <button type="button" class="btn-edit-reg" data-id="${r.registration_id}">Edit</button>
                    <button type="button" class="btn-delete-reg" data-id="${r.registration_id}">Cancel</button>
                ` : '<em>Read Only</em>';

                $tbody.append(`
                    <tr>
                        <td><strong>${r.full_name || 'Unknown'}</strong></td>
                        <td>${r.payment_status || 'Pending'}</td>
                        <td>${r.payment_method || 'None'}</td>
                        <td>$${parseFloat(r.amount_paid || 0).toFixed(2)}</td>
                        <td>${statusBadge}</td>
                        <td style="text-align:right;">${actionBtns}</td>
                    </tr>
                `);
            });
        }

        // Edit Registration Trigger
        $(document).on('click', '.btn-edit-reg', function() {
            if (!IS_ADMIN) return;
            clearStatusMessage();
            let regId = $(this).data('id');
            let reg = currentRegistrations.find(r => r.registration_id == regId);

            if (reg) {
                $('#registration_id').val(reg.registration_id);
                $('#contact_id').val(reg.contact_id);
                $('#payment_method').val(reg.payment_method || 'None');
                $('#payment_status').val(reg.payment_status || 'Paid');
                $('#amount_paid').val(parseFloat(reg.amount_paid || 0).toFixed(2));

                $('#submitBtn').text('Update Registration').addClass('btn-pulse');
                $('#formLegendText').text('Edit Attendance Registration');
                updateCompletionPreview();
                $('html, body').animate({ scrollTop: $('#registrationFormFields').offset().top - 50 }, 300);
            }
        });

        // Cancel Registration Trigger
        $(document).on('click', '.btn-delete-reg', function() {
            if (!IS_ADMIN) return;
            clearStatusMessage();
            let regId = $(this).data('id');
            let eventId = $('#prg_evnt_id').val();

            if (!confirm('Are you sure you want to cancel and remove this registration?')) return;

            $.ajax({
                url: 'prg_event_api.php',
                type: 'POST',
                data: { action: 'cancel_registration', registration_id: regId },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        showStatusMessage(res.message || 'Registration canceled.', 'success');
                        loadExistingRegistrations(eventId);
                    } else {
                        showStatusMessage(res.error || 'Failed to cancel registration.', 'error');
                    }
                }
            });
        });

        $('#payment_status').on('change', updateCompletionPreview);

        function resetForm() {
            clearStatusMessage();
            let selectedEvent = $('#prg_evnt_id').val();
            if ($('#registrationForm').length) {
                $('#registrationForm')[0].reset();
                $('#registration_id').val('');
                $('#prg_evnt_id').val(selectedEvent);
                $('#form_prg_evnt_id').val(selectedEvent);
                $('#submitBtn').text('Save Registration');
                $('#formLegendText').text('Attendee & Registration Details');
            }
        }

        $('#resetBtn').on('click', function() {
            resetForm();
            $('#eventDetailsBox').slideUp();
            $('#registrationFormFields').slideUp();
            $('#rosterSection').slideUp();
        });

        // Submit Event Registration (Handles both INSERT and UPDATE)
        $('#registrationForm').on('submit', function(e) {
            e.preventDefault();
            clearStatusMessage();

            let eventId = $('#prg_evnt_id').val();
            let contactId = $('#contact_id').val();

            // Client-side quick check
            if (!eventId || !contactId) {
                showStatusMessage('Please select both an event and a contact.', 'error');
                return;
            }

            let $btn = $('#submitBtn');
            $btn.prop('disabled', true).text('Processing...');

            let actionName = $('#registration_id').val() ? 'save_registration' : 'register_contact';

            // Explicitly include prg_evnt_id in the AJAX request payload
            let formData = $(this).serialize() + '&prg_evnt_id=' + encodeURIComponent(eventId) + '&action=' + actionName;

            $.ajax({
                url: 'prg_event_api.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        showStatusMessage(res.message || 'Registration saved!', 'success');
                        resetForm();
                        $('#prg_evnt_id').val(eventId).trigger('change');
                    } else {
                        showStatusMessage((res.message || res.error || 'Unknown error occurred.'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showStatusMessage('Server communication error: ' + error, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });
    });
  </script>
</head>
<body>
  <?php require_once("config/db.php"); ?>
  <?php include 'include/header.php'; ?> 

  <h1>Event Registration Portal</h1>

  <div id="status-message" class="alert-box"></div>

  <div class="registration-wrapper">
    <!-- STEP 1: EVENT SELECTION FIELDSET -->
    <fieldset class="form-grid-section-8">
      <legend>
        <h2>Event Selection</h2>
      </legend>
      <div class="field-group" style="--colspan: 8;">
        <label for="prg_evnt_id"><h3 style="color: #28089a; margin: 0 0 5px 0;">Select Event:</h3></label>
        <select id="prg_evnt_id" name="prg_evnt_id" required>
          <option value="">-- Choose an Event --</option>
        </select>
      </div>
    </fieldset>

    <!-- DYNAMIC EVENT OVERVIEW BOX -->
    <div id="eventDetailsBox" class="event-info-box">
      <h3 style="margin-top:0; color: #28089a; border-bottom: 1px solid #cbd5e1; padding-bottom: 5px;">Event Overview</h3>
      <div class="form-row" style="display: flex; gap: 20px;">
        <p style="flex:1; margin: 0;"><strong>Location:</strong> <span id="infoLocation">-</span></p>
        <p style="flex:1; margin: 0;"><strong>Sponsor:</strong> <span id="infoSponsor">-</span></p>
        <p style="flex:1; margin: 0;"><strong>Required Fee:</strong> <span id="infoFee">-</span></p>
      </div>
    </div>

    <?php if ($adminUser): ?>
      <!-- STEP 2: ATTENDEE & PAYMENT DETAILS FIELDSET -->
      <div id="registrationFormFields" style="display: none;">
        <form id="registrationForm" name="registrationForm">
          <input type="hidden" id="registration_id" name="registration_id" value="">
          <input type="hidden" id="form_prg_evnt_id" name="prg_evnt_id" value="">

          <fieldset class="form-grid-section-8 fieldset-relative">
            <legend>
              <h2 id="formLegendText">Attendee & Registration Details</h2>
            </legend>

            <div class="field-group" style="--colspan: 8;">
              <label for="contact_id">Select Contact / Attendee:</label>
              <div class="contact-select-row">
                <select id="contact_id" name="contact_id" required>
                  <option value="">-- Select Contact --</option>
                </select>
                <a href="contacts.php" class="btn-add-contact" title="Create a new contact if person isn't listed">
                  + Add New Contact
                </a>
              </div>
            </div>

            <!-- DYNAMIC PAYMENT SECTION (HIDDEN IF NO FEE REQUIRED) -->
            <div id="paymentFieldsSection" style="display: contents;">
              <div class="field-group" style="--colspan: 4;">
                <label for="payment_method">Payment Method:</label>
                <select id="payment_method" name="payment_method">
                  <option value="None">None / Free</option>
                  <option value="Cash">Cash</option>
                  <option value="Check">Check</option>
                  <option value="Credit Card">Credit Card</option>
                  <option value="Online">Online Transfer</option>
                </select>
              </div>

              <div class="field-group" style="--colspan: 4;">
                <label for="payment_status">Payment Status:</label>
                <select id="payment_status" name="payment_status">
                  <option value="Paid">Completed / Paid</option>
                  <option value="Pending">Pending</option>
                  <option value="Waived">Waived</option>
                </select>
              </div>

              <div class="field-group" style="--colspan: 8;">
                <label for="amount_paid">Amount Paid ($):</label>
                <input type="number" step="0.01" id="amount_paid" name="amount_paid" value="0.00">
              </div>
            </div>

            <div class="field-group" style="--colspan: 8; margin-top: 10px;" id="completionPreviewBox">
              <!-- Dynamic indicator inserted here -->
            </div>
          </fieldset>

          <!-- SUBMIT & RESET BUTTONS -->
          <fieldset class="form-grid-section-short-rght">
            <div class="field-group" style="--colspan: 3;">
              <button type="submit" id="submitBtn" class="btn-pulse">Save Registration</button>
            </div>
            <div class="field-group" style="--colspan: 3;">
              <button type="button" id="resetBtn" class="btn-secondary">Reset</button>
            </div>
          </fieldset> 
        </form>
      </div>
    <?php else: ?>
      <div class="read-only-banner">
        <strong>Read-Only Mode:</strong> You must be an administrator to register contacts for events.
      </div>
    <?php endif; ?>

    <!-- ROSTER OF CURRENT REGISTRATIONS WITH EDIT/CANCEL CONTROLS -->
    <div id="rosterSection" class="card" style="display: none; margin-top: 30px;">
      <h3>Current Registered Attendees for Event</h3>
      <table class="data-table">
        <thead>
          <tr>
            <th>Attendee Name</th>
            <th>Payment Status</th>
            <th>Payment Method</th>
            <th>Amount Paid</th>
            <th>Status</th>
            <th style="text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody id="rosterTableBody">
          <!-- Dynamically populated rows -->
        </tbody>
      </table>
    </div>

  </div>
<?php include_once 'include/footer.php'; ?>
</body>
</html>