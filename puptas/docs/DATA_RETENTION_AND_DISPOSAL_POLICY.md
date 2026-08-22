# Data Retention & Disposal Policy
**PUP Taguig Admission System (PUPTAS)**  
Team Undrafted — BSIT Capstone 2, A.Y. 2026–2027

---

## 1. Purpose

This policy defines how PUPTAS handles the lifecycle of personal data collected during the admission process, from active use through deactivation and eventual permanent disposal. It exists to satisfy two obligations:

1. **Legal** — compliance with the **Data Privacy Act of 2012 (RA 10173)** and the National Privacy Commission's (NPC) guidance on proportionality and storage limitation.
2. **Operational** — giving the system administrator a defined, auditable, and reversible process for removing data, rather than ad-hoc manual deletion.

---

## 2. Legal Framework

RA 10173 requires that personal data be **retained only for as long as necessary** to fulfill the purpose for which it was collected, or as required by law. The Act does not itself prescribe fixed retention periods for admission-system data; the specific durations below are PUPTAS's own operational interpretation, set to align with the academic cycle and reasonable audit needs, and are documented here so they can be reviewed and revised by the data controller (the University / Admissions Office) as needed.

### Core Privacy Principles Applied:
- **Proportionality**: Data is kept no longer than needed for admission processing, ranking, qualification, and official appeal windows.
- **Storage Limitation**: Expired data is either permanently deleted from the database or physically unlinked/shredded from disk storage, not simply hidden from the interface.
- **Accountability & Auditability**: Every disposal action is logged immutably in the system audit trail for compliance verification.

---

## 3. Two-Phase Data Lifecycle

PUPTAS separates data disposal into two distinct phases so that no data is ever destroyed irreversibly without a designated hold period.

```
Active Account / Application
        │
        │  Account deactivation / Applicant withdrawal
        ▼
Phase 1: Soft-Delete & Deactivation  ──── (Restorable by admin within hold period)
  (Grace / Statutory Hold Period)
        │
        │  Hold period expires (e.g. > 365 Days)
        ▼
Phase 2: Automated Hard Disposal
        ├── Permanently purge database records
        ├── Unlink physical files from storage disks
        └── Write immutable compliance audit log entry
```

### Phase 1 — Soft-Delete & Deactivation (Grace / Statutory Hold)
When an account is withdrawn, an applicant is marked inactive, or an admin removes a record, the system does **not** hard-delete the database row. Instead:
- The record's `deleted_at` timestamp is set (Laravel `SoftDeletes`), and/or `is_active` is set to `false`.
- The account immediately loses the ability to authenticate or perform actions.
- All foreign-key relationships and audit trail references remain intact.
- The record is **restorable by an administrator** at any point during the hold period.

### Phase 2 — Automated Hard Disposal
Once a record's hold period has expired, an automated / scheduled disposal process permanently cleanses it:
- Database rows are hard-deleted (`forceDelete()`).
- Any linked physical files (credential PDFs, uploads) are unlinked and destroyed from disk storage (`Storage::disk('public')` / `Storage::disk('local')`).
- A record of the disposal action — what was purged, row counts, reclaimed storage bytes, and execution timestamp — is written to an **immutable audit log entry**, so the disposal itself is auditable even though the underlying sensitive personal data is gone.

---

## 4. Retention Schedule & Lifespan Matrix

| Data Category | Classification | Retention / Hold Lifespan | Retention Trigger | Disposal Procedure |
|---|---|---|---|---|
| **Soft-deleted / Deactivated Applicant PII** (`users`, `applicant_profiles`, `grades`) | Sensitive Personal Information (SPI) | **365 days** (1 Academic Year) | Deactivation / withdrawal date | Cascading hard-purge of profile & database rows + file shredding |
| **Applicant Uploaded Credentials** (`user_files` & `/storage/app/public/*`) | Confidential Verification Documents | **365 days** (or upon official SIS handoff) | Deactivation / cycle completion | Physical file unlinking from storage disk + hard row deletion |
| **System & Security Audit Logs** (`audit_logs`) | Operational & Compliance Logs | **180 days** (6 Months) | Log creation timestamp | Automated query purge with daily retention sweep |
| **Email Delivery Logs** (`email_logs`, `bulk_email_operations`) | Operational Dispatch Logs | **90 days** | Message dispatch timestamp | Hard deletion of delivery metadata and error payloads |
| **Generated Credential Slips** (`sar_generations`, `gvs_generations`, `f137_generations`) | Confidential Slips / PDF Assets | **180 days** | Document generation timestamp | Physical PDF unlinking from storage + metadata purge |
| **Orphaned / Unlinked File Uploads** | Transient Uploads | **30 days** | Upload timestamp (unlinked) | Physical file deletion from storage disk |

