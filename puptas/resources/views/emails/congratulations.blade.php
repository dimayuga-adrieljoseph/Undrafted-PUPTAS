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
        .congratulations-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-left: 4px solid #28a745;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .congratulations-box h3 {
            margin: 0 0 10px 0;
            color: #155724;
            font-size: 16px;
        }
        .congratulations-box p {
            margin: 0;
            color: #155724;
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
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
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
                Congratulations!
            </div>

            <div class="congratulations-box">
                <h3>🎉 You Have Been Accepted!</h3>
                <p>We are pleased to inform you that you have passed the admission process for PUP Taguig Campus.</p>
            </div>

            <div class="message">
                <p>This is to officially notify you that you have successfully met the requirements for admission to the Polytechnic University of the Philippines - Taguig Campus.</p>
            </div>

            <div class="info-box">
                <p><strong>What to do next:</strong></p>
                <ul style="line-height: 2; margin: 10px 0;">
                    <li>Check your email regularly for enrollment instructions</li>
                    <li>Prepare the required documents for enrollment</li>
                    <li>Visit the campus on your designated enrollment schedule</li>
                </ul>
            </div>

            <div class="message">
                <p>If you have any questions or need assistance, please contact the Admission and Registration Office at:</p>
                <p style="margin-left: 20px;">
                    📧 Email: <a href="mailto:taguig@pup.edu.ph">taguig@pup.edu.ph</a> / <a href="mailto:puptadmission@gmail.com">puptadmission@gmail.com</a>
                </p>
            </div>

            <div class="message">
                <p>We look forward to welcoming you to the PUP Taguig community!</p>
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
