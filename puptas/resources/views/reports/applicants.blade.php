<!DOCTYPE html>
<html>
<head>
    <title>Applicant Report</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h1 { text-align: center; }
        .text-center { text-align: center; }
    </style>
</head>
<body>

    <h1>Applicant Status Report</h1>
    <div class="text-center">
        Report Type: {{ $reportType == 'overall' || !$reportType ? 'Overall' : ucfirst($reportType) }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Student Number</th>
                <th>Name</th>
                <th>Program</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($applicants as $app)
            <tr>
                <td>{{ $app['student_number'] }}</td>
                <td>{{ $app['name'] }}</td>
                <td>{{ $app['program'] }}</td>
                <td>{{ $app['status'] }}</td>
                <td>{{ $app['date'] }}</td>
            </tr>
            @endforeach
            @if(count($applicants) == 0)
            <tr>
                <td colspan="5" class="text-center">No applicants found for the selected criteria.</td>
            </tr>
            @endif
        </tbody>
    </table>

</body>
</html>
