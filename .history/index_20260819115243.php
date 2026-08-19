<?php
// Display errors for development debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enforce persistent cookie scope before session start
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400, // 24 Hours
        'path'     => '/',   // Root path ensures session spans all sub-folders
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Helper function to format 10-digit phone numbers as (XXX) XXX-XXXX
function formatPhoneNumber($val) {
    if (empty($val)) return 'N/A';
    $digits = preg_replace('/\D/', '', (string)$val);
    if (strlen($digits) === 10) {
        return sprintf("(%s) %s-%s", 
            substr($digits, 0, 3), 
            substr($digits, 3, 3), 
            substr($digits, 6)
        );
    }
    return $val;
}

// Database Connection (Uses relative path from current directory)
require_once __DIR__ . '/config/db.php';

// Require auth helper
require_once __DIR__ . '/include/auth.php';

// Optional: Restrict page to logged-in users
// requireRole(['admin', 'staff', 'browse']); 

// Initialize default metric counters
$total_contacts = 0;
$total_members = 0;
$member_adults = 0;
$member_children = 0;
$total_events = 0;
$total_ministries = 0;
$vbs_sessions_count = 0;
$vbs_classes_count = 0;

$recent_contacts = [];
$upcoming_events = [];
$future_vbs_sessions = [];
$last_vbs_session = null;
$last_vbs_class_attendance = [];

