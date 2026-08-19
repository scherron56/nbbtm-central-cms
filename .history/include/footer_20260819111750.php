<?php
// footer.php
require_once __DIR__ . '/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$currentYear = date('Y');
?>
<footer class="site-footer">
  <div class="footer-container">
    
    <!-- Quick Navigation Links -->
    <div class="footer-nav">
      <div class="footer-col">
        <h4>Navigation</h4>
        <ul>
          <li><a href="index.php" class="<?= in_array($currentPage, ['index.php', 'index1.php']) ? 'active' : '' ?>">Home</a></li>
          <li><a href="contacts.php" class="<?= ($currentPage === 'contacts.php') ? 'active' : '' ?>">New/Update Contacts</a></li>
          <li><a href="contact_dashboard.php" class="<?= ($currentPage === 'contact_dashboard.php') ? 'active' : '' ?>">Contact Dashboard</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Ministries & Programs</h4>
        <ul>
          <li><a href="ministry_manager.php" class="<?= ($currentPage === 'ministry_manager.php') ? 'active' : '' ?>">Ministries</a></li>
          <li><a href="ministry_members.php" class="<?= ($currentPage === 'ministry_members.php') ? 'active' : '' ?>">Ministry Members</a></li>
          <li><a href="events.php" class="<?= ($currentPage === 'events.php') ? 'active' : '' ?>">Program / Event Updates</a></li>
          <li><a href="event_dashboard.php" class="<?= ($currentPage === 'event_dashboard.php') ? 'active' : '' ?>">Event Dashboard</a></li>
          <li><a href="event_registration.php" class="<?= ($currentPage === 'event_registration.php') ? 'active' : '' ?>">Event Registration</a></li>
          <li><a href="event_checkin.php" class="<?= ($currentPage === 'event_checkin.php') ? 'active' : '' ?>">Event Check-in</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>VBS</h4>
        <ul>
          <li><a href="vbs_sessions.php" class="<?= ($currentPage === 'vbs_sessions.php') ? 'active' : '' ?>">Sessions</a></li>
          <li><a href="vbs_roster.php" class="<?= ($currentPage === 'vbs_roster.php') ? 'active' : '' ?>">Registration</a></li>
          <li><a href="vbs_attendance.php" class="<?= ($currentPage === 'vbs_attendance.php') ? 'active' : '' ?>">Attendance</a></li>
        </ul>
      </div>

      <!-- Admin Utilities Section -->
      <?php if (isAdmin()): ?>
        <div class="footer-col admin-col">
          <h4>Admin</h4>
          
          <span class="footer-subheading">Emails</span>
          <ul>
            <li><a href="admin_mailer.php" class="<?= ($currentPage === 'admin_mailer.php') ? 'active' : '' ?>">Send Email(s) - Contacts</a></li>
            <li><a href="ministry_mailer.php" class="<?= ($currentPage === 'ministry_mailer.php') ? 'active' : '' ?>">Send Email(s) - Ministries & Committees</a></li>
          </ul>

          <span class="footer-subheading">Reports</span>
          <ul>
            <li><a href="reports.php" class="<?= ($currentPage === 'reports.php') ? 'active' : '' ?>">General Reports</a></li>
          </ul>

          <ul>
            <li><a href="user_management.php" class="<?= ($currentPage === 'user_management.php') ? 'active' : '' ?>">👥 User Management</a></li>
          </ul>
        </div>
      <?php endif; ?>
    </div>

    <div class="footer-bottom">
      <p>&copy; <?= $currentYear ?> New Beginnings Baptist Tabernacle Ministries. All rights reserved.</p>
    </div>
  </div>
</footer>

<style>
.site-footer {
  background-color: #0f172a;
  color: #94a3b8;
  padding: 2.5rem 1rem 1.5rem 1rem;
  margin-top: 3rem;
  border-top: 3px solid #043b8f;
  font-size: 0.9rem;
}
.footer-container {
  max-width: 1200px;
  margin: 0 auto;
}
.footer-nav {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 2rem;
  margin-bottom: 2rem;
}
.footer-col h4 {
  color: #f8fafc;
  font-size: 1rem;
  margin-top: 0;
  margin-bottom: 0.75rem;
  border-bottom: 2px solid #334155;
  padding-bottom: 0.4rem;
}
.footer-subheading {
  display: block;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #f59e0b;
  font-weight: 600;
  margin-top: 0.75rem;
  margin-bottom: 0.25rem;
}
.footer-col ul {
  list-style: none;
  padding: 0;
  margin: 0 0 0.5rem 0;
}
.footer-col ul li {
  margin-bottom: 0.4rem;
}
.footer-col ul li a {
  color: #cbd5e1;
  text-decoration: none;
  transition: color 0.2s ease;
}
.footer-col ul li a:hover,
.footer-col ul li a.active {
  color: #60a5fa;
}
.footer-bottom {
  text-align: center;
  border-top: 1px solid #1e293b;
  padding-top: 1.25rem;
  font-size: 0.82rem;
  color: #64748b;
}
</style>