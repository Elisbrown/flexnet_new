<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Require authentication
requireUserAuth();

// Get household and subscription info
$user = getUserProfile($_SESSION['user_id']);
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
    <meta name="description" content="Pay your Flexnet bill securely online">
    <meta property="og:title" content="Billing - Flexnet">
    <meta property="og:description" content="Pay your Flexnet bill">
    <meta property="og:type" content="website">
    <link rel="manifest" href="/user/manifest.json">
    <link rel="icon" type="image/x-icon" href="/user/favicon/favicon.ico">
    <link rel="apple-touch-icon" href="/user/favicon/apple-touch-icon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Billing - Flexnet</title>
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

        .amount-card {
            background: linear-gradient(135deg, rgba(39, 228, 106, 0.1) 0%, rgba(39, 228, 106, 0.05) 100%);
            border: 1px solid rgba(39, 228, 106, 0.3);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            text-align: center;
        }

        .amount-label {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .amount-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .amount-period {
            font-size: 13px;
            color: var(--text-muted);
        }

        .payment-method {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .payment-method:hover {
            border-color: var(--primary);
            background: rgba(39, 228, 106, 0.05);
        }

        .payment-method input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .payment-method label {
            flex: 1;
            margin: 0;
            cursor: pointer;
        }

        .payment-method-icon {
            font-size: 24px;
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.1);
        }

        .form-group input::placeholder {
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
        }

        .btn-primary:hover:not(:disabled) {
            background: #22c957;
            transform: translateY(-2px);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert-info {
            background: rgba(39, 228, 106, 0.1);
            border: 1px solid rgba(39, 228, 106, 0.3);
            color: var(--primary);
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            color: #ffc107;
        }

        .small-text {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 8px;
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
            <h1>Billing</h1>
        </header>

        <main>
            <div class="content">
                <div class="amount-card">
                    <div class="amount-label">Amount Due</div>
                    <div class="amount-value">
                        <?php 
                            $amount = $activeSubscription ? ($activeSubscription['price'] ?? 0) : 0;
                            echo 'UGX ' . number_format($amount);
                        ?>
                    </div>
                    <div class="amount-period">Monthly subscription</div>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Secure payment powered by Fapshi
                </div>

                <h2 class="section-title">Select Payment Method</h2>

                <form id="paymentForm" method="POST">
                    <div id="paymentMethodsContainer">
                        <!-- Payment methods will be loaded here -->
                    </div>

                    <h2 class="section-title">Payment Details</h2>

                    <div class="form-group">
                        <label for="phoneNumber">Phone Number</label>
                        <input 
                            type="tel" 
                            id="phoneNumber" 
                            name="phone_number" 
                            placeholder="256 700 123456"
                            value="<?php echo htmlspecialchars(str_replace('+256 ', '256 ', $user['phone'] ?? '')); ?>"
                            required
                        >
                        <div class="small-text">Payment will be deducted from this number</div>
                    </div>

                    <div class="form-group">
                        <label for="amount">Amount</label>
                        <input 
                            type="number" 
                            id="amount" 
                            name="amount" 
                            value="<?php echo $amount; ?>"
                            readonly
                            style="background: rgba(255, 255, 255, 0.02);"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary" id="payButton">
                        <i class="bi bi-shield-check"></i> Pay Now
                    </button>

                    <div class="small-text" style="text-align: center;">
                        Your payment is secure and encrypted
                    </div>
                </form>
            </div>
        </main>

        <!-- Bottom Navigation -->
        <nav>
            <a href="dashboard.php" class="nav-link">
                <i class="bi bi-house-fill"></i>
                <span>Home</span>
            </a>
            <a href="billing.php" class="nav-link active">
                <i class="bi bi-credit-card"></i>
                <span>Billing</span>
            </a>
            <a href="subscriptions.php" class="nav-link">
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
        // Initialize payment methods
        function initializePaymentMethods() {
            const methods = [
                { id: 'mtn', name: 'MTN Mobile Money', icon: 'bi-phone' },
                { id: 'airtel', name: 'Airtel Money', icon: 'bi-phone' },
                { id: 'card', name: 'Credit/Debit Card', icon: 'bi-credit-card' }
            ];

            const container = document.getElementById('paymentMethodsContainer');
            container.innerHTML = methods.map((method, index) => `
                <div class="payment-method">
                    <input 
                        type="radio" 
                        id="${method.id}" 
                        name="payment_method" 
                        value="${method.id}"
                        ${index === 0 ? 'checked' : ''}
                        required
                    >
                    <label for="${method.id}" style="display: flex; align-items: center; gap: 12px; width: 100%;">
                        <i class="bi ${method.icon} payment-method-icon"></i>
                        <span>${method.name}</span>
                    </label>
                </div>
            `).join('');
        }

        // Handle form submission
        document.getElementById('paymentForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const phoneNumber = document.getElementById('phoneNumber').value;
            const amount = document.getElementById('amount').value;
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
            const payButton = document.getElementById('payButton');

            if (!phoneNumber || !amount || !paymentMethod) {
                alert('Please fill in all fields');
                return;
            }

            payButton.disabled = true;
            payButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

            try {
                // Submit payment to backend
                const response = await fetch('api/payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'initiate',
                        phone_number: phoneNumber,
                        amount: amount,
                        payment_method: paymentMethod,
                        account_reference: '<?php echo $_SESSION['user_id']; ?>'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Payment initiated. You will receive a prompt on your phone.');
                    // You can redirect or update UI here
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                } else {
                    alert('Payment failed: ' + (result.message || 'Unknown error'));
                    payButton.disabled = false;
                    payButton.innerHTML = '<i class="bi bi-shield-check"></i> Pay Now';
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error processing payment. Please try again.');
                payButton.disabled = false;
                payButton.innerHTML = '<i class="bi bi-shield-check"></i> Pay Now';
            }
        });

        // Initialize on load
        initializePaymentMethods();

        // Register service worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/user/service-worker.js').catch(err => {
                console.log('SW registration failed:', err);
            });
        }
    </script>
</body>
</html>
