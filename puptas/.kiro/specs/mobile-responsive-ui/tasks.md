# Implementation Plan: Mobile-Responsive UI

## Overview

Implement full mobile responsiveness across the PUP Admissions Portal using Tailwind CSS responsive utilities. Tasks are ordered by dependency: layouts and sidebar first, then shared components, then pages, then Blade templates, then tests.

## Tasks

- [x] 1. Make AppLayout.vue and role layouts responsive
  - Add `isMobileSidebarOpen` ref (default `false`)
  - Add hamburger button (`md:hidden`, `min-h-[44px] min-w-[44px]`) in the header with `aria-label="Open navigation menu"`
  - Replace inline `margin-left` style with `md:ml-[var(--sidebar-width,5rem)]` and `ml-0` as mobile base
  - Pass `isMobileSidebarOpen` as prop and `@close` handler to `Sidebar`
  - Add `watchEffect` to toggle `overflow-hidden` on `<body>` when sidebar is open
  - Apply same changes to `ApplicantLayout.vue` and all other role layout files
  - _Requirements: 1.1, 1.2, 1.5, 2.1, 2.6, 3.4_

  - [x] 1.1 Write property test for main content left margin on mobile
    - **Property 2: Main content left margin is zero on mobile**
    - **Validates: Requirements 1.2**

  - [x] 1.2 Write property test for hamburger button visibility
    - **Property 4: Hamburger button visibility is breakpoint-gated**
    - **Validates: Requirements 2.1, 2.6**

  - [x] 1.3 Write property test for body scroll lock
    - **Property 6: Body scroll is locked when sidebar is open on mobile**
    - **Validates: Requirements 3.4**

  - [x] 1.4 Write property test for hamburger touch target size
    - **Property 7: Hamburger button meets touch target size**
    - **Validates: Requirements 2.5**

- [x] 2. Make Sidebar.vue responsive with mobile overlay drawer
  - Accept `isMobileOpen` Boolean prop (default `false`)
  - Add `defineEmits<{ close: [] }>()`
  - Add semi-transparent backdrop div (`md:hidden`, `fixed inset-0 z-[9998] bg-black/50`) that emits `close` on click
  - Add `role="dialog"` and `aria-modal="true"` to sidebar root when `isMobileOpen` is true
  - Add close button inside sidebar with `aria-label="Close navigation menu"` (`md:hidden`)
  - Apply transition classes: `transition-transform duration-300`, `translate-x-0` when open, `-translate-x-full md:translate-x-0` when closed
  - Set `z-[9999]` on sidebar root; keep existing hover-to-expand desktop behavior unchanged
  - Add `min-h-[44px]` to all `.nav-item` elements
  - Emit `close` when a nav link is clicked on mobile
  - _Requirements: 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.5, 7.3, 7.5_

  - [x] 2.1 Write property test for sidebar overlay on mobile
    - **Property 3: Sidebar is an overlay on mobile**
    - **Validates: Requirements 1.5, 3.3**

  - [x] 2.2 Write property test for sidebar open/close round trip
    - **Property 5: Sidebar open/close round trip**
    - **Validates: Requirements 2.2, 2.3, 2.4, 3.1**

  - [x] 2.3 Write property test for main content no fixed pixel width
    - **Property 1: Main content has no fixed pixel width on mobile**
    - **Validates: Requirements 1.1**

- [x] 3. Checkpoint — Ensure layout and sidebar tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Make modal components scrollable on mobile
  - In `ApplicationReviewModal.vue`: add `overflow-y-auto max-h-[90vh]` to the modal content container; ensure `p-4` on the modal wrapper
  - In `ConfirmationModal.vue`: apply the same `overflow-y-auto max-h-[90vh]` and `p-4` wrapper padding
  - Apply to any other modal components found in `resources/js/`
  - _Requirements: 11.4_

  - [x] 4.1 Write property test for modal scrollability
    - **Property 24: Modal components are scrollable on mobile**
    - **Validates: Requirements 11.4**

