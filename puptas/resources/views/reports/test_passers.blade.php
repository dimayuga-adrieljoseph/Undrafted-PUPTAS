<!DOCTYPE html>
<html>
<head>
    <title>{{ $reportType }} Report</title>
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

    <h1>{{ $reportType }} Report</h1>
    <div class="text-center">
        Date Generated: {{ $date }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Rank</th>
                <th>Reference Number</th>
                <th>Name</th>
                <th>Email</th>
                <th>Strand</th>
                <th>Score</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($passers as $passer)
            <tr>
                <td>#{{ $passer->rank }}</td>
                <td>{{ $passer->reference_number ?? 'N/A' }}</td>
                <td>{{ $passer->full_name }}</td>
                <td>{{ $passer->email ?? 'N/A' }}</td>
                <td>{{ $passer->strand ?? 'N/A' }}</td>
                <td>{{ $passer->pupcet_total_score }}</td>
                <td>{{ ucfirst($passer->passerStatus ? $passer->passerStatus->status : 'Unknown') }}</td>
            </tr>
            @endforeach
            @if(count($passers) == 0)
            <tr>
                <td colspan="7" class="text-center">No applicants found for the selected criteria.</td>
            </tr>
            @endif
        </tbody>
    </table>

    <div style="margin-top: 30px; font-size: 10px; text-align: center;">
        This is a system generated report.
    </div>
</body>
</html>
