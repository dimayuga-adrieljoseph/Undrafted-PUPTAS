<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAR Form - {{ $reference_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
            padding: 20px;
        }
        
        .page {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
        }
        
        h1 {
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        h2 {
            font-size: 13pt;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 8px;
            border-bottom: 2px solid #000;
            padding-bottom: 3px;
        }
        
        h3 {
            font-size: 11pt;
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 5px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 3px solid #000;
            padding-bottom: 10px;
        }
        
        .institution {
            font-size: 14pt;
            font-weight: bold;
        }
        
        .field-group {
            margin-bottom: 8px;
        }
        
        .field-label {
            font-weight: bold;
            display: inline-block;
            min-width: 180px;
        }
        
        .field-value {
            display: inline-block;
            border-bottom: 1px solid #333;
            min-width: 250px;
            padding: 0 5px;
        }
        
        .checkbox-group {
            margin: 8px 0;
        }
        
        .checkbox {
            display: inline-block;
            width: 15px;
            height: 15px;
            border: 1px solid #000;
            margin-right: 5px;
            vertical-align: middle;
        }
        
        .declaration {
            background: #f5f5f5;
            border: 1px solid #ccc;
            padding: 10px;
            margin: 10px 0;
            font-style: italic;
        }
        
        .signature-section {
            margin-top: 30px;
            display: table;
            width: 100%;
        }
        
        .signature-box {
            display: table-cell;
            width: 48%;
            text-align: center;
            padding: 10px;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            width: 250px;
            margin: 40px auto 5px;
        }
        
        .requirements-list {
            margin-left: 20px;
        }
        
        .requirements-list li {
            margin-bottom: 5px;
        }
        
        .section-break {
            page-break-before: always;
            margin-top: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        
        table td {
            padding: 5px;
            border: 1px solid #000;
        }
        
        .warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 10px;
            margin: 10px 0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="page">
        {{-- HEADER --}}
        <div class="header">
            <div class="institution">POLYTECHNIC UNIVERSITY OF THE PHILIPPINES</div>
            <div>Taguig Campus</div>
            <h1>Student Admission Record (SAR) Form</h1>
            <div style="font-size: 10pt; margin-top: 5px;">Academic Year {{ $academic_year ?? '2025-2026' }}</div>
        </div>

        {{-- SECTION 1: SAR FORM 1 – CONFIRMATION SLIP --}}
        <h2>SECTION 1: SAR FORM 1 – CONFIRMATION SLIP</h2>
        
        <div class="field-group">
            <span class="field-label">Program Type:</span>
            <span class="checkbox"></span> {{ $program_type ?? '4 or 5-year Degree' }}
        </div>

        <h3>Applicant Information</h3>
        <div class="field-group">
            <span class="field-label">Reference Number:</span>
            <span class="field-value">{{ $reference_number }}</span>
        </div>
        
        <div class="field-group">
            <span class="field-label">Selected NSTP:</span>
            <span class="field-value">{{ $nstp ?? 'CWTS' }}</span>
        </div>
        
        <div class="field-group">
            <span class="field-label">Full Name:</span>
            <span class="field-value">{{ $full_name }}</span>
        </div>
        
        <div class="field-group">
            <span class="field-label">Graduation Year:</span>
            <span class="field-value">{{ $graduation_year }}</span>
        </div>
        
        <div class="field-group">
            <span class="field-label">School Previously Attended:</span>
            <span class="field-value">{{ $school_attended }}</span>
        </div>

        <h3>Declaration</h3>
        <div class="declaration">
            I would like to confirm my interest to be admitted and enrolled this First Semester, 
            Academic Year {{ $academic_year ?? '2025-2026' }} in a program where I am qualified 
            based on the Specific College Criteria.
        </div>

        <h3>Enrollment Details</h3>
        <div class="field-group">
            <span class="field-label">Date of Enrollment:</span>
            <span class="field-value">{{ $enrollment_date }}</span>
        </div>
        
        <div class="field-group">
            <span class="field-label">Time:</span>
            <span class="field-value">{{ $enrollment_time }}</span>
        </div>

        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div>Signature over Printed Name of Applicant</div>
            </div>
        </div>

        {{-- SECTION 2: ROUTE AND APPROVAL SLIP --}}
        <h2 class="section-break">SECTION 2: ROUTE AND APPROVAL SLIP</h2>
        
        <div class="field-group">
            <span class="field-label">Academic Year:</span>
            <span class="field-value">{{ $academic_year ?? '2025-2026' }}</span>
        </div>

        <h3>Applicant Details</h3>
        <div class="field-group">
            <span class="field-label">Name of Applicant:</span>
            <span class="field-value">{{ $full_name }}</span>
        </div>
        
        <div class="field-group">
            <span class="field-label">Reference Number:</span>
            <span class="field-value">{{ $reference_number }}</span>
        </div>
        
        <div class="field-group">
            <span class="field-label">SHS Track / Strand:</span>
            <span class="field-value">{{ $shs_strand }}</span>
        </div>
        
        <div class="field-group">
            <span class="field-label">Date of Enrollment:</span>
            <span class="field-value">{{ $enrollment_date }}</span>
        </div>
        
        <div class="field-group">
            <span class="field-label">Time:</span>
            <span class="field-value">{{ $enrollment_time }}</span>
        </div>

        {{-- SECTION 3: ADMISSION REQUIREMENTS CHECKLIST --}}
        <h2>SECTION 3: ADMISSION REQUIREMENTS CHECKLIST</h2>
        
        <h3>Step 1: Submission of Admission Credentials</h3>
        <div style="font-style: italic; margin-bottom: 10px;">
            Processed by: Admission and Registration Services Section
        </div>

        <h3>Required Documents (Senior High School Graduates)</h3>
        <ul class="requirements-list">
            <li><span class="checkbox"></span> Waiver / Certification / Undertaking (Form 2)</li>
            <li><span class="checkbox"></span> High School Card (F138 / Grade 12) with school dry seal</li>
            <li><span class="checkbox"></span> Grade 11 Report Card with school dry seal</li>
            <li><span class="checkbox"></span> Grade 10 Report Card with school dry seal</li>
            <li><span class="checkbox"></span> Notarized Certification of Non-Enrollment</li>
            <li><span class="checkbox"></span> PSA Birth Certificate</li>
            <li><span class="checkbox"></span> Certification of Good Moral Character</li>
            <li><span class="checkbox"></span> Three (3) pcs 2"x2" photos (white background, name tag)</li>
            <li><span class="checkbox"></span> One (1) long brown envelope</li>
            <li><span class="checkbox"></span> PUPCET e-Permit (Optional)</li>
        </ul>

        <h3>Additional Requirements (Previous SHS / HS Graduates)</h3>
        <ul class="requirements-list">
            <li><span class="checkbox"></span> Certification from SHS Registrar (no F137-A sent to other HEIs)</li>
        </ul>

        {{-- SECTION 4: ADMISSION STATUS AND REMARKS --}}
        <h2 class="section-break">SECTION 4: ADMISSION STATUS AND REMARKS</h2>
        
        <div class="field-group">
            <span class="field-label">Grade 12 GWA:</span>
            <span class="field-value">{{ $gwa ?? '' }}</span>
        </div>
        
        <div class="field-group">
            <span class="field-label">SHS Strand:</span>
            <span class="field-value">{{ $shs_strand }}</span>
        </div>

        <h3>Admission Status (Check if applicable)</h3>
        <div class="checkbox-group">
            <div><span class="checkbox"></span> PUPCET Qualifier</div>
            <div><span class="checkbox"></span> ALS</div>
            <div><span class="checkbox"></span> First Generation Student</div>
            <div><span class="checkbox"></span> Indigenous People</div>
            <div><span class="checkbox"></span> SK Chair</div>
            <div><span class="checkbox"></span> Child of Solo Parent</div>
            <div><span class="checkbox"></span> Person with Disability (Specify): ______________</div>
        </div>

        {{-- SECTION 5: COLLEGE INTERVIEW AND PROGRAM TAGGING --}}
        <h2>SECTION 5: COLLEGE INTERVIEW AND PROGRAM TAGGING</h2>
        <div style="font-style: italic; margin-bottom: 10px;">
            (To be completed by interviewer only)
        </div>
        
        <div class="field-group">
            <span class="field-label">Interviewed by:</span>
            <span class="field-value">{{ $interviewer_name ?? '' }}</span>
        </div>
        
        <div class="field-group">
            <span class="field-label">Program:</span>
            <span class="field-value">{{ $program ?? '' }}</span>
        </div>
        
        <div class="field-group">
            <span class="field-label">Section:</span>
            <span class="field-value">{{ $section ?? '' }}</span>
        </div>

        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div>Signature</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div>Date</div>
            </div>
        </div>

        {{-- DATA PRIVACY NOTICE --}}
        <div class="warning" style="margin-top: 30px;">
            ⚠️ DATA PRIVACY NOTICE: This document contains personal-identifiable information (PII) 
            and is protected under the Data Privacy Act of 2012. Handle with confidentiality.
        </div>

        {{-- FOOTER --}}
        <div style="text-align: center; margin-top: 20px; font-size: 9pt; color: #666;">
            Generated: {{ now()->format('F d, Y h:i A') }} | Reference: {{ $reference_number }}
        </div>
    </div>
</body>
</html>
