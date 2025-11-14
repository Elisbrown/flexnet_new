<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Require authentication
requireUserAuth();

// Get household subscriptions
$subscriptions = getHouseholdSubscriptions($_SESSION['household_id']);
$activeSubscription = getActiveSubscription($_SESSION['household_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#27e46a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Flexnet">
    <meta name="description" content="View and manage your Flexnet subscription plans">
    <meta property="og:title" content="Plans - Flexnet">
    <meta property="og:description" content="View and manage your subscription plans">
    <meta property="og:type" content="website">
    <link rel="manifest" href="/user/manifest.json">
    <link rel="icon" type="image/x-icon" href="/user/favicon/favicon.ico">
    <link rel="apple-touch-icon" href="/user/favicon/apple-touch-icon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Plans - Flexnet</title>
    <style>
        :root {
            --primary: #27e46a;
            --secondary: #050505;
            --dark: #1a1a1a;
            --border: #2a2a2a;
            --text-muted: #888;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            background: var(--secondary);
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
        }

        .container {
            max-width: 430px;
            margin: 0 auto;
            padding: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        header {
            background: linear-gradient(135deg, rgba(39, 228, 106, 0.1) 0%, rgba(39, 228, 106, 0.05) 100%);
            border-bottom: 1px solid var(--border);
            padding: max(16px, calc(16px + env(safe-area-inset-top)));
        }

        h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        main {
            flex: 1;
            overflow-y: auto;
            padding: 0;
        }

        .content {
            padding: 16px;
            padding-bottom: 100px;
        }

        .section-title {
            font-size: 14px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin: 24px 0 16px 0;
            font-weight: 500;
        }

        .subscription-card {
            background: rgba(39, 228, 106, 0.05);
            border: 2px solid rgba(39, 228, 106, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            position: relative;
            transition: all 0.3s ease;
        }

        .subscription-card.active {
            border-color: var(--primary);
            background: rgba(39, 228, 106, 0.1);
        }

        .subscription-card:hover {
            border-color: var(--primary);
        }

        .badge-active {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--primary);
            color: #000;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .subscription-header {
            margin-bottom: 12px;
        }

        .subscription-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .subscription-price {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .subscription-price span {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 400;
        }

        .subscription-details {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .detail-label {
            color: var(--text-muted);
        }

        .detail-value {
            font-weight: 500;
        }

        .status-active {
            color: var(--primary);
        }

        .status-expired {
            color: #dc3545;
        }

        .status-inactive {
            color: var(--text-muted);
        }

        .btn {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: #000;
            width: 100%;
            margin-top: 12px;
        }

        .btn-primary:hover {
            background: #22c957;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        nav {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border-top: 1px solid var(--border);
            background: var(--dark);
            padding: 0;
            padding-bottom: env(safe-area-inset-bottom);
        }

        nav a {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 12px;
            padding-bottom: calc(12px + env(safe-area-inset-bottom));
            color: var(--text-muted);
            text-decoration: none;
            font-size: 12px;
            gap: 4px;
            transition: all 0.2s;
        }

        nav a.active {
            color: var(--primary);
        }

        nav i {
            font-size: 20px;
        }

        @media (min-width: 768px) {
            .container {
                max-width: 430px;
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Plans</h1>
        </header>

        <main>
            <div class="content">
                <p style="color: var(--text-muted); font-size: 14px;">Manage your subscription plans and billing details.</p>

                <?php if (!empty($subscriptions)): ?>
                    <h2 class="section-title">Your Subscriptions</h2>
                    
                    <?php foreach ($subscriptions as $sub): ?>
                        <?php 
                            $isActive = $activeSubscription && $activeSubscription['subscription_id'] == $sub['subscription_id'];
                            $statusClass = $isActive ? 'active' : '';
                            $statusText = $isActive ? 'ACTIVE' : 'INACTIVE';
                            $statusColor = $isActive ? 'status-active' : 'status-inactive';
                        ?>
                        <div class="subscription-card <?php echo $statusClass; ?>">
                            <?php if ($isActive): ?>
                                <div class="badge-active">ACTIVE</div>
                            <?php endif; ?>
                            
                            <div class="subscription-header">
                                <div class="subscription-name"><?php echo htmlspecialchars($sub['plan_name'] ?? 'Plan'); ?></div>
                                <div class="subscription-price">
                                    UGX <?php echo number_format($sub['price'] ?? 0); ?>
                                    <span>/month</span>
                                </div>
                            </div>

                            <div class="subscription-details">
                                <div class="detail-row">
                                    <span class="detail-label">Data Allowance</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($sub['data_allowance'] ?? 'Unlimited'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Speed</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($sub['speed'] ?? 'Standard'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Status</span>
                                    <span class="detail-value <?php echo $statusColor; ?>"><?php echo $statusText; ?></span>
                                </div>
                                <?php if ($isActive && !empty($sub['end_date'])): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Expires</span>
                                        <span class="detail-value"><?php echo date('M d, Y', strtotime($sub['end_date'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($isActive): ?>
                                <button class="btn btn-primary" onclick="window.location.href='billing.php'">
                                    <i class="bi bi-credit-card"></i> Renew Plan
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h3 style="margin-bottom: 8px; color: #fff;">No Active Subscriptions</h3>
                        <p>Get started by subscribing to a plan</p>
                        <button class="btn btn-primary" style="margin-top: 20px; width: auto;" onclick="window.location.href='billing.php'">
                            <i class="bi bi-plus-circle"></i> Choose a Plan
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <!-- Bottom Navigation -->
        <nav>
            <a href="dashboard.php" class="nav-link">
                <i class="bi bi-house-fill"></i>
                <span>Home</span>
            </a>
            <a href="billing.php" class="nav-link">
                <i class="bi bi-credit-card"></i>
                <span>Billing</span>
            </a>
            <a href="subscriptions.php" class="nav-link active">
                <i class="bi bi-list-check"></i>
                <span>Plans</span>
            </a>
            <a href="settings.php" class="nav-link">
                <i class="bi bi-gear-fill"></i>
                <span>Settings</span>
            </a>
        </nav>
    </div>

    <script>
        // Register service worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/user/service-worker.js').catch(err => {
                console.log('SW registration failed:', err);
            });
        }
    </script>
</body>
</html>
