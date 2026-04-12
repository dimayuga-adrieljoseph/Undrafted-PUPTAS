# Design Document: Grade Input UI Redesign

## Overview

This is a pure template restructure across 6 strand-specific Vue pages (STEM, ABM, GAS, HUMSS, ICT, TVL). The goal is to replace the two separate "Grade 11 Subjects" and "Grade 12 Subjects" card containers with a single unified "Core Subjects" card that organizes inputs by subject category (Math, English, Science), with G11/G12 level badges distinguishing grade levels within each category. A new "Other Subjects" card is added below for supplemental manual entries, backed by a local `otherSubjects` reactive array that is completely isolated from the existing `form` object.

No computed properties, form submission logic, v-model bindings, validation rules, Inertia.js props, or `<script setup>` code are modified in any way.

## Architecture

The change is entirely within the `<template>` block of each Vue SFC. The `<script setup>` section is untouched.

```
Before (per page):
  <form>
    [AI Banner]
    <div> <!-- Grade 11 Subjects card -->
      Math sub-section (G11 fields)
      English sub-section (G11 fields)
      Science sub-section (G11 fields)
    </div>
    <div> <!-- Grade 12 Subjects card -->
      Math sub-section (G12 fields)
      English sub-section (G12 fields)
      Science sub-section (G12 fields)
    </div>
    [Submit button]
  </form>

After (per page):
  <form>
    [AI Banner]
    <div> <!-- Core Subjects card -->
      Math sub-section
        [G11 badge] G11 math fields + math average badge
        [G12 badge] G12 math fields
      English sub-section
        [G11 badge] G11 english fields + english average badge
        [G12 badge] G12 english fields
      Science sub-section
        [G11 badge] G11 science fields + science average badge
        [G12 badge] G12 science fields
    </div>
    <div> <!-- Other Subjects card -->
      v-for row in otherSubjects: [name input] [grade input] [Remove btn]
      [Add Subject btn]
    </div>
    [Submit button]
  </form>
```

The `otherSubjects` reactive ref is added to `<script setup>` as the only script-level change:

```js
const otherSubjects = ref([{ name: '', grade: null }])
```

## Components and Interfaces

### Core Subjects Container

A single card replacing the two existing grade-level cards. Structure per page:

- Card header: "Core Subjects" (replaces "Grade 11 Subjects" / "Grade 12 Subjects")
- Three sub-sections inside the card body: Math, English, Science
- Each sub-section contains two Subject Groups:
  - G11 group: existing G11 fields, unchanged, preceded by a G11 level badge
  - G12 group: existing G12 fields, unchanged, preceded by a G12 level badge
- Average badges (mathAverage, englishAverage, scienceAverage) remain attached to their respective G11 group, as they already aggregate across both years via existing computed properties

### Level Badge

A purely decorative inline pill element. No event handlers, no data bindings.

```html
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#9E122C] text-white mr-2">G11</span>
```

The G12 badge uses a neutral variant to visually distinguish it:

```html
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-500 text-white mr-2">G12</span>
```

### Other Subjects Container

A new card below Core Subjects. Backed exclusively by the local `otherSubjects` ref.

- Renders one row per entry via `v-for="(subject, index) in otherSubjects"`
- Each row: text input bound to `subject.name`, number input bound to `subject.grade`, Remove button
- Remove button is disabled (or hidden) when `otherSubjects.length === 1` to prevent empty state
- "Add Subject" button appends `{ name: '', grade: null }` to the array
- No connection to `form`, no validation, no submission side effects

### Subject Group Header Pattern

Each subject group within a category uses the existing `<h3>` pattern with the level badge prepended:

```html
<h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
  <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#9E122C] text-white mr-2">G11</span>
  Math-Related Subjects
</h3>
```

## Data Models

### Existing (unchanged)

The `form` object from `useForm()` retains all existing keys. No keys are added, removed, or renamed. All `v-model` bindings remain identical.

### New: otherSubjects

```ts
// Added to <script setup> only — no other script changes
const otherSubjects = ref<Array<{ name: string; grade: number | null }>>([
  { name: '', grade: null }
])
```

This array is local state only. It is never read by any computed property, never included in form submission, and never validated.

## Per-Page Subject Inventory

Each page has a different set of subjects. The restructure must correctly assign each existing field to its G11 or G12 group within the right category. The mapping is:

