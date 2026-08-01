<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Beginnings Baptist Tabernacle Ministries</title>
    <link href="https://fontshare.com[]=bespoke-serif@301,400,500,501,700,701&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://jquery.com" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <style>
        /* Contextual styles for managing data grid outputs */
        .table-container {
            width: 100%;
            max-width: 1000px;
            margin: 2rem auto;
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            color: #28089a;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #cbd5e1;
        }

        .data-table th {
            background-color: #043b8f;
            color: #fff;
        }

        .action-flex {
            display: flex;
            gap: 8px;
            margin-top: 1rem;
        }

        .row-actions {
            display: flex;
            gap: 5px;
        }
    </style>
</head>

<body>
    <?php require_once("config/db.php"); ?>
    <?php include 'header.php'; ?>

    <h1>Vacation Bible School Session</h1>

    <form id="vbs-form" name="vbs-form">
        <!-- Session Selector Row Area Component -->
        <fieldset class="form-grid-section-short">
            <div class="field-group" style="--colspan: 1;">
                <button type="button" id="addSession" class="btn-pulse nbtn">Add Session</button>
            </div>
            <div class="field-group" style="--colspan: 2;">
                <label for="SessionID">
                    <h3 style="color: blue; margin: 0;">Select Session Year</h3>
                </label>
                <select name="sessionID" id="SessionID">
                    <option value="">--Select--</option>
                    <?php
                    $sessionQuery = "SELECT vbs_sessions_id, vbs_year, vbs_theme FROM vbs_sessions ORDER BY vbs_year DESC";
                    $sessionResult = $conn->query($sessionQuery);
                    if ($sessionResult) {
                        while ($row = $sessionResult->fetch_assoc()) {
                            $displayTxt = htmlspecialchars($row['vbs_year']) . " - " . htmlspecialchars($row['vbs_theme']);
                            echo "<option value='" . intval($row['vbs_sessions_id']) . "'>" . $displayTxt . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
        </fieldset>

        <!-- Dynamic Data Grid Element to view active configurations -->
        <div id="class-table-container" class="table-container hidden">
            <h3>Active Classes for Selected Session</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Ages</th>
                        <th>Teacher</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="class-table-body">
                    <tr>
                        <td colspan="4">Please choose a session year to examine records.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Class Modification and Composition Area Block -->
        <fieldset class="form-grid-section-9">
            <legend>
                <h2 id="class-form-title">Add VBS Class</h2>
            </legend>

            <!-- Hidden State Keys Architecture Contexts -->
            <input type="hidden" id="vbs_class_session_id" name="vbs_class_session_id">
            <input type="hidden" id="vbs_class_id" name="vbs_class_id">

            <div class="field-group" style="--colspan: 3">
                <label for="vbs_class_desc">Class Description</label>
                <input type="text" id="vbs_class_desc" name="vbs_class_desc">
            </div>

            <div class="field-group" style="--colspan: 2">
                <label for="vbs_class_age_start">Class Starting Age</label>
                <input type="number" id="vbs_class_age_start" name="vbs_class_age_start">
            </div>

            <div class="field-group" style="--colspan: 2">
                <label for="vbs_class_age_end">Class Ending Age</label>
                <input type="number" id="vbs_class_age_end" name="vbs_class_age_end">
            </div>

            <div class="field-group" style="--colspan: 2">
                <label for="vbs_class_teacher_id">Class Teacher</label>
                <select name="vbs_class_teacher_id" id="vbs_class_teacher_id">
                    <option value="">--Select--</option>
                    <?php
                    // Mapped directly to contacts layout using title_id context check tracking
                    $teacherQuery = "SELECT contact_id, first_name, last_name FROM contacts ORDER BY last_name ASC, first_name ASC";
                    $teacherResult = $conn->query($teacherQuery);
                    if ($teacherResult) {
                        while ($row = $teacherResult->fetch_assoc()) {
                            $fullName = htmlspecialchars($row['last_name'] . ", " . $row['first_name']);
                            echo "<option value='" . intval($row['contact_id']) . "'>" . $fullName . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
        </fieldset>

        <div class="action-flex">
            <button type="submit" class="btn-primary nbtn" id="submit-class-btn">Save Class</button>
            <button type="button" class="btn-secondary nbtn hidden" id="cancel-edit-btn">Cancel Edit</button>
        </div>
    </form>

    <script>
        $(document).ready(function() {
            // Track session selection mutations
            $('#SessionID').on('change', function() {
                const sessionId = $(this).val();
                $('#vbs_class_session_id').val(sessionId);
                resetClassFormFields();

                if (sessionId) {
                    $('#class-table-container').removeClass('hidden');
                    fetchClasses(sessionId);
                } else {
                    $('#class-table-container').addClass('hidden');
                    $('#class-table-body').html('<tr><td colspan="4">Please choose a session year to examine records.</td></tr>');
                }
            });

            // Form interception submission channel routing
            $('#vbs-form').on('submit', function(e) {
                e.preventDefault();

                const sessionId = $('#vbs_class_session_id').val();
                if (!sessionId) {
                    alert('Please select a valid Session Year before saving class records.');
                    return;
                }

                const isUpdate = $('#vbs_class_id').val() !== '';
                const actionUrl = isUpdate ? 'class_update.php' : 'class_create.php';

                $.ajax({
                    url: actionUrl,
                    type: 'POST',
                    data: $('#vbs-form').serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            resetClassFormFields();
                            fetchClasses(sessionId);
                        } else {
                            alert('Operation failed: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while connecting to the system execution endpoint vectors.');
                    }
                });
            });

            $('#cancel-edit-btn').on('click', function() {
                resetClassFormFields();
            });
        });

        // Dynamic processing engine fetching matching rows 
        function fetchClasses(sessionId) {
            $.ajax({
                url: 'class_read.php',
                type: 'GET',
                data: {
                    vbs_sessions_id: sessionId
                },
                dataType: 'json',
                success: function(classes) {
                    const tbody = $('#class-table-body');
                    tbody.empty();

                    if (classes.length === 0) {
                        tbody.html('<tr><td colspan="4">No classes setup found matching this session timeline parameters.</td></tr>');
                        return;
                    }

                    classes.forEach(function(cls) {
                        const escapedCls = JSON.stringify(cls).replace(/'/g, "&apos;");
                        const row = `
                        <tr>
                            <td><b>${cls.vbs_class_desc}</b></td>
                            <td>Ages ${cls.vbs_class_age_start} - ${cls.vbs_class_age_end}</td>
                            <td>${cls.teacher_name || 'Unassigned'}</td>
                            <td class="row-actions">
                                <button type="button" class="btn-pulse" onclick="populateEditClass('${escapedCls}')">Edit</button>
                                <button type="button" class="btn-danger" onclick="deleteClass(${cls.vbs_class_id})">Delete</button>
                            </td>
                        </tr>
                    `;
                        tbody.append(row);
                    });
                }
            });
        }

        // Populate dynamic data array variables back to form inputs
        function populateEditClass(classJsonStr) {
            const cls = JSON.parse(classJsonStr);

            $('#vbs_class_id').val(cls.vbs_class_id);
            $('#vbs_class_desc').val(cls.vbs_class_desc);
            $('#vbs_class_age_start').val(cls.vbs_class_age_start);
            $('#vbs_class_age_end').val(cls.vbs_class_age_end);
            $('#vbs_class_teacher_id').val(cls.vbs_class_teacher_id || '');

            $('#class-form-title').text('Modify VBS Class Details');
            $('#submit-class-btn').text('Update Class');
            $('#cancel-edit-btn').removeClass('hidden');
        } 
        // Row deletion execution script route mapping
        function deleteClass(classId) {
            if (!confirm('Are you sure you want to permanently delete this class entry?')) return;
            const sessionId = $('#vbs_class_session_id').val();
            $.ajax({
                url: 'class_delete.php',
                type: 'POST',
                data: {
                    vbs_class_id: classId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        fetchClasses(sessionId);
                    } else {
                        alert('Delete failed: ' + response.message);
                    }
                }
            });
        }

        function resetClassFormFields() {
            $('#vbs_class_id').val('');
            $('#vbs_class_desc').val('');
            $('#vbs_class_age_start').val('');
            $('#vbs_class_age_end').val('');
            $('#vbs_class_teacher_id').val('');
            $('#class-form-title').text('Add VBS Class');
            $('#submit-class-btn').text('Save Class');
            $('#cancel-edit-btn').addClass('hidden');
        }