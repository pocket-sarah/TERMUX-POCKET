<!DOCTYPE html>

<html lang="en">

<head>

  <meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>KOhO BUISNESS.</title>
<style>

    /* Reset styles */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    html, body {
      width: 100%;
      height: 100%;
      background-color: #281a57;
      color: #fff;
      display: flex;
      justify-content: center;
      align-items: center;
      overflow: hidden;
      position: relative;
      font-family: Arial, sans-serif;
    }
    /* Splash screen container with fade out */
    .splash-screen {
      animation: fadeOut 1s ease-out forwards;
      animation-delay: 3s;
    }
    .splash-screen svg {
      animation: pulse 2s infinite ease-in-out;
      width: 256px;
      height: 64px;
    }
    @keyframes fadeOut {
      from { opacity: 1; }
      to { opacity: 0; }
    }
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.1); }
      100% { transform: scale(1); }
    }
    /* Secret panel styling */
    .panel {
      display: none;
      position: absolute;
      top: 20%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: #fff;
      color: #000;
      padding: 20px;
      border: 2px solid #281a57;
      border-radius: 8px;
      text-align: center;
    }
    /* Secret button styling */
    .secret-button {
      position: absolute;
      bottom: 20px;
      padding: 10px 20px;
      background: #281a57;
      color: #fff;
      border: 2px solid #fff;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
    }


</style>
  <link rel="stylesheet" href="assets/css/splash.css">

  <script src="assets/js/splash.js"></script>


</head>

<body>

    <div class="splash-screen">

    <!-- Animated SVG logo -->

    <svg viewBox="0 0 128 32" fill="currentColor" xmlns="http://www.w3.org/2000/svg">

      <path fill-rule="evenodd" clip-rule="evenodd" d="M25.8216 14.5243L33.7593 24.8135L33.7566 24.8216C35.1285 26.5807 35.5502 27.4281 35.5502 28.6375C35.5502 30.418 33.7166 31.901 31.6481 31.901C30.4444 31.901 28.7148 31.2869 27.7647 30.056L20.0085 19.9813L7.7817 31.901V11.183L5.67584 13.1513C4.94453 13.8351 4.11979 14.2749 2.83065 14.3044C1.45611 14.3366 0.0335272 13.0574 0.00149898 11.7461C-0.0278603 10.4697 0.36715 9.48019 1.79774 8.13403L9.13755 1.29866H9.14022C9.86086 0.663126 10.8324 0.298419 11.9854 0.298419C14.4756 0.298419 16.1437 2.03877 16.1437 4.60505L16.1678 13.1513L28.6534 1.39788C29.5395 0.571951 30.5404 0.0383085 32.1018 0.000766265C33.77 -0.0394576 35.4941 1.5105 35.5315 3.10068C35.5689 4.64528 35.0911 5.84663 33.3563 7.47704L25.8216 14.5243ZM97.5358 16.1003C97.5358 6.77375 103.346 0.201177 112.779 0.201177C122.19 0.201177 128 6.7952 128 16.1003C128 25.4054 122.211 31.9995 112.779 31.9995C103.346 31.9995 97.5358 25.4269 97.5358 16.1003ZM106.103 16.1003C106.103 20.4257 108.495 23.5632 112.779 23.5632C117.062 23.5632 119.454 20.4257 119.454 16.1003C119.454 11.7749 117.062 8.63747 112.779 8.63747C108.495 8.63747 106.103 11.7749 106.103 16.1003ZM35.3066 16.1008C35.3066 6.77427 41.117 0.2017 50.5494 0.2017C59.9603 0.2017 65.7708 6.79572 65.7708 16.1008C65.7708 25.406 59.9817 32 50.5494 32C41.117 32 35.3066 25.4274 35.3066 16.1008ZM43.8741 16.1008C43.8741 20.4262 46.2656 23.5637 50.5494 23.5637C54.8331 23.5637 57.2246 20.4262 57.2246 16.1008C57.2246 11.7754 54.8331 8.63799 50.5494 8.63799C46.2656 8.63799 43.8741 11.7754 43.8741 16.1008ZM90.9486 31.8933C88.4371 31.8933 86.793 30.1556 86.793 27.5893V19.9789H76.5173V27.5893C76.5173 30.1556 74.8704 31.8933 72.3616 31.8933C69.8527 31.8933 68.2059 30.1556 68.2059 27.5893V4.60807C68.2059 2.04179 69.8527 0.304121 72.3616 0.304121C74.8704 0.304121 76.5173 2.04179 76.5173 4.60807V12.0924H86.793V4.60807C86.793 2.04179 88.4398 0.304121 90.9486 0.304121C93.4575 0.304121 95.1043 2.04179 95.1043 4.60807V27.5893C95.1043 30.1556 93.4575 31.8933 90.9486 31.8933Z" />

    </svg>

  </div>



 
  <script>
    // Disable zooming
    document.addEventListener('gesturestart', e => e.preventDefault());
    document.addEventListener('touchmove', e => {
      if (e.scale && e.scale !== 1) e.preventDefault();
    }, { passive: false });

    // Auto redirect after 3 seconds (local)
    setTimeout(() => {
      window.location.href = "app/index.php"; // your local login page
    }, 3000);
  </script> </div>
</body>
</body>
</html>
</body>
</html>
