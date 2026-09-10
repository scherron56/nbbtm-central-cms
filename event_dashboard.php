<?php
// event_dashboard.php
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
  <title>Event Executive Dashboard - NBBTM CMS</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { font-family: 'Bespoke Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
    .container { max-width: 1200px; margin: 0 auto; }
    .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .form-control { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
    .btn { padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; }
    .btn-primary { background: #2563eb; color: #ffffff; }
    .btn-secondary { background: #64748b; color: #ffffff; }
    
    .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
    .kpi-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; text-align: center; }
    .kpi-card .title { font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 600; }
    .kpi-card .value { font-size: 22px; font-weight: 700; margin-top: 5px; }
    
    .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .table th, .table td { padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 0.9rem; }
    .table th { background: #f1f5f9; font-weight: 600; }
    
    .badge-expense { background-color: #fee2e2; color: #991b1b; padding: 3px 6px; border-radius: 4px; font-size: 11px; font-weight: 600; }
    .badge-income { background-color: #dcfce7; color: #166534; padding: 3px 6px; border-radius: 4px; font-size: 11px; font-weight: 600; }
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
    .modal-card { background: #ffffff; border-radius: 8px; padding: 24px; width: 100%; max-width: 480px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>

<?php include 'include/header.php'; ?>

<div class="container" style="margin-top:20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="margin:0;">Event Executive Financials</h1>
        <?php if ($canEdit): ?>
            <button type="button" id="btnOpenActualModal" class="btn btn-primary">+ Add Actual Transaction</button>
        <?php endif; ?>
    </div>

    <!-- Event Selector Card -->
    <div class="card">
        <label for="dash_event_select" style="font-weight: 600; display: block; margin-bottom: 5px;">Select Event to View Dashboard Metrics:</label>
        <select id="dash_event_select" class="form-control">
            <option value="">-- Choose an Event --</option>
        </select>
    </div>

    <div id="dashboardContent" style="display: none;">
        
        <!-- Planned vs Actual KPI Metrics -->
        <h3>Planned vs. Actual Financial Performance</h3>
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="title">Planned Expenses</div>
                <div class="value" id="kpi_plan_expense" style="color: #dc2626;">$0.00</div>
            </div>
            <div class="kpi-card">
                <div class="title">Actual Expenses</div>
                <div class="value" id="kpi_act_expense" style="color: #991b1b;">$0.00</div>
            </div>
            <div class="kpi-card">
                <div class="title">Planned Income</div>
                <div class="value" id="kpi_plan_income" style="color: #16a34a;">$0.00</div>
            </div>
            <div class="kpi-card">
                <div class="title">Actual Income</div>
                <div class="value" id="kpi_act_income" style="color: #166534;">$0.00</div>
            </div>
            <div class="kpi-card">
                <div class="title">Actual Net Balance</div>
                <div class="value" id="kpi_act_net" style="color: #2563eb;">$0.00</div>
            </div>
        </div>

        <!-- Side-by-Side Financial Performance Tables -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(480px, 1fr)); gap: 20px;">
            
            <!-- Planned Budget Table -->
            <div class="card">
                <h3 style="margin-top:0;">Planned Budget Items</h3>
                <table class="table" id="plannedTable">
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

            <!-- Actual Transactions Table -->
            <div class="card">
                <h3 style="margin-top:0;">Actual Transactions Ledger</h3>
                <table class="table" id="actualsTable">
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

        </div>

    </div>
</div>

<!-- Modal: Add Actual Transaction -->
<div id="modalAddActual" class="modal-overlay" style="display: none;">
    <div class="modal-card">
        <h3 style="margin-top:0; margin-bottom: 15px;">Add Actual Transaction</h3>
        <form id="formAddActual">
            <input type="hidden" id="actual_prg_evnt_id" name="prg_evnt_id">
            
            <div style="margin-bottom: 12px;">
                <label style="display:block; margin-bottom: 4px; font-weight:600;">Budget Line Item (Optional):</label>
                <select id="actual_budget_item_id" name="budget_item_id" class="form-control">
                    <option value="">-- General / Unassigned --</option>
                </select>
            </div>

            <div style="margin-bottom: 12px;">
                <label style="display:block; margin-bottom: 4px; font-weight:600;">Type:</label>
                <select id="actual_entry_type" name="entry_type" class="form-control" required>
                    <option value="Expense">Expense</option>
                    <option value="Income">Income</option>
                </select>
            </div>

            <div style="margin-bottom: 12px;">
                <label style="display:block; margin-bottom: 4px; font-weight:600;">Description:</label>
                <input type="text" id="actual_description" name="description" placeholder="e.g. Catering Invoice #102" required class="form-control">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; margin-bottom: 4px; font-weight:600;">Amount ($):</label>
                <input type="number" step="0.01" min="0.01" id="actual_amount" name="amount" placeholder="0.00" required class="form-control">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" id="btnCloseActualModal" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Transaction</button>
            </div>
        </form>
    </div>
</div>

<script>
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

$(document).ready(function() {

    // Load Events Dropdown
    function loadEventDropdown() {
        $.ajax({
            url: 'prg_event_api.php',
            type: 'GET',
            data: { action: 'get_events' },
            dataType: 'json',
            success: function(data) {
                if(data.success) {
                    let $select = $('#dash_event_select');
                    $select.find('option:not(:first)').remove();
                    $.each(data.programs_events, function(i, ev) {
                        let dispDate = ev.primary_start ? ` (${ev.primary_start.substring(0,10)})` : '';
                        $select.append(`<option value="${ev.prg_evnt_id}">${escapeHtml(ev.prg_evnt_name)}${dispDate}</option>`);
                    });
                }
            }
        });
    }

    $('#dash_event_select').on('change', function() {
        let eventId = $(this).val();
        if (!eventId) {
            $('#dashboardContent').hide();
            return;
        }

        $.get('prg_event_api.php', { action: 'get_event', prg_evnt_id: eventId }, function(res) {
            if (res.success) {
                $('#dashboardContent').show();

                // Calculate Planned Totals
                let planExp = 0, planInc = 0;
                let $planBody = $('#plannedTable tbody').empty();
                if (res.budgets && res.budgets.length > 0) {
                    $.each(res.budgets, function(i, b) {
                        let amt = parseFloat(b.amount) || 0;
                        if (b.item_type === 'Expense') planExp += amt;
                        else planInc += amt;

                        $planBody.append(`
                            <tr>
                                <td>${escapeHtml(b.item_description)}</td>
                                <td><span class="${b.item_type === 'Expense' ? 'badge-expense' : 'badge-income'}">${escapeHtml(b.item_type)}</span></td>
                                <td>$${amt.toFixed(2)}</td>
                            </tr>
                        `);
                    });
                } else {
                    $planBody.append('<tr><td colspan="3" style="text-align:center; color:#64748b; padding:10px;">No budget items planned.</td></tr>');
                }

                // Calculate Actual Totals
                let actExp = 0, actInc = 0;
                let $actBody = $('#actualsTable tbody').empty();
                if (res.actuals && res.actuals.length > 0) {
                    $.each(res.actuals, function(i, a) {
                        let amt = parseFloat(a.amount) || 0;
                        if (a.entry_type === 'Expense') actExp += amt;
                        else actInc += amt;

                        let linkedItem = a.budget_item_name ? `<br><small style="color:#64748b;">Line: ${escapeHtml(a.budget_item_name)}</small>` : '';

                        $actBody.append(`
                            <tr>
                                <td>${escapeHtml(a.description)}${linkedItem}</td>
                                <td><span class="${a.entry_type === 'Expense' ? 'badge-expense' : 'badge-income'}">${escapeHtml(a.entry_type)}</span></td>
                                <td>$${amt.toFixed(2)}</td>
                            </tr>
                        `);
                    });
                } else {
                    $actBody.append('<tr><td colspan="3" style="text-align:center; color:#64748b; padding:10px;">No actual transactions recorded.</td></tr>');
                }

                // Update Dashboard KPI Values
                $('#kpi_plan_expense').text('$' + planExp.toFixed(2));
                $('#kpi_plan_income').text('$' + planInc.toFixed(2));
                $('#kpi_act_expense').text('$' + actExp.toFixed(2));
                $('#kpi_act_income').text('$' + actInc.toFixed(2));

                let net = actInc - actExp;
                $('#kpi_act_net').text((net >= 0 ? '+' : '') + '$' + net.toFixed(2))
                                 .css('color', net >= 0 ? '#16a34a' : '#dc2626');

            } else {
                alert(res.error || 'Failed to fetch dashboard metrics.');
            }
        }, 'json');
    });

    // Modal Events
    $('#btnOpenActualModal').on('click', function() {
        let eventId = $('#dash_event_select').val();
        if (!eventId) {
            alert('Please select an event first.');
            return;
        }

        $('#actual_prg_evnt_id').val(eventId);

        $.get('prg_event_api.php', { action: 'get_event_budget_items', prg_evnt_id: eventId }, function(res) {
            if (res.success) {
                let $sel = $('#actual_budget_item_id').empty().append('<option value="">-- General / Unassigned --</option>');
                $.each(res.budget_items, function(i, item) {
                    $sel.append(`<option value="${item.budget_item_id}">${escapeHtml(item.item_description)} (${item.item_type})</option>`);
                });
                $('#modalAddActual').fadeIn(200);
            }
        }, 'json');
    });

    $('#btnCloseActualModal').on('click', function() {
        $('#modalAddActual').fadeOut(200);
    });

    $('#formAddActual').on('submit', function(e) {
        e.preventDefault();
        $.post('prg_event_api.php?action=save_actual', $(this).serialize(), function(res) {
            if (res.success) {
                $('#modalAddActual').fadeOut(200);
                $('#formAddActual')[0].reset();
                $('#dash_event_select').trigger('change');
            } else {
                alert(res.error || 'Failed to save transaction.');
            }
        }, 'json');
    });

    loadEventDropdown();
});
</script>

<?php include_once 'include/footer.php'; ?>
</body>
</html>