try {
    // 1. Fetch Contact & Membership Statistics (including Adults and Children)
    $contactStats = $db->query("
        SELECT 
            COUNT(*) AS total_contacts,
            SUM(CASE WHEN is_member = 1 THEN 1 ELSE 0 END) AS total_members,
            SUM(CASE WHEN is_member = 1 AND (is_child = 0 OR is_child IS NULL) THEN 1 ELSE 0 END) AS member_adults,
            SUM(CASE WHEN is_member = 1 AND is_child = 1 THEN 1 ELSE 0 END) AS member_children
        FROM contacts
    ");
    if ($row = $contactStats->fetch_assoc()) {
        $total_contacts  = intval($row['total_contacts']);
        $total_members   = intval($row['total_members']);
        $member_adults   = intval($row['member_adults']);
        $member_children = intval($row['member_children']);
    }

    // 2. Fetch Programs / Events Statistics & Upcoming Events
    $eventStats = $db->query("SELECT COUNT(*) AS total_events FROM programs_events");
    if ($row = $eventStats->fetch_assoc()) {
        $total_events = intval($row['total_events']);
    }

    $upcomingQuery = $db->query("
        SELECT e.prg_evnt_id, e.prg_evnt_name, m.min_comm_name AS ministry_name,
               (SELECT MIN(start_datetime) FROM prg_evnt_schedules WHERE prg_evnt_id = e.prg_evnt_id) AS primary_start
        FROM programs_events e
        LEFT JOIN ministry_committee m ON e.min_comm_id = m.min_comm_id
        HAVING primary_start >= NOW() OR primary_start IS NULL
        ORDER BY primary_start ASC
        LIMIT 5
    ");
    if ($upcomingQuery && $upcomingQuery->num_rows > 0) {
        $upcoming_events = $upcomingQuery->fetch_all(MYSQLI_ASSOC);
    }

    // 3. Fetch Active Ministries Count (Handles NULL, 1, or 'Y')
    $minStats = $db->query("
        SELECT COUNT(*) AS total_ministries 
        FROM ministry_committee 
        WHERE is_active = 1 OR is_active IS NULL OR is_active = 'Y'
    ");
    if ($row = $minStats->fetch_assoc()) {
        $total_ministries = intval($row['total_ministries']);
    }

    // 4. Fetch Active/Current Year VBS Sessions Count
    $vbsStats = $db->query("
        SELECT COUNT(*) AS total_sessions 
        FROM vbs_sessions 
        WHERE vbs_year >= YEAR(CURDATE())
    ");
    if ($row = $vbsStats->fetch_assoc()) {
        $vbs_sessions_count = intval($row['total_sessions']);
    }

    // 5. Fetch Active VBS Classes Count linked to Current/Upcoming Sessions
    $classStats = $db->query("
        SELECT COUNT(c.vbs_class_id) AS total_classes 
        FROM vbs_classes c
        INNER JOIN vbs_sessions s ON c.vbs_class_session_id = s.vbs_sessions_id
        WHERE s.vbs_year >= YEAR(CURDATE())
    ");
    if ($row = $classStats->fetch_assoc()) {
        $vbs_classes_count = intval($row['total_classes']);
    }

    // 6. Fetch Recently Added Contacts
    $recentQuery = $db->query("
        SELECT contact_id, first_name, last_name, is_member, is_child, c_email, phone_1 
        FROM contacts 
        ORDER BY contact_id DESC 
        LIMIT 5
    ");
    if ($recentQuery && $recentQuery->num_rows > 0) {
        $recent_contacts = $recentQuery->fetch_all(MYSQLI_ASSOC);
    }

    // 7. Fetch FUTURE VBS Sessions
    $futureSessionQuery = $db->query("
        SELECT vbs_sessions_id, vbs_year, vbs_theme, vbs_start_date, vbs_end_date 
        FROM vbs_sessions 
        WHERE (vbs_start_date > CURDATE()) OR (vbs_start_date IS NULL AND vbs_year > YEAR(CURDATE()))
        ORDER BY vbs_year ASC, vbs_start_date ASC
    ");
    if ($futureSessionQuery && $futureSessionQuery->num_rows > 0) {
        $future_vbs_sessions = $futureSessionQuery->fetch_all(MYSQLI_ASSOC);
    }

    // 8. Fetch LAST VBS Session Attendance Overview by Class
    $lastSessionQuery = $db->query("
        SELECT vbs_sessions_id, vbs_year, vbs_theme, vbs_start_date, vbs_end_date
        FROM vbs_sessions 
        WHERE (vbs_end_date < CURDATE() OR vbs_start_date <= CURDATE() OR vbs_year <= YEAR(CURDATE()))
        ORDER BY vbs_year DESC, vbs_sessions_id DESC 
        LIMIT 1
    ");
    if ($lastSessionQuery && $lastSessionQuery->num_rows > 0) {
        $last_vbs_session = $lastSessionQuery->fetch_assoc();
        $last_session_id = $last_vbs_session['vbs_sessions_id'];

        // Get class counts for attended students in this session
        $classAttQuery = $db->query("
            SELECT vc.vbs_class_id, vc.vbs_class_desc, COUNT(DISTINCT va.student_id) AS attended_count
            FROM vbs_classes vc
            LEFT JOIN vbs_students vs ON vc.vbs_class_id = vs.class_id AND vs.vbs_sessions_id = {$last_session_id}
            LEFT JOIN vbs_attendance va ON vs.contact_id = va.student_id AND va.vbs_session_id = {$last_session_id} AND va.status = 'present'
            WHERE vc.vbs_class_session_id = {$last_session_id}
            GROUP BY vc.vbs_class_id, vc.vbs_class_desc
            ORDER BY vc.vbs_class_desc ASC
        ");

        if ($classAttQuery && $classAttQuery->num_rows > 0) {
            $last_vbs_class_attendance = $classAttQuery->fetch_all(MYSQLI_ASSOC);
        }
    }

} catch (mysqli_sql_exception $e) {
    error_log("Dashboard Data Fetch Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CMS Home - New Beginnings Baptist Tabernacle Ministries</title>
  <link href="https://api.fontshare.com/v2/css?f[]=bespoke-sans@301,400,401,500,501,700,701,800,801,1,2&f[]=bespoke-serif@300,301,400,401,500,501,700,701,800,801,1,2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
</head>
<body>

<?php include 'include/header.php'; ?>

<!-- Main Dashboard Container -->
<main class="dashboard-container">

  <!-- Dynamic KPI Metrics Grid -->
  <section class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-title">Total Contacts</div>
      <div class="kpi-value"><?= number_format($total_contacts) ?></div>
      <div class="kpi-subtext">Registered Directory Records</div>
    </div>
    
    <div class="kpi-card highlight">
      <div class="kpi-title">Active Members</div>
      <div class="kpi-value"><?= number_format($total_members) ?></div>
      <div class="kpi-subtext">Adults: <?= number_format($member_adults) ?> | Children: <?= number_format($member_children) ?></div>
    </div>

    <div class="kpi-card">
      <div class="kpi-title">Active Ministries</div>
      <div class="kpi-value"><?= number_format($total_ministries) ?></div>
      <div class="kpi-subtext">Committees & Groups</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-title">Programs & Events</div>
      <div class="kpi-value"><?= number_format($total_events) ?></div>
      <div class="kpi-subtext">Scheduled Events</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-title">Active VBS Sessions</div>
      <div class="kpi-value"><?= number_format($vbs_sessions_count) ?></div>
      <div class="kpi-subtext">Current & Upcoming Programs</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-title">Active VBS Classes</div>
      <div class="kpi-value"><?= number_format($vbs_classes_count) ?></div>
      <div class="kpi-subtext">Active Class Modules</div>
    </div>
  </section>

  <!-- Two-Column Flexible Layout -->
  <div class="dashboard-layout">

    <!-- Primary Left Column -->
    <section class="main-content">
      
      <!-- Quick Operations Panel (Visible only to authenticated Admins) -->
      <?php if (isAdmin()): ?>
        <div class="card">
          <h3>Quick Operations</h3>
          <div class="action-buttons">
            <a href="contacts.php?action=new" class="btn btn-primary">+ Add New Contact</a>
            <a href="events.php" class="btn btn-accent">Manage Events</a>
            <a href="ministry_manager.php" class="btn btn-secondary">Ministry Directory</a>
            <a href="vbs_sessions.php" class="btn btn-secondary">VBS Sessions</a>
          </div>
        </div>
      <?php endif; ?>

      <!-- Recent Programs & Events Widget -->
      <div class="card">
        <h3>Upcoming Programs & Events</h3>
        <table class="data-table">
          <thead>
            <tr>
              <th>Event Name</th>
              <th>Sponsoring Ministry</th>
              <th>Primary Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($upcoming_events)): ?>
              <?php foreach ($upcoming_events as $event): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($event['prg_evnt_name']) ?></strong>
                  </td>
                  <td><?= htmlspecialchars($event['ministry_name'] ?? 'Unassigned') ?></td>
                  <td>
                    <?= !empty($event['primary_start']) ? date('M d, Y h:i A', strtotime($event['primary_start'])) : 'TBD'; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="3">No upcoming events scheduled.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
        <br>
        <a href="<?= isAdmin() ? 'events.php' : 'event_dashboard.php' ?>" class="link-btn"><?= isAdmin() ? 'View Event Manager &rarr;' : 'View Event Dashboard &rarr;' ?></a>
      </div>

      <!-- Recently Added Contacts Widget -->
      <div class="card">
        <h3>Recently Added Contacts</h3>
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Status</th>
              <th>Category</th>
              <th>Phone</th>
              <th>Email</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($recent_contacts)): ?>
              <?php foreach ($recent_contacts as $contact): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></strong>
                  </td>
                  <td>
                    <?= $contact['is_member'] ? '<span style="color:#0d9488; font-weight:600;">Member</span>' : 'Non-Member'; ?>
                  </td>
                  <td>
                    <?= !empty($contact['is_child']) ? 'Child' : 'Adult'; ?>
                  </td>
                  <td><?= htmlspecialchars(formatPhoneNumber($contact['phone_1'] ?? '')) ?></td>
                  <td><?= htmlspecialchars($contact['c_email'] ?? 'N/A') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5">No contacts found in database.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </section>

    <!-- Secondary Right Column Sidebar -->
    <aside class="sidebar">

      <!-- Member Demographics Breakdown Widget -->
      <div class="card">
        <h3>Member Categories</h3>
        <table class="data-table" style="margin-top: 0.5rem;">
          <thead>
            <tr>
              <th>Age Category</th>
              <th style="text-align: right;">Count</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Adult Members</strong></td>
              <td style="text-align: right;"><strong><?= number_format($member_adults) ?></strong></td>
            </tr>
            <tr>
              <td><strong>Child Members</strong></td>
              <td style="text-align: right;"><strong><?= number_format($member_children) ?></strong></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Last VBS Session Attendance Overview by Class -->
      <div class="card">
        <h3>
          Last VBS Session Attendance 
          <?php if (!empty($last_vbs_session)): ?>
            <span style="font-size:0.85rem; font-weight:normal; color:#64748b; display:block;">
              (<?= htmlspecialchars($last_vbs_session['vbs_year']) ?> - <?= htmlspecialchars($last_vbs_session['vbs_theme'] ?: 'Session #' . $last_vbs_session['vbs_sessions_id']) ?>)
            </span>
          <?php endif; ?>
        </h3>

        <?php if (!empty($last_vbs_class_attendance)): ?>
          <table class="data-table" style="margin-top: 0.5rem;">
            <thead>
              <tr>
                <th>Class</th>
                <th style="text-align: right;">Attended</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($last_vbs_class_attendance as $cls): ?>
                <tr>
                  <td><?= htmlspecialchars($cls['vbs_class_desc']) ?></td>
                  <td style="text-align: right;"><strong><?= number_format($cls['attended_count']) ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p style="font-size:0.9rem; color:#64748b;">No class attendance records available from the last session.</p>
        <?php endif; ?>
      </div>

      <!-- Future VBS Sessions Module (Only rendered if available) -->
      <?php if (!empty($future_vbs_sessions)): ?>
        <div class="card">
          <h3>Upcoming Future VBS Sessions</h3>
          <ul class="quick-links">
            <?php foreach ($future_vbs_sessions as $sess): ?>
              <li style="margin-bottom: 0.75rem;">
                <strong><?= htmlspecialchars($sess['vbs_year']) ?></strong> - <?= htmlspecialchars($sess['vbs_theme'] ?: 'New Session') ?>
                <br>
                <small style="color: #64748b;">
                  <?php 
                    if (!empty($sess['vbs_start_date']) && $sess['vbs_start_date'] !== '0000-00-00') {
                        echo date('M d, Y', strtotime($sess['vbs_start_date']));
                        if (!empty($sess['vbs_end_date']) && $sess['vbs_end_date'] !== '0000-00-00') {
                            echo ' - ' . date('M d, Y', strtotime($sess['vbs_end_date']));
                        }
                    } else {
                        echo 'Dates TBD';
                    }
                  ?>
                </small>
              </li>
            <?php endforeach; ?>
          </ul>
          <br>
          <a href="vbs_sessions.php" class="link-btn">Manage VBS Sessions &rarr;</a>
        </div>
      <?php endif; ?>

      <!-- Modular Task / Action Checklist -->
      <div class="card">
        <h3>System Tasks</h3>
        <ul class="task-list">
          <li>
            <input type="checkbox" id="task1">
            <label for="task1">Verify contact details for new members</label>
          </li>
          <li>
            <input type="checkbox" id="task2">
            <label for="task2">Review upcoming event budgets</label>
          </li>
          <li>
            <input type="checkbox" id="task3">
            <label for="task3">Update active ministry assignments</label>
          </li>
          <li>
            <input type="checkbox" id="task4">
            <label for="task4">Check VBS class roster capacity</label>
          </li>
        </ul>
      </div>

    </aside>

  </div>
</main>
<?php include_once 'include/footer.php'; ?>
</body>
</html>