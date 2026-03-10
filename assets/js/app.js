async function postFormJson(url, form) {
  const fd = new FormData(form);
  if (!fd.has('_csrf') && window.UCCRDCPortal && window.UCCRDCPortal.csrf) {
    fd.append('_csrf', window.UCCRDCPortal.csrf);
  }
  const res = await fetch(url, { method: 'POST', body: fd });
  const data = await res.json().catch(() => ({ ok: false, message: 'Invalid server response.' }));
  return { res, data };
}

function showFormAlert(container, type, message) {
  container.innerHTML = `
    <div class="alert alert-${type} py-2 mb-3" role="alert">
      ${message}
    </div>`;
}

document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('loginForm');
  const registerForm = document.getElementById('registerForm');
  const forgotForm = document.getElementById('forgotForm');
  const authModal = document.getElementById('authModal');
  const sidebar = document.getElementById('sidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');

  function showTab(tabName) {
    const btnId = ({ login: 'tab-login-btn', register: 'tab-register-btn', forgot: 'tab-forgot-btn' })[tabName] || 'tab-login-btn';
    const btn = document.getElementById(btnId);
    if (btn && window.bootstrap) {
      window.bootstrap.Tab.getOrCreateInstance(btn).show();
    }
  }

  // When opening the auth modal, select the requested tab (login/register)
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-bs-target="#authModal"][data-auth-tab]');
    if (!trigger || !authModal) return;
    const tab = trigger.getAttribute('data-auth-tab') || 'login';
    authModal.dataset.pendingTab = tab;
  });

  if (authModal) {
    authModal.addEventListener('shown.bs.modal', () => {
      const tab = authModal.dataset.pendingTab || 'login';
      showTab(tab);
      delete authModal.dataset.pendingTab;
    });
  }

  // Switch tabs inside auth modal
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-switch-tab]');
    if (!trigger) return;
    e.preventDefault();
    showTab(trigger.getAttribute('data-switch-tab') || 'login');
  });

  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const alerts = loginForm.querySelector('[data-alerts]');
      alerts.innerHTML = '';
      const { data } = await postFormJson('/uccrdc/auth/login.php', loginForm);
      if (!data.ok) {
        showFormAlert(alerts, 'danger', data.message || 'Login failed.');
        return;
      }
      window.location.href = data.redirect || '/uccrdc/index.php';
    });
  }

  if (registerForm) {
    const regionSelect = registerForm.querySelector('select[name="region"]');
    const municipalitySelect = document.getElementById('municipalitySelect');
    const postalCodeInput = document.getElementById('postalCodeInput');
    
    // OTP Verification Elements
    const registerEmail = document.getElementById('registerEmail');
    const registrationEmail = document.getElementById('registrationEmail');
    const sendOtpBtn = document.getElementById('sendOtpBtn');
    const otpVerificationStep = document.getElementById('otpVerificationStep');
    const emailVerificationStep = document.getElementById('emailVerificationStep');
    const otpInput = document.getElementById('otpInput');
    const verifyOtpBtn = document.getElementById('verifyOtpBtn');
    const otpTimer = document.getElementById('otpTimer');
    const resendOtpBtn = document.getElementById('resendOtpBtn');
    const mainRegistrationFields = document.getElementById('mainRegistrationFields');
    const registerSubmitBtn = document.getElementById('registerSubmitBtn');
    const alerts = registerForm.querySelector('[data-alerts]');
    
    let otpExpiryTime = null;
    let otpTimerInterval = null;

    // OTP input validation - only allow digits and limit to 6
    if (otpInput) {
      otpInput.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 6);
      });

      // Auto-verify when 6 digits are entered
      otpInput.addEventListener('keyup', (e) => {
        if (e.target.value.length === 6 && !verifyOtpBtn.disabled) {
          verifyOtpBtn.click();
        }
      });
    }

    // Send OTP
    if (sendOtpBtn) {
      sendOtpBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        const email = registerEmail.value.trim();
        
        if (!email) {
          showFormAlert(alerts, 'danger', 'Please enter your email.');
          return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          showFormAlert(alerts, 'danger', 'Please enter a valid email.');
          return;
        }

        sendOtpBtn.disabled = true;
        sendOtpBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';

        try {
          const fd = new FormData();
          fd.append('email', email);
          fd.append('_csrf', window.UCCRDCPortal?.csrf || registerForm.querySelector('[name="_csrf"]').value);

          const res = await fetch('/uccrdc/auth/send_otp.php', { method: 'POST', body: fd });
          const data = await res.json();

          if (data.ok) {
            registrationEmail.value = email;
            registerEmail.disabled = true;
            sendOtpBtn.style.display = 'none';
            emailVerificationStep.style.borderBottom = 'none';
            otpVerificationStep.style.display = '';
            otpExpiryTime = Date.now() + (data.expires_in * 1000);
            
            showFormAlert(alerts, 'success', 'OTP sent to your email. Check your inbox.');
            startOtpTimer();
            otpInput.focus();
          } else {
            const msg = data.errors?.email || data.message || 'Failed to send OTP';
            showFormAlert(alerts, 'danger', msg);
          }
        } catch (error) {
          console.error('Send OTP error:', error);
          showFormAlert(alerts, 'danger', 'Failed to send OTP. Please try again.');
        } finally {
          sendOtpBtn.disabled = false;
          sendOtpBtn.textContent = 'Send OTP';
        }
      });
    }

    // Start OTP Timer
    function startOtpTimer() {
      if (otpTimerInterval) clearInterval(otpTimerInterval);
      
      otpTimerInterval = setInterval(() => {
        const remaining = Math.max(0, Math.floor((otpExpiryTime - Date.now()) / 1000));
        const mins = Math.floor(remaining / 60);
        const secs = remaining % 60;
        
        otpTimer.textContent = `Code expires in ${mins}:${String(secs).padStart(2, '0')}`;
        resendOtpBtn.style.display = remaining < 60 && remaining > 0 ? 'inline' : 'none';

        if (remaining === 0) {
          clearInterval(otpTimerInterval);
          otpTimer.textContent = 'OTP expired. ';
          otpInput.disabled = true;
          verifyOtpBtn.disabled = true;
          resendOtpBtn.style.display = 'inline';
        }
      }, 1000);

      otpTimer.textContent = 'OTP sent. Code expires in 10:00';
      resendOtpBtn.style.display = 'none';
    }

    // Resend OTP
    if (resendOtpBtn) {
      resendOtpBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (sendOtpBtn) sendOtpBtn.click();
      });
    }

    // Verify OTP
    if (verifyOtpBtn) {
      verifyOtpBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        const otp = otpInput.value.trim();
        const email = registrationEmail.value;

        if (!otp || otp.length !== 6) {
          showFormAlert(alerts, 'danger', 'Please enter a valid 6-digit code.');
          return;
        }

        verifyOtpBtn.disabled = true;
        verifyOtpBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying...';

        try {
          const fd = new FormData();
          fd.append('email', email);
          fd.append('otp', otp);
          fd.append('_csrf', window.UCCRDCPortal?.csrf || registerForm.querySelector('[name="_csrf"]').value);

          const res = await fetch('/uccrdc/auth/verify_otp.php', { method: 'POST', body: fd });
          const data = await res.json();

          if (data.ok) {
            otpVerificationStep.style.display = 'none';
            mainRegistrationFields.style.display = '';
            registerSubmitBtn.disabled = false;
            showFormAlert(alerts, 'success', 'Email verified! Now complete your registration.');
            if (otpTimerInterval) clearInterval(otpTimerInterval);
          } else {
            const msg = data.errors?.otp || data.message || 'Invalid OTP';
            showFormAlert(alerts, 'danger', msg);
          }
        } catch (error) {
          console.error('Verify OTP error:', error);
          showFormAlert(alerts, 'danger', 'Verification failed. Please try again.');
        } finally {
          verifyOtpBtn.disabled = false;
          verifyOtpBtn.textContent = 'Verify';
        }
      });
    }

    // Handle region change to populate municipalities
    if (regionSelect && municipalitySelect) {
      regionSelect.addEventListener('change', async (e) => {
        const region = e.target.value;
        municipalitySelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
        postalCodeInput.value = '';

        if (!region) {
          municipalitySelect.innerHTML = '<option value="" disabled selected>Select Municipality</option>';
          return;
        }

        try {
          const response = await fetch(`/uccrdc/files/get_municipalities.php?region=${encodeURIComponent(region)}`);
          const result = await response.json();

          if (result.ok && Array.isArray(result.data)) {
            municipalitySelect.innerHTML = '<option value="" disabled selected>Select Municipality</option>';
            result.data.forEach(municipality => {
              const option = document.createElement('option');
              option.value = municipality.name;
              option.textContent = municipality.name;
              option.dataset.postalCode = municipality.postal_code;
              municipalitySelect.appendChild(option);
            });
          } else {
            console.error('API response error:', result);
            municipalitySelect.innerHTML = '<option value="" disabled selected>No municipalities found</option>';
          }
        } catch (error) {
          console.error('Error fetching municipalities:', error);
          municipalitySelect.innerHTML = '<option value="" disabled selected>Error loading municipalities</option>';
        }
      });
    }

    // Handle municipality change to auto-fill postal code
    if (municipalitySelect && postalCodeInput) {
      municipalitySelect.addEventListener('change', (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex];
        postalCodeInput.value = selectedOption.dataset.postalCode || '';
      });
    }

    registerForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      alerts.innerHTML = '';
      const { data } = await postFormJson('/uccrdc/auth/register.php', registerForm);
      if (!data.ok) {
        if (data.errors && typeof data.errors === 'object') {
          const list = Object.values(data.errors).filter(Boolean);
          if (list.length) {
            showFormAlert(alerts, 'danger', `<ul class="mb-0">${list.map(x => `<li>${x}</li>`).join('')}</ul>`);
            return;
          }
        }
        showFormAlert(alerts, 'danger', data.message || 'Registration failed.');
        return;
      }
      window.location.href = data.redirect || '/uccrdc/index.php';
    });
  }

  if (forgotForm) {
    forgotForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const alerts = forgotForm.querySelector('[data-alerts]');
      alerts.innerHTML = '';
      const { data } = await postFormJson('/uccrdc/auth/forgot_request.php', forgotForm);
      if (!data.ok) {
        showFormAlert(alerts, 'danger', data.message || 'Request failed.');
        return;
      }
      showFormAlert(alerts, 'success', data.message || 'If the email exists, a reset link was sent.');
    });
  }

  // Former publication toggle.
  const formerlyYes = document.getElementById('formerly_yes');
  const formerlyNo = document.getElementById('formerly_no');
  const formerSection = document.getElementById('formerPublicationSection');
  function syncFormerSection() {
    if (!formerSection) return;
    const show = formerlyYes && formerlyYes.checked;
    formerSection.classList.toggle('d-none', !show);
    formerSection.querySelectorAll('input,select,textarea').forEach(el => {
      if (show) {
        el.removeAttribute('disabled');
      } else {
        el.setAttribute('disabled', 'disabled');
        if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
        else el.value = '';
      }
    });
  }
  if (formerlyYes || formerlyNo) {
    if (formerlyYes) formerlyYes.addEventListener('change', syncFormerSection);
    if (formerlyNo) formerlyNo.addEventListener('change', syncFormerSection);
    syncFormerSection();
  }

  // Dashboard sidebar toggle + overlay + submenu toggle
  if (sidebar && sidebarToggle) {
    function removeOverlay() {
      const overlay = document.getElementById('sidebarOverlay');
      if (overlay) overlay.remove();
    }

    function showOverlay() {
      removeOverlay();
      document.body.insertAdjacentHTML(
        'beforeend',
        '<div id="sidebarOverlay" style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:1039;background:rgba(0,0,0,0.25)"></div>'
      );
      const overlay = document.getElementById('sidebarOverlay');
      if (overlay) {
        overlay.addEventListener('click', () => {
          sidebar.classList.remove('show');
          removeOverlay();
        });
      }
    }

    function handleResize() {
      if (window.innerWidth <= 991.98) {
        sidebarToggle.style.display = 'block';
      } else {
        sidebarToggle.style.display = 'none';
        sidebar.classList.remove('show');
        removeOverlay();
      }
    }

    handleResize();
    window.addEventListener('resize', handleResize);

    sidebarToggle.addEventListener('click', () => {
      sidebar.classList.toggle('show');
      if (sidebar.classList.contains('show')) {
        showOverlay();
      } else {
        removeOverlay();
      }
    });

    // Submenu toggles
    document.addEventListener('click', (e) => {
      const t = e.target.closest('[data-submenu-toggle]');
      if (!t) return;
      e.preventDefault();
      const id = t.getAttribute('data-submenu-toggle');
      if (!id) return;
      const menu = document.getElementById(id);
      if (!menu) return;
      menu.classList.toggle('show');
    });
  }

  // Password visibility toggle (eye button)
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-toggle-password');
    if (!btn) return;

    const targetSelector = btn.getAttribute('data-target') || '';
    let input = null;
    if (targetSelector) {
      input = document.querySelector(targetSelector);
    }
    if (!input) {
      const group = btn.closest('.input-group');
      input = group ? group.querySelector('input') : null;
    }
    if (!input) return;

    const wasPassword = (input.getAttribute('type') || '').toLowerCase() !== 'text';
    try {
      input.type = wasPassword ? 'text' : 'password';
    } catch {
      input.setAttribute('type', wasPassword ? 'text' : 'password');
    }

    const icon = btn.querySelector('i');
    if (icon) {
      icon.classList.remove('fa-eye', 'fa-eye-slash');
      icon.classList.add(wasPassword ? 'fa-eye-slash' : 'fa-eye');
    }
    btn.setAttribute('aria-label', wasPassword ? 'Hide password' : 'Show password');
  });
});
