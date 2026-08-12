<?php
// include/auth.php
ob_start(); // Prevent unintended whitespace output breaking JSON responses

// Enforce persistent cookie settings across all site sub-paths before session start
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400, // 24 Hours
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Ensure non-authenticated users default to the 'browse' role
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_role'] = 'browse';
}

// Resilient DB file include path handling for Debian Apache
$dbPathConfig = __DIR__ . '/config/db.php';
$dbPathRoot   = __DIR__ . '/db.php';

if (file_exists($dbPathConfig)) {
    require_once $dbPathConfig;
} elseif (file_exists($dbPathRoot)) {
    require_once $dbPathRoot;
} elseif (file_exists('config/db.php')) {
    require_once 'config/db.php';
} elseif (file_exists('db.php')) {
    require_once 'db.php';
} else {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Database configuration file missing.']);
    exit;
}

/**
 * Check if current user is an authenticated Admin
 */
function isAdmin() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'admin';
}

/**
 * Security middleware: Block execution if user is not an Admin
 */
function requireAdmin() {
    if (!isAdmin()) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'status'  => 'error', 
            'success' => false, 
            'message' => 'Access Denied: Admin privileges required to perform this action.'
        ]);
        exit;
    }
}

/**
 * Flexible security middleware: Allow specified roles
 */
function requireRole(array $allowedRoles = ['admin', 'staff', 'browse']) {
    $currentRole = strtolower($_SESSION['user_role'] ?? 'browse');
    $normalizedAllowed = array_map('strtolower', $allowedRoles);

    if (!in_array($currentRole, $normalizedAllowed, true)) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'status'  => 'error', 
            'success' => false, 
            'message' => 'Access Denied: You do not have permission to perform this action.'
        ]);
        exit;
    }
}

$action = $_REQUEST['action'] ?? '';

// --- ACTION: LOGIN ---
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $identity = trim($_POST['username'] ?? ''); // Accepts Username or Email
    $password = trim($_POST['password'] ?? '');

    if (empty($identity) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    $query = "SELECT u.user_id, u.contact_id, u.username, u.password_hash, u.role, u.is_active,
                     COALESCE(c.c_email, u.email) AS active_email
              FROM system_users u
              LEFT JOIN contacts c ON u.contact_id = c.contact_id
              WHERE u.username = ? OR u.email = ? OR c.c_email = ?";

    $stmt = $db->prepare($query);
    $stmt->bind_param("sss", $identity, $identity, $identity);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if ((int)$user['is_active'] !== 1) {
            echo json_encode(['success' => false, 'message' => 'Account is inactive. Please contact system admin.']);
            exit;
        }

        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']    = (int)$user['user_id'];
            $_SESSION['contact_id'] = $user['contact_id'] ? (int)$user['contact_id'] : null;
            $_SESSION['username']   = $user['username'];
            $_SESSION['user_email']  = $user['active_email'];
            $_SESSION['user_role']   = $user['role'];

            echo json_encode([
                'success' => true,
                'user' => [
                    'username' => $user['username'],
                    'email'    => $user['active_email'],
                    'role'     => $user['role']
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

    $stmt = $db->prepare("SELECT password_hash FROM system_users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (!password_verify($currentPassword, $user['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Incorrect current password.']);
            exit;
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE system_users SET password_hash = ? WHERE user_id = ?");
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
    $_SESSION['user_role'] = 'browse'; // Default back to browse mode on logout
    
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
        'logged_in' => isset($_SESSION['user_id']),
        'username'  => $_SESSION['username'] ?? 'Guest',
        'email'     => $_SESSION['user_email'] ?? null,
        'role'      => $_SESSION['user_role'] ?? 'browse',
        'is_admin'  => isAdmin()
    ]);
    exit;
}
?>