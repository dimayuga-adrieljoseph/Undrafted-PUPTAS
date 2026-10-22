<#
.SYNOPSIS
    Concurrent Interviewer Accept Race Test (AJ-38)
    Fires two parallel accept requests at the same applicant on Railway Staging or Local.

.DESCRIPTION
    Validates pessimistic row locking and concurrency protection in InterviewerDashboardController::accept().
    Under concurrency (double-click, simultaneous reviewers), the database transaction serializes
    on Application::lockForUpdate() and Program::lockForUpdate(), allowing exactly one request to succeed (HTTP 200)
    and cleanly rejecting the second concurrent request with HTTP 409 Conflict without duplicate slot decrements.

.PARAMETER BaseUrl
    The base URL to test against. Defaults to Staging.
    SAFETY GUARD: Any production hostname will immediately abort execution.

.PARAMETER InterviewerEmail
    Email of the interviewer account.

.PARAMETER InterviewerPassword
    Password of the interviewer account.

.PARAMETER ApplicantId
    User ID of the applicant currently at interviewer stage.

.PARAMETER ProgramId
    Target Program ID to accept the applicant into.

.EXAMPLE
    .\interviewer-accept-race.ps1 -ApplicantId 123 -ProgramId 1 -InterviewerEmail "interviewer@example.com" -InterviewerPassword "password"
#>

[CmdletBinding()]
param(
    [string]$BaseUrl = "https://pup-admission-system-staging.up.railway.app",
    [string]$InterviewerEmail = $env:INTERVIEWER_EMAIL,
    [string]$InterviewerPassword = $env:INTERVIEWER_PASSWORD,
    [Parameter(Mandatory = $true)]
    [string]$ApplicantId,
    [Parameter(Mandatory = $true)]
    [int]$ProgramId
)

# -----------------------------------------------------------------------------
# SAFETY GUARD: NEVER RUN AGAINST PRODUCTION
# -----------------------------------------------------------------------------
$normalizedUrl = $BaseUrl.Trim().ToLower()
if ($normalizedUrl -match "prod" -or $normalizedUrl -match "pup-taguig\.edu\.ph" -or $normalizedUrl -match "puptas\.net") {
    Write-Error "==============================================================`nCRITICAL SAFETY ERROR: Execution halted!`nNever run race condition tests against production!`nAllowed targets: Staging or Local environments only.`n==============================================================" -ErrorAction Stop
}

Write-Host "=============================================================" -ForegroundColor Cyan
Write-Host " PUPTAS AJ-38: Concurrent Interviewer Accept Race Test" -ForegroundColor Cyan
Write-Host " Target URL   : $BaseUrl" -ForegroundColor Gray
Write-Host " Applicant ID : $ApplicantId" -ForegroundColor Gray
Write-Host " Program ID   : $ProgramId" -ForegroundColor Gray
Write-Host "=============================================================" -ForegroundColor Cyan

if ([string]::IsNullOrWhiteSpace($InterviewerEmail) -or [string]::IsNullOrWhiteSpace($InterviewerPassword)) {
    Write-Error "Interviewer credentials required. Supply -InterviewerEmail and -InterviewerPassword or set environment variables." -ErrorAction Stop
}

# -----------------------------------------------------------------------------
# 1. AUTHENTICATE AND OBTAIN SESSION COOKIES + CSRF TOKEN
# -----------------------------------------------------------------------------
Write-Host "[1/3] Authenticating as $InterviewerEmail..." -ForegroundColor Yellow
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

try {
    # Fetch login page to retrieve XSRF token
    $loginPage = Invoke-WebRequest -Uri "$BaseUrl/login?local=1" -WebSession $session -Method Get
    
    # Extract CSRF token from cookies or HTML
    $tokenCookie = $session.Cookies.GetCookies([System.Uri]$BaseUrl) | Where-Object { $_.Name -eq 'XSRF-TOKEN' }
    if ($tokenCookie) {
        $csrfToken = [System.Web.HttpUtility]::UrlDecode($tokenCookie.Value)
    } elseif ($loginPage.Content -match 'name="_token"\s+value="([^"]+)"') {
        $csrfToken = $matches[1]
    } else {
        throw "Could not obtain CSRF token from login page."
    }

    # Perform login
    $loginBody = @{
        email    = $InterviewerEmail
        password = $InterviewerPassword
        _token   = $csrfToken
    }

    $loginResponse = Invoke-WebRequest -Uri "$BaseUrl/login" -WebSession $session -Method Post -Body $loginBody -Headers @{
        "Accept" = "text/html,application/xhtml+xml,application/xml"
    }

    Write-Host "      Authentication successful." -ForegroundColor Green
} catch {
    Write-Error "Authentication failed: $_" -ErrorAction Stop
}

