<?php
/**
 * User Dashboard Page
 * Main dashboard showing subscription status, billing info, and quick actions
 */

require_once 'includes/config.php';
require_once 'includes/session.php';
require_once 'includes/db.php';

// Require authentication
requireUserAuth();

// Get user data
$user_id = getUserId();
$household_id = getUserHouseholdId();
$user_name = getUserName();

// Load user profile
$user = getUserProfile($user_id);

// Load household info
$household = getHouseholdInfo($household_id);

// Load active subscription
$subscription = getActiveSubscription($household_id);

// Load recent payments
$recent_payments = getRecentPayments($household_id);

// Calculate subscription status
$subscription_status = 'No Active Subscription';
$subscription_expires = null;
$payment_channel = 'N/A';
$days_remaining = 0;

if ($subscription) {
    $subscription_status = ucfirst(strtolower($subscription['subscription_status']));
    $subscription_expires = $subscription['subscription_end_date'];
    $payment_channel = $subscription['payment_channel'] ?? 'N/A';
    
    // Calculate days remaining
    $now = new DateTime();
    $expire_date = new DateTime($subscription_expires);
    $interval = $expire_date->diff($now);
    $days_remaining = $interval->invert ? $interval->days : 0;
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#27e46a">
  <meta name="description" content="Your Flexnet dashboard - Manage subscription and billing">
  
  <title>Flexnet – Dashboard</title>

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="favicon/favicon.ico">
  <link rel="apple-touch-icon" href="favicon/apple-touch-icon.png">
  
  <!-- PWA Manifest -->
  <link rel="manifest" href="manifest.json">

  <!-- Apple Web App Settings -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Flexnet">

  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <style>
    :root {
      --flex-green: #27e46a;
      --flex-green-soft: #b9f7cf;
      --bg-dark: #050505;
      --chip-dark: #262626;
    }

    * {
      box-sizing: border-box;
    }

    html, body {
      margin: 0;
      padding: 0;
      background: var(--bg-dark);
      color: #ffffff;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    .dashboard {
      min-height: 100vh;
      padding: 1.6rem 1.4rem 6rem;
      display: flex;
      flex-direction: column;
    }

    @media (min-width: 768px) {
      .dashboard {
        max-width: 430px;
        margin: 0 auto;
      }
    }

    /* Header */
    .top-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.6rem;
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }

    .user-avatar {
      width: 58px;
      height: 58px;
      border-radius: 50%;
      background: #ffffff;
      overflow: hidden;
      flex-shrink: 0;
    }

    .user-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .user-greeting {
      font-size: 0.95rem;
      line-height: 1.2;
    }

    .user-greeting span {
      font-weight: 700;
      display: block;
      color: var(--flex-green);
    }

    .status-pill {
      background: #ffffff;
      color: #000000;
      border-radius: 999px;
      padding: 0.35rem 0.9rem;
      font-size: 0.85rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
    }

    .status-pill i {
      font-size: 0.75rem;
    }

    /* Subscription Status Card */
    .subscription-card {
      background: linear-gradient(135deg, #27e46a 0%, #1fc556 100%);
      border-radius: 24px;
      padding: 1.8rem;
      margin-bottom: 1.6rem;
      color: #000000;
      box-shadow: 0 10px 40px rgba(39, 228, 106, 0.2);
    }

    .sub-status {
      font-size: 0.9rem;
      font-weight: 600;
      opacity: 0.9;
      margin-bottom: 0.4rem;
    }

    .sub-status-main {
      font-size: 2rem;
      font-weight: 800;
      margin-bottom: 1rem;
    }

    .sub-details {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      font-size: 0.9rem;
    }

    .sub-detail {
      display: flex;
      flex-direction: column;
      gap: 0.2rem;
    }

    .sub-detail-label {
      opacity: 0.8;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .sub-detail-value {
      font-size: 1.1rem;
      font-weight: 700;
    }

    /* Quick Actions */
    .quick-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1.6rem;
    }

    .action-btn {
      background: var(--chip-dark);
      border: 1px solid #333;
      border-radius: 16px;
      padding: 1rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.6rem;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      color: #ffffff;
    }

    .action-btn:hover {
      background: #333;
      border-color: var(--flex-green);
      transform: translateY(-4px);
    }

    .action-btn i {
      font-size: 1.6rem;
      color: var(--flex-green);
    }

    .action-btn span {
      font-size: 0.9rem;
      font-weight: 600;
      text-align: center;
    }

    /* Recent Transactions */
    .recent-section {
      margin-bottom: 2rem;
    }

    .recent-title {
      font-size: 1.1rem;
      font-weight: 700;
      margin-bottom: 1rem;
    }

    .transaction-list {
      display: flex;
      flex-direction: column;
      gap: 0.8rem;
    }

    .transaction-item {
      background: #111;
      border: 1px solid #222;
      border-radius: 12px;
      padding: 1rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .transaction-info {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      flex: 1;
    }

    .transaction-icon {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: var(--chip-dark);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      color: var(--flex-green);
    }

    .transaction-text {
      display: flex;
      flex-direction: column;
      gap: 0.2rem;
    }

    .transaction-desc {
      font-size: 0.9rem;
      font-weight: 600;
    }

    .transaction-date {
      font-size: 0.8rem;
      color: #aaa;
    }

    .transaction-amount {
      font-weight: 700;
      text-align: right;
    }

    /* Bottom Navigation */
    .bottom-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: var(--bg-dark);
      border-top: 1px solid #222;
      display: flex;
      justify-content: space-around;
      align-items: center;
      height: 5rem;
      padding-bottom: env(safe-area-inset-bottom);
    }

    @media (min-width: 768px) {
      .bottom-nav {
        max-width: 430px;
        left: 50%;
        transform: translateX(-50%);
      }
    }

    .nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.3rem;
      cursor: pointer;
      text-decoration: none;
      color: #aaa;
      font-size: 0.8rem;
      transition: color 0.3s ease;
      flex: 1;
      height: 100%;
      justify-content: center;
    }

    .nav-item.active {
      color: var(--flex-green);
    }

    .nav-item i {
      font-size: 1.5rem;
    }

    .empty-state {
      text-align: center;
      padding: 2rem 1rem;
      color: #aaa;
    }

    .empty-state i {
      font-size: 2.5rem;
      margin-bottom: 0.5rem;
      color: #555;
    }
  </style>
