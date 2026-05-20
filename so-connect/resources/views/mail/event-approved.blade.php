<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Event Approved</title>
    <style>
        body { background-color: #f4f5f7; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.6; margin: 0; padding: 0; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; }
        table { border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%; }
        table td { font-family: Arial, sans-serif; font-size: 16px; vertical-align: top; }
        .body { background-color: #f4f5f7; width: 100%; }
        .container { display: block; margin: 0 auto !important; max-width: 580px; padding: 10px; width: 580px; }
        .content { box-sizing: border-box; display: block; margin: 0 auto; max-width: 580px; padding: 10px; }
        .header { padding: 20px 0; text-align: center; }
        .header a { color: #3869d4; font-size: 18px; font-weight: bold; text-decoration: none; }
        .main { background: #ffffff; border-radius: 6px; width: 100%; }
        .wrapper { box-sizing: border-box; padding: 30px; }
        .footer { clear: both; padding-top: 10px; text-align: center; width: 100%; }
        .footer td, .footer p, .footer span, .footer a { color: #9ca3af; font-size: 12px; text-align: center; }
        h1 { color: #111827; font-size: 22px; font-weight: bold; line-height: 1.4; margin: 0 0 16px; }
        p { font-size: 15px; color: #374151; margin: 0 0 16px; }
        .highlight-box { background: #f0fdf4; border-left: 4px solid #15803d; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; }
        .highlight-box p { margin: 0; color: #166534; font-weight: 600; }
        hr { border: 0; border-top: 1px solid #e5e7eb; margin: 20px 0; }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .wrapper { padding: 16px !important; }
        }
    </style>
</head>
<body>
<table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body">
    <tr>
        <td>&nbsp;</td>
        <td class="container">
            <div class="content">

                <div class="header">
                    <a href="#">StudentConnect</a>
                </div>

                <table role="presentation" class="main">
                    <tr>
                        <td class="wrapper">
                            <h1>Event Request Approved</h1>
                            <p>Hello {{ $recipientName }},</p>
                            <p>Great news! Your event request has been reviewed and <strong>approved</strong>.</p>
                            <div class="highlight-box">
                                <p>{{ $plan->title }}</p>
                            </div>
                            <p>Your event is now visible on the calendar. Please ensure all preparations are on track.</p>
                            <hr>
                            <p style="font-size:13px;color:#6b7280;">If you did not submit this event request, please contact your organization admin.</p>
                        </td>
                    </tr>
                </table>

                <div class="footer">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td><span>StudentConnect &mdash; Student Organization Management System</span></td>
                        </tr>
                    </table>
                </div>

            </div>
        </td>
        <td>&nbsp;</td>
    </tr>
</table>
</body>
</html>
