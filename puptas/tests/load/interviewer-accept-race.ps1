<#
.SYNOPSIS
    Concurrent Interviewer Accept Race Test (AJ-38)
    Fires two parallel accept requests at the same applicant on Staging or Local MySQL.

.DESCRIPTION
    Validates pessimistic row locking and concurrency protection in InterviewerDashboardController::accept().
    Under concurrency (double-click, simultaneous reviewers), the database transaction serializes
    on Application::lockForUpdate() and Program::lockForUpdate(), allowing exactly one request to succeed (HTTP 200)
    and cleanly rejecting the second concurrent request with HTTP 409 Conflict without duplicate slot decrements.

    Safety:
    Uses a strict allow-list: only pup-admission-system-staging.up.railway.app, localhost, and 127.0.0.1 are allowed.
    Any other host (including production) immediately aborts.

.PARAMETER BaseUrl
    The base URL to test against. Allowed: Staging or Localhost/127.0.0.1 only.
    Default: "http://127.0.0.1:8000"

.PARAMETER N
    Number of applicants to run in the test loop. Default: 10.

.PARAMETER ApplicantIds
    Array or comma-separated string of applicant user IDs currently at the interviewer stage.
    If not specified, will be prompted or expected to match N.

.PARAMETER ProgramId
    Target Program ID to accept the applicants into.

.PARAMETER SessionCookie
    Raw 'puptas_session' cookie value from an already authenticated browser session.
    Required for staging environments where /dev-login is disabled (APP_DEBUG=false) and IDP OAuth is active.
    Can also be supplied via $env:PUPTAS_SESSION_COOKIE or entered at the interactive prompt.

.PARAMETER XsrfToken
    Raw 'XSRF-TOKEN' cookie value from the browser session. If omitted, the script auto-extracts
    the active CSRF token by querying /interviewer-dashboard with the session cookie.
    Can also be supplied via $env:PUPTAS_XSRF_TOKEN.
#>

[CmdletBinding()]
param(
    [string]$BaseUrl = "http://127.0.0.1:8000",
    [int]$N = 10,
    [string[]]$ApplicantIds,
    [Parameter(Mandatory = $true)]
    [int]$ProgramId,
    [string]$InterviewerEmail = $env:INTERVIEWER_EMAIL,
    [string]$InterviewerPassword = $env:INTERVIEWER_PASSWORD,
    [string]$SessionCookie = $env:PUPTAS_SESSION_COOKIE,
    [string]$XsrfToken = $env:PUPTAS_XSRF_TOKEN
)

# -----------------------------------------------------------------------------
# 1. STRICT ALLOW-LIST HOST CHECK (NEVER RUN AGAINST PRODUCTION)
# -----------------------------------------------------------------------------
$allowedHosts = @(
    'pup-admission-system-staging.up.railway.app',
    'localhost',
    '127.0.0.1'
)

$uri = [System.Uri]$BaseUrl
$targetHost = $uri.Host.ToLower()

if ($allowedHosts -notcontains $targetHost) {
    Write-Error @"
================================================================================
CRITICAL SAFETY ERROR: Host '$targetHost' is NOT permitted!
Execution halted immediately.
Allowed hosts:
  - pup-admission-system-staging.up.railway.app (Staging)
  - localhost (Local development)
  - 127.0.0.1 (Local development)
================================================================================
"@ -ErrorAction Stop
    exit 1
}

# -----------------------------------------------------------------------------
# 2. CREDENTIAL / SESSION COLLECTION (ENV, PROMPT, OR PARAMETER)
# -----------------------------------------------------------------------------
$hasSessionCookie = -not [string]::IsNullOrWhiteSpace($SessionCookie)
$hasCredentials = (-not [string]::IsNullOrWhiteSpace($InterviewerEmail)) -and (-not [string]::IsNullOrWhiteSpace($InterviewerPassword))

