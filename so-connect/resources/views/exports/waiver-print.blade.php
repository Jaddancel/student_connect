<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent / Guardian Waiver</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 13px;
            line-height: 1.6;
            color: #111827;
            background: #fff;
            padding: 48px 56px;
        }

        .header {
            text-align: center;
            margin-bottom: 28px;
        }

        .header h1 {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header h2 {
            font-size: 15px;
            font-weight: 700;
        }

        .header p {
            font-size: 12px;
        }

        .doc-title {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 24px 0 28px;
        }

        .field {
            font-weight: 700;
            text-decoration: underline;
        }

        p.body-text {
            text-align: justify;
            margin-bottom: 16px;
        }

        .signatures {
            margin-top: 56px;
            width: 100%;
        }

        .sig-block {
            margin-top: 40px;
        }

        .sig-line {
            border-top: 1px solid #111827;
            width: 280px;
            padding-top: 4px;
            font-size: 12px;
        }

        .muted {
            color: #4b5563;
            font-size: 12px;
        }

        .print-actions {
            text-align: center;
            margin-bottom: 24px;
        }

        .print-actions button {
            font-family: Arial, sans-serif;
            font-size: 13px;
            padding: 8px 18px;
            border: 1px solid #16a34a;
            background: #16a34a;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
        }

        @media print {
            body {
                padding: 0;
            }

            .print-actions {
                display: none;
            }
        }
    </style>
</head>

@php
    $blank = function ($value, $width = '180px') {
        $value = trim((string) $value);
        return $value !== ''
            ? '<span class="field">' . e($value) . '</span>'
            : '<span style="display:inline-block;border-bottom:1px solid #111827;min-width:' . $width . '">&nbsp;</span>';
    };
@endphp

<body>
    <div class="print-actions">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="header">
        <h1>Republic of the Philippines</h1>
        <h2>Tarlac Agricultural University</h2>
        <p>Camiling, Tarlac</p>
        <p style="margin-top:6px;font-weight:600;">Office of Student Services and Development</p>
        <p>Student Development Unit</p>
    </div>

    <div class="doc-title">Parent / Guardian Waiver and Consent</div>

    <p class="body-text">
        I, {!! $blank($parentName, '220px') !!}, of legal age, being the
        {!! $blank($relationship, '140px') !!} of
        {!! $blank($studentName, '220px') !!} (Student ID No.
        {!! $blank($studentId, '140px') !!}), a bona fide student of Tarlac
        Agricultural University, hereby grant my full consent and permission for
        my child/ward to participate in the activity described below.
    </p>

    <p class="body-text">
        Activity: {!! $blank($activityName, '260px') !!} &nbsp;&nbsp;
        Date: {!! $blank($activityDate, '140px') !!} <br>
        Venue: {!! $blank($venue, '300px') !!}
    </p>

    <p class="body-text">
        In consideration of the participation of my child/ward in this activity, I
        understand and acknowledge the nature of the activity and any risks that
        may be involved. I hereby release, waive, and discharge Tarlac
        Agricultural University, its officers, faculty advisers, employees, and
        organizers from any and all liability, claims, or demands arising from any
        injury, loss, or damage that may be sustained by my child/ward in
        connection with this activity, except those caused by gross negligence or
        willful misconduct.
    </p>

    <p class="body-text">
        I further certify that, to the best of my knowledge, my child/ward is
        physically fit to participate, and I consent to the administration of
        reasonable first aid or emergency medical treatment should it become
        necessary during the activity.
    </p>

    <p class="body-text">
        I am signing this waiver freely and voluntarily, with full understanding of
        its contents.
    </p>

    <div class="signatures">
        <div class="sig-block">
            <div class="sig-line">
                Signature over Printed Name of Parent / Guardian
            </div>
        </div>
        <div class="sig-block">
            <div class="sig-line">
                Date Signed
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>

</html>
