<?php
require_once __DIR__ . '/include/auth.php';

// Security check: Admin access only
if (!isAdmin()) {
    header('Location: ./index.php');
    exit;
}

$message = '';
$statusClass = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- CREATE USER ---
    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $role     = trim($_POST['role'] ?? 'view');
        $password = trim($_POST['password'] ?? '');

        if ($username && $fullName && $email && $password && strlen($password) >= 8) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO nbbtm_users (username, full_name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sssss", $username, $fullName, $email, $passwordHash, $role);
            
            try {
                $stmt->execute();
                $message = "User '{$username}' created successfully.";
                $statusClass = "text-success";
            } catch (mysqli_sql_exception $e) {
                $message = "Error creating user: Username or email may already exist.";
                $statusClass = "text-danger";
            }
        } else {
            $message = "Invalid input. Please complete all fields and ensure the password has at least 8 characters.";
            $statusClass = "text-danger";
        }
    }

    // --- UPDATE USER ---
    if ($action === 'update_user') {
        $userId   = (int)($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $role     = trim($_POST['role'] ?? 'view');
        $password = trim($_POST['password'] ?? '');

        if ($userId > 0 && $username && $fullName && $email) {
            try {
                if (!empty($password)) {
                    if (strlen($password) >= 8) {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE nbbtm_users SET username = ?, full_name = ?, email = ?, role = ?, password_hash = ? WHERE user_id = ?");
                        $stmt->bind_param("sssssi", $username, $fullName, $email, $role, $passwordHash, $userId);
                    } else {
                        $message = "Password update requires at least 8 characters.";
                        $statusClass = "text-danger";
                    }
                } else {
                    $stmt = $db->prepare("UPDATE nbbtm_users SET username = ?, full_name = ?, email = ?, role = ? WHERE user_id = ?");
                    $stmt->bind_param("ssssi", $username, $fullName, $email, $role, $userId);
                }

                if (empty($message)) {
                    $stmt->execute();
                    $message = "User '{$username}' updated successfully.";
                    $statusClass = "text-success";
                }
            } catch (mysqli_sql_exception $e) {
                $message = "Error updating user: Username or email already in use.";
                $statusClass = "text-danger";
            }
        } else {
            $message = "Please fill in all required fields.";
            $statusClass = "text-danger";
        }
    }

    // --- DELETE USER ---
    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId === (int)$_SESSION['user_id']) {
            $message = "Security restriction: You cannot delete your own account.";
            $statusClass = "text-danger";
        } elseif ($userId > 0) {
            $stmt = $db->prepare("DELETE FROM nbbtm_users WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $message = "User record deleted successfully.";
            $statusClass = "text-success";
        }
    }

    // --- TOGGLE STATUS ---
    if ($action === 'toggle_status') {
        $userId    = (int)($_POST['user_id'] ?? 0);
        $newStatus = (int)($_POST['new_status'] ?? 0);
        
        $stmt = $db->prepare("UPDATE nbbtm_users SET is_active = ? WHERE user_id = ?");
        $stmt->bind_param("ii", $newStatus, $userId);
        $stmt->execute();
        $message = "User account status updated.";
        $statusClass = "text-success";
    }
}