if (-not $hasSessionCookie -and -not $hasCredentials) {
    if ([string]::IsNullOrWhiteSpace($InterviewerEmail)) {
        $inputEmail = Read-Host "Enter Interviewer Email (or press Enter to supply browser session cookie)"
        if (-not [string]::IsNullOrWhiteSpace($inputEmail)) {
            $InterviewerEmail = $inputEmail
            $securePass = Read-Host "Enter Interviewer Password" -AsSecureString
            $bstr = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePass)
            $InterviewerPassword = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
            [System.Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
            $hasCredentials = $true
        }
    }

    if (-not $hasCredentials) {
        Write-Host "No credentials provided. Prompting for browser session cookie (staging mode)..." -ForegroundColor Yellow
        $SessionCookie = Read-Host "Enter 'puptas_session' cookie value"
        if ([string]::IsNullOrWhiteSpace($SessionCookie)) {
            Write-Error "Either Interviewer credentials (email/password) or browser session cookie (puptas_session) required." -ErrorAction Stop
        }
        $XsrfToken = Read-Host "Enter 'XSRF-TOKEN' value (optional, press Enter to auto-extract)"
        $hasSessionCookie = $true
    }
}

# -----------------------------------------------------------------------------
# 3. APPLICANT IDS VALIDATION
# -----------------------------------------------------------------------------
if ($ApplicantIds) {
    if ($ApplicantIds.Count -eq 1 -and $ApplicantIds[0] -match ',') {
        $ApplicantIds = $ApplicantIds[0].Split(',') | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne "" }
    }
}

if (-not $ApplicantIds -or $ApplicantIds.Count -eq 0) {
    $enteredIds = Read-Host "Enter $N Applicant IDs (comma-separated)"
    if (-not [string]::IsNullOrWhiteSpace($enteredIds)) {
        $ApplicantIds = $enteredIds.Split(',') | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne "" }
    }
}

if (-not $ApplicantIds -or $ApplicantIds.Count -lt $N) {
    Write-Error "Insufficient applicant IDs provided. Need at least $N applicant IDs (got $($ApplicantIds.Count))." -ErrorAction Stop
}

$targetApplicants = $ApplicantIds[0..($N - 1)]

Write-Host "=============================================================" -ForegroundColor Cyan
Write-Host " PUPTAS AJ-38: Concurrent Interviewer Accept Race Test" -ForegroundColor Cyan
Write-Host " Target URL   : $BaseUrl" -ForegroundColor Gray
Write-Host " Program ID   : $ProgramId" -ForegroundColor Gray
Write-Host " Rounds (N)   : $N applicants" -ForegroundColor Gray
Write-Host " Host Check   : ALLOWED ($targetHost)" -ForegroundColor Green
Write-Host " Auth Mode    : $(if (-not [string]::IsNullOrWhiteSpace($SessionCookie)) { 'Browser Session Cookie' } else { 'Direct Credentials / Login' })" -ForegroundColor Gray
Write-Host "=============================================================" -ForegroundColor Cyan

# -----------------------------------------------------------------------------
# 4. AUTHENTICATE AND CAPTURE SESSION COOKIES + CSRF TOKEN
# -----------------------------------------------------------------------------
Write-Host "`n[1/4] Establishing authenticated session..." -ForegroundColor Yellow
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$authenticated = $false

