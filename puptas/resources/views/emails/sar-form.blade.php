<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #9E122C;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border: 1px solid #ddd;
            border-top: none;
        }
        .greeting {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #9E122C;
        }
        .message {
            margin-bottom: 20px;
            line-height: 1.8;
        }
        .info-box {
            background: white;
            border-left: 4px solid #9E122C;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box strong {
            color: #9E122C;
        }
        .attachment-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .attachment-notice h3 {
            margin: 0 0 10px 0;
            color: #856404;
            font-size: 16px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
        }
        .button {
            display: inline-block;
            background: #9E122C;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Polytechnic University of the Philippines</h1>
        <p>Taguig Campus - Admission Office</p>
    </div>
    
    <div class="content">
        <div class="greeting">
            Dear {{ $passerName }},
        </div>
        
        <div class="message">
            <p>Congratulations on your successful application to PUP Taguig Campus!</p>
            
            <p>We are pleased to provide you with your <strong>Student Admission Record (SAR)</strong> form. Please download your SAR form using the button below.</p>
        </div>
        
        <div class="info-box">
            <p><strong>Reference Number:</strong> {{ $referenceNumber }}</p>
            <p><strong>Document:</strong> Student Admission Record (SAR)</p>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $downloadUrl }}" class="button" style="color: white; font-weight: bold; font-size: 16px;">
                📄 Download Your SAR Form
            </a>
            <p style="margin-top: 10px; font-size: 13px; color: #666;">
                Click the button above to download your SAR form as a PDF
            </p>
        </div>
        
        <div class="attachment-notice">
            <h3>📋 Important Instructions</h3>
            <p>Please <strong>download and print</strong> your SAR form for your records and enrollment process.</p>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>This document is required for your enrollment</li>
                <li>Please keep it safe and bring it during enrollment</li>
                <li>Review all information for accuracy</li>
            </ul>
        </div>
        
        <div class="message">
            <p><strong>Next Steps:</strong></p>
            <ol style="line-height: 2;">
                <li>Click the download button above to get your SAR form PDF</li>
                <li>Print the document</li>
                <li>Review all information for accuracy</li>
                <li>Bring this form on your scheduled enrollment date</li>
                <li>Follow the admission requirements checklist in the SAR form</li>
            </ol>
        </div>
        
        <div class="message">
            <p>If you have any questions or need assistance, please contact the Admission Office at:</p>
            <p style="margin-left: 20px;">
                📧 Email: admission@pup.edu.ph<br>
                📞 Phone: (02) 8123-4567
            </p>
        </div>
        
        <div class="message">
            <p>We look forward to welcoming you to the PUP community!</p>
            <p><strong>Best regards,</strong><br>
            PUP Taguig Admission Office</p>
        </div>
    </div>
    
    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>© {{ date('Y') }} Polytechnic University of the Philippines - Taguig Campus</p>
        <p style="margin-top: 10px; font-size: 11px; color: #999;">
            This email and any attachments contain confidential information intended solely for the recipient.
        </p>
    </div>
</body>
</html>
