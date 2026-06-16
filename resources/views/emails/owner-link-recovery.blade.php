<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <title>Your SlipNote owner access</title>
</head>
<body style="margin:0; padding:0; background-color:#f7f7f5; -webkit-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f7f5;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:480px; background-color:#ffffff; border:1px solid #e6e5e1; border-radius:16px;">
                    <tr>
                        <td style="padding:28px 28px 0 28px;">
                            <p style="margin:0 0 4px 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; font-weight:bold; letter-spacing:0.5px; text-transform:uppercase; color:#5b5bd6;">SlipNote</p>
                            <h1 style="margin:0 0 8px 0; font-family:Arial,Helvetica,sans-serif; font-size:22px; line-height:1.25; color:#18181b;">Your owner access is ready</h1>
                            <p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.5; color:#52525b;">
                                Here's fresh owner access for your board
                                <strong style="color:#18181b;">{{ $workspaceName }}</strong>.
                            </p>
                            <p style="margin:0 0 20px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.5; color:#52525b;">
                                <strong style="color:#18181b;">Your previous owner link has stopped working.</strong> Use the button below from now on.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" bgcolor="#5b5bd6" style="border-radius:10px;">
                                        <a href="{{ $ownerUrl }}"
                                           style="display:block; padding:14px 24px; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:10px;">
                                            Open board as owner
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:12px 0 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:1.5; color:#8a8a8a;">
                                Button not working? Copy this link:<br>
                                <a href="{{ $ownerUrl }}" style="color:#5b5bd6; word-break:break-all;">{{ $ownerUrl }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px 28px 28px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
                                   style="background-color:#fafafa; border:1px solid #e6e5e1; border-radius:10px;">
                                <tr>
                                    <td style="padding:14px 16px;">
                                        <p style="margin:0 0 8px 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.5; color:#52525b;">
                                            Keep this private. Anyone who has it can manage or delete the board, so don't share it or forward this email.
                                        </p>
                                        <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.5; color:#52525b;">
                                            Didn't request this? You can safely ignore this email; nothing changes until this link is used.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:20px 0 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#8a8a8a;">The SlipNote team</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
