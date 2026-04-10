<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFile;
use App\Services\OpenRouterClient;
use Illuminate\Support\Facades\Storage;

class GradeExtractionService
{
    public function __construct(
        private OpenRouterClient $openRouterClient
    ) {}

    /**
     * Main entry point: extract grades for the given user.
     *
     * @param  User  $user
     * @return array  Normalized ExtractionResult
     *
     * @throws \InvalidArgumentException if no valid image files are found
     */
    public function extract(User $user): array
    {
        $images = $this->loadImages($user);

        if (empty($images)) {
            throw new \InvalidArgumentException('No valid image files found for extraction.');
        }

        $prompt = $this->buildPrompt();

        $raw = $this->openRouterClient->send($images, $prompt);

        $sanitized = $this->sanitize($raw);
        $parsed    = $this->parse($sanitized);
        $validated = $this->validate($parsed);
        $result    = $this->normalizeKeys($validated);

        return $result;
    }

    /**
     * Load image files belonging to the user (jpeg/png only).
     *
     * @param  User  $user
     * @return array<int, array{mime_type: string, data: string}>
     */
    protected function loadImages(User $user): array
    {
        $files = UserFile::where('user_id', $user->id)->get();

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $images = [];

        foreach ($files as $file) {
            $disk = \App\Helpers\FileMapper::resolveDiskForPath($file->file_path);
            $storage = Storage::disk($disk);

            if (! $storage->exists($file->file_path)) {
                continue;
            }

            $absolutePath = $storage->path($file->file_path);
            $mimeType = mime_content_type($absolutePath);

            if (! in_array($mimeType, $allowedMimeTypes, true)) {
                continue;
            }

            $contents = $storage->get($file->file_path);
            $images[] = [
                'mime_type' => $mimeType,
                'data'      => base64_encode($contents),
            ];
        }

        return $images;
    }

    /**
     * Build the structured Gemini prompt for grade extraction.
     */
    protected function buildPrompt(): string
    {
        return <<<'PROMPT'
Analyze the uploaded report card images and extract all subject grades.

Return ONLY a JSON object with exactly four top-level keys: "math", "science", "english", and "others".
Each key maps subject names to objects containing "grade" (integer, 0–100) and "confidence" (float, 0.0–1.0).

Rules:
- Use lowercase, trimmed subject name keys.
- "grade" must be an integer between 0 and 100.
- "confidence" must be a float between 0.0 and 1.0 representing your certainty in the extracted value.
- Place each subject under the most appropriate group: math, science, english, or others.
- Do NOT include markdown code fences, backticks, or any prose — return ONLY the raw JSON object.

Example format:
{
  "math": { "algebra": { "grade": 90, "confidence": 0.95 } },
  "science": { "biology": { "grade": 88, "confidence": 0.92 } },
  "english": { "english": { "grade": 92, "confidence": 0.97 } },
  "others": { "araling panlipunan": { "grade": 90, "confidence": 0.55 } }
}
PROMPT;
    }

    /**
     * Strip non-JSON surrounding content (e.g. markdown fences, prose) from the raw response.
     */
    protected function sanitize(string $raw): string
    {
        // Strip markdown code fences (```json ... ``` or ``` ... ```)
        $stripped = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $stripped = preg_replace('/\s*```\s*$/i', '', $stripped);

        // Extract the first JSON object: from the first '{' to the last '}'
        $start = strpos($stripped, '{');
        $end   = strrpos($stripped, '}');

        if ($start === false || $end === false || $end < $start) {
            return trim($raw);
        }

        return trim(substr($stripped, $start, $end - $start + 1));
    }

    /**
     * JSON-decode the sanitized string and perform structural validation.
     *
     * @throws \RuntimeException on invalid JSON or unexpected structure
     */
    protected function parse(string $json): array
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new \RuntimeException('OpenRouter response is not valid JSON.');
        }

        $requiredKeys = ['math', 'science', 'english', 'others'];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $decoded)) {
                throw new \RuntimeException('OpenRouter response missing required keys: math, science, english, others.');
            }
        }

        foreach ($requiredKeys as $key) {
            foreach ($decoded[$key] as $entry) {
                if (
                    ! is_array($entry) ||
                    ! array_key_exists('grade', $entry) ||
                    ! array_key_exists('confidence', $entry) ||
                    ! is_numeric($entry['grade']) ||
                    ! is_numeric($entry['confidence'])
                ) {
                    throw new \RuntimeException('OpenRouter response has invalid subject entry structure.');
                }
            }
        }

        return $decoded;
    }

    /**
     * Validate that all grade values are in [0, 100] and confidence values are in [0.0, 1.0].
     *
     * @throws \RuntimeException on out-of-range values
     */
    protected function validate(array $data): array
    {
        foreach (['math', 'science', 'english', 'others'] as $group) {
            foreach ($data[$group] as $subject => $entry) {
                if ($entry['grade'] < 0 || $entry['grade'] > 100) {
                    throw new \RuntimeException(
                        "Grade value out of range [0,100] for subject '{$subject}': {$entry['grade']}"
                    );
                }
                if ($entry['confidence'] < 0.0 || $entry['confidence'] > 1.0) {
                    throw new \RuntimeException(
                        "Confidence value out of range [0.0,1.0] for subject '{$subject}': {$entry['confidence']}"
                    );
                }
            }
        }

        return $data;
    }

    /**
     * Lowercase and trim all subject name keys in the extraction result.
     */
    protected function normalizeKeys(array $data): array
    {
        $normalized = [];
        foreach (['math', 'science', 'english', 'others'] as $group) {
            $normalized[$group] = [];
            foreach ($data[$group] as $subject => $entry) {
                $normalizedKey = strtolower(trim($subject));
                $normalized[$group][$normalizedKey] = $entry;
            }
        }
        return $normalized;
    }
}
