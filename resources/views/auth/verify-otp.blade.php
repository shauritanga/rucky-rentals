<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mwamba Properties - Verify Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root[data-theme="dark"] {
            --bg-base: #0f1012;
            --bg-surface: #16181c;
            --bg-elevated: #1c1e23;
            --bg-hover: #22252b;
            --border: rgba(255, 255, 255, .08);
            --border-subtle: rgba(255, 255, 255, .05);
            --text-primary: #f0f1f3;
            --text-secondary: #9ba3b0;
            --text-muted: #5c6470;
            --accent: #3b82f6;
            --accent-dim: rgba(59, 130, 246, .12);
            --red: #ef4444;
            --red-dim: rgba(239, 68, 68, .12);
            --green: #22c55e;
            --green-dim: rgba(34, 197, 94, .12);
            --amber: #f59e0b;
            --amber-dim: rgba(245, 158, 11, .12);
            --shadow: 0 20px 60px rgba(0, 0, 0, .5);
        }

        :root[data-theme="light"] {
            --bg-base: #f4f5f7;
            --bg-surface: #ffffff;
            --bg-elevated: #f0f1f3;
            --bg-hover: #e8eaed;
            --border: rgba(0, 0, 0, .08);
            --border-subtle: rgba(0, 0, 0, .05);
            --text-primary: #0f1012;
            --text-secondary: #4b5563;
            --text-muted: #9ca3af;
            --accent: #2563eb;
            --accent-dim: rgba(37, 99, 235, .10);
            --red: #dc2626;
            --red-dim: rgba(220, 38, 38, .10);
            --green: #16a34a;
            --green-dim: rgba(22, 163, 74, .10);
            --amber: #d97706;
            --amber-dim: rgba(217, 119, 6, .10);
            --shadow: 0 20px 60px rgba(0, 0, 0, .12);
        }

        html,
        body {
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
                linear-gradient(rgba(59, 130, 246, .04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59, 130, 246, .04) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        .left-panel::after {
            content: '';
            position: absolute;
            top: -120px;
            left: -80px;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(59, 130, 246, .08) 0%, transparent 70%);
            pointer-events: none;
        }

        .orb-2 {
            position: absolute;
            bottom: -100px;
            right: -60px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(139, 92, 246, .06) 0%, transparent 70%);
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
            width: 36px;
            height: 36px;
            background: var(--accent);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
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
            color: rgba(240, 241, 243, .4);
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
            color: rgba(240, 241, 243, .45);
            font-style: italic;
        }

        .hero-desc {
            font-size: 15px;
            color: rgba(240, 241, 243, .5);
            line-height: 1.6;
            max-width: 400px;
            margin-bottom: 40px;
        }

        .hero-features {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 8px;
        }

        .hero-feature {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.82);
            font-weight: 500;
        }

        .hero-feature svg {
            flex-shrink: 0;
            opacity: 0.9;
        }

        .left-footer {
            position: relative;
            z-index: 1;
            font-size: 12px;
            color: rgba(240, 241, 243, .25);
        }

        .right-panel {
            background: var(--bg-surface);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 32px 36px;
            position: relative;
        }

        .theme-btn {
            position: absolute;
            top: 24px;
            right: 24px;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-elevated);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .15s, color .15s;
        }

        .theme-btn:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .login-form-wrap {
            max-width: 360px;
            width: 100%;
            margin: 0 auto;
        }

        .form-heading {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -.3px;
            line-height: 1.1;
            margin-bottom: 4px;
        }

        .form-subheading {
            font-size: 11px;
            color: var(--text-secondary);
            line-height: 1.3;
            margin-bottom: 8px;
        }

        .email-callout {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
            font-size: 11px;
            color: var(--text-secondary);
        }

        .email-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0;
            text-transform: none;
            color: var(--text-secondary);
            margin-bottom: 0;
        }

        .email-address {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0;
        }

        .status {
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 11.5px;
            line-height: 1.4;
            margin-bottom: 8px;
            border: 1px solid transparent;
        }

        .status.error {
            background: var(--red-dim);
            border-color: rgba(239, 68, 68, .2);
            color: var(--red);
        }

        .status.success {
            background: var(--green-dim);
            border-color: rgba(34, 197, 94, .2);
            color: var(--green);
        }

        .otp-label {
            display: block;
            font-size: 10.5px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .otp-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 6px;
            margin-bottom: 8px;
        }

        .otp-digit {
            width: 100%;
            height: 40px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-elevated);
            color: var(--text-primary);
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            outline: none;
            transition: border-color .15s, box-shadow .15s, transform .12s, background .15s;
        }

        .otp-digit:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-dim);
            transform: translateY(-1px);
        }

        .otp-digit.filled {
            background: color-mix(in srgb, var(--bg-elevated) 85%, var(--accent) 15%);
        }

        .meta-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 12px;
            margin-bottom: 8px;
            font-size: 10.5px;
            color: var(--text-secondary);
        }

        .meta-card {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: none;
            border-radius: 0;
            padding: 0;
            background: transparent;
        }

        .meta-card-label {
            font-size: 10.5px;
            font-weight: 500;
            letter-spacing: 0;
            text-transform: none;
            color: var(--text-muted);
            margin-bottom: 0;
        }

        .meta-card-value {
            font-size: 10.5px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .btn-login {
            width: 100%;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 10px;
            font-size: 12.5px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: opacity .15s, transform .1s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: -.1px;
        }

        .btn-login:hover {
            opacity: .92;
        }

        .btn-login:active {
            transform: scale(.985);
        }

        .btn-login:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .btn-login .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, .3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: none;
        }

        .secondary-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 8px;
        }

        .link-btn,
        .link {
            background: none;
            border: none;
            color: var(--accent);
            font: inherit;
            cursor: pointer;
            text-decoration: none;
            padding: 0;
        }

        .link-btn:disabled {
            color: var(--text-muted);
            cursor: not-allowed;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 920px) {
            .login-wrapper {
                grid-template-columns: 1fr;
            }

            .left-panel {
                display: none;
            }

            .right-panel {
                padding: 22px 16px;
            }

        }

        @media (max-width: 560px) {
            .right-panel {
                padding: 18px 12px 24px;
            }

            .theme-btn {
                top: 16px;
                right: 16px;
            }

            .form-heading {
                font-size: 18px;
            }

            .otp-grid {
                gap: 6px;
            }

            .otp-digit {
                height: 38px;
                font-size: 15px;
                border-radius: 8px;
            }

            .meta-grid {
                flex-direction: column;
                align-items: flex-start;
            }

            .secondary-actions {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <div class="left-panel">
            <div class="orb-2"></div>

            <div class="left-brand">
                <div class="brand-mark">M</div>
                <div>
                    <div class="brand-name">Mwamba Properties</div>
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
                <div class="hero-features">
                    <div class="hero-feature">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        Property &amp; unit management
                    </div>
                    <div class="hero-feature">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        Rent collection &amp; invoicing
                    </div>
                    <div class="hero-feature">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                        Electricity &amp; utility tracking
                    </div>
                    <div class="hero-feature">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        Maintenance &amp; reporting
                    </div>
                </div>
            </div>

            <div class="left-footer">© 2026 Mwamba Properties Ltd - Dar es Salaam, Tanzania</div>
        </div>

        <div class="right-panel">
            <button class="theme-btn" onclick="toggleTheme()" title="Toggle theme" type="button">
                <svg id="themeIcon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
                </svg>
            </button>

            <div class="login-form-wrap">
                <div class="form-heading">Verify your login</div>
                <div class="form-subheading">
                    A 6-digit verification code was sent to <span class="email-address">{{ $maskedEmail }}</span>.
                    Enter it below to complete your login.
                </div>

                @if (session('success'))
                    <div class="status success">{{ session('success') }}</div>
                @endif

                @if (session('warning'))
                    <div class="status error">{{ session('warning') }}</div>
                @endif

                @if ($errors->any())
                    <div class="status error">{{ $errors->first() }}</div>
                @endif

                <form id="otpForm" method="POST" action="{{ route('login.otp.verify') }}">
                    @csrf
                    <label class="otp-label" for="otpDigit0">Verification code</label>
                    <input type="hidden" id="otpValue" name="otp" value="{{ old('otp', '') }}">

                    <div class="otp-grid" id="otpGrid">
                        @for ($i = 0; $i < 6; $i++)
                            <input
                                id="otpDigit{{ $i }}"
                                class="otp-digit"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                autocomplete="one-time-code"
                                maxlength="1"
                                data-index="{{ $i }}"
                                aria-label="OTP digit {{ $i + 1 }}"
                            >
                        @endfor
                    </div>

                    <div class="meta-grid">
                        <div class="meta-card">
                            <div class="meta-card-label">Expires</div>
                            <div class="meta-card-value">{{ $expiresAt->format('H:i') }}</div>
                        </div>
                        <div class="meta-card">
                            <div class="meta-card-label">Resend</div>
                            <div class="meta-card-value" id="resendStatus">{{ $resendAvailableAt->isPast() ? 'Now' : $resendAvailableAt->format('H:i:s') }}</div>
                        </div>
                    </div>

                    <button class="btn-login" id="verifyBtn" type="submit">
                        <div class="spinner" id="verifySpinner"></div>
                        <span id="verifyBtnText">Verify and sign in</span>
                        <svg id="verifyArrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </button>
                </form>

                <div class="secondary-actions">
                    <form method="POST" action="{{ route('login.otp.resend') }}">
                        @csrf
                        <button id="resendBtn" class="link-btn" type="submit">Resend code</button>
                    </form>

                    <a class="link" href="{{ route('login') }}">Back to sign in</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        const digits = Array.from(document.querySelectorAll('.otp-digit'));
        const otpValue = document.getElementById('otpValue');
        const otpForm = document.getElementById('otpForm');
        const verifyBtn = document.getElementById('verifyBtn');
        const verifySpinner = document.getElementById('verifySpinner');
        const verifyBtnText = document.getElementById('verifyBtnText');
        const verifyArrow = document.getElementById('verifyArrow');
        const resendBtn = document.getElementById('resendBtn');
        const resendStatus = document.getElementById('resendStatus');
        const resendAvailableAt = {{ $resendAvailableAt->getTimestampMs() }};

        function setLoading(on) {
            verifyBtn.disabled = on;
            verifySpinner.style.display = on ? 'block' : 'none';
            verifyBtnText.textContent = on ? 'Verifying...' : 'Verify and sign in';
            verifyArrow.style.display = on ? 'none' : '';
        }

        function syncOtpValue() {
            otpValue.value = digits.map((input) => input.value).join('');
            verifyBtn.disabled = otpValue.value.length !== 6;

            digits.forEach((input) => {
                input.classList.toggle('filled', input.value.length === 1);
            });
        }

        function maybeSubmit(lastIndex) {
            syncOtpValue();
            if (lastIndex === 5 && otpValue.value.length === 6) {
                setLoading(true);
                otpForm.requestSubmit();
            }
        }

        function fillDigits(value) {
            const clean = (value || '').replace(/\D/g, '').slice(0, 6).split('');
            digits.forEach((input, index) => {
                input.value = clean[index] || '';
            });
            syncOtpValue();
            const focusIndex = clean.length >= 6 ? 5 : clean.length;
            digits[focusIndex]?.focus();
            digits[focusIndex]?.select?.();
        }

        digits.forEach((input, index) => {
            input.addEventListener('input', (event) => {
                const digit = event.target.value.replace(/\D/g, '').slice(-1);
                event.target.value = digit;
                syncOtpValue();

                if (digit && index < digits.length - 1) {
                    digits[index + 1].focus();
                    digits[index + 1].select();
                }
            });

            input.addEventListener('keyup', (event) => {
                if (event.key === 'Backspace' && !event.target.value && index > 0) {
                    digits[index - 1].focus();
                    digits[index - 1].select();
                    syncOtpValue();
                    return;
                }

                if (/^\d$/.test(event.key)) {
                    maybeSubmit(index);
                }
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowLeft' && index > 0) {
                    digits[index - 1].focus();
                    event.preventDefault();
                }

                if (event.key === 'ArrowRight' && index < digits.length - 1) {
                    digits[index + 1].focus();
                    event.preventDefault();
                }
            });

            input.addEventListener('paste', (event) => {
                event.preventDefault();
                fillDigits(event.clipboardData.getData('text'));
                if (otpValue.value.length === 6) {
                    setLoading(true);
                    otpForm.requestSubmit();
                }
            });
        });

        function updateResendButton() {
            const remainingMs = resendAvailableAt - Date.now();
            if (remainingMs <= 0) {
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend code';
                resendStatus.textContent = 'Now';
                return;
            }

            const seconds = Math.ceil(remainingMs / 1000);
            resendBtn.disabled = true;
            resendBtn.textContent = `Resend in ${seconds}s`;
            resendStatus.textContent = `${seconds}s`;
            window.setTimeout(updateResendButton, 1000);
        }

        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-theme') === 'dark';
            html.setAttribute('data-theme', isDark ? 'light' : 'dark');
            const icon = document.getElementById('themeIcon');
            icon.innerHTML = isDark
                ? '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>'
                : '<path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>';

            try {
                localStorage.setItem('ruky_theme', isDark ? 'light' : 'dark');
            } catch (e) {}
        }

        otpForm.addEventListener('submit', () => {
            setLoading(true);
        });

        window.addEventListener('DOMContentLoaded', () => {
            try {
                const theme = localStorage.getItem('ruky_theme');
                if (theme) {
                    document.documentElement.setAttribute('data-theme', theme);
                    if (theme === 'light') {
                        document.getElementById('themeIcon').innerHTML = '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>';
                    }
                }
            } catch (e) {}

            const oldOtp = otpValue.value;
            if (oldOtp) {
                fillDigits(oldOtp);
            } else {
                digits[0]?.focus();
            }

            syncOtpValue();
            updateResendButton();
        });
    </script>
</body>

</html>