# Mode A: User supplied browser session cookie directly (staging / IDP bypass)
if (-not [string]::IsNullOrWhiteSpace($SessionCookie)) {
    Write-Host "      Configuring session using provided browser cookie..." -ForegroundColor Green
    $cleanSession = $SessionCookie.Trim()
    $session.Cookies.Add((New-Object System.Net.Cookie("puptas_session", $cleanSession, "/", $uri.Host)))
    
    if (-not [string]::IsNullOrWhiteSpace($XsrfToken)) {
        $cleanXsrf = $XsrfToken.Trim()
        $session.Cookies.Add((New-Object System.Net.Cookie("XSRF-TOKEN", $cleanXsrf, "/", $uri.Host)))
    }
    $authenticated = $true
} else {
    # Mode B: Automated login via /dev-login or form POST
    Write-Host "      Authenticating as $InterviewerEmail..." -ForegroundColor Yellow
    try {
        # 1. Try dev-login shortcut first if local or staging debug enabled
        try {
            $devRes = Invoke-WebRequest -Uri "$BaseUrl/dev-login?email=$InterviewerEmail" -WebSession $session -Method Get -UseBasicParsing -MaximumRedirection 5 -ErrorAction Stop
            $sessCookie = $session.Cookies.GetCookies($uri) | Where-Object { $_.Name -match "session" }
            if ($sessCookie) {
                $authenticated = $true
                Write-Host "      Authenticated via dev-login session." -ForegroundColor Green
            }
        } catch {
            $authenticated = $false
        }

        # 2. Fallback to standard form /login POST
        if (-not $authenticated) {
            $loginPage = Invoke-WebRequest -Uri "$BaseUrl/login?local=1" -WebSession $session -Method Get -UseBasicParsing
            
            if ($loginPage.Content -match 'name="_token"\s+value="([^"]+)"') {
                $htmlToken = $matches[1]
            } else {
                throw "Could not obtain CSRF _token from login HTML."
            }

            $loginBody = @{
                email    = $InterviewerEmail
                password = $InterviewerPassword
                _token   = $htmlToken
            }

            $null = Invoke-WebRequest -Uri "$BaseUrl/login" -WebSession $session -Method Post -Body $loginBody -Headers @{
                "Accept" = "text/html,application/xhtml+xml,application/xml"
            } -UseBasicParsing -MaximumRedirection 5

            Write-Host "      Authenticated via standard login POST." -ForegroundColor Green
            $authenticated = $true
        }
    } catch {
        Write-Warning "Automated login failed: $_"
        Write-Host "If running on Staging with APP_DEBUG=false or IDP OAuth, please supply browser session cookies:" -ForegroundColor Yellow
        $SessionCookie = Read-Host "Enter 'puptas_session' cookie value (or press Enter to abort)"
        if ([string]::IsNullOrWhiteSpace($SessionCookie)) {
            Write-Error "Authentication failed and no session cookie provided. Halting." -ErrorAction Stop
        }
        $XsrfToken = Read-Host "Enter 'XSRF-TOKEN' value (optional, press Enter to auto-extract)"
        $session.Cookies.Add((New-Object System.Net.Cookie("puptas_session", $SessionCookie.Trim(), "/", $uri.Host)))
        if (-not [string]::IsNullOrWhiteSpace($XsrfToken)) {
            $session.Cookies.Add((New-Object System.Net.Cookie("XSRF-TOKEN", $XsrfToken.Trim(), "/", $uri.Host)))
        }
        $authenticated = $true
    }
}

# Prime interviewer session and retrieve XSRF token
$dash = Invoke-WebRequest -Uri "$BaseUrl/interviewer-dashboard" -WebSession $session -Method Get -UseBasicParsing -ErrorAction SilentlyContinue

# Verify dashboard access (ensure cookie isn't expired / guest redirect)
if ($dash.StatusCode -eq 302 -or $dash.BaseResponse.ResponseUri.AbsolutePath -match '/login') {
    Write-Error "Session authentication failed: Request redirected to login. Verify interviewer role or fresh session cookie." -ErrorAction Stop
}

$authXsrfCookie = $session.Cookies.GetCookies($uri) | Where-Object { $_.Name -eq 'XSRF-TOKEN' }
$activeCsrf = if (-not [string]::IsNullOrWhiteSpace($XsrfToken)) {
    [System.Uri]::UnescapeDataString($XsrfToken.Trim())
} elseif ($authXsrfCookie) {
    [System.Uri]::UnescapeDataString($authXsrfCookie.Value)
} else {
    ""
}

