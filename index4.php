<?php
// Display errors for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database Connection
require_once 'config/db.php';

// Initialize default metric counters
$total_contacts = 0;
$total_members = 0;
$vbs_sessions_count = 0;
$vbs_classes_count = 0;
$recent_contacts = [];
$active_sessions = [];

try {
    // 1. Fetch Total Contacts & Total Members
    $contactStats = $db->query("
        SELECT 
            COUNT(*) AS total_contacts,
            SUM(CASE WHEN is_member = 1 THEN 1 ELSE 0 END) AS total_members
        FROM contacts
    ");
    if ($row = $contactStats->fetch_assoc()) {
        $total_contacts = intval($row['total_contacts']);
        $total_members  = intval($row['total_members']);
    }

    // 2. Fetch Active VBS Sessions & Classes Count
    $vbsStats = $db->query("SELECT COUNT(*) AS total_sessions FROM vbs_sessions");
    if ($row = $vbsStats->fetch_assoc()) {
        $vbs_sessions_count = intval($row['total_sessions']);
    }

    $classStats = $db->query("SELECT COUNT(*) AS total_classes FROM vbs_classes");
    if ($row = $classStats->fetch_assoc()) {
        $vbs_classes_count = intval($row['total_classes']);
    }

    // 3. Fetch Recent Contacts (Last 5 Added)
    $recentQuery = $db->query("
        SELECT contact_id, first_name, last_name, is_member, c_email, phone_1 
        FROM contacts 
        ORDER BY contact_id DESC 
        LIMIT 5
    ");
    if ($recentQuery && $recentQuery->num_rows > 0) {
        $recent_contacts = $recentQuery->fetch_all(MYSQLI_ASSOC);
    }

    // 4. Fetch Active VBS Sessions Overview
    $sessionQuery = $db->query("
        SELECT vbs_sessions_id, vbs_year, vbs_theme, vbs_start_date, vbs_end_date 
        FROM vbs_sessions 
        ORDER BY vbs_year DESC 
        LIMIT 3
    ");
    if ($sessionQuery && $sessionQuery->num_rows > 0) {
        $active_sessions = $sessionQuery->fetch_all(MYSQLI_ASSOC);
    }

} catch (mysqli_sql_exception $e) {
    // Log exception for debugging if needed
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

<?php include 'header.php'; ?>

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
      <div class="kpi-subtext">Confirmed Church Members</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-title">VBS Sessions</div>
      <div class="kpi-value"><?= number_format($vbs_sessions_count) ?></div>
      <div class="kpi-subtext">Configured VBS Programs</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-title">VBS Classes</div>
      <div class="kpi-value"><?= number_format($vbs_classes_count) ?></div>
      <div class="kpi-subtext">Active Class Modules</div>
    </div>
  </section>

  <!-- Two-Column Flexible Layout -->
  <div class="dashboard-layout">

    <!-- Primary Left Column -->
    <section class="main-content">
      
      <!-- Quick Action Panel -->
      <div class="card">
        <h3>Quick Operations</h3>
        <div class="action-buttons">
          <a href="contacts.php?action=new" class="btn btn-primary">+ Add New Contact</a>
          <a href="vbs_sessions.php" class="btn btn-accent">Manage VBS Sessions</a>
          <a href="vbs_manager.php" class="btn btn-secondary">VBS Registration</a>
          <a href="vbs_attendance.php" class="btn btn-secondary">Log Attendance</a>
        </div>
      </div>

      <!-- Recent Contacts Database Widget -->
      <div class="card">
        <h3>Recently Added Contacts</h3>
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Status</th>
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
                  <td><?= htmlspecialchars($contact['phone_1'] ?? 'N/A') ?></td>
                  <td><?= htmlspecialchars($contact['c_email'] ?? 'N/A') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="4">No contacts found in database.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- FUTURE MODULE PLACEHOLDER: Attendance / Sunday Metrics -->
      <!-- Simply add a new <div class="card"></div> block here when expanding -->

    </section>

    <!-- Secondary Right Column Sidebar -->
    <aside class="sidebar">

      <!-- VBS Active Overview Module -->
      <div class="card">
        <h3>VBS Sessions Overview</h3>
        <?php if (!empty($active_sessions)): ?>
          <ul class="quick-links">
            <?php foreach ($active_sessions as $sess): ?>
              <li style="margin-bottom: 0.5rem;">
                <strong><?= htmlspecialchars($sess['vbs_year']) ?></strong> - <?= htmlspecialchars($sess['vbs_theme'] ?: 'No Theme Title') ?>
                <br>
                <small style="color: #64748b;">
                  <?= !empty($sess['vbs_start_date']) ? date('M d, Y', strtotime($sess['vbs_start_date'])) : 'Dates TBD' ?>
                </small>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p style="font-size:0.9rem; color:#64748b;">No VBS sessions found.</p>
        <?php endif; ?>
        <br>
        <a href="vbs_sessions.php" class="link-btn">View All VBS Sessions &rarr;</a>
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
            <label for="task2">Review active VBS class rosters</label>
          </li>
          <li>
            <input type="checkbox" id="task3">
            <label for="task3">Update ministry head assignments</label>
          </li>
        </ul>
      </div>

    </aside>

  </div>
</main>

</body>
</html>