### STEM
- G11 Math: General Mathematics, Statistics and Probability, Pre-Calculus, Basic Calculus
- G11 Science: Earth Science, General Chemistry 1
- G11 English: Oral Communication in Context, Reading and Writing Skills
- G12 Math: 4 dynamic subjects (g12_math_subject_1..4 / g12_math_grade_1..4)
- G12 Science: General Physics 1, General Biology 1, General Physics 2, General Biology 2, General Chemistry 2 (fixed labels, g12_science_grade_1..5)
- G12 English: 21st Century Literature, Komunikasyon (fixed labels, g12_english_grade_1..2)

### ABM
- G11 Math: General Mathematics, Business Mathematics, Statistics and Probability
- G11 English: Oral Communication, English for Academic Purposes, Reading and Writing
- G11 Science: Earth and Life Science, Physical Science
- G12 Math: 3 dynamic subjects (g12_math_subject_1..3 / g12_math_grade_1..3)
- G12 Science: 2 dynamic subjects (g12_science_subject_1..2 / g12_science_grade_1..2)
- G12 English: 4 dynamic subjects (g12_english_subject_1..4 / g12_english_grade_1..4)

### GAS
- G11 Math: General Mathematics, Statistics and Probability
- G11 English: Oral Communication, 21st Century Literature, English for Academic Purposes, Reading and Writing
- G11 Science: Earth and Life Science, Physical Science
- G12 Math: 2 dynamic subjects
- G12 Science: 2 dynamic subjects
- G12 English: 4 dynamic subjects

### HUMSS
- G11 Math: General Mathematics, Statistics and Probability
- G11 English: Oral Communication in Context, 21st Century Literature, English for Academic Purposes, Reading and Writing Skills
- G11 Science: Earth and Life Science
- G12 Math: 2 dynamic subjects
- G12 Science: 2 dynamic subjects
- G12 English: 4 dynamic subjects

### ICT
- G11 Math: General Mathematics, Statistics and Probability
- G11 English: Oral Communication, 21st Century Literature, English for Academic Purposes, Reading and Writing
- G11 Science: Earth and Life Science, Physical Science
- G12 Math: 2 dynamic subjects
- G12 Science: 2 dynamic subjects
- G12 English: 4 dynamic subjects

### TVL
- G11 Math: General Mathematics, Statistics and Probability
- G11 Science: 2 dynamic subjects (g11_science_subject_1..2 / g11_science_grade_1..2)
- G11 English: Oral Communication in Context, Reading and Writing Skills, 21st Century Literature
- G12 Math: 2 dynamic subjects
- G12 Science: 2 dynamic subjects
- G12 English: 3 dynamic subjects

## Error Handling

This feature introduces no new error states. The `otherSubjects` array operations (add/remove) are synchronous in-memory mutations with no failure modes. The Remove button guard (`otherSubjects.length > 1`) prevents the array from reaching an empty state.

## Testing Strategy

PBT is not applicable to this feature. This is a pure UI template restructure — there is no algorithmic logic, no data transformation, and no universal properties that would benefit from randomized input generation. All testable criteria are structural/regression checks best covered by example-based component tests.

### Unit / Component Tests (Vitest + Vue Test Utils)

For each of the 6 pages, verify:

1. A single container with heading "Core Subjects" is rendered; no "Grade 11 Subjects" or "Grade 12 Subjects" headings exist.
2. Three sub-section headings (Math, English, Science) exist inside the Core Subjects container.
3. A "G11" badge and a "G12" badge are rendered within each category sub-section.
4. All existing `v-model` binding keys are present on their respective inputs (regression check).
5. The "Other Subjects" container is rendered below Core Subjects.
6. On mount, `otherSubjects` has exactly one row rendered.
7. Clicking "Add Subject" increases the rendered row count by 1.
8. Clicking "Remove" on a row (when more than one exists) decreases the rendered row count by 1.
9. The Remove button is disabled/hidden when only one row remains.
10. The `form` object is not mutated by any `otherSubjects` operation.

### Manual Smoke Tests

- Verify the page renders without console errors on all 6 strands.
- Verify AI autofill still populates fields correctly after the restructure.
- Verify form submission still works end-to-end.
- Verify responsive layout collapses to single column on mobile viewport.
- Verify dark mode renders correctly for all new elements.
