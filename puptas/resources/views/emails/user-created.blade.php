<p>Dear {{ $user->firstname }},</p>

<p>Your account has been created. Here are your login details:</p>

<p>Email: {{ $user->email }}</p>
<p>Password: {{ $password }}</p>

<p>Please log in and change your password as soon as possible.</p>
<a href="{{ url('/') }}" style="display: inline-block; padding: 10px 20px; color: #fff; background-color: #800000; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold; margin-top: 20px; transition: background-color 0.3s;">Log In</a>

<p>Best regards,</p>
<p>PUP Taguig Admission System</p>
