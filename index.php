<?php
// $db is provided by db.php; do not overwrite it here.
require_once __DIR__ . '/config/db.php';

/**
 * Fetch all phone types.
 */
function getphonetype($db)
{
    $sql = 'SELECT * FROM phone_type';
    $result = $db->query($sql);

    if ($result && $result->num_rows > 0) {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        return $rows;
    }

    return [];
}

/**
 * Fetch all titles.
 */
function gettitle($db)
{
    $sql = 'SELECT * FROM title';
    $result = $db->query($sql);

    if ($result && $result->num_rows > 0) {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        return $rows;
    }
    return [];
}

/**
 * Fetch all contacts.
 */
function getcontacts($db)
{
    $sql = 'SELECT contact_id, first_name, last_name FROM contacts';
    $result = $db->query($sql);

    if ($result && $result->num_rows > 0) {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        return $rows;
    }
    return [];
}

/* Populate data once after defining functions */
$phonetype = getphonetype($db);
$title = gettitle($db);
$contacts = getcontacts($db);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="/css/style.css">
    <title>My Title</title>
</head>

<body>
    <main class="page-wrapper">
        <header class="header el">
            <h2>Contact Information</h2>
            <label for="contactid">Contacts/Members</label>
            <select id="contact_select">
                <option value="">Select Person</option>
                <?php foreach ($contacts as $row): ?>
                    <?php $name = $row['last_name'] . ", " . $row['first_name'] ?>
                    <option value="<?= htmlspecialchars($row['contact_id'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </header>

        <div class="frame">
            <form id="member_form">
                <!-- Hidden input to pass contact_id to php -->
                <input type="hidden" id="contact_id" name="contact_id">
                <div class="box">
                    <label for="title_id">Title</label>
                    <select name="title" id="title_id">
                        <option value="">Select</option>
                        <?php foreach ($title as $row): ?>
                            <option value="<?= htmlspecialchars($row['title_id'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($row['titleabr'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="box">
                    <label for="firstname">First Name</label>
                    <input type="text" id="firstname" name="firstname" required>
                </div>

                <div class="box">
                    <label for="middlename">Middle Name</label>
                    <input type="text" id="middlename" name="middlename">
                </div>

                <div class="box">
                    <label for="lastname">Last Name</label>
                    <input type="text" id="lastname" name="lastname" required>
                </div>

                <div class="box">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address">
                </div>

                <div class="box">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city">
                </div>

                <div class="box">
                    <label for="state">State</label>
                    <input type="text" id="state" name="state">
                </div>

                <div class="box">
                    <label class="box" for="zipcode">Zip Code</label>
                    <input type="text" id="zipcode" name="zipcode">
                </div>
                <button type="submit" id="submit_btn">Add Contact/Member</button>
                <button type="button" id="reset_btn" style="display:none">Clear / New Contact Member</button>
            </form>

            <div id="response_message" role="status" aria-live="polite"></div>
        </div>
    </main>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        if (typeof jQuery === 'undefined') {
            document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
        }
    </script>
    <script>
        $(document).ready(function() {
                    $(function() {
                                // Fetch Contact/Member data
                                $('#contact_select').change(function() {
                                        var contactid = $(this).val();
                                        if (contactid) {
                                            $.ajax({
                                                    url: '/include/get_contact.php',
                                                    type: 'GET',
                                                    data: {
                                                        contact_id: contactid
                                                    },
                                                    dataType: 'json',
                                                    success: function(data) {
                                                        // Populate the form fields with the returned JSON
                                                        $('#contact_id').val(data.contact_id || '');
                                                        $('#firstname').val(data.first_name || '');
                                                        $('#middlename').val(data.middle_name || '');
                                                        $('#lastname').val(data.last_name || '');
                                                        $('#address').val(data.address_1 || '');
                                                        $('#city').val(data.city || '');
                                                        $('#state').val(data.state || '');
                                                        $('#zipcode').val(data.zipcode || '');
                                                        $('submit_btn').text('Update Contact/Member');
                                                        $('#reset_btn').show();
                                                    });
                                            }
                                            else {
                                                // Reset form if "--Add New Contact/Member --" is chosen
                                                $('#member_form')[0].reset();
                                                $('#contact_id').val('');
                                                $('submit_btn').text('Add Contact/Member');
                                                $('#reset_btn').hide();
                                            }
                                        });
                                    // Reset button functionality
                                    $('reset_btn').click(function() {
                                        $('#contact_select').val('');
                                        $('#member_form')[0].reset();
                                        $('#contact_id').val('');
                                        $('submit_btn').text('Add Contact/Member');
                                        $(this).hide();
                                    })

                                    // Handle Insert/Updates via AJAX
                                    $('#member_form').submit(function(e) {
                                        e.preventDefault(); // Prevent standard page reload
                                        $.ajax({
                                            url: '/include/save_contact.php',
                                            type: 'POST',
                                            data: $(this).serialize(),
                                            dataType: 'json',
                                            success: function(response) {
                                                $('#response_message').html(response);
                                            },
                                            error: function(xhr, status, error) {
                                                console.error('update_member error:', error);
                                            }
                                        });
                                    });
                                });
    </script>
</body>

</html>