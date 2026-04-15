You are a senior Laravel engineer tasked with refactoring an existing production system.

## 🎯 Objective

Refactor the current file upload system from **local filesystem storage (e.g., `storage/app/...`)** to an **S3-compatible storage (Railway Storage Buckets)** WITHOUT breaking existing functionality.

The system currently:

* Stores uploaded files in local folders inside the codebase
* Saves file paths in the database
* Uses Laravel + Vue stack

The environment already contains valid S3 credentials:

AWS_ACCESS_KEY_ID
AWS_BUCKET=
AWS_DEFAULT_REGION
AWS_ENDPOINT
AWS_SECRET_ACCESS_KEY

Railway buckets are **S3-compatible and private**, so files must be accessed via backend or presigned URLs.

---

## ⚠️ Constraints

* DO NOT change business logic
* DO NOT break existing upload flows
* DO NOT change database schema unless absolutely necessary
* Maintain backward compatibility for existing stored file paths
* Follow Laravel best practices (Storage facade, filesystem config)

---

## 🧠 Key Implementation Requirements

### 1. Configure S3 Disk

* Update `config/filesystems.php` to properly support S3 using environment variables
* Ensure compatibility with custom endpoint (Railway uses `https://storage.railway.app`) ([Railway Docs][1])
* Ensure correct URL style (virtual-hosted or path-style if needed)

---

### 2. Replace Local Storage Calls

Find all usages of:

* `store()`
* `storeAs()`
* `move()`
* `file_put_contents()`
* direct `storage_path()` usage

Refactor them to use:

```php
Storage::disk('s3')->put(...)
Storage::disk('s3')->putFile(...)
Storage::disk('s3')->putFileAs(...)
```

---

### 3. Standardize File Paths

* Ensure all uploads go to structured directories:

the main folder is puptas\storage\app\public\uploads\files, this is where all applicant uploads are stored

* Maintain consistent naming (timestamp + original name or UUID)

---

### 4. Update File Retrieval Logic

Since Railway buckets are **private**:

* Replace any direct URL access with:

  * `Storage::disk('s3')->temporaryUrl(...)` (preferred)
    OR
  * Backend controller that streams the file

Do NOT assume public URLs are available ([Railway Docs][2])

---

### 5. Maintain Backward Compatibility

* Detect if file path is:

  * local (old system)
  * S3 (new system)
* Implement fallback logic:

```php
if (Storage::disk('s3')->exists($path)) {
    // use S3
} else {
    // fallback to local
}
```

---

### 6. Update Upload Flow (IMPORTANT)

Refactor upload handling to:

Option A (initial/simple):

* Upload via backend using `Storage::disk('s3')`

Option B (recommended/advanced):

* Implement **presigned upload URLs**
* Backend generates URL
* Vue uploads directly to S3 (avoids server load) ([Railway Docs][2])

---

### 7. Database Handling

* Continue storing only the **file path**, NOT full URLs
* Example:

  * ✅ `documents/file123.pdf`
  * ❌ full S3 URL

---

### 8. Error Handling

* Wrap all upload operations in try-catch
* Log detailed errors
* Return generic user-friendly errors

---

### 9. Testing Checklist

* Upload file → verify in bucket
* Retrieve file → works via temporary URL
* Old files still accessible
* Large file uploads work
* No broken frontend behavior

---

## 🧪 Expected Output

* Updated Laravel configuration
* Refactored upload + retrieval logic
* Optional presigned upload implementation
* Clean, maintainable code
* No regression in functionality

---

## 🧩 Bonus (if possible)

* Add abstraction layer (e.g., FileService class)
* Allow switching between local and S3 via ENV (`FILESYSTEM_DISK`)
* Prepare system for scaling (large uploads, async processing)

---

## 🚫 Do NOT

* Hardcode credentials
* Assume public bucket access
* Break existing file references
* Remove local storage support entirely

---

## 📌 Notes

Railway Storage Buckets are:

* Private by default
* S3-compatible
* Designed for uploads, assets, and user-generated files ([Railway Docs][1])

---

Proceed step-by-step and explain each change clearly.

[1]: https://docs.railway.com/guides/storage-buckets?utm_source=chatgpt.com "Storage Buckets | Railway Docs"
[2]: https://docs.railway.com/guides/storage-buckets-guide?utm_source=chatgpt.com "Use Storage Buckets for Uploads, Exports, and Assets | Railway Guides"