# Convert session cookies into serializable objects for background worker jobs
$rawCookies = $session.Cookies.GetCookies($uri)
$cookiePayload = @()
foreach ($c in $rawCookies) {
    $cookiePayload += [PSCustomObject]@{
        Name   = $c.Name
        Value  = $c.Value
        Path   = $c.Path
        Domain = $c.Domain
    }
}

# -----------------------------------------------------------------------------
# 5. QUERY INITIAL PROGRAM SLOTS BEFORE RACE RUN
# -----------------------------------------------------------------------------
Write-Host "`n[2/4] Querying program slots before race test..." -ForegroundColor Yellow

function Get-ProgramSlots([string]$url, $sess, [int]$progId) {
    try {
        $res = Invoke-RestMethod -Uri "$url/api/staff/programs/slots" -Method Get -WebSession $sess -UseBasicParsing
        $prog = $res.programs | Where-Object { $_.id -eq $progId }
        if ($prog) { return [int]$prog.slots }
    } catch {
        Write-Warning "Could not fetch slots via API: $_"
    }
    return $null
}

$initialSlots = Get-ProgramSlots -url $BaseUrl -sess $session -progId $ProgramId
if ($null -ne $initialSlots) {
    Write-Host "      Initial Program $ProgramId Slots: $initialSlots" -ForegroundColor Green
} else {
    Write-Host "      (Slot query via API returned null; will evaluate response codes)" -ForegroundColor Gray
}

# -----------------------------------------------------------------------------
# 6. EXECUTE PARALLEL RACE ON N APPLICANTS WITH SYNCHRONIZED START TIME
# -----------------------------------------------------------------------------
Write-Host "`n[3/4] Executing synchronized parallel race over $N applicants..." -ForegroundColor Yellow

$roundResults = @()

