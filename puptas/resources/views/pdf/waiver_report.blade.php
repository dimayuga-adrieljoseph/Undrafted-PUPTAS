<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Waiver Applicants Report</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 5px 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f4f4f4; font-weight: bold; }
        .status { color: #800918; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Waiver Applicants Report</h1>
        <p>Generated on {{ date('F j, Y, g:i a') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Rank</th>
                <th>Reference Number</th>
                <th>Applicant Name</th>
                <th>Email</th>
                <th>Strand</th>
                <th>Score</th>
                <th>Status</th>
                <th>System Status</th>
                <th>Program Offering</th>
                <th>Tagged Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($applicants as $index => $applicant)
                <tr>
                    <td>{{ $applicant->waiver_rank ?? '-' }}</td>
                    <td>{{ $applicant->reference_number }}</td>
                    <td>{{ $applicant->surname }}, {{ $applicant->first_name }}</td>
                    <td>{{ $applicant->user?->email }}</td>
                    <td>{{ $applicant->strand ?? 'N/A' }}</td>
                    <td>{{ $applicant->pupcet_total_score ?? 'N/A' }}</td>
                    <td>{{ $applicant->waiver_list_status ?? 'N/A' }}</td>
                    <td class="status">{{ ucwords(str_replace('_', ' ', $applicant->passerStatus?->status ?? 'N/A')) }}</td>
                    <td>{{ $applicant->waiver_program_offering ?? 'N/A' }}</td>
                    <td>{{ $applicant->updated_at->format('M d, Y h:i A') }}</td>
                </tr>
            @endforeach
            
            @if(count($applicants) === 0)
                <tr>
                    <td colspan="10" style="text-align: center; color: #777;">No waiver applicants found.</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div style="margin-top: 30px; text-align: center; font-style: italic; color: #777; font-size: 11px;">
        *** This is a system generated report ***
    </div>
</body>
</html>
