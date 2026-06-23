<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Emergency Login OTP</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px;">
    <div style="max-w-width: 600px; margin: 0 auto; background: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <h2 style="color: #9E122C; text-align: center;">PUPTAS Emergency Login</h2>
        <p>You have requested to log in using the emergency access portal.</p>
        <p>Your One-Time Password (OTP) is:</p>
        <div style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; padding: 15px; text-align: center; margin: 20px 0;">
            <span style="font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #333;">{{ $otp }}</span>
        </div>
        <p style="color: #666; font-size: 14px;">This OTP will expire in 5 minutes. If you did not request this, please ignore this email.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #999; font-size: 12px; text-align: center;">Polytechnic University of the Philippines</p>
    </div>
</body>
</html>
