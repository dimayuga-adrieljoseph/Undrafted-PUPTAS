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
        .status-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .status-notice h3 {
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
        .custom-content {
            background: white;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div style="max-width: 600px; width: 100%; margin: 0 auto;">
        <div class="header">
            <h1>Polytechnic University of the Philippines</h1>
            <p>Taguig Campus - Admission Office</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Dear {{ $firstName }} {{ $surname }},
            </div>
            
            <div class="message">
                <p>Thank you for your interest in PUP Taguig Campus.</p>
            </div>
            
            <div class="info-box">
                <p><strong>Reference Number:</strong> {{ $referenceNumber }}</p>
                <p><strong>Status:</strong> Waitlisted</p>
            </div>
            
            <div class="status-notice">
                <h3>📋 Waitlist Status Information</h3>
                <p>You have been placed on our waitlist. This means that while you have met our admission requirements, we currently do not have available slots in your chosen program.</p>
            </div>
            
            @if($customMessage)
            <div class="custom-content">
                {!! $customMessage !!}
            </div>
            @endif
            
            <div class="message">
                <p><strong>What happens next?</strong></p>
                <ul style="line-height: 2;">
                    <li>You will be notified if a slot becomes available</li>
                    <li>Please monitor your email regularly for updates</li>
                    <li>Keep your contact information up to date</li>
                    <li>You may also check your application status online</li>
                </ul>
            </div>
            
            <div class="message">
                <p>If you have any questions or need assistance, please contact the Admission Office at:</p>
                <p style="margin-left: 20px;">
                    📧 Email: admission@pup.edu.ph<br>
                    📞 Phone: (02) 8123-4567
                </p>
            </div>
            
            <div class="message">
                <p>We appreciate your patience and understanding.</p>
                <p><strong>Best regards,</strong><br>
                PUP Taguig Admission and Registration Office</p>
            </div>
        </div>
        
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>© {{ date('Y') }} Polytechnic University of the Philippines - Taguig Campus</p>
            <p style="margin-top: 10px; font-size: 11px; color: #999;">
                This email and any attachments contain confidential information intended solely for the recipient.
            </p>
        </div>
    </div>
</body>
</html>
