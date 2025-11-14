<?php
/**
 * User Login Page
 * Authenticates user with phone number and PIN
 * Creates session and redirects to PIN change or dashboard
 */

require_once 'includes/config.php';
require_once 'includes/session.php';
require_once 'includes/db.php';

// Initialize session
initUserSession();

// If already logged in and no PIN change required, go to dashboard
if (isUserLoggedIn() && !requiresPinChange()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = false;

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'] ?? '';
    $pin = $_POST['pin'] ?? '';
    
    // Validate input
    if (empty($phone) || empty($pin)) {
        $error = 'Phone and PIN are required';
    } else {
        // Get user from database
        $user = getUserByPhone($phone);
        
        if (!$user) {
            $error = 'Phone number not found';
        } elseif ($user['pin'] !== $pin) {
            $error = 'Invalid PIN';
        } else {
            // Successful login
            // Check if user needs to change PIN (first login)
            $requires_pin_change = !$user['has_changed_default_pin'];
            
            // Create session
            createUserSession(
                $user['id'],
                $user['phone'],
                $user['household_id'],
                $user['name'],
                $requires_pin_change
            );
            
            // Redirect to PIN change or dashboard
            if ($requires_pin_change) {
                header('Location: change-pin.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        }
    }
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#27e46a">
  <meta name="description" content="Login to your Flexnet account and manage your subscription">
  
  <title>Flexnet – Login</title>

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

  <style>
    :root {
      --flex-green: #27e46a;
      --bg-dark: #050505;
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

    .login-screen {
      min-height: 100vh;
      padding: 0 1.8rem 2.4rem;
      display: flex;
      flex-direction: column;
    }

    .login-header {
      position: relative;
      display: flex;
      justify-content: center;
    }

    .green-strip {
      width: 64vw;
      max-width: 200px;
      height: 35vh;
      background: var(--flex-green);
      border-bottom-left-radius: 180px;
      border-bottom-right-radius: 180px;
      border-top-left-radius: 0;
      border-top-right-radius: 0;
      position: relative;
      overflow: visible;
    }

    .logo-circle {
      position: absolute;
      left: 50%;
      bottom: 20px;
      transform: translateX(-50%);
      width: 150px;
      height: 150px;
      border-radius: 50%;
      background: #000000;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .logo-mark {
      max-width: 70%;
      max-height: 70%;
      display: block;
    }

    .login-main {
      margin-top: 4.5rem;
    }

    .login-title-main {
      font-size: 2.1rem;
      line-height: 1.1;
      font-weight: 700;
      margin-bottom: 0.15rem;
      text-align: center;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .login-title-brand {
      font-size: 2.1rem;
      line-height: 1.1;
      font-weight: 800;
      color: var(--flex-green);
      margin-bottom: 2.2rem;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .login-form {
      margin-top: 0.5rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .login-form .form-control {
      border-radius: 999px;
      padding: 0.9rem 1.3rem;
      font-size: 1.05rem;
      border: none;
      box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.04);
      background: #ffffff;
      color: #000000;
    }

    .login-form .form-control::placeholder {
      color: #555555;
      opacity: 1;
    }

    .login-form .form-control:focus {
      outline: none;
      box-shadow: 0 0 0 2px var(--flex-green);
      background: #ffffff;
      color: #000000;
    }

    .btn-login {
      margin-top: 1.1rem;
      border-radius: 999px;
      padding: 0.7rem 2.4rem;
      border: none;
      background: var(--flex-green);
      color: #000000;
      font-weight: 600;
      font-size: 1rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      box-shadow: 0 10px 28px rgba(0, 0, 0, 0.6);
      cursor: pointer;
      transition: background 0.3s ease;
    }

    .btn-login:hover, .btn-login:focus, .btn-login:active {
      background: #37f17a;
      color: #000000;
      text-decoration: none;
    }

    .btn-login .arrow {
      font-size: 1.2rem;
      line-height: 1;
    }

    .error-message {
      background: #ff4757;
      color: #ffffff;
      border-radius: 999px;
      padding: 0.8rem 1.2rem;
      font-size: 0.95rem;
      margin-bottom: 1rem;
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (min-width: 768px) {
      .login-screen {
        max-width: 430px;
        margin: 0 auto;
      }
    }
  </style>
</head>
<body>

  <div class="login-screen">
    <div class="login-header">
      <div class="green-strip">
        <div class="logo-circle">
          <img src="flexnet-logo.svg" alt="Flexnet logo" class="logo-mark" loading="lazy">
        </div>
      </div>
    </div>

    <div class="login-main">
      <div class="login-title-main">Welcome</div>
      <div class="login-title-brand">Back!</div>

      <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="POST" class="login-form" autocomplete="off">
        <input 
          type="tel" 
          name="phone" 
          class="form-control" 
          placeholder="Phone number" 
          required 
          inputmode="numeric"
          autocomplete="tel"
          value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
        >
        
        <input 
          type="password" 
          name="pin" 
          class="form-control" 
          placeholder="PIN" 
          required 
          inputmode="numeric"
          autocomplete="current-password"
          maxlength="6"
        >

        <button type="submit" class="btn-login">
          Login <span class="arrow">→</span>
        </button>
      </form>

      <div style="text-align: center; margin-top: 2rem; font-size: 0.9rem; color: #aaa;">
        First time? <a href="onboarding.php" style="color: var(--flex-green); text-decoration: none;">View onboarding</a>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Register service worker for PWA
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('service-worker.js').catch(() => {
        // Service worker registration failed (optional)
      });
    }

    // Auto-extend session on form submission
    document.querySelector('.login-form').addEventListener('submit', () => {
      localStorage.setItem('flexnet_last_activity', new Date().getTime().toString());
    });
  </script>
</body>
</html>
