<?php
// footer.php
require_once __DIR__ . '/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$currentYear = date('Y');
?>
<footer class="site-footer">
  <div class="footer-container">
    
    <!-- Top Row: Church Information & Navigation Columns Spanning Page -->
    <div class="footer-grid">
      
      <!-- Church Address & Info Column -->
      <div class="footer-col church-info-col">
        <h4>New Beginnings Baptist Tabernacle</h4>
        <address class="church-address">
          <p>4301 So. 1st Avenue/p>
          <p>City, State 12345</p>
          <p>Phone: (555) 123-4567</p>
          <p>Email: newbeginningsbtm.org</p>
        </address>
      </div>

      <?php if (isAdmin()): ?>
        <!-- Full Admin Navigation Columns -->
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

      <?php else: ?>
        <!-- Non-Admin Navigation Columns -->
        <div class="footer-col">
          <h4>Quick Links</h4>
          <ul>
            <li><a href="index.php" class="<?= in_array($currentPage, ['index.php', 'index1.php']) ? 'active' : '' ?>">Home</a></li>
            <li><a href="contact_dashboard.php" class="<?= ($currentPage === 'contact_dashboard.php') ? 'active' : '' ?>">Contacts Dashboard</a></li>
            <li><a href="event_dashboard.php" class="<?= ($currentPage === 'event_dashboard.php') ? 'active' : '' ?>">Event Dashboard</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Ministries</h4>
          <ul>
            <li><a href="ministry_manager.php" class="<?= ($currentPage === 'ministry_manager.php') ? 'active' : '' ?>">Ministries</a></li>
            <li><a href="ministry_members.php" class="<?= ($currentPage === 'ministry_members.php') ? 'active' : '' ?>">Ministry Members</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>VBS</h4>
          <ul>
            <li><a href="vbs_sessions.php" class="<?= ($currentPage === 'vbs_sessions.php') ? 'active' : '' ?>">Sessions</a></li>
            <li><a href="vbs_roster.php" class="<?= ($currentPage === 'vbs_roster.php') ? 'active' : '' ?>">Registration</a></li>
          </ul>
        </div>
      <?php endif; ?>

    </div>

    <!-- Bottom Copyright -->
    <div class="footer-bottom">
      <p>&copy; <?= $currentYear ?> New Beginnings Baptist Tabernacle Ministries. All rights reserved.</p>
    </div>

  </div>
</footer>

<style>
.site-footer {
  background-color: #0f172a;
  color: #94a3b8;
  padding: 3rem 2rem 1.5rem 2rem;
  margin-top: 3rem;
  border-top: 3px solid #043b8f;
  font-size: 0.9rem;
  width: 100%;
  box-sizing: border-box;
}

.footer-container {
  max-width: 1400px;
  width: 100%;
  margin: 0 auto;
}

.footer-grid {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 2.5rem;
  margin-bottom: 2.5rem;
}

.footer-col {
  flex: 1 1 180px;
  min-width: 160px;
}

.church-info-col {
  flex: 1.5 1 240px;
  min-width: 220px;
}

.footer-col h4 {
  color: #f8fafc;
  font-size: 1rem;
  margin-top: 0;
  margin-bottom: 0.85rem;
  border-bottom: 2px solid #334155;
  padding-bottom: 0.4rem;
}

.church-address {
  font-style: normal;
  line-height: 1.6;
  color: #cbd5e1;
}

.church-address p {
  margin: 0 0 0.35rem 0;
}

.footer-subheading {
  display: block;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #f59e0b;
  font-weight: 600;
  margin-top: 0.85rem;
  margin-bottom: 0.3rem;
}

.footer-col ul {
  list-style: none;
  padding: 0;
  margin: 0 0 0.5rem 0;
}

.footer-col ul li {
  margin-bottom: 0.45rem;
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
  padding-top: 1.5rem;
  font-size: 0.85rem;
  color: #64748b;
  width: 100%;
}
</style>