# Refresh CSRF token post-auth
$authXsrfCookie = $session.Cookies.GetCookies([System.Uri]$BaseUrl) | Where-Object { $_.Name -eq 'XSRF-TOKEN' }
$activeCsrf = if ($authXsrfCookie) { [System.Web.HttpUtility]::UrlDecode($authXsrfCookie.Value) } else { $csrfToken }

# Extract session cookie string for parallel workers
$cookieHeader = ($session.Cookies.GetCookies([System.Uri]$BaseUrl) | ForEach-Object { "$($_.Name)=$($_.Value)" }) -join "; "

# -----------------------------------------------------------------------------
# 2. DISPATCH PARALLEL ACCEPT REQUESTS
# -----------------------------------------------------------------------------
Write-Host "[2/3] Firing 2 parallel POST /interviewer-dashboard/accept/$ApplicantId requests..." -ForegroundColor Yellow

$acceptUrl = "$BaseUrl/interviewer-dashboard/accept/$ApplicantId"
$payloadJson = @{
    program_id = $ProgramId
    notes      = "Automated race condition test verification (AJ-38)"
} | ConvertTo-Json -Compress

$requestScript = {
    param($url, $cookie, $csrf, $body, $workerId)
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    $headers = @{
        "Cookie"       = $cookie
        "X-XSRF-TOKEN" = $csrf
        "Content-Type" = "application/json"
        "Accept"       = "application/json"
    }

    try {
        $res = Invoke-RestMethod -Uri $url -Method Post -Headers $headers -Body $body
        $sw.Stop()
        return [PSCustomObject]@{
            WorkerId     = $workerId
            StatusCode   = 200
            DurationMs   = $sw.ElapsedMilliseconds
            ResponseBody = ($res | ConvertTo-Json -Compress)
        }
    } catch {
        $sw.Stop()
        $status = if ($_.Exception.Response) { [int]$_.Exception.Response.StatusCode } else { 0 }
        $bodyText = ""
        if ($_.Exception.Response) {
            $stream = $_.Exception.Response.GetResponseStream()
            if ($stream) {
                $reader = New-Object System.IO.StreamReader($stream)
                $bodyText = $reader.ReadToEnd()
            }
        }
        return [PSCustomObject]@{
            WorkerId     = $workerId
            StatusCode   = $status
            DurationMs   = $sw.ElapsedMilliseconds
            ResponseBody = $bodyText
            Error        = $_.Exception.Message
        }
    }
}

# Launch parallel jobs
$job1 = Start-Job -ScriptBlock $requestScript -ArgumentList $acceptUrl, $cookieHeader, $activeCsrf, $payloadJson, 1
$job2 = Start-Job -ScriptBlock $requestScript -ArgumentList $acceptUrl, $cookieHeader, $activeCsrf, $payloadJson, 2

$results = @($job1, $job2) | ForEach-Object {
    $_ | Wait-Job | Receive-Job
    $_ | Remove-Job
}

# -----------------------------------------------------------------------------
# 3. EVALUATE CONCURRENCY LOCK INTEGRITY
# -----------------------------------------------------------------------------
Write-Host "[3/3] Evaluating response outcomes..." -ForegroundColor Yellow
Write-Host ""
$results | Format-Table -Property WorkerId, StatusCode, DurationMs, ResponseBody -AutoSize

$statusCodes = $results.StatusCode
$count200 = ($statusCodes | Where-Object { $_ -eq 200 }).Count
$count409 = ($statusCodes | Where-Object { $_ -eq 409 }).Count
$count500 = ($statusCodes | Where-Object { $_ -ge 500 }).Count

Write-Host "Results Summary:" -ForegroundColor Cyan
Write-Host "  HTTP 200 OK       : $count200 (Expected: 1)" -ForegroundColor $(if ($count200 -eq 1) { "Green" } else { "Red" })
Write-Host "  HTTP 409 Conflict : $count409 (Expected: 1)" -ForegroundColor $(if ($count409 -eq 1) { "Green" } else { "Red" })
Write-Host "  HTTP 500+ Errors  : $count500 (Expected: 0)" -ForegroundColor $(if ($count500 -eq 0) { "Green" } else { "Red" })

if ($count200 -eq 1 -and $count409 -eq 1 -and $count500 -eq 0) {
    Write-Host "`nPASS: Pessimistic row locking successfully serialized parallel requests!" -ForegroundColor Green
    Write-Host "Exactly one request was granted; the concurrent request cleanly aborted with 409 Conflict." -ForegroundColor Green
    exit 0
} else {
    Write-Host "`nFAIL: Concurrency protection did not meet expectations." -ForegroundColor Red
    exit 1
}
