<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #1a1a1a !important;
                color: #e0e0e0 !important;
            }
            .wrapper {
                background-color: #2a2a2a !important;
            }
            p {
                color: #e0e0e0 !important;
            }
            .btn-login {
                background-color: #c0392b !important;
            }
        }
    </style>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px;">
<div class="wrapper" style="max-width: 600px; width: 100%; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px;">
<p>Dear {{ $user->firstname }},</p>

<p>Your account has been created. Here are your login details:</p>

<p>Email: {{ $user->email }}</p>
<p>Password: {{ $password }}</p>

<p>Please log in and change your password as soon as possible.</p>
<a href="{{ url('/') }}" class="btn-login" style="display: inline-block; padding: 10px 20px; color: #ffffff; background-color: #800000; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold; margin-top: 20px;">Log In</a>

<p>Best regards,</p>
<p>PUP Taguig Admission System</p>
</div>
</body>
</html>
