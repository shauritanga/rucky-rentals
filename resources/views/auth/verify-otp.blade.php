<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mwamba Properties - Verify Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root[data-theme="dark"] {
            --bg-base: #0f1012;
            --bg-surface: #16181c;
            --bg-elevated: #1c1e23;
            --border: rgba(255, 255, 255, .08);
            --text-primary: #f0f1f3;
            --text-secondary: #9ba3b0;
            --text-muted: #5c6470;
            --accent: #3b82f6;
            --accent-dim: rgba(59, 130, 246, .12);
            --red: #ef4444;
            --red-dim: rgba(239, 68, 68, .12);
            --green: #22c55e;
        }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: 'DM Sans', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(59, 130, 246, .12), transparent 32%),
                radial-gradient(circle at bottom right, rgba(16, 185, 129, .08), transparent 24%),
                var(--bg-base);
            color: var(--text-primary);
        }
        .panel {
            width: 100%;
            max-width: 520px;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 34px 30px;
            box-shadow: 0 22px 60px rgba(0, 0, 0, .42);
        }
        .eyebrow {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.1px;
            color: var(--accent);
            font-weight: 700;
            margin-bottom: 12px;
        }
        h1 {
            font-family: 'Instrument Serif', serif;
            font-size: 38px;
            font-weight: 400;
            line-height: 1.05;
            margin-bottom: 14px;
        }
        .subtext {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 26px;
        }
        .status {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 13px;
            line-height: 1.5;
        }
        .status.error {
            background: var(--red-dim);
            color: var(--red);
            border: 1px solid rgba(239, 68, 68, .18);
        }
        .status.success {
            background: rgba(34, 197, 94, .12);
            color: var(--green);
            border: 1px solid rgba(34, 197, 94, .18);
        }
        .otp-form { margin-top: 10px; }
        .otp-label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        .otp-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-bottom: 12px;
        }
        .otp-digit {
            height: 58px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: var(--bg-elevated);
            color: var(--text-primary);
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            outline: none;
        }
        .otp-digit:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-dim);
        }
        .meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 18px;
        }
        .btn {
            width: 100%;
            border: none;
            border-radius: 12px;
            background: var(--accent);
            color: #fff;
            padding: 13px 16px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn:disabled {
            opacity: .55;
            cursor: not-allowed;
        }
        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 18px;
        }
        .link-btn, .link {
            background: none;
            border: none;
            color: var(--accent);
            font: inherit;
            cursor: pointer;
            text-decoration: none;
        }
        .link-btn:disabled {
            color: var(--text-muted);
            cursor: not-allowed;
        }
        @media (max-width: 560px) {
            .panel { padding: 28px 18px; }
            .otp-grid { gap: 8px; }
            .otp-digit { height: 52px; font-size: 22px; }
            .actions { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>

<body>
    <div class="panel">
        <div class="eyebrow">Two-Factor Sign In</div>
        <h1>Enter the code we emailed you.</h1>
        <p class="subtext">
            A 6-digit verification code was sent to <strong>{{ $maskedEmail }}</strong>.
            Enter it below to complete your login.
        </p>

        @if (session('success'))
            <div class="status success">{{ session('success') }}</div>
        @endif

        @if (session('warning'))
            <div class="status error">{{ session('warning') }}</div>
        @endif

        @if ($errors->any())
            <div class="status error">{{ $errors->first() }}</div>
        @endif

        <form id="otpForm" class="otp-form" method="POST" action="{{ route('login.otp.verify') }}">
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
                    >
                @endfor
            </div>

            <div class="meta">
                <span>Code expires at {{ $expiresAt->format('H:i') }}</span>
                <span>Single use only</span>
            </div>

            <button id="verifyBtn" class="btn" type="submit">Verify and sign in</button>
        </form>

        <div class="actions">
            <form method="POST" action="{{ route('login.otp.resend') }}">
                @csrf
                <button id="resendBtn" class="link-btn" type="submit">Resend code</button>
            </form>

            <a class="link" href="{{ route('login') }}">Back to sign in</a>
        </div>
    </div>

    <script>
        const digits = Array.from(document.querySelectorAll('.otp-digit'));
        const otpValue = document.getElementById('otpValue');
        const otpForm = document.getElementById('otpForm');
        const verifyBtn = document.getElementById('verifyBtn');
        const resendBtn = document.getElementById('resendBtn');
        const resendAvailableAt = {{ $resendAvailableAt->getTimestampMs() }};

        function syncOtpValue() {
            otpValue.value = digits.map((input) => input.value).join('');
            verifyBtn.disabled = otpValue.value.length !== 6;
        }

        function maybeSubmit(lastIndex) {
            syncOtpValue();
            if (lastIndex === 5 && otpValue.value.length === 6) {
                verifyBtn.disabled = true;
                otpForm.requestSubmit();
            }
        }

        function fillDigits(value) {
            const clean = (value || '').replace(/\D/g, '').slice(0, 6).split('');
            digits.forEach((input, index) => {
                input.value = clean[index] || '';
            });
            syncOtpValue();
            const focusIndex = Math.min(clean.length, 5);
            digits[focusIndex]?.focus();
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
                    verifyBtn.disabled = true;
                    otpForm.requestSubmit();
                }
            });
        });

        function updateResendButton() {
            const remainingMs = resendAvailableAt - Date.now();
            if (remainingMs <= 0) {
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend code';
                return;
            }

            const seconds = Math.ceil(remainingMs / 1000);
            resendBtn.disabled = true;
            resendBtn.textContent = `Resend in ${seconds}s`;
            window.setTimeout(updateResendButton, 1000);
        }

        const oldOtp = otpValue.value;
        if (oldOtp) {
            fillDigits(oldOtp);
        } else {
            digits[0]?.focus();
        }
        syncOtpValue();
        updateResendButton();
    </script>
</body>

</html>
