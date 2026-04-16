<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mwamba Properties Team Invite</title>
</head>

<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0f766e,#0b5f58);padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.3;font-weight:700;">Your Team Account Is Ready</h1>
                            <p style="margin:10px 0 0;font-size:14px;opacity:.92;">Mwamba Properties</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">Hello {{ $memberName }},</p>
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">
                                A team account has been created for you on Mwamba Properties.
                            </p>
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">
                                Role: <strong>{{ $roleLabel }}</strong>
                            </p>
                            @if ($propertyName)
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">
                                Assigned property: <strong>{{ $propertyName }}</strong>
                            </p>
                            @endif

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:16px 0;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
                                <tr>
                                    <td style="padding:14px;background:#f8fafc;font-size:13px;color:#334155;text-transform:uppercase;letter-spacing:.04em;">Initial Login Credentials</td>
                                </tr>
                                <tr>
                                    <td style="padding:16px;">
                                        <p style="margin:0 0 8px;font-size:14px;line-height:1.5;"><strong>Email:</strong> {{ $email }}</p>
                                        <p style="margin:0;font-size:14px;line-height:1.5;"><strong>Temporary Password:</strong> {{ $initialPassword }}</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#b91c1c;">
                                For account security, you must change your password immediately after your first login.
                            </p>

                            <p style="margin:0 0 20px;">
                                <a href="{{ $loginUrl }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:600;font-size:14px;">Go to Login</a>
                            </p>

                            <p style="margin:0;font-size:13px;line-height:1.6;color:#475569;">
                                If you were not expecting this email, please contact your property administrator.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">
                            This is an automated onboarding message from Mwamba Properties.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>