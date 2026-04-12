# Implementation Plan: Grade Input UI Redesign

## Overview

Pure template restructure across 6 strand-specific Vue pages. Each page gets: (1) `otherSubjects` ref added to `<script setup>`, (2) the two separate G11/G12 cards merged into a single "Core Subjects" card with G11/G12 level badges per category, and (3) a new "Other Subjects" card below. No logic, v-model bindings, computed properties, or form submission code is changed.

## Tasks

- [x] 1. Restructure STEMGradeInput.vue
  - [x] 1.1 Add `otherSubjects` ref to `<script setup>`
    - Insert `const otherSubjects = ref([{ name: '', grade: null }])` after existing refs
    - _Requirements: 3.3_
  - [x] 1.2 Replace G11/G12 cards with unified Core Subjects card
    - Remove the separate "Grade 11 Subjects" and "Grade 12 Subjects" `<div>` containers
    - Add a single card with header "Core Subjects"
    - Inside: Math sub-section with G11 badge (General Mathematics, Statistics and Probability, Pre-Calculus, Basic Calculus) then G12 badge (4 dynamic math subjects via v-for)
    - Inside: Science sub-section with G11 badge (Earth Science, General Chemistry 1) then G12 badge (General Physics 1, General Biology 1, General Physics 2, General Biology 2, General Chemistry 2 — fixed labels)
    - Inside: English sub-section with G11 badge (Oral Communication in Context, Reading and Writing Skills) then G12 badge (21st Century Literature, Komunikasyon — fixed labels)
    - Preserve all existing v-model bindings, :class, AI confidence indicators, and average badges
    - Use level badge pattern from design.md: red pill for G11, gray pill for G12
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 2.1, 2.2, 2.3, 2.4_
  - [x] 1.3 Add Other Subjects card below Core Subjects
    - Add new card with header "Other Subjects"
    - Render `v-for="(subject, index) in otherSubjects"` rows, each with a text input bound to `subject.name` and a number input (type=number, min=0, max=100, step=0.01) bound to `subject.grade`
    - Add Remove button per row, disabled/hidden when `otherSubjects.length === 1`
    - Add "Add Subject" button that pushes `{ name: '', grade: null }` to `otherSubjects`
    - _Requirements: 3.1, 3.2, 3.4, 3.5, 3.6, 3.7_

- [x] 2. Restructure ABMGradeInput.vue
  - [x] 2.1 Add `otherSubjects` ref to `<script setup>`
    - Insert `const otherSubjects = ref([{ name: '', grade: null }])` after existing refs
    - _Requirements: 3.3_
  - [x] 2.2 Replace G11/G12 cards with unified Core Subjects card
    - Remove the separate "Grade 11 Subjects" and "Grade 12 Subjects" containers
    - Add single "Core Subjects" card
    - Math sub-section: G11 badge (General Mathematics, Business Mathematics, Statistics and Probability) then G12 badge (3 dynamic math subjects)
    - English sub-section: G11 badge (Oral Communication, English for Academic Purposes, Reading and Writing) then G12 badge (4 dynamic english subjects)
    - Science sub-section: G11 badge (Earth and Life Science, Physical Science) then G12 badge (2 dynamic science subjects)
    - Preserve all existing v-model bindings, required attributes, :class, AI confidence indicators, and average badges
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 2.1, 2.2, 2.3, 2.4_
  - [x] 2.3 Add Other Subjects card below Core Subjects
    - Same pattern as task 1.3
    - _Requirements: 3.1, 3.2, 3.4, 3.5, 3.6, 3.7_

- [x] 3. Restructure GASGradeInput.vue
  - [x] 3.1 Add `otherSubjects` ref to `<script setup>`
    - Insert `const otherSubjects = ref([{ name: '', grade: null }])` after existing refs
    - _Requirements: 3.3_
  - [x] 3.2 Replace G11/G12 cards with unified Core Subjects card
    - Remove the separate "Grade 11 Subjects" and "Grade 12 Subjects" containers
    - Add single "Core Subjects" card
    - Math sub-section: G11 badge (General Mathematics, Statistics and Probability) then G12 badge (2 dynamic math subjects)
    - English sub-section: G11 badge (Oral Communication, 21st Century Literature, English for Academic Purposes, Reading and Writing) then G12 badge (4 dynamic english subjects)
    - Science sub-section: G11 badge (Earth and Life Science, Physical Science) then G12 badge (2 dynamic science subjects)
    - Preserve all existing v-model bindings, :class, AI confidence indicators, and average badges
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 2.1, 2.2, 2.3, 2.4_
  - [x] 3.3 Add Other Subjects card below Core Subjects
    - Same pattern as task 1.3
    - _Requirements: 3.1, 3.2, 3.4, 3.5, 3.6, 3.7_

