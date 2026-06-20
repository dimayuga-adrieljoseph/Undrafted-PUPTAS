<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Service Unavailable - PUPTAS</title>
    <link rel="icon" type="image/png" href="/assets/images/pup_logo.png" />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100 text-gray-900 flex items-center justify-center min-h-screen">
    <div class="max-w-xl w-full px-6 py-12 bg-white shadow-xl rounded-2xl text-center relative overflow-hidden">
        <!-- Optional top accent line -->
        <div class="absolute top-0 left-0 w-full h-2 bg-[#9E122C]"></div>

        <div class="mb-6 flex justify-center mt-2">
            <img src="/assets/images/pup_logo.png" alt="PUP Logo" class="h-20 w-20">
        </div>
        
        <h1 class="text-3xl font-bold text-[#9E122C] mb-4">SSO Login Temporarily Unavailable</h1>
        
        <div class="space-y-4 text-gray-600 mb-8 text-base">
            <p>
                We are currently performing maintenance on our Single Sign-On (SSO) system. 
                We're working to have this resolved by <strong>Sunday, June 21, 2026.</strong> 
                We apologize for the inconvenience.
            </p>
            <p>
                For urgent concerns, email us at <a href="mailto:puptadmission@gmail.com" class="text-[#9E122C] font-semibold hover:underline">puptadmission@gmail.com</a> 
                or message us on our chat support.
            </p>
            <p>
                You may still view general admission information at the homepage.
            </p>
        </div>

        <div class="mb-8">
            <a href="/" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-[#9E122C] hover:bg-[#b51834] transition shadow-md">
                Return to Homepage
            </a>
        </div>

        <div class="border-t border-gray-100 pt-4 mt-2">
            <p class="text-xs text-gray-400 font-mono tracking-wide uppercase">
                Maintenance started: {{ date('F j, Y') }}
            </p>
        </div>
    </div>
</body>
</html>
