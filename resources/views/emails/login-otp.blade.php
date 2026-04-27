<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Login Verification Code</title>
</head>

<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#1d4ed8,#1e3a8a);padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.3;font-weight:700;">Login Verification Code</h1>
                            <p style="margin:10px 0 0;font-size:14px;opacity:.92;">Mwamba Properties</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">Hello {{ $recipientName }},</p>
                            <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">
                                Use the following one-time code to complete your sign in:
                            </p>

                            <div style="margin:0 0 22px;padding:18px;border-radius:12px;background:#eff6ff;border:1px solid #bfdbfe;text-align:center;">
                                <div style="font-size:32px;font-weight:700;letter-spacing:8px;color:#1d4ed8;">{{ $otpCode }}</div>
                            </div>

                            <p style="margin:0 0 12px;font-size:14px;line-height:1.6;color:#334155;">
                                This code expires in {{ $expiresInMinutes }} minutes and can only be used once.
                            </p>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;">
                                If you did not try to sign in, you can ignore this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
