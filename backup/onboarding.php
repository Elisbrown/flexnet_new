<?php
/**
 * Onboarding Page
 * First-time user experience with carousel
 * Skip on repeat visits using localStorage
 */

// Check if user already saw onboarding
// This is handled on client-side, but we can also check server-side if needed
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#27e46a">
  <meta name="description" content="Welcome to Flexnet - Manage your internet subscription with flexibility">
  
  <title>Flexnet – Welcome</title>

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="favicon/favicon.ico">
  <link rel="apple-touch-icon" href="favicon/apple-touch-icon.png">
  
  <!-- PWA Manifest -->
  <link rel="manifest" href="manifest.json">

  <!-- Apple Web App Settings -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Flexnet">

  <!-- Open Graph -->
  <meta property="og:title" content="Flexnet – Welcome">
  <meta property="og:description" content="Manage your internet subscription with flexibility">
  <meta property="og:image" content="favicon/apple-touch-icon.png">
  <meta property="og:type" content="website">
  
  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root {
      --flex-green: #27e46a;
      --bg-dark: #050505;
      --pager-muted: #444444;
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
      height: 100%;
      overflow: hidden;
    }

    .onboarding-carousel {
      height: 100vh;
      width: 100%;
    }

    .onboarding-screen {
      min-height: 100vh;
      padding: 0 1.8rem 2.4rem;
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
    }

    .onboarding-header {
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

    .onboarding-footer {
      margin-top: auto;
    }

    .onboarding-title {
      font-size: 2.2rem;
      line-height: 1.1;
      font-weight: 800;
    }

    .onboarding-title .accent {
      color: var(--flex-green);
    }

    .pager {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .pager-dot {
      width: 8px;
      height: 8px;
      border-radius: 999px;
      background: var(--pager-muted);
      display: inline-block;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .pager-dot.is-active {
      width: 24px;
      background: #ffffff;
    }

    .btn-cta {
      border-radius: 999px;
      padding: 0.7rem 1.9rem;
      border: none;
      background: var(--flex-green);
      color: #000000;
      font-weight: 600;
      font-size: 1rem;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: 0 10px 28px rgba(0, 0, 0, 0.6);
      cursor: pointer;
      transition: background 0.3s ease;
    }

    .btn-cta:hover, .btn-cta:focus, .btn-cta:active {
      background: #37f17a;
      color: #000000;
      text-decoration: none;
      outline: none;
    }

    .btn-cta .arrow {
      font-size: 1.2rem;
      line-height: 1;
    }

    /* Hide default bootstrap controls */
    .carousel-indicators, .carousel-control-prev, .carousel-control-next {
      display: none;
    }

    /* Responsive */
    @media (max-width: 360px) {
      .onboarding-title {
        font-size: 2rem;
      }
      .green-strip {
        height: 55vh;
      }
    }

    @media (min-width: 768px) {
      .onboarding-screen {
        max-width: 430px;
        margin: 0 auto;
      }
    }
  </style>
</head>
<body>

  <div id="onboardingCarousel" class="carousel slide onboarding-carousel" data-bs-touch="true" data-bs-ride="false">
    <div class="carousel-inner">

      <!-- Slide 1 -->
      <div class="carousel-item active">
        <div class="onboarding-screen">
          <div class="onboarding-header">
            <div class="green-strip">
              <div class="logo-circle">
                <img src="flexnet-logo.svg" alt="Flexnet logo" class="logo-mark" loading="lazy">
              </div>
            </div>
          </div>

          <div class="onboarding-footer">
            <h1 class="onboarding-title">
              Manage Your<br>Internet With<br><span class="accent">Flex</span>ibility
            </h1>

            <div class="d-flex justify-content-between align-items-center mt-4">
              <div class="pager">
                <span class="pager-dot is-active"></span>
                <span class="pager-dot"></span>
                <span class="pager-dot"></span>
              </div>
              <button class="btn-cta js-skip" type="button">
                Skip <span class="arrow">→</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 2 -->
      <div class="carousel-item">
        <div class="onboarding-screen">
          <div class="onboarding-header">
            <div class="green-strip">
              <div class="logo-circle">
                <img src="flexnet-logo.svg" alt="Flexnet logo" class="logo-mark" loading="lazy">
              </div>
            </div>
          </div>

          <div class="onboarding-footer">
            <h1 class="onboarding-title">
              Pay Only For<br>What You<br><span class="accent">Use</span>
            </h1>

            <div class="d-flex justify-content-between align-items-center mt-4">
              <div class="pager">
                <span class="pager-dot"></span>
                <span class="pager-dot is-active"></span>
                <span class="pager-dot"></span>
              </div>
              <button class="btn-cta js-skip" type="button">
                Skip <span class="arrow">→</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 3 -->
      <div class="carousel-item">
        <div class="onboarding-screen">
          <div class="onboarding-header">
            <div class="green-strip">
              <div class="logo-circle">
                <img src="flexnet-logo.svg" alt="Flexnet logo" class="logo-mark" loading="lazy">
              </div>
            </div>
          </div>

          <div class="onboarding-footer">
            <h1 class="onboarding-title">
              Control Your<br>Internet<br><span class="accent">Anytime</span>
            </h1>

            <div class="d-flex justify-content-between align-items-center mt-4">
              <div class="pager">
                <span class="pager-dot"></span>
                <span class="pager-dot"></span>
                <span class="pager-dot is-active"></span>
              </div>
              <button class="btn-cta js-login" type="button">
                Login <span class="arrow">→</span>
              </button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Initialize carousel
    const carouselEl = document.getElementById('onboardingCarousel');
    const carousel = new bootstrap.Carousel(carouselEl, { touch: true, ride: false });

    // Skip button - go to slide 3
    document.querySelectorAll('.js-skip').forEach(btn => {
      btn.addEventListener('click', () => {
        carousel.to(2);
      });
    });

    // Login button - mark onboarding as seen and go to login
    document.querySelector('.js-login').addEventListener('click', () => {
      localStorage.setItem('flexnet_first_visit', 'true');
      window.location.href = 'login.php';
    });

    // Pager dots - click to navigate
    document.querySelectorAll('.pager-dot').forEach((dot, index) => {
      dot.addEventListener('click', () => {
        carousel.to(index);
      });
    });

    // Update pager on slide change
    carouselEl.addEventListener('slide.bs.carousel', (event) => {
      document.querySelectorAll('.pager-dot').forEach((dot, idx) => {
        dot.classList.toggle('is-active', idx === event.to);
      });
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
