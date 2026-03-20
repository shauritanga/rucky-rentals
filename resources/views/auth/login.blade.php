<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rucky Rentals - Sign In</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root[data-theme="dark"] {
    --bg-base: #0f1012;
    --bg-surface: #16181c;
    --bg-elevated: #1c1e23;
    --bg-hover: #22252b;
    --border: rgba(255,255,255,.08);
    --border-subtle: rgba(255,255,255,.05);
    --text-primary: #f0f1f3;
    --text-secondary: #9ba3b0;
    --text-muted: #5c6470;
    --accent: #3b82f6;
    --accent-dim: rgba(59,130,246,.12);
    --red: #ef4444;
    --red-dim: rgba(239,68,68,.12);
    --green: #22c55e;
    --green-dim: rgba(34,197,94,.12);
    --amber: #f59e0b;
    --shadow: 0 20px 60px rgba(0,0,0,.5);
  }

  :root[data-theme="light"] {
    --bg-base: #f4f5f7;
    --bg-surface: #ffffff;
    --bg-elevated: #f0f1f3;
    --bg-hover: #e8eaed;
    --border: rgba(0,0,0,.08);
    --border-subtle: rgba(0,0,0,.05);
    --text-primary: #0f1012;
    --text-secondary: #4b5563;
    --text-muted: #9ca3af;
    --accent: #2563eb;
    --accent-dim: rgba(37,99,235,.10);
    --red: #dc2626;
    --red-dim: rgba(220,38,38,.10);
    --green: #16a34a;
    --green-dim: rgba(22,163,74,.10);
    --amber: #d97706;
    --shadow: 0 20px 60px rgba(0,0,0,.12);
  }

  html, body {
    height: 100%;
    font-family: 'DM Sans', sans-serif;
    background: var(--bg-base);
    color: var(--text-primary);
  }

  .login-wrapper {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1fr 1fr;
  }

  .left-panel {
    background: linear-gradient(145deg, #0a0c10 0%, #111318 50%, #0d1117 100%);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 48px 56px;
    position: relative;
    overflow: hidden;
    border-right: 1px solid var(--border);
  }

  .left-panel::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
      linear-gradient(rgba(59,130,246,.04) 1px, transparent 1px),
      linear-gradient(90deg, rgba(59,130,246,.04) 1px, transparent 1px);
    background-size: 40px 40px;
  }

  .left-panel::after {
    content: '';
    position: absolute;
    top: -120px; left: -80px;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(59,130,246,.08) 0%, transparent 70%);
    pointer-events: none;
  }

  .orb-2 {
    position: absolute;
    bottom: -100px; right: -60px;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(139,92,246,.06) 0%, transparent 70%);
    pointer-events: none;
  }

  .left-brand {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .brand-mark {
    width: 36px; height: 36px;
    background: var(--accent);
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Instrument Serif', serif;
    font-style: italic;
    font-size: 18px;
    color: #fff;
    flex-shrink: 0;
  }

  .brand-name {
    font-size: 17px;
    font-weight: 600;
    letter-spacing: -.3px;
    color: #f0f1f3;
  }

  .brand-tagline {
    font-size: 12px;
    color: rgba(240,241,243,.4);
    margin-top: 2px;
  }

  .left-hero {
    position: relative;
    z-index: 1;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 40px 0;
  }

  .hero-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 16px;
  }

  .hero-title {
    font-family: 'Instrument Serif', serif;
    font-size: 46px;
    font-weight: 400;
    line-height: 1.15;
    color: #f0f1f3;
    margin-bottom: 20px;
    max-width: 480px;
  }

  .hero-title em {
    color: rgba(240,241,243,.45);
    font-style: italic;
  }

  .hero-desc {
    font-size: 15px;
    color: rgba(240,241,243,.5);
    line-height: 1.6;
    max-width: 400px;
    margin-bottom: 40px;
  }

  .hero-stats {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
  }

  .hero-stat {
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 10px;
    padding: 12px 18px;
  }

  .hero-stat-val {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -.5px;
    color: #f0f1f3;
  }

  .hero-stat-label {
    font-size: 11.5px;
    color: rgba(240,241,243,.4);
    margin-top: 2px;
  }

  .left-footer {
    position: relative;
    z-index: 1;
    font-size: 12px;
    color: rgba(240,241,243,.25);
  }

  .right-panel {
    background: var(--bg-surface);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 56px 48px;
    position: relative;
  }

  .theme-btn {
    position: absolute;
    top: 24px; right: 24px;
    width: 34px; height: 34px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--bg-elevated);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s, color .15s;
  }

  .theme-btn:hover { background: var(--bg-hover); color: var(--text-primary); }

  .login-form-wrap {
    max-width: 440px;
    width: 100%;
    margin: 0 auto;
  }

  .form-heading {
    font-size: 26px;
    font-weight: 700;
    letter-spacing: -.5px;
    margin-bottom: 6px;
  }

  .form-subheading {
    font-size: 14px;
    color: var(--text-secondary);
    margin-bottom: 36px;
    line-height: 1.5;
  }

  .form-group { margin-bottom: 18px; }

  .form-label {
    display: block;
    font-size: 12.5px;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 7px;
    letter-spacing: .1px;
  }

  .form-input {
    width: 100%;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: 11px 14px;
    font-size: 14px;
    font-family: inherit;
    color: var(--text-primary);
    outline: none;
    transition: border-color .15s, box-shadow .15s;
  }

  .form-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-dim);
  }

  .form-input::placeholder { color: var(--text-muted); }
  .form-input.error { border-color: var(--red); box-shadow: 0 0 0 3px var(--red-dim); }

  .password-wrap { position: relative; }
  .password-wrap .form-input { padding-right: 44px; }

  .pw-toggle {
    position: absolute;
    right: 12px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 4px;
    display: flex; align-items: center;
    transition: color .12s;
  }

  .pw-toggle:hover { color: var(--text-secondary); }

  .form-options {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 26px;
    font-size: 13px;
  }

  .remember-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    cursor: pointer;
  }

  .remember-wrap input[type="checkbox"] {
    width: 15px; height: 15px;
    accent-color: var(--accent);
    cursor: pointer;
  }

  .forgot-link {
    color: var(--accent);
    background: none;
    border: none;
    font-family: inherit;
    font-size: 13px;
    cursor: pointer;
    padding: 0;
    transition: opacity .15s;
  }

  .forgot-link:hover { opacity: .75; }

  .btn-login {
    width: 100%;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 9px;
    padding: 12px;
    font-size: 14.5px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: opacity .15s, transform .1s;
    display: flex; align-items: center; justify-content: center;
    gap: 8px;
    letter-spacing: -.1px;
  }

  .btn-login:hover { opacity: .9; }
  .btn-login:active { transform: scale(.985); }
  .btn-login:disabled { opacity: .6; cursor: not-allowed; }

  .btn-login .spinner {
    width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin .7s linear infinite;
    display: none;
  }

  @keyframes spin { to { transform: rotate(360deg); } }

  .error-msg {
    background: var(--red-dim);
    border: 1px solid rgba(239,68,68,.2);
    border-radius: 8px;
    padding: 11px 14px;
    font-size: 13px;
    color: var(--red);
    margin-bottom: 20px;
    display: none;
    align-items: center;
    gap: 8px;
  }

  .error-msg.show { display: flex; }

  .demo-hint {
    margin-top: 28px;
    padding-top: 24px;
    border-top: 1px solid var(--border-subtle);
  }

  .demo-hint-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 10px;
  }

  .demo-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: background .12s;
    border: 1px solid transparent;
    margin-bottom: 6px;
  }

  .demo-user:hover {
    background: var(--bg-elevated);
    border-color: var(--border);
  }

  .demo-user:last-child { margin-bottom: 0; }

  .demo-avatar {
    width: 30px; height: 30px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700;
    flex-shrink: 0;
  }

  .demo-user-info { flex: 1; min-width: 0; }
  .demo-user-name { font-size: 13px; font-weight: 500; }
  .demo-user-email { font-size: 11.5px; color: var(--text-muted); }

  .demo-role-badge {
    font-size: 10px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
    white-space: nowrap;
  }

  .toast {
    position: fixed;
    bottom: 24px; left: 50%;
    transform: translateX(-50%) translateY(12px);
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 18px;
    font-size: 13.5px;
    color: var(--text-primary);
    box-shadow: var(--shadow);
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s, transform .2s;
    white-space: nowrap;
    z-index: 999;
  }

  .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

  @media (max-width: 760px) {
    .login-wrapper { grid-template-columns: 1fr; }
    .left-panel { display: none; }
    .right-panel { padding: 40px 24px; }
  }
