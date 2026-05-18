// Install app modal — built entirely in JS, no pre-existing HTML required

(function () {
  var activeModal = null;

  function detectPlatform() {
    var ua = navigator.userAgent.toLowerCase();
    var platform = (navigator.platform || navigator.userAgentData?.platform || "").toLowerCase();
    if (/iphone|ipad|ipod/.test(ua)) return 'ios';
    if (/android/.test(ua)) return 'android';
    // More robust Mac detection (handles Apple Silicon and legacy)
    if (platform.indexOf('mac') !== -1 || /macintosh|mac os x/.test(ua)) return 'mac';
    return 'other';
  }

  function detectBrowser() {
    var ua = navigator.userAgent;
    var vendor = navigator.vendor || "";
    if (/edg/i.test(ua)) return 'edge';
    // Chrome, Chromium, and Chrome on iOS (crios)
    if (/chrome|chromium|crios/i.test(ua)) return 'chrome';
    // Safari specifically (it has 'Safari' but not 'Chrome' or 'Edg', and vendor is Apple)
    if (/safari/i.test(ua) && vendor.indexOf('Apple') !== -1 && !/chrome|chromium|edg/i.test(ua)) return 'safari';
    return 'other';
  }

  // Reusable icon badge — looks like an actual UI button
  function badge(svgOrText, label) {
    var inner = label
      ? '<span style="display:inline-flex;align-items:center;gap:6px;background:var(--surface-alt);border:1px solid var(--border-color);border-radius:8px;padding:4px 10px 4px 8px;font-size:.85rem;font-weight:600;color:var(--text-color);vertical-align:middle;white-space:nowrap;">' + svgOrText + label + '</span>'
      : '<span style="display:inline-flex;align-items:center;justify-content:center;background:var(--surface-alt);border:1px solid var(--border-color);border-radius:8px;padding:5px 8px;font-size:.85rem;font-weight:700;color:var(--text-color);vertical-align:middle;">' + svgOrText + '</span>';
    return inner;
  }

  var ICONS = {
    chrome: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M12 6.5C15.0376 6.5 17.5 8.96243 17.5 12C17.5 15.0376 15.0376 17.5 12 17.5C8.96243 17.5 6.5 15.0376 6.5 12C6.5 8.96243 8.96243 6.5 12 6.5Z" fill="#4285F4"/>
      <path d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2ZM4 12C4 8.25301 6.56846 5.10515 10.0513 4.22552L7.26557 9.05263C6.78289 9.88877 6.5 10.8601 6.5 11.8943H4.07255C4.02458 11.929 4 11.9641 4 12ZM12 20C8.68629 20 5.8601 17.8604 4.81434 14.8943H9.73443C10.5636 14.8943 11.313 14.5445 11.8415 13.9877L14.7344 19.0003C13.9011 19.6457 12.9806 20 12 20ZM19.9274 12C19.9754 11.965 20 11.9299 20 11.8943L17.5726 11.8943C17.5 10.8601 17.2171 9.88877 16.7344 9.05263L13.9487 4.22552C17.4315 5.10515 20 8.25301 20 12ZM12 7C9.23858 7 7 9.23858 7 12C7 14.7614 9.23858 17 12 17C14.7614 17 17 14.7614 17 12C17 9.23858 14.7614 7 12 7Z" fill="#34A853"/>
      <path d="M12 2C6.47715 2 2 6.47715 2 12C2 13.0646 2.1664 14.0734 2.47255 15C3.5183 17.9657 6.3445 20.1051 9.65866 20.1051L12.5415 15.1051C12.9231 14.444 13.0415 13.6706 12.8727 12.9474H19.9274C19.9754 12.6341 20 12.3195 20 12C20 6.47715 15.5228 2 12 2Z" fill="#EA4335"/>
      <path d="M12 22C12.9806 22 13.9011 21.6457 14.7344 21.0003L11.8415 15.9877C11.313 16.5445 10.5636 16.8943 9.73443 16.8943L4.81434 16.8943C5.8601 19.8604 8.68629 22 12 22Z" fill="#FBBC05"/>
    </svg>`,
    safari: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="12" cy="12" r="10" stroke="#007AFF" stroke-width="1.5"/>
      <path d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2Z" fill="#007AFF" fill-opacity="0.1"/>
      <path d="M15 9L13.5 13.5L9 15L10.5 10.5L15 9Z" fill="#EA4335" stroke="#EA4335" stroke-width="1.5" stroke-linejoin="round"/>
      <path d="M12 12L13.5 13.5" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
    </svg>`,
    edge: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12" stroke="#0078D4" stroke-width="1.5" stroke-linecap="round"/>
      <path d="M22 12C22 7.5 19 4 14 4C8 4 4 10 4 15C4 18 6 20 9 20C13 20 13 16 11 14C9 12 14 12 18 12C21 12 22 12 22 12Z" fill="#0078D4"/>
      <path d="M11 14C13 16 13 20 9 20C6 20 4 18 4 15C4 10 8 4 14 4C19 4 22 7.5 22 12" stroke="#34A853" stroke-width="1.5" stroke-linecap="round"/>
    </svg>`,
    shareIos: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>',
    threeDots: '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>',
    addHome:   '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="3"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
    install:   '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>'
  };

  function getInstructionsHTML(platform) {
    if (platform === 'ios') {
      return `
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:1.5rem;background:var(--surface-alt);padding:12px;border-radius:12px;border:1px solid var(--border-color);">
          <div style="flex-shrink:0;">${ICONS.safari}</div>
          <div style="font-size:.9rem;color:var(--text-color);line-height:1.4;">Installing on <strong>iPhone / iPad</strong></div>
        </div>
        <ol style="padding:0;margin:0;list-style:none;display:flex;flex-direction:column;gap:1.2rem;">
          <li style="display:flex;gap:12px;align-items:flex-start;">
            <span style="background:var(--primary-color);color:white;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.8rem;font-weight:700;">1</span>
            <span style="color:var(--text-muted);">Tap the <strong>menu icon</strong> ${badge(ICONS.threeDots)} (if visible) then the <strong>Share</strong> button ${badge(ICONS.shareIos)} in the toolbar.</span>
          </li>
          <li style="display:flex;gap:12px;align-items:flex-start;">
            <span style="background:var(--primary-color);color:white;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.8rem;font-weight:700;">2</span>
            <span style="color:var(--text-muted);">Scroll down and tap <strong>'Add to Home Screen'</strong> ${badge(ICONS.addHome)}.</span>
          </li>
          <li style="display:flex;gap:12px;align-items:flex-start;">
            <span style="background:var(--primary-color);color:white;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.8rem;font-weight:700;">3</span>
            <span style="color:var(--text-muted);">Tap <strong>Add</strong> in the top-right corner to finish.</span>
          </li>
        </ol>`;
    }

    if (platform === 'android') {
      return `
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:1.5rem;background:var(--surface-alt);padding:12px;border-radius:12px;border:1px solid var(--border-color);">
          <div style="flex-shrink:0;">${ICONS.chrome}</div>
          <div style="font-size:.9rem;color:var(--text-color);line-height:1.4;">Installing on <strong>Chrome for Android</strong></div>
        </div>
        <ol style="padding:0;margin:0;list-style:none;display:flex;flex-direction:column;gap:1.2rem;">
          <li style="display:flex;gap:12px;align-items:flex-start;">
            <span style="background:var(--primary-color);color:white;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.8rem;font-weight:700;">1</span>
            <span style="color:var(--text-muted);">Tap the <strong>menu icon</strong> ${badge(ICONS.threeDots)} in the top-right.</span>
          </li>
          <li style="display:flex;gap:12px;align-items:flex-start;">
            <span style="background:var(--primary-color);color:white;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.8rem;font-weight:700;">2</span>
            <span style="color:var(--text-muted);">Select <strong>'Install app'</strong> or <strong>'Add to Home Screen'</strong>.</span>
          </li>
          <li style="display:flex;gap:12px;align-items:flex-start;">
            <span style="background:var(--primary-color);color:white;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.8rem;font-weight:700;">3</span>
            <span style="color:var(--text-muted);">Confirm the installation in the popup.</span>
          </li>
        </ol>`;
    }

    // Desktop — show unified instructions for all major browsers
    return `
      <p style="color:var(--text-muted);margin:0 0 1.25rem;font-size:.9rem;">Select your browser for instructions:</p>
      
      <div style="display:flex;flex-direction:column;gap:1.25rem;">
        <!-- Chrome -->
        <div style="display:flex;align-items:flex-start;gap:12px;padding:14px;background:var(--surface-alt);border:1px solid var(--border-color);border-radius:14px;">
          <div style="flex-shrink:0;">${ICONS.chrome}</div>
          <div>
            <div style="font-weight:800;font-size:1rem;margin-bottom:6px;color:var(--text-color);">Google Chrome</div>
            <div style="font-size:.85rem;color:var(--text-muted);line-height:1.6;">
              Click the <strong>Install icon</strong> ${badge(ICONS.install)} in the address bar (right side) OR use <strong>Menu</strong> → <strong>Save and Share</strong> → <strong>Install</strong>.
            </div>
          </div>
        </div>

        <!-- Microsoft Edge -->
        <div style="display:flex;align-items:flex-start;gap:12px;padding:14px;background:var(--surface-alt);border:1px solid var(--border-color);border-radius:14px;">
          <div style="flex-shrink:0;">${ICONS.edge}</div>
          <div>
            <div style="font-weight:800;font-size:1rem;margin-bottom:6px;color:var(--text-color);">Microsoft Edge</div>
            <div style="font-size:.85rem;color:var(--text-muted);line-height:1.6;">
              Click the <strong>three dots</strong> ${badge(ICONS.threeDots)} → <strong>Apps</strong> → <strong>Install this site as an app</strong>.
            </div>
          </div>
        </div>

        <!-- Safari (Mac) -->
        <div style="display:flex;align-items:flex-start;gap:12px;padding:14px;background:var(--surface-alt);border:1px solid var(--border-color);border-radius:14px;">
          <div style="flex-shrink:0;">${ICONS.safari}</div>
          <div>
            <div style="font-weight:800;font-size:1rem;margin-bottom:6px;color:var(--text-color);">Safari (Mac)</div>
            <div style="font-size:.85rem;color:var(--text-muted);line-height:1.6;">
              Click the <strong>Share</strong> button ${badge(ICONS.shareIos)} in the top toolbar → <strong>Add to Dock</strong>.
            </div>
          </div>
        </div>
      </div>`;
  }


  function closeInstallModal() {

    if (activeModal && activeModal.parentNode) {
      activeModal.parentNode.removeChild(activeModal);
    }
    activeModal = null;
    document.removeEventListener('keydown', onKeyDown);
  }

  function onKeyDown(e) {
    if (e.key === 'Escape') closeInstallModal();
  }

  window.showInstallModal = function () {
    if (activeModal) return;

    var platform = detectPlatform();

    // Backdrop
    var backdrop = document.createElement('div');
    backdrop.className = 'install-modal-container';
    backdrop.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(15,23,42,0.3);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:1rem;box-sizing:border-box;';

    // Card
    var card = document.createElement('div');
    card.setAttribute('role', 'dialog');
    card.setAttribute('aria-modal', 'true');
    card.style.cssText = 'background:var(--surface-color);border-radius:20px;box-shadow:0 20px 50px rgba(0,0,0,0.3);width:100%;max-width:420px;max-height:90vh;overflow-y:auto;font-family:inherit;color:var(--text-color);animation:modal-zoom-in .3s cubic-bezier(0.34, 1.56, 0.64, 1);position:relative;border:1px solid var(--border-color);';

    // Close Button (Fixed Top Right)
    var closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.cssText = 'position:absolute;top:12px;right:16px;background:none;border:none;cursor:pointer;font-size:1.8rem;line-height:1;color:#94a3b8;padding:4px;z-index:2;';
    closeBtn.onclick = closeInstallModal;

    // Content
    var content = document.createElement('div');
    content.style.cssText = 'padding:1.75rem;';

    var title = document.createElement('h2');
    title.textContent = 'Add to Home Screen';
    title.style.cssText = 'margin:0 0 0.5rem;font-size:1.3rem;font-weight:800;letter-spacing:-0.02em;';

    var subtext = document.createElement('p');
    subtext.textContent = 'Install this app on your device for the best experience and offline access.';
    subtext.style.cssText = 'margin:0 0 1.5rem;font-size:.9rem;color:#64748b;line-height:1.5;';

    var instructions = document.createElement('div');
    instructions.innerHTML = getInstructionsHTML(platform);

    content.appendChild(title);
    content.appendChild(subtext);
    content.appendChild(instructions);
    
    card.appendChild(closeBtn);
    card.appendChild(content);
    backdrop.appendChild(card);

    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) closeInstallModal();
    });

    if (!document.getElementById('_im_style')) {
      var style = document.createElement('style');
      style.id = '_im_style';
      style.textContent = '@keyframes modal-zoom-in{from{opacity:0;transform:scale(0.9) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}';
      document.head.appendChild(style);
    }

    document.body.appendChild(backdrop);
    activeModal = backdrop;
    document.addEventListener('keydown', onKeyDown);
  };

  window.closeInstallModal = closeInstallModal;

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
  });
})();
