<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPI Report - PUPTAS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #1a1a1a;
            padding: 24px;
            background: #ffffff;
        }

        /* ── Header ─────────────────────────────────────────────────────── */
        .header {
            text-align: center;
            border-bottom: 3px solid #9E122C;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .header .institution {
            font-size: 13pt;
            font-weight: bold;
            color: #9E122C;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header .campus {
            font-size: 10pt;
            color: #444;
            margin-top: 2px;
        }

        .header .report-title {
            font-size: 15pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-top: 8px;
            text-transform: uppercase;
        }

        .header .generated-at {
            font-size: 9pt;
            color: #555;
            margin-top: 6px;
        }

        /* ── Section headings ────────────────────────────────────────────── */
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #9E122C;
            border-bottom: 1.5px solid #9E122C;
            padding-bottom: 4px;
            margin-top: 22px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ── Summary row ─────────────────────────────────────────────────── */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .summary-table td {
            width: 33.33%;
            text-align: center;
            padding: 10px 8px;
            border: 1px solid #c8d3df;
        }

        .summary-table .summary-label {
            font-size: 9pt;
            color: #555;
            display: block;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .summary-table .summary-value {
            font-size: 18pt;
            font-weight: bold;
            color: #1a1a1a;
            display: block;
        }

        .summary-table .summary-value.met {
            color: #166534;
        }

        .summary-table .summary-value.failed {
            color: #991b1b;
        }

        /* ── KPI summary table ───────────────────────────────────────────── */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 10pt;
        }

        table.data-table thead tr {
            background-color: #9E122C;
            color: #ffffff;
        }

        table.data-table thead th {
            padding: 8px 10px;
            text-align: left;
            font-weight: bold;
            font-size: 10pt;
            border: 1px solid #9E122C;
        }

        table.data-table thead th.col-center {
            text-align: center;
        }

        table.data-table tbody tr:nth-child(even) {
            background-color: #f3f6fa;
        }

        table.data-table tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }

        table.data-table tbody td {
            padding: 8px 10px;
            border: 1px solid #c8d3df;
            vertical-align: middle;
        }

        table.data-table tbody td.col-center {
            text-align: center;
        }

        table.data-table tbody td.col-value {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        /* ── Status badges ───────────────────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: bold;
            text-align: center;
        }

        .badge-pass {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .badge-fail {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        /* ── Footer ──────────────────────────────────────────────────────── */
        .footer {
            margin-top: 32px;
            padding-top: 10px;
            border-top: 1px solid #c8d3df;
            font-size: 8.5pt;
            color: #777;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- ── HEADER ──────────────────────────────────────────────────────── --}}
    <div class="header">
        <div class="institution">Polytechnic University of the Philippines — Taguig Campus</div>
        <div class="campus">PUP Taguig Admissions System (PUPTAS)</div>
        <div class="report-title">Key Performance Indicator (KPI) Report</div>
        <div class="generated-at">Generated: {{ $generatedAt }}</div>
    </div>

    {{-- ── SUMMARY ROW ─────────────────────────────────────────────────── --}}
    <div class="section-title">Summary</div>

    <table class="summary-table">
        <tr>
            <td>
                <span class="summary-label">Total KPIs</span>
                <span class="summary-value">{{ $summary['total_kpis'] }}</span>
            </td>
            <td>
                <span class="summary-label">KPIs Met</span>
                <span class="summary-value met">{{ $summary['kpis_met'] }}</span>
            </td>
            <td>
                <span class="summary-label">KPIs Failed</span>
                <span class="summary-value failed">{{ $summary['kpis_failed'] }}</span>
            </td>
        </tr>
    </table>

    {{-- ── PROCESS EFFICIENCY KPIs ─────────────────────────────────────── --}}
    @if (!empty($serviceTimeKpi))
    <div class="section-title">Process Efficiency KPIs — Average Service Time per Step</div>

    <p style="font-size:8.5pt; color:#555; margin-bottom:8px;">
        Targets are based on averages from the client's sample logsheet of the last school year.
        A step is considered efficient when the PUPTAS average is at or below the manual baseline.
    </p>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 34%;">Admissions Step</th>
                <th class="col-center" style="width: 16%;">PUPTAS Avg.</th>
                <th class="col-center" style="width: 18%;">Manual Baseline</th>
                <th class="col-center" style="width: 10%;">Count</th>
                <th class="col-center" style="width: 10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($serviceTimeKpi as $step)
            <tr>
                <td>{{ $step['label'] }}</td>
                <td class="col-value">
                    @if ($step['avg_min'] !== null)
                        {{ $step['avg_min'] }} min
                    @else
                        <span style="color:#999;">No data</span>
                    @endif
                </td>
                <td class="col-value">
                    @if (!empty($step['manual_baseline']))
                        {{ $step['manual_baseline'] }} min
                    @else
                        <span style="color:#999;">—</span>
                    @endif
                </td>
                <td class="col-center">{{ $step['count'] }}</td>
                <td class="col-center">
                    @if ($step['met'] === true)
                        <span class="badge badge-pass">Pass</span>
                    @elseif ($step['met'] === false)
                        <span class="badge badge-fail">Fail</span>
                    @else
                        <span style="color:#999; font-size:9pt;">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ── KPI SUMMARY TABLE ────────────────────────────────────────────── --}}
    <div class="section-title">KPI Results</div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 38%;">KPI Label</th>
                <th class="col-center" style="width: 16%;">Value</th>
                <th class="col-center" style="width: 16%;">Target</th>
                <th class="col-center" style="width: 30%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($kpis as $kpi)
            <tr>
                <td>{{ $kpi['label'] }}</td>
                <td class="col-value">{{ number_format($kpi['value'], 2) }}{{ $kpi['unit'] }}</td>
                <td class="col-value">{{ !empty($kpi['lowerIsBetter']) ? '≤' : '≥' }} {{ number_format($kpi['target'], 1) }}{{ $kpi['unit'] }}</td>
                <td class="col-center">
                    @if ($kpi['met'])
                        <span class="badge badge-pass">Pass</span>
                    @else
                        <span class="badge badge-fail">Fail</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── PER-PROGRAM BREAKDOWN TABLE ─────────────────────────────────── --}}
    @if (count($perProgram) > 0)
    <div class="section-title">Per-Program Slot Utilization</div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 12%;">Code</th>
                <th style="width: 42%;">Program Name</th>
                <th class="col-center" style="width: 14%;">Slots</th>
                <th class="col-center" style="width: 14%;">Enrolled</th>
                <th class="col-center" style="width: 18%;">Utilization (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($perProgram as $program)
            <tr>
                <td><strong>{{ $program['code'] }}</strong></td>
                <td>{{ $program['name'] }}</td>
                <td class="col-center">{{ $program['slots'] }}</td>
                <td class="col-center">{{ $program['enrolled'] }}</td>
                <td class="col-center">
                    {{ number_format($program['value'], 2) }}%
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ── MANUAL BASELINES — NOT YET ESTABLISHED ─────────────────────── --}}
    @if (!empty($manualBaselines['not_established']))
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 35%;">KPI</th>
                <th style="width: 65%;">Data Required to Establish Baseline</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($manualBaselines['not_established'] as $kpiKey => $requirement)
            <tr>
                <td><strong>{{ ucwords(str_replace('_', ' ', $kpiKey)) }}</strong></td>
                <td style="color:#555;">{{ $requirement }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <p style="font-size:8.5pt; color:#555; margin-top:6px;">
        <em>These KPIs are computed live from the PUPTAS database. Manual baselines for comparison require historical records from the Admission Office that were not available at the time of report generation.</em>
    </p>
    @endif

    {{-- ── FOOTER ──────────────────────────────────────────────────────── --}}
    <div class="footer">
        PUPTAS &mdash; PUP Taguig Admissions System &nbsp;|&nbsp; Report generated: {{ $generatedAt }}
        <br>This document is for internal use and capstone portfolio evidence only.
    </div>

</body>
</html>