</style>
</head>
<body>
<div class="login-wrapper">
  <div class="left-panel">
    <div class="orb-2"></div>

    <div class="left-brand">
      <div class="brand-mark">R</div>
      <div>
        <div class="brand-name">Rucky Rentals</div>
        <div class="brand-tagline">Property Management System</div>
      </div>
    </div>

    <div class="left-hero">
      <div class="hero-label">Commercial Property Management</div>
      <div class="hero-title">
        Manage every building,<br>
        <em>from one place.</em>
      </div>
      <div class="hero-desc">
        Leases, invoices, maintenance, electricity billing and accounting - all in one system built for Dar es Salaam commercial real estate.
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><div class="hero-stat-val">4</div><div class="hero-stat-label">Properties</div></div>
        <div class="hero-stat"><div class="hero-stat-val">127</div><div class="hero-stat-label">Total Units</div></div>
        <div class="hero-stat"><div class="hero-stat-val">108</div><div class="hero-stat-label">Active Leases</div></div>
        <div class="hero-stat"><div class="hero-stat-val">TZS 231M</div><div class="hero-stat-label">Monthly Revenue</div></div>
      </div>
    </div>

    <div class="left-footer">© 2026 Rucky Rentals Ltd - Dar es Salaam, Tanzania</div>
  </div>

  <div class="right-panel">
    <button class="theme-btn" onclick="toggleTheme()" title="Toggle theme" type="button">
      <svg id="themeIcon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
    </button>

    <div class="login-form-wrap">
      <div class="form-heading">Welcome back</div>
      <div class="form-subheading">Sign in to your Rucky Rentals account</div>

      <div class="error-msg" id="errorMsg">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span id="errorText">Invalid email or password.</span>
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input class="form-input" type="email" id="email" placeholder="you@ruckyrentals.co.tz" autocomplete="email" onkeydown="if(event.key==='Enter')tryLogin()">
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="password-wrap">
          <input class="form-input" type="password" id="password" placeholder="Enter your password" autocomplete="current-password" onkeydown="if(event.key==='Enter')tryLogin()">
          <button class="pw-toggle" onclick="togglePassword()" type="button" tabindex="-1">
            <svg id="pwIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <div class="form-options">
        <label class="remember-wrap"><input type="checkbox" id="rememberMe" checked>Remember me</label>
        <button class="forgot-link" onclick="showForgot()" type="button">Forgot password?</button>
      </div>

      <button class="btn-login" id="loginBtn" onclick="tryLogin()" type="button">
        <div class="spinner" id="loginSpinner"></div>
        <span id="loginBtnText">Sign In</span>
        <svg id="loginArrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </button>

      <div class="demo-hint">
        <div class="demo-hint-label">Demo Accounts - click to fill</div>

        <div class="demo-user" onclick="fillCredentials('admin@ruckyrentals.co.tz','admin123')">
          <div class="demo-avatar" style="background:rgba(139,92,246,.15);color:#a78bfa">SA</div>
          <div class="demo-user-info">
            <div class="demo-user-name">Super Admin</div>
            <div class="demo-user-email">admin@ruckyrentals.co.tz</div>
          </div>
          <span class="demo-role-badge" style="background:rgba(139,92,246,.12);color:#a78bfa">Superuser</span>
        </div>

        <div class="demo-user" onclick="fillCredentials('james@ruckyrentals.co.tz','james123')">
          <div class="demo-avatar" style="background:rgba(59,130,246,.12);color:#3b82f6">JM</div>
          <div class="demo-user-info">
            <div class="demo-user-name">James Mwangi</div>
            <div class="demo-user-email">james@ruckyrentals.co.tz</div>
          </div>
          <span class="demo-role-badge" style="background:rgba(59,130,246,.10);color:#3b82f6">Manager</span>
        </div>

        <div class="demo-user" onclick="fillCredentials('grace@ruckyrentals.co.tz','grace123')">
          <div class="demo-avatar" style="background:rgba(34,197,94,.10);color:#22c55e">GW</div>
          <div class="demo-user-info">
            <div class="demo-user-name">Grace Wanjiru</div>
            <div class="demo-user-email">grace@ruckyrentals.co.tz</div>
          </div>
          <span class="demo-role-badge" style="background:rgba(34,197,94,.10);color:#22c55e">Manager</span>
        </div>

        <div class="demo-user" onclick="fillCredentials('diana@ruckyrentals.co.tz','diana123')">
          <div class="demo-avatar" style="background:rgba(245,158,11,.10);color:#f59e0b">DO</div>
          <div class="demo-user-info">
            <div class="demo-user-name">Diana Ochieng</div>
            <div class="demo-user-email">diana@ruckyrentals.co.tz</div>
          </div>
          <span class="demo-role-badge" style="background:rgba(245,158,11,.10);color:#f59e0b">Accountant</span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
  const USERS = [
    { email: 'admin@ruckyrentals.co.tz', password: 'admin123', name: 'Super Admin', redirect: '/' },
    { email: 'james@ruckyrentals.co.tz', password: 'james123', name: 'James Mwangi', redirect: '/' },
    { email: 'grace@ruckyrentals.co.tz', password: 'grace123', name: 'Grace Wanjiru', redirect: '/' },
    { email: 'diana@ruckyrentals.co.tz', password: 'diana123', name: 'Diana Ochieng', redirect: '/accounting' }
  ];

  let failedAttempts = 0;
  let lockUntil = null;

  function tryLogin() {
    const emailEl = document.getElementById('email');
    const pwEl = document.getElementById('password');
    const email = emailEl.value.trim().toLowerCase();
    const pw = pwEl.value;

    if (lockUntil && Date.now() < lockUntil) {
      const secs = Math.ceil((lockUntil - Date.now()) / 1000);
      showError(`Too many failed attempts. Try again in ${secs}s.`);
      return;
    }

    if (!email) { emailEl.classList.add('error'); emailEl.focus(); return; }
    if (!pw) { pwEl.classList.add('error'); pwEl.focus(); return; }
    emailEl.classList.remove('error');
    pwEl.classList.remove('error');

    setLoading(true);

    setTimeout(() => {
      const user = USERS.find((u) => u.email === email && u.password === pw);

      if (!user) {
        failedAttempts++;
        setLoading(false);
        if (failedAttempts >= 5) {
          lockUntil = Date.now() + 30000;
          showError('Account temporarily locked after 5 failed attempts. Try again in 30s.');
        } else {
          const left = 5 - failedAttempts;
          showError(`Invalid email or password. ${left} attempt${left !== 1 ? 's' : ''} remaining.`);
        }
        pwEl.value = '';
        pwEl.focus();
        return;
      }

      failedAttempts = 0;
      try {
        if (document.getElementById('rememberMe').checked) {
          localStorage.setItem('rucky_remember', email);
        } else {
          localStorage.removeItem('rucky_remember');
        }
      } catch (e) {}

      showToast(`Welcome back, ${user.name.split(' ')[0]}`);

      setTimeout(() => {
        window.location.href = user.redirect;
      }, 700);
    }, 120);
  }

  function setLoading(on) {
    const btn = document.getElementById('loginBtn');
    const spinner = document.getElementById('loginSpinner');
    const text = document.getElementById('loginBtnText');
    const arrow = document.getElementById('loginArrow');
    btn.disabled = on;
    spinner.style.display = on ? 'block' : 'none';
    text.textContent = on ? 'Signing in...' : 'Sign In';
    arrow.style.display = on ? 'none' : '';
  }

  function showError(msg) {
    const el = document.getElementById('errorMsg');
    document.getElementById('errorText').textContent = msg;
    el.classList.add('show');
    el.style.animation = 'none';
    el.offsetHeight;
    el.style.animation = 'shake .35s ease';
  }

  function hideError() {
    document.getElementById('errorMsg').classList.remove('show');
  }

  ['email', 'password'].forEach((id) => {
    document.getElementById(id).addEventListener('input', () => {
      hideError();
      document.getElementById(id).classList.remove('error');
    });
  });

  function togglePassword() {
    const pw = document.getElementById('password');
    const icon = document.getElementById('pwIcon');
    if (pw.type === 'password') {
      pw.type = 'text';
      icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
      pw.type = 'password';
      icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
  }

  function fillCredentials(email, password) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
    hideError();
    document.getElementById('email').classList.remove('error');
    document.getElementById('password').classList.remove('error');
    document.getElementById('email').focus();
    showToast('Credentials filled - click Sign In');
  }

  function showForgot() {
    showToast('Password reset: contact admin@ruckyrentals.co.tz');
  }

  function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 2800);
  }

  function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', isDark ? 'light' : 'dark');
    const icon = document.getElementById('themeIcon');
    icon.innerHTML = isDark
      ? '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>'
      : '<path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>';
    try { localStorage.setItem('rucky_theme', isDark ? 'light' : 'dark'); } catch (e) {}
  }

  const shakeStyle = document.createElement('style');
  shakeStyle.textContent = '@keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-6px)}40%{transform:translateX(6px)}60%{transform:translateX(-4px)}80%{transform:translateX(4px)}}';
  document.head.appendChild(shakeStyle);

  window.addEventListener('DOMContentLoaded', () => {
    try {
      const remembered = localStorage.getItem('rucky_remember');
      if (remembered) {
        document.getElementById('email').value = remembered;
        document.getElementById('password').focus();
      }
      const theme = localStorage.getItem('rucky_theme');
      if (theme) document.documentElement.setAttribute('data-theme', theme);
    } catch (e) {}
  });
</script>
</body>
</html>
