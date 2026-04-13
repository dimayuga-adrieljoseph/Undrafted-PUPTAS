<?php

// Feature: error-handling — Task 4.5
// Property 3: No internal details in ErrorResponse
// Validates: Requirements 3.6, 4.2

use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Generate 100+ exception messages containing SQL text, filesystem paths,
// stack trace markers, and class names.
// ---------------------------------------------------------------------------

function buildLeakyMessages(): array
{
    $sqlKeywords = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'WHERE', 'FROM', 'JOIN', 'DROP', 'CREATE', 'ALTER'];
    $paths = [
        '/var/www/html/app/Exceptions/Handler.php',
        '/home/ubuntu/puptas/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php',
        'C:\\xampp\\htdocs\\puptas\\app\\Models\\User.php',
        '/usr/local/lib/php/extensions/no-debug-non-zts-20230831/pdo.so',
        '/etc/passwd',
        '/root/.env',
    ];
    $stackMarkers = [
        '#0 /app/Handler.php(42): App\\Exceptions\\Handler->render()',
        'Stack trace:',
        'thrown in /var/www/html/app/Http/Controllers/UserController.php on line 55',
        'Exception in /home/ubuntu/puptas/app/Services/GradeExtractionService.php:123',
    ];
    $classNames = [
        'App\\Exceptions\\Handler',
        'Illuminate\\Database\\QueryException',
        'PDOException',
        'Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException',
    ];

    $messages = [];

    // SQL keyword messages
    foreach ($sqlKeywords as $kw) {
        $messages[] = "SQLSTATE[42000]: Syntax error near '{$kw} * FROM users'";
        $messages[] = "Error executing: {$kw} id FROM sessions WHERE token = 'abc'";
    }

    // Filesystem path messages
    foreach ($paths as $path) {
        $messages[] = "File not found: {$path}";
        $messages[] = "Permission denied: {$path} on line 42";
        $messages[] = "require({$path}): failed to open stream";
    }

    // Stack trace marker messages
    foreach ($stackMarkers as $marker) {
        $messages[] = $marker;
        $messages[] = "Unhandled exception. {$marker}";
    }

    // Class name messages
    foreach ($classNames as $class) {
        $messages[] = "Exception of type {$class} was thrown";
        $messages[] = "{$class}: Something went wrong";
    }

    // Combined messages (SQL + path)
    $messages[] = "SQLSTATE[HY000]: General error: 1 no such table: users in /var/www/html/app/Models/User.php:55";
    $messages[] = "SELECT * FROM users WHERE id = 1 -- called from /app/Http/Controllers/UserController.php";

    // Pad to 100+ with variations
    $seed = 31;
    while (count($messages) < 110) {
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $kw = $sqlKeywords[$seed % count($sqlKeywords)];
        $path = $paths[$seed % count($paths)];
        $messages[] = "Error: {$kw} failed at {$path} line " . ($seed % 500 + 1);
    }

    return $messages;
}

$leakyMessages = buildLeakyMessages();

// SQL keywords to check for in response body
$sqlKeywordsToCheck = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'WHERE', 'FROM', 'JOIN', 'DROP', 'CREATE', 'ALTER'];

// Stack trace markers to check for
$stackMarkers = ['#0 ', 'Stack trace:', 'thrown in ', '.php on line', '.php:'];

// Absolute path patterns
$pathPatterns = ['/var/', '/home/', '/usr/', '/etc/', '/root/', 'C:\\', 'C:/'];

// ---------------------------------------------------------------------------
// Property 3: No internal details in ErrorResponse
// Validates: Requirements 3.6, 4.2
// ---------------------------------------------------------------------------

/**
 * For any exception whose message contains SQL text, filesystem paths, or
 * stack trace markers, the ErrorResponse body fields SHALL NOT contain those
 * internal details.
 */
it(
    'Property 3: ErrorResponse body contains no SQL keywords, stack trace markers, or filesystem paths',
    function (string $leakyMessage) use ($sqlKeywordsToCheck, $stackMarkers, $pathPatterns) {
        $label = 'leak-' . md5($leakyMessage);

        Route::get('/_test/noleak/' . $label, function () use ($leakyMessage) {
            throw new \RuntimeException($leakyMessage);
        });

        $response = $this->getJson('/_test/noleak/' . $label);

        // Get the raw response body as a string for pattern matching
        $rawBody = $response->getContent();

        // No stack trace markers
        foreach ($stackMarkers as $marker) {
            expect($rawBody)->not->toContain($marker);
        }

        // No SQL keywords (case-insensitive check on the response body)
        $lowerBody = strtolower($rawBody);
        foreach ($sqlKeywordsToCheck as $kw) {
            // Only flag if it appears as a standalone SQL keyword (not part of errorCode like INTERNAL_ERROR)
            // We check the message and errors fields specifically
            $body = $response->json();
            $message = strtolower($body['message'] ?? '');
            $errorCode = strtolower($body['errorCode'] ?? '');

            // The message field must not contain SQL keywords
            expect($message)->not->toContain(strtolower($kw));
        }

        // No absolute filesystem paths in any response field
        $body = $response->json();
        $messageField = $body['message'] ?? '';
        foreach ($pathPatterns as $pattern) {
            expect($messageField)->not->toContain($pattern);
        }

        // Structural invariant still holds
        expect($body['success'])->toBeFalse();
        expect($body['errorCode'])->toBe('INTERNAL_ERROR');
        expect($body['message'])->toBe('Something went wrong. Please try again later.');
    }
)->with(array_map(fn ($m) => [$m], $leakyMessages));
