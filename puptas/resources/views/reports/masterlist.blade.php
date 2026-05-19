<!DOCTYPE html>
<html>
<head>
    <title>Accepted Applicants Masterlist</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #000; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; color: #000; }
        .header p { margin: 5px 0 0 0; font-size: 12px; color: #000; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; color: #000; }
        th { font-size: 10px; text-transform: uppercase; font-weight: bold; }
        .text-center { text-align: center; }
        .footer { margin-top: 30px; font-size: 9px; color: #000; text-align: right; }
    </style>
</head>
<body>

    <div class="header">
        <h1>PUP Admissions - Accepted Applicants Masterlist</h1>
        <p>Generated on {{ $date }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 15%;">Student Number</th>
                <th style="width: 15%;">Reference Number</th>
                <th style="width: 25%;">Name</th>
                <th style="width: 20%;">Email</th>
                <th style="width: 10%;">Program</th>
                <th style="width: 10%;">Date Accepted</th>
            </tr>
        </thead>
        <tbody>
            @foreach($applicants as $key => $app)
            <tr>
                <td class="text-center">{{ $key + 1 }}</td>
                <td>{{ $app['student_number'] }}</td>
                <td>{{ $app['reference_number'] }}</td>
                <td>{{ $app['name'] }}</td>
                <td>{{ $app['email'] }}</td>
                <td class="text-center">{{ $app['program'] }}</td>
                <td>{{ $app['date_accepted'] }}</td>
            </tr>
            @endforeach
            @if(count($applicants) == 0)
            <tr>
                <td colspan="7" class="text-center" style="padding: 20px;">No accepted applicants found.</td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        Total Accepted Applicants: {{ count($applicants) }}
    </div>

</body>
</html>
