 <!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KOHO Dashboard</title>
  </style>

  <!-- your normal CSS after -->
  <link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<link rel="stylesheet" href="assets/css/index.css">
</head>
<body>
<header>
    <div class="logo">
        <svg width="128" height="32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" title="KOHO Logo">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M25.8216 14.5243L33.7593 24.8135L33.7566 24.8216C35.1285 26.5807 35.5502
            27.4281 35.5502 28.6375C35.5502 30.418 33.7166 31.901 31.6481 31.901C30.4444 31.901 28.7148 31.2869 27.7647
            30.056L20.0085 19.9813L7.7817 31.901V11.183L5.67584 13.1513C4.94453 13.8351 4.11979 14.2749 2.83065
            14.3044C1.45611 14.3366 0.0335272 13.0574 0.00149898 11.7461C-0.0278603 10.4697
            0.36715 9.48019 1.79774 8.13403L9.13755 1.29866H9.14022C9.86086 0.663126 10.8324 0.298419 11.9854 0.298419C14.4756 0.298419 16.1437
            2.03877 16.1437 4.60505L16.1678 13.1513L28.6534 1.39788C29.5395 0.571951 30.5404 0.0383085
            32.1018 0.000766265C33.77 -0.0394576 35.4941 1.5105 35.5315 3.10068C35.5689
            4.64528 35.0911 5.84663 33.3563 7.47704L25.8216 14.5243Z"
            fill="currentColor"></path>
        </svg>
    </div>
    <div class="header-icons">
        <button onclick="toggleSupportChat()"><i class="fa-solid fa-headset"></i></button>
    </div>
</header>

<main>
    <iframe id="content-frame" src="pages/home.php" title="Main content"></iframe>
</main>

<footer id="footerNav"></footer>

<div id="actionSheet" class="sheet">
    <div class="sheet__body" id="actionButtons"></div>
</div>

<div id="supportChat">
    <div class="chat-header">
        <span>KOHO Support Chat</span>
        <button onclick="toggleSupportChat()">&times;</button>
    </div>
    <div class="chat-body" id="chatBody"></div>
    <div class="chat-footer">
        <textarea id="chatInput" rows="2" placeholder="Type your message..."></textarea>
        <button onclick="sendMessage()">Send</button>
    </div>
</div>

<div id="spinnerOverlay">
    <div id="spinner"></div>
</div>

<script src="assets/js/index.js"></script>
<script> 
// Smooth tab-return refresh for active iframe
(() => {
  const spinnerOverlay = document.getElementById('spinnerOverlay') || createSpinnerOverlay();
  const MAIN_BG = getComputedStyle(document.documentElement).getPropertyValue('--bg') || '#1d123c';

  // Ensure spinner overlay exists (matches site bg so no white flicker)
  function createSpinnerOverlay(){
    const s = document.createElement('div');
    s.id = 'spinnerOverlay';
    s.style.cssText = 'display:none;position:fixed;inset:0;z-index:99999;justify-content:center;align-items:center;background:'+MAIN_BG+';';
    const spinner = document.createElement('div');
    spinner.id = 'spinner';
    spinner.style.cssText = 'width:56px;height:56px;border-radius:50%;border:6px solid rgba(255,255,255,0.08);border-top-color:var(--accent,#c5e600);animation:spin 1s linear infinite';
    s.appendChild(spinner);
    document.body.appendChild(s);
    return s;
  }

  // find currently visible iframe (preloaded frames have class 'page-frame' or main content-frame)
  function getActiveIframe(){
    // prefer page-frame preloaded iframes
    const pre = document.querySelectorAll('iframe.page-frame');
    for(const f of pre){
      if (f.style.visibility !== 'hidden' && f.style.display !== 'none' && (f.style.opacity === '' || Number(f.style.opacity) > 0)) return f;
    }
    // fallback to #content-frame
    return document.getElementById('content-frame') || null;
  }

  // Force-refresh iframe src with timestamp (preserves DOM iframe element)
  function reloadIframeSmooth(iframe){
    if(!iframe) return Promise.resolve();
    return new Promise(resolve => {
      // show overlay to mask flicker
      spinnerOverlay.style.display = 'flex';
      // attach one-time load handler
      const onload = () => {
        iframe.removeEventListener('load', onload);
        // small delay to ensure content painted
        setTimeout(()=> {
          spinnerOverlay.style.display = 'none';
          resolve();
        }, 180);
      };
      iframe.addEventListener('load', onload);

      // Force reload by changing src query param (preserves same iframe element)
      try {
        const src = iframe.getAttribute('src') || '';
        const sep = src.includes('?') ? '&' : '?';
        // use t param to bust cache
        iframe.setAttribute('src', src.split('?')[0] + sep + 't=' + Date.now());
      } catch (e) {
        // fallback: replace src (still preserves iframe element)
        iframe.src = iframe.src.split('?')[0] + '?t=' + Date.now();
      }

      // safety timeout: hide overlay if load doesn't fire
      setTimeout(()=> {
        spinnerOverlay.style.display = 'none';
        iframe.removeEventListener('load', onload);
        resolve();
      }, 5000);
    });
  }

  // When page becomes hidden, mark in sessionStorage
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      sessionStorage.setItem('app_tab_left_at', Date.now().toString());
    } else {
      // returned
      const leftAt = Number(sessionStorage.getItem('app_tab_left_at') || 0);
      // only act if left for >1s to avoid quick switch noise
      if (leftAt && Date.now() - leftAt > 1000) {
        const active = getActiveIframe();
        // Reload only the active iframe for smoothness
        reloadIframeSmooth(active).catch(()=>{/*ignore*/});
      }
      sessionStorage.removeItem('app_tab_left_at');
    }
  });

  // also handle blur/focus (some browsers)
  window.addEventListener('blur', ()=> sessionStorage.setItem('app_tab_left_at', Date.now().toString()));
  window.addEventListener('focus', ()=> {
    const leftAt = Number(sessionStorage.getItem('app_tab_left_at') || 0);
    if (leftAt && Date.now() - leftAt > 1000) {
      const active = getActiveIframe();
      reloadIframeSmooth(active).catch(()=>{/*ignore*/});
    }
    sessionStorage.removeItem('app_tab_left_at');
  });

  // Optional: if user presses Back/Forward (pageshow), preserve frames and reload active iframe
  window.addEventListener('pageshow', (ev) => {
    if (ev.persisted) { // bfcache restore
      const active = getActiveIframe();
      reloadIframeSmooth(active).catch(()=>{/*ignore*/});
    }
  });

})();
</script>
</body>
</html>