- [x] 5. Make ProgramRequirements.vue responsive
  - Wrap any `<table>` elements in `<div class="overflow-x-auto">`
  - Add `w-full` to the component root element
  - Apply responsive text sizing to headings and body text
  - _Requirements: 8.1, 11.2, 11.5_

  - [x] 5.1 Write property test for table overflow-x-auto wrapping
    - **Property 22: Blade template tables are wrapped in overflow-x-auto**
    - **Validates: Requirements 10.3, 11.2**

- [x] 6. Make form components responsive (ScheduleForm, AddUser, EditUser)
  - In `ScheduleForm.vue`: apply `flex flex-col md:flex-row gap-4` to multi-field rows; add `w-full` to all `<input>`, `<select>`, `<textarea>` elements; ensure labels are `block`
  - In `AddUser.vue` and `EditUser.vue`: apply `w-full` to all inputs; stack multi-column rows with `flex-col md:flex-row`
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

  - [x] 6.1 Write property test for form inputs fill container width
    - **Property 13: Form inputs fill container width**
    - **Validates: Requirements 5.5, 6.2**

  - [x] 6.2 Write property test for multi-field form rows stack on mobile
    - **Property 14: Multi-field form rows stack on mobile**
    - **Validates: Requirements 6.1, 6.3**

- [x] 7. Make Grade Input pages responsive (ICTGradeInput, STEMGradeInput, and other strand pages)
  - Change summary cards grid from `grid-cols-1 md:grid-cols-4` to `grid-cols-2 md:grid-cols-4`
  - Ensure all subject input grids use `grid-cols-1 md:grid-cols-2`
  - Add `w-full` to all grade `<input>` elements
  - Add `flex-wrap` and `text-xs sm:text-sm` to the progress step indicator; use `hidden xs:inline` for long labels
  - Add `break-words` to validation error message elements
  - Apply same changes to all strand-specific grade input pages
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

  - [x] 7.1 Write property test for grade input grids single-column on mobile
    - **Property 8: Grade input grids are single-column on mobile**
    - **Validates: Requirements 4.1, 4.2, 4.5**

  - [x] 7.2 Write property test for grade input fields fill container width
    - **Property 9: Grade input fields fill container width**
    - **Validates: Requirements 4.4**

  - [x] 7.3 Write property test for step indicator no overflow on small viewports
    - **Property 10: Step indicator does not overflow on small viewports**
    - **Validates: Requirements 4.3**

  - [x] 7.4 Write property test for multi-step workflows render all steps on mobile
    - **Property 23: Multi-step workflows render all steps on mobile**
    - **Validates: Requirements 11.3**

- [x] 8. Make Dashboard pages responsive (Admin, Applicant, and other role dashboards)
  - Ensure stats grid uses `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`
  - Ensure main content grid uses `grid-cols-1 lg:grid-cols-3`
  - Add `flex-wrap` to applicant list item status badge rows
  - Change chart container height to `h-64 md:h-80`; add `w-full`; confirm `maintainAspectRatio: false`
  - Add `w-full` to the search input
  - Apply same changes to all role-specific dashboard pages
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

  - [x] 8.1 Write property test for dashboard stats grid responsive classes
    - **Property 11: Dashboard stats grid uses responsive column classes**
    - **Validates: Requirements 5.1**

  - [x] 8.2 Write property test for dashboard main grid stacks below 1024px
    - **Property 12: Dashboard main grid stacks below 1024px**
    - **Validates: Requirements 5.2**

