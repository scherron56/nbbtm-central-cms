 <?php
// include/auth.php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
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
 * Log user access and security events to access_logs table
 */
function logAccess($db, $userId, $identifier, $action) {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    $stmt = $db->prepare("INSERT INTO access_logs (user_id, identifier_used, action, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userId, $identifier, $action, $ipAddress, $userAgent);
    $stmt->execute();
    $stmt->close();
}

/**
 * Rate Limiter: Checks if current IP exceeds maximum allowed failed attempts.
 * Standard Rule: Max 5 failed attempts in 15 minutes.
 */
function isRateLimited($db, $identity = '', $maxAttempts = 5, $timeWindowMinutes = 15) {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    $timeWindowMinutes = max(1, (int)$timeWindowMinutes);
    $query = "SELECT COUNT(*) AS failed_count 
              FROM access_logs 
              WHERE ip_address = ? 
                AND identifier_used = ?
                AND action LIKE 'LOGIN_FAILED%' 
                AND created_at >= NOW() - INTERVAL {$timeWindowMinutes} MINUTE";

    $stmt = $db->prepare($query);
    $stmt->bind_param("ss", $ipAddress, $identity);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return ($row['failed_count'] ?? 0) >= $maxAttempts;
}

/**
 * Check if user is Admin
 */
function isAdmin() {
    if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
        return false;
    }

    $role = strtolower($_SESSION['user_role']);
    return $role === 'admin' || $role === 'developer';
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

    if ($currentRole === 'developer' && in_array('admin', $normalizedAllowed, true)) {
        return;
    }

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

    // Rate limiting check: Max 5 failed attempts within 15 minutes
    if (isRateLimited($db, $identity, 5, 15)) {
        logAccess($db, null, $identity, 'LOGIN_BLOCKED_RATE_LIMIT');
        http_response_code(429);
        echo json_encode([
            'success' => false, 
            'message' => 'Too many failed login attempts. Please try again in 15 minutes.'
        ]);
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
            logAccess($db, (int)$user['user_id'], $identity, 'LOGIN_BLOCKED_INACTIVE');
            echo json_encode(['success' => false, 'message' => 'Account is inactive. Please contact system admin.']);
            exit;
        }

        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']    = (int)$user['user_id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['full_name']  = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['nbbtm_role_name'] ?? 'browser';

            logAccess($db, (int)$user['user_id'], $identity, 'LOGIN_SUCCESS');

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
        } else {
            logAccess($db, (int)$user['user_id'], $identity, 'LOGIN_FAILED_BAD_PASSWORD');
        }
    } else {
        logAccess($db, null, $identity, 'LOGIN_FAILED_NO_USER');
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
            logAccess($db, $userId, $_SESSION['username'] ?? '', 'PASSWORD_CHANGE_FAILED');
            echo json_encode(['success' => false, 'message' => 'Incorrect current password.']);
            exit;
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE nbbtm_users SET password_hash = ? WHERE user_id = ?");
        $updateStmt->bind_param("si", $newHash, $userId);

        if ($updateStmt->execute()) {
            logAccess($db, $userId, $_SESSION['username'] ?? '', 'PASSWORD_CHANGE_SUCCESS');
            echo json_encode(['success' => true, 'message' => 'Password updated successfully!']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Failed to update password. Please try again.']);
    exit;
}

// --- ACTION: LOGOUT ---
if ($action === 'logout') {
    if (isset($_SESSION['user_id'])) {
        logAccess($db, (int)$_SESSION['user_id'], $_SESSION['username'] ?? '', 'LOGOUT');
    }

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

// Log page views for logged-in users on standard requests
if (isset($_SESSION['user_id']) && empty($action)) {
    $currentPage = $_SERVER['SCRIPT_NAME'] ?? 'UNKNOWN_PAGE';
    logAccess($db, (int)$_SESSION['user_id'], $_SESSION['username'] ?? '', 'PAGE_VIEW: ' . $currentPage);
}
?>