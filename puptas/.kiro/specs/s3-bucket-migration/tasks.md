# Implementation Plan: S3 Bucket Migration

## Overview

Migrate PUPTAS file storage from the local `public` disk to an S3-compatible backend (Railway Storage Buckets) by introducing a `FileService` abstraction layer, decoupling `ImageCompressionService` from storage, and wiring all upload/deletion calls through the new service.

## Tasks

- [x] 1. Decouple ImageCompressionService from storage
  - [x] 1.1 Add `processImage()` method to `ImageCompressionService` that runs the validate → resize → WebP pipeline and returns `['webp_data' => string, 'original_name' => string, 'filename' => string]` without calling `Storage::disk('public')->put()`
    - Keep the existing `compress()` signature intact for now; it will be updated in task 3
    - _Requirements: 3.1_

  - [x] 1.2 Write unit test asserting `processImage()` returns the expected array shape and does not call `Storage::disk('public')->put()`
    - _Requirements: 3.1_

- [-] 2. Create FileService
  - [x] 2.1 Create `app/Services/FileService.php` implementing `store(UploadedFile $file, string $directory): array`, `delete(string $path): void`, and `url(UserFile $file): string`
    - `store()` calls `ImageCompressionService::processImage()`, then persists WebP bytes to `Storage::disk($this->activeDisk())->put()`
    - `activeDisk()` reads `config('filesystems.default', 'public')`
    - `store()` validates the returned path has no leading slash or protocol prefix before returning; strips if present (Requirement 8.4)
    - `delete()` resolves disk via `FileMapper::resolveDiskForPath()` and calls `Storage::disk($disk)->delete()`; on S3 delete failure logs `\Log::warning()` and does not throw
    - `url()` delegates to `FileMapper::buildPreviewUrl()`
    - On S3 `put()` failure throw `\RuntimeException` with S3 error code and attempted path
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 7.2, 7.3, 8.4_

  - [ ] 2.2 Write property test for Property 1: FileService routes to configured disk
    - **Property 1: FileService routes to the configured disk**
    - **Validates: Requirements 1.5, 2.4, 2.5**

  - [ ] 2.3 Write property test for Property 2: Store then delete removes the file
    - **Property 2: Store then delete removes the file**
    - **Validates: Requirements 2.2**

  - [ ] 2.4 Write property test for Property 3: Stored paths are always relative
    - **Property 3: Stored paths are always relative**
    - **Validates: Requirements 3.5, 8.1, 8.2, 8.4**

  - [ ] 2.5 Write unit tests for FileService error handling
    - Assert `store()` throws `\RuntimeException` when S3 `put()` fails (mock S3 disk)
    - Assert `delete()` logs warning and does not throw when S3 `delete()` fails (mock S3 disk)
    - _Requirements: 2.6, 7.2, 7.3_

- [ ] 3. Checkpoint — Ensure all tests pass, ask the user if questions arise.

- [ ] 4. Update FileMapper disk probe order
  - [x] 4.1 Update `FileMapper::resolveDiskForPath()` to probe in the order: Active_Disk → `public` → `local` → `s3`, stopping at the first disk where the file exists
    - Active_Disk is resolved from `config('filesystems.default', 'public')`
    - _Requirements: 4.1, 4.5_

  - [ ] 4.2 Write property test for Property 4: Disk resolution returns correct disk for legacy paths
    - **Property 4: Disk resolution — legacy paths**
    - **Validates: Requirements 4.2, 8.3**

  - [ ] 4.3 Write property test for Property 5: Disk resolution returns correct disk for migration paths
    - **Property 5: Disk resolution — migration paths**
    - **Validates: Requirements 4.3, 8.3**

  - [ ] 4.4 Write property test for Property 6: Disk resolution respects probe order
    - **Property 6: Disk resolution probe order**
    - **Validates: Requirements 4.5**

