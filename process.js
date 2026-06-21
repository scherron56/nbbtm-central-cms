$(document).ready(function () {
   // Fire off a single request to get data for both dropdown components
    $.ajax({
        url: 'tblSelects.php', 
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            // Error safety handling Check
            if(response.error) {
                console.error("Server Error: ", response.error);
                return;
            }

            // 1. Process and append phonetype table to the 3 select options
            var phtype1Select = $('#phone1type');
            $.each(response.phonetypes, function(index, item) {
               phtypeSelect.append(
                    $('<option></option>').val(item.phone_type_id).text(item.phone_type_desc)
                );
            });
            var phtype2Select = $('#phone2type');
            $.each(response.phonetypes, function(index, item) {
               phtypeSelect.append(
                    $('<option></option>').val(item.phone_type_id).text(item.phone_type_desc)
                );
            });
           var phtype3Select = $('#phone3type');
            $.each(response.phonetypes, function(index, item) {
               phtypeSelect.append(
                    $('<option></option>').val(item.phone_type_id).text(item.phone_type_desc)
                );
            });

            // 2. Process and append title records
            var titleSelect = $('#titles');
            $.each(response.titles, function(index, item) {
                titleSelect.append(
                    $('<option></option>').val(item.title_id).text(item.titleabr)
                );
            });
            var contactSelect = $('#contacts');
            $.each(response.contacts, function(index, item) {
                contactSelect.append(
                    $('<option></option>').val(item.contact_id).text(item.fullname)
                );
            });
        },
        error: function(xhr, status, error) {
            console.error("AJAX Connection Failed: " + error);
        }
    });

        // Fetch Contact/Member data
        $('#conSelect').change(function () {
            var contactid = $(this).val();
            if (contactid) {
                $.ajax({
                    url: '/include/getContact.php',
                    type: 'GET',
                    data: {
                        contact_id: contactid
                    },
                    dataType: 'json',
                    success: function (data) {
                        // Populate the form fields with the returned JSON
                        $('#contact_id').val(data.contact_id || '');
                        $('#title').val(data.title_id || '');
                        $('#firstname').val(data.first_name || '');
                        $('#middlename').val(data.middle_name || '');
                        $('#lastname').val(data.last_name || '');
                        $('#address1').val(data.address_1 || '');
                        $('#city').val(data.city || '');
                        $('#state').val(data.state || '');
                        $('#zipcode').val(data.zipcode || '');
                        $('#dob').val(data.date_of_birth || ''); 
                        $('#gender').val(data.gender || '');
                        $('#marital').val(data.marital_status || '');
                        $('#anniv').val(data.anniv_date || ''); 
                        $('#phone1').val(data.phone_1 || '');
                        $('#phone2').val(data.phone_2 || '');
                        $('#phone3').val(data.phone_3 || ''); 
                        $('#phone1type').val(data.phone_1_type || '');
                        $('#phone2type').val(data.phone_2_type || '');
                        $('#phone3type').val(data.phone_3_type || ''); 
                        $('#email').val(data.c_email || '');
                        $('#ismember').val(data.is_member || '');
                        $('#dateJoined').val(data.join_date || ''); 
                        $('#isbaptized').val(data.is_baptized || ''); 
                        $('#baptizedDate').val(data.baptized_date || '');
                        $('#isactive').val(data.is_active || ''); 
                        $('submit_btn').text('Update Contact/Member');
                        $('#reset_btn').show();
                    }});
            }
            else {
                // Reset form if "--Add New Contact/Member --" is chosen
                $('#pers-form')[0].reset();
                $('#cont-fom')[0].reset();
                $('#memb-form')[0].reset();
                $('#contact_id').val('');
                $('submit_btn').text('Add Contact/Member');
                $('reset_btn').hide();
            }
        });
        // Reset button functionality
        $('reset_btn').click(function () {
                $('#pers-form')[0].reset();
                $('#cont-fom')[0].reset();
                $('#memb-form')[0].reset();
                $('#contact_id').val('');
                $('submit_btn').text('Add Contact/Member');
                $(this).hide();
        })

        // Handle Insert/Updates via AJAX
        $('#membr-action').submit(function (e) {
            e.preventDefault(); // Prevent standard page reload
            $.ajax({
                url: '/include/saveContact.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    $('#response_message').html(response);
                },
                error: function (xhr, status, error) {
                    console.error('update_member error:', error);
                }
        });
    });
})