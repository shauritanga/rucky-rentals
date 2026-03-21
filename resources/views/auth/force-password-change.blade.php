<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Rucky Rentals</title>
    <style>
        :root {
            --bg: #f3f6fb;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #dbe5f3;
            --accent: #0f766e;
            --danger: #b91c1c;
            --danger-bg: #fef2f2;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: radial-gradient(circle at top right, #d9f5f2, transparent 40%), var(--bg);
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 460px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(15, 23, 42, .08);
            overflow: hidden;
        }

        .head {
            padding: 22px 24px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, #0f766e, #0b5f58);
            color: #fff;
        }

        .head h1 {
            margin: 0;
            font-size: 20px;
            line-height: 1.2;
        }

        .head p {
            margin: 8px 0 0;
            font-size: 13px;
            opacity: .92;
        }

        .body {
            padding: 22px 24px;
        }

        .alert {
            background: var(--danger-bg);
            border: 1px solid #fecaca;
            color: var(--danger);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            margin-bottom: 14px;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 11px 12px;
            font-size: 14px;
            margin-bottom: 14px;
            outline: none;
        }

        input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, .14);
        }

        .error {
            margin: -8px 0 12px;
            color: var(--danger);
            font-size: 12px;
        }

        button {
            width: 100%;
            border: none;
            border-radius: 8px;
            background: var(--accent);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            padding: 11px 14px;
            cursor: pointer;
        }

        .hint {
            margin-top: 12px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="head">
            <h1>Change Your Password</h1>
            <p>For security, you must set a new password before continuing.</p>
        </div>
        <div class="body">
            @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.force.update') }}">
                @csrf

                <label for="current_password">Current Password</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                @error('current_password')
                <div class="error">{{ $message }}</div>
                @enderror

                <label for="password">New Password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required>
                @error('password')
                <div class="error">{{ $message }}</div>
                @enderror

                <label for="password_confirmation">Confirm New Password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

                <button type="submit">Update Password & Continue</button>

                <div class="hint">
                    Use at least 8 characters and avoid reusing the default password.
                </div>
            </form>
        </div>
    </div>
</body>

</html>