</head>
<body>

  <div class="dashboard">
    <!-- Header -->
    <div class="top-header">
      <div class="user-info">
        <div class="user-avatar">
          <img src="<?php echo htmlspecialchars($user['avatar_url'] ?? 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2250%22 fill=%22%23ddd%22/%3E%3Ctext x=%2250%22 y=%2260%22 font-size=%2240%22 text-anchor=%22middle%22 fill=%22%23666%22%3E' . substr($user_name, 0, 1) . '%3C/text%3E%3C/svg%3E'); ?>" alt="User avatar" loading="lazy">
        </div>
        <div class="user-greeting">
          Hello,<br>
          <span><?php echo htmlspecialchars($user_name); ?></span>
        </div>
      </div>
      <div class="status-pill">
        <i class="bi bi-check-circle-fill"></i>
        <?php echo $subscription_status; ?>
      </div>
    </div>

    <!-- Subscription Card -->
    <div class="subscription-card">
      <div class="sub-status">Current Subscription</div>
      <div class="sub-status-main"><?php echo $subscription ? 'Active' : 'Inactive'; ?></div>
      
      <?php if ($subscription): ?>
        <div class="sub-details">
          <div class="sub-detail">
            <div class="sub-detail-label">Expires</div>
            <div class="sub-detail-value"><?php echo date('d M Y', strtotime($subscription_expires)); ?></div>
          </div>
          <div class="sub-detail">
            <div class="sub-detail-label">Days Left</div>
            <div class="sub-detail-value"><?php echo $days_remaining; ?></div>
          </div>
          <div class="sub-detail">
            <div class="sub-detail-label">Type</div>
            <div class="sub-detail-value"><?php echo htmlspecialchars($subscription['subscription_type'] ?? 'Standard'); ?></div>
          </div>
          <div class="sub-detail">
            <div class="sub-detail-label">Channel</div>
            <div class="sub-detail-value"><?php echo htmlspecialchars($payment_channel); ?></div>
          </div>
        </div>
      <?php else: ?>
        <div class="sub-details">
          <div class="sub-detail">
            <div class="sub-detail-label">Status</div>
            <div class="sub-detail-value">Inactive</div>
          </div>
          <div class="sub-detail">
            <div class="sub-detail-label">Action</div>
            <div class="sub-detail-value"><a href="billing.php" style="color: #000; text-decoration: underline;">Renew</a></div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <a href="billing.php" class="action-btn">
        <i class="bi bi-credit-card"></i>
        <span>Pay Now</span>
      </a>
      <a href="subscriptions.php" class="action-btn">
        <i class="bi bi-list-check"></i>
        <span>Subscriptions</span>
      </a>
    </div>

    <!-- Recent Transactions -->
    <div class="recent-section">
      <div class="recent-title">Recent Payments</div>
      
      <?php if (!empty($recent_payments)): ?>
        <div class="transaction-list">
          <?php foreach (array_slice($recent_payments, 0, 3) as $payment): ?>
            <div class="transaction-item">
              <div class="transaction-info">
                <div class="transaction-icon">
                  <i class="bi bi-arrow-down"></i>
                </div>
                <div class="transaction-text">
                  <div class="transaction-desc"><?php echo htmlspecialchars($payment['payment_channel']); ?></div>
                  <div class="transaction-date"><?php echo date('d M Y', strtotime($payment['created_at'])); ?></div>
                </div>
              </div>
              <div class="transaction-amount">
                <?php echo isset($payment['amount']) ? 'XAF ' . number_format($payment['amount']) : 'Pending'; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="bi bi-inbox"></i>
          <p>No payments yet</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Bottom Navigation -->
  <nav class="bottom-nav">
    <a href="dashboard.php" class="nav-item active">
      <i class="bi bi-house-fill"></i>
      <span>Home</span>
    </a>
    <a href="billing.php" class="nav-item">
      <i class="bi bi-credit-card"></i>
      <span>Billing</span>
    </a>
    <a href="subscriptions.php" class="nav-item">
      <i class="bi bi-list-check"></i>
      <span>Plans</span>
    </a>
    <a href="settings.php" class="nav-item">
      <i class="bi bi-gear"></i>
      <span>Settings</span>
    </a>
  </nav>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Extend session on page interaction
    document.addEventListener('click', () => {
      localStorage.setItem('flexnet_last_activity', new Date().getTime().toString());
    });

    // Register service worker for PWA
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('service-worker.js').catch(() => {
        // Service worker registration failed (optional)
      });
    }

    // Set active nav item
    document.querySelectorAll('.nav-item').forEach(item => {
      if (item.href === window.location.href) {
        item.classList.add('active');
      }
    });
  </script>
</body>
</html>