- [ ] 5. Wire FileService into UserFileController and ConfirmationService
  - [x] 5.1 Inject `FileService` into `UserFileController` and replace the direct `ImageCompressionService::compress()` call in `uploadFiles()` with `FileService::store()`
    - Wrap the call in a try/catch; on `\RuntimeException` log with `user_id`, `file_type`, `disk`, `exception_message` and return HTTP 503 `'Storage service temporarily unavailable.'` for connectivity errors or HTTP 500 `'File operation failed. Please try again.'` for other failures
    - _Requirements: 3.3, 7.1, 7.5_

  - [x] 5.2 Inject `FileService` into `ConfirmationService` and replace `$this->compressionService->compress()` in `reuploadFile()` with `FileService::store()`, and replace `Storage::disk('public')->delete()` in `deleteExistingFile()` with `FileService::delete()`
    - _Requirements: 3.2, 3.4_

  - [ ] 5.3 Write unit tests for controller and service delegation
    - Assert `UserFileController::uploadFiles()` delegates to `FileService::store()` (mock `FileService`)
    - Assert `ConfirmationService::reuploadFile()` delegates to `FileService::store()` and `FileService::delete()` (mock `FileService`)
    - Assert controller returns HTTP 500 with generic message when `FileService` throws a generic exception
    - Assert controller returns HTTP 503 when S3 is unreachable
    - _Requirements: 3.2, 3.3, 7.1, 7.5_

- [ ] 6. Verify backward-compatible file retrieval in UserFileController::preview()
  - [x] 6.1 Confirm `UserFileController::preview()` already uses `FileMapper::resolveDiskForPath()` and streams S3 contents via `Storage::disk('s3')->get($path)` for the `s3` disk; add the S3 streaming branch if missing
    - Ensure HTTP 404 is returned when the file does not exist on any probed disk
    - Ensure no `Location` header or direct S3 URL is included in the response
    - _Requirements: 4.1, 4.4, 5.1, 5.2_

  - [ ] 6.2 Write property test for Property 7: File preview streams through backend with no direct S3 URLs
    - **Property 7: Preview streams through backend**
    - **Validates: Requirements 5.1, 5.2, 7.4**

  - [ ] 6.3 Write property test for Property 8: URL generation always returns a signed preview route
    - **Property 8: URL generation returns signed route**
    - **Validates: Requirements 2.3, 5.3**

  - [ ] 6.4 Write unit tests for preview edge cases
    - Assert HTTP 404 for non-existent paths
    - Assert HTTP 403 for expired signed URLs
    - Assert `FileService::url()` never calls `Storage::disk('s3')->temporaryUrl()`
    - _Requirements: 4.4, 5.4, 5.5_

- [ ] 7. Checkpoint — Ensure all tests pass, ask the user if questions arise.

- [ ] 8. Implement Presigned Upload endpoints (optional advanced flow)
  - [ ] 8.1 Create `app/Http/Controllers/PresignedUploadController.php` with `generatePresignedUrl()` and `confirmUpload()` actions
    - `generatePresignedUrl()` validates `{ field, content_type }`, rejects non-allowed MIME types with HTTP 422, calls `Storage::disk('s3')->temporaryUploadUrl()` with 300-second expiry, returns `{ upload_url, path, expires_in }`
    - Path follows pattern `uploads/files/{slug}_{timestamp}_{random}.{ext}`
    - `confirmUpload()` validates `{ field, path, original_name }` and calls `UserFile::updateOrCreate()` with `status = 'pending'`
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

  - [ ] 8.2 Register `POST /upload-presigned` and `POST /upload-confirm` routes in `routes/web.php` (or `api.php`), guarded by auth middleware
    - _Requirements: 6.1_

  - [ ] 8.3 Write property test for Property 9: Presigned upload confirm creates a correct UserFile record
    - **Property 9: Presigned confirm creates correct record**
    - **Validates: Requirements 6.3, 6.5**

  - [ ] 8.4 Write unit tests for presigned upload endpoints
    - Assert HTTP 422 for disallowed `content_type` values
    - Assert generated path matches naming pattern
    - Assert `confirmUpload()` creates `UserFile` with correct `file_path`, `original_name`, and `status = 'pending'`
    - _Requirements: 6.2, 6.3, 6.4, 6.5_

- [ ] 9. Final checkpoint — Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP
- Property-based tests use [Eris](https://github.com/giorgiosironi/eris) and should run a minimum of 100 iterations each
- All error responses to the client use generic messages; internal details are logged server-side only
- No database schema changes are required — `UserFile.file_path` continues to store only relative paths
