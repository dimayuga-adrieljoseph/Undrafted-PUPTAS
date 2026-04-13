# Design Document: Application Grades Display

## Overview

The application details modal in `Pages/Applications/Index.vue` currently renders only three of the five academic grade fields from the `grades` table: `mathematics`, `science`, and `english`. The `g12_first_sem` and `g12_second_sem` fields exist in the `Grade` model and are already fetched via the `selectUser` function, but are never rendered.

This change is purely a frontend template update — no backend changes are required. The grades data is already present in `selectedUser.grades` when the modal opens.

## Architecture

The feature touches a single component: the inline "User Details Modal" section inside `Pages/Applications/Index.vue`. No new components, routes, controllers, or API endpoints are needed.

```
selectUser() → axios GET → response.data.user.grades → selectedUser.grades
                                                              ↓
                                              Grades_Section template (Index.vue)
                                              [currently: 3 cards → after: 5 cards]
```

## Components and Interfaces

### Affected Component

`puptas/resources/js/Pages/Applications/Index.vue` — the Grades Section template block.

Current structure (3-column grid, 3 cards):
```html
<div class="grid grid-cols-3 gap-3">
  <!-- mathematics, science, english -->
</div>
```

Target structure (responsive grid, 5 cards):
```html
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
  <!-- english, mathematics, science, g12_first_sem, g12_second_sem -->
</div>
```

### Grade Label Mapping

| Field key       | Display label              |
|-----------------|----------------------------|
| `english`       | English                    |
| `mathematics`   | Mathematics                |
| `science`       | Science                    |
| `g12_first_sem` | Grade 12 – 1st Semester    |
| `g12_second_sem`| Grade 12 – 2nd Semester    |

### Null / Absent Value Handling

The template already uses `?? "—"` for the existing three cards. The two new cards follow the same pattern. When `selectedUser.grades` is `null`, all five cards display `"—"`.

Numeric formatting uses `toFixed(2)` via a computed helper or inline expression so values always render to two decimal places when present.

## Data Models

No schema changes. The `Grade` model already exposes all five fields:

```php
protected $fillable = [
    'user_id', 'english', 'mathematics', 'science', 'g12_first_sem', 'g12_second_sem',
];
protected $casts = [
    'english' => 'decimal:2', 'mathematics' => 'decimal:2', 'science' => 'decimal:2',
    'g12_first_sem' => 'decimal:2', 'g12_second_sem' => 'decimal:2',
];
```

The `selectUser` function in `Index.vue` already maps `response.data.user.grades` into `selectedUser.grades`, so both new fields are available without any backend change.

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: All five grade fields are rendered

*For any* grades object passed to the Grades_Section, the rendered output SHALL contain display values for all five fields: `english`, `mathematics`, `science`, `g12_first_sem`, and `g12_second_sem`.

**Validates: Requirements 1.1**

### Property 2: Null or absent grade values render as "—"

*For any* grades object where one or more fields are `null` or `undefined` (including when the entire `grades` prop is `null`), the rendered card for each such field SHALL display the string `"—"` and no JavaScript error SHALL be thrown.

**Validates: Requirements 1.3, 3.2**

### Property 3: Present grade values render to two decimal places

*For any* numeric grade value present in the grades object, the rendered card SHALL display that value formatted to exactly two decimal places (e.g., `85` → `"85.00"`, `92.5` → `"92.50"`).

**Validates: Requirements 1.4**

### Property 4: All five grade cards share the same CSS classes

*For any* rendered Grades_Section, every grade card element SHALL have the same set of CSS classes (background, padding, typography), ensuring visual consistency across all five cards.

**Validates: Requirements 2.1**

### Property 5: Grade label precedes grade value in each card

*For any* rendered grade card, the label element SHALL appear before the value element in the DOM.

**Validates: Requirements 2.3**

## Error Handling

| Scenario | Behavior |
|---|---|
| `selectedUser.grades` is `null` | All five cards render `"—"` via `selectedUser?.grades?.field ?? "—"` |
| A specific grade field is `null` | That card renders `"—"`, others render normally |
| Grade value is a string (e.g., from API) | `parseFloat(value).toFixed(2)` handles coercion; if `NaN`, falls back to `"—"` |

No new error boundaries or try/catch blocks are needed — the existing optional chaining pattern is sufficient.

## Testing Strategy

This feature is a Vue template change with clear input/output behavior, making it well-suited for property-based testing of the rendering logic.

**Property-based testing library**: `@fast-check/vitest` (already used in the project's test suite pattern).

**Unit / example tests**:
- Render with a complete grades object → assert all five label strings appear
- Render with `grades = null` → assert all five cards show `"—"`
- Render with three fields null, two present → assert correct mix of values and dashes
- Assert responsive grid classes are present on the container

**Property tests** (minimum 100 iterations each):

- Property 1 — generate random grades objects with all five fields populated; assert all five values appear in rendered output.
  Tag: `Feature: application-grades-display, Property 1: all five grade fields are rendered`

- Property 2 — generate grades objects where any arbitrary subset of fields is null/undefined (including fully null grades); assert "—" appears for each null field and no error is thrown.
  Tag: `Feature: application-grades-display, Property 2: null or absent grade values render as "—"`

- Property 3 — generate random numeric grade values (integers and decimals); assert rendered string matches `value.toFixed(2)`.
  Tag: `Feature: application-grades-display, Property 3: present grade values render to two decimal places`

- Property 4 — generate grades objects of varying content; assert all five card elements share identical CSS class sets.
  Tag: `Feature: application-grades-display, Property 4: all five grade cards share the same CSS classes`

- Property 5 — generate grades objects; for each rendered card assert label node precedes value node in the DOM.
  Tag: `Feature: application-grades-display, Property 5: grade label precedes grade value in each card`

**Non-regression**: Properties 1 and 4 implicitly cover Requirement 3.1 — if all five cards render with correct labels and consistent styling, the original three are also correct.
