<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#FDFCF8;font-family:'Helvetica Neue',Arial,sans-serif;color:#3E3D38;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FDFCF8;padding:40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #E5E0D8;">

                    <!-- Header -->
                    <tr>
                        <td style="background:#7F77DD;padding:32px 32px 28px;text-align:left;">
                            <p style="margin:0;color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:2px;font-weight:700;opacity:0.85;">
                                Moving Guru
                            </p>
                            <h1 style="margin:8px 0 0;color:#fff;font-size:22px;font-weight:900;line-height:1.3;">
                                {{ $subjectLine }}
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">
                                Hi {{ $recipientName ? $recipientName : 'there' }},
                            </p>
                            <div style="font-size:14px;line-height:1.6;color:#3E3D38;">
                                {!! nl2br(e($bodyText)) !!}
                            </div>
                            <p style="margin:32px 0 0;font-size:13px;color:#6B6B66;">
                                — The Moving Guru team
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#FDFCF8;padding:20px 32px;border-top:1px solid #E5E0D8;text-align:center;">
                            <p style="margin:0;font-size:11px;color:#9A9A94;">
                                You're receiving this because you have a Moving Guru account.<br>
                                Visit <a href="{{ config('app.frontend_url', config('app.url')) }}" style="color:#7F77DD;text-decoration:none;">movingguru.co</a> to manage your profile.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>