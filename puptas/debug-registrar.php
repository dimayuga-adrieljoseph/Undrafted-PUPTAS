<?php
// Debug registrar login issue
// Run with: php debug-registrar.php

$baseUrl = 'https://puptas.undraftedbsit2027.com';

echo "=== Registrar Debug ===\n\n";

// Test 1: Can we reach the login page?
$ch = curl_init("$baseUrl/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Login page: HTTP $httpCode\n";

// Test 2: Can we reach the record-dashboard directly?
$ch = curl_init("$baseUrl/record-dashboard");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Record dashboard (unauthenticated): HTTP $httpCode\n";

// Test 3: Check if there's a specific error in the response body
$ch = curl_init("$baseUrl/record-dashboard");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Record dashboard (JSON): HTTP $httpCode\n";
if ($httpCode >= 400) {
    echo "Error body: " . substr($response, 0, 500) . "\n";
}

echo "\n=== Done ===\n";
echo "\nNOTE: Check Railway logs for the actual PHP error.\n";
echo "In Railway dashboard: Go to your service → Deployments → View Logs\n";
