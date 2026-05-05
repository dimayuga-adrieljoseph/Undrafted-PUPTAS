<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFile;
use App\Services\GeminiClient;
use App\Services\OpenRouterClient;
use Illuminate\Support\Facades\Storage;

class GradeExtractionService
{
    public function __construct(
        private GeminiClient $geminiClient,
        private OpenRouterClient $openRouterClient,
    ) {}

    /**
     * Main entry point: extract grades for the given user.
     *
     * Prefers Docling JSON (already OCR'd) fed into OpenRouter over raw-image Gemini.
     * Falls back to Gemini + raw images if no Docling data is available.
     *
     * @param  User  $user
     * @return array  Normalized ExtractionResult
     *
     * @throws \InvalidArgumentException if no valid source data is found
     */
    public function extract(User $user): array
    {
        // --- Primary path: Docling JSON → OpenRouter ---
        $doclingJson = $this->loadDoclingJson($user);

        if (!empty($doclingJson)) {
            \Log::info('GradeExtractionService: using Docling JSON path', ['user_id' => $user->id]);

            $prompt = $this->buildDoclingPrompt($doclingJson);
            $raw    = $this->openRouterClient->sendText($prompt);

            \Log::info('OpenRouter raw response (docling)', ['raw' => $raw]);

            $sanitized = $this->sanitize($raw);
            $parsed    = $this->parse($sanitized);
            $validated = $this->validate($parsed);

            return $this->normalizeKeys($validated);
        }

        // --- Fallback path: raw images → Gemini ---
        \Log::info('GradeExtractionService: no Docling JSON, falling back to Gemini', ['user_id' => $user->id]);

        $images = $this->loadImages($user);

        if (empty($images)) {
            throw new \InvalidArgumentException('No valid image files found for extraction.');
        }

        $prompt = $this->buildPrompt();
        $raw    = $this->geminiClient->send($images, $prompt);

        \Log::info('Gemini raw response', ['raw' => $raw]);

        $sanitized = $this->sanitize($raw);
        $parsed    = $this->parse($sanitized);
        $validated = $this->validate($parsed);

        return $this->normalizeKeys($validated);
    }

