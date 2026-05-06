<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DoclingService
 *
 * Sends a WebP image to the Docling Serve instance and returns the
 * structured JSON representation of the document content.
 *
 * Endpoint used: POST /v1/convert/file
 * Docs: https://caddy-production-8a10.up.railway.app/docs
 */
class DoclingService
{
    private string $baseUrl;
    private ?string $apiKey;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.docling.url', ''), '/');
        $this->apiKey  = config('services.docling.api_key') ?: null;
        $this->timeout = (int) config('services.docling.timeout', 60);
    }

    /**
     * Convert raw WebP bytes to a structured JSON document via Docling.
     *
     * @param  string  $webpData   Raw binary content of the WebP image
     * @param  string  $filename   Filename hint sent to Docling (e.g. "document.webp")
     * @return array|null          Parsed json_content array, or null on failure
     */
    public function convertToJson(string $webpData, string $filename = 'document.webp'): ?array
    {
        if (empty($this->baseUrl)) {
            Log::warning('DoclingService: DOCLING_URL is not configured, skipping conversion.');
            return null;
        }

        try {
            $request = Http::timeout($this->timeout)
                ->attach('files', $webpData, $filename, ['Content-Type' => 'image/webp'])
                ->when($this->apiKey, fn ($http) => $http->withHeader('Authorization', 'Bearer ' . $this->apiKey));

            $response = $request->post("{$this->baseUrl}/v1/convert/file", [
                'to_formats'   => 'json',
                'from_formats' => 'image',
                'do_ocr'       => 'true',
            ]);

            if ($response->failed()) {
                Log::warning('DoclingService: conversion request failed.', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $body = $response->json();

            return $body['document']['json_content'] ?? null;
        } catch (ConnectionException $e) {
            Log::warning('DoclingService: could not reach Docling service.', [
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error('DoclingService: unexpected error during conversion.', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
