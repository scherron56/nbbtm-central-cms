<?php
// user_management.php
require_once __DIR__ . '/include/auth.php';

// Security check: Admin only
if (!isAdmin()) {
    header('Location: ./index.php');
    exit;
}

$message = '';
$statusClass = '';

// Handle Actions (Add User, Toggle Status, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create New User
    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $email    = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $role     = trim($_POST['role'] ?? 'browse');
        $password = trim($_POST['password'] ?? '');

        if ($username && $email && $password && strlen($password) >= 8) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO system_users (username, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param("ssss", $username, $email, $passwordHash, $role);
            
            try {
                $stmt->execute();
                $message = "User '{$username}' created successfully.";
                $statusClass = "text-success";
            } catch (mysqli_sql_exception $e) {
                $message = "Error creating user: Username or email may already exist.";
                $statusClass = "text-danger";
            }
        } else {
            $message = "Please provide valid inputs (Password must be at least 8 characters).";
            $statusClass = "text-danger";
        }
    }

    // Toggle Active Status
    if ($action === 'toggle_status') {
        $targetId = (int)$_POST['user_id'];
        $newStatus = (int)$_POST['new_status'];
        
        $stmt = $db->prepare("UPDATE system_users SET is_active = ? WHERE user_id = ?");
        $stmt->bind_param("ii", $newStatus, $targetId);
        $stmt->execute();
        $message = "User status updated.";
        $statusClass = "text-success";
    }
}

// Fetch all system users
$usersResult = $db->query("SELECT user_id, username, email, role, is_active FROM system_users ORDER BY user_id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - NBBTM Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .admin-wrapper {
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1.5rem;
            width: 100%;
        }
        .admin-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .admin-card h2 {
            margin-top: 0;
            color: #28089a;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0.5rem;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/header.php'; ?>

<main class="admin-wrapper">
    <div class="admin-card">
        <h2>👥 Create New System User</h2>

        <?php if ($message): ?>
            <div style="margin-bottom: 1rem;" class="<?= $statusClass ?>"><strong><?= $message ?></strong></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="create_user">
            <div class="form-grid">
                <div class="field-group">
                    <label>Username</label>
                    <input type="text" name="username" required style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                </div>
                <div class="field-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                </div>
                <div class="field-group">
                    <label>Temporary Password</label>
                    <input type="password" name="password" minlength="8" required style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                </div>
                <div class="field-group">
                    <label>Role</label>
                    <select name="role" style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                        <option value="browse">Browse</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="height: 38px;">Create User</button>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <h2>Registered System Users</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $usersResult->fetch_assoc()): ?>
                    <tr>
                        <td><?= $user['user_id'] ?></td>
                        <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                        <td><?= htmlspecialchars($user['email'] ?? '—') ?></td>
                        <td><?= ucfirst(htmlspecialchars($user['role'])) ?></td>
                        <td>
                            <span class="status-badge <?= $user['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" style="margin:0; padding:0; background:none; box-shadow:none; display:inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $user['is_active'] ? 0 : 1 ?>">
                                <button type="submit" class="link-btn" style="background:none; border:none; cursor:pointer;">
                                    <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

</body>
</html>