    /**
     * Collect all non-null docling_json blobs from the user's uploaded files.
     * Returns a flat array of the JSON objects (one per file).
     */
    protected function loadDoclingJson(User $user): array
    {
        return UserFile::where('user_id', $user->id)
            ->whereNotNull('docling_json')
            ->get()
            ->map(fn ($f) => $f->docling_json)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Strip heavy fields from a Docling JSON item before embedding in the prompt.
     * Removes base64 page images and other non-text fields to keep token count low.
     */
    protected function stripDoclingJson(array $item): array
    {
        // Keep only the texts array — that's all the LLM needs
        return [
            'name'   => $item['name']    ?? null,
            'texts'  => array_map(fn ($t) => [
                'label' => $t['label'] ?? null,
                'text'  => $t['text']  ?? $t['orig'] ?? null,
            ], $item['texts'] ?? []),
            'tables' => $item['tables'] ?? [],
        ];
    }

    /**
     * Build a text-only prompt that embeds the Docling JSON output.
     * No images needed — the OCR text is already extracted.
     */
    protected function buildDoclingPrompt(array $doclingJsonItems): string
    {
        $stripped = array_map(fn ($item) => $this->stripDoclingJson($item), $doclingJsonItems);
        $encoded  = json_encode($stripped, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an AI system that extracts and organizes academic grades from structured document data.

Below is the OCR-extracted content from one or more school documents, provided as a JSON array produced by Docling. Each item has a "texts" array where each entry has a "label" (e.g. "section_header", "text") and "text" (the extracted string). Tables, if any, are in "tables".

DOCUMENT DATA:
{$encoded}

Your task:
1. Read and interpret all visible text from the document data above.
2. Extract subject-grade pairs.
3. Categorize and map the extracted grades into Math, Science, and English groups.
4. Include any additional subjects that do not belong to these categories under "others".

Predefined Subject Mapping:

Math:
* General Mathematics
* Business Mathematics
* Statistics and Probability
* Pre-Calculus
* Basic Calculus

Science:
* Earth and Life Science
* Physical Science
* Earth Science
* General Chemistry 1

English:
* Oral Communication
* 21st Century Literature
* English for Academic Purposes
* Reading and Writing

Instructions:
* Identify all subject-grade pairs visible in the document data.
* Consider both Grade 11 and Grade 12 subjects and grades.
* Normalize and clean subject names.
* Use reasoning to match subjects to the closest predefined subject and category:
  * "Math", "Gen Math" → General Mathematics (Math)
  * "Stats" → Statistics and Probability (Math)
  * "Pre-Cal", "Precalculus" → Pre-Calculus (Math)
  * "Basic Cal" → Basic Calculus (Math)
  * "Gen Chem", "Gen Chem 1" → General Chemistry 1 (Science)
  * "Earth Sci" → Earth Science (Science)
  * "EAPP" → English for Academic Purposes (English)
  * "21st Lit", "21st Century Lit" → 21st Century Literature (English)
* If a subject matches a predefined subject, use the exact predefined name.
* If a subject does NOT match any predefined subject, place it under "others".
* If multiple grades appear for the same subject, select the final or most relevant grade.
* Ignore unrelated numbers (student IDs, dates, etc.).
* Merge information across multiple documents.

Output format:
* Return ONLY a valid JSON object.
* Do NOT include explanations, comments, markdown fences, or extra text.
* Ensure the output is directly parseable by JSON.parse().

The JSON structure must be:
{
  "subjects": {
    "math": { "Subject Name": "Grade" },
    "science": { "Subject Name": "Grade" },
    "english": { "Subject Name": "Grade" },
    "others": { "Subject Name": "Grade" }
  }
}

Rules:
* Only include Grade 11 and Grade 12 subjects.
* Do not duplicate subjects.
* Omit subjects with no detected grade.
* If a category has no subjects, return it as an empty object {}.
* Do not invent or guess missing data.
* Values must be numeric strings (e.g. "95").
* Ensure valid JSON (no trailing commas, no comments).
PROMPT;
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
            if (in_array($file->type, ['file10Front', 'file10'], true)) {
                continue;
            }

            $disk = \App\Helpers\FileMapper::resolveDiskForPath($file->file_path);
            $storage = Storage::disk($disk);

            if (! $storage->exists($file->file_path)) {
                continue;
            }

            try {
                $mimeType = \App\Helpers\FileMapper::detectMimeType($file);

                // If extension-based detection is ambiguous, try finfo on the raw bytes
                if ($mimeType === 'application/octet-stream') {
                    $contents = $storage->get($file->file_path);
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $detected = $finfo->buffer($contents);
                    if (is_string($detected) && $detected !== '') {
                        $mimeType = $detected;
                    }
                } else {
                    $contents = $storage->get($file->file_path);
                }

                if (! in_array($mimeType, $allowedMimeTypes, true)) {
                    continue;
                }

                $images[] = [
                    'mime_type' => $mimeType,
                    'data'      => base64_encode($contents),
                ];
            } catch (\Throwable $e) {
                \Log::warning('GradeExtractionService: skipping file due to error', [
                    'file_id'   => $file->id,
                    'file_path' => $file->file_path,
                    'error'     => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $images;
    }

    /**
     * Build the structured Gemini prompt for grade extraction.
     */
    protected function buildPrompt(): string
    {
        return <<<'PROMPT'
You are an AI system that extracts and organizes academic grades directly from images.

The input may contain one or multiple images. You must carefully examine ALL images and combine the information before producing the final answer.

Your task:

1. Read and interpret all visible text from the images.
2. Extract subject-grade pairs.
3. Categorize and map the extracted grades into Math, Science, and English groups.
4. Include any additional subjects that do not belong to these categories.

Predefined Subject Mapping:

Math:

* General Mathematics
* Business Mathematics
* Statistics and Probability
* Pre-Calculus
* Basic Calculus

Science:

* Earth and Life Science
* Physical Science
* Earth Science
* General Chemistry 1

English:

* Oral Communication
* 21st Century Literature
* English for Academic Purposes
* Reading and Writing

Instructions:

* Identify all subject-grade pairs visible in the images.
* Consider both Grade 11 and Grade 12 subjects and grades. Include grade level context where visible.
* Normalize and clean subject names.
* Use reasoning to match subjects to the closest predefined subject and category:

  * “Math”, “Gen Math” → General Mathematics (Math)
  * “Stats” → Statistics and Probability (Math)
  * “Pre-Cal”, “Precalculus” → Pre-Calculus (Math)
  * “Basic Cal” → Basic Calculus (Math)
  * “Gen Chem”, “Gen Chem 1” → General Chemistry 1 (Science)
  * “Earth Sci” → Earth Science (Science)
  * “EAPP” → English for Academic Purposes (English)
  * “21st Lit”, “21st Century Lit” → 21st Century Literature (English)
* If a subject matches a predefined subject, use the exact predefined name.
* If a subject does NOT match any predefined subject:

  * Place it under an "others" category using its cleaned name.
* If multiple grades appear for the same subject, select the final or most relevant grade.
* Ignore unrelated numbers (student IDs, dates, etc.).
* Merge information across multiple images.

Output format:

* Return ONLY a valid JSON object.
* Do NOT include explanations, comments, or extra text.
* Ensure the output is directly parseable by JSON.parse().

The JSON structure must be:

{
"subjects": {
"math": {
"General Mathematics": "Grade",
"Statistics and Probability": "Grade"
},
"science": {
"Earth and Life Science": "Grade",
"Physical Science": "Grade"
},
"english": {
"Oral Communication": "Grade",
"21st Century Literature": "Grade",
"English for Academic Purposes": "Grade",
"Reading and Writing": "Grade"
},
"others": {
"Additional Subject Name": "Grade"
}
}
}

Rules:

* Use predefined subject names when a match is clear.
* Place subjects in the correct category (math, science, english).
* Subjects that do not fit must go under "others".
* Only include Grade 11 and Grade 12 subjects and grades.
* Do not duplicate subjects.
* Omit subjects with no detected grade.
* If a category has no subjects, return it as an empty object {}.
* Do not invent or guess missing data.
* Values must be strings or numbers.
* Ensure valid JSON formatting (no trailing commas, no comments).

Example Output:
{
"subjects": {
"math": {
"General Mathematics": "95"
},
"science": {},
"english": {
"Oral Communication": "90"
},
"others": {
"Filipino": "92",
"Physical Education": "93"
}
}
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
            throw new \RuntimeException('Gemini response is not valid JSON.');
        }

        if (! array_key_exists('subjects', $decoded)) {
             throw new \RuntimeException('Gemini response missing required "subjects" root key.');
        }

        $subjects = $decoded['subjects'];
        $requiredKeys = ['math', 'science', 'english', 'others'];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $subjects)) {
                throw new \RuntimeException('Gemini response missing required keys: math, science, english, others.');
            }
        }

        foreach ($requiredKeys as $key) {
            foreach ($subjects[$key] as $entry) {
                if (! is_string($entry) && ! is_numeric($entry)) {
                    throw new \RuntimeException('Gemini response has invalid subject grade structure.');
                }
            }
        }

        return $subjects;
    }

    /**
     * Validate that all grade values are in [0, 100].
     *
     * @throws \RuntimeException on out-of-range values
     */
    protected function validate(array $data): array
    {
        foreach (['math', 'science', 'english', 'others'] as $group) {
            foreach ($data[$group] as $subject => $grade) {
                if (! is_numeric($grade)) {
                    throw new \RuntimeException(
                        "Non-numeric grade value for subject '{$subject}': {$grade}"
                    );
                }
                $numericGrade = (float) $grade;
                if ($numericGrade < 0 || $numericGrade > 100) {
                    throw new \RuntimeException(
                        "Grade value out of range [0,100] for subject '{$subject}': {$grade}"
                    );
                }
            }
        }

        return $data;
    }

    /**
     * Lowercase and trim all subject name keys in the extraction result.
     * Maps back to an array structure with fake confidence.
     */
    protected function normalizeKeys(array $data): array
    {
        $normalized = [];
        // Envelope it back inside 'subjects' so the frontend root has the same property name
        $normalized['subjects'] = [];
        foreach (['math', 'science', 'english', 'others'] as $group) {
            $normalized['subjects'][$group] = [];
            foreach ($data[$group] as $subject => $grade) {
                $normalizedKey = strtolower(trim($subject));
                $normalized['subjects'][$group][$normalizedKey] = (float) $grade;
            }
        }
        return $normalized;
    }
}
