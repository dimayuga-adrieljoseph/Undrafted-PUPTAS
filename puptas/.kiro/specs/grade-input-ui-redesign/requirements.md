# Requirements Document

## Introduction

This feature redesigns the Grade Input Page UI across all 6 strand pages (STEM, ABM, GAS, HUMSS, ICT, TVL). The goal is to improve layout clarity and organization by merging the separate Grade 11 and Grade 12 containers into a unified "Core Subjects" container with G11/G12 level badges, and adding a new "Other Subjects" container for manual supplemental entries. No existing logic, validation, data bindings, computed properties, or backend interactions are modified.

## Glossary

- **Grade_Input_Page**: Any of the 6 strand-specific Vue pages (STEMGradeInput.vue, ABMGradeInput.vue, GASGradeInput.vue, HUMSSGradeInput.vue, ICTGradeInput.vue, TVLGradeInput.vue).
- **Core_Subjects_Container**: The unified card/section that replaces the separate "Grade 11 Subjects" and "Grade 12 Subjects" containers.
- **Other_Subjects_Container**: A new additive UI section for manually entering supplemental subject name and grade pairs.
- **Level_Badge**: A small inline visual label ("G11" or "G12") applied to subject groups — purely decorative, no logic.
- **Subject_Group**: A set of subject inputs (fixed or dynamic) belonging to one category (Math, English, Science) and one grade level.
- **otherSubjects**: A local reactive array of `{ name: '', grade: null }` objects used exclusively by the Other_Subjects_Container.
- **AI_Confidence_Indicator**: The existing `isLowConfidence` / `getConfidence` display logic applied per field.
- **Average_Badge**: The existing computed average display (mathAverage, englishAverage, scienceAverage) shown per category.

---

## Requirements

### Requirement 1: Unified Core Subjects Container

**User Story:** As an applicant, I want to see all my Grade 11 and Grade 12 subjects organized together by subject category, so that I can review and enter grades in a cleaner, less fragmented layout.

#### Acceptance Criteria

1. THE Grade_Input_Page SHALL replace the separate "Grade 11 Subjects" and "Grade 12 Subjects" containers with a single Core_Subjects_Container titled "Core Subjects".
2. THE Core_Subjects_Container SHALL contain three sub-sections: Math, English, and Science.
3. WHEN rendering the Math sub-section, THE Core_Subjects_Container SHALL display all G11 math Subject_Groups followed by all G12 math Subject_Groups within the same section.
4. WHEN rendering the English sub-section, THE Core_Subjects_Container SHALL display all G11 english Subject_Groups followed by all G12 english Subject_Groups within the same section.
5. WHEN rendering the Science sub-section, THE Core_Subjects_Container SHALL display all G11 science Subject_Groups followed by all G12 science Subject_Groups within the same section.
6. THE Core_Subjects_Container SHALL preserve all existing v-model bindings, type, min, max, step, :class, placeholder, required, and id attributes on every input field without modification.
7. THE Core_Subjects_Container SHALL preserve all existing AI_Confidence_Indicator markup (isLowConfidence, getConfidence) on every field without modification.
8. THE Core_Subjects_Container SHALL preserve all existing Average_Badge displays (mathAverage, englishAverage, scienceAverage) per category section.

### Requirement 2: Grade Level Badges

**User Story:** As an applicant, I want each subject group to be clearly labeled with its grade level, so that I can distinguish G11 subjects from G12 subjects within the unified container.

#### Acceptance Criteria

1. THE Grade_Input_Page SHALL render a Level_Badge labeled "G11" adjacent to each G11 Subject_Group header within the Core_Subjects_Container.
2. THE Grade_Input_Page SHALL render a Level_Badge labeled "G12" adjacent to each G12 Subject_Group header within the Core_Subjects_Container.
3. THE Level_Badge SHALL be a purely visual element with no associated logic, event handlers, or data bindings.
4. THE Level_Badge SHALL use a small pill/badge style consistent with the existing color scheme (accent color #9E122C or a neutral variant).

### Requirement 3: Other Subjects Container

**User Story:** As an applicant, I want a dedicated section to manually add supplemental subjects not covered by the core categories, so that I can provide a more complete academic record.

#### Acceptance Criteria

1. THE Grade_Input_Page SHALL render an Other_Subjects_Container titled "Other Subjects" below the Core_Subjects_Container.
2. THE Other_Subjects_Container SHALL display one row per entry in the local `otherSubjects` reactive array, each row containing a text input for subject name and a numeric input for grade.
3. THE Grade_Input_Page SHALL initialize `otherSubjects` as a reactive array with one default entry `{ name: '', grade: null }`.
4. WHEN the user clicks "Add Subject", THE Other_Subjects_Container SHALL append a new `{ name: '', grade: null }` entry to `otherSubjects`.
5. WHEN the user clicks "Remove" on a row and `otherSubjects` contains more than one entry, THE Other_Subjects_Container SHALL remove that entry from `otherSubjects`.
6. THE Other_Subjects_Container grade input SHALL use type="number", min="0", max="100", step="0.01" attributes matching the style of existing grade inputs.
7. THE Other_Subjects_Container SHALL NOT introduce any new form bindings to the existing `form` object or affect any existing validation, submission, or computed logic.

### Requirement 4: Visual Consistency and Responsive Layout

**User Story:** As an applicant, I want the redesigned page to look and behave consistently with the rest of the application, so that the experience feels cohesive on all screen sizes.

#### Acceptance Criteria

1. THE Grade_Input_Page SHALL maintain the existing card style (bg-white dark:bg-gray-800, rounded-xl, shadow-lg, border) for the Core_Subjects_Container and Other_Subjects_Container.
2. THE Grade_Input_Page SHALL maintain the existing two-column responsive grid (grid-cols-1 md:grid-cols-2) for subject input fields.
3. THE Grade_Input_Page SHALL preserve the existing header section, progress steps indicator, AI autofill banner, and form submit button without modification.
4. THE Grade_Input_Page SHALL use the existing accent color #9E122C for section header decorators and interactive elements.
5. WHILE rendered on a mobile viewport, THE Grade_Input_Page SHALL stack all input columns to a single column layout using the existing responsive Tailwind classes.
6. THE Other_Subjects_Container input fields SHALL use the same Tailwind CSS classes as existing grade input fields for visual consistency.

### Requirement 5: No Logic or Binding Regression

**User Story:** As a developer, I want the UI refactor to be purely structural, so that no existing functionality, validation, or data flow is broken.

#### Acceptance Criteria

1. THE Grade_Input_Page SHALL NOT modify any computed properties (mathAverage, englishAverage, scienceAverage, or any eligibility computations).
2. THE Grade_Input_Page SHALL NOT modify any form submission handlers, API calls, or Inertia.js props.
3. THE Grade_Input_Page SHALL NOT rename, remove, or alter any existing v-model binding keys on the `form` object.
4. THE Grade_Input_Page SHALL NOT alter any existing validation rules or required attributes on existing fields.
5. IF the `otherSubjects` array is modified by the user, THEN THE Grade_Input_Page SHALL NOT propagate those changes to the `form` object or trigger any existing form validation.
6. THE Grade_Input_Page SHALL preserve all existing `<script setup>` logic, imports, defineProps, useForm, and lifecycle hooks without modification.
