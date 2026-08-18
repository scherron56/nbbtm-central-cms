<?php
// user_api.php
header('Content-Type: application/json; charset=utf-8');
require_once 'config/db.php';
require_once 'include/auth.php';

// Enforce admin-only access for this entire endpoint
requireAdmin();

$action = $_REQUEST['action'] ?? '';

// System page definitions
$AVAILABLE_PAGES = [
    'contacts'           => 'Contacts & Members',
    'contact_dashboard'  => 'Contact Dashboard',
    'ministries'         => 'Ministry Management',
    'ministry_members'   => 'Ministry Rosters',
    'events'             => 'Event Management',
    'event_registration' => 'Event Registration',
    'event_checkin'      => 'Event Live Check-In',
    'vbs_sessions'       => 'VBS Sessions & Classes',
    'vbs_attendance'     => 'VBS Attendance Tracker'
];

try {
    switch ($action) {

        // --- 1. FETCH ALL USERS ---
        case 'get_users':
            $query = "SELECT user_id, username, full_name, email, role, is_active, created_at FROM users ORDER BY full_name ASC";
            $res = $db->query($query);
            $users = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            echo json_encode(['status' => 'success', 'data' => $users]);
            break;

        // --- 2. FETCH SINGLE USER & PERMISSIONS ---
        case 'get_user':
            $userId = intval($_GET['user_id'] ?? 0);
            if ($userId <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid User ID.']);
                exit;
            }

            $stmt = $db->prepare("SELECT user_id, username, full_name, email, role, is_active FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user) {
                echo json_encode(['status' => 'error', 'message' => 'User record not found.']);
                exit;
            }

            // Fetch custom page permissions
            $pStmt = $db->prepare("SELECT page_slug, can_view, can_edit FROM user_page_permissions WHERE user_id = ?");
            $pStmt->bind_param("i", $userId);
            $pStmt->execute();
            $pRes = $pStmt->get_result();
            $permissions = [];
            while ($row = $pRes->fetch_assoc()) {
                $permissions[$row['page_slug']] = [
                    'can_view' => (int)$row['can_view'],
                    'can_edit' => (int)$row['can_edit']
                ];
            }
            $pStmt->close();

            echo json_encode([
                'status'      => 'success',
                'user'        => $user,
                'permissions' => $permissions
            ]);
            break;

        // --- 3. SAVE / UPDATE USER ---
        case 'save_user':
            $userId   = !empty($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            $username = trim($_POST['username'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role     = trim($_POST['role'] ?? 'browse');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $perms    = $_POST['permissions'] ?? [];

            if (empty($username) || empty($fullName) || empty($email)) {
                echo json_encode(['status' => 'error', 'message' => 'Username, Full Name, and Email are required.']);
                exit;
            }

            $db->begin_transaction();

            if ($userId > 0) {
                // UPDATE USER
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $db->prepare("UPDATE users SET username = ?, password_hash = ?, full_name = ?, email = ?, role = ?, is_active = ? WHERE user_id = ?");
                    $stmt->bind_param("sssssii", $username, $hash, $fullName, $email, $role, $isActive, $userId);
                } else {
                    $stmt = $db->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, is_active = ? WHERE user_id = ?");
                    $stmt->bind_param("ssssii", $username, $fullName, $email, $role, $isActive, $userId);
                }
                $stmt->execute();
                $stmt->close();
            } else {
                // INSERT NEW USER
                if (empty($password)) {
                    echo json_encode(['status' => 'error', 'message' => 'Password is required when creating a new user.']);
                    exit;
                }
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO users (username, password_hash, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssi", $username, $hash, $fullName, $email, $role, $isActive);
                $stmt->execute();
                $userId = $stmt->insert_id;
                $stmt->close();
            }

            // Sync Page Permissions
            $delP = $db->prepare("DELETE FROM user_page_permissions WHERE user_id = ?");
            $delP->bind_param("i", $userId);
            $delP->execute();
            $delP->close();

            $insP = $db->prepare("INSERT INTO user_page_permissions (user_id, page_slug, can_view, can_edit) VALUES (?, ?, ?, ?)");
            foreach ($AVAILABLE_PAGES as $slug => $label) {
                $canView = 0;
                $canEdit = 0;

                if ($role === 'admin') {
                    $canView = 1;
                    $canEdit = 1;
                } elseif ($role === 'browse') {
                    $canView = 1;
                    $canEdit = 0;
                } else {
                    $permLevel = $perms[$slug] ?? 'none';
                    if ($permLevel === 'edit') {
                        $canView = 1;
                        $canEdit = 1;
                    } elseif ($permLevel === 'view') {
                        $canView = 1;
                        $canEdit = 0;
                    }
                }

                $insP->bind_param("isii", $userId, $slug, $canView, $canEdit);
                $insP->execute();
            }
            $insP->close();

            $db->commit();
            echo json_encode(['status' => 'success', 'message' => 'User profile and permissions saved successfully.']);
            break;

        // --- 4. DELETE USER ---
        case 'delete_user':
            $userId = intval($_POST['user_id'] ?? 0);
            if ($userId <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid User ID.']);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['status' => 'success', 'message' => 'User deleted successfully.']);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid API action requested.']);
            break;
    }
} catch (Exception $e) {
    if (isset($db) && $db->ping()) {
        $db->rollback();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

if (isset($db) && $db instanceof mysqli) {
    mysqli_close($db);
}