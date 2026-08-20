# Performance Optimizations

This document records intended query-performance work and the decisions taken.

## 1. `dynamic_subjects` JSON column — Deferred

**Status:** Deferred (no code change).

The `grades.dynamic_subjects` JSON column stores applicant-added subjects per
category (`math`, `english`, `science`) as an array of objects:

```json
[{ "category": "math", "name": "...", "grade": 92.50 }]
```

### Why no change was made

A full scan of the codebase found **zero** SQL-level JSON queries against this
column. There is no `whereJsonContains`, `JSON_CONTAINS`, `json_extract`,
`json_unquote`, or arrow (`->`) predicate anywhere in the application.

The column is:

- Cast to an `array` on `App\Models\Grade`.
- Read only in PHP via `Grade::getDynamicSubjectsForCategory()`, which loads the
  whole array and filters it with `collect()->where()`.
- Written wholesale via `Grade::updateOrCreate(...)` / `UserController` grade
  updates.
- Serialized to the frontend as `grade.dynamic_subjects`.

### Implications

- **Option B (virtual generated columns + index)** would add columns and indexes
  that no SQL predicate would ever use, because no query filters on JSON keys.
  It would not remove any real table scan from the current code.
- **Option A (normalization into a `grade_subjects` table)** is the correct
  long-term structure if/when the application begins querying individual dynamic
  subject grades in SQL. It is a cross-cutting change (migration, model,
  repositories, services, controllers, and the Vue components that read
  `dynamic_subjects` as an array attribute).

**Recommended future trigger:** only normalize once there is a concrete need to
filter/aggregate dynamic subjects at the SQL level (e.g. admin reports or search).
Until then, the current array-cast approach is sufficient.

## 2. Query caching

Single-record Eloquent repository lookups are cached behind the Cache facade and
invalidated on write via model `saved`/`deleted` events:

- `Grade` — `grade:user:{id}`
- `Application` — `application:user:{id}`, `application:user:{id}:returned_rejected`
- `UserFile` — `user_file:user:{id}`, `user_file:user:{id}:{type}`
- `TestPasser` — `test_passer:user:{id}`

Missing records are cached as `false` to avoid repeated lookups for absent data.

## 3. N+1 prevention

- `AppServiceProvider` calls `Model::preventLazyLoading(! app()->isProduction())`
  so accidental lazy loads throw during development/testing (production is
  unaffected).
- Services/traits/controllers that previously triggered lazy loads were updated
  to eager load relationships before access.