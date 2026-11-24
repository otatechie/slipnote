<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <title>School enquiry</title>
</head>
<body style="margin:0; padding:0; background-color:#f7f7f5; -webkit-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f7f5;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:480px; background-color:#ffffff; border:1px solid #e6e5e1; border-radius:16px;">
                    <tr>
                        <td style="padding:28px 28px 8px 28px;">
                            <p style="margin:0 0 4px 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; font-weight:bold; letter-spacing:0.5px; text-transform:uppercase; color:#5b5bd6;">SlipNote</p>
                            <h1 style="margin:0 0 16px 0; font-family:Arial,Helvetica,sans-serif; font-size:22px; line-height:1.25; color:#18181b;">A school asked about SlipNote</h1>
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
                                   style="font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.5; color:#18181b;">
                                @foreach (array_filter([
                                    'School' => $enquiry['school'],
                                    'From' => $enquiry['from'],
                                    'Classes' => $enquiry['classes'],
                                    'Reply to' => $contact,
                                    'Came via' => $enquiry['ref'] ? \App\Models\Ambassador::refLabel($enquiry['ref']) : null,
                                ]) as $label => $value)
                                    <tr>
                                        <td style="padding:3px 12px 3px 0; color:#71717a; white-space:nowrap; vertical-align:top;">{{ $label }}</td>
                                        <td style="padding:3px 0; vertical-align:top; word-break:break-word;">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </table>
                            @if ($enquiry['message'] !== '')
                                <p style="margin:18px 0 4px 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; font-weight:bold; color:#71717a;">What they wrote</p>
                                <p style="margin:0 0 4px 0; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.55; color:#18181b; white-space:pre-line;">{{ $enquiry['message'] }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px 28px 28px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" bgcolor="#5b5bd6" style="border-radius:10px;">
                                        <a href="{{ $dashboardUrl }}"
                                           style="display:block; padding:14px 24px; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:10px;">
                                            Open in the dashboard
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
