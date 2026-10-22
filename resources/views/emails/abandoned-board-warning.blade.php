<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <title>Your SlipNote board will be removed</title>
</head>
<body style="margin:0; padding:0; background-color:#f7f7f5; -webkit-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f7f5;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:480px; background-color:#ffffff; border:1px solid #e6e5e1; border-radius:16px;">
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 4px 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; font-weight:bold; letter-spacing:0.5px; text-transform:uppercase; color:#5b5bd6;">SlipNote</p>
                            <h1 style="margin:0 0 8px 0; font-family:Arial,Helvetica,sans-serif; font-size:22px; line-height:1.25; color:#18181b;">Keep your board?</h1>
                            <p style="margin:0 0 20px 0; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.5; color:#52525b;">
                                <strong style="color:#18181b;">{{ $workspaceName }}</strong> has no files, and nobody has opened it for a few weeks. It will be removed in <strong style="color:#18181b;">{{ $days }} days</strong>. To keep it, just open it.
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" bgcolor="#5b5bd6" style="border-radius:10px;">
                                        <a href="{{ $boardUrl }}"
                                           style="display:block; padding:14px 24px; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:10px;">
                                            Open my board
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:16px 0 0 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.5; color:#52525b;">
                                Opening it resets the clock. If you don't need it, there's nothing to do.
                            </p>
                            <p style="margin:20px 0 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#8a8a8a;">The SlipNote team</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
