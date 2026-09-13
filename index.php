<?php
// Display errors for development debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enforce persistent cookie scope before session start
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, // 24 Hours
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

// Require updated auth helper (which also resolves database connection)
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/include/auth.php';

// Initialize default metric counters
$total_contacts = 0;
$total_members = 0;
$member_adults = 0;
$member_children = 0;
$total_events = 0;
$total_ministries = 0;

$recent_contacts = [];
$upcoming_events = [];
$this_week_celebrations = [];
$next_week_celebrations = [];

// Calculate THIS Sunday and NEXT Sunday start dates (YYYY-MM-DD)
$this_sunday = (date('w') == 0) ? date('Y-m-d') : date('Y-m-d', strtotime('last Sunday'));
$next_sunday = date('Y-m-d', strtotime($this_sunday . ' +1 week'));

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

    // 4. Fetch Recently Added Contacts
    $recentQuery = $db->query("
        SELECT contact_id, first_name, last_name, is_member, is_child, c_email, phone_1 
        FROM contacts 
        ORDER BY contact_id DESC 
        LIMIT 5
    ");
    if ($recentQuery && $recentQuery->num_rows > 0) {
        $recent_contacts = $recentQuery->fetch_all(MYSQLI_ASSOC);
    }

    // 5a. Fetch THIS Week's Celebrations
    $stmt_cel_this = $db->prepare("CALL GetWeeklyCelebrations(?)");
    if ($stmt_cel_this) {
        $stmt_cel_this->bind_param("s", $this_sunday);
        $stmt_cel_this->execute();
        $result = $stmt_cel_this->get_result();
        if ($result) {
            $this_week_celebrations = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt_cel_this->close();

        // Flush procedure result buffers
        while ($db->more_results() && $db->next_result()) {
            if ($extra_result = $db->use_result()) {
                $extra_result->free();
            }
        }
    }

    // 5b. Fetch NEXT Week's Celebrations
    $stmt_cel_next = $db->prepare("CALL GetWeeklyCelebrations(?)");
    if ($stmt_cel_next) {
        $stmt_cel_next->bind_param("s", $next_sunday);
        $stmt_cel_next->execute();
        $result = $stmt_cel_next->get_result();
        if ($result) {
            $next_week_celebrations = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt_cel_next->close();

        // Flush procedure result buffers
        while ($db->more_results() && $db->next_result()) {
            if ($extra_result = $db->use_result()) {
                $extra_result->free();
            }
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
  </section>

  <!-- Two-Column Flexible Layout -->
  <div class="dashboard-layout">

    <!-- Primary Left Column -->
    <section class="main-content">
      
      <!-- Quick Operations Panel -->
      <?php if (canEdit()): ?>
        <div class="card">
          <h3>Quick Operations</h3>
          <div class="action-buttons">
            <a href="contacts.php?action=new" class="btn btn-primary">+ Add New Contact</a>
            <a href="events.php" class="btn btn-accent">Manage Events</a>
            <a href="ministry_manager.php" class="btn btn-secondary">Ministry Directory</a>
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
        <a href="<?= canEdit() ? 'events.php' : 'event_dashboard.php' ?>" class="link-btn"><?= canEdit() ? 'View Event Manager &rarr;' : 'View Event Dashboard &rarr;' ?></a>
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

      <!-- Celebrations Widget (This Week & Next Week) -->
      <div class="card">
        <h3 style="margin-bottom: 0.75rem;">Celebrations Overview</h3>

        <!-- SECTION 1: THIS WEEK -->
        <h4 style="margin: 0.5rem 0; color: #0d9488; font-size: 0.875rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 4px;">
          This Week <span style="font-size:0.75rem; font-weight:normal; color:#64748b;">(Week of <?= date('M j', strtotime($this_sunday)) ?>)</span>
        </h4>

        <?php if (!empty($this_week_celebrations)): ?>
          <table class="data-table" style="margin-top: 0.25rem; margin-bottom: 1rem;">
            <thead>
              <tr>
                <th>Name / Couple</th>
                <th>Type</th>
                <th style="text-align: right;">Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($this_week_celebrations as $item): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($item['title']) ?></strong>
                  </td>
                  <td>
                    <?php if ($item['celebration_type'] === 'Birthday'): ?>
                      <span style="background-color: #e0f2fe; color: #0284c7; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">🎂 Birthday</span>
                    <?php else: ?>
                      <span style="background-color: #fef3c7; color: #d97706; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">💍 Anniversary</span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align: right; color: #0d9488; font-weight: 600;">
                    <?= htmlspecialchars($item['display_date']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p style="font-size:0.85rem; color:#64748b; margin-top: 0.25rem; margin-bottom: 1rem;">No celebrations scheduled for this week.</p>
        <?php endif; ?>

        <!-- SECTION 2: NEXT WEEK -->
        <h4 style="margin: 0.5rem 0; color: #475569; font-size: 0.875rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 4px;">
          Next Week <span style="font-size:0.75rem; font-weight:normal; color:#64748b;">(Week of <?= date('M j', strtotime($next_sunday)) ?>)</span>
        </h4>

        <?php if (!empty($next_week_celebrations)): ?>
          <table class="data-table" style="margin-top: 0.25rem;">
            <thead>
              <tr>
                <th>Name / Couple</th>
                <th>Type</th>
                <th style="text-align: right;">Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($next_week_celebrations as $item): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($item['title']) ?></strong>
                  </td>
                  <td>
                    <?php if ($item['celebration_type'] === 'Birthday'): ?>
                      <span style="background-color: #e0f2fe; color: #0284c7; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">🎂 Birthday</span>
                    <?php else: ?>
                      <span style="background-color: #fef3c7; color: #d97706; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">💍 Anniversary</span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align: right; color: #0d9488; font-weight: 600;">
                    <?= htmlspecialchars($item['display_date']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p style="font-size:0.85rem; color:#64748b; margin-top: 0.25rem;">No celebrations scheduled for next week.</p>
        <?php endif; ?>

      </div>

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
        </ul>
      </div>

    </aside>

  </div>
</main>
<?php include_once 'include/footer.php'; ?>
</body>
</html>