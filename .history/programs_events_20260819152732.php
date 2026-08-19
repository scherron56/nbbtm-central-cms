<?php
// events.php
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
$canEdit = canEdit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Event Management Module - NBBTM CMS</title>
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
    .read-only-banner {
      background-color: #f1f5f9;
      border-left: 4px solid #0ea5e9;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 4px;
      color: #334155;
    }
    .doc-management-box {
      background: #f8fafc;
      border: 1px dashed #cbd5e1;
      border-radius: 6px;
      padding: 12px;
      margin-top: 5px;
    }
    .doc-status-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 8px;
    }
    .support-min-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 12px;
      padding: 12px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
    }
    .support-min-item {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .support-radio-options {
      margin-left: 24px;
      display: flex;
      gap: 15px;
      font-size: 0.85rem;
    }
    .auth-box {
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .auth-item {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.95rem;
      color: #1e293b;
      cursor: pointer;
      background: #ffffff;
      padding: 8px 12px;
      border: 1px solid #e2e8f0;
      border-radius: 4px;
    }
    .auth-item input[type="checkbox"] {
      cursor: pointer;
      width: 18px;
      height: 18px;
    }
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script>
    $(document).ready(function() {
        const CAN_EDIT = <?php echo $canEdit ? 'true' : 'false'; ?>;
        
        // Allowed support ministries: 5000, 5002, 5007, 5008, 5020, 5018
        const TARGET_MIN_IDS = [5000, 5002, 5007, 5008, 5020, 5018];
        const ADMIN_SUPPORT_ID = 5000;
        const BUILDING_MAINT_ID = 5018;

        let contactsCache = [];

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

        // Dynamic Schedule Dates
        $('#btnAddDate').on('click', function() {
            $('#dateTimeContainer').append(`
                <div class="date-time-row">
                    <div class="field-group">
                        <label>Start Date & Time:</label>
                        <input type="datetime-local" name="start_datetimes[]" required class="form-control">
                    </div>
                    <div class="field-group">
                        <label>End Date & Time (Optional):</label>
                        <input type="datetime-local" name="end_datetimes[]" class="form-control">
                    </div>
                    <div class="btn-remove-wrapper">
                        <button type="button" class="btn btn-danger btnRemoveDate">&times;</button>
                    </div>
                </div>`);
            toggleDateButtons();
        });

        $(document).on('click', '.btnRemoveDate', function() {
            $(this).closest('.date-time-row').remove();
            toggleDateButtons();
        });

        function toggleDateButtons() {
            let rows = $('.date-time-row');
            rows.find('.btnRemoveDate').prop('disabled', rows.length <= 1);
        }

        // Dynamic Budgets
        $('#btnAddBudget').on('click', function() {
            $('#budgetContainer').append(`
                <div class="form-row budget-row" style="margin-bottom: 8px;">
                    <div class="form-group" style="flex:2;">
                        <input type="text" name="budget_desc[]" placeholder="Description" required class="form-control budget-calc-trigger">
                    </div>
                    <div class="form-group">
                        <select name="budget_type[]" class="form-control budget-calc-trigger">
                            <option value="Expense">Expense</option>
                            <option value="Income">Income</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="number" step="0.01" name="budget_amount[]" placeholder="0.00" value="0.00" required class="form-control budget-calc-trigger">
                    </div>
                    <div style="display:flex; align-items:flex-end; margin-bottom:15px;">
                        <button type="button" class="btn btn-danger btnRemoveBudget" style="padding:6px 10px;">&times;</button>
                    </div>
                </div>`);
            toggleBudgetButtons();
        });

        $(document).on('click', '.btnRemoveBudget', function() {
            $(this).closest('.budget-row').remove();
            toggleBudgetButtons();
            calculateLiveBudgetSummary();
        });

        function toggleBudgetButtons() {
            let rows = $('.budget-row');
            rows.find('.btnRemoveBudget').prop('disabled', rows.length <= 1);
        }

        $(document).on('input change', '.budget-calc-trigger', function() {
            calculateLiveBudgetSummary();
        });

        function calculateLiveBudgetSummary() {
            let totalExpenses = 0;
            let totalIncome = 0;

            $('.budget-row').each(function() {
                let type = $(this).find('select[name="budget_type[]"]').val();
                let amount = parseFloat($(this).find('input[name="budget_amount[]"]').val()) || 0;

                if (type === 'Expense') {
                    totalExpenses += amount;
                } else {
                    totalIncome += amount;
                }
            });

            let netBalance = totalIncome - totalExpenses;

            $('#summaryExpenses').text('$' + totalExpenses.toFixed(2));
            $('#summaryIncome').text('$' + totalIncome.toFixed(2));
            $('#summaryNet').text((netBalance >= 0 ? '+' : '') + '$' + netBalance.toFixed(2));

            let $bc = $('#balanceCard');
            if (netBalance < 0) {
                $bc.css({'background': '#fef2f2', 'border-color': '#fee2e2', 'color': '#ef4444'});
            } else if (netBalance > 0) {
                $bc.css({'background': '#f0fdf4', 'border-color': '#dcfce7', 'color': '#10b981'});
            } else {
                $bc.css({'background': '#f8fafc', 'border-color': '#e2e8f0', 'color': '#334155'});
            }
        }

        $(document.body).on('change', '#requires_fee', function() {
            if (this.checked) {
                $('#fee_container').slideDown();
            } else {
                $('#fee_container').slideUp();
                $('#registration_fee').val('0.00');
            }
        });

        // Toggle Building Maintenance options & refresh sign-offs
        $(document).on('change', '.chk-support-min', function() {
            let target = $(this).data('target');
            if(target) {
                if(this.checked) {
                    $(target).slideDown();
                } else {
                    $(target).slideUp();
                }
            }
            refreshSignoffCheckboxes();
        });

        // 1. Load Contacts & Populate Dropdown (along with Phone and Email)
        function loadContactsDropdown(preselectedId = null) {
            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_contacts_list' },
                dataType: 'json',
                success: function(data) {
                    if(data.success && data.contacts) {
                        contactsCache = data.contacts;
                        let $cSelect = $('#contact_id');
                        $cSelect.find('option:not(:first)').remove();

                        $.each(contactsCache, function(i, c) {
                            let displayName = c.full_name ? c.full_name.trim() : `Contact #${c.contact_id}`;
                            let extraDetails = [];
                            if (c.phone && c.phone.trim() !== '') extraDetails.push(c.phone.trim());
                            if (c.email && c.email.trim() !== '') extraDetails.push(c.email.trim());

                            let label = displayName;
                            if (extraDetails.length > 0) {
                                label += ` (${extraDetails.join(' | ')})`;
                            }

                            $cSelect.append(`<option value="${c.contact_id}">${escapeHtml(label)}</option>`);
                        });

                        if (preselectedId) {
                            $cSelect.val(preselectedId).trigger('change');
                        }
                    } else {
                        showStatusMessage('Could not load contacts: ' + (data.error || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showStatusMessage('Server error fetching contacts list.', 'error');
                }
            });
        }

        $('#contact_id').on('change', function() {
            let cid = $(this).val();
            let selectedText = $('#contact_id option:selected').text();

            if(!cid) {
                $('#contact_phone').val('');
                $('#contact_email').val('');
                $('#signoff_contact_label').html('<strong>Signed by Primary Contact</strong>');
                return;
            }

            let found = contactsCache.find(c => String(c.contact_id) === String(cid));
            if(found) {
                if(found.phone) $('#contact_phone').val(found.phone);
                if(found.email) $('#contact_email').val(found.email);
            }

            let cleanName = selectedText.split('(')[0].trim();
            $('#signoff_contact_label').html(`<strong>Signed by Primary Contact (${escapeHtml(cleanName)})</strong>`);
        });

        // 2. Load Ministries and Support Ministries (5000, 5002, 5007, 5008, 5020, 5018)
        function loadMinistries() {
            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_ministries_list' },
                dataType: 'json',
                success: function(data) {
                    if(data.success) {
                        let $minSelect = $('#min_comm_id');
                        $minSelect.find('option:not(:first)').remove();
                        let $supportGrid = $('#ministrySupportContainer').empty();

                        $.each(data.ministries, function(i, m) {
                            let mId = parseInt(m.min_comm_id);
                            $minSelect.append(`<option value="${mId}">${escapeHtml(m.min_comm_name)}</option>`);

                            if(TARGET_MIN_IDS.includes(mId)) {
                                let isAdmin = (mId === ADMIN_SUPPORT_ID);
                                let isMaint = (mId === BUILDING_MAINT_ID);

                                let itemHtml = `
                                    <div class="support-min-item">
                                        <label style="font-weight: 600; cursor:pointer;">
                                            <input type="checkbox" name="support_min_ids[]" value="${mId}" data-name="${escapeHtml(m.min_comm_name)}" class="chk-support-min" 
                                                ${isAdmin ? 'checked onclick="return false;"' : ''} 
                                                ${isMaint ? 'data-target="#maintOptions"' : ''}>
                                            ${escapeHtml(m.min_comm_name)}
                                        </label>
                                `;

                                if(isAdmin) {
                                    itemHtml += `<input type="hidden" name="support_min_ids[]" value="${mId}">`;
                                }

                                if(isMaint) {
                                    itemHtml += `
                                        <div id="maintOptions" class="support-radio-options" style="display:none;">
                                            <label><input type="radio" name="support_option_${mId}" value="Option A" checked> Option A</label>
                                            <label><input type="radio" name="support_option_${mId}" value="Option B"> Option B</label>
                                        </div>
                                    `;
                                }

                                itemHtml += `</div>`;
                                $supportGrid.append(itemHtml);
                            }
                        });

                        refreshSignoffCheckboxes();
                    }
                }
            });
        }

        // 3. Dynamically Populate Sign-off Checkboxes for Selected Affected Ministries
        function refreshSignoffCheckboxes() {
            let $container = $('#dynamicMinistrySignoffs').empty();
            $('.chk-support-min:checked').each(function() {
                let minName = $(this).data('name');
                let minId = parseInt($(this).val());
                if (minName && minId !== ADMIN_SUPPORT_ID) {
                    $container.append(`
                        <label class="auth-item">
                            <input type="checkbox" name="signoff_min_${minId}" class="chk-signoff" required>
                            <span><strong>Signed by Affected Ministry:</strong> ${minName}</span>
                        </label>
                    `);
                }
            });
        }

        function loadEventDropdown(selectedId = null) {
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
                            $select.append(`<option value="${ev.prg_evnt_id}">${escapeHtml(ev.prg_evnt_name)}${dispDate}</option>`);
                        });
                        if (selectedId) {
                            $select.val(selectedId).trigger('change');
                        }
                    }
                }
            });
        }

        $('#event_select').on('change', function() {
            clearStatusMessage();
            let id = $(this).val();
            if(!id) {
                resetForm();
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
                        $('#prg_evnt_id').val(ev.prg_evnt_id);
                        $('#prg_evnt_name').val(ev.prg_evnt_name);
                        $('#min_comm_id').val(ev.min_comm_id);
                        $('#contact_id').val(ev.contact_id || '').trigger('change');
                        $('#contact_phone').val(ev.contact_phone || '');
                        $('#contact_email').val(ev.contact_email || '');
                        $('#location').val(ev.location);
                        $('#prg_evnt_purpose').val(ev.prg_evnt_purpose || '');
                        $('#goal').val(ev.goal || '');
                        $('#notes').val(ev.notes || '');
                        
                        // Support checkboxes & radio options
                        $('.chk-support-min').not('[onclick]').prop('checked', false);
                        $('#maintOptions').hide();
                        if(data.support_ministries) {
                            $.each(data.support_ministries, function(i, sm) {
                                let $chk = $(`.chk-support-min[value="${sm.min_comm_id}"]`);
                                $chk.prop('checked', true);
                                if(sm.option) {
                                    $(`input[name="support_option_${sm.min_comm_id}"][value="${sm.option}"]`).prop('checked', true);
                                    $('#maintOptions').show();
                                }
                            });
                        }
                        refreshSignoffCheckboxes();

                        // Planning Packet Status
                        $('#remove_document_flag').val('0');
                        $('#event_document').val('');

                        if (ev.document_name && ev.document_name.trim() !== '') {
                            $('#doc_status_text').html(`Current Packet: <a href="download_document.php?prg_evnt_id=${ev.prg_evnt_id}" target="_blank" class="link-btn" style="font-weight:bold;">📄 ${escapeHtml(ev.document_name)}</a>`);
                            $('#btnRemoveDoc').show();
                            $('#file_input_label').text('Replace Document / Packet (Optional):');
                        } else {
                            $('#doc_status_text').html('<span style="color:#64748b; font-style:italic;">No planning packet stored in database.</span>');
                            $('#btnRemoveDoc').hide();
                            $('#file_input_label').text('Upload Document / Packet (.pdf, .doc, .docx):');
                        }
                        
                        let reqReg = parseInt(ev.requires_registration) === 1;
                        let reqFee = parseInt(ev.requires_fee) === 1;

                        $('#requires_registration').prop('checked', reqReg);
                        $('#requires_fee').prop('checked', reqFee);

                        if (reqFee) {
                            $('#fee_container').show();
                            $('#registration_fee').val(parseFloat(ev.registration_fee || 0).toFixed(2));
                        } else {
                            $('#fee_container').hide();
                            $('#registration_fee').val('0.00');
                        }
                        
                        if (reqReg) {
                            $('#btnGoRegister').attr('href', 'event_registration.php?prg_evnt_id=' + ev.prg_evnt_id).show();
                        } else {
                            $('#btnGoRegister').hide();
                        }

                        // Schedules populate
                        $('#dateTimeContainer').empty();
                        if(data.schedules && data.schedules.length > 0) {
                            $.each(data.schedules, function(i, sch) {
                                let startVal = sch.start_datetime ? sch.start_datetime.replace(' ', 'T').substring(0,16) : '';
                                let endVal = sch.end_datetime ? sch.end_datetime.replace(' ', 'T').substring(0,16) : '';
                                
                                $('#dateTimeContainer').append(`
                                    <div class="date-time-row">
                                        <div class="field-group">
                                            <label>Start Date & Time:</label>
                                            <input type="datetime-local" name="start_datetimes[]" value="${startVal}" required class="form-control">
                                        </div>
                                        <div class="field-group">
                                            <label>End Date & Time (Optional):</label>
                                            <input type="datetime-local" name="end_datetimes[]" value="${endVal}" class="form-control">
                                        </div>
                                        <div class="btn-remove-wrapper">
                                            <button type="button" class="btn btn-danger btnRemoveDate">&times;</button>
                                        </div>
                                    </div>`);
                            });
                        } else {
                            $('#btnAddDate').trigger('click');
                        }
                        toggleDateButtons();

                        // Itemized budgets populate
                        $('#budgetContainer').empty();
                        if(data.budgets && data.budgets.length > 0) {
                            $.each(data.budgets, function(i, bud) {
                                $('#budgetContainer').append(`
                                    <div class="form-row budget-row" style="margin-bottom: 8px;">
                                        <div class="form-group" style="flex:2;">
                                            <input type="text" name="budget_desc[]" value="${escapeHtml(bud.item_description)}" required class="form-control budget-calc-trigger">
                                        </div>
                                        <div class="form-group">
                                            <select name="budget_type[]" class="form-control budget-calc-trigger">
                                                <option value="Expense" ${bud.item_type === 'Expense' ? 'selected' : ''}>Expense</option>
                                                <option value="Income" ${bud.item_type === 'Income' ? 'selected' : ''}>Income</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <input type="number" step="0.01" name="budget_amount[]" value="${bud.amount}" required class="form-control budget-calc-trigger">
                                        </div>
                                        <div style="display:flex; align-items:flex-end; margin-bottom:15px;">
                                            <button type="button" class="btn btn-danger btnRemoveBudget">&times;</button>
                                        </div>
                                    </div>`);
                            });
                        } else {
                            $('#btnAddBudget').trigger('click');
                        }
                        toggleBudgetButtons();
                        calculateLiveBudgetSummary();
                        
                        // Check off sign-offs for existing loaded record
                        $('.chk-signoff').prop('checked', true);

                        if ($('#btnDelete').length) $('#btnDelete').show();
                        loadRoster(id);
                    }
                }
            });
        });

        $('#btnRemoveDoc').on('click', function() {
            if (confirm('Are you sure you want to remove the stored planning packet from this event?')) {
                $('#remove_document_flag').val('1');
                $('#doc_status_text').html('<span style="color:#dc2626; font-weight:bold;">Document marked for removal upon save.</span>');
                $(this).hide();
            }
        });

        $('#eventForm').on('submit', function(e) {
            e.preventDefault();
            if (!CAN_EDIT) return;
            clearStatusMessage();

            let uncheckedSignoffs = $('.chk-signoff:not(:checked)');
            if (uncheckedSignoffs.length > 0) {
                showStatusMessage('Please verify and check off all Authorization & Sign-off items before saving.', 'error');
                $('html, body').animate({ scrollTop: $('#acknowledgementSection').offset().top - 40 }, 300);
                return;
            }

            let formData = new FormData(this);

            $.ajax({
                url: 'prg_event_api.php?action=save_event',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if(res.success) {
                        showStatusMessage(res.message, 'success');
                        loadEventDropdown(res.prg_evnt_id);
                    } else {
                        showStatusMessage("Error: " + (res.message || res.error), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showStatusMessage("Server Request Failed: " + error, 'error');
                }
            });
        });

        function loadRoster(eventId) {
            $('#rosterPlaceholder').hide();
            $('#rosterContent').show();

            $.ajax({
                url: 'prg_event_api.php',
                type: 'GET',
                data: { action: 'get_event_roster', prg_evnt_id: eventId },
                dataType: 'json',
                success: function(data) {
                    if(data.success) {
                        let $grid = $('#attendanceGrid').empty();
                        let checkedInIds = data.attendance.map(a => parseInt(a.contact_id));

                        if(data.registrations.length === 0) {
                            $grid.append('<p>No registered attendees found for this event.</p>');
                        } else {
                            $.each(data.registrations, function(i, r) {
                                let isCheckedIn = checkedInIds.includes(parseInt(r.contact_id));
                                $grid.append(`
                                    <div class="card attendance-card ${isCheckedIn ? 'active-checkin' : ''}" style="padding:10px; border:1px solid #ccc; text-align:center;">
                                        <strong>${escapeHtml(r.full_name)}</strong>
                                        <br><br>
                                        <button type="button" class="btn ${isCheckedIn ? 'btn-success' : 'btn-secondary'} btn-toggle" 
                                                data-contact="${r.contact_id}" ${!CAN_EDIT ? 'disabled' : ''}>
                                            ${isCheckedIn ? 'Checked In ✓' : 'Mark Present'}
                                        </button>
                                    </div>
                                `);
                            });
                        }
                    }
                }
            });
        }

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
                    if(res.success) {
                        loadRoster(eventId);
                    } else {
                        showStatusMessage(res.error || "Failed to update attendance.", 'error');
                    }
                }
            });
        });

        function resetForm() {
            clearStatusMessage();
            if ($('#eventForm').length) {
                $('#eventForm')[0].reset();
                $('#prg_evnt_id').val('');
                $('#fee_container').hide();
                $('#remove_document_flag').val('0');
                $('#doc_status_text').html('<span style="color:#64748b; font-style:italic;">No planning packet stored in database.</span>');
                $('#btnRemoveDoc').hide();
                $('#file_input_label').text('Upload Document / Packet (.pdf, .doc, .docx):');
                $('.chk-support-min').not('[onclick]').prop('checked', false);
                $('#maintOptions').hide();
                $('.chk-signoff').prop('checked', false);
                $('#signoff_contact_label').html('<strong>Signed by Primary Contact</strong>');
                refreshSignoffCheckboxes();
                if ($('#btnDelete').length) $('#btnDelete').hide();
            }
            $('#event_select').val('');
            $('#btnGoRegister').hide();
            $('#rosterPlaceholder').show();
            $('#rosterContent').hide();
            
            $('#dateTimeContainer').html(`
            <div class="date-time-row">
                <div class="field-group">
                    <label>Start Date & Time:</label>
                    <input type="datetime-local" name="start_datetimes[]" required class="form-control">
                </div>
                <div class="field-group">
                    <label>End Date & Time (Optional):</label>
                    <input type="datetime-local" name="end_datetimes[]" class="form-control">
                </div>
                <div class="btn-remove-wrapper">
                    <button type="button" class="btn btn-danger btnRemoveDate" disabled>&times;</button>
                </div>
            </div>`);

            $('#budgetContainer').html(`
                <div class="form-row budget-row" style="margin-bottom: 8px;">
                    <div class="form-group" style="flex:2;">
                        <input type="text" name="budget_desc[]" placeholder="Description" required class="form-control budget-calc-trigger">
                    </div>
                    <div class="form-group">
                        <select name="budget_type[]" class="form-control budget-calc-trigger">
                            <option value="Expense">Expense</option>
                            <option value="Income">Income</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="number" step="0.01" name="budget_amount[]" placeholder="0.00" value="0.00" required class="form-control budget-calc-trigger">
                    </div>
                    <div style="display:flex; align-items:flex-end; margin-bottom:15px;">
                        <button type="button" class="btn btn-danger btnRemoveBudget" disabled>&times;</button>
                    </div>
                </div>`);
            
            calculateLiveBudgetSummary();
        }

        $('#btnReset').on('click', resetForm);

        $('#btnDelete').on('click', function() {
            if (!CAN_EDIT) return;
            clearStatusMessage();
            if(confirm("Are you sure you want to delete this event? All associated schedules, registrations, support assignments, and budgets will be permanently deleted.")) {
                $.ajax({
                    url: 'prg_event_api.php',
                    type: 'POST',
                    data: { action: 'delete_event', prg_evnt_id: $('#prg_evnt_id').val() },
                    dataType: 'json',
                    success: function(res) {
                        if(res.success) {
                            showStatusMessage(res.message, 'success');
                            resetForm();
                            loadEventDropdown();
                        } else {
                            showStatusMessage(res.error || "Failed to delete event.", 'error');
                        }
                    }
                });
            }
        });

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        loadContactsDropdown();
        loadMinistries();
        loadEventDropdown();
    });
  </script>