*Note: All retention lifespans are centralized in `config/data_retention.php` and can be reconfigured via `.env` variables without code refactoring.*

---

## 5. Roles & Responsibilities

| Role | Responsibility |
|---|---|
| **System / Database Administrator** | Owns this policy, runs and monitors the disposal process, reviews dry-run output before forced purges. |
| **Admissions Office (Data Controller)** | Approves retention periods, receives notifications of major purge cycles, requests student records restoration. |
| **Application (Automated Engine)** | Executes scheduled disposal sweeps, writes immutable audit records, enforces authentication blocks on deactivated users. |

---

## 6. Technical Implementation & Code Evidence

This policy is backed by active, working code implementation across the repository:

| Requirement | Code Implementation & Artifact Evidence |
|---|---|
| **Soft-Delete Capability** | `deleted_at` timestamp columns + `SoftDeletes` trait on [User.php](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/app/Models/User.php), [ApplicantProfile.php](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/app/Models/ApplicantProfile.php), and [UserFile.php](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/app/Models/UserFile.php) |
| **Account Deactivation (Independent of Deletion)** | `is_active` boolean column on `users`; blocked in [AuthenticatedSessionController.php](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/app/Http/Controllers/AuthenticatedSessionController.php) |
| **Reversibility within Hold Period** | `UserService::restoreUser()` and `UserService::reactivateUser()` in [UserService.php](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/app/Services/UserService.php) |
| **Configurable Retention Periods** | [data_retention.php](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/config/data_retention.php), overridable via `.env` |
| **Disposal Orchestration Service** | [DataRetentionService.php](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/app/Services/DataRetentionService.php) — handles chunked database deletion and physical file disk unlinking (`Storage::disk()->delete()`) |
| **Safe Inspection Before Destruction** | `php artisan data-retention:purge --dry-run` — calculates and renders purge statistics without mutating state |
| **Manual & Scheduled Execution** | [PurgeExpiredData.php](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/app/Console/Commands/PurgeExpiredData.php) via `php artisan data-retention:purge`; automated in [bootstrap/app.php](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/bootstrap/app.php) (`dailyAt('02:00')`) |
| **Audit Trail of Disposal Actions** | Each purge run writes a structured [AuditLog.php](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/app/Models/AuditLog.php) entry with record counts and freed storage bytes |
| **Automated Verification Suite** | [DataRetentionTest.php](file:///c:/Users/Myla/OneDrive/Desktop/Undrafted-PUPTAS/puptas/tests/Feature/DataRetentionTest.php) verifying deactivation, dry-run safety, and hard-purge execution |

---

## 7. Verification Steps (For Defense Demonstration)

1. **Dry-Run Inspection (Safe Preview)**:
   ```bash
   php artisan data-retention:purge --dry-run
   ```
   *Demonstrates*: Renders a tabular summary of records eligible for disposal across all categories without deleting any data.

2. **User Account Deactivation**:
   * Action: Deactivate an account via User Management or `UserService::deactivateUser($id)`.
   * Result: Account loses login access immediately while preserving referential integrity and audit records.

3. **Account Restoration within Hold Period**:
   * Action: Restore account via `UserService::restoreUser($id)`.
   * Result: Account re-enters active state and access is restored.

4. **Forced Retention Disposal & Audit Verification**:
   ```bash
   php artisan data-retention:purge --force
   ```
   *Demonstrates*: Purges expired records and generated slips, unlinks files, and automatically creates an immutable log entry in the `audit_logs` table.

---

## 8. Review & Governance

This policy should be reviewed annually at the conclusion of each academic admission cycle or whenever Republic Act No. 10173 regulations / National Privacy Commission advisories are amended.