// Fetch user directory
$usersResult = $db->query("SELECT user_id, username, full_name, email, role, is_active FROM nbbtm_users ORDER BY user_id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Management - NBBTM CMS</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .status-badge { padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; display: inline-block; }
    .status-active { background: #dcfce7; color: #166534; }
    .status-inactive { background: #fee2e2; color: #991b1b; }
    .table-actions { display: flex; align-items: center; justify-content: flex-end; gap: 0.4rem; flex-wrap: wrap; }
    .table-actions form { padding: 0; margin: 0; background: none; box-shadow: none; display: inline; width: auto; }
    .btn-sm { padding: 6px 12px; font-size: 0.8rem; border-radius: 4px; }
  </style>
</head>
<body>

<?php include_once __DIR__ . '/include/header.php'; ?>

<main class="dashboard-container">

  <?php if ($message): ?>
    <div class="card" style="margin-bottom: 1.5rem; padding: 1rem;">
      <span class="<?= $statusClass ?>"><strong><?= htmlspecialchars($message) ?></strong></span>
    </div>
  <?php endif; ?>

  <!-- Add / Edit User Form Card -->
  <div class="card" style="margin-bottom: 2rem;">
    <h3 id="form-heading">Create New System User</h3>
    
    <form method="POST" id="user-form" style="max-width: 100%; box-shadow: none; padding: 0;">
      <input type="hidden" name="action" id="form-action" value="create_user">
      <input type="hidden" name="user_id" id="form-user-id" value="">

      <div class="form-grid-section-12" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <div class="field-group">
          <label for="username"><strong>Username</strong></label>
          <input type="text" id="username" name="username" required style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; width: 100%;">
        </div>

        <div class="field-group">
          <label for="full_name"><strong>Full Name</strong></label>
          <input type="text" id="full_name" name="full_name" required style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; width: 100%;">
        </div>

        <div class="field-group">
          <label for="email"><strong>Email Address</strong></label>
          <input type="email" id="email" name="email" required style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; width: 100%;">
        </div>

        <div class="field-group">
          <label for="password"><strong id="password-label">Password</strong></label>
          <input type="password" id="password" name="password" minlength="8" required placeholder="Min. 8 characters" style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; width: 100%;">
        </div>

        <div class="field-group">
          <label for="role"><strong>Role</strong></label>
          <select id="role" name="role" style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; height: 38px; width: 100%;">
            <option value="view">View</option>
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>

      <div class="action-buttons" style="margin-top: 1.25rem;">
        <button type="submit" id="submit-btn" class="btn btn-primary">+ Create User</button>
        <button type="button" id="cancel-edit-btn" class="btn btn-secondary hidden" onclick="resetUserForm()">Cancel Edit</button>
      </div>
    </form>
  </div>

  <!-- User Table Card -->
  <div class="card">
    <h3>Registered System Users</h3>
    <div style="overflow-x: auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($usersResult && $usersResult->num_rows > 0): ?>
            <?php while ($user = $usersResult->fetch_assoc()): ?>
              <tr>
                <td><?= $user['user_id'] ?></td>
                <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                <td><?= htmlspecialchars($user['full_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($user['email'] ?? '—') ?></td>
                <td><?= ucfirst(htmlspecialchars($user['role'])) ?></td>
                <td>
                  <span class="status-badge <?= $user['is_active'] ? 'status-active' : 'status-inactive' ?>">
                    <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td style="text-align: right;">
                  <div class="table-actions">
                    <button type="button" class="btn btn-accent btn-sm" onclick='editUser(<?= json_encode($user) ?>)'>
                      Edit
                    </button>

                    <form method="POST">
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                      <input type="hidden" name="new_status" value="<?= $user['is_active'] ? 0 : 1 ?>">
                      <button type="submit" class="btn btn-secondary btn-sm">
                        <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>
                      </button>
                    </form>

                    <?php if ((int)$user['user_id'] !== (int)$_SESSION['user_id']): ?>
                      <form method="POST" onsubmit="return confirm('Are you sure you want to delete user \'<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>\'?');">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7">No system users found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<?php include_once __DIR__ . '/include/footer.php'; ?>

<script>
function editUser(user) {
  document.getElementById('form-heading').textContent = 'Edit System User: ' + user.username;
  document.getElementById('form-action').value = 'update_user';
  document.getElementById('form-user-id').value = user.user_id;
  document.getElementById('username').value = user.username;
  document.getElementById('full_name').value = user.full_name || '';
  document.getElementById('email').value = user.email || '';
  document.getElementById('role').value = user.role;
  
  const pwdInput = document.getElementById('password');
  pwdInput.removeAttribute('required');
  pwdInput.placeholder = 'Leave blank to keep unchanged';
  document.getElementById('password-label').textContent = 'Password (optional)';

  document.getElementById('submit-btn').textContent = 'Update User';
  document.getElementById('cancel-edit-btn').classList.remove('hidden');

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetUserForm() {
  document.getElementById('form-heading').textContent = 'Create New System User';
  document.getElementById('form-action').value = 'create_user';
  document.getElementById('form-user-id').value = '';
  document.getElementById('user-form').reset();

  const pwdInput = document.getElementById('password');
  pwdInput.setAttribute('required', 'required');
  pwdInput.placeholder = 'Min. 8 characters';
  document.getElementById('password-label').textContent = 'Password';

  document.getElementById('submit-btn').textContent = '+ Create User';
  document.getElementById('cancel-edit-btn').classList.add('hidden');
}
</script>
</body>
</html>