{{--
    Shared shell for the sign-up lifecycle emails (request received, approved,
    rejected). Sections: `accent` (brand colour of the state), `badge`,
    `heading`, `body`. Modelled on mail/officer-activation.blade.php, which
    predates this layout and is left as-is.
--}}
@php($accent = $accent ?? '#16a34a')
@php($tint = $tint ?? '#f0fdf4')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>@yield('title', 'StudentConnect')</title>
    <style>
        body { background-color: {{ $tint }}; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.6; margin: 0; padding: 0; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; }
        table { border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%; }
        table td { font-family: Arial, sans-serif; font-size: 16px; vertical-align: top; }
        .body { background-color: {{ $tint }}; width: 100%; }
        .container { display: block; margin: 0 auto !important; max-width: 580px; padding: 10px; width: 580px; }
        .content { box-sizing: border-box; display: block; margin: 0 auto; max-width: 580px; padding: 10px; }
        .header { padding: 20px 0; text-align: center; }
        .header a { color: {{ $accent }}; font-size: 18px; font-weight: bold; text-decoration: none; }
        .main { background: #ffffff; border-radius: 8px; width: 100%; border-top: 4px solid {{ $accent }}; }
        .wrapper { box-sizing: border-box; padding: 32px; }
        .footer { clear: both; padding-top: 10px; text-align: center; width: 100%; }
        .footer td, .footer p, .footer span, .footer a { color: #9ca3af; font-size: 12px; text-align: center; }
        h1 { color: #111827; font-size: 22px; font-weight: bold; line-height: 1.4; margin: 0 0 16px; }
        p { font-size: 15px; color: #374151; margin: 0 0 16px; }
        .badge { display: inline-block; background: {{ $tint }}; border: 1px solid {{ $accent }}33; border-radius: 20px; color: {{ $accent }}; font-size: 12px; font-weight: 700; letter-spacing: 0.08em; padding: 4px 12px; text-transform: uppercase; margin-bottom: 20px; }
        .highlight-box { background: {{ $tint }}; border-left: 4px solid {{ $accent }}; padding: 14px 18px; border-radius: 4px; margin-bottom: 20px; }
        .highlight-box p { margin: 0; color: #1f2937; font-size: 15px; }
        .btn-wrap { text-align: center; margin: 28px 0; }
        .btn { background-color: {{ $accent }}; border-radius: 6px; color: #ffffff !important; display: inline-block; font-size: 15px; font-weight: 700; padding: 14px 32px; text-decoration: none; }
        .note { font-size: 13px; color: #6b7280; }
        ol, ul { color: #374151; font-size: 15px; padding-left: 20px; }
        li { margin-bottom: 8px; }
        hr { border: 0; border-top: 1px solid #e5e7eb; margin: 20px 0; }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .wrapper { padding: 20px !important; }
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
                    <a href="{{ config('app.url') }}">StudentConnect</a>
                </div>

                <table role="presentation" class="main">
                    <tr>
                        <td class="wrapper">
                            <span class="badge">@yield('badge')</span>
                            <h1>@yield('heading')</h1>
                            @yield('body')
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
