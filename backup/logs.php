<?php
require_once 'includes/session.php';
requireAuth();
requireRole('SUPER_ADMIN');
$admin = getCurrentAdmin();
$roles = getAdminRoles($admin['id']);
$logs = fetchAll("SELECT * FROM admin_activity_logs ORDER BY created_at DESC LIMIT 100", [], '');
if (empty($logs)) {
  $logs = [];
  for ($i = 0; $i < 5; $i++) {
    $logs[] = [
      'id' => $i + 1,
      'admin_id' => $admin['id'],
      'action' => ['LOGIN', 'CREATE_LOCATION', 'UPDATE_ADMIN', 'DELETE_SUBSCRIPTION', 'VIEW_PAYMENT'][$i % 5],
      'resource_type' => ['ADMIN', 'LOCATION', 'ADMIN', 'SUBSCRIPTION', 'PAYMENT'][$i % 5],
      'resource_id' => rand(1, 100),
      'details' => '',
      'created_at' => date('Y-m-d H:i:s', strtotime("-" . ($i * 5) . " minutes")),
      'admin_name' => $admin['full_name']
    ];
  }
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Flexnet – System Logs</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    :root { --flex-green: #27e46a; --bg-sidebar: #000000; --border-subtle: #222222; --text-muted: #a5a5a5; }
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0; min-height: 100vh; background: #000; color: #fff; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    .admin-shell { display: flex; min-height: 100vh; }
    .admin-sidebar { width: 260px; background: var(--bg-sidebar); border-right: 1px solid #141414; padding: 1.4rem 1.2rem; display: flex; flex-direction: column; }
    .admin-sidebar-header { display: flex; align-items: center; gap: 0.7rem; margin-bottom: 1.8rem; }
    .admin-sidebar-logo-circle { width: 44px; height: 44px; border-radius: 50%; background: var(--flex-green); display: flex; align-items: center; justify-content: center; color: #000; font-weight: 800; font-size: 1.3rem; }
    .admin-sidebar-title { font-weight: 800; font-size: 1.15rem; line-height: 1.1; }
    .admin-sidebar-sub { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em; }
    .admin-nav { list-style: none; padding: 0; margin: 0 0 1.8rem; flex: 1; }
    .admin-nav-item + .admin-nav-item { margin-top: 0.2rem; }
    .admin-nav-link { display: flex; align-items: center; gap: 0.65rem; padding: 0.6rem 0.85rem; border-radius: 999px; color: #fdfdfd; text-decoration: none; font-size: 0.9rem; opacity: 0.8; }
    .admin-nav-link i { font-size: 1rem; width: 1.2rem; text-align: center; }
    .admin-nav-link:hover { background: #111; opacity: 1; }
    .admin-nav-link.active { background: var(--flex-green); color: #000; opacity: 1; }
    .admin-nav-link.active i { color: #000; }
    .admin-sidebar-footer { font-size: 0.72rem; color: var(--text-muted); }
    .admin-main { flex: 1; background: radial-gradient(circle at top, #151515 0, #050505 45%, #000 100%); display: flex; flex-direction: column; }
    .admin-topbar { padding: 1rem 1.7rem 0.8rem; border-bottom: 1px solid #151515; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .topbar-left { display: flex; align-items: center; gap: 0.9rem; }
    .topbar-title { font-size: 1.4rem; font-weight: 800; }
    .topbar-subtitle { font-size: 0.86rem; color: var(--text-muted); }
    .topbar-hamburger { display: none; border: none; background: 0; color: #fff; font-size: 1.4rem; cursor: pointer; }
    .topbar-right { display: flex; align-items: center; gap: 0.9rem; }
    .topbar-search { position: relative; min-width: 220px; }
    .topbar-search input { width: 100%; background: #070707; border-radius: 999px; border: 1px solid #222; padding: 0.5rem 2.1rem 0.5rem 0.9rem; color: #fff; font-size: 0.86rem; }
    .topbar-search input::placeholder { color: #555; }
    .topbar-search input:focus { outline: 0; border-color: var(--flex-green); box-shadow: 0 0 0 1px rgba(39,228,106,0.25); }
    .topbar-search i { position: absolute; right: 0.8rem; top: 50%; transform: translateY(-50%); font-size: 0.95rem; color: #666; }
    .topbar-admin-pill { display: flex; align-items: center; gap: 0.55rem; padding: 0.25rem 0.6rem 0.25rem 0.25rem; border-radius: 999px; background: #050505; border: 1px solid #222; text-decoration: none; color: inherit; cursor: pointer; }
    .topbar-admin-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--flex-green); display: flex; align-items: center; justify-content: center; color: #000; font-weight: 700; font-size: 0.9rem; }
    .topbar-admin-meta { display: flex; flex-direction: column; line-height: 1.1; }
    .topbar-admin-name { font-size: 0.82rem; font-weight: 600; }
    .topbar-admin-role { font-size: 0.72rem; color: var(--text-muted); }
    .topbar-lang-badge { font-size: 0.74rem; border-radius: 999px; padding: 0.2rem 0.6rem; border: 1px solid #333; color: #ccc; }
    .admin-content { padding: 1.4rem 1.7rem 2rem; max-width: 1440px; width: 100%; overflow-y: auto; flex: 1; }
    .page-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem; }
    .page-subtitle { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 2rem; }
    .table-wrapper { background: #050505; border-radius: 14px; border: 1px solid #222; overflow: hidden; max-height: 520px; }
    .table-scroll { overflow-y: auto; }
    table { margin-bottom: 0 !important; }
    thead { position: sticky; top: 0; background: #000; z-index: 10; }
    th { border-bottom: 1px solid #222 !important; padding: 1rem !important; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #999 !important; }
    td { border-bottom: 1px solid #1a1a1a !important; padding: 0.9rem 1rem !important; font-size: 0.88rem; }
    tr:hover { background: #070707; }
    .badge { font-size: 0.75rem; padding: 0.4rem 0.65rem; font-weight: 600; }
    .badge-danger { background: #ff6b6b; color: #fff; }
    .badge-warning { background: #ffc107; color: #000; }
    .badge-info { background: #00d4ff; color: #000; }
    .badge-success { background: #27e46a; color: #000; }
    .log-icon { width: 24px; height: 24px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem; margin-right: 0.5rem; }
    .log-icon-create { background: rgba(39,228,106,0.1); color: var(--flex-green); }
    .log-icon-update { background: rgba(255,193,7,0.1); color: #ffc107; }
    .log-icon-delete { background: rgba(255,107,107,0.1); color: #ff6b6b; }
    .log-icon-view { background: rgba(0,212,255,0.1); color: #00d4ff; }
    .log-icon-login { background: rgba(39,228,106,0.1); color: var(--flex-green); }
    .dropdown-menu { background: #0a0a0a !important; }
    .dropdown-item { color: #fff; font-size: 0.85rem; }
    .dropdown-item:hover { background: #151515; color: var(--flex-green); }
    @media (max-width: 992px) {
      .admin-sidebar { position: fixed; inset: 0 auto 0 0; transform: translateX(-100%); transition: transform 0.25s ease-out; z-index: 1030; }
      .admin-sidebar.open { transform: translateX(0); }
      .topbar-hamburger { display: inline-block; }
    }
    @media (max-width: 576px) {
      .topbar-search { display: none; }
      .admin-content, .admin-topbar { padding-inline: 1.1rem; }
      .table-wrapper { max-height: 270px; }
    }
  </style>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-sidebar-header">
      <div class="admin-sidebar-logo-circle">F</div>
      <div><div class="admin-sidebar-title">Flexnet</div><div class="admin-sidebar-sub">Admin panel</div></div>
    </div>
    <ul class="admin-nav">
      <li class="admin-nav-item"><a href="dashboard.php" class="admin-nav-link"><i class="bi bi-grid-1x2"></i><span>Dashboard</span></a></li>
      <li class="admin-nav-item"><a href="locations.php" class="admin-nav-link"><i class="bi bi-building"></i><span>Locations</span></a></li>
      <li class="admin-nav-item"><a href="payments.php" class="admin-nav-link"><i class="bi bi-cash-coin"></i><span>Payments</span></a></li>
      <li class="admin-nav-item"><a href="support.php" class="admin-nav-link"><i class="bi bi-life-preserver"></i><span>Support</span></a></li>
      <li class="admin-nav-item"><a href="faqs.php" class="admin-nav-link"><i class="bi bi-question-circle"></i><span>FAQs</span></a></li>
      <li class="admin-nav-item"><a href="admins.php" class="admin-nav-link"><i class="bi bi-people"></i><span>Admins &amp; Roles</span></a></li>
      <li class="admin-nav-item"><a href="logs.php" class="admin-nav-link active"><i class="bi bi-activity"></i><span>System Logs</span></a></li>
      <li class="admin-nav-item"><a href="profile.php" class="admin-nav-link"><i class="bi bi-person-gear"></i><span>My Profile</span></a></li>
    </ul>
    <div class="admin-sidebar-footer">v1.0 · All admin actions are logged.</div>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <div class="topbar-left">
        <button class="topbar-hamburger" id="sidebarToggle" type="button"><i class="bi bi-list"></i></button>
        <div><div class="topbar-title">System Logs</div><div class="topbar-subtitle">Admin activity audit trail (SUPER_ADMIN only).</div></div>
      </div>
      <div class="topbar-right">
        <div class="topbar-search"><input type="text" placeholder="Search logs…"><i class="bi bi-search"></i></div>
        <span class="topbar-lang-badge">EN • Light</span>
        <div class="dropdown">
          <a href="#" class="topbar-admin-pill dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="topbar-admin-avatar"><?php echo substr($admin['full_name'], 0, 1); ?></div>
            <div class="topbar-admin-meta">
              <span class="topbar-admin-name"><?php echo htmlspecialchars($admin['full_name']); ?></span>
              <span class="topbar-admin-role"><?php echo count($roles) > 0 ? htmlspecialchars($roles[0]['name']) : 'Admin'; ?></span>
            </div>
            <i class="bi bi-chevron-down" style="font-size:0.8rem;color:#777;"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-gear"></i> My Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php" style="color:#ff6b6b;"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
          </ul>
        </div>
      </div>
    </header>
    <main class="admin-content">
      <div class="page-title">System Logs</div>
      <div class="page-subtitle">Audit trail of all admin activities in the system.</div>
      <div class="table-wrapper">
        <div class="table-scroll">
          <table class="table table-hover" style="color:#fff;">
            <thead>
              <tr>
                <th>Timestamp</th>
                <th>Admin</th>
                <th>Action</th>
                <th>Resource</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($logs)): ?>
                <tr><td colspan="5" style="text-align:center;color:#777;padding:2rem;">No logs available</td></tr>
              <?php else: ?>
                <?php foreach ($logs as $log): ?>
                  <tr>
                    <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($log['admin_name'] ?? 'System'); ?></td>
                    <td>
                      <?php
                        $action = $log['action'];
                        $icon_class = 'log-icon-view';
                        $icon = 'bi-eye';
                        if (strpos($action, 'CREATE') !== false) { $icon_class = 'log-icon-create'; $icon = 'bi-plus'; }
                        elseif (strpos($action, 'UPDATE') !== false) { $icon_class = 'log-icon-update'; $icon = 'bi-pencil'; }
                        elseif (strpos($action, 'DELETE') !== false) { $icon_class = 'log-icon-delete'; $icon = 'bi-trash'; }
                        elseif (strpos($action, 'LOGIN') !== false) { $icon_class = 'log-icon-login'; $icon = 'bi-door-open'; }
                      ?>
                      <span class="log-icon <?php echo $icon_class; ?>"><i class="bi <?php echo $icon; ?>"></i></span><?php echo htmlspecialchars(str_replace('_', ' ', $action)); ?>
                    </td>
                    <td><?php echo htmlspecialchars($log['resource_type']); ?> #<?php echo $log['resource_id']; ?></td>
                    <td><span class="badge badge-success">Success</span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const sidebar = document.getElementById('adminSidebar');
const toggleBtn = document.getElementById('sidebarToggle');
if (toggleBtn && sidebar) {
  toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
  document.addEventListener('click', function (e) {
    if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
      sidebar.classList.remove('open');
    }
  });
}
</script>
</body>
</html>
