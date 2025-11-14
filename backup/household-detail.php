<?php
require_once 'includes/session.php';
requireAuth();
$admin = getCurrentAdmin();
$roles = getAdminRoles($admin['id']);

// Get household ID from URL
$household_id = $_GET['id'] ?? null;
if (!$household_id) {
    header('Location: location-households.php');
    exit;
}

// Fetch household details
$household = getHouseholdById($household_id);
if (!$household) {
    header('Location: location-households.php');
    exit;
}

// Fetch location details for breadcrumb
$location = getLocationById($household['location_id']);

// Fetch household user details
$user = getHouseholdUser($household_id);

// Fetch subscription history
$subscriptions = fetchAll("SELECT * FROM subscriptions WHERE household_id = ? ORDER BY created_at DESC", [$household_id], 'i') ?? [];

// Fetch payments for this household
$payments = fetchAll("SELECT * FROM payments WHERE household_id = ? ORDER BY created_at DESC", [$household_id], 'i') ?? [];

// Fetch support tickets for this household
$tickets = fetchAll("SELECT * FROM support_tickets WHERE household_id = ? ORDER BY created_at DESC", [$household_id], 'i') ?? [];

// Fetch audit logs for this household (table may not exist yet)
$audit_logs = [];
// $audit_logs = fetchAll("SELECT * FROM audit_logs WHERE household_id = ? ORDER BY created_at DESC", [$household_id], 'i') ?? [];

// Get current subscription status
$current_sub = fetchOne("SELECT * FROM subscriptions WHERE household_id = ? AND status = 'active' ORDER BY end_date DESC LIMIT 1", [$household_id], 'i');