$workerScript = {
    param($url, $cookies, $csrf, $body, $workerId, [long]$targetEpochMs)

    # Reconstruct worker web session with cookies
    $workerSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    foreach ($c in $cookies) {
        $workerSession.Cookies.Add((New-Object System.Net.Cookie($c.Name, $c.Value, $c.Path, $c.Domain)))
    }

    # Tight synchronization wait: hold execution until exact target millisecond
    while ([DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds() -lt $targetEpochMs) {
        [System.Threading.Thread]::Sleep(1)
    }

    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    $headers = @{
        "X-CSRF-TOKEN" = $csrf
        "X-XSRF-TOKEN" = $csrf
        "Content-Type" = "application/json"
        "Accept"       = "application/json"
    }

    try {
        $res = Invoke-RestMethod -Uri $url -Method Post -Headers $headers -Body $body -WebSession $workerSession -UseBasicParsing
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

$roundIndex = 0
foreach ($applicantId in $targetApplicants) {
    $roundIndex++
    $acceptUrl = "$BaseUrl/interviewer-dashboard/accept/$applicantId"
    $payloadJson = @{
        program_id = $ProgramId
        notes      = "AJ-38 race condition round $roundIndex"
    } | ConvertTo-Json -Compress

    # Set shared start time: 2000ms ahead for synchronized concurrent execution
    $sharedStartTimeEpochMs = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds() + 2000

    $job1 = Start-Job -ScriptBlock $workerScript -ArgumentList $acceptUrl, $cookiePayload, $activeCsrf, $payloadJson, 1, $sharedStartTimeEpochMs
    $job2 = Start-Job -ScriptBlock $workerScript -ArgumentList $acceptUrl, $cookiePayload, $activeCsrf, $payloadJson, 2, $sharedStartTimeEpochMs

    $jobs = @($job1, $job2)
    $results = $jobs | ForEach-Object {
        $_ | Wait-Job | Receive-Job
        $_ | Remove-Job
    }

    $statusCodes = $results.StatusCode
    $has200 = ($statusCodes -contains 200)
    $has409 = ($statusCodes -contains 409)
    $has500 = ($statusCodes | Where-Object { $_ -ge 500 }).Count -gt 0
    $passed = $has200 -and $has409 -and (-not $has500)

    $roundResult = [PSCustomObject]@{
        Round        = $roundIndex
        ApplicantId  = $applicantId
        Worker1_Code = $results[0].StatusCode
        Worker1_Ms   = $results[0].DurationMs
        Worker2_Code = $results[1].StatusCode
        Worker2_Ms   = $results[1].DurationMs
        Clean_200    = if ($has200) { "YES" } else { "NO" }
        Clean_409    = if ($has409) { "YES" } else { "NO" }
        Status       = if ($passed) { "PASS" } else { "FAIL" }
    }
    $roundResults += $roundResult

    $color = if ($passed) { "Green" } else { "Red" }
    Write-Host ("  Round {0,2}/$N [Applicant ID: {1,4}] -> Worker1: {2} ({3,3}ms), Worker2: {4} ({5,3}ms) => {6}" -f `
        $roundIndex, $applicantId, $results[0].StatusCode, $results[0].DurationMs, $results[1].StatusCode, $results[1].DurationMs, $roundResult.Status) -ForegroundColor $color
}

# -----------------------------------------------------------------------------
# 7. QUERY FINAL PROGRAM SLOTS & VERIFY OVERALL DECREMENT
# -----------------------------------------------------------------------------
Write-Host "`n[4/4] Evaluating overall test results and slot decrements..." -ForegroundColor Yellow

$finalSlots = Get-ProgramSlots -url $BaseUrl -sess $session -progId $ProgramId

Write-Host "`nPer-Round Concurrency Results:" -ForegroundColor Cyan
$roundResults | Format-Table -Property Round, ApplicantId, Worker1_Code, Worker1_Ms, Worker2_Code, Worker2_Ms, Clean_200, Clean_409, Status -AutoSize

$totalPass = ($roundResults | Where-Object { $_.Status -eq "PASS" }).Count
$totalFail = ($roundResults | Where-Object { $_.Status -ne "PASS" }).Count

Write-Host "Summary Statistics:" -ForegroundColor Cyan
Write-Host "  Rounds Passed (exactly one 200 and one 409) : $totalPass / $N" -ForegroundColor $(if ($totalPass -eq $N) { "Green" } else { "Red" })
Write-Host "  Rounds Failed                               : $totalFail / $N" -ForegroundColor $(if ($totalFail -eq 0) { "Green" } else { "Red" })

$slotDecrementedCorrectly = $true
if ($null -ne $initialSlots -and $null -ne $finalSlots) {
    $expectedFinalSlots = $initialSlots - $N
    $actualDecrement = $initialSlots - $finalSlots
    Write-Host "  Initial Program Slots : $initialSlots" -ForegroundColor Gray
    Write-Host "  Final Program Slots   : $finalSlots" -ForegroundColor Gray
    Write-Host "  Actual Slot Decrement : -$actualDecrement (Expected: -$N)" -ForegroundColor $(if ($actualDecrement -eq $N) { "Green" } else { "Red" })
    if ($actualDecrement -ne $N) {
        $slotDecrementedCorrectly = $false
    }
}

if ($totalPass -eq $N -and $slotDecrementedCorrectly) {
    Write-Host "`n=============================================================" -ForegroundColor Green
    Write-Host " SUCCESS: AJ-38 Parallel Race Condition Verified!" -ForegroundColor Green
    Write-Host " All $N applicants processed: exactly one 200 and one 409 per round." -ForegroundColor Green
    Write-Host " Program slots accurately decremented by exactly $N overall." -ForegroundColor Green
    Write-Host "=============================================================" -ForegroundColor Green
    exit 0
} else {
    Write-Host "`n=============================================================" -ForegroundColor Red
    Write-Host " FAILURE: Concurrency race condition detected or slots mismatch." -ForegroundColor Red
    Write-Host "=============================================================" -ForegroundColor Red
    exit 1
}