- [x] 9. Checkpoint — Ensure all Vue component and page tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Apply responsive typography across Vue components
  - Add `text-xl md:text-2xl lg:text-3xl` (or equivalent) to all `<h1>` elements in Vue components
  - Ensure all body and label text uses at minimum `text-sm`
  - Remove any inline `style` attributes containing `font-size` with pixel values
  - Add `break-words` to elements that may contain long strings (email addresses, names)
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

  - [x] 10.1 Write property test for headings use responsive text scale classes
    - **Property 17: Headings use responsive text scale classes**
    - **Validates: Requirements 8.1, 8.2**

  - [x] 10.2 Write property test for no fixed pixel font sizes in inline styles
    - **Property 19: No fixed pixel font sizes in inline styles**
    - **Validates: Requirements 8.4**

  - [x] 10.3 Write property test for long strings do not overflow on small viewports
    - **Property 20: Long strings do not overflow on small viewports**
    - **Validates: Requirements 8.5**

- [x] 11. Apply touch target sizing across Vue components
  - Add `min-h-[44px]` to all button elements in Vue components
  - Add `w-full` to all primary action buttons on mobile (use `sm:w-auto` to revert on larger screens)
  - Ensure 8px minimum gap between adjacent touch targets
  - _Requirements: 7.1, 7.2, 7.4_

  - [x] 11.1 Write property test for all interactive elements meet 44px touch target height
    - **Property 15: All interactive elements meet 44px touch target height**
    - **Validates: Requirements 7.1, 7.3, 7.5**

  - [x] 11.2 Write property test for touch targets are spaced at least 8px apart on mobile
    - **Property 16: Touch targets are spaced at least 8px apart on mobile**
    - **Validates: Requirements 7.4**

- [x] 12. Apply cross-browser compatibility fixes in Vue components
  - Audit all `position: sticky` elements; ensure no ancestor has `overflow: hidden`
  - Ensure all `backdrop-blur` elements also carry a solid background color class (e.g., `bg-white/80`)
  - _Requirements: 12.3, 12.4_

  - [x] 12.1 Write property test for sticky elements have no overflow-hidden ancestors
    - **Property 25: Sticky elements have no overflow-hidden ancestors**
    - **Validates: Requirements 12.3**

  - [x] 12.2 Write property test for backdrop-blur elements have solid fallback background
    - **Property 26: backdrop-blur elements have solid fallback background**
    - **Validates: Requirements 12.4**

- [x] 13. Update Sienna widget Blade partial (sienna-widget.blade.php)
  - Add `max-width: 100vw; overflow-x: hidden;` CSS override to `.asw-menu`
  - Add `bottom: 80px` override for the trigger button on viewports below 480px
  - Wrap the widget `<script>` and `<style>` block in `<div class="overflow-x-auto">`
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

  - [x] 13.1 Write property test for Sienna widget panel does not exceed viewport width
    - **Property 21: Sienna widget panel does not exceed viewport width**
    - **Validates: Requirements 9.1, 9.2**

- [x] 14. Update SAR Blade template (sar/template.blade.php)
  - Wrap the `.page` div in `<div style="overflow-x: auto;">`
  - _Requirements: 10.2, 10.3_

- [x] 15. Update email Blade templates (emails/*.blade.php)
  - Add `style="max-width: 600px; width: 100%; margin: 0 auto;"` to the outermost container div in each email template
  - _Requirements: 10.4_

  - [x] 15.1 Write property test for Blade template tables are wrapped in overflow-x-auto
    - **Property 22: Blade template tables are wrapped in overflow-x-auto**
    - **Validates: Requirements 10.3, 11.2**

- [x] 16. Verify app.blade.php viewport meta tag is preserved
  - Confirm `<meta name="viewport" content="width=device-width, initial-scale=1">` is present and unmodified
  - _Requirements: 1.3_

- [x] 17. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Property tests use **fast-check** (TypeScript) for Vue component tests; run with `vitest --run`
- Each property test runs a minimum of 100 iterations
- Tag format for each test: `// Feature: mobile-responsive-ui, Property N: <property_text>`
- Desktop behavior is unchanged — all changes are additive Tailwind class adjustments
- Sidebar hover-to-expand desktop behavior must remain intact throughout implementation