- [x] 4. Restructure HUMSSGradeInput.vue
  - [x] 4.1 Add `otherSubjects` ref to `<script setup>`
    - Insert `const otherSubjects = ref([{ name: '', grade: null }])` after existing refs
    - _Requirements: 3.3_
  - [x] 4.2 Replace G11/G12 cards with unified Core Subjects card
    - Remove the separate "Grade 11 Subjects" and "Grade 12 Subjects" containers
    - Add single "Core Subjects" card
    - Math sub-section: G11 badge (General Mathematics, Statistics and Probability) then G12 badge (2 dynamic math subjects)
    - English sub-section: G11 badge (Oral Communication in Context, 21st Century Literature, English for Academic Purposes, Reading and Writing Skills) then G12 badge (4 dynamic english subjects)
    - Science sub-section: G11 badge (Earth and Life Science) then G12 badge (2 dynamic science subjects)
    - Preserve all existing v-model bindings, :class, AI confidence indicators, and average badges
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 2.1, 2.2, 2.3, 2.4_
  - [x] 4.3 Add Other Subjects card below Core Subjects
    - Same pattern as task 1.3
    - _Requirements: 3.1, 3.2, 3.4, 3.5, 3.6, 3.7_

- [x] 5. Restructure ICTGradeInput.vue
  - [x] 5.1 Add `otherSubjects` ref to `<script setup>`
    - Insert `const otherSubjects = ref([{ name: '', grade: null }])` after existing refs
    - _Requirements: 3.3_
  - [x] 5.2 Replace G11/G12 cards with unified Core Subjects card
    - Remove the separate "Grade 11 Subjects" and "Grade 12 Subjects" containers
    - Add single "Core Subjects" card
    - Math sub-section: G11 badge (General Mathematics, Statistics and Probability) then G12 badge (2 dynamic math subjects)
    - English sub-section: G11 badge (Oral Communication, 21st Century Literature, English for Academic Purposes, Reading and Writing) then G12 badge (4 dynamic english subjects)
    - Science sub-section: G11 badge (Earth and Life Science, Physical Science) then G12 badge (2 dynamic science subjects)
    - Preserve all existing v-model bindings, :class, AI confidence indicators, and average badges
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 2.1, 2.2, 2.3, 2.4_
  - [x] 5.3 Add Other Subjects card below Core Subjects
    - Same pattern as task 1.3
    - _Requirements: 3.1, 3.2, 3.4, 3.5, 3.6, 3.7_

- [x] 6. Restructure TVLGradeInput.vue
  - [x] 6.1 Add `otherSubjects` ref to `<script setup>`
    - Insert `const otherSubjects = ref([{ name: '', grade: null }])` after existing refs
    - _Requirements: 3.3_
  - [x] 6.2 Replace G11/G12 cards with unified Core Subjects card
    - Remove the separate "Grade 11 Subjects" and "Grade 12 Subjects" containers
    - Add single "Core Subjects" card
    - Math sub-section: G11 badge (General Mathematics, Statistics and Probability) then G12 badge (2 dynamic math subjects)
    - Science sub-section: G11 badge (2 dynamic science subjects via g11_science_subject_1..2) then G12 badge (2 dynamic science subjects)
    - English sub-section: G11 badge (Oral Communication in Context, Reading and Writing Skills, 21st Century Literature) then G12 badge (3 dynamic english subjects)
    - Preserve all existing v-model bindings, :class, AI confidence indicators, and average badges
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 2.1, 2.2, 2.3, 2.4_
  - [x] 6.3 Add Other Subjects card below Core Subjects
    - Same pattern as task 1.3
    - _Requirements: 3.1, 3.2, 3.4, 3.5, 3.6, 3.7_

- [x] 7. Final checkpoint — verify all 6 pages
  - Ensure all 6 pages render without console errors
  - Confirm no "Grade 11 Subjects" or "Grade 12 Subjects" headings remain
  - Confirm G11 and G12 badges appear in each category sub-section
  - Confirm Other Subjects add/remove works on each page
  - Confirm existing form submission and AI autofill are unaffected
  - Ask the user if any questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- The design has no Correctness Properties section — this is a pure template restructure, so no property-based tests are included
- Each task (1–6) is self-contained and can be executed one file at a time
- The level badge HTML patterns are defined in design.md: red pill (`bg-[#9E122C]`) for G11, gray pill (`bg-gray-500`) for G12
- Average badges (mathAverage, englishAverage, scienceAverage) stay attached to the G11 group header within each category — they already aggregate across both years via existing computed properties
- `otherSubjects` is local state only — never read by any computed property, never submitted with the form