</head>
<body>
  <?php include 'include/header.php'; ?> 

<div class="dashboard-layout">
    <div class="card">
        <h2>Manage Event Details</h2>

        <div id="status-message" class="alert-box"></div>

        <div class="form-group">
            <label for="event_select">Select Existing Event:</label>
            <select id="event_select" class="form-control">
                <option value="">-- Create New Event --</option>
            </select>
        </div>

        <?php if ($canEdit): ?>
            <form id="eventForm" enctype="multipart/form-data">
                <input type="hidden" id="prg_evnt_id" name="prg_evnt_id">
                <input type="hidden" id="remove_document_flag" name="remove_document" value="0">

                <fieldset class="dashboard-main-grid fieldset-relative">
                    <legend>
                        <h2 id="form-title">Program / Event Information</h2>
                    </legend>
                    <div class="field-group" style="--colspan: 2;">
                        <label for="prg_evnt_name">Event Name:</label>
                        <input type="text" id="prg_evnt_name" name="prg_evnt_name" required class="form-control">
                    </div>

                    <!-- SPONSORING MINISTRY -->
                    <div class="field-group" style="--colspan: 2;">
                        <label for="min_comm_id">Sponsoring Ministry:</label>
                        <select id="min_comm_id" name="min_comm_id" required class="form-control">
                            <option value="">-- Select Sponsoring Ministry --</option>
                        </select>
                    </div>

                    <!-- CONTACT DETAILS -->
                    <div class="field-group" style="--colspan: 2;">
                        <label for="contact_id">Primary Contact:</label>
                        <select id="contact_id" name="contact_id" class="form-control">
                            <option value="">-- Select Contact Person --</option>
                        </select>
                    </div>

                    <div class="field-group" style="--colspan: 1;">
                        <label for="contact_phone">Contact Phone:</label>
                        <input type="text" id="contact_phone" name="contact_phone" placeholder="Phone number" class="form-control">
                    </div>

                    <div class="field-group" style="--colspan: 1;">
                        <label for="contact_email">Contact Email:</label>
                        <input type="email" id="contact_email" name="contact_email" placeholder="Email address" class="form-control">
                    </div>

                    <!-- DYNAMIC DATES CONTAINER -->
                    <div class="field-group" style="--colspan: 2;">
                        <label style="display:flex; justify-content:space-between; align-items:center;">
                            Event Date(s) & Time(s):
                            <button type="button" id="btnAddDate" class="btn btn-sm btn-success" style="padding: 2px 8px; font-size: 0.8rem;">+ Add Date</button>
                        </label>
                        
                        <div id="dateTimeContainer">
                            <div class="date-time-row">
                                <div class="field-group">
                                    <label>Start Date & Time:</label>
                                    <input type="datetime-local" name="start_datetimes[]" required class="form-control">
                                </div>
                                <div class="field-group">
                                    <label>End Date & Time (Optional):</label>
                                    <input type="datetime-local" name="end_datetimes[]" class="form-control">
                                </div>
                                <div class="btn-remove-wrapper">
                                    <button type="button" class="btn btn-danger btnRemoveDate" disabled>&times;</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ITEMIZED BUDGET -->
                    <div class="field-group" style="--colspan: 2;">
                        <label style="display:flex; justify-content:space-between; align-items:center;">
                            Budget Ledger (Income / Expenses):
                            <button type="button" id="btnAddBudget" class="btn btn-sm btn-success" style="padding: 2px 8px; font-size: 0.8rem;">+ Add Line Item</button>
                        </label>
                        <div id="budgetContainer">
                            <div class="form-row budget-row" style="margin-bottom: 8px;">
                                <div class="form-group" style="flex:2;">
                                    <label>Description:</label>   
                                    <input type="text" name="budget_desc[]" placeholder="e.g., Facility Rental / Ticket Sales" required class="form-control budget-calc-trigger">
                                </div>
                                <div class="form-group">
                                    <label>Type:</label>
                                    <select name="budget_type[]" class="form-control budget-calc-trigger">
                                        <option value="Expense">Expense</option>
                                        <option value="Income">Income</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Amount ($):</label>
                                    <input type="number" step="0.01" name="budget_amount[]" placeholder="0.00" value="0.00" required class="form-control budget-calc-trigger">
                                </div>
                                <div style="display:flex; align-items:flex-end; margin-bottom:15px;">
                                    <button type="button" class="btn btn-danger btnRemoveBudget" style="padding:6px 10px;" disabled>&times;</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- LOCATION -->
                    <div class="field-group" style="--colspan: 2;">
                        <label for="location">Location:</label>
                        <input type="text" id="location" name="location" required class="form-control">
                    </div>

                    <!-- EVENT PURPOSE -->
                    <div class="field-group" style="--colspan: 2;">
                        <label for="prg_evnt_purpose">Event Purpose:</label>
                        <textarea id="prg_evnt_purpose" name="prg_evnt_purpose" rows="2" class="form-control" placeholder="Purpose and core mission of this event..."></textarea>
                    </div>

                    <!-- KEY DELIVERABLES / GOALS -->
                    <div class="field-group" style="--colspan: 2;">
                        <label for="goal">Key Deliverables / Goals:</label>
                        <textarea id="goal" name="goal" rows="2" class="form-control" placeholder="Target objectives and deliverables..."></textarea>
                    </div>

                    <!-- MINISTRY SUPPORT & COLLABORATION -->
                    <div class="field-group" style="--colspan: 2;">
                        <label><strong>Ministry Support & Collaboration:</strong></label>
                        <div id="ministrySupportContainer" class="support-min-grid">
                            <!-- Populated via JS -->
                        </div>
                    </div>

                    <!-- REGISTRATION & FEE CHECKBOXES -->
                    <div class="field-group" style="--colspan: 1;">
                        <label>
                            <input type="checkbox" id="requires_registration" name="requires_registration" value="1"> 
                            Requires Registration
                        </label>
                    </div>

                    <div class="field-group" style="--colspan: 1;">
                        <label>
                            <input type="checkbox" id="requires_fee" name="requires_fee" value="1"> 
                            Requires Fee
                        </label>
                    </div>

                    <div class="field-group" id="fee_container" style="display: none; --colspan: 2;">
                        <label for="registration_fee">Registration Fee ($):</label>
                        <input type="number" step="0.01" id="registration_fee" name="registration_fee" class="form-control" value="0.00">
                    </div>

                    <!-- SPECIAL REQUESTS AND SPECIFIC SUPPORT NOTES -->
                    <div class="field-group" style="--colspan: 2;">
                        <label for="notes">Special Requests and Specific Support Notes:</label>
                        <textarea id="notes" name="notes" rows="3" class="form-control" placeholder="Enter special requests, technical requirements, room arrangements, or ministry support notes..."></textarea>
                    </div>

                    <!-- PLANNING PACKET DATABASE ATTACHMENT -->
                    <div class="field-group" style="--colspan: 2;">
                        <label><strong>Database Attachment (Planning Packet / Document):</strong></label>
                        <div class="doc-management-box">
                            <div class="doc-status-row">
                                <div id="doc_status_text">
                                    <span style="color:#64748b; font-style:italic;">No planning packet stored in database.</span>
                                </div>
                                <button type="button" id="btnRemoveDoc" class="btn btn-sm btn-danger" style="display:none; padding:4px 10px; font-size:0.8rem;">
                                    🗑️ Remove Document
                                </button>
                            </div>
                            
                            <label for="event_document" id="file_input_label" style="font-size:0.85rem; color:#475569;">
                                Upload Document / Packet (.pdf, .doc, .docx):
                            </label>
                            <input type="file" id="event_document" name="event_document" accept=".pdf,.doc,.docx" class="form-control" style="background:#fff;">
                        </div>
                    </div>

                    <!-- ACKNOWLEDGEMENT & AUTHORIZATION SIGN-OFFS -->
                    <div class="field-group" id="acknowledgementSection" style="--colspan: 2;">
                        <label><strong>Acknowledgement & Authorization Sign-Offs:</strong></label>
                        <div class="auth-box">
                            <!-- Primary Contact Sign-off -->
                            <label class="auth-item">
                                <input type="checkbox" id="signoff_contact" name="signoff_contact" class="chk-signoff" required>
                                <span id="signoff_contact_label"><strong>Signed by Primary Contact</strong></span>
                            </label>

                            <!-- Administrative Support Sign-off -->
                            <label class="auth-item">
                                <input type="checkbox" id="signoff_admin" name="signoff_admin" class="chk-signoff" required>
                                <span><strong>Signed by Administrative Support</strong></span>
                            </label>

                            <!-- Affected Ministries Sign-offs (Dynamically updated based on selection) -->
                            <div id="dynamicMinistrySignoffs" style="display: flex; flex-direction: column; gap: 12px;"></div>

                            <!-- Pastor Sign-off -->
                            <label class="auth-item">
                                <input type="checkbox" id="signoff_pastor" name="signoff_pastor" class="chk-signoff" required>
                                <span><strong>Signed by Pastor</strong></span>
                            </label>
                        </div>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">Save Event</button>
                        <a id="btnGoRegister" href="event_registration.php" class="btn btn-accent" style="display:none; text-decoration:none;">Register Attendees</a>
                        <button type="button" id="btnReset" class="btn btn-secondary">Reset / New</button>
                        <button type="button" id="btnDelete" class="btn btn-danger" style="display:none;">Delete</button>
                    </div>
                </fieldset>
            </form>
        <?php else: ?>
            <div class="read-only-banner">
                <strong>Read-Only Mode:</strong> You must be signed in as staff or an administrator to create or edit event details.
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Financial Projection Ledger</h2>
        <div class="form-row" style="margin-bottom:20px; text-align:center;">
            <div class="card" style="background:#fef2f2; border:1px solid #fee2e2; padding:10px; flex:1;">
                <span style="font-size:0.85rem; color:#991b1b; font-weight:bold;">Total Expenses</span>
                <h3 id="summaryExpenses" style="margin:5px 0 0 0; color:#ef4444;">$0.00</h3>
            </div>
            <div class="card" style="background:#f0fdf4; border:1px solid #dcfce7; padding:10px; flex:1;">
                <span style="font-size:0.85rem; color:#166534; font-weight:bold;">Projected Income</span>
                <h3 id="summaryIncome" style="margin:5px 0 0 0; color:#10b981;">$0.00</h3>
            </div>
            <div class="card" id="balanceCard" style="background:#f8fafc; border:1px solid #e2e8f0; padding:10px; flex:1;">
                <span style="font-size:0.85rem; color:#334155; font-weight:bold;">Net Operational Balance</span>
                <h3 id="summaryNet" style="margin:5px 0 0 0;">$0.00</h3>
            </div>
        </div>

        <hr>

        <h2>Live Attendance Check-In</h2>
        <div id="rosterPlaceholder">
            <p>Select or save a program/event on the left to manage live attendee check-ins.</p>
        </div>

        <div id="rosterContent" style="display: none;">
            <h3>Registered Attendees Check-In Grid</h3>
            <div id="attendanceGrid" class="kpi-grid"></div>
        </div>
    </div>
</div>
<?php include_once 'include/footer.php'; ?>
</body>
</html>