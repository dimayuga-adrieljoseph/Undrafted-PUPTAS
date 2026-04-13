# Implementation Plan: Application Grades Display

## Overview

Pure frontend change to `Pages/Applications/Index.vue` — expand the Grades Section from 3 cards to 5 cards, add the two missing grade fields (`g12_first_sem`, `g12_second_sem`), apply responsive grid layout, and format numeric values to two decimal places.

## Tasks

- [x] 1. Update the Grades Section template in `Index.vue`
  - [x] 1.1 Add a `formatGrade` helper function in the `<script setup>` block
    - Accepts a raw grade value (number | string | null | undefined)
    - Returns `value.toFixed(2)` for valid numerics, `"—"` for null/undefined/NaN
    - _Requirements: 1.3, 1.4_

  - [x] 1.2 Write property test for `formatGrade` — Property 3: present grade values render to two decimal places
    - **Property 3: Present grade values render to two decimal places**
    - **Validates: Requirements 1.4**
    - Use `@fast-check/vitest`; generate random integers and decimals, assert output matches `value.toFixed(2)`

  - [x] 1.3 Write property test for `formatGrade` — Property 2: null or absent grade values render as "—"
    - **Property 2: Null or absent grade values render as "—"**
    - **Validates: Requirements 1.3, 3.2**
    - Generate arbitrary subsets of null/undefined inputs; assert return value is `"—"` and no error is thrown

  - [x] 1.4 Replace the existing 3-card grid markup with a 5-card responsive grid
    - Change grid container to `grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3`
    - Keep existing `mathematics`, `science`, `english` cards; update their values to use `formatGrade()`
    - Add two new cards: "Grade 12 – 1st Semester" (`g12_first_sem`) and "Grade 12 – 2nd Semester" (`g12_second_sem`)
    - All five cards use identical classes: `p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center`
    - Label (`<p class="text-xs ...">`) appears before value (`<p class="text-lg ...">`) in each card
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 2.3, 3.1_

  - [x] 1.5 Write property test for rendered Grades Section — Property 1: all five grade fields are rendered
    - **Property 1: All five grade fields are rendered**
    - **Validates: Requirements 1.1**
    - Mount the Grades Section with generated grades objects; assert all five label strings appear in the output

  - [x] 1.6 Write property test for rendered Grades Section — Property 4: all five grade cards share the same CSS classes
    - **Property 4: All five grade cards share the same CSS classes**
    - **Validates: Requirements 2.1**
    - Assert every card element has identical class sets across varied grades inputs

  - [x] 1.7 Write property test for rendered Grades Section — Property 5: grade label precedes grade value in each card
    - **Property 5: Grade label precedes grade value in each card**
    - **Validates: Requirements 2.3**
    - For each rendered card assert the label node appears before the value node in the DOM

  - [x] 1.8 Write unit tests for the Grades Section
    - Render with a complete grades object → assert all five label strings and formatted values appear
    - Render with `grades = null` → assert all five cards show `"—"` with no JS error
    - Render with three fields null, two present → assert correct mix of values and dashes
    - Assert responsive grid classes are present on the container
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 3.1, 3.2_

- [x] 2. Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- `formatGrade` can be a plain function in `<script setup>` — no composable needed
- No backend, route, or model changes are required; `selectedUser.grades` already contains all five fields
- Property tests use `@fast-check/vitest` consistent with the project's existing test suite pattern
