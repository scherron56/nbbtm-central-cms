 <?php
// include/auth.php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Ensure non-authenticated users default to the 'browser' role
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_role'] = 'browser';
}

$dbPathConfig = __DIR__ . '/../config/db.php';
$dbPathRoot   = dirname(__DIR__) . '/config/db.php';

if (file_exists($dbPathConfig)) {
    require_once $dbPathConfig;
} elseif (file_exists($dbPathRoot)) {
    require_once $dbPathRoot;
} elseif (file_exists('config/db.php')) {
    require_once 'config/db.php';
} elseif (file_exists('../config/db.php')) {
    require_once '../config/db.php';
} else {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Database configuration file missing.']);
    exit;
}

/**
 * Check if user is Admin
 */
function isAdmin() {
    return isset($_SESSION['user_id'], $_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'admin';
}

/**
 * Check if user is Developer
 */
function isDeveloper() {
    return isset($_SESSION['user_id'], $_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'developer';
}

/**
 * Check if user is Staff
 */
function isStaff() {
    return isset($_SESSION['user_id'], $_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'staff';
}

/**
 * Check if user has edit permissions (Admin, Developer, or Staff)
 */
function canEdit() {
    if (!isset($_SESSION['user_role'])) return false;
    $role = strtolower($_SESSION['user_role']);
    return in_array($role, ['admin', 'developer', 'staff'], true);
}

/**
 * Security middleware: Admin only
 */
function requireAdmin() {
    if (!isAdmin()) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'status'  => 'error', 
            'success' => false, 
            'message' => 'Access Denied: Admin privileges required.'
        ]);
        exit;
    }
}

/**
 * Security middleware: Developer ONLY (Locks out Admin and everyone else)
 */
function requireDeveloperOnly() {
    if (!isDeveloper()) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'status'  => 'error', 
            'success' => false, 
            'message' => 'Access Denied: Developer-only privileges required.'
        ]);
        exit;
    }
}

/**
 * Security middleware: Editors only (Admin, Developer & Staff)
 */
function requireEditor() {
    if (!canEdit()) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'status'  => 'error', 
            'success' => false, 
            'message' => 'Access Denied: Edit privileges required.'
        ]);
        exit;
    }
}

/**
 * Flexible security middleware
 */
function requireRole(array $allowedRoles = ['admin', 'developer', 'staff', 'browser']) {
    $currentRole = strtolower($_SESSION['user_role'] ?? 'browser');
    $normalizedAllowed = array_map('strtolower', $allowedRoles);

    if (!in_array($currentRole, $normalizedAllowed, true)) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'status'  => 'error', 
            'success' => false, 
            'message' => 'Access Denied: Insufficient permissions.'
        ]);
        exit;
    }
}

/**
 * Security middleware: Restrict page to specific User IDs (Admins and Developers always bypass)
 */
function requireSpecificUser(array $allowedUserIds) {
    if (isAdmin() || isDeveloper()) {
        return;
    }

    $currentUserId = $_SESSION['user_id'] ?? 0;

    if (!in_array($currentUserId, $allowedUserIds, true)) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'status'  => 'error', 
            'success' => false, 
            'message' => 'Access Denied: This page is restricted to specific users.'
        ]);
        exit;
    }
}

$action = $_REQUEST['action'] ?? '';

// --- ACTION: LOGIN ---
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $identity = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($identity) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    $query = "SELECT nu.user_id, nu.username, nu.full_name, nu.email, nu.password_hash, nu.nbbtm_role_id, nr.nbbtm_role_name, nu.is_active 
              FROM nbbtm_users nu
              LEFT JOIN nbbtm_roles nr ON nu.nbbtm_role_id = nr.nbbtm_role_id
              WHERE nu.username = ? OR nu.email = ?";

    $stmt = $db->prepare($query);
    $stmt->bind_param("ss", $identity, $identity);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if ((int)$user['is_active'] !== 1) {
            echo json_encode(['success' => false, 'message' => 'Account is inactive. Please contact system admin.']);
            exit;
        }

        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']    = (int)$user['user_id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['full_name']  = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            // Using the joined role name (e.g., 'admin', 'developer', 'staff')
            $_SESSION['user_role']  = $user['nbbtm_role_name'] ?? 'browser';

            echo json_encode([
                'success' => true,
                'user' => [
                    'username'  => $user['username'],
                    'full_name' => $user['full_name'],
                    'email'     => $user['email'],
                    'role'      => $_SESSION['user_role']
                ]
            ]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Invalid username/email or password.']);
    exit;
}

// --- ACTION: CHANGE PASSWORD ---
if ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'You must be logged in to change your password.']);
        exit;
    }

    $userId          = (int)$_SESSION['user_id'];
    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword     = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all password fields.']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'New password and confirmation do not match.']);
        exit;
    }

    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long.']);
        exit;
    }

    $stmt = $db->prepare("SELECT password_hash FROM nbbtm_users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (!password_verify($currentPassword, $user['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Incorrect current password.']);
            exit;
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE nbbtm_users SET password_hash = ? WHERE user_id = ?");
        $updateStmt->bind_param("si", $newHash, $userId);

        if ($updateStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Password updated successfully!']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Failed to update password. Please try again.']);
    exit;
}

// --- ACTION: LOGOUT ---
if ($action === 'logout') {
    session_unset();
    $_SESSION['user_role'] = 'browser';
    
    if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true]);
        exit;
    }
    
    header('Location: ./index.php');
    exit;
}

// --- ACTION: CHECK SESSION ---
if ($action === 'check_session') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'logged_in'    => isset($_SESSION['user_id']),
        'username'     => $_SESSION['username'] ?? 'Guest',
        'full_name'    => $_SESSION['full_name'] ?? '',
        'email'        => $_SESSION['user_email'] ?? null,
        'role'         => $_SESSION['user_role'] ?? 'browser',
        'is_admin'     => isAdmin(),
        'is_developer' => isDeveloper(),
        'can_edit'     => canEdit()
    ]);
    exit;
}
?>