$subscription_status = $household['subscription_status'] ?? 'pending';
$subscription_expires = $current_sub ? $current_sub['end_date'] : null;
$subscription_channel = $current_sub ? $current_sub['payment_channel'] : 'N/A';
$subscription_start = $current_sub ? $current_sub['start_date'] : null;
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Flexnet – Household Detail · <?php echo htmlspecialchars($household['primary_full_name']); ?></title>
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
    .topbar-admin-pill { display: flex; align-items: center; gap: 0.55rem; padding: 0.25rem 0.6rem 0.25rem 0.25rem; border-radius: 999px; background: #050505; border: 1px solid #222; text-decoration: none; color: inherit; cursor: pointer; }
    .topbar-admin-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--flex-green); display: flex; align-items: center; justify-content: center; color: #000; font-weight: 700; font-size: 0.9rem; }
    .topbar-admin-name { font-size: 0.82rem; font-weight: 600; }
    .topbar-admin-role { font-size: 0.72rem; color: var(--text-muted); }
    .admin-content { padding: 1.4rem 1.7rem 2rem; max-width: 1440px; width: 100%; overflow-y: auto; flex: 1; }
    .page-header-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .page-header-main { display: flex; flex-direction: column; gap: 0.25rem; }
    .breadcrumb-line { font-size: 0.78rem; color: var(--text-muted); }
    .page-title { font-size: 1.2rem; font-weight: 700; }
    .page-subtitle { font-size: 0.85rem; color: var(--text-muted); }
    .page-header-meta { display: flex; gap: 0.5rem; margin-top: 0.3rem; flex-wrap: wrap; }
    .meta-chip { font-size: 0.74rem; border-radius: 999px; padding: 0.15rem 0.6rem; border: 1px solid #333; color: #ccc; }
    .meta-chip.accent { border-color: var(--flex-green); color: var(--flex-green); }
    .status-active { border-color: var(--flex-green); color: var(--flex-green); }
    .page-header-actions { display: flex; gap: 0.6rem; flex-wrap: wrap; }
    .btn-outline-back { border-radius: 999px; padding: 0.4rem 0.9rem; border: 1px solid #333; background: #050505; color: #f2f2f2; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; }
    .btn-outline-back:hover { border-color: var(--flex-green); color: var(--flex-green); }
    .btn-reset-pin { border-radius: 999px; padding: 0.4rem 0.9rem; border: none; background: #ff6b6b; color: #fff; font-size: 0.82rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; }
    .btn-reset-pin:hover { background: #ff5252; }
    .content-card { background: #050505; border: 1px solid #222; border-radius: 14px; padding: 1.2rem; }
    .nav-tabs { border-bottom: 1px solid #222; }
    .nav-tabs .nav-link { color: #ccc; border: none; padding: 0.6rem 1rem; font-size: 0.88rem; border-bottom: 2px solid transparent; }
    .nav-tabs .nav-link:hover { border-bottom-color: #333; color: #fff; }
    .nav-tabs .nav-link.active { border-bottom-color: var(--flex-green); color: var(--flex-green); background: transparent; }
    .tab-content { padding: 1.2rem 0; }
    .tab-pane { display: none; opacity: 0; transition: opacity 0.15s linear; }
    .tab-pane.active { display: block; opacity: 1; }
    .overview-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
    .overview-card { background: #0a0a0a; border: 1px solid #1a1a1a; border-radius: 10px; padding: 1rem; }
    .overview-title { font-weight: 600; font-size: 0.95rem; margin-bottom: 0.8rem; }
    .overview-row { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #1a1a1a; }
    .overview-row:last-child { border-bottom: none; }
    .overview-label { color: var(--text-muted); font-size: 0.82rem; }
    .overview-value { font-weight: 500; }
    .quick-actions-group { display: flex; flex-direction: column; gap: 0.5rem; }
    .quick-action-btn { background: #1a1a1a; border: 1px solid #222; color: #fff; padding: 0.5rem 0.8rem; border-radius: 6px; font-size: 0.82rem; cursor: pointer; display: flex; align-items: center; gap: 0.4rem; width: 100%; }
    .quick-action-btn:hover { background: #222; }
    .subscription-actions { display: flex; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .btn-sub-action { border: 1px solid #333; background: transparent; color: #fff; padding: 0.5rem 0.9rem; border-radius: 8px; font-size: 0.82rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.3rem; }
    .btn-sub-action:hover { border-color: var(--flex-green); color: var(--flex-green); }
    .btn-sub-action.primary { background: var(--flex-green); color: #000; border: none; }
    .btn-sub-action.primary:hover { background: #37f17a; }
    .sub-summary { font-size: 0.88rem; color: #ccc; margin: 0.8rem 0; padding: 0.8rem; background: #0a0a0a; border-radius: 8px; border-left: 3px solid var(--flex-green); }
    .sub-history { margin-top: 1.5rem; }
    .sub-history-item { display: flex; gap: 1rem; margin-bottom: 1rem; }
    .sub-history-dot { width: 12px; height: 12px; border-radius: 50%; background: var(--flex-green); margin-top: 0.2rem; flex-shrink: 0; }
    .sub-history-meta { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem; }
    .table-wrapper { max-height: 540px; overflow-y: auto; border-radius: 12px; border: 1px solid #202020; }
    table { margin-bottom: 0; }
    thead { position: sticky; top: 0; background: #000; z-index: 10; }
    th { border-bottom: 1px solid #222 !important; padding: 1rem !important; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #999 !important; }
    td { border-bottom: 1px solid #1a1a1a !important; padding: 0.9rem 1rem !important; font-size: 0.88rem; }
    tr:hover { background: #070707; }
    .badge-status { display: inline-block; font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 4px; font-weight: 600; }
    .badge-status.success { background: rgba(39,228,106,0.2); color: #27e46a; }
    .badge-status.pending { background: rgba(255,193,7,0.2); color: #ffc107; }
    .badge-status.failed { background: rgba(255,107,107,0.2); color: #ff6b6b; }
    .btn-icon { border: 1px solid #333; background: #050505; color: #fff; width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.8rem; padding: 0; }
    .btn-icon:hover { border-color: var(--flex-green); color: var(--flex-green); }
    .btn-icon:disabled { opacity: 0.5; cursor: not-allowed; }
    .table-actions { display: flex; gap: 0.4rem; }
    .support-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
    .support-list { display: flex; flex-direction: column; gap: 0.8rem; }
    .ticket-item { background: #0a0a0a; border: 1px solid #1a1a1a; border-radius: 8px; padding: 1rem; }
    .ticket-subject { font-weight: 500; }
    .ticket-status-chip { font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600; }
    .ticket-status-chip.open { background: rgba(39,228,106,0.2); color: var(--flex-green); }
    .ticket-status-chip.closed { background: rgba(108,117,125,0.2); color: #b5b5b5; }
    .ticket-meta { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem; }
    .support-compose { display: flex; flex-direction: column; gap: 0.8rem; }
    .support-compose textarea { background: #0a0a0a; border: 1px solid #222; color: #fff; border-radius: 8px; padding: 0.8rem; min-height: 150px; font-family: inherit; }
    .btn-send-reply { background: var(--flex-green); color: #000; border: none; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer; }
    .btn-send-reply:hover { background: #37f17a; }
    .audit-footnote { font-size: 0.82rem; color: var(--text-muted); }
    .modal-content { background: #050505 !important; color: #fff !important; border-radius: 14px !important; border: 1px solid #222 !important; }
    .modal-header { border-bottom: 1px solid #222 !important; }
    .modal-body { padding: 1.5rem; }
    .modal-footer { border-top: 1px solid #222 !important; }
    .btn-close-white { filter: brightness(0) invert(1); }
    .form-control, .form-select { background: #0a0a0a !important; border: 1px solid #222 !important; color: #fff !important; border-radius: 8px; }
    .form-control::placeholder { color: #666; }
    .form-label { font-size: 0.88rem; margin-bottom: 0.4rem; }
    .btn-primary-compact { background: var(--flex-green); color: #000; border: none; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer; }
    .btn-danger-compact { background: #ff6b6b; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer; }
    .toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 9999; }
    .toast { background: #0a0a0a; border: 1px solid #222; color: #fff; border-radius: 8px; padding: 1rem; margin-bottom: 0.5rem; min-width: 300px; }
    .toast.success { border-left: 3px solid var(--flex-green); }
    .toast.error { border-left: 3px solid #ff6b6b; }
  </style>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-sidebar-header">
      <div class="admin-sidebar-logo-circle">
        <img src="../flexnet-logo.svg" alt="Flexnet">
      </div>
      <div>
        <div class="admin-sidebar-title">Flexnet</div>
        <div class="admin-sidebar-sub">Admin Panel</div>
      </div>
    </div>

    <ul class="admin-nav">
      <li class="admin-nav-item">
        <a href="dashboard.php" class="admin-nav-link">
          <i class="bi bi-speedometer2"></i> Dashboard
        </a>
      </li>
      <li class="admin-nav-item">
        <a href="locations.php" class="admin-nav-link">
          <i class="bi bi-map"></i> Locations
        </a>
      </li>
      <li class="admin-nav-item">
        <a href="admins.php" class="admin-nav-link">
          <i class="bi bi-people"></i> Admins
        </a>
      </li>
      <li class="admin-nav-item">
        <a href="payments.php" class="admin-nav-link">
          <i class="bi bi-credit-card"></i> Payments
        </a>
      </li>
      <li class="admin-nav-item">
        <a href="logs.php" class="admin-nav-link">
          <i class="bi bi-clock-history"></i> Logs
        </a>
      </li>
      <li class="admin-nav-item">
        <a href="support.php" class="admin-nav-link">
          <i class="bi bi-chat-dots"></i> Support
        </a>
      </li>
      <li class="admin-nav-item">
        <a href="faqs.php" class="admin-nav-link">
          <i class="bi bi-question-circle"></i> FAQs
        </a>
      </li>
    </ul>

    <div class="admin-sidebar-footer">
      <?php echo htmlspecialchars($admin['name']); ?> · <a href="logout.php" style="color: var(--flex-green); text-decoration: none;">Logout</a>
    </div>
  </aside>

  <div class="admin-main">
    <header class="admin-topbar">
      <div class="topbar-left">
        <button class="topbar-hamburger" id="sidebarToggle">
          <i class="bi bi-list"></i>
        </button>
        <div>
          <div class="topbar-title">Flexnet</div>
          <div class="topbar-subtitle">Household Management</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="dropdown">
          <a href="#" class="topbar-admin-pill dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="topbar-admin-avatar">
              <?php echo strtoupper(substr($admin['name'], 0, 1)); ?>
            </div>
            <div class="topbar-admin-meta">
              <span class="topbar-admin-name"><?php echo htmlspecialchars($admin['name']); ?></span>
              <span class="topbar-admin-role">System Admin</span>
            </div>
            <i class="bi bi-chevron-down" style="font-size: 0.8rem; color:#777;"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-gear"></i> My Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item logout-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
          </ul>
        </div>
      </div>
    </header>

    <main class="admin-content">
      <div class="page-header-row">
        <div class="page-header-main">
          <div class="breadcrumb-line">
            Home / Locations / 
            <a href="locations.php" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($location['name']); ?></a> / 
            <a href="location-households.php?id=<?php echo $household['location_id']; ?>" style="color: inherit; text-decoration: none;">Households</a> / 
            <?php echo htmlspecialchars($household['primary_full_name']); ?>
          </div>
          <div class="page-title"><?php echo htmlspecialchars($household['primary_full_name']); ?></div>
          <div class="page-subtitle">
            <?php echo htmlspecialchars($household['apartment_label']); ?> · 
            <?php echo htmlspecialchars($location['name']); ?>
          </div>
          <div class="page-header-meta">
            <span class="meta-chip status-active">Subscription: <?php echo ucfirst($subscription_status); ?></span>
            <?php if ($subscription_expires): ?>
              <span class="meta-chip">Expires: <?php echo date('d M Y', strtotime($subscription_expires)); ?></span>
            <?php endif; ?>
            <span class="meta-chip accent"><?php echo htmlspecialchars($subscription_channel); ?></span>
            <span class="meta-chip">Phone: <?php echo htmlspecialchars($household['primary_phone_number']); ?></span>
          </div>
        </div>

        <div class="page-header-actions">
          <button class="btn-outline-back" type="button" onclick="window.history.back()">
            <i class="bi bi-arrow-left"></i> Back
          </button>
          <button class="btn-reset-pin" type="button" data-bs-toggle="modal" data-bs-target="#resetPinModal">
            <i class="bi bi-key"></i> Reset PIN to 1234
          </button>
        </div>
      </div>

      <section class="content-card">
        <ul class="nav nav-tabs" id="householdTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab"
                    data-bs-target="#overview" type="button" role="tab"
                    aria-controls="overview" aria-selected="true">
              Overview
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="subscription-tab" data-bs-toggle="tab"
                    data-bs-target="#subscription" type="button" role="tab"
                    aria-controls="subscription" aria-selected="false">
              Subscription
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="payments-tab" data-bs-toggle="tab"
                    data-bs-target="#payments" type="button" role="tab"
                    aria-controls="payments" aria-selected="false">
              Payments
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="support-tab" data-bs-toggle="tab"
                    data-bs-target="#support" type="button" role="tab"
                    aria-controls="support" aria-selected="false">
              Support
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="audit-tab" data-bs-toggle="tab"
                    data-bs-target="#audit" type="button" role="tab"
                    aria-controls="audit" aria-selected="false">
              Audit
            </button>
          </li>
        </ul>

        <div class="tab-content">
          <!-- OVERVIEW TAB -->
          <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
            <div class="overview-grid">
              <div class="overview-card">
                <div class="overview-title">Subscriber</div>
                <div class="overview-row">
                  <span class="overview-label">Full name</span>
                  <span class="overview-value"><?php echo htmlspecialchars($household['primary_full_name']); ?></span>
                </div>
                <div class="overview-row">
                  <span class="overview-label">Phone</span>
                  <span class="overview-value"><?php echo htmlspecialchars($household['primary_phone_number']); ?></span>
                </div>
                <div class="overview-row">
                  <span class="overview-label">Email</span>
                  <span class="overview-value"><?php echo htmlspecialchars($household['primary_email']); ?></span>
                </div>
                <div class="overview-row">
                  <span class="overview-label">Preferred language</span>
                  <span class="overview-value"><?php echo htmlspecialchars($household['preferred_language'] ?? 'English'); ?></span>
                </div>
              </div>

              <div class="overview-card">
                <div class="overview-title">Location & Access</div>
                <div class="overview-row">
                  <span class="overview-label">Apartment</span>
                  <span class="overview-value"><?php echo htmlspecialchars($household['apartment_label']); ?></span>
                </div>
                <div class="overview-row">
                  <span class="overview-label">Building</span>
                  <span class="overview-value"><?php echo htmlspecialchars($location['name']); ?></span>
                </div>
                <div class="overview-row">
                  <span class="overview-label">City</span>
                  <span class="overview-value"><?php echo htmlspecialchars($location['code'] ?? 'N/A'); ?></span>
                </div>
                <div class="overview-row">
                  <span class="overview-label">Current status</span>
                  <span class="overview-value"><?php echo ucfirst($subscription_status); ?> subscription</span>
                </div>
                <div class="overview-title" style="margin-top:0.8rem;">Quick actions</div>
                <div class="quick-actions-group">
                  <button class="quick-action-btn" onclick="alert('Call feature coming soon')">
                    <i class="bi bi-telephone"></i> Call subscriber
                  </button>
                  <button class="quick-action-btn" onclick="alert('Email feature coming soon')">
                    <i class="bi bi-envelope"></i> Send email
                  </button>
                  <button class="quick-action-btn" onclick="document.getElementById('supportType').value='new'; document.getElementById('support-tab').click();">
                    <i class="bi bi-chat-dots"></i> New support ticket
                  </button>
                  <button class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#subscriptionActionModal" data-action="renew">
                    <i class="bi bi-arrow-repeat"></i> Renew subscription
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- SUBSCRIPTION TAB -->
          <div class="tab-pane fade" id="subscription" role="tabpanel" aria-labelledby="subscription-tab">
            <div class="subscription-actions">
              <button class="btn-sub-action primary" data-bs-toggle="modal" data-bs-target="#subscriptionActionModal" data-action="activate">
                <i class="bi bi-play-circle"></i> Activate
              </button>
              <button class="btn-sub-action" data-bs-toggle="modal" data-bs-target="#subscriptionActionModal" data-action="renew">
                <i class="bi bi-arrow-clockwise"></i> Renew
              </button>
              <button class="btn-sub-action" data-bs-toggle="modal" data-bs-target="#subscriptionActionModal" data-action="pause">
                <i class="bi bi-pause-circle"></i> Pause
              </button>
              <button class="btn-sub-action" data-bs-toggle="modal" data-bs-target="#subscriptionActionModal" data-action="extend">
                <i class="bi bi-plus-circle"></i> Extend
              </button>
            </div>

            <div class="sub-summary">
              Current status: <strong><?php echo ucfirst($subscription_status); ?></strong>
              <?php if ($subscription_start && $subscription_expires): ?>
                · From <strong><?php echo date('d M Y', strtotime($subscription_start)); ?></strong> to
                <strong><?php echo date('d M Y', strtotime($subscription_expires)); ?></strong>
              <?php endif; ?>
              · Channel: <strong><?php echo htmlspecialchars($subscription_channel); ?></strong>
            </div>

            <div class="sub-history">
              <div class="overview-title">History</div>
              <?php if (!empty($subscriptions)): ?>
                <?php foreach ($subscriptions as $sub): ?>
                  <div class="sub-history-item">
                    <div class="sub-history-dot"></div>
                    <div>
                      <div><strong><?php echo ucfirst($sub['status'] ?? 'Updated'); ?></strong> – <?php echo number_format($sub['amount'] ?? 0, 0); ?> <?php echo htmlspecialchars($sub['currency'] ?? 'XAF'); ?> · <?php echo htmlspecialchars($sub['payment_channel'] ?? 'N/A'); ?></div>
                      <div class="sub-history-meta">
                        <?php echo date('d M Y', strtotime($sub['created_at'])); ?> · Period: <?php echo date('d M', strtotime($sub['start_date'])); ?> – <?php echo date('d M Y', strtotime($sub['end_date'])); ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p style="color: var(--text-muted);">No subscription history yet.</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- PAYMENTS TAB -->
          <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">
            <div class="overview-title">Payment History</div>
            <p class="sub-summary">
              All payments associated with this household. Use verify / reject for pending mobile money entries.
            </p>

            <?php if (!empty($payments)): ?>
              <div class="table-wrapper">
                <table class="table-dark-custom">
                  <thead>
                  <tr>
                    <th>Date</th>
                    <th>Channel</th>
                    <th>Amount</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th style="width: 110px;">Actions</th>
                  </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($payments as $payment): ?>
                    <tr>
                      <td><?php echo date('Y-m-d H:i', strtotime($payment['created_at'])); ?></td>
                      <td><?php echo htmlspecialchars($payment['payment_channel'] ?? 'N/A'); ?></td>
                      <td><?php echo number_format($payment['amount'] ?? 0, 0); ?> <?php echo htmlspecialchars($payment['currency'] ?? 'XAF'); ?></td>
                      <td><?php echo htmlspecialchars($payment['reference'] ?? 'N/A'); ?></td>
                      <td><span class="badge-status <?php echo $payment['status']; ?>"><?php echo ucfirst($payment['status'] ?? 'Pending'); ?></span></td>
                      <td>
                        <div class="table-actions">
                          <?php if ($payment['status'] === 'pending'): ?>
                            <button class="btn-icon" title="Verify" data-bs-toggle="modal" data-bs-target="#paymentDecisionModal" data-decision="verify" data-payment-id="<?php echo $payment['id']; ?>">
                              <i class="bi bi-check2"></i>
                            </button>
                            <button class="btn-icon" title="Reject" data-bs-toggle="modal" data-bs-target="#paymentDecisionModal" data-decision="reject" data-payment-id="<?php echo $payment['id']; ?>">
                              <i class="bi bi-x"></i>
                            </button>
                          <?php else: ?>
                            <button class="btn-icon" title="View details" onclick="alert('Payment #<?php echo htmlspecialchars($payment['id']); ?>')">
                              <i class="bi bi-eye"></i>
                            </button>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p style="color: var(--text-muted);">No payments recorded yet.</p>
            <?php endif; ?>
          </div>

          <!-- SUPPORT TAB -->
          <div class="tab-pane fade" id="support" role="tabpanel" aria-labelledby="support-tab">
            <div class="support-layout">
              <div>
                <div class="overview-title">Tickets for this household</div>
                <?php if (!empty($tickets)): ?>
                  <div class="support-list">
                    <?php foreach ($tickets as $ticket): ?>
                      <div class="ticket-item">
                        <div class="d-flex justify-content-between align-items-center">
                          <span class="ticket-subject"><?php echo htmlspecialchars($ticket['subject'] ?? 'N/A'); ?></span>
                          <span class="ticket-status-chip <?php echo strtolower($ticket['status'] ?? 'open'); ?>"><?php echo ucfirst($ticket['status'] ?? 'Open'); ?></span>
                        </div>
                        <div class="ticket-meta">
                          #<?php echo $ticket['id']; ?> · Last update <?php echo date('M d, Y', strtotime($ticket['updated_at'] ?? $ticket['created_at'])); ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <p style="color: var(--text-muted);">No support tickets yet.</p>
                <?php endif; ?>
              </div>

              <div>
                <div class="overview-title">Quick reply / new ticket</div>
                <div class="support-compose">
                  <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="supportType">
                      <option value="reply">Reply on existing ticket</option>
                      <option value="new">Open new ticket</option>
                    </select>
                    <select class="form-select form-select-sm" id="supportStatus">
                      <option value="open">Set status: Open</option>
                      <option value="pending">Set status: Pending</option>
                      <option value="closed">Set status: Closed</option>
                    </select>
                  </div>
                  <textarea id="supportMessage" placeholder="Type your reply or new ticket description…"></textarea>
                  <button class="btn-send-reply" type="button" id="sendSupportBtn">
                    Send
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- AUDIT TAB -->
          <div class="tab-pane fade" id="audit" role="tabpanel" aria-labelledby="audit-tab">
            <p class="audit-footnote">
              All admin actions on this household, including subscription changes, payment decisions and PIN resets.
            </p>
            <?php if (!empty($audit_logs)): ?>
              <div class="table-wrapper">
                <table class="table-dark-custom">
                  <thead>
                  <tr>
                    <th>When</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Details</th>
                  </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($audit_logs as $log): ?>
                    <tr>
                      <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                      <td><?php echo htmlspecialchars($log['actor'] ?? 'System'); ?></td>
                      <td><?php echo htmlspecialchars($log['action'] ?? 'N/A'); ?></td>
                      <td><?php echo htmlspecialchars($log['entity_type'] ?? 'N/A'); ?></td>
                      <td><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p style="color: var(--text-muted);">No audit logs yet.</p>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </main>
  </div>
</div>

<!-- Subscription Action Modal -->
<div class="modal fade modal-content" id="subscriptionActionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="subActionTitle">Subscription Action</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="subscriptionActionForm">
        <div class="modal-body">
          <p class="sub-summary" id="subActionDescription">
            Configure the subscription dates and details for this household.
          </p>

          <div class="row g-3" id="subActionDatesRow">
            <div class="col-md-6">
              <label class="form-label" for="subStartDate">Start date</label>
              <input type="date" class="form-control" id="subStartDate" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="subEndDate">End date</label>
              <input type="date" class="form-control" id="subEndDate" required>
            </div>
          </div>

          <div class="row g-3 mt-1" id="subPauseFields" style="display:none;">
            <div class="col-md-6">
              <label class="form-label" for="pauseReason">Pause reason</label>
              <input type="text" class="form-control" id="pauseReason" placeholder="e.g. travelling, maintenance">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="pauseNote">Internal note (optional)</label>
              <input type="text" class="form-control" id="pauseNote" placeholder="Visible only to admins">
            </div>
          </div>

          <input type="hidden" id="subActionType" value="activate">
          <input type="hidden" id="subHouseholdId" value="<?php echo $household_id; ?>">

          <p class="modal-footnote mb-0" style="font-size: 0.78rem; color: var(--text-muted); margin-top: 1rem;">
            This action will be logged in the audit trail with your admin identity and timestamp.
          </p>
        </div>
        <div class="modal-footer border-0 d-flex justify-content-between">
          <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal">
            Cancel
          </button>
          <button type="submit" class="btn-primary-compact" id="subSubmitBtn">
            Confirm action
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Payment Decision Modal -->
<div class="modal fade modal-content" id="paymentDecisionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentDecisionTitle">Payment decision</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="paymentDecisionForm">
        <div class="modal-body">
          <p class="sub-summary" id="paymentDecisionText">
            Confirm how you want to handle this pending payment.
          </p>
          <div class="mb-3">
            <label class="form-label" for="paymentNote">Internal note (optional)</label>
            <textarea class="form-control" id="paymentNote" rows="2" placeholder="e.g. Confirmed on MTN portal, customer notified."></textarea>
          </div>
          <input type="hidden" id="paymentDecisionType" value="verify">
          <input type="hidden" id="paymentId" value="">
        </div>
        <div class="modal-footer border-0 justify-content-between">
          <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal">
            Cancel
          </button>
          <button type="submit" class="btn-primary-compact" id="paymentDecisionSubmitBtn">
            Confirm
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reset PIN Modal -->
<div class="modal fade modal-content" id="resetPinModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title text-danger">Reset PIN to 1234</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>
          You are about to reset this subscriber's login PIN back to
          <strong>1234</strong>. On their next login, they will be forced to
          change it.
        </p>
        <p class="audit-footnote mb-0">
          This action will be recorded in the audit trail. Make sure you have
          a valid reason for this reset.
        </p>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="button" class="btn btn-danger-compact" id="confirmResetPinBtn">
          Reset PIN
        </button>
      </div>
    </div>
  </div>
</div>

<div class="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const householdId = <?php echo $household_id; ?>;

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

// Subscription action modal logic
const subModalEl = document.getElementById('subscriptionActionModal');
if (subModalEl) {
  subModalEl.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    if (!button) return;
    const action = button.getAttribute('data-action') || 'activate';
    const titleEl = document.getElementById('subActionTitle');
    const descEl = document.getElementById('subActionDescription');
    const pauseFieldsEl = document.getElementById('subPauseFields');
    const actionTypeInput = document.getElementById('subActionType');

    actionTypeInput.value = action;

    if (action === 'activate') {
      titleEl.textContent = 'Activate subscription';
      descEl.textContent = 'Set the start and end date for the new active subscription.';
      pauseFieldsEl.style.display = 'none';
    } else if (action === 'renew') {
      titleEl.textContent = 'Renew subscription';
      descEl.textContent = 'Extend the subscription by setting a new period.';
      pauseFieldsEl.style.display = 'none';
    } else if (action === 'pause') {
      titleEl.textContent = 'Pause subscription';
      descEl.textContent = 'Temporarily pause the subscription and provide a reason.';
      pauseFieldsEl.style.display = 'grid';
    } else if (action === 'extend') {
      titleEl.textContent = 'Extend subscription';
      descEl.textContent = 'Adjust the end date to extend this subscription period.';
      pauseFieldsEl.style.display = 'none';
    }
  });

  document.getElementById('subscriptionActionForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = document.getElementById('subSubmitBtn');
    disableButton(btn);

    const payload = {
      action: 'subscription_action',
      household_id: householdId,
      sub_action: document.getElementById('subActionType').value,
      start_date: document.getElementById('subStartDate').value,
      end_date: document.getElementById('subEndDate').value,
      pause_reason: document.getElementById('pauseReason').value.trim(),
      pause_note: document.getElementById('pauseNote').value.trim()
    };

    fetch('includes/api-handlers.php', { method: 'POST', body: new URLSearchParams(payload) })
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          showToast('Subscription updated successfully');
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast(json.message || 'Error updating subscription', 'error');
          enableButton(btn);
        }
      })
      .catch(err => {
        showToast('Error: ' + err.message, 'error');
        enableButton(btn);
      });
  });
}

// Payment decision modal logic
const paymentModalEl = document.getElementById('paymentDecisionModal');
if (paymentModalEl) {
  paymentModalEl.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    if (!button) return;
    const decision = button.getAttribute('data-decision') || 'verify';
    const paymentId = button.getAttribute('data-payment-id') || '';
    const titleEl = document.getElementById('paymentDecisionTitle');
    const textEl = document.getElementById('paymentDecisionText');
    const typeInput = document.getElementById('paymentDecisionType');
    const submitBtn = document.getElementById('paymentDecisionSubmitBtn');
    const paymentIdInput = document.getElementById('paymentId');

    paymentIdInput.value = paymentId;
    typeInput.value = decision;
    if (decision === 'verify') {
      titleEl.textContent = 'Verify payment';
      textEl.textContent = 'Mark this payment as successfully received and apply the subscription if relevant.';
      submitBtn.textContent = 'Verify payment';
      submitBtn.classList.remove('btn-danger-compact');
      submitBtn.classList.add('btn-primary-compact');
    } else {
      titleEl.textContent = 'Reject payment';
      textEl.textContent = 'Mark this payment as rejected / failed. The subscription will not be activated.';
      submitBtn.textContent = 'Reject payment';
      submitBtn.classList.remove('btn-primary-compact');
      submitBtn.classList.add('btn-danger-compact');
    }
  });

  document.getElementById('paymentDecisionForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = document.getElementById('paymentDecisionSubmitBtn');
    disableButton(btn);

    const payload = {
      action: 'payment_decision',
      household_id: householdId,
      payment_id: document.getElementById('paymentId').value,
      decision: document.getElementById('paymentDecisionType').value,
      note: document.getElementById('paymentNote').value.trim()
    };

    fetch('includes/api-handlers.php', { method: 'POST', body: new URLSearchParams(payload) })
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          showToast('Payment decision recorded');
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast(json.message || 'Error processing payment', 'error');
          enableButton(btn);
        }
      })
      .catch(err => {
        showToast('Error: ' + err.message, 'error');
        enableButton(btn);
      });
  });
}

// Support quick reply / new ticket
document.getElementById('sendSupportBtn').addEventListener('click', function () {
  const btn = this;
  disableButton(btn);

  const mode = document.getElementById('supportType').value;
  const status = document.getElementById('supportStatus').value;
  const message = document.getElementById('supportMessage').value.trim();

  if (!message) {
    showToast('Please enter a message before sending.', 'error');
    enableButton(btn);
    return;
  }

  const payload = {
    action: 'support_action',
    household_id: householdId,
    mode: mode,
    status: status,
    message: message
  };

  fetch('includes/api-handlers.php', { method: 'POST', body: new URLSearchParams(payload) })
    .then(r => r.json())
    .then(json => {
      if (json.success) {
        showToast('Support message sent successfully');
        document.getElementById('supportMessage').value = '';
        setTimeout(() => location.reload(), 1500);
      } else {
        showToast(json.message || 'Error sending support message', 'error');
        enableButton(btn);
      }
    })
    .catch(err => {
      showToast('Error: ' + err.message, 'error');
      enableButton(btn);
    });
});

// Reset PIN
document.getElementById('confirmResetPinBtn').addEventListener('click', function () {
  const btn = this;
  disableButton(btn);

  const payload = {
    action: 'reset_pin',
    household_id: householdId
  };

  fetch('includes/api-handlers.php', { method: 'POST', body: new URLSearchParams(payload) })
    .then(r => r.json())
    .then(json => {
      if (json.success) {
        showToast('PIN reset successfully to 1234');
        setTimeout(() => location.reload(), 1500);
      } else {
        showToast(json.message || 'Error resetting PIN', 'error');
        enableButton(btn);
      }
    })
    .catch(err => {
      showToast('Error: ' + err.message, 'error');
      enableButton(btn);
    });
});

// Sidebar toggle for mobile
const sidebar = document.getElementById('adminSidebar') || document.querySelector('.admin-sidebar');
const toggleBtn = document.getElementById('sidebarToggle');
if (toggleBtn && sidebar) {
  toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
  document.addEventListener('click', function (e) {
    if (sidebar && sidebar.classList && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
      sidebar.classList.remove('open');
    }
  });
}
</script>

</body>
</html>
