<?php
require_once 'includes/session.php';
requireAuth();
requireRole('SUPER_ADMIN');
$admin = getCurrentAdmin();
$roles = getAdminRoles($admin['id']);
$admins = fetchAll("SELECT a.id, a.full_name, a.email, a.is_active, a.last_login_at, a.created_at FROM admins a ORDER BY a.created_at DESC", [], '');
foreach ($admins as &$adm) $adm['admin_roles'] = getAdminRoles($adm['id']);
$all_roles = getAllRoles();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Flexnet – Admins & Roles</title>
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
    .admin-sidebar-logo-circle { width: 44px; height: 44px; border-radius: 50%; background: var(--flex-green); display: flex; align-items: center; justify-content: center; color: #000; font-weight: 800; font-size: 1.3rem; overflow: hidden; }
    .admin-sidebar-logo-circle img { width: 100%; height: 100%; object-fit: cover; }
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
    .page-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; }
    .page-title { font-size: 1.2rem; font-weight: 700; }
    .page-subtitle { font-size: 0.85rem; color: var(--text-muted); }
    .page-actions { display: flex; gap: 0.6rem; }
    .btn-action { border-radius: 999px; padding: 0.4rem 0.9rem; border: none; background: #222; color: #fff; font-size: 0.82rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.3rem; }
    .btn-action:hover { background: #333; }
    .btn-add { background: var(--flex-green); color: #000; font-weight: 600; }
    .btn-add:hover { background: #37f17a; }
    .card { background: #050505; border: 1px solid #222; border-radius: 14px; }
    .card-header { border-bottom: 1px solid #222; padding: 1rem; }
    .card-body { padding: 1rem; }
    .table-wrapper { max-height: 520px; overflow-y: auto; border-radius: 12px; border: 1px solid #202020; }
    table { margin-bottom: 0; }
    thead { position: sticky; top: 0; background: #000; z-index: 10; }
    th { border-bottom: 1px solid #222 !important; padding: 1rem !important; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #999 !important; }
    td { border-bottom: 1px solid #1a1a1a !important; padding: 0.9rem 1rem !important; font-size: 0.88rem; }
    tr:hover { background: #070707; }
    .badge { font-size: 0.75rem; padding: 0.4rem 0.65rem; font-weight: 600; }
    .badge-org { background: rgba(255,165,0,0.2); color: #ffa500; }
    .badge-grn { background: rgba(39,228,106,0.2); color: #27e46a; }
    .badge-blu { background: rgba(0,212,255,0.2); color: #00d4ff; }
    .badge-success { background: #27e46a; color: #000; }
    .badge-danger { background: #ff6b6b; color: #fff; }
    .btn-icon { border: 1px solid #333; background: #050505; color: #fff; width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.8rem; padding: 0; }
    .btn-icon:hover { border-color: var(--flex-green); color: var(--flex-green); }
    .modal-content { background: #050505 !important; color: #fff !important; border-radius: 14px !important; border: 1px solid #222 !important; }
    .modal-header { border-bottom: 1px solid #222 !important; }
    .modal-title { font-weight: 600; }
    .form-label { font-size: 0.82rem; color: #e0e0e0; }
    .form-control, .form-select { font-size: 0.85rem; background: #050505 !important; color: #fff !important; border: 1px solid #333 !important; border-radius: 10px; }
    .form-control:focus, .form-select:focus { border-color: var(--flex-green) !important; box-shadow: 0 0 0 1px rgba(39,228,106,0.2) !important; outline: none; }
    .form-control::placeholder { color: #777; }
    .form-check-input { background: #050505 !important; border-color: #333 !important; }
    .form-check-input:checked { background: var(--flex-green) !important; border-color: var(--flex-green) !important; }
    .btn-primary { background: var(--flex-green); color: #000; border: none; }
    .btn-primary:hover { background: #37f17a; }
    .toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 1050; }
    .toast { background: #050505; border: 1px solid #222; border-radius: 10px; }
    .toast.success { border-left: 3px solid #27e46a; }
    .toast.error { border-left: 3px solid #ff6b6b; }
    @media (max-width: 992px) {
      .admin-sidebar { position: fixed; inset: 0 auto 0 0; transform: translateX(-100%); transition: transform 0.25s ease-out; z-index: 1030; }
      .admin-sidebar.open { transform: translateX(0); }
      .topbar-hamburger { display: inline-block; }
    }
    @media (max-width: 576px) {
      .topbar-search { display: none; }
      .admin-content, .admin-topbar { padding-inline: 1.1rem; }
      .page-header { flex-direction: column; align-items: flex-start; }
      .table-wrapper { max-height: 270px; }
    }
  </style>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-sidebar-header">
      <div class="admin-sidebar-logo-circle">
        <img src="../flexnet-logo.svg" alt="Flexnet logo">
      </div>
      <div><div class="admin-sidebar-title">Flexnet</div><div class="admin-sidebar-sub">Admin panel</div></div>
    </div>
    <ul class="admin-nav">
      <li class="admin-nav-item"><a href="dashboard.php" class="admin-nav-link"><i class="bi bi-grid-1x2"></i><span>Dashboard</span></a></li>
      <li class="admin-nav-item"><a href="locations.php" class="admin-nav-link"><i class="bi bi-building"></i><span>Locations</span></a></li>
      <li class="admin-nav-item"><a href="payments.php" class="admin-nav-link"><i class="bi bi-cash-coin"></i><span>Payments</span></a></li>
      <li class="admin-nav-item"><a href="support.php" class="admin-nav-link"><i class="bi bi-life-preserver"></i><span>Support</span></a></li>
      <li class="admin-nav-item"><a href="faqs.php" class="admin-nav-link"><i class="bi bi-question-circle"></i><span>FAQs</span></a></li>
      <li class="admin-nav-item"><a href="admins.php" class="admin-nav-link active"><i class="bi bi-people"></i><span>Admins &amp; Roles</span></a></li>
      <li class="admin-nav-item"><a href="logs.php" class="admin-nav-link"><i class="bi bi-activity"></i><span>System Logs</span></a></li>
      <li class="admin-nav-item"><a href="profile.php" class="admin-nav-link"><i class="bi bi-person-gear"></i><span>My Profile</span></a></li>
    </ul>
    <div class="admin-sidebar-footer">v1.0 · All admin actions are logged.</div>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <div class="topbar-left">
        <button class="topbar-hamburger" id="sidebarToggle" type="button"><i class="bi bi-list"></i></button>
        <div><div class="topbar-title">Admins & Roles</div><div class="topbar-subtitle">Manage administrator accounts and permissions.</div></div>
      </div>
      <div class="topbar-right">
        <div class="topbar-search"><input type="text" id="searchAdmins" placeholder="Search admins…"><i class="bi bi-search"></i></div>
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
      <div class="page-header">
        <div><div class="page-title">Administrator Accounts</div><div class="page-subtitle">Manage admins with role assignment.</div></div>
        <div class="page-actions">
          <button class="btn-action" type="button" data-bs-toggle="modal" data-bs-target="#filterModal"><i class="bi bi-sliders"></i> Filter</button>
          <button class="btn-action btn-add" type="button" data-bs-toggle="modal" data-bs-target="#createAdminModal"><i class="bi bi-plus-lg"></i> Add Admin</button>
        </div>
      </div>
      <div class="card">
        <div class="card-header">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
              <div class="page-title">Admin Accounts</div>
              <div class="page-subtitle">Email, roles, status and last login.</div>
            </div>
            <div class="page-subtitle">Showing <span id="adminCount"><?php echo count($admins); ?></span> admin(s)</div>
          </div>
        </div>
        <div class="table-wrapper">
          <table class="table table-hover" style="color:#fff;">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th>Status</th>
                <th>Last Login</th>
                <th style="width:90px;">Actions</th>
              </tr>
            </thead>
            <tbody id="adminsTableBody">
              <?php foreach ($admins as $adm): ?>
                <tr data-admin-id="<?php echo $adm['id']; ?>">
                  <td><?php echo htmlspecialchars($adm['full_name']); ?></td>
                  <td><?php echo htmlspecialchars($adm['email']); ?></td>
                  <td>
                    <?php foreach ($adm['admin_roles'] as $role):
                      $role_colors = ['SUPER_ADMIN' => 'badge-org', 'BILLING_ADMIN' => 'badge-grn', 'SUPPORT_AGENT' => 'badge-blu'];
                      $color = $role_colors[$role['name']] ?? 'badge-success';
                    ?>
                      <span class="badge <?php echo $color; ?>"><?php echo str_replace('_', ' ', $role['name']); ?></span>
                    <?php endforeach; ?>
                  </td>
                  <td><span class="badge <?php echo $adm['is_active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $adm['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                  <td><?php echo $adm['last_login_at'] ? date('M d, Y H:i', strtotime($adm['last_login_at'])) : 'Never'; ?></td>
                  <td>
                    <button class="btn-icon edit-admin" data-id="<?php echo $adm['id']; ?>" data-name="<?php echo htmlspecialchars($adm['full_name']); ?>" data-email="<?php echo htmlspecialchars($adm['email']); ?>" data-active="<?php echo $adm['is_active']; ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                    <button class="btn-icon delete-admin" data-id="<?php echo $adm['id']; ?>" data-name="<?php echo htmlspecialchars($adm['full_name']); ?>" title="Delete"><i class="bi bi-trash"></i></button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Create Admin Modal -->
<div class="modal fade" id="createAdminModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Create New Admin</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="createAdminForm">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Full Name *</label>
            <input type="text" class="form-control" name="full_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email *</label>
            <input type="email" class="form-control" name="email" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password *</label>
            <input type="password" class="form-control" name="password" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Roles</label>
            <?php foreach ($all_roles as $role): ?>
              <div class="form-check">
                <input type="checkbox" class="form-check-input" name="roles" value="<?php echo $role['id']; ?>" id="role_<?php echo $role['id']; ?>">
                <label class="form-check-label" for="role_<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></label>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="mb-0">
            <label class="form-label">Status</label>
            <select class="form-select" name="is_active">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Create Admin</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Edit Admin</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="editAdminForm">
        <input type="hidden" name="id" id="editAdminId">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Full Name *</label>
            <input type="text" class="form-control" name="full_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email *</label>
            <input type="email" class="form-control" name="email" required>
          </div>
          <div class="mb-3">
            <label class="form-label">New Password (leave blank to keep)</label>
            <input type="password" class="form-control" name="password">
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="is_active">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Update Admin</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Filter Admins</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Status</label>
          <select class="form-select" id="filterStatus">
            <option value="">All statuses</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-light btn-sm" id="resetFilterBtn" data-bs-dismiss="modal">Reset</button>
        <button type="button" class="btn btn-primary btn-sm" id="applyFilterBtn" data-bs-dismiss="modal">Apply Filter</button>
      </div>
    </div>
  </div>
</div>

<div class="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const sidebar = document.getElementById('adminSidebar'), toggleBtn = document.getElementById('sidebarToggle');
if (toggleBtn && sidebar) {
  toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
  document.addEventListener('click', function (e) {
    if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
      sidebar.classList.remove('open');
    }
  });
}

function showToast(msg, type = 'success') {
  const toastHTML = `<div class="toast ${type}" role="alert">
    <div style="padding:0.75rem;display:flex;align-items:center;gap:0.5rem;">
      <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
      <span>${msg}</span>
    </div>
  </div>`;
  const container = document.querySelector('.toast-container');
  const toast = document.createElement('div');
  toast.innerHTML = toastHTML;
  container.appendChild(toast.firstElementChild);
  const toastEl = container.lastElementChild;
  setTimeout(() => toastEl.remove(), 3000);
}

function disableButton(btn) { btn.disabled = true; btn.style.opacity = '0.5'; }
function enableButton(btn) { btn.disabled = false; btn.style.opacity = '1'; }

// Create Admin
document.getElementById('createAdminForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = e.target.querySelector('button[type="submit"]');
  disableButton(btn);
  const fd = new FormData(e.target);
  fd.append('action', 'create_admin');
  try {
    const res = await fetch('includes/api-handlers.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      showToast('Admin created successfully');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(json.message, 'error');
      enableButton(btn);
    }
  } catch (err) {
    showToast('Error: ' + err.message, 'error');
    enableButton(btn);
  }
});

// Edit Admin
document.querySelectorAll('.edit-admin').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('editAdminId').value = btn.dataset.id;
    document.getElementById('editAdminForm').querySelector('[name="full_name"]').value = btn.dataset.name;
    document.getElementById('editAdminForm').querySelector('[name="email"]').value = btn.dataset.email;
    document.getElementById('editAdminForm').querySelector('[name="is_active"]').value = btn.dataset.active;
    new bootstrap.Modal(document.getElementById('editAdminModal')).show();
  });
});

document.getElementById('editAdminForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = e.target.querySelector('button[type="submit"]');
  disableButton(btn);
  const fd = new FormData(e.target);
  fd.append('action', 'update_admin');
  try {
    const res = await fetch('includes/api-handlers.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      showToast('Admin updated successfully');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(json.message, 'error');
      enableButton(btn);
    }
  } catch (err) {
    showToast('Error: ' + err.message, 'error');
    enableButton(btn);
  }
});

// Delete Admin
document.querySelectorAll('.delete-admin').forEach(btn => {
  btn.addEventListener('click', () => {
    if (confirm(`Delete admin "${btn.dataset.name}"?`)) {
      const fd = new FormData();
      fd.append('action', 'delete_admin');
      fd.append('id', btn.dataset.id);
      fetch('includes/api-handlers.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(json => {
          if (json.success) {
            showToast('Admin deleted');
            setTimeout(() => location.reload(), 1000);
          } else {
            showToast(json.message, 'error');
          }
        })
        .catch(err => showToast('Error: ' + err.message, 'error'));
    }
  });
});

// Search Admins
document.getElementById('searchAdmins').addEventListener('input', async (e) => {
  const q = e.target.value.trim();
  if (!q) {
    location.reload();
    return;
  }
  try {
    const res = await fetch(`includes/api-handlers.php?action=search_admins&q=${encodeURIComponent(q)}`);
    const json = await res.json();
    if (json.success) {
      const tbody = document.getElementById('adminsTableBody');
      tbody.innerHTML = '';
      json.data.forEach(adm => {
        const row = `<tr data-admin-id="${adm.id}">
          <td>${adm.full_name}</td>
          <td>${adm.email}</td>
          <td>-</td>
          <td><span class="badge ${adm.is_active ? 'badge-success' : 'badge-danger'}">${adm.is_active ? 'Active' : 'Inactive'}</span></td>
          <td>${adm.last_login_at ? new Date(adm.last_login_at).toLocaleString() : 'Never'}</td>
          <td>
            <button class="btn-icon edit-admin" data-id="${adm.id}" data-name="${adm.full_name}" data-email="${adm.email}" data-active="${adm.is_active}"><i class="bi bi-pencil"></i></button>
            <button class="btn-icon delete-admin" data-id="${adm.id}" data-name="${adm.full_name}"><i class="bi bi-trash"></i></button>
          </td>
        </tr>`;
        tbody.innerHTML += row;
      });
      document.getElementById('adminCount').textContent = json.data.length;
      reattachEventListeners();
    }
  } catch (err) {
    showToast('Search error: ' + err.message, 'error');
  }
});

// Filter Admins
document.getElementById('applyFilterBtn').addEventListener('click', async () => {
  const status = document.getElementById('filterStatus').value;
  if (!status && status !== '0') {
    showToast('Please select a status', 'error');
    return;
  }
  try {
    const res = await fetch(`includes/api-handlers.php?action=filter_admins&status=${status}`);
    const json = await res.json();
    if (json.success) {
      const tbody = document.getElementById('adminsTableBody');
      tbody.innerHTML = '';
      json.data.forEach(adm => {
        const row = `<tr data-admin-id="${adm.id}">
          <td>${adm.full_name}</td>
          <td>${adm.email}</td>
          <td>-</td>
          <td><span class="badge ${adm.is_active ? 'badge-success' : 'badge-danger'}">${adm.is_active ? 'Active' : 'Inactive'}</span></td>
          <td>${adm.last_login_at ? new Date(adm.last_login_at).toLocaleString() : 'Never'}</td>
          <td>
            <button class="btn-icon edit-admin" data-id="${adm.id}" data-name="${adm.full_name}" data-email="${adm.email}" data-active="${adm.is_active}"><i class="bi bi-pencil"></i></button>
            <button class="btn-icon delete-admin" data-id="${adm.id}" data-name="${adm.full_name}"><i class="bi bi-trash"></i></button>
          </td>
        </tr>`;
        tbody.innerHTML += row;
      });
      document.getElementById('adminCount').textContent = json.data.length;
      reattachEventListeners();
    }
  } catch (err) {
    showToast('Filter error: ' + err.message, 'error');
  }
});

document.getElementById('resetFilterBtn').addEventListener('click', () => {
  document.getElementById('filterStatus').value = '';
  location.reload();
});

function reattachEventListeners() {
  document.querySelectorAll('.edit-admin').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('editAdminId').value = btn.dataset.id;
      document.getElementById('editAdminForm').querySelector('[name="full_name"]').value = btn.dataset.name;
      document.getElementById('editAdminForm').querySelector('[name="email"]').value = btn.dataset.email;
      document.getElementById('editAdminForm').querySelector('[name="is_active"]').value = btn.dataset.active;
      new bootstrap.Modal(document.getElementById('editAdminModal')).show();
    });
  });
  document.querySelectorAll('.delete-admin').forEach(btn => {
    btn.addEventListener('click', () => {
      if (confirm(`Delete admin "${btn.dataset.name}"?`)) {
        const fd = new FormData();
        fd.append('action', 'delete_admin');
        fd.append('id', btn.dataset.id);
        fetch('includes/api-handlers.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(json => {
            if (json.success) {
              showToast('Admin deleted');
              setTimeout(() => location.reload(), 1000);
            } else {
              showToast(json.message, 'error');
            }
          })
          .catch(err => showToast('Error: ' + err.message, 'error'));
      }
    });
  });
}
</script>
</body>
</html>
