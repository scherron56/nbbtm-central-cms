<?php
// doc_categories_admin.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/include/auth.php';[cite: 5]
requireAdmin(); // Restrict entire page to admin users[cite: 6]
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document Category Settings - Admin</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .admin-wrapper { max-width: 1000px; margin: 2rem auto; }
    .alert-box { padding: 12px 16px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; font-size: 0.95rem; display: none; }
    .alert-success { background-color: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
    .alert-error { background-color: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
    .badge-active { background: #dcfce7; color: #15803d; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 0.8rem; }
    .badge-inactive { background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 0.8rem; }
    .filter-row { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; }
  </style>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    $(document).ready(function() {
        loadCategories();

        function showStatus(msg, type = 'success') {
            $('#status-msg').removeClass('alert-success alert-error')
                .addClass(type === 'success' ? 'alert-success' : 'alert-error')
                .html(msg).stop(true, true).fadeIn(200);
            if (type === 'success') setTimeout(() => $('#status-msg').fadeOut(400), 4000);
        }

        function loadCategories(filter = '') {
            $.ajax({
                url: 'category_api.php',
                type: 'GET',
                data: { action: 'get_categories', entity_type: filter },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        let $tbody = $('#categoryTableBody').empty();
                        if (res.categories.length === 0) {
                            $tbody.append('<tr><td colspan="5" style="text-align:center; color:#64748b;">No categories configured.</td></tr>');
                            return;
                        }
                        $.each(res.categories, function(i, c) {
                            let statusBadge = parseInt(c.is_active) === 1
                                ? '<span class="badge-active">Active</span>'
                                : '<span class="badge-inactive">Inactive</span>';

                            $tbody.append(`
                                <tr>
                                    <td><strong>${c.category_name}</strong></td>
                                    <td><span style="text-transform: capitalize;">${c.entity_type}</span></td>
                                    <td>${c.description || '-'}</td>
                                    <td>${statusBadge}</td>
                                    <td style="text-align:right;">
                                        <button type="button" class="btn btn-sm btn-secondary btnEdit" data-id="${c.doc_category_id}">Edit</button>
                                        <button type="button" class="btn btn-sm btn-danger btnDelete" data-id="${c.doc_category_id}">Delete</button>
                                    </td>
                                </tr>
                            `);
                        });
                    }
                }
            });
        }

        $('#filter_entity').on('change', function() {
            loadCategories($(this).val());
        });

        $('#categoryForm').on('submit', function(e) {
            e.preventDefault();
            let formData = $(this).serialize() + '&action=save_category';

            $.ajax({
                url: 'category_api.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        showStatus(res.message, 'success');
                        resetForm();
                        loadCategories($('#filter_entity').val());
                    } else {
                        showStatus(res.error || 'Failed to save category.', 'error');
                    }
                }
            });
        });

        $(document).on('click', '.btnEdit', function() {
            let id = $(this).data('id');
            $.ajax({
                url: 'category_api.php',
                type: 'GET',
                data: { action: 'get_category', doc_category_id: id },
                dataType: 'json',
                success: function(res) {
                    if (res.success && res.category) {
                        let c = res.category;
                        $('#doc_category_id').val(c.doc_category_id);
                        $('#entity_type').val(c.entity_type);
                        $('#category_name').val(c.category_name);
                        $('#description').val(c.description || '');
                        $('#is_active').prop('checked', parseInt(c.is_active) === 1);
                        $('#formTitle').text('Edit Document Category');
                        $('#btnSave').text('Update Category');
                        $('html, body').animate({ scrollTop: $('#categoryForm').offset().top - 20 }, 200);
                    }
                }
            });
        });

        $(document).on('click', '.btnDelete', function() {
            if (!confirm('Are you sure you want to delete this category?')) return;
            let id = $(this).data('id');
            $.ajax({
                url: 'category_api.php',
                type: 'POST',
                data: { action: 'delete_category', doc_category_id: id },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        showStatus(res.message, 'success');
                        loadCategories($('#filter_entity').val());
                    } else {
                        showStatus(res.error, 'error');
                    }
                }
            });
        });

        function resetForm() {
            $('#categoryForm')[0].reset();
            $('#doc_category_id').val('');
            $('#is_active').prop('checked', true);
            $('#formTitle').text('Add Document Category');
            $('#btnSave').text('Save Category');
        }

        $('#btnReset').on('click', resetForm);
    });
  </script>
</head>
<body>
  <?php include 'include/header.php'; ?>[cite: 5]

  <div class="admin-wrapper">
    <h2>Document Category Settings</h2>
    <div id="status-msg" class="alert-box"></div>

    <!-- Entry Form -->
    <div class="card" style="margin-bottom: 20px;">
        <h3 id="formTitle">Add Document Category</h3>
        <form id="categoryForm">
            <input type="hidden" id="doc_category_id" name="doc_category_id" value="">
            
            <div class="form-row" style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div class="form-group" style="flex: 1;">
                    <label for="entity_type">Entity Scope:</label>
                    <select id="entity_type" name="entity_type" class="form-control" required>
                        <option value="event">Event (Programs/Events)</option>
                        <option value="ministry">Ministry (Committees)</option>
                        <option value="contact">Contact (Members/Staff)</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 2;">
                    <label for="category_name">Category Name:</label>
                    <input type="text" id="category_name" name="category_name" placeholder="e.g. Planning Packet, Liability Form" class="form-control" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 10px;">
                <label for="description">Description (Optional):</label>
                <input type="text" id="description" name="description" placeholder="Brief note about when to use this category" class="form-control">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="cursor: pointer;">
                    <input type="checkbox" id="is_active" name="is_active" value="1" checked> Active
                </label>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" id="btnSave" class="btn btn-primary">Save Category</button>
                <button type="button" id="btnReset" class="btn btn-secondary">Reset</button>
            </div>
        </form>
    </div>

    <!-- Table of Configured Categories -->
    <div class="card">
        <div class="filter-row">
            <h3 style="margin: 0; flex: 1;">Configured Categories</h3>
            <div>
                <label for="filter_entity">Filter by Entity: </label>
                <select id="filter_entity" class="form-control" style="display: inline-block; width: auto;">
                    <option value="">-- All Entities --</option>
                    <option value="event">Events</option>
                    <option value="ministry">Ministries</option>
                    <option value="contact">Contacts</option>
                </select>
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Entity Scope</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="categoryTableBody"></tbody>
        </table>
    </div>
  </div>

  <?php include_once 'include/footer.php'; ?>[cite: 5]
</body>
</html>