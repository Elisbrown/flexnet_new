<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Require authentication
requireUserAuth();

// Get user profile
$user = getUserProfile($_SESSION['user_id']);
if (!$user) {
    header('Location: login.php');
    exit;
}

// Get household info if available
$household = null;
if (!empty($_SESSION['household_id'])) {
    $household = getHouseholdInfo($_SESSION['household_id']);
}
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
    <meta name="description" content="Manage your Flexnet account settings and preferences">
    <meta property="og:title" content="Settings - Flexnet">
    <meta property="og:description" content="Manage your Flexnet account settings">
    <meta property="og:type" content="website">
    <link rel="manifest" href="/user/manifest.json">
    <link rel="icon" type="image/x-icon" href="/user/favicon/favicon.ico">
    <link rel="apple-touch-icon" href="/user/favicon/apple-touch-icon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Settings - Flexnet</title>
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
            padding: 0;
        }

        .settings-section {
            border-bottom: 1px solid var(--border);
            padding: 20px 16px;
        }

        .settings-section h2 {
            font-size: 14px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 16px;
            font-weight: 500;
        }

        .settings-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .settings-item:last-child {
            border-bottom: none;
        }

        .settings-item-label {
            display: flex;
            flex-direction: column;
        }

        .settings-item-label strong {
            font-size: 16px;
            margin-bottom: 4px;
        }

        .settings-item-label span {
            font-size: 13px;
            color: var(--text-muted);
        }

        .settings-item-value {
            font-size: 14px;
            color: var(--text-muted);
            text-align: right;
            max-width: 50%;
            word-break: break-word;
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
            margin-bottom: 12px;
        }

        .btn-primary:hover {
            background: #22c957;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            width: 100%;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .btn-danger:hover {
            background: rgba(220, 53, 69, 0.2);
        }

        .profile-info {
            display: flex;
            align-items: center;
            padding: 16px 0;
            gap: 16px;
        }

        .avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #20b558);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }

        .profile-meta {
            flex: 1;
        }

        .profile-meta h3 {
            font-size: 16px;
            margin: 0 0 4px 0;
        }

        .profile-meta p {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            background: rgba(39, 228, 106, 0.1);
            color: var(--primary);
            border: 1px solid rgba(39, 228, 106, 0.3);
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

        .logout-section {
            padding: 20px 16px;
            margin-bottom: 20px;
        }

        .device-info {
            background: rgba(39, 228, 106, 0.05);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 16px;
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
            <h1>Settings</h1>
        </header>

        <main>
            <div class="content">
                <!-- Profile Section -->
                <div class="settings-section">
                    <h2>Account</h2>
                    <div class="profile-info">
                        <div class="avatar">
                            <i class="bi bi-person"></i>
                        </div>
                        <div class="profile-meta">
                            <h3><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></h3>
                            <p><?php echo htmlspecialchars($user['phone'] ?? ''); ?></p>
                            <span class="badge">Active</span>
                        </div>
                    </div>
                </div>

                <!-- Account Settings -->
                <div class="settings-section">
                    <h2>Account Settings</h2>
                    <div class="settings-item">
                        <div class="settings-item-label">
                            <strong>Phone Number</strong>
                            <span>Login number</span>
                        </div>
                        <div class="settings-item-value"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
                    </div>
                    <?php if ($household): ?>
                    <div class="settings-item">
                        <div class="settings-item-label">
                            <strong>Household</strong>
                            <span>Primary household</span>
                        </div>
                        <div class="settings-item-value"><?php echo htmlspecialchars($household['household_name'] ?? 'Main House'); ?></div>
                    </div>
                    <div class="settings-item">
                        <div class="settings-item-label">
                            <strong>Location</strong>
                            <span>Service location</span>
                        </div>
                        <div class="settings-item-value"><?php echo htmlspecialchars($household['location'] ?? 'N/A'); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="settings-item">
                        <div class="settings-item-label">
                            <strong>Account Status</strong>
                            <span>Current status</span>
                        </div>
                        <div class="settings-item-value">
                            <span class="badge">Active</span>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="settings-section">
                    <h2>Security</h2>
                    <button class="btn btn-primary" onclick="goToChangePIN()">
                        <i class="bi bi-shield-lock"></i> Change PIN
                    </button>
                </div>

                <!-- Preferences -->
                <div class="settings-section">
                    <h2>Preferences</h2>
                    <div class="settings-item">
                        <div class="settings-item-label">
                            <strong>Notifications</strong>
                            <span>Payment reminders</span>
                        </div>
                        <div>
                            <input type="checkbox" checked id="notificationsToggle" class="form-check-input">
                        </div>
                    </div>
                    <div class="settings-item">
                        <div class="settings-item-label">
                            <strong>Dark Mode</strong>
                            <span>Always enabled</span>
                        </div>
                        <div class="badge">On</div>
                    </div>
                </div>

                <!-- About -->
                <div class="settings-section">
                    <h2>About</h2>
                    <div class="settings-item">
                        <div class="settings-item-label">
                            <strong>Version</strong>
                        </div>
                        <div class="settings-item-value">1.0.0</div>
                    </div>
                    <div class="settings-item">
                        <div class="settings-item-label">
                            <strong>Last Updated</strong>
                        </div>
                        <div class="settings-item-value" id="lastUpdated">Today</div>
                    </div>
                    <div class="device-info">
                        <strong>Device Information:</strong><br>
                        Browser: <span id="browserInfo">Chrome</span><br>
                        OS: <span id="osInfo">Android</span>
                    </div>
                </div>

                <!-- Logout -->
                <div class="logout-section">
                    <button class="btn btn-danger" onclick="confirmLogout()">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </button>
                </div>
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
            <a href="subscriptions.php" class="nav-link">
                <i class="bi bi-list-check"></i>
                <span>Plans</span>
            </a>
            <a href="settings.php" class="nav-link active">
                <i class="bi bi-gear-fill"></i>
                <span>Settings</span>
            </a>
        </nav>
    </div>

    <script>
        // Detect device info
        function detectDevice() {
            const ua = navigator.userAgent;
            let browser = 'Unknown';
            let os = 'Unknown';

            if (ua.indexOf('Firefox') > -1) browser = 'Firefox';
            else if (ua.indexOf('Chrome') > -1) browser = 'Chrome';
            else if (ua.indexOf('Safari') > -1) browser = 'Safari';
            else if (ua.indexOf('Edge') > -1) browser = 'Edge';

            if (ua.indexOf('Windows') > -1) os = 'Windows';
            else if (ua.indexOf('Mac') > -1) os = 'macOS';
            else if (ua.indexOf('Android') > -1) os = 'Android';
            else if (ua.indexOf('iPhone') > -1 || ua.indexOf('iPad') > -1) os = 'iOS';
            else if (ua.indexOf('Linux') > -1) os = 'Linux';

            document.getElementById('browserInfo').textContent = browser;
            document.getElementById('osInfo').textContent = os;
        }

        // Go to change PIN page
        function goToChangePIN() {
            window.location.href = 'change-pin.php';
        }

        // Confirm logout
        function confirmLogout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        // Initialize
        detectDevice();

        // Register service worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/user/service-worker.js').catch(err => {
                console.log('SW registration failed:', err);
            });
        }
    </script>
</body>
</html>
