<?php
/**
 * Change PIN Page
 * Forced PIN change on first login
 * User must set a new PIN before accessing dashboard
 */

require_once 'includes/config.php';
require_once 'includes/session.php';
require_once 'includes/db.php';

// Require PIN change
requirePinChange();

$error = '';
$success = false;

// Handle PIN change form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pin = $_POST['new_pin'] ?? '';
    $confirm_pin = $_POST['confirm_pin'] ?? '';
    
    // Validate input
    if (empty($new_pin) || empty($confirm_pin)) {
        $error = 'New PIN and confirmation are required';
    } elseif (strlen($new_pin) < 4 || strlen($new_pin) > 6) {
        $error = 'PIN must be between 4 and 6 digits';
    } elseif (!ctype_digit($new_pin)) {
        $error = 'PIN must contain only digits';
    } elseif ($new_pin === '1234') {
        $error = 'Cannot use default PIN';
    } elseif ($new_pin !== $confirm_pin) {
        $error = 'PIN confirmation does not match';
    } else {
        // Update PIN in database
        $user_id = getUserId();
        
        if (updateUserPin($user_id, $new_pin)) {
            // Clear PIN change requirement
            clearPinChangeFlag();
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Failed to update PIN. Please try again.';
        }
    }
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#27e46a">
  <meta name="description" content="Change your PIN and secure your Flexnet account">
  
  <title>Flexnet – Secure Your Account</title>

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

    .pin-screen {
      min-height: 100vh;
      padding: 0 1.8rem 2.4rem;
      display: flex;
      flex-direction: column;
    }

    .pin-header {
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

    .pin-main {
      margin-top: 4.5rem;
    }

    .pin-title-main {
      font-size: 2.1rem;
      line-height: 1.1;
      font-weight: 700;
      margin-bottom: 0.15rem;
      text-align: left;
    }

    .pin-title-accent {
      font-size: 2.1rem;
      line-height: 1.1;
      font-weight: 800;
      color: var(--flex-green);
      margin-bottom: 1rem;
      text-align: left;
    }

    .pin-subtitle {
      font-size: 0.95rem;
      color: #aaa;
      margin-bottom: 2rem;
      line-height: 1.5;
    }

    .pin-form {
      margin-top: 0.5rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .pin-form .form-control {
      border-radius: 999px;
      padding: 0.9rem 1.3rem;
      font-size: 1.05rem;
      border: none;
      box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.04);
      background: #ffffff;
      color: #000000;
      letter-spacing: 0.2em;
    }

    .pin-form .form-control::placeholder {
      color: #555555;
      opacity: 1;
      letter-spacing: normal;
    }

    .pin-form .form-control:focus {
      outline: none;
      box-shadow: 0 0 0 2px var(--flex-green);
      background: #ffffff;
      color: #000000;
    }

    .btn-change {
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

    .btn-change:hover, .btn-change:focus, .btn-change:active {
      background: #37f17a;
      color: #000000;
      text-decoration: none;
    }

    .btn-change .arrow {
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

    .info-box {
      background: rgba(39, 228, 106, 0.1);
      border: 1px solid rgba(39, 228, 106, 0.3);
      border-radius: 12px;
      padding: 1rem;
      margin-bottom: 1.5rem;
      font-size: 0.9rem;
      line-height: 1.5;
      color: #b9f7cf;
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
      .pin-screen {
        max-width: 430px;
        margin: 0 auto;
      }
    }
  </style>
</head>
<body>

  <div class="pin-screen">
    <div class="pin-header">
      <div class="green-strip">
        <div class="logo-circle">
          <img src="flexnet-logo.svg" alt="Flexnet logo" class="logo-mark" loading="lazy">
        </div>
      </div>
    </div>

    <div class="pin-main">
      <div class="pin-title-main">Secure Your</div>
      <div class="pin-title-accent">Account</div>

      <div class="info-box">
        ✓ This is your first login. Set a new PIN to secure your account.
      </div>

      <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="POST" class="pin-form" autocomplete="off">
        <input 
          type="password" 
          name="new_pin" 
          class="form-control" 
          placeholder="New PIN (4-6 digits)" 
          required 
          inputmode="numeric"
          autocomplete="off"
          maxlength="6"
          pattern="[0-9]{4,6}"
        >
        
        <input 
          type="password" 
          name="confirm_pin" 
          class="form-control" 
          placeholder="Confirm PIN" 
          required 
          inputmode="numeric"
          autocomplete="off"
          maxlength="6"
          pattern="[0-9]{4,6}"
        >

        <button type="submit" class="btn-change">
          Change PIN <span class="arrow">→</span>
        </button>
      </form>

      <div style="text-align: center; margin-top: 2rem; font-size: 0.85rem; color: #777;">
        <p style="margin: 0; line-height: 1.6;">
          Your PIN must be different from the default PIN.<br>
          Use 4-6 digits for maximum security.
        </p>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // PIN validation - prevent submission if PINs don't match
    const form = document.querySelector('.pin-form');
    const newPinInput = document.querySelector('input[name="new_pin"]');
    const confirmPinInput = document.querySelector('input[name="confirm_pin"]');

    confirmPinInput.addEventListener('input', function() {
      if (this.value && newPinInput.value && this.value !== newPinInput.value) {
        this.style.boxShadow = '0 0 0 2px #ff4757';
      } else {
        this.style.boxShadow = '';
      }
    });

    // Register service worker for PWA
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('service-worker.js').catch(() => {
        // Service worker registration failed (optional)
      });
    }
  </script>
</body>
</html>
