<?php
require_once 'includes/session.php';
requireAuth();
$admin = getCurrentAdmin();
$roles = getAdminRoles($admin['id']);

// Get location ID from URL
$location_id = $_GET['id'] ?? null;
if (!$location_id) {
    header('Location: locations.php');
    exit;
}

// Fetch location details
$location = getLocationById($location_id);
if (!$location) {
    header('Location: locations.php');
    exit;
}

// Fetch households for this location
$households = getHouseholdsByLocation($location_id);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Flexnet – Households · <?php echo htmlspecialchars($location['name']); ?></title>
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
    .page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .page-header-main { display: flex; flex-direction: column; gap: 0.25rem; }
    .breadcrumb-line { font-size: 0.78rem; color: var(--text-muted); }
    .page-title { font-size: 1.2rem; font-weight: 700; }
    .page-subtitle { font-size: 0.85rem; color: var(--text-muted); }
    .page-header-meta { display: flex; gap: 0.5rem; margin-top: 0.3rem; flex-wrap: wrap; }
    .meta-chip { font-size: 0.74rem; border-radius: 999px; padding: 0.15rem 0.6rem; border: 1px solid #333; color: #ccc; }
    .meta-chip.accent { border-color: var(--flex-green); color: var(--flex-green); }
    .page-actions { display: flex; gap: 0.6rem; flex-wrap: wrap; }
    .btn-back { border-radius: 999px; padding: 0.4rem 0.9rem; border: 1px solid #333; background: #050505; color: #f2f2f2; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; }
    .btn-back:hover { border-color: var(--flex-green); color: var(--flex-green); }
    .btn-add { border-radius: 999px; padding: 0.45rem 1rem; border: none; background: var(--flex-green); color: #000; font-size: 0.86rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; }
    .btn-add:hover { background: #37f17a; }
    .btn-add:disabled, .btn-back:disabled { opacity: 0.5; cursor: not-allowed; }
    .card { background: #050505; border: 1px solid #222; border-radius: 14px; }
    .card-header { border-bottom: 1px solid #222; padding: 1rem; }
    .card-body { padding: 1rem; }
    .table-wrapper { max-height: 540px; overflow-y: auto; border-radius: 12px; border: 1px solid #202020; }
    table { margin-bottom: 0; }
    thead { position: sticky; top: 0; background: #000; z-index: 10; }
    th { border-bottom: 1px solid #222 !important; padding: 1rem !important; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #999 !important; }
    td { border-bottom: 1px solid #1a1a1a !important; padding: 0.9rem 1rem !important; font-size: 0.88rem; }
    tr:hover { background: #070707; }
    .badge { font-size: 0.75rem; padding: 0.4rem 0.65rem; font-weight: 600; }
    .badge-active { background: rgba(39,228,106,0.2); color: #27e46a; }
    .badge-pending { background: rgba(255,193,7,0.2); color: #ffc107; }
    .badge-expired { background: rgba(255,107,107,0.2); color: #ff6b6b; }
    .badge-paused { background: rgba(108,117,125,0.2); color: #b5b5b5; }
    .btn-icon { border: 1px solid #333; background: #050505; color: #fff; width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.8rem; padding: 0; }
    .btn-icon:hover { border-color: var(--flex-green); color: var(--flex-green); }
    .btn-icon:disabled { opacity: 0.5; cursor: not-allowed; }
    .modal-content { background: #050505 !important; color: #fff !important; border-radius: 14px !important; border: 1px solid #222 !important; }
    .modal-header { border-bottom: 1px solid #222 !important; }
    .modal-title { font-weight: 600; }
    .form-label { font-size: 0.82rem; color: #e0e0e0; }
    .form-control, .form-select, textarea { font-size: 0.85rem; background: #050505 !important; color: #fff !important; border: 1px solid #333 !important; border-radius: 10px; }
    .form-control:focus, .form-select:focus, textarea:focus { border-color: var(--flex-green) !important; box-shadow: 0 0 0 1px rgba(39,228,106,0.2) !important; outline: none; }
    .form-control::placeholder, textarea::placeholder { color: #777; }
    .btn-primary { background: var(--flex-green); color: #000; border: none; }
    .btn-primary:hover { background: #37f17a; }
    .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
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
      .page-actions { width: 100%; }
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
      <li class="admin-nav-item"><a href="locations.php" class="admin-nav-link active"><i class="bi bi-building"></i><span>Locations</span></a></li>
      <li class="admin-nav-item"><a href="payments.php" class="admin-nav-link"><i class="bi bi-cash-coin"></i><span>Payments</span></a></li>
      <li class="admin-nav-item"><a href="support.php" class="admin-nav-link"><i class="bi bi-life-preserver"></i><span>Support</span></a></li>
      <li class="admin-nav-item"><a href="faqs.php" class="admin-nav-link"><i class="bi bi-question-circle"></i><span>FAQs</span></a></li>
      <li class="admin-nav-item"><a href="admins.php" class="admin-nav-link"><i class="bi bi-people"></i><span>Admins &amp; Roles</span></a></li>
      <li class="admin-nav-item"><a href="logs.php" class="admin-nav-link"><i class="bi bi-activity"></i><span>System Logs</span></a></li>
      <li class="admin-nav-item"><a href="profile.php" class="admin-nav-link"><i class="bi bi-person-gear"></i><span>My Profile</span></a></li>
    </ul>
    <div class="admin-sidebar-footer">v1.0 · All admin actions are logged.</div>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <div class="topbar-left">
        <button class="topbar-hamburger" id="sidebarToggle" type="button"><i class="bi bi-list"></i></button>
        <div><div class="topbar-title">Households</div><div class="topbar-subtitle">Dashboard / Locations / <?php echo htmlspecialchars($location['name']); ?></div></div>
      </div>
      <div class="topbar-right">
        <div class="topbar-search"><input type="text" id="searchHouseholds" placeholder="Search households…"><i class="bi bi-search"></i></div>
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
        <div class="page-header-main">
          <div class="breadcrumb-line"><span>Home</span> / <span>Locations</span> / <span><?php echo htmlspecialchars($location['name']); ?></span></div>
          <div class="page-title"><?php echo htmlspecialchars($location['name']); ?></div>
          <div class="page-subtitle">Household view – manage subscribers, apartments and subscription status.</div>
          <div class="page-header-meta">
            <span class="meta-chip accent"><?php echo count($households); ?> household(s)</span>
            <span class="meta-chip"><?php echo htmlspecialchars($location['city'] ?? 'N/A'); ?></span>
          </div>
        </div>
        <div class="page-actions">
          <button class="btn-back" type="button" onclick="window.location.href='locations.php'"><i class="bi bi-arrow-left"></i> Back</button>
          <button class="btn-add" type="button" data-bs-toggle="modal" data-bs-target="#newHouseholdModal"><i class="bi bi-plus-lg"></i> New Household</button>
        </div>
      </div>
      <div class="card">
        <div class="card-header">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
              <div class="page-title">Households in this Location</div>
              <div class="page-subtitle">Apartment, subscriber details and subscription status.</div>
            </div>
            <div class="page-subtitle">Showing <span id="householdCount"><?php echo count($households); ?></span> household(s)</div>
          </div>
        </div>
        <div class="table-wrapper">
          <table class="table table-hover" style="color:#fff;">
            <thead>
              <tr>
                <th>Apartment</th>
                <th>Subscriber</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Email</th>
                <th style="width:100px;">Actions</th>
              </tr>
            </thead>
            <tbody id="householdsTableBody">
              <?php foreach ($households as $hh): ?>
                <tr data-household-id="<?php echo $hh['id']; ?>">
                  <td><?php echo htmlspecialchars($hh['apartment_label'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($hh['primary_full_name'] ?? 'Unassigned'); ?></td>
                  <td><?php echo htmlspecialchars($hh['primary_phone_number'] ?? '–'); ?></td>
                  <td>
                    <span class="badge badge-<?php echo $hh['subscription_status'] ?? 'paused'; ?>">
                      <?php echo ucfirst(str_replace('_', ' ', $hh['subscription_status'] ?? 'paused')); ?>
                    </span>
                  </td>
                  <td><?php echo htmlspecialchars($hh['primary_email'] ?? '–'); ?></td>
                  <td>
                    <button class="btn-icon view-household" data-id="<?php echo $hh['id']; ?>" title="View"><i class="bi bi-eye"></i></button>
                    <button class="btn-icon edit-household" data-id="<?php echo $hh['id']; ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                    <button class="btn-icon reset-pin" data-id="<?php echo $hh['id']; ?>" title="Reset PIN"><i class="bi bi-key"></i></button>
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

<!-- View Household Modal -->
<div class="modal fade" id="viewHouseholdModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Household Details</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Apartment</label><p id="viewApt" style="font-size:0.9rem;margin:0;"></p></div>
        <div class="mb-2"><label class="form-label">Subscriber Name</label><p id="viewName" style="font-size:0.9rem;margin:0;"></p></div>
        <div class="mb-2"><label class="form-label">Phone</label><p id="viewPhone" style="font-size:0.9rem;margin:0;"></p></div>
        <div class="mb-2"><label class="form-label">Email</label><p id="viewEmail" style="font-size:0.9rem;margin:0;"></p></div>
        <div class="mb-2"><label class="form-label">Preferred Language</label><p id="viewLang" style="font-size:0.9rem;margin:0;"></p></div>
        <div class="mb-2"><label class="form-label">Status</label><p id="viewStatus" style="font-size:0.9rem;margin:0;"></p></div>
        <div class="mb-0"><label class="form-label">Notes</label><p id="viewNotes" style="font-size:0.85rem;margin:0;color:#999;"></p></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Household Modal -->
<div class="modal fade" id="editHouseholdModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Edit Household</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="editHouseholdForm">
        <input type="hidden" name="id" id="editHouseholdId">
        <input type="hidden" name="action" value="update_household">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Apartment / Room *</label>
              <input type="text" class="form-control" name="apartment_label" id="editApt" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Status *</label>
              <select class="form-select" name="subscription_status" id="editStatus" required>
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="expired">Expired</option>
                <option value="paused">Paused</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Subscriber Name *</label>
              <input type="text" class="form-control" name="primary_full_name" id="editName" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone *</label>
              <input type="tel" class="form-control" name="primary_phone_number" id="editPhone" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="primary_email" id="editEmail">
            </div>
            <div class="col-md-6">
              <label class="form-label">Language</label>
              <select class="form-select" name="preferred_language" id="editLang">
                <option value="en">English</option>
                <option value="fr">Français</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea class="form-control" name="notes" id="editNotes" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm" id="editHouseholdBtn">Update Household</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- New Household Modal -->
<div class="modal fade" id="newHouseholdModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">New Household – <?php echo htmlspecialchars($location['name']); ?></h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="newHouseholdForm">
        <input type="hidden" name="action" value="create_household">
        <input type="hidden" name="location_id" value="<?php echo $location_id; ?>">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label">Subscriber full name *</label>
              <input type="text" class="form-control" name="primary_full_name" placeholder="e.g. Sunyin Elisbrown" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Phone number *</label>
              <input type="tel" class="form-control" name="primary_phone_number" placeholder="679 690 703" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Apartment / Room *</label>
              <input type="text" class="form-control" name="apartment_label" placeholder="Room 12" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email (optional)</label>
              <input type="email" class="form-control" name="primary_email" placeholder="subscriber@example.com">
            </div>
            <div class="col-md-6">
              <label class="form-label">Preferred language</label>
              <select class="form-select" name="preferred_language">
                <option value="en" selected>English</option>
                <option value="fr">Français</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Notes (optional)</label>
              <textarea class="form-control" name="notes" rows="2" placeholder="Extra info about this household"></textarea>
            </div>
          </div>
          <p style="font-size:0.78rem;color:#a5a5a5;margin-top:0.5rem;">A user account will be created with default PIN <strong>1234</strong>. The user will be forced to change this PIN on their first login.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm" id="createHouseholdBtn">Save Household</button>
        </div>
      </form>
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

// Create Household
document.getElementById('newHouseholdForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('createHouseholdBtn');
  disableButton(btn);
  const fd = new FormData(e.target);
  try {
    const res = await fetch('includes/api-handlers.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      showToast('Household created successfully');
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

// Edit Household
document.querySelectorAll('.edit-household').forEach(btn => {
  btn.addEventListener('click', async () => {
    disableButton(btn);
    const hh_id = btn.dataset.id;
    try {
      const row = document.querySelector(`tr[data-household-id="${hh_id}"]`);
      document.getElementById('editHouseholdId').value = hh_id;
      document.getElementById('editApt').value = row.cells[0].textContent.trim();
      document.getElementById('editName').value = row.cells[1].textContent.trim();
      document.getElementById('editPhone').value = row.cells[2].textContent.trim();
      document.getElementById('editEmail').value = row.cells[4].textContent.trim();
      const statusText = row.cells[3].textContent.trim().toLowerCase().replace(' ', '_');
      document.getElementById('editStatus').value = statusText;
      new bootstrap.Modal(document.getElementById('editHouseholdModal')).show();
    } catch (err) {
      showToast('Error: ' + err.message, 'error');
    } finally {
      enableButton(btn);
    }
  });
});

document.getElementById('editHouseholdForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('editHouseholdBtn');
  disableButton(btn);
  const fd = new FormData(e.target);
  try {
    const res = await fetch('includes/api-handlers.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      showToast('Household updated successfully');
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

// View Household
document.querySelectorAll('.view-household').forEach(btn => {
  btn.addEventListener('click', () => {
    window.location.href = 'household-detail.php?id=' + btn.dataset.id;
  });
});

// Reset PIN
document.querySelectorAll('.reset-pin').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!confirm('Reset PIN to 1234? User will be forced to change it on next login.')) return;
    disableButton(btn);
    const hh_id = btn.dataset.id;
    try {
      const fd = new FormData();
      fd.append('action', 'reset_household_pin');
      fd.append('id', hh_id);
      const res = await fetch('includes/api-handlers.php', { method: 'POST', body: fd });
      const json = await res.json();
      if (json.success) {
        showToast(json.message);
      } else {
        showToast(json.message, 'error');
      }
    } catch (err) {
      showToast('Error: ' + err.message, 'error');
    } finally {
      enableButton(btn);
    }
  });
});

// Search Households
document.getElementById('searchHouseholds').addEventListener('input', async (e) => {
  const q = e.target.value.trim();
  if (!q) {
    location.reload();
    return;
  }
  try {
    const res = await fetch(`includes/api-handlers.php?action=search_households&q=${encodeURIComponent(q)}&location_id=<?php echo $location_id; ?>`);
    const json = await res.json();
    if (json.success) {
      const tbody = document.getElementById('householdsTableBody');
      tbody.innerHTML = '';
      json.data.forEach(hh => {
        const statusClass = 'badge-' + (hh.subscription_status || 'paused');
        const row = `<tr data-household-id="${hh.id}">
          <td>${hh.apartment_label || 'N/A'}</td>
          <td>${hh.primary_full_name || 'Unassigned'}</td>
          <td>${hh.primary_phone_number || '–'}</td>
          <td><span class="badge ${statusClass}">${(hh.subscription_status || 'paused').replace('_', ' ')}</span></td>
          <td>${hh.primary_email || '–'}</td>
          <td>
            <button class="btn-icon view-household" data-id="${hh.id}"><i class="bi bi-eye"></i></button>
            <button class="btn-icon edit-household" data-id="${hh.id}"><i class="bi bi-pencil"></i></button>
            <button class="btn-icon reset-pin" data-id="${hh.id}"><i class="bi bi-key"></i></button>
          </td>
        </tr>`;
        tbody.innerHTML += row;
      });
      document.getElementById('householdCount').textContent = json.data.length;
      reattachEventListeners();
    }
  } catch (err) {
    showToast('Search error: ' + err.message, 'error');
  }
});

function reattachEventListeners() {
  document.querySelectorAll('.view-household').forEach(btn => {
    btn.addEventListener('click', () => {
      window.location.href = 'household-detail.php?id=' + btn.dataset.id;
    });
  });
  
  document.querySelectorAll('.edit-household').forEach(btn => {
    btn.addEventListener('click', async () => {
      disableButton(btn);
      const hh_id = btn.dataset.id;
      try {
        const row = document.querySelector(`tr[data-household-id="${hh_id}"]`);
        document.getElementById('editHouseholdId').value = hh_id;
        document.getElementById('editApt').value = row.cells[0].textContent.trim();
        document.getElementById('editName').value = row.cells[1].textContent.trim();
        document.getElementById('editPhone').value = row.cells[2].textContent.trim();
        document.getElementById('editEmail').value = row.cells[4].textContent.trim();
        const statusText = row.cells[3].textContent.trim().toLowerCase().replace(' ', '_');
        document.getElementById('editStatus').value = statusText;
        new bootstrap.Modal(document.getElementById('editHouseholdModal')).show();
      } catch (err) {
        showToast('Error: ' + err.message, 'error');
      } finally {
        enableButton(btn);
      }
    });
  });
  
  document.querySelectorAll('.reset-pin').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('Reset PIN to 1234? User will be forced to change it on next login.')) return;
      disableButton(btn);
      const hh_id = btn.dataset.id;
      try {
        const fd = new FormData();
        fd.append('action', 'reset_household_pin');
        fd.append('id', hh_id);
        const res = await fetch('includes/api-handlers.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
          showToast(json.message);
        } else {
          showToast(json.message, 'error');
        }
      } catch (err) {
        showToast('Error: ' + err.message, 'error');
      } finally {
        enableButton(btn);
      }
    });
  });
}
</script>
</body>
</html>
